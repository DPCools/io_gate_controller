<?php
// router.php for PHP built-in server (php -S localhost:82 router.php)
// Routes all non-file requests to index.php so pretty URLs work.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fullPath = __DIR__ . $uri;

// If the request is for an existing file or directory, let the server handle it
if ($uri !== '/' && (file_exists($fullPath) || is_dir($fullPath))) {
    return false;
}

// Otherwise, include the front controller
require __DIR__ . '/index.php';
