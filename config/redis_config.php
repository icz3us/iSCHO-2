<?php
/**
 * Redis Configuration
 * Copy this file to redis_config.php and update with your settings
 * 
 * @package iSCHO
 */

 return [
    'host' => '127.0.0.1',      // Redis server host
    'port' => 6379,              // Redis server port
    'timeout' => 5,               // Connection timeout
    'password' => null,           // Set if Redis requires password
    'database' => 0,              // Redis database number (0-15)
    'prefix' => 'ischo:'          // Key prefix for all Redis keys
];

