<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cities extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        
        // Set CORS headers
        header('Access-Control-Allow-Origin: http://localhost:3000');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    public function index() {
        try {
            $state = $this->input->get('state');
            
            if (empty($state)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['error' => 'State parameter is required']));
                return;
            }
            
            // Debug: Log the received state
            log_message('debug', 'Received state: ' . $state);
            
            // Check if database connection is working
            if (!$this->db->conn_id) {
                throw new Exception('Database connection failed');
            }
            
            $query = $this->db->query("SELECT city_name FROM cities WHERE city_state = ? ORDER BY city_name", array($state));
            
            if (!$query) {
                throw new Exception('Query failed: ' . $this->db->last_query());
            }
            
            $cities = $query->result_array();
            
            // Debug: Log query result
            log_message('debug', 'Query returned ' . count($cities) . ' cities for state: ' . $state);
            
            $cityList = array();
            foreach ($cities as $city) {
                $cityList[] = $city['city_name'];
            }
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($cityList));
                
        } catch (Exception $e) {
            log_message('error', 'Cities controller error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Failed to fetch cities',
                    'message' => $e->getMessage()
                ]));
        }
    }
}
