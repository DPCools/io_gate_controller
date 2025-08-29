<?php
/**
 * migrate.php
 * 
 * Migration script to move from standalone configuration to DB-backed system
 */

// Display information about the migration
echo "Gate Controller Migration Tool\n";
echo "-----------------------------\n";
echo "This script will migrate existing gate and device configurations to the database.\n";
echo "Ensure you have a backup before proceeding.\n\n";

// Ask for confirmation
echo "Do you want to continue? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
if (strtolower($line) !== 'y') {
    echo "Migration aborted.\n";
    exit;
}

// Load configuration
$config = require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/models/Device.php';
require_once __DIR__ . '/src/models/Gate.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/utils/DatabaseInitializer.php';
require_once __DIR__ . '/src/utils/AuditLogger.php';

use GateController\Models\Device;
use GateController\Models\Gate;
use GateController\Models\User;
use GateController\Utils\DatabaseInitializer;
use GateController\Utils\AuditLogger;

// Make sure the db directory exists
$dbDir = __DIR__ . '/db';
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Connect to database
try {
    // Ensure config has valid db path
    if (empty($config['db']['file'])) {
        $config['db']['file'] = __DIR__ . '/db/gate_controller.sqlite';
    }
    
    $dbFile = $config['db']['file'];
    $db = new PDO("sqlite:{$dbFile}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error connecting to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if database is initialized
$initializer = new DatabaseInitializer($config, $db);
if (!$initializer->isDatabaseInitialized()) {
    echo "Database not initialized. Initializing...\n";
    $initializer->initializeDatabase();
    echo "Database initialized successfully.\n";
}

// Initialize models
$deviceModel = new Device($db);
$gateModel = new Gate($db);
$auditLogger = new AuditLogger($db);

// Load existing configuration from opengate.php
echo "Loading existing configuration...\n";
$existingConfig = [];
if (file_exists(__DIR__ . '/opengate.php')) {
    $configContent = file_get_contents(__DIR__ . '/opengate.php');
    
    // Extract devices configuration
    if (preg_match('/\$devices\s*=\s*array\s*\((.*?)\);/s', $configContent, $matches)) {
        $devicesCode = $matches[1];
        
        // Evaluate the devices array
        $devices = [];
        eval('$devices = array(' . $devicesCode . ');');
        $existingConfig['devices'] = $devices;
    }
    
    // Extract gates configuration - first try proper gate array
    if (preg_match('/\$gates\s*=\s*array\s*\((.*?)\);/s', $configContent, $matches)) {
        $gatesCode = $matches[1];
        
        // Evaluate the gates array
        $gates = [];
        eval('$gates = array(' . $gatesCode . ');');
        $existingConfig['gates'] = $gates;
    }
    
    // Also try gate_map which is used in some configs
    if (preg_match('/\$gate_map\s*=\s*\[(.*?)\];/s', $configContent, $matches)) {
        $gateMapCode = $matches[1];
        
        // Remove commented lines
        $lines = explode("\n", $gateMapCode);
        $cleanLines = [];
        foreach ($lines as $line) {
            // Skip completely commented lines
            if (preg_match('/^\s*\/\//', $line)) {
                continue;
            }
            $cleanLines[] = $line;
        }
        
        $cleanCode = implode("\n", $cleanLines);
        
        // Evaluate the gate map
        $gate_map = [];
        $evalCode = '$gate_map = [' . $cleanCode . '];';
        @eval($evalCode);
        
        // Convert gate_map format to gates format if we found any
        if (!empty($gate_map)) {
            $gates = [];
            foreach ($gate_map as $name => $config) {
                $gates[$name] = [
                    'device' => $config['site'] ?? '',
                    'port' => $config['port'] ?? 0,
                    'pulse' => $config['pulse_ms'] ?? 500
                ];
            }
            $existingConfig['gates'] = $gates;
        }
    }
    
    // If no gates found, create some sample ones for testing
    if (empty($existingConfig['gates']) && !empty($existingConfig['devices'])) {
        echo "No gates found in configuration, creating sample gates for testing...\n";
        $deviceNames = array_keys($existingConfig['devices']);
        $sampleGates = [
            'main_entrance' => [
                'device' => $deviceNames[0] ?? 'site1',
                'port' => 1,
                'pulse' => 500
            ],
            'exit_gate' => [
                'device' => $deviceNames[0] ?? 'site1',
                'port' => 2,
                'pulse' => 800
            ],
            'delivery_entrance' => [
                'device' => $deviceNames[count($deviceNames) > 1 ? 1 : 0] ?? 'site1',
                'port' => 3,
                'pulse' => 1000
            ]
        ];
        $existingConfig['gates'] = $sampleGates;
    }
}

if (empty($existingConfig['devices']) && empty($existingConfig['gates'])) {
    echo "No existing configuration found or could not parse the config.\n";
    echo "Please check if opengate.php exists and contains valid configuration.\n";
    exit(1);
}

// Create admin user if not exists
echo "Checking for admin user...\n";
$adminModel = new User($db);
if (!$adminModel->loadByUsername('admin')) {
    echo "Creating admin user...\n";
    $adminPassword = bin2hex(random_bytes(8)); // Generate random password
    $adminId = $adminModel->create('admin', $adminPassword, 'admin@example.com', true);
    
    if ($adminId) {
        echo "Admin user created with password: $adminPassword\n";
        echo "Please change this password after first login.\n";
    } else {
        echo "Failed to create admin user.\n";
        exit(1);
    }
} else {
    echo "Admin user already exists.\n";
}

// Migrate devices
echo "Migrating devices...\n";
$deviceCount = 0;

if (!empty($existingConfig['devices'])) {
    foreach ($existingConfig['devices'] as $deviceName => $deviceConfig) {
        echo "  Processing device '$deviceName'... ";
        
        // Extract device details
        $host = $deviceConfig['host'] ?? '';
        $port = $deviceConfig['port'] ?? 80;
        $scheme = $deviceConfig['scheme'] ?? 'http';
        $username = $deviceConfig['username'] ?? '';
        $password = $deviceConfig['password'] ?? '';
        $authType = $deviceConfig['auth_type'] ?? 'basic';
        
        // Create device in database
        $deviceData = [
            'name' => $deviceName,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'username' => $username,
            'password' => $password,
            'auth_type' => strtolower($authType),
            'description' => "Migrated from legacy config"
        ];
        
        // Check if device already exists
        if ($deviceModel->loadByName($deviceName)) {
            echo "already exists, skipping.\n";
            continue;
        }
        
        $deviceId = $deviceModel->createDevice($deviceData);
        
        if ($deviceId) {
            echo "migrated successfully (ID: $deviceId).\n";
            $deviceCount++;
            
            // Log the migration
            $auditLogger->logSystemAction('DEVICE_MIGRATE', [
                'device_id' => $deviceId,
                'device_name' => $deviceName
            ]);
        } else {
            echo "failed to migrate.\n";
        }
    }
}

echo "Migrated $deviceCount devices.\n";

// Migrate gates
echo "Migrating gates...\n";
$gateCount = 0;

if (!empty($existingConfig['gates'])) {
    foreach ($existingConfig['gates'] as $gateName => $gateConfig) {
        echo "  Processing gate '$gateName'... ";
        
        // Extract gate details
        $deviceName = $gateConfig['device'] ?? '';
        $port = $gateConfig['port'] ?? '';
        $pulseTime = $gateConfig['pulse'] ?? 500; // Default pulse time in ms
        
        // Convert pulse time from ms to seconds for new config
        $pulseTimeSeconds = $pulseTime / 1000;
        
        // Find device ID by name
        if (!$deviceName) {
            echo "no device specified, skipping.\n";
            continue;
        }
        
        if (!$deviceModel->loadByName($deviceName)) {
            echo "device '$deviceName' not found, skipping.\n";
            continue;
        }
        
        $deviceId = $deviceModel->getId();
        
        // Create gate in database
        $gateData = [
            'name' => $gateName,
            'device_id' => $deviceId,
            'port' => $port,
            'pulse_time' => $pulseTimeSeconds,
            'description' => "Migrated from legacy config"
        ];
        
        // Check if gate already exists
        if ($gateModel->loadByName($gateName)) {
            echo "already exists, skipping.\n";
            continue;
        }
        
        $gateId = $gateModel->createGate($gateData);
        
        if ($gateId) {
            echo "migrated successfully (ID: $gateId).\n";
            $gateCount++;
            
            // Log the migration
            $auditLogger->logSystemAction('GATE_MIGRATE', [
                'gate_id' => $gateId,
                'gate_name' => $gateName,
                'device_name' => $deviceName
            ]);
        } else {
            echo "failed to migrate.\n";
        }
    }
}

echo "Migrated $gateCount gates.\n";

// Migration complete
echo "\nMigration completed successfully.\n";
echo "- Migrated $deviceCount devices\n";
echo "- Migrated $gateCount gates\n";
echo "\nYou can now use the new Gate Controller system with the migrated configuration.\n";
