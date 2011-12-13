<?php

/**
 * sfMemcached cache with "cache info" stored in a redis database
 * 
 * provides symfony a memcached cache but the evil "storeCacheInfo" is done in redis
 *
 * in case of cache hit in a symfony prod env, there is no communication with redis server
 * 
 * @version   $Id$
 * @author    Benjamin Viellard <gbicou@gmail.com>
 * @since     2011-12-12
 */
class sfMemcachedRedisCache extends sfCache
{
    protected
        $memcached = null;

    protected
        $redis = null;

    /**
     * Initializes this sfCache instance.
     *
     * Available options:
     *
     * * redis:    sfRedis client configuration key
     *
     * * memcached: A memcached object (optional)
     *
     * * persistent_id: Memcached constructor persistent ID
     *
     * * host:       The default host (default to localhost)
     * * port:       The port for the default server (default to 11211)
     * * weight: true if the connection must be persistent, false otherwise (true by default)
     *
     * * servers:    An array of additional servers (keys: host, port, weight)
     *
     * * see sfCache for options available for all drivers
     *
     * @see sfCache
     */
    public function initialize($options = array())
    {
        parent::initialize($options);

        if (!extension_loaded('memcached'))
        {
            throw new sfInitializationException('You must have memcached installed and enabled to use sfMemcachedRedisCache class.');
        }

        if ($this->getOption('memcached'))
        {
            $this->memcached = $this->getOption('memcached');
        }
        else
        {
            $this->memcached = new Memcached($this->getOption('persistent_id'));

            if (!count($this->memcached->getServerList()))
            {
                $this->memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                $this->memcached->setOption(Memcached::OPT_NO_BLOCK, true);
                // $this->memcached->setOption(Memcached::OPT_BUFFER_WRITES, true);

                if ($this->getOption('compression'))
                {
                    $this->memcached->setOption(Memcached::OPT_COMPRESSION, true);
                }

                if ($this->getOption('servers'))
                {
                    foreach ($this->getOption('servers') as $server) {
                        if ( ! array_key_exists('weight', $server)) {
                            $server['weight'] = 100;
                        }
                        if ( ! array_key_exists('port', $server)) {
                            $server['port'] = 11211;
                        }
                        $this->memcached->addServer($server['host'], $server['port'], $server['weight']);
                    }
                }
                else
                {
                    $this->memcached->addServer(
                        $this->getOption('host', '127.0.0.1'),
                        $this->getOption('port', 11211),
                        $this->getOption('weight', 100));
                }
            }

        }

        $this->redis = sfRedis::getClient($this->getOption('redis', 'default'));
    }

    /**
     * @see sfCache
     */
    public function getBackend()
    {
        return $this->memcached;
    }

    /**
     * @see sfCache
     */
    public function get($key, $default = null)
    {
        $value = $this->memcached->get($this->getKey($key));

        return false === $value ? $default : $value;
    }

    /**
     * @see sfCache
     */
    public function has($key)
    {
        // use redis instead of memcache (memcache don't know existance without getting full value of key)
        return $this->redis->exists($this->getKey($key));
    }

    /**
     * @see sfCache
     */
    public function set($key, $data, $lifetime = null)
    {
        $mtime    = time();
        $lifetime = $this->getLifetime($lifetime);
        $p_key    = $this->getKey($key);

        // save metadata (modification time) in redis
        $pipe = $this->redis->pipeline();

        $pipe->set($p_key, $mtime);
        $pipe->expire($p_key, $lifetime);

        $reply = $pipe->execute();

        // set data in memcached
        if (false !== $this->memcached->replace($p_key, $data, $mtime + $lifetime))
        {
            return true;
        }

        return $this->memcached->set($p_key, $data, $mtime + $lifetime);
    }

    /**
     * @see sfCache
     */
    public function remove($key)
    {
        $p_key = $this->getKey($key);

        return $this->redis->del($p_key) and $this->memcached->delete($p_key);
    }

    /**
     * @see sfCache
     */
    public function clean($mode = sfCache::ALL)
    {
        if (sfCache::ALL === $mode)
        {
            $this->memcached->flush();
            $this->redis->flushdb();
        }
    }

    /**
     * @see sfCache
     */
    public function getLastModified($key)
    {
        // redis value is the modification time
        return $this->redis->get($this->getKey($key)) ?: 0;
    }

    /**
     * @see sfCache
     */
    public function getTimeout($key)
    {
        // use redis TTL command
        $ttl = $this->redis->ttl($this->getKey($key));

        return $ttl > -1 ? time() + $ttl : 0;
    }

    /**
     * @see sfCache
     */
    public function removePattern($pattern)
    {
        $pattern    = $this->getKey($pattern);
        $regexp     = self::patternToRegexp($pattern);
        $prefix_len = strlen($this->getOption('prefix'));

        // redis KEYS pattern understand * glob
        foreach ($this->redis->keys($pattern) as $key)
        {
            // but don't understand regexp, php needs to ensure it matches
            if (preg_match($regexp, $key))
            {
                $this->remove(substr($key, $prefix_len));
            }
        }
    }

    /**
     * @see sfCache
     */
    public function getMany($keys)
    {
        $values = array();
        $prefix_len = strlen($this->getOption('prefix'));

        foreach ($this->memcached->get(array_map(array($this, 'getKey'), $keys)) as $key => $value)
        {
            $values[substr($key, $prefix_len)] = $value;
        }

        return $values;
    }

    /**
     * Apply prefix and suffix to a value
     *
     * Usefull to be mapped on an array. Faster than foreach
     *
     * @param string $name
     * @param string $suffix
     * @access protected
     * @return string
     */
    protected function getKey($name, $suffix = null)
    {
        $key = $this->getOption('prefix').$name;

        if ($suffix !== null)
        {
            $key .= self::SEPARATOR.$suffix;
        }

        return $key;
    }
}

