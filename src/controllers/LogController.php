<?php
/**
 * LogController.php
 * 
 * Controller for log and audit management
 */

namespace GateController\Controllers;

class LogController {
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
     * Get logs with filtering
     */
    public function getLogs($filters = []) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $allowedActionTypes = [
            'LOGIN', 'LOGOUT', 'USER_CREATE', 'USER_UPDATE', 'USER_DELETE',
            'DEVICE_CREATE', 'DEVICE_UPDATE', 'DEVICE_DELETE', 'DEVICE_TEST',
            'GATE_CREATE', 'GATE_UPDATE', 'GATE_DELETE', 'GATE_TRIGGER', 'GATE_TEST',
            'API_KEY_CREATE', 'API_KEY_UPDATE', 'API_KEY_DELETE', 'API_REQUEST',
            'CONFIG_CHANGE', 'SYSTEM'
        ];
        
        $where = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['action_type'])) {
            if (in_array($filters['action_type'], $allowedActionTypes)) {
                $where[] = "l.action_type = :action_type";
                $params['action_type'] = $filters['action_type'];
            }
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "l.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = "l.api_key_id = :api_key_id";
            $params['api_key_id'] = $filters['api_key_id'];
        }
        
        if (!empty($filters['ip_address'])) {
            $where[] = "l.ip_address LIKE :ip_address";
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "l.created_at >= :from_date";
            $params['from_date'] = $filters['from_date'] . ' 00:00:00';
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "l.created_at <= :to_date";
            $params['to_date'] = $filters['to_date'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(l.action_details LIKE :search OR l.user_agent LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Build query
        $sql = "SELECT 
                    l.*,
                    u.username,
                    k.name as api_key_name
                FROM audit_log l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN api_keys k ON l.api_key_id = k.id";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add sorting
        $sql .= " ORDER BY l.created_at DESC";
        
        // Add limit for pagination (default 100, cap at 100)
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 100;
        if ($perPage <= 0) { $perPage = 100; }
        if ($perPage > 100) { $perPage = 100; }
        $offset = ($page - 1) * $perPage;
        
        $sql .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        // Execute query
        $stmt = $this->db->prepare($sql);
        
        // Bind params with correct type
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        $stmt->execute();
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM audit_log l";
        if (!empty($where)) {
            $countSql .= " WHERE " . implode(" AND ", $where);
        }
        
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            if ($key !== 'limit' && $key !== 'offset') {
                $countStmt->bindValue(":$key", $value);
            }
        }
        
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        
        // Process logs to extract action details
        foreach ($logs as &$log) {
            if (!empty($log['action_details'])) {
                $log['details'] = json_decode($log['action_details'], true);
            } else {
                $log['details'] = [];
            }
        }
        
        return [
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'total' => $totalCount,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalCount / $perPage)
            ]
        ];
    }
    
    /**
     * Get recent activity for dashboard
     */
    public function getRecentActivity($limit = 5) {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        $sql = "SELECT 
                    l.*,
                    u.username,
                    k.name as api_name
                FROM audit_log l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN api_keys k ON l.api_key_id = k.id
                WHERE l.action_type = 'GATE_TRIGGER'
                ORDER BY l.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Process logs to extract gate name and success status
        foreach ($activities as &$activity) {
            $details = json_decode($activity['action_details'], true);
            $activity['gate_name'] = $details['gate_name'] ?? 'Unknown';
            $activity['success'] = $details['success'] ?? false;
        }
        
        return [
            'success' => true,
            'activities' => $activities
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        if (!$this->authController->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'Authentication required'
            ];
        }
        
        // Get gate count
        $stmt = $this->db->query("SELECT COUNT(*) FROM gates");
        $gateCount = $stmt->fetchColumn();
        
        // Get device count
        $stmt = $this->db->query("SELECT COUNT(*) FROM devices");
        $deviceCount = $stmt->fetchColumn();
        
        // Get triggers today count
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM audit_log 
            WHERE action_type = 'GATE_TRIGGER' 
            AND created_at >= :start_date
        ");
        $stmt->execute(['start_date' => $today . ' 00:00:00']);
        $triggerCount = $stmt->fetchColumn();
        
        return [
            'success' => true,
            'stats' => [
                'gateCount' => $gateCount,
                'deviceCount' => $deviceCount,
                'triggerCount' => $triggerCount
            ]
        ];
    }
    
    /**
     * Get action types for filter dropdowns
     */
    public function getActionTypes() {
        return [
            'GATE_TRIGGER' => 'Gate Trigger',
            'GATE_TEST' => 'Gate Test',
            'GATE_CREATE' => 'Gate Created',
            'GATE_UPDATE' => 'Gate Updated',
            'GATE_DELETE' => 'Gate Deleted',
            'DEVICE_TEST' => 'Device Test',
            'DEVICE_CREATE' => 'Device Created',
            'DEVICE_UPDATE' => 'Device Updated',
            'DEVICE_DELETE' => 'Device Deleted',
            'LOGIN' => 'User Login',
            'LOGOUT' => 'User Logout',
            'USER_CREATE' => 'User Created',
            'USER_UPDATE' => 'User Updated',
            'USER_DELETE' => 'User Deleted',
            'API_KEY_CREATE' => 'API Key Created',
            'API_KEY_UPDATE' => 'API Key Updated',
            'API_KEY_DELETE' => 'API Key Deleted',
            'API_REQUEST' => 'API Request',
            'CONFIG_CHANGE' => 'Config Change',
            'SYSTEM' => 'System Event'
        ];
    }
}
