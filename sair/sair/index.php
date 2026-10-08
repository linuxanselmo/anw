<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();

if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

$appConfig = __DIR__ . '/../config/app.php';
if (file_exists($appConfig)) {
    require_once $appConfig;
}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
header("Location: {$baseUrl}/");
exit;
