<?php
/**
 * Gate Controller Configuration
 */

return [
    // Application settings
    'app' => [
        'name' => 'Gate Controller',
        'version' => '2.0.0',
        'mode' => 'debug', // 'debug' or 'production'
        'base_url' => '', // Will be set dynamically
    ],
    
    // Database settings
    'db' => [
        'file' => __DIR__ . '/../db/gate_controller.sqlite',
        'queue_file' => __DIR__ . '/../db/axis_commands.sqlite',
    ],
    
    // Security settings
    'security' => [
        'session_lifetime' => 86400, // 24 hours
        'password_min_length' => 8,
        'jwt_secret' => 'change_this_in_production', // Change this in production
        'jwt_expiration' => 3600, // 1 hour
    ],
    
    // Logging settings
    'log' => [
        'file' => __DIR__ . '/../logs/app.log',
        'level' => 'debug', // debug, info, warning, error
        'max_size' => 5242880, // 5MB
    ],
    
    // HTTP request settings
    'http' => [
        'connect_timeout' => 3, // seconds
        'timeout' => 10,        // seconds
        'user_agent' => 'GateController/2.0',
    ],
    
    // Retry behaviour for device commands
    'retry' => [
        'max_attempts' => 4,
        'initial_backoff_ms' => 250,
    ],
];
