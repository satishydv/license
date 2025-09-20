<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| JWT Configuration
|--------------------------------------------------------------------------
|
| Configuration for JWT (JSON Web Token) authentication
| Loads from environment configuration
|
*/

$config['jwt_key'] = 'your-secret-key-change-this-in-production';
$config['jwt_algorithm'] = 'HS256';
$config['jwt_expire_time'] = 3600; // 1 hour in seconds
$config['jwt_refresh_expire_time'] = 604800; // 7 days in seconds
