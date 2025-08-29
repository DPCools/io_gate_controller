<?php
/**
 * Axis VAPIX Central Controller
 * ---------------------------------
 * A clean, simple, and scalable PHP service to send VAPIX IO commands
 * to Axis devices from a central CRM or control system.
 *
 * Features:
 *  - Debug / Production modes
 *  - Device configuration and credential storage (in-memory array for demo)
 *  - Immediate command execution with retries & exponential backoff
 *  - Persistent queue (SQLite) for commands that fail — processed later
 *  - Logging with rotation
 *  - Health checks and a "self-repair" attempt (retry + queue + notify placeholder)
 *
 * Usage:
 *  - POST to this script with JSON body: { "site_id":"site1", "io_port":6, "action":"pulse", "pulse_ms":2000 }
 *  - Or call sendCommand($siteId, $port, $action) from other PHP code.
 *
 * Notes:
 *  - This is intentionally standalone (no frameworks) so you can drop it into many hosts.
 *  - Replace device configuration with a secure store in production (DB, secrets manager).
 *  - Run processQueue() regularly (cron, supervisor, or a background worker).
 */

declare(strict_types=1);

// --------------------
// Configuration
// --------------------
$config = [
    'mode' => 'debug', // 'debug' or 'production'

    // Logging
    'log_file' => __DIR__ . '/axis_controller.log',
    'log_max_kb' => 5120, // rotate when log > 5MB

    // Queue DB (sqlite file)
    'queue_db' => __DIR__ . '/axis_commands.sqlite',

    // HTTP request settings
    'http' => [
        'connect_timeout' => 3, // seconds
        'timeout' => 10,        // seconds
        'user_agent' => 'AxisVAPIX-Central/1.0',
    ],

    // Retry behaviour
    'max_retries' => 4,
    'initial_backoff_ms' => 250,

    // Notification hook (placeholder). In production, implement email/slack/pagerduty here.
    'notify_on_persistent_failure' => false,
];

// --------------------
// Device registry
// --------------------
// In production store these in a DB or secrets manager. Keep only IDs here for demo.
$devices = [
    'site1' => [
        'host' => '10.160.37.60',
        'port' => 443,
        'scheme' => 'https',
        'username' => 'anprtrigger',
        'password' => 'kl4no3j24nkjg90m3098s',
        'description' => 'Demo site 1 - Axis door IO (HTTPS, digest)',
        'base_path' => '/',
        'insecure' => true, // allow self-signed certs (match --insecure)
        'tlsv1_2' => true,  // prefer TLS1.2
        'auth' => 'digest',
    ],
    'site2' => [
        'host' => '10.160.29.82',
        'port' => 443,
        'scheme' => 'https',
        'username' => 'anprtrigger',
        'password' => 'kl4no3j24nkjg90m3098s',
        'description' => 'Demo site 1 - Axis door IO (HTTPS, digest)',
        'base_path' => '/',
        'insecure' => true, // allow self-signed certs (match --insecure)
        'tlsv1_2' => true,  // prefer TLS1.2
        'auth' => 'digest',
    ],
    'site3' => [
        'host' => '10.160.37.84',
        'port' => 443,
        'scheme' => 'https',
        'username' => 'anprtrigger',
        'password' => 'kl4no3j24nkjg90m3098s',
        'description' => 'Demo site 1 - Axis door IO (HTTPS, digest)',
        'base_path' => '/',
        'insecure' => true, // allow self-signed certs (match --insecure)
        'tlsv1_2' => true,  // prefer TLS1.2
        'auth' => 'digest',
    ],
    'site4' => [
        'host' => '10.160.37.62',
        'port' => 443,
        'scheme' => 'https',
        'username' => 'anprtrigger',
        'password' => 'kl4no3j24nkjg90m3098s',
        'description' => 'Demo site 1 - Axis door IO (HTTPS, digest)',
        'base_path' => '/',
        'insecure' => true, // allow self-signed certs (match --insecure)
        'tlsv1_2' => true,  // prefer TLS1.2
        'auth' => 'digest',
    ],
];

// --------------------
// Gate Configuration - EDIT THIS SECTION TO ADD GATES
// --------------------
// Easy mapping of gate names to their configuration
// Format: 'friendly_gate_name' => ['site' => 'site_id', 'port' => port_number, 'pulse_ms' => pulse_length_ms]
$gate_map = [
    // Default gates
    'front_gate' => ['site' => 'site2', 'port' => 3, 'pulse_ms' => 800],
    'back_door' => ['site' => 'site1', 'port' => 7, 'pulse_ms' => 1000], 
    'garage' => ['site' => 'site3', 'port' => 2, 'pulse_ms' => 1500],
];

// --------------------
// Utility functions
// --------------------
function logMsg(string $msg)
{
    global $config;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    file_put_contents($config['log_file'], $line, FILE_APPEND | LOCK_EX);
    rotateLogsIfNeeded();
}

function rotateLogsIfNeeded()
{
    global $config;
    $f = $config['log_file'];
    if (!file_exists($f)) return;
    $kb = filesize($f) / 1024;
    if ($kb > $config['log_max_kb']) {
        $dst = $f . '.' . date('YmdHis');
        rename($f, $dst);
        // keep only last 5 rotated logs
        $files = glob($f . '.*');
        rsort($files);
        $keep = array_slice($files, 0, 5);
        foreach ($files as $file) {
            if (!in_array($file, $keep)) @unlink($file);
        }
    }
}

function debug(string $msg)
{
    global $config;
    if ($config['mode'] === 'debug') {
        logMsg('[DEBUG] ' . $msg);
    }
}

// --------------------
// Queue (SQLite) helpers
// --------------------
function getQueuePdo(): PDO
{
    global $config;
    $dbfile = $config['queue_db'];
    $pdo = new PDO('sqlite:' . $dbfile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // create table if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS command_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id TEXT NOT NULL,
        io_port INTEGER NOT NULL,
        action TEXT NOT NULL,
        pulse_ms INTEGER DEFAULT 0,
        attempts INTEGER DEFAULT 0,
        last_error TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_attempt_at DATETIME
    )");

    return $pdo;
}

function queueCommand(string $siteId, int $port, string $action, int $pulseMs = 0)
{
    $pdo = getQueuePdo();
    $stmt = $pdo->prepare('INSERT INTO command_queue (site_id, io_port, action, pulse_ms) VALUES (:s, :p, :a, :m)');
    $stmt->execute([':s' => $siteId, ':p' => $port, ':a' => $action, ':m' => $pulseMs]);
    logMsg("Queued command for site={$siteId} port={$port} action={$action} pulseMs={$pulseMs}");
}

// --------------------
// VAPIX helpers
// --------------------
function buildVapixUrl(array $device, int $port, string $action, int $pulseMs = 0): string
{
    // Map friendly actions to Axis port.cgi params
    // For 'pulse' we want Axis to perform the pulse internally using the
    // special syntax <port>:/<milliseconds>\ which must be URL-encoded.
    // Example encoded: action=6%3a%2f500%5C

    $scheme = $device['scheme'] ?? ((isset($device['port']) && $device['port'] == 443) ? 'https' : 'http');
    $host = $device['host'];
    $devicePort = $device['port'] ?? 80;
    $basePath = rtrim($device['base_path'] ?? '/', '/');

    $actionParam = '';
    if ($action === 'on') {
        $actionParam = "1:{$port}";
    } elseif ($action === 'off') {
        $actionParam = "0:{$port}";
    } elseif ($action === 'pulse') {
        // Use the caller-specified pulseMs when provided (ms)
        $ms = max(1, (int)$pulseMs);
        // For pulse commands, Axis needs the format port:/ms\ which becomes port%3a%2fms%5C when URL-encoded
        $actionParam = "{$port}:/{$ms}\\";
    } else {
        $actionParam = "1:{$port}";
    }

    // URL-encode the action parameter so the device receives the exact bytes
    $encoded = rawurlencode($actionParam);
    
    // Debug log the encoded parameter to verify format
    debug("Action parameter: {$actionParam}, encoded as: {$encoded}");

    $url = "{$scheme}://{$host}:{$devicePort}{$basePath}/axis-cgi/io/port.cgi?action={$encoded}";
    return $url;
} 

function executeVapix(array $device, string $url, array $httpOpts = []): array
{
    global $config;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['http']['user_agent']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $config['http']['connect_timeout']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['http']['timeout']);

    // Authentication: prefer digest if available; allow devices to override via 'auth'
    $auth = $device['auth'] ?? 'digest';
    if (stripos($auth, 'digest') !== false && defined('CURLAUTH_DIGEST')) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    } else {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
    curl_setopt($ch, CURLOPT_USERPWD, $device['username'] . ':' . $device['password']);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);

    // TLS and HTTP settings
    if (!empty($httpOpts['insecure']) || !empty($device['insecure'])) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    if (!empty($httpOpts['tlsv1_2']) || !empty($device['tlsv1_2'])) {
        if (defined('CURL_SSLVERSION_TLSv1_2')) curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    }
    if (!empty($httpOpts['http1.1']) || !empty($device['http1.1'])) {
        if (defined('CURL_HTTP_VERSION_1_1')) curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    }

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);

    // Some Axis devices close the connection early; treat 200/401/403 as allowable success
    $httpCode = $info['http_code'] ?? 0;
    $ok = ($errno === 0 && ($httpCode === 200 || $httpCode === 401 || $httpCode === 403));

    return ['ok' => $ok, 'code' => $httpCode, 'errno' => $errno, 'error' => $err, 'response' => $response];
} 

// --------------------
// High-level command function
// --------------------
/**
 * Send command to an Axis device identified by site id.
 * action: 'on'|'off'|'pulse'
 * pulseMs: integer ms for pulse length if action == 'pulse'
 */
function sendCommand(string $siteId, int $ioPort, string $action, int $pulseMs = 0): array
{
    global $devices, $config;

    if (!isset($devices[$siteId])) {
        $msg = "Unknown site_id: {$siteId}";
        logMsg($msg);
        return ['success' => false, 'error' => $msg];
    }

    $device = $devices[$siteId];

    // Validate action
    if (!in_array($action, ['on', 'off', 'pulse'])) {
        $msg = "Invalid action: {$action}";
        logMsg($msg);
        return ['success' => false, 'error' => $msg];
    }

    $attempt = 0;
    $backoff = $config['initial_backoff_ms'];

    while ($attempt <= $config['max_retries']) {
        $attempt++;
        debug("Attempt {$attempt} for site={$siteId} port={$ioPort} action={$action}");

        // If pulse, ask the Axis device to perform the timed pulse internally.
        if ($action === 'pulse') {
            $res = sendSingleAction($device, $siteId, $ioPort, 'pulse', $pulseMs);
            if ($res['success']) {
                return ['success' => true];
            }

            logMsg("Pulse failed for {$siteId}: " . $res['error']);
            if ($attempt > $config['max_retries']) {
                queueCommand($siteId, $ioPort, 'pulse', $pulseMs);
                if ($config['notify_on_persistent_failure']) {
                    // notifyAdmins(...)
                }
                return ['success' => false, 'error' => 'Queued after repeated failures'];
            }

            usleep((int)($backoff * 1000));
            $backoff *= 2;
            continue;
        } else {
            $res = sendSingleAction($device, $siteId, $ioPort, $action, 0);
            if ($res['success']) return ['success' => true];

            logMsg("Attempt {$attempt} failed for {$siteId}: " . $res['error']);

            if ($attempt > $config['max_retries']) {
                queueCommand($siteId, $ioPort, $action, $pulseMs);
                // Optionally notify
                if ($config['notify_on_persistent_failure']) {
                    // notifyAdmins(...)
                }
                return ['success' => false, 'error' => 'Queued after repeated failures'];
            }

            usleep((int)($backoff * 1000));
            $backoff *= 2;
        }
    }

    return ['success' => false, 'error' => 'Unknown error'];
}

function sendSingleAction(array $device, string $siteId, int $ioPort, string $action, int $pulseMs = 0): array
{
    $url = buildVapixUrl($device, $ioPort, $action, $pulseMs);
    $httpOpts = [];
    // allow per-device overrides
    if (!empty($device['insecure'])) $httpOpts['insecure'] = true;
    if (!empty($device['tlsv1_2'])) $httpOpts['tlsv1_2'] = true;
    if (!empty($device['http1.1'])) $httpOpts['http1.1'] = true;

    $result = executeVapix($device, $url, $httpOpts);

    if ($result['ok']) {
        logMsg("{$action} command OK for {$siteId} (port {$ioPort}) http_code={$result['code']}");
        return ['success' => true];
    }

    $err = "HTTP={$result['code']} errno={$result['errno']} err='{$result['error']}'";
    return ['success' => false, 'error' => $err, 'raw' => $result];
}

// --------------------
// Queue processing
// --------------------
function processQueue(int $maxRows = 50)
{
    $pdo = getQueuePdo();
    $stmt = $pdo->prepare('SELECT * FROM command_queue ORDER BY id ASC LIMIT :l');
    $stmt->bindValue(':l', $maxRows, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $id = $row['id'];
        $siteId = $row['site_id'];
        $port = (int)$row['io_port'];
        $action = $row['action'];
        $pulse = (int)$row['pulse_ms'];
        $attempts = (int)$row['attempts'];

        logMsg("Processing queue id={$id} site={$siteId} action={$action} port={$port} attempts={$attempts}");

        $res = sendCommand($siteId, $port, $action, $pulse);

        if ($res['success']) {
            $pdo->prepare('DELETE FROM command_queue WHERE id = :id')->execute([':id' => $id]);
            logMsg("Queue id={$id} succeeded and removed");
        } else {
            $attempts++;
            $stmtU = $pdo->prepare('UPDATE command_queue SET attempts = :a, last_error = :e, last_attempt_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtU->execute([':a' => $attempts, ':e' => $res['error'] ?? 'unknown', ':id' => $id]);
            logMsg("Queue id={$id} failed (attempts={$attempts}) error={$res['error']}");

            // If attempts get too large, consider notifying and deleting to avoid infinite retry loop
            if ($attempts > 10) {
                // placeholder notify
                logMsg("Queue id={$id} exceeded retry limit and will be removed. Consider manual intervention.");
                $pdo->prepare('DELETE FROM command_queue WHERE id = :id')->execute([':id' => $id]);
            }
        }
    }
}

// --------------------
// Simple HTTP endpoint
// --------------------
// Allows CRM to POST JSON to this file or use GET with specific key to trigger commands.
if (php_sapi_name() !== 'cli') {
    // Check for list_gates action to display available gates
    if (isset($_GET['action']) && $_GET['action'] === 'list_gates') {
        header('Content-Type: application/json');
        $gates_list = [];
        foreach ($gate_map as $name => $config) {
            $gates_list[$name] = [
                'site' => $config['site'],
                'port' => $config['port'],
                'pulse_ms' => $config['pulse_ms']
            ];
        }
        echo json_encode(['status' => 'ok', 'gates' => $gates_list]);
        exit;
    }
    
    // Check for special GET parameter for gate trigger
    if (isset($_GET['trigger']) && !empty($_GET['trigger'])) {
        $gate_name = $_GET['trigger'];
        
        if (isset($gate_map[$gate_name])) {
            $gate = $gate_map[$gate_name];
            debug("Gate trigger activated via GET parameter: {$gate_name}");
            $result = sendCommand($gate['site'], $gate['port'], 'pulse', $gate['pulse_ms']);
        }
        
        header('Content-Type: application/json');
        if (isset($result) && $result['success']) {
            echo json_encode(['status' => 'ok', 'message' => 'Gate triggered']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => isset($result) ? ($result['error'] ?? 'unknown') : 'Gate not found']);
        }
        exit;
    }
    
    // Standard POST API
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Use POST with JSON body or GET with specific parameters']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }

    $site = $data['site_id'] ?? ($data['device_id'] ?? null);
    $port = isset($data['io_port']) ? (int)$data['io_port'] : null;
    $action = $data['action'] ?? null;
    $pulseMs = isset($data['pulse_ms']) ? (int)$data['pulse_ms'] : 0;

    if (!$site || !$port || !$action) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'site_id, io_port and action required']);
        exit;
    }

    $result = sendCommand($site, $port, $action, $pulseMs);
    if ($result['success']) {
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'unknown']);
    }
    exit;
}

// --------------------
// CLI helpers for maintenance
// --------------------
if (php_sapi_name() === 'cli') {
    $argv0 = $argv[1] ?? null;
    if ($argv0 === 'process_queue') {
        processQueue(100);
        exit;
    }
    if ($argv0 === 'show_queue') {
        $pdo = getQueuePdo();
        $rows = $pdo->query('SELECT * FROM command_queue ORDER BY id ASC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
        exit;
    }
    if (isset($gate_map[$argv0])) {
        // Trigger any defined gate from command line using the gate map
        $gate = $gate_map[$argv0];
        echo "Triggering {$argv0}...\n";
        $r = sendCommand($gate['site'], $gate['port'], 'pulse', $gate['pulse_ms']);
        print_r($r);
        exit;
    }
    
    if ($argv0 === 'list_gates') {
        // List all available gates
        echo "Available gates:\n";
        foreach ($gate_map as $name => $config) {
            echo "  - {$name} (Site: {$config['site']}, Port: {$config['port']}, Pulse: {$config['pulse_ms']}ms)\n";
        }
        exit;
    }
    if ($argv0 === 'test') {
        // quick local test
        echo "Test mode\n";
        $r = sendCommand('site1', 6, 'pulse', 500);
        print_r($r);
        exit;
    }
}