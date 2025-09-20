<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {
    
    public function index() {
        echo "Test endpoint working!<br>";
        echo "Database: " . $this->db->database . "<br>";
        
        // Test database connection
        $query = $this->db->query("SELECT COUNT(*) as count FROM users");
        $result = $query->row();
        echo "Users table has " . $result->count . " records<br>";
    }
}
