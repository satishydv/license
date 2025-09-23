<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Environment Configuration
|--------------------------------------------------------------------------
|
| This file contains environment-specific configuration values.
| You can override these values by setting environment variables.
|
*/

// Database Configuration
$config['db_hostname'] = getenv('DB_HOSTNAME') ?: 'localhost';
$config['db_username'] = getenv('DB_USERNAME') ?: 'root';
$config['db_password'] = getenv('DB_PASSWORD') ?: '';
$config['db_database'] = getenv('DB_DATABASE') ?: 'license';

// JWT Configuration
$config['jwt_key'] = getenv('JWT_SECRET_KEY') ?: 'driving_license_secure_secret_key_2024_xyz789_abc123';
$config['jwt_algorithm'] = getenv('JWT_ALGORITHM') ?: 'HS256';
$config['jwt_expire_time'] = getenv('JWT_EXPIRE_TIME') ?: 3600; // 1 hour
$config['jwt_refresh_expire_time'] = getenv('JWT_REFRESH_EXPIRE_TIME') ?: 604800; // 7 days

// CORS Configuration
$config['cors_allowed_origins'] = getenv('CORS_ALLOWED_ORIGINS') ?: '*';
$config['cors_allowed_methods'] = getenv('CORS_ALLOWED_METHODS') ?: 'GET,POST,PUT,DELETE,OPTIONS';
$config['cors_allowed_headers'] = getenv('CORS_ALLOWED_HEADERS') ?: 'Content-Type,Authorization,X-Requested-With';
$config['cors_allow_credentials'] = getenv('CORS_ALLOW_CREDENTIALS') ?: 'false';
$config['cors_max_age'] = getenv('CORS_MAX_AGE') ?: 86400;

// Application Configuration
$config['app_name'] = getenv('APP_NAME') ?: 'Driving License System';
$config['app_env'] = getenv('APP_ENV') ?: 'development';
$config['app_debug'] = getenv('APP_DEBUG') ?: 'true';
