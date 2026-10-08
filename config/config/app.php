<?php
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');

// Try to build dynamic BASE_URL, fallback if empty
// Since this file is in config/app.php, the base URL should be the directory above it.
$baseDir = dirname(dirname($_SERVER['SCRIPT_NAME'])); 
$baseDir = ($baseDir === '\\' || $baseDir === '/') ? '' : str_replace('\\', '/', $baseDir);

$baseUrl = $protocol . "://" . $host . $baseDir;

define('BASE_URL', $baseUrl);
define('APP_INSTALLED', true);
