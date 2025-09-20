# Environment Configuration Guide

## Database Configuration

The system now uses environment variables for configuration. Here's how to set up your environment:

### 1. Database Settings (Already configured for "license" database)

The system is configured to use:
- **Database Name**: `license` (as shown in your database structure)
- **Host**: `localhost`
- **Username**: `root`
- **Password**: (empty by default)

### 2. Environment Variables

You can set these environment variables in your system or modify the `application/config/env.php` file directly:

```php
// Database Configuration
$config['db_hostname'] = 'localhost';
$config['db_username'] = 'root';
$config['db_password'] = '';
$config['db_database'] = 'license';  // Your database name

// JWT Configuration
$config['jwt_key'] = 'your-very-secure-secret-key-change-this-in-production';
$config['jwt_algorithm'] = 'HS256';
$config['jwt_expire_time'] = 3600; // 1 hour
$config['jwt_refresh_expire_time'] = 604800; // 7 days

// CORS Configuration
$config['cors_allowed_origins'] = 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001';
$config['cors_allowed_methods'] = 'GET,POST,PUT,DELETE,OPTIONS';
$config['cors_allowed_headers'] = 'Content-Type,Authorization,X-Requested-With';
$config['cors_allow_credentials'] = 'true';
$config['cors_max_age'] = 86400;
```

### 3. Security Recommendations

1. **Change JWT Secret Key**: Update the JWT secret key in production:
   ```php
   $config['jwt_key'] = 'your-very-secure-random-string-here';
   ```

2. **Database Security**: If you have a database password, update it:
   ```php
   $config['db_password'] = 'your-database-password';
   ```

3. **CORS Origins**: Update allowed origins for production:
   ```php
   $config['cors_allowed_origins'] = 'https://yourdomain.com,https://www.yourdomain.com';
   ```

### 4. Testing the Configuration

1. **Check Database Connection**: The system should now connect to your "license" database
2. **Test API Endpoints**: Use the same endpoints as before
3. **Verify CORS**: Frontend should be able to communicate with backend

### 5. Current Configuration Status

✅ **Database**: Configured for "license" database  
✅ **CORS**: Configured for Next.js frontend (localhost:3000)  
✅ **JWT**: Ready for authentication  
✅ **Environment**: Centralized configuration in `env.php`  

### 6. Next Steps

1. **Install Dependencies**: Run `composer install`
2. **Test Database**: Verify connection to "license" database
3. **Test Authentication**: Try logging in with existing users
4. **Create Test User**: Use the registration endpoint to create a test user

The system is now properly configured to use your "license" database with the "users" table you've already created!
