<?php

/**
 * TODO: short description.
 *
 * TODO: long description.
 *
 * @version   $Id$
 * @author    Benjamin Viellard <benjamin.viellard@bicou.com>
 * @copyright (c) 2010 AGAPS
 * @since     2010-03-16
 */
class sfRedis
{
  private static $config = null;

  private static $clients = array();

  public static function initialize($config)
  {
    self::$config = $config;
  }

  /**
   * TODO: short description.
   *
   * @param string $connection Optional, defaults to 'default'.
   *
   * @return TODO
   * @author Benjamin Viellard <benjamin.viellard@bicou.com>
   * @since  2010-03-16
   */
  public static function getClient($connection = 'default')
  {
    if (!isset(self::$clients[$connection]))
    {
      $parameters = isset(self::$config['connections'][$connection]) ? self::$config['connections'][$connection] : null;
      self::$clients[$connection] = Predis_Client::create($parameters);
    }

    return self::$clients[$connection];
  }
}

