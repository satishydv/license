<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cors {
    
    public function handle_cors() {
        // Get CodeIgniter instance
        $CI =& get_instance();
        $CI->load->config('env');
        
        // Get CORS configuration from environment
        $allowed_origins = $CI->config->item('cors_allowed_origins');
        $allowed_methods = $CI->config->item('cors_allowed_methods');
        $allowed_headers = $CI->config->item('cors_allowed_headers');
        $allow_credentials = $CI->config->item('cors_allow_credentials');
        $max_age = $CI->config->item('cors_max_age');
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        // Set CORS origin header
        if ($allowed_origins === '*') {
            header("Access-Control-Allow-Origin: *");
        } else {
            $origins_array = explode(',', $allowed_origins);
            if (in_array($origin, $origins_array)) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }
        
        // Set CORS headers
        header("Access-Control-Allow-Methods: $allowed_methods");
        header("Access-Control-Allow-Headers: $allowed_headers");
        header("Access-Control-Allow-Credentials: $allow_credentials");
        header("Access-Control-Max-Age: $max_age");
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}
