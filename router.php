<?php
// PHP Built-in Server Router for RoseSMM Panel
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Serve existing static files directly (excluding PHP files, root, and index.php)
$filePath = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '/index.php' && file_exists($filePath) && !is_dir($filePath) && substr($filePath, -4) !== '.php') {
    return false;
}

// Forward to index.php front controller
require __DIR__ . '/index.php';

