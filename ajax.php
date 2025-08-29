<?php
/**
 * ajax.php
 * 
 * AJAX endpoint handler for the Gate Controller UI
 */

$config = require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/controllers/AuthController.php';
require_once __DIR__ . '/src/controllers/DeviceController.php';
require_once __DIR__ . '/src/controllers/GateController.php';
require_once __DIR__ . '/src/utils/AjaxHandler.php';

use GateController\Controllers\AuthController;
use GateController\Controllers\DeviceController;
use GateController\Controllers\GateController;
use GateController\Utils\AjaxHandler;

// Set error reporting
$debugMode = isset($config['app']['mode']) && $config['app']['mode'] === 'debug';
if (!$debugMode) {
    error_reporting(0);
}

// Connect to database
try {
    $dbFile = $config['db']['file'];
    $db = new PDO("sqlite:{$dbFile}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    AjaxHandler::sendError('Database connection failed', 500);
}

// Initialize controllers
$authController = new AuthController($db, $config);
$deviceController = new DeviceController($db, $config, $authController);
$gateController = new GateController($db, $config, $authController);

// Check authentication
if (!$authController->isAuthenticated()) {
    AjaxHandler::sendError('Authentication required', 401);
}

// Process the action parameter
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Process AJAX requests
switch ($action) {
    case 'test_device_connection':
        // Test connection to a device
        $deviceId = $_POST['device_id'] ?? $_GET['device_id'] ?? null;
        
        if (!$deviceId) {
            AjaxHandler::sendError('Device ID is required');
        }
        
        $result = $deviceController->testConnection($deviceId);
        if ($result['success']) {
            AjaxHandler::sendSuccess('Connection successful', $result);
        } else {
            AjaxHandler::sendError($result['message'] ?? 'Connection failed', 400, $result);
        }
        break;
        
    case 'get_trigger_stats':
        // Get trigger counts per device for day/month/year and by source (web vs api)
        $today = date('Y-m-d');
        $month = date('Y-m');
        $year = date('Y');

        // Build aggregated stats. Some older logs may not include device_id in action_details.
        // Resolve a device_id by preferring action_details.device_id, else fallback via gate_id -> gates.device_id.
        $stmt = $db->prepare("\n            SELECT\n                COALESCE(\n                    json_extract(al.action_details, '$.device_id'),\n                    g.device_id\n                ) AS device_id,\n                SUM(CASE WHEN date(al.created_at) = :today THEN 1 ELSE 0 END) AS day_total,\n                SUM(CASE WHEN strftime('%Y-%m', al.created_at) = :month THEN 1 ELSE 0 END) AS month_total,\n                SUM(CASE WHEN strftime('%Y', al.created_at) = :year THEN 1 ELSE 0 END) AS year_total,\n                -- Fail counts (success explicitly false)\n                SUM(CASE WHEN date(al.created_at) = :today AND (\n                    json_extract(al.action_details, '$.success') IN (0, 'false')\n                ) THEN 1 ELSE 0 END) AS day_fail,\n                SUM(CASE WHEN strftime('%Y-%m', al.created_at) = :month AND (\n                    json_extract(al.action_details, '$.success') IN (0, 'false')\n                ) THEN 1 ELSE 0 END) AS month_fail,\n                SUM(CASE WHEN strftime('%Y', al.created_at) = :year AND (\n                    json_extract(al.action_details, '$.success') IN (0, 'false')\n                ) THEN 1 ELSE 0 END) AS year_fail,\n                -- Success counts (success explicitly true)\n                SUM(CASE WHEN date(al.created_at) = :today AND (\n                    json_extract(al.action_details, '$.success') IN (1, 'true')\n                ) THEN 1 ELSE 0 END) AS day_success,\n                SUM(CASE WHEN strftime('%Y-%m', al.created_at) = :month AND (\n                    json_extract(al.action_details, '$.success') IN (1, 'true')\n                ) THEN 1 ELSE 0 END) AS month_success,\n                SUM(CASE WHEN strftime('%Y', al.created_at) = :year AND (\n                    json_extract(al.action_details, '$.success') IN (1, 'true')\n                ) THEN 1 ELSE 0 END) AS year_success,\n                -- Web/API splits (totals)\n                SUM(CASE WHEN al.api_key_id IS NULL AND date(al.created_at) = :today THEN 1 ELSE 0 END) AS day_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND date(al.created_at) = :today THEN 1 ELSE 0 END) AS day_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y-%m', al.created_at) = :month THEN 1 ELSE 0 END) AS month_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y-%m', al.created_at) = :month THEN 1 ELSE 0 END) AS month_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y', al.created_at) = :year THEN 1 ELSE 0 END) AS year_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y', al.created_at) = :year THEN 1 ELSE 0 END) AS year_api,\n                -- Success web/api splits\n                SUM(CASE WHEN al.api_key_id IS NULL AND date(al.created_at) = :today AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS day_success_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND date(al.created_at) = :today AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS day_success_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y-%m', al.created_at) = :month AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS month_success_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y-%m', al.created_at) = :month AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS month_success_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y', al.created_at) = :year AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS year_success_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y', al.created_at) = :year AND json_extract(al.action_details, '$.success') IN (1, 'true') THEN 1 ELSE 0 END) AS year_success_api,\n                -- Fail web/api splits\n                SUM(CASE WHEN al.api_key_id IS NULL AND date(al.created_at) = :today AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS day_fail_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND date(al.created_at) = :today AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS day_fail_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y-%m', al.created_at) = :month AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS month_fail_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y-%m', al.created_at) = :month AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS month_fail_api,\n                SUM(CASE WHEN al.api_key_id IS NULL AND strftime('%Y', al.created_at) = :year AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS year_fail_web,\n                SUM(CASE WHEN al.api_key_id IS NOT NULL AND strftime('%Y', al.created_at) = :year AND json_extract(al.action_details, '$.success') IN (0, 'false') THEN 1 ELSE 0 END) AS year_fail_api\n            FROM audit_log al\n            LEFT JOIN gates g ON g.id = json_extract(al.action_details, '$.gate_id')\n            WHERE al.action_type = 'GATE_TRIGGER'\n              AND NOT (\n                  json_extract(al.action_details, '$.message') LIKE 'Auto-close%'\n              )\n              AND COALESCE(json_extract(al.action_details, '$.device_id'), g.device_id) IS NOT NULL\n            GROUP BY device_id\n        ");

        try {
            $stmt->execute([
                'today' => $today,
                'month' => $month,
                'year' => $year
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats = [];
            foreach ($rows as $r) {
                $deviceId = (string)$r['device_id'];
                $stats[$deviceId] = [
                    'day' => [
                        'total' => (int)$r['day_total'],
                        'success' => (int)$r['day_success'],
                        'fail' => (int)$r['day_fail'],
                        'web' => (int)$r['day_web'],
                        'api' => (int)$r['day_api'],
                        'success_web' => (int)$r['day_success_web'],
                        'success_api' => (int)$r['day_success_api'],
                        'fail_web' => (int)$r['day_fail_web'],
                        'fail_api' => (int)$r['day_fail_api'],
                    ],
                    'month' => [
                        'total' => (int)$r['month_total'],
                        'success' => (int)$r['month_success'],
                        'fail' => (int)$r['month_fail'],
                        'web' => (int)$r['month_web'],
                        'api' => (int)$r['month_api'],
                        'success_web' => (int)$r['month_success_web'],
                        'success_api' => (int)$r['month_success_api'],
                        'fail_web' => (int)$r['month_fail_web'],
                        'fail_api' => (int)$r['month_fail_api'],
                    ],
                    'year' => [
                        'total' => (int)$r['year_total'],
                        'success' => (int)$r['year_success'],
                        'fail' => (int)$r['year_fail'],
                        'web' => (int)$r['year_web'],
                        'api' => (int)$r['year_api'],
                        'success_web' => (int)$r['year_success_web'],
                        'success_api' => (int)$r['year_success_api'],
                        'fail_web' => (int)$r['year_fail_web'],
                        'fail_api' => (int)$r['year_fail_api'],
                    ]
                ];
            }
            AjaxHandler::sendSuccess('Trigger stats retrieved', [ 'stats' => $stats ]);
        } catch (Exception $e) {
            AjaxHandler::sendError('Failed to retrieve trigger stats', 500);
        }
        break;
        
    case 'test_gate':
        // Test a gate
        $gateId = $_POST['gate_id'] ?? $_GET['gate_id'] ?? null;
        
        if (!$gateId) {
            AjaxHandler::sendError('Gate ID is required');
        }
        
        $result = $gateController->testGate($gateId);
        if ($result['success']) {
            AjaxHandler::sendSuccess('Gate test successful', $result);
        } else {
            AjaxHandler::sendError($result['message'] ?? 'Gate test failed', 400, $result);
        }
        break;
        
    case 'trigger_gate':
        // Trigger a gate
        $gateId = $_POST['gate_id'] ?? $_GET['gate_id'] ?? null;
        
        if (!$gateId) {
            AjaxHandler::sendError('Gate ID is required');
        }
        
        $result = $gateController->triggerGate($gateId);
        if ($result['success']) {
            AjaxHandler::sendSuccess('Gate triggered successfully', $result);
        } else {
            AjaxHandler::sendError($result['message'] ?? 'Failed to trigger gate', 400, $result);
        }
        break;
        
    case 'get_devices':
        // Get a list of all devices (for dropdowns)
        $result = $deviceController->listDevices();
        $devices = $result['success'] ? $result['devices'] : [];
        AjaxHandler::sendSuccess('Devices retrieved', ['devices' => $devices]);
        break;
        
    case 'get_gates_by_device':
        // Get gates filtered by device
        $deviceId = $_POST['device_id'] ?? $_GET['device_id'] ?? null;
        
        if (!$deviceId) {
            // If no device id, return empty list to avoid blocking UI
            AjaxHandler::sendSuccess('No device id provided', ['gates' => []]);
        }
        
        $result = $gateController->getGatesByDevice($deviceId);
        if (!empty($result['success'])) {
            AjaxHandler::sendSuccess('Gates retrieved', ['gates' => $result['gates'] ?? []]);
        } else {
            // On failure, return empty gates with success=false for debugging context
            AjaxHandler::sendError($result['message'] ?? 'Failed to retrieve gates', 400, $result);
        }
        break;
        
    default:
        AjaxHandler::sendError('Unknown action', 400);
        break;
}
