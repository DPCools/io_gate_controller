<?php
/**
 * AjaxHandler.php
 * 
 * Utility class for handling AJAX responses
 */

namespace GateController\Utils;

class AjaxHandler {
    /**
     * Send a JSON response and exit
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send a success response
     * 
     * @param string $message Success message
     * @param array $data Additional data
     * @return void
     */
    public static function sendSuccess($message, $data = []) {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        self::sendJsonResponse($response);
    }
    
    /**
     * Send an error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $data Additional data
     * @return void
     */
    public static function sendError($message, $statusCode = 400, $data = []) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        self::sendJsonResponse($response, $statusCode);
    }
    
    /**
     * Check if the request is an AJAX request
     * 
     * @return bool
     */
    public static function isAjaxRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
               (!empty($_SERVER['HTTP_ACCEPT']) && 
                strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Get JSON input from request body
     * 
     * @return array
     */
    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
