<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Authenticate user with email and password
     */
    public function authenticate($email, $password) {
        $this->db->where('email', $email);
        $this->db->where('status', 'active');
        $query = $this->db->get('users');
        
        if ($query->num_rows() === 1) {
            $user = $query->row();
            if (password_verify($password, $user->password)) {
                // Update last login
                $this->update_last_login($user->id);
                return $user;
            }
        }
        return false;
    }
    
    /**
     * Get user by email
     */
    public function get_user_by_email($email) {
        $this->db->where('email', $email);
        $query = $this->db->get('users');
        return $query->num_rows() === 1 ? $query->row() : false;
    }
    
    /**
     * Get user by ID
     */
    public function get_user_by_id($id) {
        $this->db->where('id', $id);
        $query = $this->db->get('users');
        return $query->num_rows() === 1 ? $query->row() : false;
    }
    
    /**
     * Create new user
     */
    public function create_user($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Set default values
        $data['status'] = isset($data['status']) ? $data['status'] : 'pending';
        $data['role'] = isset($data['role']) && !empty($data['role']) ? $data['role'] : 'user';
        $data['created_at'] = date('Y-m-d H:i:s');
        
        // Get role_id from role name
        log_message('debug', 'User model - role data: ' . json_encode($data));
        
        if (isset($data['role']) && !empty($data['role'])) {
            $this->load->model('Role_model');
            $role = $this->Role_model->get_role_by_name($data['role']);
            log_message('debug', 'Role lookup result: ' . json_encode($role));
            
            if ($role) {
                // Preserve the original role name and set the role_id
                $data['role'] = $data['role']; // Keep the original role name
                $data['role_id'] = $role->id;
                log_message('debug', 'Role assigned - role: ' . $data['role'] . ', role_id: ' . $data['role_id']);
            } else {
                // If role not found, set to default 'user' role
                $default_role = $this->Role_model->get_role_by_name('user');
                if ($default_role) {
                    $data['role'] = 'user';
                    $data['role_id'] = $default_role->id;
                }
            }
        } else {
            // If no role provided, set to default 'user' role
            $this->load->model('Role_model');
            $default_role = $this->Role_model->get_role_by_name('user');
            if ($default_role) {
                $data['role'] = 'user';
                $data['role_id'] = $default_role->id;
            }
        }
        
        $this->db->insert('users', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Update user
     */
    public function update_user($id, $data) {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Get role_id from role name if role is being updated
        if (isset($data['role']) && !empty($data['role'])) {
            $this->load->model('Role_model');
            $role = $this->Role_model->get_role_by_name($data['role']);
            if ($role) {
                // Preserve the original role name and set the role_id
                $data['role'] = $data['role']; // Keep the original role name
                $data['role_id'] = $role->id;
            }
        }
        
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }
    
    /**
     * Update last login timestamp
     */
    public function update_last_login($user_id) {
        $this->db->where('id', $user_id);
        return $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Check if email exists
     */
    public function email_exists($email) {
        $this->db->where('email', $email);
        return $this->db->count_all_results('users') > 0;
    }
    
    /**
     * Get all users (for admin)
     */
    public function get_all_users($limit = null, $offset = null) {
        if ($limit) {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get('users');
        return $query->result();
    }
    
    /**
     * Delete user
     */
    public function delete_user($id) {
        $this->db->where('id', $id);
        return $this->db->delete('users');
    }
}
