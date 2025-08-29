<?php
/**
 * User.php
 * 
 * User model for authentication and user management
 */

namespace GateController\Models;

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $isAdmin;
    private $active;
    private $createdAt;
    private $lastLogin;
    private $mustChangePassword = false;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Load user by ID
     */
    public function loadById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user) {
            $this->populateFromArray($user);
            return true;
        }
        
        return false;
    }
    
    /**
     * Load user by username
     */
    public function loadByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user) {
            $this->populateFromArray($user);
            return true;
        }
        
        return false;
    }
    
    /**
     * Populate user object from database row
     */
    private function populateFromArray(array $data) {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->email = $data['email'] ?? null;
        $this->isAdmin = (bool) $data['is_admin'];
        $this->active = (bool) $data['active'];
        $this->createdAt = $data['created_at'];
        $this->lastLogin = $data['last_login'] ?? null;
        $this->mustChangePassword = isset($data['must_change_password']) ? (bool)$data['must_change_password'] : false;
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username AND active = 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $this->populateFromArray($user);
            $this->updateLastLogin();
            return true;
        }
        
        return false;
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin() {
        $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $this->id]);
    }
    
    /**
     * Create new user
     */
    public function create($username, $password, $email = null, $isAdmin = false) {
        // Check if username already exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Username already exists
        }
        
        // Create new user
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, email, is_admin) 
            VALUES (:username, :password, :email, :is_admin)
        ");
        
        $success = $stmt->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email' => $email,
            'is_admin' => $isAdmin ? 1 : 0
        ]);
        
        if ($success) {
            $this->id = $this->db->lastInsertId();
            return $this->loadById($this->id);
        }
        
        return false;
    }
    
    /**
     * Update user
     */
    public function update($data) {
        $allowedFields = ['email', 'active'];
        $updates = [];
        $params = ['id' => $this->id];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = :$field";
                $params[$field] = $value;
            }
        }
        
        if (empty($updates)) {
            return true; // Nothing to update
        }
        
        $updateString = implode(', ', $updates);
        $sql = "UPDATE users SET $updateString WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            return $this->loadById($this->id);
        }
        
        return false;
    }
    
    /**
     * Change password
     */
    public function changePassword($newPassword) {
        $stmt = $this->db->prepare("UPDATE users SET password = :password, must_change_password = 0 WHERE id = :id");
        $success = $stmt->execute([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'id' => $this->id
        ]);
        
        return $success;
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        $stmt = $this->db->query("SELECT id, username, email, is_admin, active, created_at, last_login FROM users ORDER BY username");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function isAdmin() { return $this->isAdmin; }
    public function isActive() { return $this->active; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getLastLogin() { return $this->lastLogin; }
    public function requiresPasswordChange() { return (bool)$this->mustChangePassword; }
    
    /**
     * Check if current user is authenticated
     */
    public function isAuthenticated() {
        return isset($this->id);
    }
    
    /**
     * Log audit entry for user actions
     */
    public function logAudit($actionType, $actionDetails) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                action_type, action_details, user_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :user_id,
                :ip_address, :user_agent
            )
        ");
        
        return $stmt->execute([
            'action_type' => $actionType,
            'action_details' => json_encode($actionDetails),
            'user_id' => $this->id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
}
