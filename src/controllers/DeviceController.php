<?php
/**
 * DeviceController.php
 * 
 * Controller for device management
 */

namespace GateController\Controllers;

use GateController\Models\Device;
use GateController\Models\User;
// Explicitly include models (no Composer autoload in this project)
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/User.php';

class DeviceController {
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
     * List all devices
     */
    public function listDevices() {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $device = new Device($this->db);
        $devices = $device->getAllDevices();
        
        // Mask passwords
        foreach ($devices as &$d) {
            $d['password'] = '********';
        }
        
        return [
            'success' => true,
            'devices' => $devices
        ];
    }
    
    /**
     * Get device details
     */
    public function getDevice($deviceId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $device = new Device($this->db);
        if ($device->loadById($deviceId)) {
            $deviceData = $device->toArray();
            
            // Get information about who created/updated this device
            $createdBy = null;
            $updatedBy = null;
            
            if ($deviceData['created_by']) {
                $user = new User($this->db);
                if ($user->loadById($deviceData['created_by'])) {
                    $createdBy = [
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    ];
                }
            }
            
            if ($deviceData['updated_by']) {
                $user = new User($this->db);
                if ($user->loadById($deviceData['updated_by'])) {
                    $updatedBy = [
                        'id' => $user->getId(),
                        'username' => $user->getUsername()
                    ];
                }
            }
            
            return [
                'success' => true,
                'device' => $deviceData,
                'created_by' => $createdBy,
                'updated_by' => $updatedBy
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Device not found'
        ];
    }
    
    /**
     * Create new device
     */
    public function createDevice($data) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $device = new Device($this->db);
        $result = $device->create($data, $currentUser['id']);
        
        if ($result['success']) {
            // Log device creation
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('DEVICE_CREATE', [
                'device_id' => $result['device_id'],
                'device_name' => $data['name'],
                'host' => $data['host']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Update device
     */
    public function updateDevice($deviceId, $data) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $device = new Device($this->db);
        if (!$device->loadById($deviceId)) {
            return [
                'success' => false,
                'message' => 'Device not found'
            ];
        }
        
        $result = $device->update($data, $currentUser['id']);
        
        if ($result['success']) {
            // Log device update
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('DEVICE_UPDATE', [
                'device_id' => $deviceId,
                'device_name' => isset($data['name']) ? $data['name'] : $device->getName(),
                'updated_fields' => array_keys($data)
            ]);
        }
        
        return $result;
    }
    
    /**
     * Delete device
     */
    public function deleteDevice($deviceId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $device = new Device($this->db);
        if (!$device->loadById($deviceId)) {
            return [
                'success' => false,
                'message' => 'Device not found'
            ];
        }
        
        // Store device info before deletion for logging
        $deviceName = $device->getName();
        
        $result = $device->delete();
        
        if ($result['success']) {
            // Log device deletion
            $user = new User($this->db);
            $user->loadById($currentUser['id']);
            $user->logAudit('DEVICE_DELETE', [
                'device_id' => $deviceId,
                'device_name' => $deviceName
            ]);
        }
        
        return $result;
    }
    
    /**
     * Test device connection
     */
    public function testConnection($deviceId) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $currentUser = $this->authController->getCurrentUser();
        
        $device = new Device($this->db);
        if (!$device->loadById($deviceId)) {
            return [
                'success' => false,
                'message' => 'Device not found'
            ];
        }
        
        $result = $device->testConnection();
        
        // Log test attempt
        $user = new User($this->db);
        $user->loadById($currentUser['id']);
        $user->logAudit('DEVICE_TEST', [
            'device_id' => $deviceId,
            'device_name' => $device->getName(),
            'success' => $result['success'],
            'message' => $result['message']
        ]);
        
        return $result;
    }
}
