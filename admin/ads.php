<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        if ($_POST['action'] === 'update' && isset($_POST['status'])) {
            $status = $_POST['status'];
            $highlight = isset($_POST['highlight_level']) ? $_POST['highlight_level'] : 'organic';
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $is_verified = isset($_POST['is_verified']) ? 1 : 0;

            $validStatuses = ['pending', 'active', 'inactive'];
            $validHighlights = ['ultra_top', 'super_top', 'super_highlight', 'paid_highlight', 'organic'];

            if (in_array($status, $validStatuses) && in_array($highlight, $validHighlights)) {
                $stmt = $pdo->prepare("UPDATE ads SET status = ?, highlight_level = ?, expires_at = ?, is_verified = ? WHERE id = ?");
                $stmt->execute([$status, $highlight, $expires_at, $is_verified, $id]);
            }
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    header("Location: {$baseUrl}/admin/ads.php");
    exit;
}

$stmt = $pdo->query("SELECT a.id, a.title, a.status, a.highlight_level, a.expires_at, a.created_at, a.is_verified, a.phone as ad_phone, u.name as user_name, u.cpf as user_cpf 
                     FROM ads a 
                     JOIN users u ON a.user_id = u.id 
                     ORDER BY a.created_at DESC");
$ads = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <div class="flex items-center mb-8">
        <a href="<?= $baseUrl ?>/admin/" class="text-secundario hover:text-brand transition mr-4">&larr; Voltar</a>
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Gerenciar <span class="text-brand">Anúncios</span></h1>
    </div>

    <div class="bg-sup1 border border-borda rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-sup2 text-secundario text-sm uppercase tracking-wider">
                        <th class="px-6 py-4 font-bold border-b border-borda">ID</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Título</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Autor</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Contato</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Data</th>
                        <th class="px-6 py-4 font-bold border-b border-borda text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-borda text-gray-300">
                    <?php foreach($ads as $ad): ?>
                    <tr class="hover:bg-sup2/50 transition">
                        <td class="px-6 py-4 text-texto">#<?= $ad['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-texto">
                            <a href="<?= $baseUrl ?>/anuncio/?id=<?= $ad['id'] ?>" target="_blank" class="hover:text-brand transition">
                                <?= htmlspecialchars($ad['title']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-texto">
                            <div class="font-bold"><?= htmlspecialchars($ad['user_name']) ?></div>
                            <div class="text-xs text-secundario">CPF: <?= $ad['user_cpf'] ? htmlspecialchars($ad['user_cpf']) : 'Não inf.' ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-secundario">
                            <?= $ad['ad_phone'] ? htmlspecialchars($ad['ad_phone']) : 'Não inf.' ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-secundario"><?= date('d/m/Y', strtotime($ad['created_at'])) ?></td>
                        <td class="px-6 py-4 text-right flex justify-end space-x-3 items-center">
                            <!-- Edit Button -->
                            <a href="<?= $baseUrl ?>/painel/editar.php?id=<?= $ad['id'] ?>" class="bg-blue-500/20 hover:bg-blue-500 text-blue-300 hover:text-white px-3 py-2 rounded font-bold text-xs transition self-stretch flex items-center justify-center">Editar</a>
                            
                            <!-- Update Form -->
                            <form action="<?= $baseUrl ?>/admin/ads.php" method="POST" class="inline-flex items-center space-x-2">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                
                                <div class="flex flex-col space-y-2 text-left">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-[10px] text-secundario uppercase font-bold w-16">Status</label>
                                        <select name="status" class="bg-sup2 border border-borda text-xs text-texto rounded p-1 outline-none w-32">
                                            <option value="active" <?= $ad['status'] == 'active' ? 'selected' : '' ?>>Ativo</option>
                                            <option value="pending" <?= $ad['status'] == 'pending' ? 'selected' : '' ?>>Pendente</option>
                                            <option value="inactive" <?= $ad['status'] == 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-[10px] text-secundario uppercase font-bold w-16">Seção</label>
                                        <select name="highlight_level" class="bg-sup2 border border-borda text-xs text-texto rounded p-1 outline-none w-32">
                                            <option value="organic" <?= $ad['highlight_level'] == 'organic' ? 'selected' : '' ?>>Orgânico</option>
                                            <option value="paid_highlight" <?= $ad['highlight_level'] == 'paid_highlight' ? 'selected' : '' ?>>Destaque</option>
                                            <option value="super_highlight" <?= $ad['highlight_level'] == 'super_highlight' ? 'selected' : '' ?>>Super Destaque</option>
                                            <option value="super_top" <?= $ad['highlight_level'] == 'super_top' ? 'selected' : '' ?>>Super Top</option>
                                            <option value="ultra_top" <?= $ad['highlight_level'] == 'ultra_top' ? 'selected' : '' ?>>Ultra Top</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-[10px] text-secundario uppercase font-bold w-16">Expira em</label>
                                        <input type="datetime-local" name="expires_at" value="<?= $ad['expires_at'] ? date('Y-m-d\TH:i', strtotime($ad['expires_at'])) : '' ?>" class="bg-sup2 border border-borda text-xs text-texto rounded p-1 outline-none w-32">
                                    </div>
                                    
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-[10px] text-secundario uppercase font-bold w-16">Verificado</label>
                                        <div class="w-32 flex justify-start">
                                            <input type="checkbox" name="is_verified" value="1" <?= $ad['is_verified'] ? 'checked' : '' ?> class="w-4 h-4 text-brand bg-sup2 border-borda rounded focus:ring-brand focus:ring-2">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="bg-brand/20 hover:bg-brand text-brand hover:text-white px-3 py-2 rounded font-bold text-xs transition h-full self-stretch flex items-center justify-center">Salvar</button>
                            </form>
                            
                            <!-- Delete Form -->
                            <form action="<?= $baseUrl ?>/admin/ads.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este anúncio permanentemente?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-sm transition mt-1">Excluir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($ads)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-secundario">Nenhum anúncio encontrado.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
