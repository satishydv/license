<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cities extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('JWT_Library');
        
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
    
    private function authenticate() {
        $headers = getallheaders();
        $token = null;
        
        // Debug: Log headers
        log_message('debug', 'Cities authenticate - Headers: ' . json_encode($headers));
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token) {
            log_message('debug', 'Cities authenticate - No token found');
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Token required']));
            return false;
        }
        
        try {
            $decoded = $this->jwt_library->validate_token($token);
            log_message('debug', 'Cities authenticate - Token validated successfully');
            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'Cities authenticate - Token validation failed: ' . $e->getMessage());
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid token']));
            return false;
        }
    }

    public function index() {
        try {
            log_message('debug', 'Cities index - Request received');
            
            // Check for state parameter for backward compatibility
            $state = $this->input->get('state');
            
            if (!empty($state)) {
                log_message('debug', 'Cities index - State parameter found: ' . $state);
                // Original functionality - return city names for a specific state
                $query = $this->db->query("SELECT city_name FROM cities WHERE city_state = ? ORDER BY city_name", array($state));
                
                if (!$query) {
                    throw new Exception('Query failed: ' . $this->db->last_query());
                }
                
                $cities = $query->result_array();
                $cityList = array();
                foreach ($cities as $city) {
                    $cityList[] = $city['city_name'];
                }
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode($cityList));
                return;
            }
            
            log_message('debug', 'Cities index - No state parameter, requiring authentication');
            // New functionality - return all cities with full data (requires authentication)
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Cities index - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            } else {
                log_message('debug', 'Cities index - Authentication successful');
            }
            
            // Get pagination and search parameters
            $page = (int) $this->input->get('page') ?: 1;
            $limit = (int) $this->input->get('limit') ?: 100;
            $search = $this->input->get('search') ?: '';
            $offset = ($page - 1) * $limit;
            
            log_message('debug', 'Cities index - Pagination params: page=' . $page . ', limit=' . $limit . ', offset=' . $offset . ', search=' . $search);
            
            // Build search conditions
            $whereConditions = array();
            $whereParams = array();
            
            if (!empty($search)) {
                $whereConditions[] = "(city_name LIKE ? OR city_state LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $whereParams[] = $searchTerm;
                $whereParams[] = $searchTerm;
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            // Get total count with search
            $countQuery = $this->db->query("SELECT COUNT(*) as total FROM cities " . $whereClause, $whereParams);
            $totalCount = $countQuery->row()->total;
            
            log_message('debug', 'Cities index - Total cities count: ' . $totalCount);
            
            // Get paginated cities with search
            $query = $this->db->query("SELECT city_id, city_name, city_state FROM cities " . $whereClause . " ORDER BY city_state, city_name LIMIT ? OFFSET ?", array_merge($whereParams, array($limit, $offset)));
            
            if (!$query) {
                throw new Exception('Query failed: ' . $this->db->last_query());
            }
            
            $cities = $query->result_array();
            
            log_message('debug', 'Cities index - Retrieved ' . count($cities) . ' cities');
            
            $response = [
                'success' => true,
                'data' => [
                    'cities' => $cities,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit),
                        'has_next' => $page < ceil($totalCount / $limit),
                        'has_prev' => $page > 1
                    ]
                ]
            ];
            
            log_message('debug', 'Cities index - Response: ' . json_encode($response));
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'Cities controller error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch cities',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function create() {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Cities create - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            $raw_input = $this->input->raw_input_stream;
            log_message('debug', 'Cities create - Raw input: ' . $raw_input);
            
            $input = json_decode($raw_input, true);
            log_message('debug', 'Cities create - Decoded input: ' . json_encode($input));
            
            if (!$input || !isset($input['city_name']) || !isset($input['city_state'])) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City name and state are required'
                    ]));
                return;
            }
            
            $city_name = trim($input['city_name']);
            $city_state = trim($input['city_state']);
            
            if (empty($city_name) || empty($city_state)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City name and state cannot be empty'
                    ]));
                return;
            }
            
            // Check if city already exists
            $existing = $this->db->query("SELECT city_id FROM cities WHERE city_name = ? AND city_state = ?", 
                array($city_name, $city_state))->row();
            
            if ($existing) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City already exists in this state'
                    ]));
                return;
            }
            
            $data = array(
                'city_name' => $city_name,
                'city_state' => $city_state
            );
            
            $this->db->insert('cities', $data);
            
            if ($this->db->affected_rows() > 0) {
                $city_id = $this->db->insert_id();
                $new_city = $this->db->query("SELECT city_id, city_name, city_state FROM cities WHERE city_id = ?", 
                    array($city_id))->row_array();
                
                $this->output
                    ->set_status_header(201)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'city' => $new_city
                        ]
                    ]));
            } else {
                throw new Exception('Failed to create city');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Cities create error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to create city',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function update($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Cities update - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            $input = json_decode($this->input->raw_input_stream, true);
            
            if (!$input || !isset($input['city_name']) || !isset($input['city_state'])) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City name and state are required'
                    ]));
                return;
            }
            
            $city_name = trim($input['city_name']);
            $city_state = trim($input['city_state']);
            
            if (empty($city_name) || empty($city_state)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City name and state cannot be empty'
                    ]));
                return;
            }
            
            // Check if city exists
            $existing = $this->db->query("SELECT city_id FROM cities WHERE city_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City not found'
                    ]));
                return;
            }
            
            // Check if another city with same name and state exists
            $duplicate = $this->db->query("SELECT city_id FROM cities WHERE city_name = ? AND city_state = ? AND city_id != ?", 
                array($city_name, $city_state, $id))->row();
            
            if ($duplicate) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Another city with this name already exists in this state'
                    ]));
                return;
            }
            
            $data = array(
                'city_name' => $city_name,
                'city_state' => $city_state
            );
            
            $this->db->where('city_id', $id);
            $this->db->update('cities', $data);
            
            if ($this->db->affected_rows() >= 0) {
                $updated_city = $this->db->query("SELECT city_id, city_name, city_state FROM cities WHERE city_id = ?", 
                    array($id))->row_array();
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'city' => $updated_city
                        ]
                    ]));
            } else {
                throw new Exception('Failed to update city');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Cities update error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to update city',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function delete($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Cities delete - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            // Check if city exists
            $existing = $this->db->query("SELECT city_id FROM cities WHERE city_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City not found'
                    ]));
                return;
            }
            
            $this->db->where('city_id', $id);
            $this->db->delete('cities');
            
            if ($this->db->affected_rows() > 0) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => 'City deleted successfully'
                    ]));
            } else {
                throw new Exception('Failed to delete city');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Cities delete error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to delete city',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function get($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Cities get - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            $city = $this->db->query("SELECT city_id, city_name, city_state FROM cities WHERE city_id = ?", 
                array($id))->row_array();
            
            if (!$city) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'City not found'
                    ]));
                return;
            }
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'data' => [
                        'city' => $city
                    ]
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'Cities get error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to get city',
                    'message' => $e->getMessage()
                ]));
        }
    }
}
