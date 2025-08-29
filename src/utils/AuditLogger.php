<?php
/**
 * AuditLogger.php
 * 
 * Centralized audit logging utility for the Gate Controller system
 */

namespace GateController\Utils;

class AuditLogger {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Log an action to the audit log
     * 
     * @param string $actionType The type of action (LOGIN, GATE_TRIGGER, etc)
     * @param array $details Action-specific details
     * @param int|null $userId The ID of the user who performed the action (null for system or API actions)
     * @param int|null $apiKeyId The ID of the API key used for the action (null for user or system actions)
     * @return bool True if logging succeeded, false otherwise
     */
    public function log($actionType, $details = [], $userId = null, $apiKeyId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                action_type, action_details, user_id, api_key_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :user_id, :api_key_id,
                :ip_address, :user_agent
            )
        ");
        
        // Get client information
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Execute the statement
        return $stmt->execute([
            'action_type' => $actionType,
            'action_details' => json_encode($details),
            'user_id' => $userId,
            'api_key_id' => $apiKeyId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }
    
    /**
     * Log a user action
     */
    public function logUserAction($actionType, $details = [], $userId = null) {
        return $this->log($actionType, $details, $userId);
    }
    
    /**
     * Log an API action
     */
    public function logApiAction($actionType, $details = [], $apiKeyId = null) {
        return $this->log($actionType, $details, null, $apiKeyId);
    }
    
    /**
     * Log a system action
     */
    public function logSystemAction($actionType, $details = []) {
        return $this->log($actionType, $details);
    }
    
    /**
     * Get a list of audit log entries with filtering
     */
    public function getAuditLogs($filters = [], $page = 1, $perPage = 20) {
        $where = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['action_type'])) {
            $where[] = "action_type = :action_type";
            $params['action_type'] = $filters['action_type'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = "api_key_id = :api_key_id";
            $params['api_key_id'] = $filters['api_key_id'];
        }
        
        if (!empty($filters['ip_address'])) {
            $where[] = "ip_address LIKE :ip_address";
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "timestamp >= :from_date";
            $params['from_date'] = $filters['from_date'] . ' 00:00:00';
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "timestamp <= :to_date";
            $params['to_date'] = $filters['to_date'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(action_details LIKE :search OR user_agent LIKE :search)";
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
        $sql .= " ORDER BY l.timestamp DESC";
        
        // Add pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        // Execute query
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters
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
        
        // Process logs to extract details
        foreach ($logs as &$log) {
            if (!empty($log['action_details'])) {
                $log['details'] = json_decode($log['action_details'], true);
            } else {
                $log['details'] = [];
            }
        }
        
        return [
            'logs' => $logs,
            'pagination' => [
                'total' => $totalCount,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalCount / $perPage)
            ]
        ];
    }
}
