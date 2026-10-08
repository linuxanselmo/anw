<?php
require_once __DIR__ . '/config/database.php';

$appConfig = __DIR__ . '/config/app.php';
if (file_exists($appConfig)) {
    require_once $appConfig;
}

$baseUrl = defined('BASE_URL') ? BASE_URL : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

header("Content-Type: application/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static URLs
$staticPages = [
    '/',
    '/anuncios/',
    '/categorias/',
    '/cadastro/',
    '/login/'
];

foreach ($staticPages as $page) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . $page) . '</loc>' . "\n";
    echo '    <changefreq>daily</changefreq>' . "\n";
    echo '    <priority>1.0</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Dynamic URLs for Ads
try {
    $stmt = $pdo->query("SELECT id, updated_at, created_at FROM ads WHERE status = 'active' ORDER BY id DESC");
    while ($ad = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($baseUrl . '/anuncio/?id=' . $ad['id']) . '</loc>' . "\n";
        
        $dateStr = !empty($ad['updated_at']) ? $ad['updated_at'] : $ad['created_at'];
        if (!empty($dateStr)) {
            echo '    <lastmod>' . date('c', strtotime($dateStr)) . '</lastmod>' . "\n";
        }
        
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.8</priority>' . "\n";
        echo '  </url>' . "\n";
    }
} catch (Exception $e) {
    // Ignore error if table doesn't exist or query fails
}

echo '</urlset>';
