<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$baseUrl}/login/");
    exit;
}

if ($_SESSION['user_role'] === 'advertiser') {
    $stmtStatus = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtStatus->execute([$_SESSION['user_id']]);
    if ($stmtStatus->fetchColumn() !== 'active') {
        header("Location: {$baseUrl}/painel/verificacao.php");
        exit;
    }
}

$userId = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user's ads
$stmt = $pdo->prepare("SELECT a.*, al.city FROM ads a LEFT JOIN (SELECT ad_id, MIN(city) as city FROM ad_locations GROUP BY ad_id) al ON a.id = al.ad_id WHERE a.user_id = ?");
$stmt->execute([$userId]);
$ads = $stmt->fetchAll();

if ($_SESSION['user_role'] === 'advertiser' && empty($ads)) {
    header("Location: {$baseUrl}/painel/criar.php");
    exit;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-texto">Meu Painel</h1>
        <?php if ($_SESSION['user_role'] === 'advertiser'): ?>
            <a href="<?= $baseUrl ?>/painel/criar.php" class="bg-brand hover:bg-brand-hover text-white px-6 py-2 rounded-full font-bold transition shadow-lg shadow-rose-900/50">+ Novo Anúncio</a>
        <?php endif; ?>
    </div>

    <div class="bg-sup1 rounded-2xl border border-borda p-6">
        <?php if ($_SESSION['user_role'] === 'client'): ?>
            <h2 class="text-xl font-bold text-texto mb-4">Olá, <?= htmlspecialchars($user_name) ?>!</h2>
            <div class="text-secundario py-8">
                Como cliente, você pode favoritar anúncios e avaliar perfis.
            </div>
        <?php else: ?>
            <h2 class="text-xl font-bold text-texto mb-4">Meus Anúncios</h2>
        
        <?php if(empty($ads)): ?>
            <div class="text-secundario text-center py-8">
                Você ainda não possui anúncios.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-texto">
                    <thead class="bg-sup2 text-secundario">
                        <tr>
                            <th class="p-4 rounded-tl-lg">Título</th>
                            <th class="p-4 text-center">Visualizações</th>
                            <th class="p-4 text-center">Cliques (Whats)</th>
                            <th class="p-4">Cidade</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right rounded-tr-lg">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ads as $ad): ?>
                        <tr class="border-b border-borda hover:bg-sup2 transition">
                            <td class="p-4 font-bold text-texto"><?= htmlspecialchars($ad['title']) ?></td>
                            <td class="p-4 text-center text-brand font-bold"><?= isset($ad['views']) ? $ad['views'] : 0 ?></td>
                            <td class="p-4 text-center text-green-500 font-bold"><?= isset($ad['clicks']) ? $ad['clicks'] : 0 ?></td>
                            <td class="p-4"><?= htmlspecialchars(isset($ad['city']) ? $ad['city'] : '') ?></td>
                            <td class="p-4">
                                <?php if($ad['status'] == 'active'): ?>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2 py-1 rounded border border-green-500/30">Ativo</span>
                                <?php else: ?>
                                    <span class="bg-yellow-500/20 text-yellow-400 text-xs px-2 py-1 rounded border border-yellow-500/30"><?= ucfirst($ad['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <a href="<?= $baseUrl ?>/anuncio/?id=<?= $ad['id'] ?>" class="text-blue-400 hover:underline mr-3">Ver</a>
                                <a href="#" class="text-secundario hover:text-texto">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
