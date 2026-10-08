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
        <form action="<?= $baseUrl ?>/admin/clear_cache.php" method="POST">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition shadow-lg shadow-red-500/20 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Limpar Cache
            </button>
        </form>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-lg mb-8 flex justify-between items-center">
            <?= htmlspecialchars($_SESSION['msg']) ?>
            <button onclick="this.parentElement.style.display='none'" class="text-green-300 hover:text-white">&times;</button>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

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
            <h3 class="text-brand text-sm uppercase tracking-wider font-bold mb-2">Aprovações Pendentes</h3>
            <div class="text-4xl font-bold text-white"><?= $pendingAds ?></div>
        </a>
    </div>

    <!-- Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <a href="<?= $baseUrl ?>/admin/analytics.php" class="bg-sup1 border border-borda hover:border-brand/50 rounded-2xl p-6 flex flex-col items-center justify-center text-center transition group">
            <div class="w-16 h-16 bg-sup2 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <svg class="w-8 h-8 text-secundario group-hover:text-brand transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-texto mb-2">Analytics</h3>
            <p class="text-secundario text-sm">Dashboard com estatísticas de visitas e anúncios.</p>
        </a>

        <a href="<?= $baseUrl ?>/admin/ads.php" class="bg-sup1 border border-borda hover:border-brand/50 rounded-2xl p-6 flex flex-col items-center justify-center text-center transition group">
            <div class="w-16 h-16 bg-sup2 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <svg class="w-8 h-8 text-secundario group-hover:text-brand transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-texto mb-2">Gerenciar Anúncios</h3>
            <p class="text-secundario text-sm">Visualizar, editar ou excluir anúncios ativos na plataforma.</p>
        </a>

        <a href="<?= $baseUrl ?>/admin/users.php" class="bg-sup1 border border-borda hover:border-brand/50 rounded-2xl p-6 flex flex-col items-center justify-center text-center transition group">
            <div class="w-16 h-16 bg-sup2 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <svg class="w-8 h-8 text-secundario group-hover:text-brand transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-texto mb-2">Gerenciar Usuários</h3>
            <p class="text-secundario text-sm">Controle de anunciantes e clientes, edição de perfis.</p>
        </a>

        <a href="<?= $baseUrl ?>/admin/settings.php" class="bg-sup1 border border-borda hover:border-brand/50 rounded-2xl p-6 flex flex-col items-center justify-center text-center transition group">
            <div class="w-16 h-16 bg-sup2 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <svg class="w-8 h-8 text-secundario group-hover:text-brand transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-texto mb-2">Configurações do Site</h3>
            <p class="text-secundario text-sm">Gerencie tags de rastreamento (Google, Meta Pixel).</p>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
