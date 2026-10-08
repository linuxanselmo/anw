<?php
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$appRoot = str_replace('\\', '/', dirname(__DIR__));
$baseDir = str_replace($docRoot, '', $appRoot);
if (substr($baseDir, 0, 1) !== '/' && $baseDir !== '') {
    $baseDir = '/' . $baseDir;
}

define('BASE_URL', $protocol . '://' . $host . $baseDir);
define('APP_INSTALLED', false);
define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'nao-responda@example.com');