<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library(['form_validation', 'JWT_Library']);
        $this->load->model('User_model');
        
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        // Set JSON header
        header('Content-Type: application/json');
    }
    
    /**
     * Get comprehensive report data
     */
    public function index() {
        // Check if user has permission to view reports
        if (!$this->check_permission('reports:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $dateRange = $this->input->get('date_range') ?: 'this_month';
        $startDate = $this->input->get('start_date');
        $endDate = $this->input->get('end_date');
        
        // Set date range based on parameter
        $dateCondition = $this->getDateCondition($dateRange, $startDate, $endDate);
        
        try {
            // Get total applications
            $totalApplications = $this->getTotalApplications($dateCondition);
            
            // Get financial data
            $financialData = $this->getFinancialData($dateCondition);
            
            // Get applications by type
            $applicationsByType = $this->getApplicationsByType($dateCondition);
            
            // Get payment methods breakdown
            $paymentMethods = $this->getPaymentMethodsBreakdown($dateCondition);
            
            $reportData = [
                'totalApplications' => $totalApplications,
                'totalIncome' => $financialData['total_income'],
                'totalDues' => $financialData['total_dues'],
                'totalPaid' => $financialData['total_paid'],
                'applicationsByType' => $applicationsByType,
                'paymentMethods' => $paymentMethods,
                'dateRange' => $dateRange,
                'generatedAt' => date('Y-m-d H:i:s')
            ];
            
            $this->json_response(true, 'Report data retrieved successfully', $reportData);
            
        } catch (Exception $e) {
            log_message('error', 'Report generation error: ' . $e->getMessage());
            $this->json_response(false, 'Failed to generate report', null, 500);
        }
    }
    
    /**
     * Get financial summary
     */
    public function financial() {
        if (!$this->check_permission('reports:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $dateRange = $this->input->get('date_range') ?: 'this_month';
        $dateCondition = $this->getDateCondition($dateRange);
        
        try {
            $financialData = $this->getFinancialData($dateCondition);
            $this->json_response(true, 'Financial data retrieved successfully', $financialData);
        } catch (Exception $e) {
            $this->json_response(false, 'Failed to retrieve financial data', null, 500);
        }
    }
    
    /**
     * Get applications summary
     */
    public function applications() {
        if (!$this->check_permission('reports:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $dateRange = $this->input->get('date_range') ?: 'this_month';
        $dateCondition = $this->getDateCondition($dateRange);
        
        try {
            $totalApplications = $this->getTotalApplications($dateCondition);
            $applicationsByType = $this->getApplicationsByType($dateCondition);
            
            $data = [
                'totalApplications' => $totalApplications,
                'applicationsByType' => $applicationsByType
            ];
            
            $this->json_response(true, 'Applications data retrieved successfully', $data);
        } catch (Exception $e) {
            $this->json_response(false, 'Failed to retrieve applications data', null, 500);
        }
    }
    
    /**
     * Get income report for specific date range
     */
    public function income() {
        if (!$this->check_permission('reports:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $fromDate = $this->input->get('from_date');
        $toDate = $this->input->get('to_date');
        
        if (!$fromDate || !$toDate) {
            $this->json_response(false, 'from_date and to_date parameters are required', null, 400);
            return;
        }
        
        try {
            // Validate date format
            $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
            $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
            
            if (!$fromDateTime || !$toDateTime) {
                $this->json_response(false, 'Invalid date format. Use YYYY-MM-DD', null, 400);
                return;
            }
            
            // Ensure to_date is not before from_date
            if ($toDateTime < $fromDateTime) {
                $this->json_response(false, 'to_date cannot be before from_date', null, 400);
                return;
            }
            
            // Create date condition for the specific range
            $dateCondition = "created_at BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
            
            // Get income data
            $this->db->select('
                SUM(amount) as total_income,
                COUNT(*) as application_count
            ');
            $this->db->from('applications');
            $this->db->where($dateCondition);
            $query = $this->db->get();
            $result = $query->row();
            
            $data = [
                'total_income' => (float)($result->total_income ?: 0),
                'application_count' => (int)$result->application_count,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'period' => $fromDate . ' to ' . $toDate
            ];
            
            $this->json_response(true, 'Income data retrieved successfully', $data);
            
        } catch (Exception $e) {
            log_message('error', 'Income report error: ' . $e->getMessage());
            $this->json_response(false, 'Failed to retrieve income data', null, 500);
        }
    }
    
    /**
     * Get dues report for specific date range
     */
    public function dues() {
        if (!$this->check_permission('reports:read')) {
            $this->json_response(false, 'Access denied. Insufficient permissions.', null, 403);
            return;
        }
        
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        $fromDate = $this->input->get('from_date');
        $toDate = $this->input->get('to_date');
        
        if (!$fromDate || !$toDate) {
            $this->json_response(false, 'from_date and to_date parameters are required', null, 400);
            return;
        }
        
        try {
            // Validate date format
            $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
            $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
            
            if (!$fromDateTime || !$toDateTime) {
                $this->json_response(false, 'Invalid date format. Use YYYY-MM-DD', null, 400);
                return;
            }
            
            // Ensure to_date is not before from_date
            if ($toDateTime < $fromDateTime) {
                $this->json_response(false, 'to_date cannot be before from_date', null, 400);
                return;
            }
            
            // Create date condition for the specific range
            $dateCondition = "created_at BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
            
            // Get dues data - only applications where pay_amount < amount
            $this->db->select('
                SUM(amount - pay_amount) as total_dues,
                COUNT(*) as application_count
            ');
            $this->db->from('applications');
            $this->db->where($dateCondition);
            $this->db->where('pay_amount < amount'); // Only applications with outstanding dues
            $query = $this->db->get();
            $result = $query->row();
            
            $data = [
                'total_dues' => (float)($result->total_dues ?: 0),
                'application_count' => (int)$result->application_count,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'period' => $fromDate . ' to ' . $toDate
            ];
            
            $this->json_response(true, 'Dues data retrieved successfully', $data);
            
        } catch (Exception $e) {
            log_message('error', 'Dues report error: ' . $e->getMessage());
            $this->json_response(false, 'Failed to retrieve dues data', null, 500);
        }
    }
    
    /**
     * Search customers with flexible criteria
     */
    public function customers() {
        if ($this->input->method() !== 'get') {
            $this->json_response(false, 'Method not allowed', null, 405);
            return;
        }
        
        try {
            $fromDate = $this->input->get('from_date');
            $toDate = $this->input->get('to_date');
            $name = $this->input->get('name');
            $phone = $this->input->get('phone');
            $licenseNo = $this->input->get('license_no');
            $applicationNo = $this->input->get('application_no');
            
            // Check if at least one search parameter is provided
            if (!$fromDate && !$toDate && !$name && !$phone && !$licenseNo && !$applicationNo) {
                $this->json_response(false, 'At least one search parameter is required', null, 400);
                return;
            }
            
            $this->db->select('
                id,
                application_no,
                name,
                contact_no as phone,
                license_no,
                amount,
                pay_amount,
                created_at,
                updated_at
            ');
            $this->db->from('applications');
            
            // Apply date filter if provided
            if ($fromDate && $toDate) {
                // Validate date format
                $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
                $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
                
                if (!$fromDateTime || !$toDateTime) {
                    $this->json_response(false, 'Invalid date format. Use YYYY-MM-DD', null, 400);
                    return;
                }
                
                if ($toDateTime < $fromDateTime) {
                    $this->json_response(false, 'to_date cannot be before from_date', null, 400);
                    return;
                }
                
                $this->db->where("created_at BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");
            } elseif ($fromDate) {
                $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
                if (!$fromDateTime) {
                    $this->json_response(false, 'Invalid from_date format. Use YYYY-MM-DD', null, 400);
                    return;
                }
                $this->db->where("created_at >= '$fromDate 00:00:00'");
            } elseif ($toDate) {
                $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
                if (!$toDateTime) {
                    $this->json_response(false, 'Invalid to_date format. Use YYYY-MM-DD', null, 400);
                    return;
                }
                $this->db->where("created_at <= '$toDate 23:59:59'");
            }
            
            // Apply other filters
            if ($name) {
                $this->db->like('name', $name);
            }
            if ($phone) {
                $this->db->like('contact_no', $phone);
            }
            if ($licenseNo) {
                $this->db->like('license_no', $licenseNo);
            }
            if ($applicationNo) {
                $this->db->like('application_no', $applicationNo);
            }
            
            // Order by created_at descending
            $this->db->order_by('created_at', 'DESC');
            
            $query = $this->db->get();
            $results = $query->result_array();
            
            // Convert to proper data types and add missing fields
            foreach ($results as &$result) {
                $result['id'] = (int)$result['id'];
                $result['amount'] = (float)($result['amount'] ?: 0);
                $result['pay_amount'] = (float)($result['pay_amount'] ?: 0);
                $result['email'] = ''; // Add empty email field since it doesn't exist in applications table
                $result['status'] = 'pending'; // Default status since status column doesn't exist
            }
            
            $this->json_response(true, 'Customer search completed successfully', $results);
            
        } catch (Exception $e) {
            log_message('error', 'Customer search error: ' . $e->getMessage());
            $this->json_response(false, 'Failed to search customers: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * Get total applications count
     */
    private function getTotalApplications($dateCondition) {
        $this->db->select('COUNT(*) as total');
        $this->db->from('applications');
        if ($dateCondition) {
            $this->db->where($dateCondition);
        }
        $query = $this->db->get();
        $result = $query->row();
        return (int)$result->total;
    }
    
    /**
     * Get financial data
     */
    private function getFinancialData($dateCondition) {
        $this->db->select('
            SUM(amount) as total_income,
            SUM(CASE WHEN pay_amount < amount THEN amount - pay_amount ELSE 0 END) as total_dues,
            SUM(pay_amount) as total_paid
        ');
        $this->db->from('applications');
        if ($dateCondition) {
            $this->db->where($dateCondition);
        }
        $query = $this->db->get();
        $result = $query->row();
        
        return [
            'total_income' => (float)$result->total_income,
            'total_dues' => (float)$result->total_dues,
            'total_paid' => (float)$result->total_paid
        ];
    }
    
    /**
     * Get applications by license type
     */
    private function getApplicationsByType($dateCondition) {
        $this->db->select('
            license_type as type,
            COUNT(*) as count,
            SUM(amount) as amount
        ');
        $this->db->from('applications');
        if ($dateCondition) {
            $this->db->where($dateCondition);
        }
        $this->db->group_by('license_type');
        $this->db->order_by('count', 'DESC');
        $query = $this->db->get();
        
        return $query->result_array();
    }
    
    /**
     * Get payment methods breakdown
     */
    private function getPaymentMethodsBreakdown($dateCondition) {
        $this->db->select('
            mode_of_payment as method,
            COUNT(*) as count,
            SUM(amount) as amount
        ');
        $this->db->from('applications');
        if ($dateCondition) {
            $this->db->where($dateCondition);
        }
        $this->db->group_by('mode_of_payment');
        $this->db->order_by('amount', 'DESC');
        $query = $this->db->get();
        
        return $query->result_array();
    }
    
    /**
     * Get date condition based on range
     */
    private function getDateCondition($dateRange, $startDate = null, $endDate = null) {
        if ($startDate && $endDate) {
            return "created_at BETWEEN '$startDate' AND '$endDate'";
        }
        
        switch ($dateRange) {
            case 'today':
                return "DATE(created_at) = CURDATE()";
            case 'yesterday':
                return "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'this_week':
                return "created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
            case 'last_week':
                return "created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
            case 'this_month':
                return "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
            case 'last_month':
                return "MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            case 'this_year':
                return "YEAR(created_at) = YEAR(CURDATE())";
            case 'last_year':
                return "YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
            default:
                return null;
        }
    }
    
    /**
     * Check if user has permission
     */
    private function check_permission($permission) {
        try {
            // Get Authorization header
            $token = $this->input->get_request_header('Authorization');
            if (!$token) {
                return false;
            }
            
            // Remove 'Bearer ' prefix
            $token = str_replace('Bearer ', '', $token);
            
            // Validate token using JWT library
            $decoded = $this->jwt_library->validate_token($token);
            if (!$decoded) {
                return false;
            }
            
            // Get user's role
            $user_id = isset($decoded->user_id) ? $decoded->user_id : (isset($decoded->id) ? $decoded->id : null);
            if (!$user_id) {
                return false;
            }
            
            $user_data = $this->User_model->get_user_by_id($user_id);
            if (!$user_data || $user_data->status !== 'active') {
                return false;
            }
            
            // For now, allow all authenticated users to view reports
            // You can add role-based permission checking here
            return true;
            
        } catch (Exception $e) {
            log_message('error', 'Permission check error: ' . $e->getMessage());
            return false;
        }
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
