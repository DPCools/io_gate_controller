-- Gate Controller Database Schema

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,  -- Will store hashed passwords
    email TEXT,
    is_admin BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    active BOOLEAN NOT NULL DEFAULT 1
);

-- Insert default admin user (password: admin123)
INSERT OR IGNORE INTO users (username, password, is_admin) 
VALUES ('admin', '$2y$10$bVK2YN.ECg4FcJGh1Rac8OodZQST1JH6zyWTq4KaLI5GXKmOLsYc.', 1);

-- Devices table (formerly sites)
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
);

-- Gates table
CREATE TABLE IF NOT EXISTS gates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    device_id INTEGER NOT NULL,
    io_port INTEGER NOT NULL,
    pulse_seconds REAL NOT NULL DEFAULT 1.0,  -- Store as seconds
    description TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- API Keys for CRM integration
CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    api_key TEXT NOT NULL UNIQUE,
    active BOOLEAN NOT NULL DEFAULT 1,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Audit log for all actions
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action_type TEXT NOT NULL,
    action_details TEXT NOT NULL,  -- JSON details of the action
    user_id INTEGER,               -- NULL if API key used
    api_key_id INTEGER,            -- NULL if user authenticated
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
);

-- Keep the existing command queue table
CREATE TABLE IF NOT EXISTS command_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id TEXT NOT NULL,
    io_port INTEGER NOT NULL,
    action TEXT NOT NULL,
    pulse_ms INTEGER DEFAULT 0,
    last_attempt_at TIMESTAMP,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_audit_log_action_type ON audit_log(action_type);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_gates_device_id ON gates(device_id);
