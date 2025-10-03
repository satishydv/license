<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library('form_validation');
        $this->load->config('jwt');
        
        // Load JWT library using CodeIgniter's loader
        $this->load->library('JWT_Library');
        
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        // Set JSON header
        header('Content-Type: application/json');
    }
    
    /**
     * User login
     */
    public function login() {
        if ($this->input->method() !== 'post') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
        
        if ($this->form_validation->run() === FALSE) {
            $this->json_response(false, 'Validation failed', $this->form_validation->error_array(), 400);
            return;
        }
        
        $email = $input['email'];
        $password = $input['password'];
        
        // Authenticate user
        $user = $this->User_model->authenticate($email, $password);
        
        if ($user) {
            // Generate JWT token
            $payload = [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'iat' => time(),
                'exp' => time() + $this->config->item('jwt_expire_time')
            ];
            
            $token = $this->jwt_library->encode($payload);
            
            // Prepare user data (exclude password)
            // Get user's permissions
            $this->load->model('Role_model');
            $role = $this->Role_model->get_role_by_id($user->role_id);
            $permissions = [];
            if ($role && $role->permissions) {
                $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
            }
            
            $user_data = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'last_login' => $user->last_login,
                'permissions' => $permissions
            ];
            
            $this->json_response(true, 'Login successful', [
                'user' => $user_data,
                'token' => $token,
                'expires_in' => $this->config->item('jwt_expire_time')
            ]);
        } else {
            $this->json_response(false, 'Invalid email or password', null, 401);
        }
    }
    
    /**
     * User registration
     */
    public function register() {
        if ($this->input->method() !== 'post') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[255]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
        $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[password]');
        
        if ($this->form_validation->run() === FALSE) {
            $this->json_response(false, 'Validation failed', $this->form_validation->error_array(), 400);
            return;
        }
        
        // Check if email already exists
        if ($this->User_model->email_exists($input['email'])) {
            $this->json_response(false, 'Email already exists', null, 409);
            return;
        }
        
        // Prepare user data
        $user_data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => isset($input['role']) ? $input['role'] : 'user',
            'status' => 'active' // You might want to set this to 'pending' and require email verification
        ];
        
        // Create user
        $user_id = $this->User_model->create_user($user_data);
        
        if ($user_id) {
            // Get created user
            $user = $this->User_model->get_user_by_id($user_id);
            
            // Prepare user data (exclude password)
            $user_response = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at
            ];
            
            $this->json_response(true, 'User registered successfully', ['user' => $user_response]);
        } else {
            $this->json_response(false, 'Registration failed', null, 500);
        }
    }
    
    /**
     * Get current user info
     */
    public function me() {
        $user = $this->authenticate_request();
        if (!$user) {
            return;
        }
        
        // Get user's permissions
        $this->load->model('Role_model');
        $role = $this->Role_model->get_role_by_id($user->role_id);
        $permissions = [];
        if ($role && $role->permissions) {
            $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
        }
        
        $user_data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'last_login' => $user->last_login,
            'permissions' => $permissions
        ];
        
        $this->json_response(true, 'User data retrieved', ['user' => $user_data]);
    }
    
    /**
     * Refresh JWT token
     */
    public function refresh() {
        $user = $this->authenticate_request();
        if (!$user) {
            return;
        }
        
        // Generate new JWT token
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + $this->config->item('jwt_expire_time')
        ];
        
        $token = $this->jwt_library->encode($payload);
        
        $this->json_response(true, 'Token refreshed', [
            'token' => $token,
            'expires_in' => $this->config->item('jwt_expire_time')
        ]);
    }
    
    /**
     * Logout (client-side token removal)
     */
    public function logout() {
        $this->json_response(true, 'Logged out successfully');
    }
    
    /**
     * Change user password
     */
    public function change_password() {
        if ($this->input->method() !== 'put') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Authenticate user first
        $user = $this->authenticate_request();
        if (!$user) {
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('current_password', 'Current Password', 'required');
        $this->form_validation->set_rules('new_password', 'New Password', 'required|min_length[6]');
        $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[new_password]');
        
        if ($this->form_validation->run() === FALSE) {
            $this->json_response(false, 'Validation failed', $this->form_validation->error_array(), 400);
            return;
        }
        
        $current_password = $input['current_password'];
        $new_password = $input['new_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user->password)) {
            $this->json_response(false, 'Current password is incorrect', null, 400);
            return;
        }
        
        // Check if new password is different from current
        if (password_verify($new_password, $user->password)) {
            $this->json_response(false, 'New password must be different from current password', null, 400);
            return;
        }
        
        // Update password in database (User_model will hash it)
        $update_data = ['password' => $new_password];
        $updated = $this->User_model->update_user($user->id, $update_data);
        
        if ($updated) {
            $this->json_response(true, 'Password changed successfully');
        } else {
            $this->json_response(false, 'Failed to update password', null, 500);
        }
    }
    
    /**
     * Authenticate JWT request
     */
    private function authenticate_request() {
        $headers = $this->input->request_headers();
        
        if (!isset($headers['Authorization'])) {
            $this->json_response(false, 'Authorization header missing', null, 401);
            return false;
        }
        
        $auth_header = $headers['Authorization'];
        $token = null;
        
        // Extract token from "Bearer TOKEN" format
        if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            $token = $matches[1];
        }
        
        if (!$token) {
            $this->json_response(false, 'Token not found', null, 401);
            return false;
        }
        
        // Validate token
        $decoded = $this->jwt_library->validate_token($token);
        if (!$decoded) {
            $this->json_response(false, 'Invalid or expired token', null, 401);
            return false;
        }
        
        // Get user data
        $user = $this->User_model->get_user_by_id($decoded->user_id);
        if (!$user || $user->status !== 'active') {
            $this->json_response(false, 'User not found or inactive', null, 401);
            return false;
        }
        
        return $user;
    }
    
    /**
     * JSON response helper
     */
    private function json_response($success, $message, $data = null, $http_code = 200) {
        http_response_code($http_code);
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
}
