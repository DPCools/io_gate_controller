<?php
/**
 * Device.php
 * 
 * Device model for managing gate controller devices
 */

namespace GateController\Models;

class Device {
    private $db;
    private $id;
    private $name;
    private $host;
    private $port;
    private $scheme;
    private $username;
    private $password;
    private $description;
    private $basePath;
    private $insecure;
    private $tlsv1_2;
    private $auth;
    private $createdBy;
    private $updatedBy;
    private $createdAt;
    private $updatedAt;
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Load device by ID
     */
    public function loadById($id) {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($device) {
            $this->populateFromArray($device);
            return true;
        }
        
        return false;
    }
    
    /**
     * Load device by name
     */
    public function loadByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE name = :name");
        $stmt->execute(['name' => $name]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($device) {
            $this->populateFromArray($device);
            return true;
        }
        
        return false;
    }
    
    /**
     * Populate device object from database row
     */
    private function populateFromArray(array $data) {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->host = $data['host'];
        $this->port = $data['port'];
        $this->scheme = $data['scheme'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->description = $data['description'];
        $this->basePath = $data['base_path'];
        $this->insecure = (bool) $data['insecure'];
        $this->tlsv1_2 = (bool) $data['tlsv1_2'];
        $this->auth = $data['auth'];
        $this->createdBy = $data['created_by'];
        $this->updatedBy = $data['updated_by'];
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }
    
    /**
     * Create new device
     */
    public function create($data, $userId) {
        // Check if name already exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM devices WHERE name = :name");
        $stmt->execute(['name' => $data['name']]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Device name already exists'
            ];
        }
        
        // Create new device
        $stmt = $this->db->prepare("
            INSERT INTO devices (
                name, host, port, scheme, username, password, 
                description, base_path, insecure, tlsv1_2, auth,
                created_by
            ) VALUES (
                :name, :host, :port, :scheme, :username, :password,
                :description, :base_path, :insecure, :tlsv1_2, :auth,
                :created_by
            )
        ");
        
        $success = $stmt->execute([
            'name' => $data['name'],
            'host' => $data['host'],
            'port' => $data['port'] ?? 443,
            'scheme' => $data['scheme'] ?? 'https',
            'username' => $data['username'],
            'password' => $data['password'],
            'description' => $data['description'] ?? null,
            'base_path' => $data['base_path'] ?? '/',
            'insecure' => isset($data['insecure']) ? (int)$data['insecure'] : 0,
            'tlsv1_2' => isset($data['tlsv1_2']) ? (int)$data['tlsv1_2'] : 1,
            'auth' => $data['auth'] ?? 'digest',
            'created_by' => $userId
        ]);
        
        if ($success) {
            $this->id = $this->db->lastInsertId();
            $this->loadById($this->id);
            
            return [
                'success' => true,
                'device_id' => $this->id
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create device'
        ];
    }
    
    /**
     * Update device
     */
    public function update($data, $userId) {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Device not loaded'
            ];
        }
        
        // Check if name already exists for another device
        if (isset($data['name']) && $data['name'] !== $this->name) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM devices WHERE name = :name AND id != :id");
            $stmt->execute([
                'name' => $data['name'],
                'id' => $this->id
            ]);
            if ($stmt->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'message' => 'Device name already exists'
                ];
            }
        }
        
        // Normalize incoming data
        if (array_key_exists('password', $data) && trim((string)$data['password']) === '') {
            // Do not update password if left blank
            unset($data['password']);
        }
        if (array_key_exists('insecure', $data)) {
            $data['insecure'] = (int)$data['insecure'];
        }
        if (array_key_exists('tlsv1_2', $data)) {
            $data['tlsv1_2'] = (int)$data['tlsv1_2'];
        }

        // Build update query
        $allowedFields = [
            'name', 'host', 'port', 'scheme', 'username', 'password',
            'description', 'base_path', 'insecure', 'tlsv1_2', 'auth'
        ];
        
        $updates = [];
        $params = ['id' => $this->id, 'updated_by' => $userId];
        
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
        
        $updates[] = "updated_by = :updated_by";
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        
        $updateString = implode(', ', $updates);
        $sql = "UPDATE devices SET $updateString WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            $this->loadById($this->id);
            
            return [
                'success' => true,
                'device_id' => $this->id
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to update device'
        ];
    }
    
    /**
     * Delete device
     */
    public function delete() {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Device not loaded'
            ];
        }
        
        // Check if device is in use by any gates
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM gates WHERE device_id = :device_id");
        $stmt->execute(['device_id' => $this->id]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Cannot delete device because it is in use by one or more gates'
            ];
        }
        
        // Delete device
        $stmt = $this->db->prepare("DELETE FROM devices WHERE id = :id");
        $success = $stmt->execute(['id' => $this->id]);
        
        if ($success) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to delete device'
        ];
    }
    
    /**
     * Get all devices
     */
    public function getAllDevices() {
        $stmt = $this->db->query("
            SELECT d.*, 
                   u1.username as created_by_username, 
                   u2.username as updated_by_username
            FROM devices d
            LEFT JOIN users u1 ON d.created_by = u1.id
            LEFT JOIN users u2 ON d.updated_by = u2.id
            ORDER BY d.name
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Test device connection
     */
    public function testConnection() {
        if (!$this->id) {
            return [
                'success' => false,
                'message' => 'Device not loaded'
            ];
        }

        $isReachableCode = function($code) {
            if (!is_int($code)) return false;
            return ($code >= 200 && $code < 400) || $code === 401 || $code === 403;
        };

        $schemes = [];
        if ($this->scheme === 'http' || $this->scheme === 'https') {
            $schemes[] = $this->scheme;
            $schemes[] = ($this->scheme === 'http') ? 'https' : 'http';
        } else {
            $schemes = ['http', 'https'];
        }

        $basePath = $this->basePath ?: '/';
        if ($basePath === '') { $basePath = '/'; }
        if ($basePath[0] !== '/') { $basePath = '/' . $basePath; }
        $paths = [$basePath];
        if ($basePath !== '/') { $paths[] = '/'; }

        foreach ($schemes as $scheme) {
            foreach ($paths as $path) {
                $url = $scheme . '://' . $this->host;
                if (($scheme === 'http' && (int)$this->port !== 80) || ($scheme === 'https' && (int)$this->port !== 443)) {
                    $url .= ':' . $this->port;
                }
                $url .= $path;

                // Use cURL to support Basic and Digest auth properly
                $ch = curl_init();
                $headers = [
                    'User-Agent: GateController/2.0',
                    'Connection: close'
                ];
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_NOBODY => false, // some devices don't support HEAD properly
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true, // include headers so we don't need the body
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_HTTPHEADER => $headers,
                ]);

                // TLS verification options based on settings
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->insecure ? false : ($this->tlsv1_2 ? true : false));
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->insecure ? 0 : 2);
                if ($this->tlsv1_2) {
                    // Prefer TLS 1.2 when requested
                    if (defined('CURL_SSLVERSION_TLSv1_2')) {
                        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                    }
                }

                // Authentication
                if ($this->username && $this->password) {
                    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
                    if ($this->auth === 'basic') {
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    } elseif ($this->auth === 'digest') {
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    } else {
                        // auto-detect if unspecified
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    }
                }

                try {
                    $response = @curl_exec($ch);
                    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);

                    if ($isReachableCode($httpCode)) {
                        return [
                            'success' => true,
                            'message' => 'Reachable: HTTP ' . $httpCode,
                            'url' => $url
                        ];
                    }
                } catch (\Throwable $e) {
                    // Try next combination
                }

                // Fallback if cURL is unavailable or inconclusive: use streams
                if (!function_exists('curl_init') || !isset($httpCode) || $httpCode === 0) {
                    $options = [
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                'User-Agent: GateController/2.0',
                                'Connection: close'
                            ],
                            'timeout' => 6
                        ],
                        'ssl' => [
                            'verify_peer' => $this->tlsv1_2 ? true : false,
                            'verify_peer_name' => $this->tlsv1_2 ? true : false,
                            'allow_self_signed' => $this->insecure ? true : false,
                        ]
                    ];
                    if ($this->auth === 'basic' && $this->username && $this->password) {
                        $options['http']['header'][] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
                    }
                    try {
                        $context = stream_context_create($options);
                        @file_get_contents($url, false, $context);
                        $statusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
                        // Parse status code
                        $code = null;
                        if (is_string($statusLine) && preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
                            $code = (int)$m[1];
                        }
                        if ($code !== null && $isReachableCode($code)) {
                            return [
                                'success' => true,
                                'message' => 'Reachable: HTTP ' . $code,
                                'url' => $url
                            ];
                        }
                    } catch (\Throwable $e) {
                        // continue to next combination
                    }
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Not reachable over HTTP/HTTPS'
        ];
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function getScheme() { return $this->scheme; }
    public function getUsername() { return $this->username; }
    public function getPassword() { return $this->password; }
    public function getDescription() { return $this->description; }
    public function getBasePath() { return $this->basePath; }
    public function isInsecure() { return $this->insecure; }
    public function isTlsv1_2() { return $this->tlsv1_2; }
    public function getAuth() { return $this->auth; }
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
            'host' => $this->host,
            'port' => $this->port,
            'scheme' => $this->scheme,
            'username' => $this->username,
            'password' => '********', // Don't expose actual password
            'description' => $this->description,
            'base_path' => $this->basePath,
            'insecure' => $this->insecure,
            'tlsv1_2' => $this->tlsv1_2,
            'auth' => $this->auth,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}
