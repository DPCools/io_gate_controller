<?php
/**
 * AuthController.php
 * 
 * Handles authentication-related functionality
 */

namespace GateController\Controllers;

use GateController\Models\User;
// Load User model explicitly (no Composer autoload)
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $db;
    private $config;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db, array $config) {
        $this->db = $db;
        $this->config = $config;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Process login
     */
    public function login($username, $password, $remember = false) {
        $user = new User($this->db);
        
        if ($user->authenticate($username, $password)) {
            // Set session variables
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['username'] = $user->getUsername();
            $_SESSION['is_admin'] = $user->isAdmin();
            $_SESSION['last_activity'] = time();
            // If password rotation is required, mark session to force redirect
            if (method_exists($user, 'requiresPasswordChange') && $user->requiresPasswordChange()) {
                $_SESSION['must_change_password'] = true;
            } else {
                unset($_SESSION['must_change_password']);
            }
            
            // Log successful login
            $user->logAudit('LOGIN', [
                'username' => $username,
                'success' => true
            ]);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'is_admin' => $user->isAdmin(),
                    'must_change_password' => method_exists($user, 'requiresPasswordChange') ? $user->requiresPasswordChange() : false
                ]
            ];
        }
        
        // Log failed login attempt
        $user->logAudit('LOGIN', [
            'username' => $username,
            'success' => false,
            'reason' => 'Invalid credentials'
        ]);
        
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    /**
     * Process registration
     */
    public function register($username, $password, $email = null, $isAdmin = false) {
        // Only admins can create other admins
        if ($isAdmin && (!$this->isAuthenticated() || !$this->isAdmin())) {
            return [
                'success' => false,
                'message' => 'Insufficient privileges to create admin user'
            ];
        }
        
        // Validate inputs
        if (strlen($username) < 3) {
            return [
                'success' => false,
                'message' => 'Username must be at least 3 characters'
            ];
        }
        
        if (strlen($password) < $this->config['security']['password_min_length']) {
            return [
                'success' => false,
                'message' => 'Password must be at least ' . $this->config['security']['password_min_length'] . ' characters'
            ];
        }
        
        // Create new user
        $user = new User($this->db);
        $result = $user->create($username, $password, $email, $isAdmin);
        
        if ($result) {
            // Log user creation
            if ($this->isAuthenticated()) {
                $currentUser = new User($this->db);
                $currentUser->loadById($_SESSION['user_id']);
                $currentUser->logAudit('USER_CREATE', [
                    'created_username' => $username,
                    'is_admin' => $isAdmin
                ]);
            } else {
                $user->logAudit('SELF_REGISTER', [
                    'username' => $username
                ]);
            }
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'is_admin' => $user->isAdmin()
                ]
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Username already exists'
        ];
    }
    
    /**
     * Logout
     */
    public function logout() {
        if ($this->isAuthenticated()) {
            // Log logout
            $user = new User($this->db);
            $user->loadById($_SESSION['user_id']);
            $user->logAudit('LOGOUT', [
                'username' => $_SESSION['username']
            ]);
            
            // Destroy session
            session_unset();
            session_destroy();
        }
        
        return [
            'success' => true
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        // Check if user is logged in
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            // Check session timeout
            $timeout = $this->config['security']['session_lifetime'];
            if (time() - $_SESSION['last_activity'] < $timeout) {
                // Update last activity time
                $_SESSION['last_activity'] = time();
                return true;
            } else {
                // Session expired, logout
                $this->logout();
            }
        }
        
        return false;
    }
    
    /**
     * Check if user is an admin
     */
    public function isAdmin() {
        return $this->isAuthenticated() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if ($this->isAuthenticated()) {
            $user = new User($this->db);
            $user->loadById($_SESSION['user_id']);
            
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'is_admin' => $user->isAdmin(),
                'must_change_password' => method_exists($user, 'requiresPasswordChange') ? $user->requiresPasswordChange() : false
            ];
        }
        
        return null;
    }
    
    /**
     * Generate API key for CRM integration
     */
    public function generateApiKey($name) {
        if (!$this->isAuthenticated() || !$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Insufficient privileges to create API key'
            ];
        }
        
        // Generate a random API key
        $apiKey = bin2hex(random_bytes(32));
        
        // Store API key in database
        $stmt = $this->db->prepare("
            INSERT INTO api_keys (name, api_key, created_by)
            VALUES (:name, :api_key, :user_id)
        ");
        
        $success = $stmt->execute([
            'name' => $name,
            'api_key' => $apiKey,
            'user_id' => $_SESSION['user_id']
        ]);
        
        if ($success) {
            // Log API key creation
            $user = new User($this->db);
            $user->loadById($_SESSION['user_id']);
            $user->logAudit('API_KEY_CREATE', [
                'name' => $name,
                'api_key_id' => $this->db->lastInsertId()
            ]);
            
            return [
                'success' => true,
                'api_key' => $apiKey
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create API key'
        ];
    }
    
    /**
     * List all API keys
     */
    public function listApiKeys() {
        if (!$this->isAuthenticated() || !$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Insufficient privileges to list API keys'
            ];
        }
        
        $stmt = $this->db->query("
            SELECT id, name, active, created_by, created_at, last_used_at
            FROM api_keys
            ORDER BY name
        ");
        
        return [
            'success' => true,
            'api_keys' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
    
    /**
     * Revoke API key
     */
    public function revokeApiKey($keyId) {
        if (!$this->isAuthenticated() || !$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Insufficient privileges to revoke API key'
            ];
        }
        
        $stmt = $this->db->prepare("UPDATE api_keys SET active = 0 WHERE id = :id");
        $success = $stmt->execute(['id' => $keyId]);
        
        if ($success) {
            // Log API key revocation
            $user = new User($this->db);
            $user->loadById($_SESSION['user_id']);
            $user->logAudit('API_KEY_REVOKE', [
                'api_key_id' => $keyId
            ]);
            
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to revoke API key'
        ];
    }
    
    /**
     * Validate API key
     */
    public function validateApiKey($apiKey) {
        $stmt = $this->db->prepare("
            SELECT id, name, active 
            FROM api_keys 
            WHERE api_key = :api_key AND active = 1
        ");
        
        $stmt->execute(['api_key' => $apiKey]);
        $key = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($key) {
            // Update last used timestamp
            $updateStmt = $this->db->prepare("
                UPDATE api_keys 
                SET last_used_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $key['id']]);
            
            // Log API key usage
            $this->logApiKeyUsage($key['id']);
            
            return [
                'success' => true,
                'api_key_id' => $key['id'],
                'name' => $key['name']
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Invalid API key'
        ];
    }
    
    /**
     * Log API key usage
     */
    private function logApiKeyUsage($apiKeyId) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                action_type, action_details, api_key_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :api_key_id,
                :ip_address, :user_agent
            )
        ");
        
        return $stmt->execute([
            'action_type' => 'API_KEY_USED',
            'action_details' => json_encode([
                'endpoint' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
            ]),
            'api_key_id' => $apiKeyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
}
