<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class DTO extends CI_Controller {
    
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
        
        log_message('debug', 'DTO authenticate - Headers: ' . json_encode($headers));
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token) {
            log_message('debug', 'DTO authenticate - No token found');
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Token required']));
            return false;
        }
        
        try {
            $decoded = $this->jwt_library->validate_token($token);
            log_message('debug', 'DTO authenticate - Token validated successfully');
            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'DTO authenticate - Token validation failed: ' . $e->getMessage());
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
            log_message('debug', 'DTO index - Request received');
            
            // Check permission for reading DTOs
            if (!$this->check_permission('dto:read')) {
                log_message('debug', 'DTO index - Permission denied: dto:read');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to read DTOs.'
                    ]));
                return;
            }
            
            $page = (int) $this->input->get('page') ?: 1;
            $limit = (int) $this->input->get('limit') ?: 100;
            $offset = ($page - 1) * $limit;
            
            log_message('debug', 'DTO index - Pagination params: page=' . $page . ', limit=' . $limit . ', offset=' . $offset);
            
            $countQuery = $this->db->query("SELECT COUNT(*) as total FROM dto");
            $totalCount = $countQuery->row()->total;
            
            log_message('debug', 'DTO index - Total DTOs count: ' . $totalCount);
            
            $query = $this->db->query("SELECT * FROM dto ORDER BY date DESC LIMIT ? OFFSET ?", array($limit, $offset));
            
            if (!$query) {
                throw new Exception('Query failed: ' . $this->db->last_query());
            }
            
            $dtos = $query->result_array();
            
            log_message('debug', 'DTO index - Retrieved ' . count($dtos) . ' DTOs');
            
            $response = [
                'success' => true,
                'data' => [
                    'dtos' => $dtos,
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
            
            log_message('debug', 'DTO index - Response: ' . json_encode($response));
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'DTO controller error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch DTOs',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function create() {
        try {
            // Check permission for creating DTOs
            if (!$this->check_permission('dto:create')) {
                log_message('debug', 'DTO create - Permission denied: dto:create');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to create DTOs.'
                    ]));
                return;
            }
            
            // Get form data
            $date = $this->input->post('date');
            $amount = $this->input->post('amount') ? (float) $this->input->post('amount') : 0.00;
            $pay_amount = $this->input->post('pay_amount') ? (float) $this->input->post('pay_amount') : 0.00;
            $no_of_applicant = $this->input->post('no_of_applicant') ? (int) $this->input->post('no_of_applicant') : 0;
            
            if (empty($date)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Date is required'
                    ]));
                return;
            }
            
            // Handle file upload
            $receipt = null;
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
                $upload_path = FCPATH . 'public/payment/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                $file_info = $_FILES['receipt'];
                $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode([
                            'success' => false,
                            'error' => 'Invalid file type. Only JPG, JPEG, PNG, and PDF files are allowed.'
                        ]));
                    return;
                }
                
                // Generate unique filename
                $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_path . $unique_filename;
                
                if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
                    $receipt = $unique_filename;
                    log_message('debug', 'DTO create - File uploaded successfully: ' . $unique_filename);
                } else {
                    log_message('error', 'DTO create - Failed to upload file');
                    $this->output
                        ->set_status_header(500)
                        ->set_content_type('application/json')
                        ->set_output(json_encode([
                            'success' => false,
                            'error' => 'Failed to upload receipt image'
                        ]));
                    return;
                }
            }
            
            $data = array(
                'date' => $date,
                'amount' => $amount,
                'pay_amount' => $pay_amount,
                'no_of_applicant' => $no_of_applicant,
                'receipt' => $receipt
            );
            
            $this->db->insert('dto', $data);
            
            if ($this->db->affected_rows() > 0) {
                $dto_id = $this->db->insert_id();
                $new_dto = $this->db->query("SELECT * FROM dto WHERE dto_id = ?", 
                    array($dto_id))->row_array();
                
                $this->output
                    ->set_status_header(201)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'dto' => $new_dto
                        ]
                    ]));
            } else {
                throw new Exception('Failed to create DTO');
            }
            
        } catch (Exception $e) {
            log_message('error', 'DTO create error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to create DTO',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function update($id) {
        try {
            // Check permission for updating DTOs
            if (!$this->check_permission('dto:update')) {
                log_message('debug', 'DTO update - Permission denied: dto:update');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to update DTOs.'
                    ]));
                return;
            }
            
            // Get form data
            log_message('debug', 'DTO update - POST data: ' . json_encode($_POST));
            log_message('debug', 'DTO update - FILES data: ' . json_encode($_FILES));
            log_message('debug', 'DTO update - REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
            
            $date = $this->input->post('date');
            $amount = $this->input->post('amount') ? (float) $this->input->post('amount') : 0.00;
            $pay_amount = $this->input->post('pay_amount') ? (float) $this->input->post('pay_amount') : 0.00;
            $no_of_applicant = $this->input->post('no_of_applicant') ? (int) $this->input->post('no_of_applicant') : 0;
            
            if (empty($date)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Date is required'
                    ]));
                return;
            }
            
            $existing = $this->db->query("SELECT * FROM dto WHERE dto_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'DTO not found'
                    ]));
                return;
            }
            
            // Handle file upload
            $receipt = $existing->receipt; // Keep existing file by default
            
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
                $upload_path = FCPATH . 'public/payment/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                $file_info = $_FILES['receipt'];
                $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode([
                            'success' => false,
                            'error' => 'Invalid file type. Only JPG, JPEG, PNG, and PDF files are allowed.'
                        ]));
                    return;
                }
                
                // Generate unique filename
                $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_path . $unique_filename;
                
                if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
                    // Delete old file if it exists
                    if ($existing->receipt && file_exists($upload_path . $existing->receipt)) {
                        unlink($upload_path . $existing->receipt);
                    }
                    
                    $receipt = $unique_filename;
                    log_message('debug', 'DTO update - File uploaded successfully: ' . $unique_filename);
                } else {
                    log_message('error', 'DTO update - Failed to upload file');
                    $this->output
                        ->set_status_header(500)
                        ->set_content_type('application/json')
                        ->set_output(json_encode([
                            'success' => false,
                            'error' => 'Failed to upload receipt image'
                        ]));
                    return;
                }
            }
            
            $data = array(
                'date' => $date,
                'amount' => $amount,
                'pay_amount' => $pay_amount,
                'no_of_applicant' => $no_of_applicant,
                'receipt' => $receipt
            );
            
            $this->db->where('dto_id', $id);
            $this->db->update('dto', $data);
            
            if ($this->db->affected_rows() >= 0) {
                $updated_dto = $this->db->query("SELECT * FROM dto WHERE dto_id = ?", 
                    array($id))->row_array();
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'dto' => $updated_dto
                        ]
                    ]));
            } else {
                throw new Exception('Failed to update DTO');
            }
            
        } catch (Exception $e) {
            log_message('error', 'DTO update error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to update DTO',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function delete($id) {
        try {
            // Check permission for deleting DTOs
            if (!$this->check_permission('dto:delete')) {
                log_message('debug', 'DTO delete - Permission denied: dto:delete');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to delete DTOs.'
                    ]));
                return;
            }
            
            $existing = $this->db->query("SELECT * FROM dto WHERE dto_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'DTO not found'
                    ]));
                return;
            }
            
            // Delete associated file if it exists
            if ($existing->receipt) {
                $upload_path = FCPATH . 'public/payment/';
                $file_path = $upload_path . $existing->receipt;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $this->db->where('dto_id', $id);
            $this->db->delete('dto');
            
            if ($this->db->affected_rows() > 0) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => 'DTO deleted successfully'
                    ]));
            } else {
                throw new Exception('Failed to delete DTO');
            }
            
        } catch (Exception $e) {
            log_message('error', 'DTO delete error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to delete DTO',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function get($id) {
        try {
            // Check permission for reading DTOs
            if (!$this->check_permission('dto:read')) {
                log_message('debug', 'DTO get - Permission denied: dto:read');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to read DTOs.'
                    ]));
                return;
            }
            
            $dto = $this->db->query("SELECT * FROM dto WHERE dto_id = ?", 
                array($id))->row_array();
            
            if (!$dto) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'DTO not found'
                    ]));
                return;
            }
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'data' => [
                        'dto' => $dto
                    ]
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'DTO get error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to get DTO',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function report() {
        try {
            // Check permission for reading DTOs (reports are read operations)
            if (!$this->check_permission('dto:read')) {
                log_message('debug', 'DTO report - Permission denied: dto:read');
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Permission denied. You do not have permission to read DTO reports.'
                    ]));
                return;
            }
            
            $fromDate = $this->input->get('from_date');
            $toDate = $this->input->get('to_date');
            
            if (!$fromDate || !$toDate) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'from_date and to_date parameters are required'
                    ]));
                return;
            }
            
            log_message('debug', 'DTO report - Date range: ' . $fromDate . ' to ' . $toDate);
            
            // Build the query with date filtering
            $query = "SELECT 
                        COUNT(*) as dto_count,
                        COALESCE(SUM(amount), 0) as total_amount,
                        COALESCE(SUM(pay_amount), 0) as total_pay_amount,
                        COALESCE(SUM(no_of_applicant), 0) as total_applicants
                      FROM dto 
                      WHERE date BETWEEN ? AND ?";
            
            $result = $this->db->query($query, array($fromDate, $toDate));
            
            if (!$result) {
                throw new Exception('Query failed: ' . $this->db->last_query());
            }
            
            $data = $result->row_array();
            
            log_message('debug', 'DTO report - Result: ' . json_encode($data));
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'data' => [
                        'dto_count' => (int) $data['dto_count'],
                        'total_amount' => (float) $data['total_amount'],
                        'total_pay_amount' => (float) $data['total_pay_amount'],
                        'total_applicants' => (int) $data['total_applicants']
                    ]
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'DTO report error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to generate DTO report',
                    'message' => $e->getMessage()
                ]));
        }
    }
}
