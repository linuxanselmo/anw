<?php
session_start();

$appConfig = __DIR__ . '/../config/app.php';
if (file_exists($appConfig)) {
    require_once $appConfig;
}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear OPcache if enabled
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Clear PHP stat cache
    clearstatcache();
    
    // Attempt to clear apcu cache if enabled
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }

    $_SESSION['msg'] = 'Cache do servidor (OPcache/Stat cache) limpo com sucesso!';
}

// Redirect back to admin panel
header("Location: {$baseUrl}/admin/");
exit;
