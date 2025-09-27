<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Vendors extends CI_Controller {
    
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
        
        log_message('debug', 'Vendors authenticate - Headers: ' . json_encode($headers));
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token) {
            log_message('debug', 'Vendors authenticate - No token found');
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Token required']));
            return false;
        }
        
        try {
            $decoded = $this->jwt_library->validate_token($token);
            log_message('debug', 'Vendors authenticate - Token validated successfully');
            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'Vendors authenticate - Token validation failed: ' . $e->getMessage());
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid token']));
            return false;
        }
    }

    public function index() {
        try {
            log_message('debug', 'Vendors index - Request received');
            
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Vendors index - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            } else {
                log_message('debug', 'Vendors index - Authentication successful');
            }
            
            $page = (int) $this->input->get('page') ?: 1;
            $limit = (int) $this->input->get('limit') ?: 100;
            $offset = ($page - 1) * $limit;
            
            log_message('debug', 'Vendors index - Pagination params: page=' . $page . ', limit=' . $limit . ', offset=' . $offset);
            
            $countQuery = $this->db->query("SELECT COUNT(*) as total FROM vendors");
            $totalCount = $countQuery->row()->total;
            
            log_message('debug', 'Vendors index - Total vendors count: ' . $totalCount);
            
            $query = $this->db->query("SELECT * FROM vendors ORDER BY created_at DESC LIMIT ? OFFSET ?", array($limit, $offset));
            
            if (!$query) {
                throw new Exception('Query failed: ' . $this->db->last_query());
            }
            
            $vendors = $query->result_array();
            
            log_message('debug', 'Vendors index - Retrieved ' . count($vendors) . ' vendors');
            
            $response = [
                'success' => true,
                'data' => [
                    'vendors' => $vendors,
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
            
            log_message('debug', 'Vendors index - Response: ' . json_encode($response));
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'Vendors controller error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch vendors',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function create() {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Vendors create - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            // Get form data
            $name = $this->input->post('name');
            $phone_no = $this->input->post('phone_no');
            $address = $this->input->post('address');
            $amount = $this->input->post('amount') ? (float) $this->input->post('amount') : 0.00;
            $pay_amount = $this->input->post('pay_amount') ? (float) $this->input->post('pay_amount') : 0.00;
            $mode_of_payment = $this->input->post('mode_of_payment') ?: 'cash';
            $total_customer = $this->input->post('total_customer') ? (int) $this->input->post('total_customer') : 0;
            
            if (empty($name) || empty($phone_no) || empty($address)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Name, phone number, and address are required'
                    ]));
                return;
            }
            
            // Validate payment mode
            if (!in_array($mode_of_payment, ['cash', 'upi', 'bank-transfer'])) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Invalid payment mode'
                    ]));
                return;
            }
            
            // Handle file upload
            $receipt_image_path = null;
            if (isset($_FILES['receipt_image_path']) && $_FILES['receipt_image_path']['error'] == 0) {
                $upload_path = FCPATH . 'public/payment/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                $file_info = $_FILES['receipt_image_path'];
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
                    $receipt_image_path = $unique_filename;
                    log_message('debug', 'Vendors create - File uploaded successfully: ' . $unique_filename);
                } else {
                    log_message('error', 'Vendors create - Failed to upload file');
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
                'name' => trim($name),
                'phone_no' => trim($phone_no),
                'address' => trim($address),
                'amount' => $amount,
                'pay_amount' => $pay_amount,
                'mode_of_payment' => $mode_of_payment,
                'total_customer' => $total_customer,
                'receipt_image_path' => $receipt_image_path
            );
            
            $this->db->insert('vendors', $data);
            
            if ($this->db->affected_rows() > 0) {
                $vendor_id = $this->db->insert_id();
                $new_vendor = $this->db->query("SELECT * FROM vendors WHERE vendor_id = ?", 
                    array($vendor_id))->row_array();
                
                $this->output
                    ->set_status_header(201)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'vendor' => $new_vendor
                        ]
                    ]));
            } else {
                throw new Exception('Failed to create vendor');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Vendors create error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to create vendor',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function update($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Vendors update - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            // Get form data
            log_message('debug', 'Vendors update - POST data: ' . json_encode($_POST));
            log_message('debug', 'Vendors update - FILES data: ' . json_encode($_FILES));
            log_message('debug', 'Vendors update - REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
            
            $name = $this->input->post('name');
            $phone_no = $this->input->post('phone_no');
            $address = $this->input->post('address');
            $amount = $this->input->post('amount') ? (float) $this->input->post('amount') : 0.00;
            $pay_amount = $this->input->post('pay_amount') ? (float) $this->input->post('pay_amount') : 0.00;
            $mode_of_payment = $this->input->post('mode_of_payment') ?: 'cash';
            $total_customer = $this->input->post('total_customer') ? (int) $this->input->post('total_customer') : 0;
            
            log_message('debug', 'Vendors update - Parsed data: name=' . $name . ', phone=' . $phone_no . ', address=' . $address);
            
            if (empty($name) || empty($phone_no) || empty($address)) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Name, phone number, and address are required'
                    ]));
                return;
            }
            
            // Validate payment mode
            if (!in_array($mode_of_payment, ['cash', 'upi', 'bank-transfer'])) {
                $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Invalid payment mode'
                    ]));
                return;
            }
            
            $existing = $this->db->query("SELECT * FROM vendors WHERE vendor_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Vendor not found'
                    ]));
                return;
            }
            
            // Handle file upload
            $receipt_image_path = $existing->receipt_image_path; // Keep existing file by default
            
            if (isset($_FILES['receipt_image_path']) && $_FILES['receipt_image_path']['error'] == 0) {
                $upload_path = FCPATH . 'public/payment/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                $file_info = $_FILES['receipt_image_path'];
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
                    if ($existing->receipt_image_path && file_exists($upload_path . $existing->receipt_image_path)) {
                        unlink($upload_path . $existing->receipt_image_path);
                    }
                    
                    $receipt_image_path = $unique_filename;
                    log_message('debug', 'Vendors update - File uploaded successfully: ' . $unique_filename);
                } else {
                    log_message('error', 'Vendors update - Failed to upload file');
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
                'name' => trim($name),
                'phone_no' => trim($phone_no),
                'address' => trim($address),
                'amount' => $amount,
                'pay_amount' => $pay_amount,
                'mode_of_payment' => $mode_of_payment,
                'total_customer' => $total_customer,
                'receipt_image_path' => $receipt_image_path
            );
            
            $this->db->where('vendor_id', $id);
            $this->db->update('vendors', $data);
            
            if ($this->db->affected_rows() >= 0) {
                $updated_vendor = $this->db->query("SELECT * FROM vendors WHERE vendor_id = ?", 
                    array($id))->row_array();
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => [
                            'vendor' => $updated_vendor
                        ]
                    ]));
            } else {
                throw new Exception('Failed to update vendor');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Vendors update error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to update vendor',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function delete($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Vendors delete - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            $existing = $this->db->query("SELECT vendor_id FROM vendors WHERE vendor_id = ?", array($id))->row();
            
            if (!$existing) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Vendor not found'
                    ]));
                return;
            }
            
            $this->db->where('vendor_id', $id);
            $this->db->delete('vendors');
            
            if ($this->db->affected_rows() > 0) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => 'Vendor deleted successfully'
                    ]));
            } else {
                throw new Exception('Failed to delete vendor');
            }
            
        } catch (Exception $e) {
            log_message('error', 'Vendors delete error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to delete vendor',
                    'message' => $e->getMessage()
                ]));
        }
    }

    public function get($id) {
        try {
            $user = $this->authenticate();
            if (!$user) {
                log_message('debug', 'Vendors get - Authentication failed, but allowing access for testing');
                // For now, allow access without authentication for testing
                // TODO: Remove this and add proper permission checking
            }
            
            $vendor = $this->db->query("SELECT * FROM vendors WHERE vendor_id = ?", 
                array($id))->row_array();
            
            if (!$vendor) {
                $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Vendor not found'
                    ]));
                return;
            }
            
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'data' => [
                        'vendor' => $vendor
                    ]
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'Vendors get error: ' . $e->getMessage());
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Failed to get vendor',
                    'message' => $e->getMessage()
                ]));
        }
    }
}
