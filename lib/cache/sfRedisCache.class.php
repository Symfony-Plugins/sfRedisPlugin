<?php

/**
 * Cache class that stores cached content in Redis server.
 *
 * @package    sfRedisPlugin
 * @subpackage cache
 * @author     Benjamin Viellard <bicou@bicou.com>
 * @version    SVN: $Id$
 */
class sfRedisCache extends sfCache
{
  /**
   * Predis client instance
   *
   * @var Predis_Client
   * @access protected
   */
  protected $redis = null;

  /**
   * Available options :
   *
   * * connection:   Configuration key to connection parameters
   *
   * @see sfCache
   */
  public function initialize($options = array())
  {
    parent::initialize($options);

    $this->redis = sfRedis::getClient($this->getOption('connection', 'default'));
  }

  /**
   * @see sfCache
   */
  public function getBackend()
  {
    return $this->redis;
  }

 /**
  * @see sfCache
  */
  public function get($key, $default = null)
  {
    $value = $this->redis->get($this->getKey($key));

    return null === $value ? $default : $value;
  }

  /**
   * @see sfCache
   */
  public function has($key)
  {
    return $this->redis->exists($this->getKey($key));
  }

  /**
   * @see sfCache
   */
  public function set($key, $data, $lifetime = null)
  {
    $lifetime = $this->getLifetime($lifetime);

    if ($lifetime < 1)
    {
      $ret = $this->remove($key);
    }
    else
    {
      $pkey = $this->getKey($key);
      $mkey = $this->getKey($key, '_lastmodified');
      $pipe = $this->redis->pipeline();

      $pipe->mset(array($pkey => $data, $mkey => time()));
      $pipe->expire($pkey, $lifetime);
      $pipe->expire($mkey, $lifetime);

      $reply = $pipe->execute();

      $ret = $reply[0] and $reply[1] and $reply[2];
    }

    return $ret;
  }

  /**
   * @see sfCache
   */
  public function remove($key)
  {
    return $this->redis->del($this->getKey($key), $this->getKey($key, '_lastmodified'));
  }

  /**
   * We manually remove keys as the redis glob style * == sfCache ** style
   *
   * @see sfCache
   */
  public function removePattern($pattern)
  {
    $pattern = $this->getKey($pattern);
    $regexp  = self::patternToRegexp($pattern);
    foreach ($this->redis->keys($pattern) as $key)
    {
      if (preg_match($regexp, $key))
      {
        $this->remove(substr($key, strlen($this->getOption('prefix'))));
      }
    }
  }

  /**
   * @see sfCache
   */
  public function clean($mode = sfCache::ALL)
  {
    if (sfCache::ALL === $mode)
    {
      $this->removePattern('*');
    }
  }

  /**
   * @see sfCache
   */
  public function getTimeout($key)
  {
    $ttl = $this->redis->ttl($this->getKey($key));
    return $ttl > -1 ? time() + $ttl : 0;
  }

  /**
   * @see sfCache
   */
  public function getLastModified($key)
  {
    return $this->redis->get($this->getKey($key, '_lastmodified'));
  }

  /**
   * Optimized getMany with Redis mget method
   *
   * @param array $keys
   * @access public
   * @return array
   */
  public function getMany($keys)
  {
    $cache_keys = array_map(array($this, 'getKey'), $keys);

    return array_combine($keys, $this->redis->mget($cache_keys));
  }

  /**
   * Checks if a key is expired or not
   *
   * @param string $key
   * @access public
   * @return void
   */
  public function isExpired($key)
  {
    return !$this->getTimeout($key);
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

