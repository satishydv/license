<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Role_model');
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
            
            $this->load->model('User_model');
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
     * Get all roles
     */
    public function index() {
        log_message('debug', 'Role controller index() called');
        // Check if user has permission to read roles
        if (!$this->check_permission('roles:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        log_message('debug', 'Permission check passed for roles:read');
        
        $roles = $this->Role_model->get_all_roles();
        log_message('debug', 'Retrieved ' . count($roles) . ' roles from database');
        
        // Ensure all roles have permissions as arrays
        foreach ($roles as $role) {
            $role->permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
        }
        
        $this->json_response(true, 'Roles retrieved successfully', ['roles' => $roles]);
    }
    
    /**
     * Create new role
     */
    public function create() {
        // Check if user has permission to create roles
        if (!$this->check_permission('roles:create')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'post') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        // Get JSON input
        $input = json_decode($this->input->raw_input_stream, true);
        
        // Validate input
        $this->form_validation->set_data($input);
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[100]|is_unique[roles.name]');
        $this->form_validation->set_rules('description', 'Description', 'max_length[500]');
        
        // Custom validation for permissions
        if (!isset($input['permissions']) || !is_array($input['permissions']) || empty($input['permissions'])) {
            $this->json_response(false, 'Validation failed: Permissions field is required and must be an array', ['permissions' => 'The Permissions field is required and must be an array'], 400);
            return;
        }
        
        if ($this->form_validation->run() === FALSE) {
            $errors = $this->form_validation->error_array();
            $this->json_response(false, 'Validation failed: ' . implode(', ', $errors), $errors, 400);
            return;
        }
        
        // Prepare role data
        $role_data = [
            'name' => $input['name'],
            'description' => isset($input['description']) ? $input['description'] : '',
            'permissions' => json_encode($input['permissions']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Create role
        $role_id = $this->Role_model->create_role($role_data);
        
        if ($role_id) {
            // Get created role
            $role = $this->Role_model->get_role_by_id($role_id);
            // Ensure permissions is an array
            $role->permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
            
            $this->json_response(true, 'Role created successfully', ['role' => $role]);
        } else {
            $this->json_response(false, 'Role creation failed', null, 500);
        }
    }
    
    /**
     * Get role by ID
     */
    public function show($id) {
        // Check if user has permission to read roles
        if (!$this->check_permission('roles:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        $role = $this->Role_model->get_role_by_id($id);
        
        if ($role) {
            $role->permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
            $this->json_response(true, 'Role retrieved successfully', ['role' => $role]);
        } else {
            $this->json_response(false, 'Role not found', null, 404);
        }
    }
    
    /**
     * Update role
     */
    public function update($id) {
        // Check if user has permission to update roles
        if (!$this->check_permission('roles:update')) {
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
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]|max_length[100]|callback_check_role_name_unique[' . $id . ']');
        $this->form_validation->set_rules('description', 'Description', 'max_length[500]');
        
        // Custom validation for permissions
        if (!isset($input['permissions']) || !is_array($input['permissions']) || empty($input['permissions'])) {
            $this->json_response(false, 'Validation failed: Permissions field is required and must be an array', ['permissions' => 'The Permissions field is required and must be an array'], 400);
            return;
        }
        
        if ($this->form_validation->run() === FALSE) {
            $errors = $this->form_validation->error_array();
            $this->json_response(false, 'Validation failed: ' . implode(', ', $errors), $errors, 400);
            return;
        }
        
        // Prepare role data
        $role_data = [
            'name' => $input['name'],
            'description' => isset($input['description']) ? $input['description'] : '',
            'permissions' => json_encode($input['permissions']),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Update role
        $result = $this->Role_model->update_role($id, $role_data);
        
        if ($result) {
            $role = $this->Role_model->get_role_by_id($id);
            $role->permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
            
            $this->json_response(true, 'Role updated successfully', ['role' => $role]);
        } else {
            $this->json_response(false, 'Role update failed', null, 500);
        }
    }
    
    /**
     * Delete role
     */
    public function delete($id) {
        // Check if user has permission to delete roles
        if (!$this->check_permission('roles:delete')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'delete') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $result = $this->Role_model->delete_role($id);
        
        if ($result) {
            $this->json_response(true, 'Role deleted successfully');
        } else {
            $this->json_response(false, 'Role deletion failed', null, 500);
        }
    }
    
    /**
     * Check if role name is unique (excluding current role for updates)
     */
    public function check_role_name_unique($name, $exclude_id) {
        if ($this->Role_model->role_name_exists($name, $exclude_id)) {
            $this->form_validation->set_message('check_role_name_unique', 'The {field} field must be unique.');
            return FALSE;
        }
        return TRUE;
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
