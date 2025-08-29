<?php
/**
 * Reset admin password to 'admin' for testing
 */

// Load configuration
$config = require_once __DIR__ . '/src/config.php';

// Connect to database
try {
    $dbFile = $config['db']['file'];
    $db = new PDO("sqlite:{$dbFile}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Reset admin password
$newPassword = 'admin';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'admin'");
$success = $stmt->execute(['password' => $hashedPassword]);

if ($success) {
    echo "Admin password reset to 'admin' successfully!\n";
} else {
    echo "Failed to reset admin password.\n";
}

// Verify the password works
$stmt = $db->prepare("SELECT password FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch();

if ($user && password_verify('admin', $user['password'])) {
    echo "Password verification successful!\n";
} else {
    echo "Password verification failed!\n";
}
