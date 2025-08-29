<?php
/**
 * DatabaseInitializer.php
 * 
 * Handles database initialization and migrations
 */

namespace GateController\Utils;

class DatabaseInitializer {
    private $config;
    private $pdo;
    
    public function __construct(array $config, \PDO $pdo = null) {
        $this->config = $config;
        $this->pdo = $pdo;
    }
    
    /**
     * Check if the database is initialized with required tables
     * 
     * @return bool
     */
    public function isDatabaseInitialized() {
        // If PDO is not set, create connection
        if (!$this->pdo) {
            $this->createDatabaseConnection();
        }
        
        // Check if required tables exist
        $requiredTables = [
            'users',
            'devices',
            'gates',
            'api_keys',
            'audit_log'
        ];
        
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table';");
        $existingTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($requiredTables as $table) {
            if (!in_array($table, $existingTables)) {
                return false;
            }
        }
        
        // Also ensure required columns exist on api_keys
        $stmt = $this->pdo->query("PRAGMA table_info('api_keys')");
        $cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $colNames = array_map(function($c){ return $c['name']; }, $cols);
        $needed = ['description','expires_at','allowed_ips'];
        foreach ($needed as $need) {
            if (!in_array($need, $colNames)) {
                // Missing a required column triggers initialization to run migrations
                return false;
            }
        }

        // Ensure newer columns exist on gates for auto-close feature
        $stmt = $this->pdo->query("PRAGMA table_info('gates')");
        $gcols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $gcolNames = array_map(function($c){ return $c['name']; }, $gcols);
        $gneeded = ['close_enabled','close_io_port','close_delay_seconds'];
        foreach ($gneeded as $need) {
            if (!in_array($need, $gcolNames)) {
                // Missing gates column means we need to run migrations
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialize the database
     */
    public function initializeDatabase() {
        $this->createDatabaseConnection();
        $this->createTables();
        // If a legacy config file exists, migrate it; otherwise seed clean defaults
        $legacyFilePath = __DIR__ . '/../../opengate.php';
        if (file_exists($legacyFilePath)) {
            $this->migrateLegacyData();
        } else {
            $this->seedDefaultData();
        }
        
        return true;
    }
    
    /**
     * Create the database connection
     */
    private function createDatabaseConnection() {
        // If PDO is already set, skip connection creation
        if ($this->pdo) {
            // Ensure pragmas are applied when an external PDO was injected
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
            $this->pdo->exec('PRAGMA temp_store = MEMORY');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            return;
        }
        
        // Get the database file path, supporting both new and old config formats
        $dbFile = $this->config['db_path'] ?? $this->config['db']['file'] ?? __DIR__ . '/../../db/axis_commands.sqlite';
        
        // Create directory if it doesn't exist
        $directory = dirname($dbFile);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Create the PDO connection
        $this->pdo = new \PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Apply pragmas to improve concurrency and reliability
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
        $this->pdo->exec('PRAGMA temp_store = MEMORY');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
    }
    
    /**
     * Create all database tables
     */
    private function createTables() {
        // Users table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT,
                is_admin BOOLEAN NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP,
                active BOOLEAN NOT NULL DEFAULT 1,
                must_change_password BOOLEAN NOT NULL DEFAULT 0
            )
        ");
        // Ensure column exists in case of upgrades from older versions
        $this->ensureColumnExists('users', 'must_change_password', 'BOOLEAN NOT NULL DEFAULT 0');
        
        // Insert default admin user if not exists (password: admin)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, is_admin, must_change_password) 
                VALUES ('admin', :password, 1, 1)
            ");
            $stmt->execute([
                // Reason: first login simplicity; user will be forced to change
                'password' => password_hash('admin', PASSWORD_BCRYPT)
            ]);
        }
        
        // Devices table (formerly sites)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                host TEXT NOT NULL,
                port INTEGER NOT NULL DEFAULT 443,
                scheme TEXT NOT NULL DEFAULT 'https',
                username TEXT NOT NULL,
                password TEXT NOT NULL,
                description TEXT,
                base_path TEXT DEFAULT '/',
                insecure BOOLEAN DEFAULT 1,
                tlsv1_2 BOOLEAN DEFAULT 1,
                auth TEXT DEFAULT 'digest',
                created_by INTEGER,
                updated_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (updated_by) REFERENCES users(id)
            )
        ");
        
        // Gates table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                device_id INTEGER NOT NULL,
                io_port INTEGER NOT NULL,
                pulse_seconds REAL NOT NULL DEFAULT 1.0,
                close_enabled BOOLEAN NOT NULL DEFAULT 0,
                close_io_port INTEGER,
                close_delay_seconds INTEGER NOT NULL DEFAULT 20,
                description TEXT,
                created_by INTEGER,
                updated_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP,
                FOREIGN KEY (device_id) REFERENCES devices(id),
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (updated_by) REFERENCES users(id)
            )
        ");

        // Ensure newer columns exist on gates (for upgrades)
        $this->ensureColumnExists('gates', 'close_enabled', 'BOOLEAN NOT NULL DEFAULT 0');
        $this->ensureColumnExists('gates', 'close_io_port', 'INTEGER');
        $this->ensureColumnExists('gates', 'close_delay_seconds', 'INTEGER NOT NULL DEFAULT 20');
        
        // API Keys for CRM integration
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                api_key TEXT NOT NULL UNIQUE,
                active BOOLEAN NOT NULL DEFAULT 1,
                created_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )
        ");

        // Ensure newer columns exist on api_keys
        $this->ensureColumnExists('api_keys', 'description', 'TEXT');
        $this->ensureColumnExists('api_keys', 'expires_at', 'TIMESTAMP');
        $this->ensureColumnExists('api_keys', 'allowed_ips', 'TEXT');
        
        // Audit log for all actions
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action_type TEXT NOT NULL,
                action_details TEXT NOT NULL,
                user_id INTEGER,
                api_key_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )
        ");
        
        // Create indexes for better performance
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_action_type ON audit_log(action_type)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_gates_device_id ON gates(device_id)");
        // Additional hot-path indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_api_key ON api_keys(api_key)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_gates_name ON gates(name)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_devices_name ON devices(name)");
    }

    /**
     * Add a column to a table if it's missing
     */
    private function ensureColumnExists($table, $column, $type) {
        $stmt = $this->pdo->query("PRAGMA table_info('" . $table . "')");
        $cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (isset($c['name']) && $c['name'] === $column) {
                return; // already exists
            }
        }
        // SQLite ALTER TABLE ADD COLUMN adds column at end; default NULL
        $this->pdo->exec("ALTER TABLE " . $table . " ADD COLUMN " . $column . " " . $type);
    }
    
    /**
     * Migrate legacy data from the old configuration
     */
    private function migrateLegacyData() {
        // Copy the legacy command queue database if it exists
        $legacyCommandsDb = __DIR__ . '/../../axis_commands.sqlite';
        $newCommandsDb = $this->config['db']['queue_file'];
        
        if (file_exists($legacyCommandsDb) && !file_exists($newCommandsDb)) {
            copy($legacyCommandsDb, $newCommandsDb);
        }
        
        // Load the legacy PHP configuration
        $legacyFilePath = __DIR__ . '/../../opengate.php';
        if (file_exists($legacyFilePath)) {
            // Extract the config variables from the legacy file
            // This is a basic implementation; a more robust solution would parse the file
            $fileContent = file_get_contents($legacyFilePath);
            
            // Extract devices from the legacy config
            if (preg_match('/\$devices\s*=\s*\[(.*?)\];/s', $fileContent, $devicesMatch)) {
                $devicesCode = $devicesMatch[1];
                
                // Extract each device block
                preg_match_all("/'(.*?)'\s*=>\s*\[(.*?)\],/s", $devicesCode, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $siteName = $match[1];
                    $siteConfig = $match[2];
                    
                    // Extract site properties
                    $host = $this->extractValue($siteConfig, 'host');
                    $port = $this->extractValue($siteConfig, 'port');
                    $scheme = $this->extractValue($siteConfig, 'scheme');
                    $username = $this->extractValue($siteConfig, 'username');
                    $password = $this->extractValue($siteConfig, 'password');
                    $description = $this->extractValue($siteConfig, 'description');
                    $basePath = $this->extractValue($siteConfig, 'base_path');
                    $insecure = $this->extractValue($siteConfig, 'insecure') === 'true' ? 1 : 0;
                    $tlsv1_2 = $this->extractValue($siteConfig, 'tlsv1_2') === 'true' ? 1 : 0;
                    $auth = $this->extractValue($siteConfig, 'auth');
                    
                    // Insert device if it doesn't exist
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices WHERE name = :name");
                    $stmt->execute(['name' => $siteName]);
                    
                    if ($stmt->fetchColumn() == 0) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO devices (
                                name, host, port, scheme, username, password, 
                                description, base_path, insecure, tlsv1_2, auth,
                                created_by
                            ) VALUES (
                                :name, :host, :port, :scheme, :username, :password,
                                :description, :base_path, :insecure, :tlsv1_2, :auth,
                                1
                            )
                        ");
                        
                        $stmt->execute([
                            'name' => $siteName,
                            'host' => $host,
                            'port' => $port,
                            'scheme' => $scheme,
                            'username' => $username,
                            'password' => $password,
                            'description' => $description,
                            'base_path' => $basePath,
                            'insecure' => $insecure,
                            'tlsv1_2' => $tlsv1_2,
                            'auth' => $auth
                        ]);
                        
                        // Get the device ID
                        $deviceId = $this->pdo->lastInsertId();
                        
                        // Log the migration in the audit log
                        $this->logAudit(
                            'MIGRATION', 
                            json_encode([
                                'action' => 'Migrated device from legacy config',
                                'device_name' => $siteName,
                                'device_id' => $deviceId
                            ]),
                            1, // Admin user
                            null
                        );
                    }
                }
            }
            
            // Extract gates from the legacy config
            if (preg_match('/\$gate_map\s*=\s*\[(.*?)\];/s', $fileContent, $gatesMatch)) {
                $gatesCode = $gatesMatch[1];
                
                // Extract each gate configuration
                preg_match_all("/'(.*?)'\s*=>\s*\['site'\s*=>\s*'(.*?)',\s*'port'\s*=>\s*(\d+),\s*'pulse_ms'\s*=>\s*(\d+)\]/s", $gatesCode, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $gateName = $match[1];
                    $siteName = $match[2];
                    $port = (int)$match[3];
                    $pulseMs = (int)$match[4];
                    $pulseSeconds = $pulseMs / 1000; // Convert milliseconds to seconds
                    
                    // Get the device ID
                    $stmt = $this->pdo->prepare("SELECT id FROM devices WHERE name = :name");
                    $stmt->execute(['name' => $siteName]);
                    $deviceId = $stmt->fetchColumn();
                    
                    if ($deviceId) {
                        // Insert gate if it doesn't exist
                        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM gates WHERE name = :name");
                        $stmt->execute(['name' => $gateName]);
                        
                        if ($stmt->fetchColumn() == 0) {
                            $stmt = $this->pdo->prepare("
                                INSERT INTO gates (
                                    name, device_id, io_port, pulse_seconds,
                                    created_by
                                ) VALUES (
                                    :name, :device_id, :io_port, :pulse_seconds,
                                    1
                                )
                            ");
                            
                            $stmt->execute([
                                'name' => $gateName,
                                'device_id' => $deviceId,
                                'io_port' => $port,
                                'pulse_seconds' => $pulseSeconds
                            ]);
                            
                            // Get the gate ID
                            $gateId = $this->pdo->lastInsertId();
                            
                            // Log the migration in the audit log
                            $this->logAudit(
                                'MIGRATION', 
                                json_encode([
                                    'action' => 'Migrated gate from legacy config',
                                    'gate_name' => $gateName,
                                    'gate_id' => $gateId,
                                    'device_id' => $deviceId
                                ]),
                                1, // Admin user
                                null
                            );
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Helper method to extract values from a config string
     */
    private function extractValue($configString, $key) {
        if (preg_match("/'$key'\s*=>\s*'(.*?)'/", $configString, $match)) {
            return $match[1];
        } elseif (preg_match("/'$key'\s*=>\s*(\d+)/", $configString, $match)) {
            return $match[1];
        } elseif (preg_match("/'$key'\s*=>\s*(true|false)/", $configString, $match)) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Log an audit entry
     */
    private function logAudit($actionType, $actionDetails, $userId = null, $apiKeyId = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_log (
                action_type, action_details, user_id, api_key_id,
                ip_address, user_agent
            ) VALUES (
                :action_type, :action_details, :user_id, :api_key_id,
                :ip_address, :user_agent
            )
        ");
        
        $stmt->execute([
            'action_type' => $actionType,
            'action_details' => $actionDetails,
            'user_id' => $userId,
            'api_key_id' => $apiKeyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }

    /**
     * Seed a default device and example gates for fresh installs
     * Names: Front_Door, Back_Door, Garage
     */
    private function seedDefaultData() {
        // Create a default device to attach example gates
        $deviceStmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices WHERE name = :name");
        $deviceStmt->execute(['name' => 'Default_Device']);
        if ($deviceStmt->fetchColumn() == 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO devices (
                    name, host, port, scheme, username, password,
                    description, base_path, insecure, tlsv1_2, auth, created_by
                ) VALUES (
                    :name, :host, :port, :scheme, :username, :password,
                    :description, :base_path, :insecure, :tlsv1_2, :auth, 1
                )
            ");
            $stmt->execute([
                'name' => 'Default_Device',
                'host' => '127.0.0.1',
                'port' => 443,
                'scheme' => 'https',
                'username' => 'admin',
                'password' => 'admin',
                'description' => 'Example device for initial setup',
                'base_path' => '/',
                'insecure' => 1,
                'tlsv1_2' => 1,
                'auth' => 'digest'
            ]);
        }
        // Get device id
        $stmt = $this->pdo->prepare("SELECT id FROM devices WHERE name = :name");
        $stmt->execute(['name' => 'Default_Device']);
        $deviceId = $stmt->fetchColumn();

        // Seed example gates
        $examples = [
            ['Front_Door', 1, 1.0],
            ['Back_Door', 2, 1.0],
            ['Garage', 3, 1.0],
        ];
        foreach ($examples as [$name, $ioPort, $pulseSeconds]) {
            $exists = $this->pdo->prepare("SELECT COUNT(*) FROM gates WHERE name = :name");
            $exists->execute(['name' => $name]);
            if ($exists->fetchColumn() == 0) {
                $ins = $this->pdo->prepare("
                    INSERT INTO gates (name, device_id, io_port, pulse_seconds, created_by)
                    VALUES (:name, :device_id, :io_port, :pulse_seconds, 1)
                ");
                $ins->execute([
                    'name' => $name,
                    'device_id' => $deviceId,
                    'io_port' => $ioPort,
                    'pulse_seconds' => $pulseSeconds,
                ]);
            }
        }
    }
}
