<?php

global $config;

/**
 * Sample configuration file for test environment
 *
 *   MySql:
 *     create database <DATABASE>;
 */
$config['host'] = "localhost";
$config['db'] = "<DATABASE>";

// User with all privileges in <DATABASE>
// MySql:
//   create user  'someuser'@'someuser' identified by 'somepassword';
//   grant all privileges on theDb.* to 'someuser'@'somehost';
//   flush privileges;

$config['user'] = "<DB USER>";
$config['pwd'] = "<DB PASSWORD";



// User with only select privileges in <DATABASE>
// MySql:
//   create user  'someuser'@'someuser' identified by 'somepassword';
//   grant select on theDb.* to 'someuser'@'somehost';
//   flush privileges;

$config['restricteduser'] = "<Restricted DB user>";
$config['restricteduserpwd'] = "<DB PASSWORD";