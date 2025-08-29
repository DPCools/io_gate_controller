<?php
/**
 * Gate.php
 * 
 * Gate model for managing gate controller gates
 */

namespace GateController\Models;

class Gate {
    private $db;
    private $id;
    private $name;
    private $deviceId;
    private $ioPort;
    private $pulseSeconds;
    private $closeEnabled;
    private $closeIoPort;
    private $closeDelaySeconds;
    private $description;
    private $createdBy;
    private $updatedBy;
    private $createdAt;
    private $updatedAt;
    private $gateColumnsCache;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    /**
     * Get columns of gates table (cached)
     */
    private function getGateColumns() {
        if (is_array($this->gateColumnsCache)) {
            return $this->gateColumnsCache;
        }
        try {
            $stmt = $this->db->query("PRAGMA table_info('gates')");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->gateColumnsCache = array_map(function($r){ return $r['name']; }, $rows);
        } catch (\Throwable $e) {
            $this->gateColumnsCache = ['id','name','device_id','io_port','pulse_seconds','description','created_by','updated_by','created_at','updated_at'];
        }
        return $this->gateColumnsCache;
    }
    
    /**
     * Load gate by ID
     */
    public function loadById($id) {
        $stmt = $this->db->prepare("SELECT * FROM gates WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $gate = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($gate) {
            $this->populateFromArray($gate);
            return true;
        }
        
        return false;
    }
    
    /**
     * Load gate by name
     */
    public function loadByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM gates WHERE name = :name");
        $stmt->execute(['name' => $name]);
        $gate = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($gate) {
            $this->populateFromArray($gate);
            return true;
        }
        
        return false;
    }
    
    /**
     * Populate gate object from database row
     */
    private function populateFromArray(array $data) {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->deviceId = $data['device_id'];
        $this->ioPort = $data['io_port'];
        $this->pulseSeconds = $data['pulse_seconds'];
        $this->closeEnabled = isset($data['close_enabled']) ? (int)$data['close_enabled'] : 0;
        $this->closeIoPort = $data['close_io_port'] ?? null;
        $this->closeDelaySeconds = isset($data['close_delay_seconds']) ? (int)$data['close_delay_seconds'] : 20;
        $this->description = $data['description'];
        $this->createdBy = $data['created_by'];
        $this->updatedBy = $data['updated_by'];
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }
    
    /**
     * Create new gate
     */
    public function create($data, $userId) {
        // Check if name already exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM gates WHERE name = :name");
        $stmt->execute(['name' => $data['name']]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Gate name already exists'
            ];
        }
        
        // Create new gate (conditionally include close_* fields if columns exist)
        $cols = $this->getGateColumns();
        $fields = ['name','device_id','io_port','pulse_seconds','description','created_by'];
        $params = [
            'name' => $data['name'],
            'device_id' => $data['device_id'],
            'io_port' => $data['io_port'],
            'pulse_seconds' => $data['pulse_seconds'],
            'description' => $data['description'] ?? null,
            'created_by' => $userId
        ];
        if (in_array('close_enabled', $cols)) {
            $fields[] = 'close_enabled';
            $params['close_enabled'] = !empty($data['close_enabled']) ? 1 : 0;
        }
        if (in_array('close_io_port', $cols)) {
            $fields[] = 'close_io_port';
            $params['close_io_port'] = isset($data['close_io_port']) && $data['close_io_port'] !== '' ? (int)$data['close_io_port'] : null;
        }
        if (in_array('close_delay_seconds', $cols)) {
            $fields[] = 'close_delay_seconds';
            $params['close_delay_seconds'] = isset($data['close_delay_seconds']) && $data['close_delay_seconds'] !== '' ? (int)$data['close_delay_seconds'] : 20;
        }
        $placeholders = array_map(function($f){ return ':' . $f; }, $fields);
        $sql = 'INSERT INTO gates (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            $this->id = $this->db->lastInsertId();
            $this->loadById($this->id);
            
            return [
                'success' => true,
                'gate_id' => $this->id
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create gate'
        ];
    }
    
    /**
     * Update gate
     */
    public function update($data, $userId) {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Gate not loaded'
            ];
        }
        
        // Check if name already exists for another gate
        if (isset($data['name']) && $data['name'] !== $this->name) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM gates WHERE name = :name AND id != :id");
            $stmt->execute([
                'name' => $data['name'],
                'id' => $this->id
            ]);
            if ($stmt->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'message' => 'Gate name already exists'
                ];
            }
        }
        
        // Build update query
        $allowedFields = [
            'name', 'device_id', 'io_port', 'pulse_seconds', 'description'
        ];
        $cols = $this->getGateColumns();
        if (in_array('close_enabled', $cols)) { $allowedFields[] = 'close_enabled'; }
        if (in_array('close_io_port', $cols)) { $allowedFields[] = 'close_io_port'; }
        if (in_array('close_delay_seconds', $cols)) { $allowedFields[] = 'close_delay_seconds'; }
        
        $updates = [];
        $params = ['id' => $this->id, 'updated_by' => $userId];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $val = $data[$field];
                // Normalize auto-close related fields
                if ($field === 'close_enabled') {
                    // Checkbox posts 'on' when checked, nothing when unchecked
                    $val = (!empty($val) && $val !== '0') ? 1 : 0;
                } elseif ($field === 'close_io_port') {
                    // Empty string => NULL, otherwise cast to int
                    $val = ($val === '' || $val === null) ? null : (int)$val;
                } elseif ($field === 'close_delay_seconds') {
                    $val = (int)$val;
                    if ($val <= 0) { $val = 20; }
                }

                $updates[] = "$field = :$field";
                $params[$field] = $val;
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => true,
                'message' => 'No changes to update'
            ];
        }
        
        $updates[] = "updated_by = :updated_by";
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        
        $updateString = implode(', ', $updates);
        $sql = "UPDATE gates SET $updateString WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            $this->loadById($this->id);
            
            return [
                'success' => true,
                'gate_id' => $this->id
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to update gate'
        ];
    }
    
    /**
     * Delete gate
     */
    public function delete() {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Gate not loaded'
            ];
        }
        
        // Delete gate
        $stmt = $this->db->prepare("DELETE FROM gates WHERE id = :id");
        $success = $stmt->execute(['id' => $this->id]);
        
        if ($success) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to delete gate'
        ];
    }
    
    /**
     * Get all gates
     */
    public function getAllGates() {
        $stmt = $this->db->query("
            SELECT g.*,
                   d.name as device_name,
                   u1.username as created_by_username,
                   u2.username as updated_by_username
            FROM gates g
            LEFT JOIN devices d ON g.device_id = d.id
            LEFT JOIN users u1 ON g.created_by = u1.id
            LEFT JOIN users u2 ON g.updated_by = u2.id
            ORDER BY g.name
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get gates by device ID
     */
    public function getGatesByDeviceId($deviceId) {
        $stmt = $this->db->prepare("
            SELECT * FROM gates
            WHERE device_id = :device_id
            ORDER BY name
        ");
        
        $stmt->execute(['device_id' => $deviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Trigger gate
     */
    public function trigger($userId = null, $apiKeyId = null, $details = []) {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Gate not loaded'
            ];
        }
        
        // Get device information
        $stmt = $this->db->prepare("
            SELECT * FROM devices
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $this->deviceId]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$device) {
            return [
                'success' => false,
                'message' => 'Device not found'
            ];
        }
        
        // Build URL
        $url = $this->buildVapixUrl($device);
        
        // Execute request
        $result = $this->executeVapix($device, $url);

        // Log the trigger
        $this->logTrigger($userId, $apiKeyId, $result['success'], $result['message'] ?? '');

        // If open succeeded and auto-close is configured, schedule a delayed close pulse
        if (!empty($result['success']) && $this->closeEnabled) {
            $closePort = $this->closeIoPort ?: $this->ioPort; // default to same port if not specified
            $delay = $this->closeDelaySeconds > 0 ? $this->closeDelaySeconds : 20;
            $this->scheduleClosePulse($device, $closePort, $delay, $userId, $apiKeyId);
        }

        return $result;
    }
    
    /**
     * Test gate
     */
    public function test($userId) {
        $result = $this->trigger($userId, null, ['test' => true]);
        return $result;
    }
    
    /**
     * Build VAPIX URL for gate
     */
    private function buildVapixUrl($device) {
        // Build base URL
        $scheme = $device['scheme'] ?? 'https';
        $host = $device['host'];
        $port = $device['port'] ?? ($scheme === 'https' ? 443 : 80);
        $url = $scheme . '://' . $host;
        if (($scheme === 'http' && (int)$port !== 80) || ($scheme === 'https' && (int)$port !== 443)) {
            $url .= ':' . (int)$port;
        }

        // Normalize base path
        $basePath = rtrim($device['base_path'] ?? '/', '/');
        $url .= $basePath . '/';

        // Axis pulse format: action=<port>:/<milliseconds>\ (Axis expects a trailing backslash)
        $pulseMs = max(1, (int)($this->pulseSeconds * 1000));
        $actionParam = $this->ioPort . ':/'. $pulseMs . '\\';
        $encodedAction = rawurlencode($actionParam);

        $url .= 'axis-cgi/io/port.cgi?action=' . $encodedAction;

        return $url;
    }

    /**
     * Build VAPIX URL for arbitrary port/pulse
     */
    private function buildVapixUrlFor($device, $ioPort, $pulseSeconds) {
        $scheme = $device['scheme'] ?? 'https';
        $host = $device['host'];
        $port = $device['port'] ?? ($scheme === 'https' ? 443 : 80);
        $url = $scheme . '://' . $host;
        if (($scheme === 'http' && (int)$port !== 80) || ($scheme === 'https' && (int)$port !== 443)) {
            $url .= ':' . (int)$port;
        }
        $basePath = rtrim($device['base_path'] ?? '/', '/');
        $url .= $basePath . '/';
        $pulseMs = max(1, (int)($pulseSeconds * 1000));
        $actionParam = $ioPort . ':/'. $pulseMs . '\\';
        $encodedAction = rawurlencode($actionParam);
        $url .= 'axis-cgi/io/port.cgi?action=' . $encodedAction;
        return $url;
    }

// ... (rest of the code remains the same)
    /**
     * Schedule a delayed close pulse without blocking response
     */
    private function scheduleClosePulse(array $device, int $ioPort, int $delaySeconds, $userId, $apiKeyId) {
        // Try to flush response to the client before sleeping
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (session_id()) { @session_write_close(); }
            @ignore_user_abort(true);
            @flush();
        }
        // Perform in-process delay and close pulse
        // Note: This will run after response flush; best-effort approach
        $start = microtime(true);
        sleep($delaySeconds);
        $closeUrl = $this->buildVapixUrlFor($device, $ioPort, max(0.2, (float)$this->pulseSeconds));
        $closeResult = $this->executeVapix($device, $closeUrl);
        $elapsed = (int)round((microtime(true) - $start) * 1000);
        $msg = ($closeResult['success'] ?? false)
            ? "Auto-close pulse sent after {$delaySeconds}s (elapsed ~{$elapsed} ms)"
            : ('Auto-close failed: ' . ($closeResult['message'] ?? 'Unknown error'));
        // Log the auto-close attempt
        $this->logTrigger($userId, $apiKeyId, (bool)($closeResult['success'] ?? false), $msg);
    }
    
    /**
     * Execute VAPIX request
     */
    private function executeVapix($device, $url) {
        // Fallback if cURL extension is not available
        if (!function_exists('curl_init')) {
            $authType = strtolower($device['auth'] ?? 'digest');
            if ($authType !== 'basic') {
                return [
                    'success' => false,
                    'message' => 'Digest authentication requires PHP cURL extension. Please enable ext-curl or switch device auth to Basic.'
                ];
            }

            // Basic-only fallback using streams
            $headers = [
                'User-Agent: GateController/2.0',
                'Connection: close',
                'Authorization: Basic ' . base64_encode(($device['username'] ?? '') . ':' . ($device['password'] ?? ''))
            ];
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 10
                ],
                'ssl' => [
                    'verify_peer' => empty($device['insecure']),
                    'verify_peer_name' => empty($device['insecure'])
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            $responseCodeHeader = $http_response_header[0] ?? '';
            if ($response !== false && strpos($responseCodeHeader, '200') !== false) {
                return ['success' => true, 'message' => 'Gate triggered successfully'];
            }
            return [
                'success' => false,
                'message' => 'Failed to trigger gate (Basic, no-cURL): ' . ($responseCodeHeader ?: 'Unknown HTTP status')
            ];
        }

        // Use cURL to support Basic and Digest auth properly
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // Force HTTP/1.1 to avoid some Axis TLS/HTTP quirks
        if (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: GateController/2.0',
            'Connection: close',
            // Send empty Expect header to avoid 100-continue delays with some devices
            'Expect:'
        ]);

        // SSL options
        if (!empty($device['insecure'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (!empty($device['tlsv1_2'])) {
            if (defined('CURL_SSLVERSION_TLSv1_2')) {
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            }
        }

        // Authentication
        $userpwd = ($device['username'] ?? '') . ':' . ($device['password'] ?? '');
        curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        $preferredAuth = strtolower($device['auth'] ?? 'digest');
        $tryOrder = $preferredAuth === 'basic' ? [CURLAUTH_BASIC, CURLAUTH_DIGEST] : [CURLAUTH_DIGEST, CURLAUTH_BASIC];
        $triedLabels = [];

        $lastError = null;
        foreach ($tryOrder as $idx => $authConst) {
            // Set auth type for this attempt
            curl_setopt($ch, CURLOPT_HTTPAUTH, $authConst);
            $triedLabels[] = ($authConst === CURLAUTH_BASIC) ? 'basic' : 'digest';

            $responseBody = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($errno) {
                // Special handling: some devices execute the relay but do not send a timely response body.
                // If we connected and sent the request but timed out waiting, assume success.
                if ($errno === 28 || (defined('CURLE_OPERATION_TIMEDOUT') && $errno === CURLE_OPERATION_TIMEDOUT)) {
                    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
                    $primaryIp   = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
                    $totalTime   = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                    if (!empty($primaryIp) || $connectTime > 0) {
                        curl_close($ch);
                        return [
                            'success' => true,
                            'message' => 'Gate triggered (device did not respond before timeout ~' . (int)round($totalTime * 1000) . ' ms)'
                        ];
                    }
                    // If hitting Axis VAPIX io/port.cgi specifically, assume success on timeout as devices often flip relay then stall response
                    if (strpos($url, 'axis-cgi/io/port.cgi') !== false) {
                        curl_close($ch);
                        return [
                            'success' => true,
                            'message' => 'Gate triggered (Axis VAPIX endpoint timed out after ~' . (int)round($totalTime * 1000) . ' ms)'
                        ];
                    }
                }
                // Handle SSL EOF/recv errors: device may have already executed the action.
                if ($errno === 56 || (defined('CURLE_RECV_ERROR') && $errno === CURLE_RECV_ERROR)) {
                    // If HTTP code indicates success, or if we reached the device and it's the Axis endpoint, treat as success.
                    if ($httpCode >= 200 && $httpCode < 300) {
                        curl_close($ch);
                        return [
                            'success' => true,
                            'message' => 'Gate triggered (device closed connection early after sending HTTP ' . $httpCode . ')'
                        ];
                    }
                    $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
                    if (!empty($primaryIp) && strpos($url, 'axis-cgi/io/port.cgi') !== false) {
                        curl_close($ch);
                        return [
                            'success' => true,
                            'message' => 'Gate triggered (device closed TLS early; Axis VAPIX)'
                        ];
                    }
                }
                $lastError = 'Connection error: ' . $error;
                // If first attempt errored at network level, no point retrying with other auth
                break;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                curl_close($ch);
                return [
                    'success' => true,
                    'message' => 'Gate triggered successfully'
                ];
            }

            // If unauthorized and we have another auth to try, continue loop
            if ($httpCode === 401 && $idx === 0) {
                $lastError = 'HTTP 401 (auth attempt failed)';
                continue;
            }

            // Other non-2xx codes: stop and report
            $lastError = 'HTTP ' . $httpCode;
            break;
        }

        curl_close($ch);
        $host = $device['host'] ?? 'unknown-host';
        $scheme = $device['scheme'] ?? 'https';
        $port = (int)($device['port'] ?? ($scheme === 'https' ? 443 : 80));
        $authTried = implode(',', $triedLabels) ?: 'n/a';
        return [
            'success' => false,
            'message' => 'Failed to trigger gate: ' . ($lastError ?: 'Unknown error') . " (target: {$scheme}://{$host}:{$port}, auth_tried: {$authTried})"
        ];
    }
    
    /**
     * Log trigger action
     */
    private function logTrigger($userId, $apiKeyId, $success, $message) {
        // If unauthorized, append a helpful tip about credentials being case-sensitive
        if (!$success && (strpos($message, '401') !== false || stripos($message, 'unauthorized') !== false)) {
            if (stripos($message, 'case') === false) {
                $message .= ' Tip: Check the device username and password (they are case-sensitive), and ensure the correct auth mode is configured.';
            }
        }
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                action_type, action_details, user_id, api_key_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :user_id, :api_key_id,
                :ip_address, :user_agent
            )
        ");
        
        return $stmt->execute([
            'action_type' => 'GATE_TRIGGER',
            'action_details' => json_encode([
                'gate_id' => $this->id,
                'gate_name' => $this->name,
                'device_id' => $this->deviceId,
                'io_port' => $this->ioPort,
                'pulse_seconds' => $this->pulseSeconds,
                'success' => $success,
                'message' => $message
            ]),
            'user_id' => $userId,
            'api_key_id' => $apiKeyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getDeviceId() { return $this->deviceId; }
    public function getIoPort() { return $this->ioPort; }
    public function getPulseSeconds() { return $this->pulseSeconds; }
    public function isCloseEnabled() { return (bool)$this->closeEnabled; }
    public function getCloseIoPort() { return $this->closeIoPort; }
    public function getCloseDelaySeconds() { return $this->closeDelaySeconds; }
    public function getDescription() { return $this->description; }
    public function getCreatedBy() { return $this->createdBy; }
    public function getUpdatedBy() { return $this->updatedBy; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    
    /**
     * Convert to array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_id' => $this->deviceId,
            'io_port' => $this->ioPort,
            'pulse_seconds' => $this->pulseSeconds,
            'close_enabled' => (int)$this->closeEnabled,
            'close_io_port' => $this->closeIoPort,
            'close_delay_seconds' => $this->closeDelaySeconds,
            'description' => $this->description,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}
