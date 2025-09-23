<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Application extends CI_Controller {

	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->helper(['url', 'file']);
		$this->load->library(['form_validation', 'upload']);

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
	}

	public function create() {
		if ($this->input->method() !== 'post') {
			return $this->json_response(false, 'Method not allowed', null, 405);
		}

		// Validate basic required fields
		$this->form_validation->set_rules('name', 'Name', 'required');
		$this->form_validation->set_rules('father_name', 'Father Name', 'required');
		$this->form_validation->set_rules('contact_no', 'Contact Number', 'required');
		$this->form_validation->set_rules('dob', 'Date of Birth', 'required');
		$this->form_validation->set_rules('blood_group', 'Blood Group', 'required');
		$this->form_validation->set_rules('state', 'State', 'required');
		$this->form_validation->set_rules('city', 'City', 'required');
		$this->form_validation->set_rules('license_type', 'License Type', 'required');
		$this->form_validation->set_rules('application_no', 'Application Number', 'required');
		$this->form_validation->set_rules('license_no', 'License Number', 'required');
		$this->form_validation->set_rules('issue_date', 'Issue Date', 'required');
		$this->form_validation->set_rules('expiry_date', 'Expiry Date', 'required');
		$this->form_validation->set_rules('cover_class', 'Cover Class', 'required');
		$this->form_validation->set_rules('amount', 'Amount', 'required');
		// Pay amount is optional, but if provided it must be numeric
		$this->form_validation->set_rules('pay_amount', 'Pay Amount', 'numeric');
		$this->form_validation->set_rules('mode_of_payment', 'Mode of Payment', 'required');

		if ($this->form_validation->run() === FALSE) {
			$errors = $this->form_validation->error_array();
			return $this->json_response(false, 'Validation failed', $errors, 400);
		}

		$licenseUploadPath = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'license' . DIRECTORY_SEPARATOR;
		$paymentUploadPath = FCPATH . 'public' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR;

		if (!is_dir($licenseUploadPath) || !is_dir($paymentUploadPath)) {
			return $this->json_response(false, 'Upload directories not found', null, 500);
		}

		$licensePathRel = null;
		$paymentPathRel = null;

		// Upload license attachment (field: license_attachment)
		if (isset($_FILES['license_attachment']) && $_FILES['license_attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
			$config = [
				'upload_path' => $licenseUploadPath,
				'allowed_types' => 'pdf|jpg|jpeg|png|doc|docx',
				'max_size' => 5120, // 5MB
				'file_ext_tolower' => TRUE,
				'encrypt_name' => TRUE,
			];
			$this->upload->initialize($config);
			if (!$this->upload->do_upload('license_attachment')) {
				return $this->json_response(false, $this->upload->display_errors('', ''), null, 400);
			}
			$data = $this->upload->data();
			$licensePathRel = 'public/license/' . $data['file_name'];
		}

		// Upload payment receipt (field: payment_receipt)
		if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
			$config = [
				'upload_path' => $paymentUploadPath,
				'allowed_types' => 'pdf|jpg|jpeg|png',
				'max_size' => 5120,
				'file_ext_tolower' => TRUE,
				'encrypt_name' => TRUE,
			];
			$this->upload->initialize($config);
			if (!$this->upload->do_upload('payment_receipt')) {
				// roll back first upload if needed
				if ($licensePathRel) {
					@unlink($licenseUploadPath . basename($licensePathRel));
				}
				return $this->json_response(false, $this->upload->display_errors('', ''), null, 400);
			}
			$data = $this->upload->data();
			$paymentPathRel = 'public/payment/' . $data['file_name'];
		}

		$payload = [
			'name' => $this->input->post('name'),
			'father_name' => $this->input->post('father_name'),
			'contact_no' => $this->input->post('contact_no'),
			'dob' => $this->input->post('dob'),
			'blood_group' => $this->input->post('blood_group'),
			'state' => $this->input->post('state'),
			'city' => $this->input->post('city'),
			'license_type' => $this->input->post('license_type'),
			'application_no' => $this->input->post('application_no'),
			'license_no' => $this->input->post('license_no'),
			'issue_date' => $this->input->post('issue_date'),
			'expiry_date' => $this->input->post('expiry_date'),
			'cover_class' => $this->input->post('cover_class'),
			'amount' => $this->input->post('amount'),
			'pay_amount' => $this->input->post('pay_amount'),
			'mode_of_payment' => $this->input->post('mode_of_payment'),
			'license_attachment_path' => $licensePathRel,
			'payment_receipt_path' => $paymentPathRel,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		];

		// Insert
		$inserted = $this->db->insert('applications', $payload);
		if (!$inserted) {
			// cleanup files on failure
			if ($licensePathRel) { @unlink($licenseUploadPath . basename($licensePathRel)); }
			if ($paymentPathRel) { @unlink($paymentUploadPath . basename($paymentPathRel)); }
			return $this->json_response(false, 'Database insert failed', null, 500);
		}

		return $this->json_response(true, 'Application created successfully', [ 'id' => $this->db->insert_id() ], 201);
	}

	public function index() {
		if ($this->input->method() !== 'get') {
			return $this->json_response(false, 'Method not allowed', null, 405);
		}

		$licenseType = $this->input->get('license_type');
		if ($licenseType) {
			$this->db->where('license_type', $licenseType);
		}
		$this->db->order_by('id', 'DESC');
		$query = $this->db->get('applications');
		$rows = $query->result_array();
		return $this->json_response(true, 'OK', $rows, 200);
	}

	public function show($id) {
		if ($this->input->method() !== 'get') {
			return $this->json_response(false, 'Method not allowed', null, 405);
		}

		$query = $this->db->get_where('applications', ['id' => $id]);
		$application = $query->row_array();
		
		if (!$application) {
			return $this->json_response(false, 'Application not found', null, 404);
		}

		return $this->json_response(true, 'OK', $application, 200);
	}

	public function update($id) {
		if ($this->input->method() !== 'post') {
			return $this->json_response(false, 'Method not allowed', null, 405);
		}

		// Validate basic required fields
		$this->form_validation->set_rules('name', 'Name', 'required');
		$this->form_validation->set_rules('father_name', 'Father Name', 'required');
		$this->form_validation->set_rules('contact_no', 'Contact Number', 'required');
		$this->form_validation->set_rules('dob', 'Date of Birth', 'required');
		$this->form_validation->set_rules('blood_group', 'Blood Group', 'required');
		$this->form_validation->set_rules('state', 'State', 'required');
		$this->form_validation->set_rules('city', 'City', 'required');
		$this->form_validation->set_rules('license_type', 'License Type', 'required');
		$this->form_validation->set_rules('application_no', 'Application Number', 'required');
		$this->form_validation->set_rules('license_no', 'License Number', 'required');
		$this->form_validation->set_rules('issue_date', 'Issue Date', 'required');
		$this->form_validation->set_rules('expiry_date', 'Expiry Date', 'required');
		$this->form_validation->set_rules('cover_class', 'Cover Class', 'required');
		$this->form_validation->set_rules('amount', 'Amount', 'required');
		$this->form_validation->set_rules('pay_amount', 'Pay Amount', 'numeric');
		$this->form_validation->set_rules('mode_of_payment', 'Mode of Payment', 'required');

		if ($this->form_validation->run() === FALSE) {
			$errors = $this->form_validation->error_array();
			return $this->json_response(false, 'Validation failed', $errors, 400);
		}

		$payload = [
			'name' => $this->input->post('name'),
			'father_name' => $this->input->post('father_name'),
			'contact_no' => $this->input->post('contact_no'),
			'dob' => $this->input->post('dob'),
			'blood_group' => $this->input->post('blood_group'),
			'state' => $this->input->post('state'),
			'city' => $this->input->post('city'),
			'license_type' => $this->input->post('license_type'),
			'application_no' => $this->input->post('application_no'),
			'license_no' => $this->input->post('license_no'),
			'issue_date' => $this->input->post('issue_date'),
			'expiry_date' => $this->input->post('expiry_date'),
			'cover_class' => $this->input->post('cover_class'),
			'amount' => $this->input->post('amount'),
			'pay_amount' => $this->input->post('pay_amount'),
			'mode_of_payment' => $this->input->post('mode_of_payment'),
			'updated_at' => date('Y-m-d H:i:s')
		];

		$this->db->where('id', $id);
		$updated = $this->db->update('applications', $payload);
		if (!$updated) {
			return $this->json_response(false, 'Database update failed', null, 500);
		}

		return $this->json_response(true, 'Application updated successfully', null, 200);
	}

	public function delete($id) {
		if ($this->input->method() !== 'delete') {
			return $this->json_response(false, 'Method not allowed', null, 405);
		}

		// Get application to delete file paths
		$query = $this->db->get_where('applications', ['id' => $id]);
		$application = $query->row_array();
		
		if (!$application) {
			return $this->json_response(false, 'Application not found', null, 404);
		}

		// Delete the record
		$this->db->where('id', $id);
		$deleted = $this->db->delete('applications');
		if (!$deleted) {
			return $this->json_response(false, 'Database delete failed', null, 500);
		}

		// Clean up files if they exist
		if ($application['license_attachment_path']) {
			@unlink(FCPATH . $application['license_attachment_path']);
		}
		if ($application['payment_receipt_path']) {
			@unlink(FCPATH . $application['payment_receipt_path']);
		}

		return $this->json_response(true, 'Application deleted successfully', null, 200);
	}

	private function json_response($success, $message, $data = null, $http_code = 200) {
		http_response_code($http_code);
		header('Content-Type: application/json');
		$response = [ 'success' => $success, 'message' => $message ];
		if ($data !== null) { $response['data'] = $data; }
		echo json_encode($response);
		exit;
	}
}


