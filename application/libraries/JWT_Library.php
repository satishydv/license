<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class JWT_Library {
    
    private $key;
    private $algorithm = 'HS256';
    
    public function __construct() {
        $this->CI =& get_instance();
        
        // Try to get JWT key from config, fallback to default
        try {
            if (isset($this->CI->config) && method_exists($this->CI->config, 'item')) {
                $this->key = $this->CI->config->item('jwt_key');
            } else {
                $this->key = 'your-secret-key-change-this-in-production';
            }
        } catch (Exception $e) {
            $this->key = 'your-secret-key-change-this-in-production';
        }
        
        // Ensure we have a key
        if (empty($this->key)) {
            $this->key = 'your-secret-key-change-this-in-production';
        }
    }
    
    public function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->key, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public function decode($token) {
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return false;
            }
            
            $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
            $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
            $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[2]));
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $this->key, true);
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            return json_decode($payload);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function validate_token($token) {
        log_message('debug', 'JWT validate_token called with token: ' . substr($token, 0, 20) . '...');
        
        $decoded = $this->decode($token);
        if (!$decoded) {
            log_message('debug', 'JWT decode failed');
            return false;
        }
        
        log_message('debug', 'JWT decoded successfully: ' . json_encode($decoded));
        
        if (!isset($decoded->exp)) {
            log_message('debug', 'JWT token has no expiration');
            return false;
        }
        
        if ($decoded->exp <= time()) {
            log_message('debug', 'JWT token expired. Exp: ' . $decoded->exp . ', Current: ' . time());
            return false;
        }
        
        log_message('debug', 'JWT token is valid');
        return $decoded;
    }
}
