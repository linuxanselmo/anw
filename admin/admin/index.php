<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

// Statistics
$stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmtUsers->fetchColumn();

$stmtAds = $pdo->query("SELECT COUNT(*) FROM ads");
$totalAds = $stmtAds->fetchColumn();

$stmtPendingAds = $pdo->query("SELECT COUNT(*) FROM ads a JOIN users u ON a.user_id = u.id WHERE u.role = 'advertiser' AND a.status = 'pending_approval'");
$pendingAds = $stmtPendingAds->fetchColumn();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Painel de <span class="text-brand">Administração</span></h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-sup2 border border-borda rounded-2xl p-6">
            <h3 class="text-secundario text-sm uppercase tracking-wider font-bold mb-2">Total de Usuários</h3>
            <div class="text-4xl font-bold text-texto"><?= $totalUsers ?></div>
        </div>
        
        <div class="bg-sup2 border border-borda rounded-2xl p-6">
            <h3 class="text-secundario text-sm uppercase tracking-wider font-bold mb-2">Total de Anúncios</h3>
            <div class="text-4xl font-bold text-texto"><?= $totalAds ?></div>
        </div>
        
        <a href="<?= $baseUrl ?>/admin/verificacoes.php" class="bg-sup2 border border-brand/50 rounded-2xl p-6 shadow-[0_0_15px_rgba(233,30,99,0.1)] block transition hover:scale-105">
            <h3 class="text-secundario text-sm uppercase tracking-wider font-bold mb-2">Anúncios Pendentes</h3>
            <div class="text-4xl font-bold text-brand"><?= $pendingAds ?></div>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="<?= $baseUrl ?>/admin/users.php" class="group bg-sup1 border border-borda rounded-2xl p-8 hover:border-brand transition">
            <h2 class="text-2xl font-bold text-texto mb-2 group-hover:text-brand transition">Gerenciar Usuários &rarr;</h2>
            <p class="text-secundario">Visualize, edite funções e remova usuários da plataforma.</p>
        </a>
        
        <a href="<?= $baseUrl ?>/admin/ads.php" class="group bg-sup1 border border-borda rounded-2xl p-8 hover:border-brand transition">
            <h2 class="text-2xl font-bold text-texto mb-2 group-hover:text-brand transition">Gerenciar Anúncios &rarr;</h2>
            <p class="text-secundario">Aprove novos anúncios, suspenda ou remova anúncios existentes.</p>
        </a>
        
        <a href="<?= $baseUrl ?>/admin/verificacoes.php" class="group bg-sup1 border border-brand/50 rounded-2xl p-8 hover:border-brand transition">
            <h2 class="text-2xl font-bold text-brand mb-2 group-hover:text-brand transition">Anúncios para Aprovar &rarr;</h2>
            <p class="text-secundario">Revise e aprove os novos anúncios e planos de destaque.</p>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
