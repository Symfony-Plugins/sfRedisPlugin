<?php

/**
 * TODO: short description.
 *
 * TODO: long description.
 *
 * @version   $Id$
 * @author    Benjamin Viellard <benjamin.viellard@bicou.com>
 */
class sfRedisPluginConfiguration extends sfPluginConfiguration
{
  const VERSION = '0.a';

  const CONFIG_PATH = 'config/redis.yml';

  /**
   * TODO: short description.
   *
   * @return TODO
   * @author Benjamin Viellard <benjamin.viellard@bicou.com>
   */
  public function initialize()
  {
    try
    {
      if ($this->configuration instanceof sfApplicationConfiguration)
      {
        $configCache = $this->configuration->getConfigCache();
        $configCache->registerConfigHandler(self::CONFIG_PATH, 'sfRedisConfigHandler');
        $config = include $configCache->checkConfig(self::CONFIG_PATH);
      }
      else
      {
        $config = sfRedisConfigHandler::getConfiguration(array(
          $this->getRootDir().'/'.self::CONFIG_PATH,
          $this->configuration->getRootDir().'/'.self::CONFIG_PATH
        ));
      }
    }
    catch (sfConfigurationException $e)
    {
      $config = null;
    }

    sfRedis::initialize($config);
  }
}

