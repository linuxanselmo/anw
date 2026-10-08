<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

// Criar tabela se não existir (fallback)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT
    )");
} catch (\Exception $e) {
    // Ignorar se já existe ou erro de privilégio ao criar
}

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gaId = $_POST['ga4_id'] ?? '';
    $gAdsId = $_POST['google_ads_id'] ?? '';
    $metaPixelId = $_POST['meta_pixel_id'] ?? '';
    $gscId = $_POST['google_search_console_id'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['ga4_id', $gaId]);
        $stmt->execute(['google_ads_id', $gAdsId]);
        $stmt->execute(['meta_pixel_id', $metaPixelId]);
        $stmt->execute(['google_search_console_id', $gscId]);

        $pdo->commit();
        $success = "Configurações salvas com sucesso!";
    } catch (\Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar as configurações: " . $e->getMessage();
    }
}

// Fetch current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\Exception $e) {
    // Tabela pode ainda não existir se falhou no create
}

$gaId = $settings['ga4_id'] ?? '';
$gAdsId = $settings['google_ads_id'] ?? '';
$metaPixelId = $settings['meta_pixel_id'] ?? '';
$gscId = $settings['google_search_console_id'] ?? '';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Configurações do Site / Tags</h1>
        <a href="<?= $baseUrl ?>/admin/" class="text-brand hover:text-brand/80 underline font-medium">Voltar ao Painel</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-lg mb-8">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-4 rounded-lg mb-8">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="bg-sup2 border border-borda rounded-2xl p-8 space-y-6">
        
        <div>
            <label class="block text-secundario mb-2 font-semibold">Google Analytics (GA4) - ID de Rastreamento</label>
            <p class="text-xs text-gray-400 mb-2">Exemplo: G-XXXXXXXXXX</p>
            <input type="text" name="ga4_id" value="<?= htmlspecialchars($gaId) ?>" class="w-full bg-sup1 border border-borda rounded-lg p-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand" placeholder="G-XXXXXXXXXX">
        </div>

        <hr class="border-borda">

        <div>
            <label class="block text-secundario mb-2 font-semibold">Google Search Console - Tag de Verificação (HTML)</label>
            <p class="text-xs text-gray-400 mb-2">Cole apenas o código (content) da tag. Exemplo: se a tag for &lt;meta name="google-site-verification" content="ABC123XYZ" /&gt;, digite apenas ABC123XYZ</p>
            <input type="text" name="google_search_console_id" value="<?= htmlspecialchars($gscId) ?>" class="w-full bg-sup1 border border-borda rounded-lg p-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand" placeholder="Código de verificação">
        </div>

        <hr class="border-borda">

        <div>
            <label class="block text-secundario mb-2 font-semibold">Google Ads - ID de Conversão</label>
            <p class="text-xs text-gray-400 mb-2">Exemplo: AW-XXXXXXXXXX</p>
            <input type="text" name="google_ads_id" value="<?= htmlspecialchars($gAdsId) ?>" class="w-full bg-sup1 border border-borda rounded-lg p-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand" placeholder="AW-XXXXXXXXXX">
        </div>

        <hr class="border-borda">

        <div>
            <label class="block text-secundario mb-2 font-semibold">Meta Pixel (Facebook) - ID do Pixel</label>
            <p class="text-xs text-gray-400 mb-2">Exemplo: 123456789012345</p>
            <input type="text" name="meta_pixel_id" value="<?= htmlspecialchars($metaPixelId) ?>" class="w-full bg-sup1 border border-borda rounded-lg p-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand" placeholder="123456789012345">
        </div>

        <div class="pt-4 flex justify-end">
            <button type="submit" class="bg-brand hover:bg-brand-hover text-white font-bold py-3 px-8 rounded-lg transition shadow-lg shadow-brand/20">
                Salvar Configurações
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
