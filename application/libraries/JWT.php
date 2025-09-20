<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class JWT_Library {
    
    private $key;
    private $algorithm = 'HS256';
    
    public function __construct() {
        $this->CI =& get_instance();
        $this->key = $this->CI->config->item('jwt_key');
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
        $decoded = $this->decode($token);
        if ($decoded && isset($decoded->exp) && $decoded->exp > time()) {
            return $decoded;
        }
        return false;
    }
}
