<?php
$appConfig = __DIR__ . '/config/app.php';
if (file_exists($appConfig)) {
    require_once $appConfig;
}

$baseUrl = defined('BASE_URL') ? BASE_URL : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

header("Content-Type: text/plain; charset=utf-8");
?>
User-agent: *
Disallow: /admin/
Disallow: /painel/
Disallow: /config/
Disallow: /database/
Disallow: /includes/
Disallow: /backup/
Disallow: /login/

Sitemap: <?= $baseUrl ?>/sitemap.xml
