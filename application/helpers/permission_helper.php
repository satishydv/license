<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permission Helper
 * 
 * Provides functions to check user permissions based on their role
 */

if (!function_exists('has_permission')) {
    /**
     * Check if current user has a specific permission
     * 
     * @param string $permission The permission to check (e.g., 'users:create')
     * @return bool True if user has permission, false otherwise
     */
    function has_permission($permission) {
        try {
            $CI =& get_instance();
            
            // Get current user from JWT token
            $user = get_current_user();
            if (!$user) {
                log_message('debug', 'Permission check failed: No current user found');
                return false;
            }
            
            log_message('debug', 'User object type: ' . gettype($user));
            log_message('debug', 'User object: ' . json_encode($user));
            
            // Handle both email from JWT token and user object
            $user_email = isset($user->email) ? $user->email : 'unknown';
            log_message('debug', 'Permission check for user: ' . $user_email . ', permission: ' . $permission);
            
            // Get user's role
            if (!isset($CI->User_model)) {
                $CI->load->model('User_model');
            }
            
            // Handle both user_id (from JWT) and id (from database)
            $user_id = isset($user->user_id) ? $user->user_id : (isset($user->id) ? $user->id : null);
            if (!$user_id) {
                log_message('debug', 'Permission check failed: No user ID found');
                return false;
            }
            
            log_message('debug', 'Looking up user with ID: ' . $user_id);
            
            $user_data = $CI->User_model->get_user_by_id($user_id);
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
            if (!isset($CI->Role_model)) {
                $CI->load->model('Role_model');
            }
            
            $role = $CI->Role_model->get_role_by_id($user_data->role_id);
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
}

if (!function_exists('has_any_permission')) {
    /**
     * Check if current user has any of the specified permissions
     * 
     * @param array $permissions Array of permissions to check
     * @return bool True if user has at least one permission, false otherwise
     */
    function has_any_permission($permissions) {
        if (!is_array($permissions)) {
            return false;
        }
        
        foreach ($permissions as $permission) {
            if (has_permission($permission)) {
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('has_all_permissions')) {
    /**
     * Check if current user has all of the specified permissions
     * 
     * @param array $permissions Array of permissions to check
     * @return bool True if user has all permissions, false otherwise
     */
    function has_all_permissions($permissions) {
        if (!is_array($permissions)) {
            return false;
        }
        
        foreach ($permissions as $permission) {
            if (!has_permission($permission)) {
                return false;
            }
        }
        
        return true;
    }
}

if (!function_exists('get_current_user')) {
    /**
     * Get current user from JWT token
     * 
     * @return object|false User object or false if not authenticated
     */
    function get_current_user() {
        try {
            $CI =& get_instance();
            
            // Get Authorization header
            $token = $CI->input->get_request_header('Authorization');
            if (!$token) {
                log_message('debug', 'No Authorization header found');
                return false;
            }
            
            // Remove 'Bearer ' prefix
            $token = str_replace('Bearer ', '', $token);
            log_message('debug', 'JWT Token (first 20 chars): ' . substr($token, 0, 20));
            
            // Use JWT library that's already loaded in the controller
            if (!isset($CI->jwt_library)) {
                log_message('debug', 'JWT library not available, trying to load...');
                try {
                    $CI->load->library('JWT_Library');
                } catch (Exception $e) {
                    log_message('error', 'Failed to load JWT library: ' . $e->getMessage());
                    return false;
                }
            }
            
            if (!isset($CI->jwt_library)) {
                log_message('error', 'JWT library not available after loading attempt');
                return false;
            }
            
            log_message('debug', 'JWT library instance: ' . (isset($CI->jwt_library) ? 'loaded' : 'not loaded'));
            
            // Validate token
            $decoded = $CI->jwt_library->validate_token($token);
            if (!$decoded) {
                log_message('debug', 'JWT token validation failed');
                return false;
            }
            
            log_message('debug', 'JWT token validated for user: ' . $decoded->email);
            log_message('debug', 'JWT decoded object: ' . json_encode($decoded));
            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'get_current_user error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_user_permissions')) {
    /**
     * Get all permissions for current user
     * 
     * @return array Array of permissions or empty array if no permissions
     */
    function get_user_permissions() {
        $CI =& get_instance();
        
        // Get current user from JWT token
        $user = get_current_user();
        if (!$user) {
            return [];
        }
        
        // Get user's role
        $CI->load->model('User_model');
        $user_data = $CI->User_model->get_user_by_id($user->id);
        if (!$user_data || !$user_data->role_id) {
            return [];
        }
        
        // Get role permissions
        $CI->load->model('Role_model');
        $role = $CI->Role_model->get_role_by_id($user_data->role_id);
        if (!$role || !$role->permissions) {
            return [];
        }
        
        // Decode permissions JSON
        $permissions = is_string($role->permissions) ? json_decode($role->permissions, true) : $role->permissions;
        return is_array($permissions) ? $permissions : [];
    }
}

if (!function_exists('require_permission')) {
    /**
     * Require a specific permission, return 403 if not authorized
     * 
     * @param string $permission The permission to check
     * @return void
     */
    function require_permission($permission) {
        try {
            if (!has_permission($permission)) {
                log_message('debug', 'Permission denied for: ' . $permission);
                
                // Set CORS headers before sending the response
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
                header('Access-Control-Allow-Credentials: false');
                
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Access denied. Insufficient permissions.',
                    'error' => 'FORBIDDEN'
                ]);
                exit;
            }
        } catch (Exception $e) {
            log_message('error', 'require_permission error: ' . $e->getMessage());
            
            // Set CORS headers before sending the response
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Allow-Credentials: false');
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error during permission check.',
                'error' => 'INTERNAL_ERROR'
            ]);
            exit;
        }
    }
}
