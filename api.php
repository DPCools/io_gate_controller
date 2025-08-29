<?php
/**
 * api.php
 * 
 * API endpoint for external systems to interact with the Gate Controller
 */

$config = require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/controllers/ApiController.php';
require_once __DIR__ . '/src/models/Gate.php';
require_once __DIR__ . '/src/utils/AuditLogger.php';

use GateController\Controllers\ApiController;
// use GateController\Controllers\GateController; // Not needed for API fallback

// Set content type to JSON
header('Content-Type: application/json');

// Connect to database
try {
    $dbFile = $config['db']['file'];
    $db = new PDO("sqlite:{$dbFile}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    $debugMode = isset($config['app']['mode']) && $config['app']['mode'] === 'debug';
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $debugMode ? $e->getMessage() : null
    ]);
    exit;
}

// Initialize controller (use fallback mode without GateController to avoid auth requirement)
$apiController = new ApiController($db, $config, null);

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Normalize leading segment so both /api/health and /api.php/health work
if (isset($pathParts[0]) && ($pathParts[0] === 'api' || $pathParts[0] === 'api.php')) {
    array_shift($pathParts);
}

// Extract API key from header or query parameter
$apiKey = null;

// Check Authorization header (Bearer token)
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $apiKey = $matches[1];
    }
}

// If no Authorization header, check X-API-Key header
if (!$apiKey && isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
}

// If still no API key, check query parameter
if (!$apiKey && isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

// Define the API endpoint from path or fallback to query parameter
$endpoint = $pathParts[0] ?? '';
if ($endpoint === '' && isset($_GET['endpoint'])) {
    $endpoint = trim($_GET['endpoint']);
}

// Process the request
$response = null;

switch ($endpoint) {
    case 'trigger':
        // Trigger a gate by name
        if ($method !== 'POST') {
            http_response_code(405); // Method Not Allowed
            $response = [
                'success' => false,
                'message' => 'Method not allowed. Use POST.'
            ];
            break;
        }
        
        // Get gate name from URL or POST body
        $gateName = $pathParts[1] ?? null;
        
        // If not in URL, check POST body
        if (!$gateName) {
            $input = json_decode(file_get_contents('php://input'), true);
            $gateName = $input['gate'] ?? null;
        }
        
        // If still no gate name, check query parameter
        if (!$gateName) {
            $gateName = $_GET['gate'] ?? $_POST['gate'] ?? null;
        }
        
        $response = $apiController->triggerGate($apiKey, $gateName);
        break;
        
    case 'gates':
        // List all available gates
        if ($method !== 'GET') {
            http_response_code(405); // Method Not Allowed
            $response = [
                'success' => false,
                'message' => 'Method not allowed. Use GET.'
            ];
            break;
        }
        
        $response = $apiController->getGates($apiKey);
        break;
        
    case 'health':
        // Health check endpoint (no authentication required)
        $response = [
            'success' => true,
            'status' => 'operational',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        break;
        
    default:
        http_response_code(404);
        $response = [
            'success' => false,
            'message' => 'Endpoint not found'
        ];
        break;
}

// Return JSON response
echo json_encode($response);
exit;
