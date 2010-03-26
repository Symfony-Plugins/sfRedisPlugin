<?php

/**
 * sfRedisPluginConfiguration
 *
 * @uses      sfPluginConfiguration
 * @package   sfRedisPlugin
 * @author    Benjamin VIELLARD <bicou@bicou.com>
 * @license   The MIT License
 * @version   SVN: $Id$
 */
class sfRedisPluginConfiguration extends sfPluginConfiguration
{
  /**
   * path to config
   *
   * @var string
   */
  const CONFIG_PATH = 'config/redis.yml';

  /**
   * initialize plugin
   *
   * @access public
   * @return void
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

