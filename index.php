    <?php
/**
 * Gate Controller System
 * 
 * Main application entry point
 */

// Load configuration first (needed for session cookie params)
$config = require_once __DIR__ . '/src/config.php';

// Derive base URL/path from config for consistent redirects and cookie path
$baseUrl = $config['app']['base_url'] ?? '';
if (preg_match('#^https?://#i', $baseUrl)) {
    $baseUrl = parse_url($baseUrl, PHP_URL_PATH) ?? '';
}
$baseUrl = rtrim($baseUrl, '/');

// Configure session cookie to be scoped to app path and respect lifetime
$sessionLifetime = $config['security']['session_lifetime'] ?? 3600;
$cookiePath = ($baseUrl === '' ? '/' : $baseUrl . '/');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // Fallback for older PHP versions
    session_set_cookie_params($sessionLifetime, $cookiePath);
}

// Start session
session_start();

// Load dependencies
// (keep requires after config/session setup to avoid any accidental output before headers)
require_once __DIR__ . '/src/utils/DatabaseInitializer.php';
require_once __DIR__ . '/src/controllers/AuthController.php';
require_once __DIR__ . '/src/controllers/DeviceController.php';
require_once __DIR__ . '/src/controllers/GateController.php';
require_once __DIR__ . '/src/controllers/LogController.php';
require_once __DIR__ . '/src/controllers/ApiController.php';

use GateController\Utils\DatabaseInitializer;
use GateController\Controllers\AuthController;
use GateController\Controllers\DeviceController;
use GateController\Controllers\GateController;
use GateController\Controllers\LogController;
use GateController\Controllers\ApiController;

// Set error reporting
$debugMode = isset($config['app']['mode']) && $config['app']['mode'] === 'debug';
if (!$debugMode) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Connect to database
try {
    $dbFile = $config['db']['file'];
    // Ensure database directory exists for first-run installs
    $dbDir = dirname($dbFile);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    $db = new PDO("sqlite:{$dbFile}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Improve SQLite concurrency and reliability under load
    // WAL allows concurrent readers and a single writer; NORMAL sync is a good balance
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    // Keep temporary data in memory and avoid long writer blocks
    $db->exec('PRAGMA temp_store = MEMORY');
    $db->exec('PRAGMA foreign_keys = ON');
    // Back off when the database is busy (milliseconds)
    $db->exec('PRAGMA busy_timeout = 5000');
} catch (PDOException $e) {
    $debugMode = isset($config['app']['mode']) && $config['app']['mode'] === 'debug';
    die("Database connection failed: " . ($debugMode ? $e->getMessage() : "Check configuration"));
}

// Initialize database if needed
$initializer = new DatabaseInitializer($config, $db);
if (!$initializer->isDatabaseInitialized()) {
    $initializer->initializeDatabase();
    // Show green notice on login about new install
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'New install detected. Please log in with admin / admin for the first time, then change the admin password.'
    ];
}

// Initialize controllers
$authController = new AuthController($db, $config);
$deviceController = new DeviceController($db, $config, $authController);
$gateController = new GateController($db, $config, $authController);
$logController = new LogController($db, $config, $authController);
$apiController = new ApiController($db, $config, $gateController);

// Simple router
// Determine route from query parameter if provided; otherwise derive from REQUEST_URI (pretty URLs)
$routeParam = $_GET['route'] ?? null;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
// Extract path without query string
$path = parse_url($requestUri, PHP_URL_PATH);
// Normalize base URL path (strip scheme/host if present in config)
$basePath = $config['app']['base_url'] ?? '';
// If basePath is a full URL, extract its path component
if (preg_match('#^https?://#i', $basePath)) {
    $basePath = parse_url($basePath, PHP_URL_PATH) ?? '';
}
$basePath = rtrim($basePath, '/');
// Strip basePath prefix from request path if present
if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
// Ensure $path is a string before trim to avoid deprecation when null
$path = trim($path ?? '', '/');

if ($routeParam !== null && $routeParam !== '') {
    $route = $routeParam;
} else {
    // If directly hitting index.php or root without a route, default to home
    if ($path === '' || strtolower($path) === 'index.php') {
        // Heuristic: if logs filter params are present, treat as logs route
        $logKeys = ['action_type','from_date','to_date','search','ip_address','user_id','per_page'];
        $hasLogFilters = false;
        foreach ($logKeys as $k) {
            if (isset($_GET[$k])) { $hasLogFilters = true; break; }
        }
        $route = $hasLogFilters ? 'logs' : 'home';
    } else {
        $route = $path;
    }
}

// Check if user is authenticated
$isAuthenticated = $authController->isAuthenticated();
$currentUser = $isAuthenticated ? $authController->getCurrentUser() : null;
$isAdmin = $currentUser && $currentUser['is_admin'] === true;

// Routes that don't require authentication
$publicRoutes = ['login', 'logout', 'api'];

// Redirect to login if not authenticated and trying to access protected route
if (!$isAuthenticated && !in_array($route, $publicRoutes)) {
    // Use base URL from config or determine dynamically
    $baseUrl = $config['app']['base_url'] ?? '';
    header('Location: ' . $baseUrl . '/login');
    exit;
}

// Handle routes
switch ($route) {
    case 'home':
    case 'dashboard':
        // Dashboard
        $statsResult = $logController->getDashboardStats();
        $stats = $statsResult['stats'] ?? [];
        $recentResult = $logController->getRecentActivity(5);
        $recentActivity = $recentResult['activities'] ?? [];
        
        $pageTitle = 'Dashboard';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/dashboard.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    case 'users/delete':
        // Delete user (admin only)
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to perform this action'
            ];
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        $userId = $_GET['id'] ?? null;
        if (!$userId) {
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        // Prevent deleting yourself
        if ((int)$userId === (int)($currentUser['id'] ?? 0)) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You cannot delete your own account'
            ];
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        // Fetch target user
        $stmt = $db->prepare("SELECT id, username, is_admin, active FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'User not found'
            ];
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        // If deleting an admin, ensure at least one other admin remains active
        if (!empty($target['is_admin'])) {
            $countStmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE is_admin = 1 AND active = 1");
            $row = $countStmt->fetch(PDO::FETCH_ASSOC);
            $adminCount = (int)($row['cnt'] ?? 0);
            if ($adminCount <= 1) {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => 'Cannot delete the last active admin'
                ];
                header('Location: ' . $baseUrl . '/users');
                exit;
            }
        }

        // Perform deletion
        $del = $db->prepare("DELETE FROM users WHERE id = :id");
        $ok = $del->execute(['id' => $userId]);

        if ($ok) {
            // Log audit
            require_once __DIR__ . '/src/models/User.php';
            $actor = new \GateController\Models\User($db);
            $actor->loadById($currentUser['id']);
            $actor->logAudit('USER_DELETE', [
                'deleted_user_id' => (int)$userId,
                'deleted_username' => $target['username'],
                'was_admin' => (bool)$target['is_admin']
            ]);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'User deleted successfully'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Failed to delete user'
            ];
        }

        header('Location: ' . $baseUrl . '/users');
        exit;
        break;

    case 'profile':
        // Profile page for current user with change password
        if (!$isAuthenticated) {
            header('Location: ' . $baseUrl . '/login');
            exit;
        }

        $pageTitle = 'Your Profile';
        $currentPage = 'profile';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPwd = $_POST['current_password'] ?? '';
            $newPwd = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($newPwd !== $confirm) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Passwords do not match'];
            } elseif (strlen($newPwd) < ($config['security']['password_min_length'] ?? 8)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password too short'];
            } else {
                // Verify current password by re-auth
                require_once __DIR__ . '/src/models/User.php';
                $u = new \GateController\Models\User($db);
                if ($u->authenticate($currentUser['username'], $currentPwd)) {
                    // Ensure matches session user
                    if ((int)$u->getId() !== (int)$currentUser['id']) {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Auth mismatch'];
                    } else {
                        $ok = $u->changePassword($newPwd);
                        if ($ok) {
                            $u->logAudit('PASSWORD_CHANGE', ['user_id' => $currentUser['id']]);
                            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password updated'];
                            // Clear forced password change state if present
                            if (isset($_SESSION['must_change_password'])) { unset($_SESSION['must_change_password']); }
                        } else {
                            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update password'];
                        }
                    }
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Current password is incorrect'];
                }
            }
        }

        ob_start();
        require __DIR__ . '/src/views/auth/profile.php';
        $content = ob_get_clean();
        // Surface any session flash to the layout for display
        $flash = $_SESSION['flash'] ?? null;
        if (isset($_SESSION['flash'])) { unset($_SESSION['flash']); }
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    case 'users/password':
        // Admin reset password for a user
        if (!$isAdmin) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Insufficient privileges'];
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        $targetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($targetId <= 0) {
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        require_once __DIR__ . '/src/models/User.php';
        $targetUser = new \GateController\Models\User($db);
        if (!$targetUser->loadById($targetId)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'User not found'];
            header('Location: ' . $baseUrl . '/users');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPwd = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($newPwd !== $confirm) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Passwords do not match'];
            } elseif (strlen($newPwd) < ($config['security']['password_min_length'] ?? 8)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password too short'];
            } else {
                $ok = $targetUser->changePassword($newPwd);
                if ($ok) {
                    // Log audit under current admin
                    $adminUser = new \GateController\Models\User($db);
                    $adminUser->loadById($currentUser['id']);
                    $adminUser->logAudit('PASSWORD_RESET', [
                        'target_user_id' => $targetId,
                        'target_username' => $targetUser->getUsername()
                    ]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password reset'];
                    header('Location: ' . $baseUrl . '/users');
                    exit;
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to reset password'];
                }
            }
        }

        $pageTitle = 'Reset Password';
        $currentPage = 'users';
        ob_start();
        // Provide $targetId and $targetUser to the view
        $targetUserData = [
            'id' => $targetId,
            'username' => $targetUser->getUsername()
        ];
        require __DIR__ . '/src/views/users/password.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    case 'users/create':
        // Create user (admin only)
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';
            $email    = trim($_POST['email'] ?? '');
            $isAdminNew = isset($_POST['is_admin']) && $_POST['is_admin'] === 'on';

            if ($password !== $confirm) {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => 'Passwords do not match'
                ];
                $user = $_POST; // repopulate
            } else {
                $result = $authController->register($username, $password, $email !== '' ? $email : null, $isAdminNew);
                if ($result['success']) {
                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'User created successfully'
                    ];
                    header('Location: ' . $baseUrl . '/users');
                    exit;
                } else {
                    $_SESSION['flash'] = [
                        'type' => 'error',
                        'message' => $result['message'] ?? 'Failed to create user'
                    ];
                    $user = $_POST; // repopulate
                }
            }
        }

        $isEdit = false;
        $pageTitle = 'Create User';
        $currentPage = 'users';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/users/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'login':
        // Login page
        if ($isAuthenticated) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
            
            $result = $authController->login($username, $password, $remember);
            
            if ($result['success']) {
                // If first login requires password change, force redirect to profile
                $baseUrl = $config['app']['base_url'] ?? '';
                $mustChange = !empty($_SESSION['must_change_password']) 
                    || (!empty($result['user']['must_change_password']));
                if ($mustChange) {
                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'For security, please change the admin password now.'
                    ];
                    header('Location: ' . $baseUrl . '/profile');
                    exit;
                }
                $redirect = $_SESSION['redirect_after_login'] ?? ($baseUrl . '/');
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
            }
        }
        
        $pageTitle = 'Login';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/auth/login.php';
        $content = ob_get_clean();
        // Surface any session flash to the layout for display
        $flash = $_SESSION['flash'] ?? null;
        if (isset($_SESSION['flash'])) { unset($_SESSION['flash']); }
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'logout':
        // Logout
        $authController->logout();
        header('Location: ' . $baseUrl . '/login');
        exit;
        break;
        
    case 'status':
        // Device status page (live connectivity + trigger stats)
        $result = $deviceController->listDevices();
        if (!$result['success']) {
            $_SESSION['flash'] = [ 'type' => 'error', 'message' => $result['message'] ?? 'Failed to load devices' ];
            $devices = [];
        } else {
            $devices = $result['devices'];
        }
        $pageTitle = 'Device Status';
        $currentPage = 'status';
        ob_start();
        require __DIR__ . '/src/views/status/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'devices':
        // List devices
        $result = $deviceController->listDevices();
        if (!$result['success']) {
            $_SESSION[ 'flash' ] = [ 'type' => 'error', 'message' => $result['message'] ?? 'Failed to load devices' ];
            $devices = [];
        } else {
            $devices = $result['devices'];
        }
        $pageTitle = 'Devices';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/devices/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'devices/create':
    case 'device/create':
        // Create device
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $deviceController->createDevice($_POST);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Device created successfully'
                ];
                // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $device = $_POST;
            }
        }
        
        $isEdit = false;
        $pageTitle = 'Create Device';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/devices/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'devices/view':
    case 'device/view':
        // View device (read-only)
        $deviceId = $_GET['id'] ?? null;
        if (!$deviceId) {
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
            exit;
        }
        $result = $deviceController->getDevice($deviceId);
        if (!$result['success']) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => $result['message'] ?? 'Device not found'
            ];
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
            exit;
        }
        $device = $result['device'];
        $createdBy = $result['created_by'] ?? null;
        $updatedBy = $result['updated_by'] ?? null;
        $pageTitle = 'View Device';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/devices/view.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    case 'devices/edit':
    case 'device/edit':
        // Edit device
        $deviceId = $_GET['id'] ?? null;
        
        if (!$deviceId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
            exit;
        }
        
        $result = $deviceController->getDevice($deviceId);
        
        if (!$result['success']) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => $result['message']
            ];
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
            exit;
        }
        
        $device = $result['device'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $deviceController->updateDevice($deviceId, $_POST);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Device updated successfully'
                ];
                // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $device = array_merge($device, $_POST);
            }
        }
        
        $isEdit = true;
        $pageTitle = 'Edit Device';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/devices/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'devices/delete':
    case 'device/delete':
        // Delete device
        $deviceId = $_GET['id'] ?? null;
        
        if (!$deviceId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/devices');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $deviceController->deleteDevice($deviceId);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Device deleted successfully'
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
            }
        }
        
        header('Location: ' . $baseUrl . '/devices');
        exit;
        break;
        
    case 'gates':
        // List gates
        $deviceId = $_GET['device_id'] ?? null;
        if ($deviceId) {
            $gatesResult = $gateController->getGatesByDevice($deviceId);
        } else {
            $gatesResult = $gateController->listGates();
        }
        if (!$gatesResult['success']) {
            $_SESSION['flash'] = [ 'type' => 'error', 'message' => $gatesResult['message'] ?? 'Failed to load gates' ];
            $gates = [];
        } else {
            $gates = $gatesResult['gates'];
        }
        
        // Get devices for filter dropdown
        $devicesRes = $deviceController->listDevices();
        $devices = $devicesRes['success'] ? ($devicesRes['devices'] ?? []) : [];
        
        $pageTitle = 'Gates';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/gates/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'gates/create':
    case 'gate/create':
        // Create gate
        // Get devices for dropdown
        $devicesRes = $deviceController->listDevices();
        $devices = $devicesRes['success'] ? ($devicesRes['devices'] ?? []) : [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $gateController->createGate($_POST);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Gate created successfully'
                ];
                // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/gates');
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $gate = $_POST;
            }
        }
        
        $isEdit = false;
        $pageTitle = 'Create Gate';
        ob_start();
        require __DIR__ . '/src/views/gates/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'gates/edit':
    case 'gate/edit':
        // Edit gate
        $gateId = $_GET['id'] ?? null;
        
        if (!$gateId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/gates');
            exit;
        }
        
        $result = $gateController->getGate($gateId);
        
        if (!$result['success']) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => $result['message']
            ];
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/gates');
            exit;
        }
        
        $gate = $result['gate'];
        
        // Get devices for dropdown
        $devicesRes = $deviceController->listDevices();
        $devices = $devicesRes['success'] ? ($devicesRes['devices'] ?? []) : [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $gateController->updateGate($gateId, $_POST);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Gate updated successfully'
                ];
                // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/gates');
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $gate = array_merge($gate, $_POST);
            }
        }
        
        $isEdit = true;
        $pageTitle = 'Edit Gate';
        ob_start();
        require __DIR__ . '/src/views/gates/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'gates/delete':
    case 'gate/delete':
        // Delete gate
        $gateId = $_GET['id'] ?? null;
        
        if (!$gateId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/gates');
            exit;
        }
        // Perform deletion (link triggers GET from UI)
        $result = $gateController->deleteGate($gateId);
        if ($result['success']) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Gate deleted successfully'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => $result['message']
            ];
        }
        
        header('Location: ' . $baseUrl . '/gates');
        exit;
        break;

    case 'gates/trigger':
    case 'gate/trigger':
        // Trigger gate (AJAX JSON)
        header('Content-Type: application/json');
        $gateId = $_GET['id'] ?? null;
        if (!$gateId) {
            echo json_encode([ 'success' => false, 'message' => 'Missing gate id' ]);
            exit;
        }
        $res = $gateController->triggerGate($gateId);
        echo json_encode($res);
        exit;
        break;

    case 'gates/test':
    case 'gate/test':
        // Test gate (AJAX JSON)
        header('Content-Type: application/json');
        $gateId = $_GET['id'] ?? null;
        if (!$gateId) {
            echo json_encode([ 'success' => false, 'message' => 'Missing gate id' ]);
            exit;
        }
        $res = $gateController->testGate($gateId);
        echo json_encode($res);
        exit;
        break;
        
    case 'logs':
        // Audit logs
        if (!$isAuthenticated) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/login');
            exit;
        }
        
        // Get filter parameters
        $filters = [
            'action_type' => $_GET['action_type'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'search' => $_GET['search'] ?? '',
            'ip_address' => $_GET['ip_address'] ?? '',
            'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
            'per_page' => isset($_GET['per_page']) ? (int)$_GET['per_page'] : 100
        ];
        
        // Get logs
        $result = $logController->getLogs($filters);
        $logs = $result['logs'];
        $pagination = $result['pagination'];
        
        // Get users for filter dropdown (admins only)
        $users = [];
        if ($isAdmin) {
            require_once __DIR__ . '/src/models/User.php';
            $userModel = new \GateController\Models\User($db);
            $users = $userModel->getAllUsers();
        }
        
        // Get action types for filter dropdown
        $actionTypes = $logController->getActionTypes();
        
        // Track active filters
        $activeFilters = array_filter($filters, function($value) {
            return $value !== '' && $value !== 1; // Exclude empty and default page
        });
        
        $pageTitle = 'Audit Logs';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/logs/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'api-keys':
        // API Keys - admin only
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        
        $result = $apiController->listApiKeys();
        $apiKeys = $result['api_keys'];
        
        $pageTitle = 'API Keys';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/api/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'api-keys/create':
        // Create API key - admin only
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        
        $showNewApiKey = false;
        $newApiKey = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $apiController->createApiKey($_POST, $currentUser['id']);
            
            if ($result['success']) {
                $showNewApiKey = true;
                $newApiKey = $result['api_key'];
                $apiKey = $_POST;
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'API key created successfully'
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $apiKey = $_POST;
            }
        }
        
        $isEdit = false;
        $pageTitle = 'Create API Key';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/api/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'api-keys/edit':
        // Edit API key - admin only
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        
        $apiKeyId = $_GET['id'] ?? null;
        
        if (!$apiKeyId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/api-keys');
            exit;
        }
        
        // Get API key data
        $result = $apiController->listApiKeys();
        $apiKeys = $result['api_keys'];
        
        $apiKey = null;
        foreach ($apiKeys as $key) {
            if ($key['id'] == $apiKeyId) {
                $apiKey = $key;
                break;
            }
        }
        
        if (!$apiKey) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'API key not found'
            ];
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/api-keys');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $apiController->updateApiKey($apiKeyId, $_POST, $currentUser['id']);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'API key updated successfully'
                ];
                // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/api-keys');
                exit;
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
                $apiKey = array_merge($apiKey, $_POST);
            }
        }
        
        $isEdit = true;
        $showNewApiKey = false;
        $pageTitle = 'Edit API Key';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/api/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;
        
    case 'api-keys/delete':
        // Delete API key - admin only
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        
        $apiKeyId = $_GET['id'] ?? null;
        
        if (!$apiKeyId) {
            // Use base URL from config or determine dynamically
            $baseUrl = $config['app']['base_url'] ?? '';
            header('Location: ' . $baseUrl . '/api-keys');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $apiController->deleteApiKey($apiKeyId, $currentUser['id']);
            
            if ($result['success']) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'API key deleted successfully'
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'error',
                    'message' => $result['message']
                ];
            }
        }
        
        header('Location: ' . $baseUrl . '/api-keys');
        exit;
        break;

    case 'help':
        // Help / Documentation (authenticated users)
        if (!$isAuthenticated) {
            header('Location: ' . $baseUrl . '/login');
            exit;
        }
        $pageTitle = 'Help & Documentation';
        $currentPage = 'help';
        ob_start();
        require __DIR__ . '/src/views/help/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    case 'api':
        // In-app API proxy to avoid web server rewrite issues
        header('Content-Type: application/json');
        // API endpoints don't need the session; release the lock early to prevent blocking other requests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $endpoint = $_GET['endpoint'] ?? '';
        $response = null;

        // Extract API key from headers or query
        $apiKey = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $apiKey = $m[1];
        } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
            $apiKey = $_SERVER['HTTP_X_API_KEY'];
        } elseif (isset($_GET['api_key'])) {
            $apiKey = $_GET['api_key'];
        }

        switch ($endpoint) {
            case 'health':
                $response = [
                    'success' => true,
                    'status' => 'operational',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response);
                exit;

            case 'gates':
                if ($method !== 'GET') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
                    exit;
                }
                $response = $apiController->getGates($apiKey);
                echo json_encode($response);
                exit;

            case 'trigger':
                if ($method !== 'POST') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
                    exit;
                }
                $gateName = $_GET['gate'] ?? null;
                if (!$gateName) {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $gateName = $input['gate'] ?? null;
                }
                $response = $apiController->triggerGate($apiKey, $gateName);
                echo json_encode($response);
                exit;

            case 'gate_info':
                // Read-only diagnostics: show non-sensitive gate and device config to verify routing
                if ($method !== 'GET') {
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
                    exit;
                }
                $gateName = $_GET['gate'] ?? '';
                if ($gateName === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Missing gate parameter']);
                    exit;
                }
                try {
                    $stmt = $db->prepare("SELECT g.id as gate_id, g.name as gate_name, g.io_port, g.pulse_seconds, g.device_id,
                                                   d.name as device_name, d.host, d.scheme, d.port, d.base_path, d.auth, d.insecure, d.tlsv1_2
                                            FROM gates g
                                            LEFT JOIN devices d ON d.id = g.device_id
                                            WHERE g.name = :name");
                    $stmt->execute(['name' => $gateName]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Gate not found']);
                        exit;
                    }
                    // Ensure only non-sensitive fields are returned
                    $out = [
                        'success' => true,
                        'gate' => [
                            'id' => $row['gate_id'],
                            'name' => $row['gate_name'],
                            'io_port' => (int)($row['io_port'] ?? 0),
                            'pulse_seconds' => (float)($row['pulse_seconds'] ?? 0),
                            'device_id' => (int)($row['device_id'] ?? 0),
                        ],
                        'device' => [
                            'name' => $row['device_name'] ?? null,
                            'scheme' => $row['scheme'] ?? null,
                            'host' => $row['host'] ?? null,
                            'port' => isset($row['port']) ? (int)$row['port'] : null,
                            'base_path' => $row['base_path'] ?? null,
                            'auth' => $row['auth'] ?? null,
                            'insecure' => !empty($row['insecure']),
                            'tlsv1_2' => !empty($row['tlsv1_2'])
                        ]
                    ];
                    echo json_encode($out);
                    exit;
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Lookup failed']);
                    exit;
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
                exit;
        }
        break;
        
    case 'users':
        // Users list (admin only)
        if (!$isAdmin) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'You do not have permission to access this page'
            ];
            header('Location: ' . $baseUrl . '/');
            exit;
        }
        require_once __DIR__ . '/src/models/User.php';
        $userModel = new \GateController\Models\User($db);
        $users = $userModel->getAllUsers();
        $pageTitle = 'Users';
        $currentPage = 'users';
        // Render view into $content, then include layout
        ob_start();
        require __DIR__ . '/src/views/users/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/src/views/layouts/main.php';
        break;

    default:
        // 404 Not Found
        http_response_code(404);
        echo "404 Page Not Found";
        break;
}
