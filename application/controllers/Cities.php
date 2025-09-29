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
            
            // Check permission for reading cities
            if (!$this->check_permission('cities:read')) {
                log_message('debug', 'Cities index - Permission denied: cities:read');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to read cities.'
                    ]));
                return;
            }
            
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
            // Check permission for creating cities
            if (!$this->check_permission('cities:create')) {
                log_message('debug', 'Cities create - Permission denied: cities:create');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to create cities.'
                    ]));
                return;
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
            // Check permission for updating cities
            if (!$this->check_permission('cities:update')) {
                log_message('debug', 'Cities update - Permission denied: cities:update');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to update cities.'
                    ]));
                return;
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
            // Check permission for deleting cities
            if (!$this->check_permission('cities:delete')) {
                log_message('debug', 'Cities delete - Permission denied: cities:delete');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to delete cities.'
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
            // Check permission for reading cities
            if (!$this->check_permission('cities:read')) {
                log_message('debug', 'Cities get - Permission denied: cities:read');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to read cities.'
                    ]));
                return;
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
