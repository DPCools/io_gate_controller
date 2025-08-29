<?php
/**
 * GateController.php
 * 
 * Controller for gate management
 */

namespace GateController\Controllers;

use GateController\Models\Gate;
use GateController\Models\User;
use GateController\Models\Device;
// Explicitly include models (no Composer autoload in this project)
require_once __DIR__ . '/../models/Gate.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Device.php';

class GateController {
    private $db;
    private $config;
    private $authController;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db, array $config, AuthController $authController) {
        $this->db = $db;
        $this->config = $config;
        $this->authController = $authController;
    }
    
    /**
     * List all gates
     */
    public function listGates() {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $gate = new Gate($this->db);
        $gates = $gate->getAllGates();
        
        return [
            'success' => true,
            'gates' => $gates
        ];
    }
    
    /**
     * Get gate details
     */
    public function getGate($gateId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $gate = new Gate($this->db);
        if ($gate->loadById($gateId)) {
            $gateData = $gate->toArray();
            
            // Get device information
            $device = new Device($this->db);
            $deviceInfo = null;
            if ($device->loadById($gateData['device_id'])) {
                $deviceInfo = [
                    'id' => $device->getId(),
                    'name' => $device->getName(),
                    'host' => $device->getHost()
                ];
            }
            
            // Get information about who created/updated this gate
            $createdBy = null;
            $updatedBy = null;
            
            if ($gateData['created_by']) {
                $user = new User($this->db);
                if ($user->loadById($gateData['created_by'])) {
                    $createdBy = [
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    ];
                }
            }
            
            if ($gateData['updated_by']) {
                $user = new User($this->db);
                if ($user->loadById($gateData['updated_by'])) {
                    $updatedBy = [
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    ];
                }
            }
            
            return [
                'success' => true,
                'gate' => $gateData,
                'device' => $deviceInfo,
                'created_by' => $createdBy,
                'updated_by' => $updatedBy
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Gate not found'
        ];
    }
    
    /**
     * Create new gate
     */
    public function createGate($data) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        // Validate device exists
        $device = new Device($this->db);
        if (!$device->loadById($data['device_id'])) {
            return [
                'success' => false,
                'message' => 'Device not found'
            ];
        }
        
        // Normalize auto-close fields from form
        $data['close_enabled'] = isset($data['close_enabled']) ? 1 : 0;
        if (array_key_exists('close_io_port', $data)) {
            $data['close_io_port'] = ($data['close_io_port'] === '' || $data['close_io_port'] === null)
                ? null
                : (int)$data['close_io_port'];
        }
        if (array_key_exists('close_delay_seconds', $data)) {
            $data['close_delay_seconds'] = (int)$data['close_delay_seconds'];
            if ($data['close_delay_seconds'] <= 0) { $data['close_delay_seconds'] = 20; }
        }

        $gate = new Gate($this->db);
        $result = $gate->create($data, $currentUser['id']);
        
        if ($result['success']) {
            // Log gate creation
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('GATE_CREATE', [
                'gate_id' => $result['gate_id'],
                'gate_name' => $data['name'],
                'device_id' => $data['device_id'],
                'io_port' => $data['io_port'],
                'pulse_seconds' => $data['pulse_seconds']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Update gate
     */
    public function updateGate($gateId, $data) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $gate = new Gate($this->db);
        if (!$gate->loadById($gateId)) {
            return [
                'success' => false,
                'message' => 'Gate not found'
            ];
        }
        
        // If device_id is provided, validate it exists
        if (isset($data['device_id'])) {
            $device = new Device($this->db);
            if (!$device->loadById($data['device_id'])) {
                return [
                    'success' => false,
                    'message' => 'Device not found'
                ];
            }
        }
        
        // Normalize auto-close fields from form
    $data['close_enabled'] = isset($data['close_enabled']) ? 1 : 0;
    if (array_key_exists('close_io_port', $data)) {
        $data['close_io_port'] = ($data['close_io_port'] === '' || $data['close_io_port'] === null)
            ? null
            : (int)$data['close_io_port'];
    }
    if (array_key_exists('close_delay_seconds', $data)) {
        $data['close_delay_seconds'] = (int)$data['close_delay_seconds'];
        if ($data['close_delay_seconds'] <= 0) { $data['close_delay_seconds'] = 20; }
    }

    $result = $gate->update($data, $currentUser['id']);
        
        if ($result['success']) {
            // Log gate update
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('GATE_UPDATE', [
                'gate_id' => $gateId,
                'gate_name' => isset($data['name']) ? $data['name'] : $gate->getName(),
                'updated_fields' => array_keys($data)
            ]);
        }
        
        return $result;
    }
    
    /**
     * Delete gate
     */
    public function deleteGate($gateId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $gate = new Gate($this->db);
        if (!$gate->loadById($gateId)) {
            return [
                'success' => false,
                'message' => 'Gate not found'
            ];
        }
        
        // Store gate info before deletion for logging
        $gateName = $gate->getName();
        $deviceId = $gate->getDeviceId();
        
        $result = $gate->delete();
        
        if ($result['success']) {
            // Log gate deletion
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('GATE_DELETE', [
                'gate_id' => $gateId,
                'gate_name' => $gateName,
                'device_id' => $deviceId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Trigger gate
     */
    public function triggerGate($gateId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $gate = new Gate($this->db);
        if (!$gate->loadById($gateId)) {
            return [
                'success' => false,
                'message' => 'Gate not found'
            ];
        }
        
        $result = $gate->trigger($currentUser['id']);
        
        return $result;
    }
    
    /**
     * Test gate
     */
    public function testGate($gateId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $gate = new Gate($this->db);
        if (!$gate->loadById($gateId)) {
            return [
                'success' => false,
                'message' => 'Gate not found'
            ];
        }
        
        $result = $gate->test($currentUser['id']);
        
        return $result;
    }
    
    /**
     * Trigger gate by name (for CRM API integration)
     */
    public function triggerGateByName($gateName, $apiKeyId = null) {
        $gate = new Gate($this->db);
        if (!$gate->loadByName($gateName)) {
            return [
                'success' => false,
                'message' => 'Gate not found'
            ];
        }
        
        $result = $gate->trigger(null, $apiKeyId);
        
        return $result;
    }
    
    /**
     * Get gates by device
     */
    public function getGatesByDevice($deviceId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $gate = new Gate($this->db);
        $gates = $gate->getGatesByDeviceId($deviceId);
        
        return [
            'success' => true,
            'gates' => $gates
        ];
    }
}
