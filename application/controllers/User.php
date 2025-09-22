<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library('form_validation');
        $this->load->library('JWT_Library');
        $this->load->helper('permission');
        
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: false');
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
     * Check if current user has a specific permission
     */
    private function check_permission($permission) {
        try {
            // Get Authorization header
            $token = $this->input->get_request_header('Authorization');
            if (!$token) {
                log_message('debug', 'No Authorization header found');
                return false;
            }
            
            // Remove 'Bearer ' prefix
            $token = str_replace('Bearer ', '', $token);
            log_message('debug', 'JWT Token (first 20 chars): ' . substr($token, 0, 20));
            
            // Validate token using JWT library
            $decoded = $this->jwt_library->validate_token($token);
            if (!$decoded) {
                log_message('debug', 'JWT token validation failed');
                return false;
            }
            
            log_message('debug', 'JWT token validated for user: ' . $decoded->email);
            log_message('debug', 'JWT decoded object: ' . json_encode($decoded));
            
            // Get user's role
            $user_id = isset($decoded->user_id) ? $decoded->user_id : (isset($decoded->id) ? $decoded->id : null);
            if (!$user_id) {
                log_message('debug', 'Permission check failed: No user ID found');
                return false;
            }
            
            log_message('debug', 'Looking up user with ID: ' . $user_id);
            
            $user_data = $this->User_model->get_user_by_id($user_id);
            if (!$user_data) {
                log_message('debug', 'Permission check failed: User data not found for user_id: ' . $user_id);
                return false;
            }
            
            if (!$user_data->role_id) {
                log_message('debug', 'Permission check failed: User has no role_id for user_id: ' . $user_id);
                return false;
            }
            
            log_message('debug', 'User role_id: ' . $user_data->role_id);
            
            // Get role permissions
            $this->load->model('Role_model');
            $role = $this->Role_model->get_role_by_id($user_data->role_id);
            if (!$role || !$role->permissions) {
                log_message('debug', 'Permission check failed: Role not found or no permissions for role_id: ' . $user_data->role_id);
                return false;
            }
            
            // Decode permissions JSON
            $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
            if (!is_array($permissions)) {
                log_message('debug', 'Permission check failed: Permissions is not an array: ' . json_encode($role->permissions));
                return false;
            }
            
            log_message('debug', 'User permissions: ' . json_encode($permissions));
            
            // Check if permission exists
            $hasPermission = in_array($permission, $permissions);
            log_message('debug', 'Has permission ' . $permission . ': ' . ($hasPermission ? 'YES' : 'NO'));
            
            return $hasPermission;
        } catch (Exception $e) {
            log_message('error', 'Permission check error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users
     */
    public function index() {
        log_message('debug', 'User controller index() called');
        // Check if user has permission to read users
        if (!$this->check_permission('users:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        log_message('debug', 'Permission check passed for users:read');
        
        $users = $this->User_model->get_all_users();
        log_message('debug', 'Retrieved ' . count($users) . ' users from database');
        
        // Remove password from response
        foreach ($users as $user) {
            unset($user->password);
        }
        
        $this->json_response(true, 'Users retrieved successfully', ['users' => $users]);
    }
    
    /**
     * Create new user
     */
    public function create() {
        // Check if user has permission to create users
        if (!$this->check_permission('users:create')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'post') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Debug: Log the input data
        log_message('debug', 'User creation input: ' . json_encode($input));
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[255]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
        $this->form_validation->set_rules('role', 'Role', 'required|callback_validate_role');
        
        if ($this->form_validation->run() === FALSE) {
            $errors = $this->form_validation->error_array();
            $this->json_response(false, 'Validation failed: ' . implode(', ', $errors), $errors, 400);
            return;
        }
        
        // Prepare user data
        $user_data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => $input['role'],
            'status' => isset($input['status']) ? $input['status'] : 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Create user
        $user_id = $this->User_model->create_user($user_data);
        
        if ($user_id) {
            // Get created user (without password)
            $user = $this->User_model->get_user_by_id($user_id);
            unset($user->password);
            
            $this->json_response(true, 'User created successfully', ['user' => $user]);
        } else {
            $this->json_response(false, 'User creation failed', null, 500);
        }
    }
    
    /**
     * Get user by ID
     */
    public function show($id) {
        // Check if user has permission to read users
        if (!$this->check_permission('users:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        $user = $this->User_model->get_user_by_id($id);
        
        if ($user) {
            unset($user->password);
            $this->json_response(true, 'User retrieved successfully', ['user' => $user]);
        } else {
            $this->json_response(false, 'User not found', null, 404);
        }
    }
    
    /**
     * Update user
     */
    public function update($id) {
        // Check if user has permission to update users
        if (!$this->check_permission('users:update')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'put') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[255]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('role', 'Role', 'required|callback_validate_role');
        $this->form_validation->set_rules('status', 'Status', 'required|in_list[active,inactive,pending]');
        
        if ($this->form_validation->run() === FALSE) {
            $errors = $this->form_validation->error_array();
            $this->json_response(false, 'Validation failed: ' . implode(', ', $errors), $errors, 400);
            return;
        }
        
        // Prepare user data
        $user_data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'status' => $input['status']
        ];
        
        // Add password if provided
        if (isset($input['password']) && !empty($input['password'])) {
            $user_data['password'] = $input['password'];
        }
        
        // Update user
        $result = $this->User_model->update_user($id, $user_data);
        
        if ($result) {
            $user = $this->User_model->get_user_by_id($id);
            unset($user->password);
            
            $this->json_response(true, 'User updated successfully', ['user' => $user]);
        } else {
            $this->json_response(false, 'User update failed', null, 500);
        }
    }
    
    /**
     * Delete user
     */
    public function delete($id) {
        // Check if user has permission to delete users
        if (!$this->check_permission('users:delete')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'delete') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $result = $this->User_model->delete_user($id);
        
        if ($result) {
            $this->json_response(true, 'User deleted successfully');
        } else {
            $this->json_response(false, 'User deletion failed', null, 500);
        }
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
    
    /**
     * Validate role exists in roles table
     */
    public function validate_role($role) {
        $this->load->model('Role_model');
        $existing_role = $this->Role_model->get_role_by_name($role);
        
        if (!$existing_role) {
            $this->form_validation->set_message('validate_role', 'The {field} field must be a valid role from the roles table.');
            return FALSE;
        }
        
        return TRUE;
    }
}
