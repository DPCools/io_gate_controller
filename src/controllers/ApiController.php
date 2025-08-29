<?php
/**
 * ApiController.php
 * 
 * Controller for API endpoints and CRM integration
 */

namespace GateController\Controllers;

use GateController\Models\Gate;
use GateController\Models\User;

class ApiController {
    private $db;
    private $config;
    private $gateController;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db, array $config, GateController $gateController = null) {
        $this->db = $db;
        $this->config = $config;
        $this->gateController = $gateController;
    }
    
    /**
     * Trigger gate via API
     */
    public function triggerGate($apiKey, $gateName) {
        // Validate API key
        $apiKeyId = $this->validateApiKey($apiKey);
        if (!$apiKeyId) {
            return [
                'success' => false,
                'message' => 'Invalid API key'
            ];
        }
        
        // Check if gate name is provided
        if (empty($gateName)) {
            $this->logApiAccess($apiKeyId, 'GATE_TRIGGER', false, [
                'error' => 'Missing gate name'
            ]);
            
            return [
                'success' => false,
                'message' => 'Gate name is required'
            ];
        }
        
        // Use gate controller to trigger the gate
        if ($this->gateController) {
            $result = $this->gateController->triggerGateByName($gateName, $apiKeyId);
        } else {
            // Fallback if gate controller isn't provided
            $gate = new Gate($this->db);
            
            if (!$gate->loadByName($gateName)) {
                $this->logApiAccess($apiKeyId, 'GATE_TRIGGER', false, [
                    'gate_name' => $gateName,
                    'error' => 'Gate not found'
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Gate not found'
                ];
            }
            
            $result = $gate->trigger(null, $apiKeyId);
        }
        
        // If unauthorized, append a helpful tip about credentials being case-sensitive
        if (isset($result['success']) && !$result['success'] && isset($result['message']) && is_string($result['message'])) {
            $msgLower = strtolower($result['message']);
            if ((strpos($msgLower, '401') !== false || strpos($msgLower, 'unauthorized') !== false) && strpos($msgLower, 'case') === false) {
                $result['message'] .= ' Tip: Check the device username and password (they are case-sensitive), and ensure the correct auth mode is configured.';
            }
        }

        // Log API call as an API request to avoid double-counting gate trigger stats
        $this->logApiAccess($apiKeyId, 'API_REQUEST', $result['success'], [
            'endpoint' => 'trigger',
            'gate_name' => $gateName,
            'result' => $result
        ]);
        
        return $result;
    }
    
    /**
     * Get list of gates (API endpoint)
     */
    public function getGates($apiKey) {
        // Validate API key
        $apiKeyId = $this->validateApiKey($apiKey);
        if (!$apiKeyId) {
            return [
                'success' => false,
                'message' => 'Invalid API key'
            ];
        }
        
        // Get all gates
        $gate = new Gate($this->db);
        $gates = $gate->getAllGates();
        
        // Filter the response to include only necessary info
        $result = [];
        foreach ($gates as $gate) {
            $result[] = [
                'name' => $gate['name'],
                'description' => $gate['description'],
                'device_name' => $gate['device_name']
            ];
        }
        
        $this->logApiAccess($apiKeyId, 'API_REQUEST', true, [
            'endpoint' => 'getGates',
            'gate_count' => count($result)
        ]);
        
        return [
            'success' => true,
            'gates' => $result
        ];
    }
    
    /**
     * Validate API key
     */
    private function validateApiKey($apiKey) {
        if (empty($apiKey)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM api_keys
            WHERE api_key = :api_key
            AND active = 1
            AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute(['api_key' => $apiKey]);
        $apiKeyId = $stmt->fetchColumn();
        
        return $apiKeyId;
    }
    
    /**
     * Create new API key
     */
    public function createApiKey($data, $userId) {
        // Validate data
        if (empty($data['name'])) {
            return [
                'success' => false,
                'message' => 'API key name is required'
            ];
        }
        
        // Generate a secure API key
        $apiKey = $this->generateApiKey();
        
        // Insert into database
        $stmt = $this->db->prepare("
            INSERT INTO api_keys (
                name, api_key, description, created_by, 
                expires_at, allowed_ips
            ) VALUES (
                :name, :api_key, :description, :created_by, 
                :expires_at, :allowed_ips
            )
        ");
        
        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $expiresAt = $data['expires_at'];
        }
        
        $success = $stmt->execute([
            'name' => $data['name'],
            'api_key' => $apiKey,
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
            'expires_at' => $expiresAt,
            'allowed_ips' => $data['allowed_ips'] ?? null
        ]);
        
        if ($success) {
            $apiKeyId = $this->db->lastInsertId();
            
            // Log the creation
            $user = new User($this->db);
            $user->loadById($userId);
            $user->logAudit('API_KEY_CREATE', [
                'api_key_id' => $apiKeyId,
                'name' => $data['name']
            ]);
            
            return [
                'success' => true,
                'api_key_id' => $apiKeyId,
                'api_key' => $apiKey
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create API key'
        ];
    }
    
    /**
     * List API keys
     */
    public function listApiKeys() {
        $stmt = $this->db->query("
            SELECT k.*, u.username as created_by_username
            FROM api_keys k
            LEFT JOIN users u ON k.created_by = u.id
            ORDER BY k.created_at DESC
        ");
        
        $apiKeys = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Mask the actual API keys
        foreach ($apiKeys as &$key) {
            if (isset($key['api_key']) && strlen($key['api_key']) > 8) {
                $key['api_key_masked'] = substr($key['api_key'], 0, 4) . '...' . substr($key['api_key'], -4);
            } else {
                $key['api_key_masked'] = '********';
            }
        }
        
        return [
            'success' => true,
            'api_keys' => $apiKeys
        ];
    }
    
    /**
     * Delete API key
     */
    public function deleteApiKey($apiKeyId, $userId) {
        // Check if API key exists
        $stmt = $this->db->prepare("SELECT name FROM api_keys WHERE id = :id");
        $stmt->execute(['id' => $apiKeyId]);
        $apiKeyName = $stmt->fetchColumn();
        
        if (!$apiKeyName) {
            return [
                'success' => false,
                'message' => 'API key not found'
            ];
        }
        
        // Delete API key with FK-safe steps
        try {
            // Begin transaction to keep FK updates atomic
            $this->db->beginTransaction();

            // Clear references from audit_log to satisfy FK constraints
            $stmt = $this->db->prepare("UPDATE audit_log SET api_key_id = NULL WHERE api_key_id = :id");
            $stmt->execute(['id' => $apiKeyId]);

            // Delete API key
            $stmt = $this->db->prepare("DELETE FROM api_keys WHERE id = :id");
            $success = $stmt->execute(['id' => $apiKeyId]);

            if (!$success) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to delete API key'
                ];
            }

            $this->db->commit();

            // Log the deletion
            $user = new User($this->db);
            $user->loadById($userId);
            $user->logAudit('API_KEY_DELETE', [
                'api_key_id' => $apiKeyId,
                'name' => $apiKeyName
            ]);
            
            return [
                'success' => true
            ];
        } catch (\Throwable $e) {
            // Ensure we roll back if transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Failed to delete API key: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update API key
     */
    public function updateApiKey($apiKeyId, $data, $userId) {
        // Check if API key exists
        $stmt = $this->db->prepare("SELECT name FROM api_keys WHERE id = :id");
        $stmt->execute(['id' => $apiKeyId]);
        $apiKeyName = $stmt->fetchColumn();
        
        if (!$apiKeyName) {
            return [
                'success' => false,
                'message' => 'API key not found'
            ];
        }
        
        // Build update query
        $allowedFields = [
            'name', 'description', 'active', 'expires_at', 'allowed_ips'
        ];
        
        $updates = [];
        $params = ['id' => $apiKeyId];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = :$field";
                $params[$field] = $value;
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => true,
                'message' => 'No changes to update'
            ];
        }
        
        $updateString = implode(', ', $updates);
        $sql = "UPDATE api_keys SET $updateString WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            // Log the update
            $user = new User($this->db);
            $user->loadById($userId);
            $user->logAudit('API_KEY_UPDATE', [
                'api_key_id' => $apiKeyId,
                'name' => $data['name'] ?? $apiKeyName,
                'updated_fields' => array_keys($data)
            ]);
            
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to update API key'
        ];
    }
    
    /**
     * Generate a secure API key
     */
    private function generateApiKey() {
        return bin2hex(random_bytes(24)); // 48 characters
    }
    
    /**
     * Log API access
     */
    private function logApiAccess($apiKeyId, $actionType, $success, $details = []) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                action_type, action_details, api_key_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :api_key_id,
                :ip_address, :user_agent
            )
        ");
        
        $details['success'] = $success;
        
        $stmt->execute([
            'action_type' => $actionType,
            'action_details' => json_encode($details),
            'api_key_id' => $apiKeyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
}
