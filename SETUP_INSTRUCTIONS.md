# JWT Authentication System Setup Instructions

## Backend Setup (CodeIgniter)

### 1. Install Dependencies
```bash
# Navigate to your project root
cd C:\xampp\htdocs\driving-license

# Install Composer dependencies
composer install
```

### 2. Database Setup
1. Create a MySQL database named `driving_license_db`
2. Import the users table using the SQL provided earlier
3. Update database credentials in `application/config/database.php` if needed

### 3. JWT Configuration
1. Update the JWT secret key in `application/config/jwt.php`:
   ```php
   $config['jwt_key'] = 'your-very-secure-secret-key-here';
   ```

### 4. Test Backend API
You can test the API endpoints using tools like Postman or curl:

**Login Test:**
```bash
curl -X POST http://localhost/driving-license/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

**Register Test:**
```bash
curl -X POST http://localhost/driving-license/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123","confirm_password":"password123"}'
```

## Frontend Setup (Next.js)

### 1. Install Dependencies
```bash
# Navigate to frontend directory
cd frontend

# Install dependencies (if not already installed)
npm install
```

### 2. Start Development Server
```bash
npm run dev
```

The frontend will be available at `http://localhost:3000`

## API Endpoints

### Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/login` | User login | No |
| POST | `/api/auth/register` | User registration | No |
| GET | `/api/auth/me` | Get current user | Yes |
| POST | `/api/auth/refresh` | Refresh token | Yes |
| POST | `/api/auth/logout` | User logout | Yes |

### Request/Response Examples

**Login Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Login Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "user",
      "status": "active",
      "created_at": "2024-01-15 10:30:00",
      "last_login": "2024-01-20 15:45:00"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600
  }
}
```

## Security Features

1. **Password Hashing**: Uses PHP's `password_hash()` with bcrypt
2. **JWT Tokens**: Stateless authentication with configurable expiration
3. **CORS Protection**: Configured for frontend communication
4. **Input Validation**: Server-side validation for all inputs
5. **SQL Injection Protection**: Uses CodeIgniter's Query Builder

## Testing the System

1. **Start both servers:**
   - Backend: Ensure XAMPP is running
   - Frontend: `npm run dev` in frontend directory

2. **Test Registration:**
   - Go to `http://localhost:3000`
   - Click "Register here"
   - Fill out the registration form

3. **Test Login:**
   - Use the credentials you just created
   - Should redirect to dashboard on successful login

4. **Test Protected Routes:**
   - Try accessing `/dashboard` without login (should redirect to login)
   - Login and access dashboard (should work)

## Troubleshooting

### Common Issues:

1. **CORS Errors**: Make sure the CORS hook is properly configured
2. **JWT Library Not Found**: Run `composer install` to install dependencies
3. **Database Connection**: Check database credentials and ensure MySQL is running
4. **Token Expired**: Tokens expire after 1 hour by default, use refresh endpoint

### Debug Mode:
Enable debug mode in `application/config/config.php`:
```php
$config['log_threshold'] = 4; // Enable all logging
```

## Next Steps

1. Create a registration page
2. Add password reset functionality
3. Implement role-based access control
4. Add user management features
5. Create protected dashboard pages
