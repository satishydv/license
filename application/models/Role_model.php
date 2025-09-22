<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Get all roles
     */
    public function get_all_roles() {
        $query = $this->db->get('roles');
        return $query->result();
    }
    
    /**
     * Get role by ID
     */
    public function get_role_by_id($id) {
        $this->db->where('id', $id);
        $query = $this->db->get('roles');
        return $query->num_rows() === 1 ? $query->row() : false;
    }
    
    /**
     * Get role by name
     */
    public function get_role_by_name($name) {
        $this->db->where('name', $name);
        $query = $this->db->get('roles');
        return $query->num_rows() === 1 ? $query->row() : false;
    }
    
    /**
     * Create new role
     */
    public function create_role($data) {
        $this->db->insert('roles', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Update role
     */
    public function update_role($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('roles', $data);
    }
    
    /**
     * Delete role
     */
    public function delete_role($id) {
        $this->db->where('id', $id);
        return $this->db->delete('roles');
    }
    
    /**
     * Get user permissions
     */
    public function get_user_permissions($user_id) {
        $this->db->select('r.permissions');
        $this->db->from('users u');
        $this->db->join('roles r', 'u.role_id = r.id');
        $this->db->where('u.id', $user_id);
        $result = $this->db->get()->row();
        
        if ($result) {
            return json_decode($result->permissions, true);
        }
        return [];
    }
    
    /**
     * Check if role name exists
     */
    public function role_name_exists($name, $exclude_id = null) {
        $this->db->where('name', $name);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('roles') > 0;
    }
}
