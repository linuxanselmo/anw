<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: {$baseUrl}/admin/users.php");
    exit;
}

$stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <div class="flex items-center mb-8">
        <a href="<?= $baseUrl ?>/admin/" class="text-secundario hover:text-brand transition mr-4">&larr; Voltar</a>
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Gerenciar <span class="text-brand">Usuários</span></h1>
    </div>

    <div class="bg-sup1 border border-borda rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-sup2 text-secundario text-sm uppercase tracking-wider">
                        <th class="px-6 py-4 font-bold border-b border-borda">ID</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Nome</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">E-mail</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Papel</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Data</th>
                        <th class="px-6 py-4 font-bold border-b border-borda text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-borda text-gray-300">
                    <?php foreach($users as $user): ?>
                    <tr class="hover:bg-sup2/50 transition">
                        <td class="px-6 py-4 text-texto">#<?= $user['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-texto"><?= htmlspecialchars($user['name']) ?></td>
                        <td class="px-6 py-4 text-texto"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="px-6 py-4">
                            <?php if($user['role'] === 'admin'): ?>
                                <span class="bg-brand/20 text-brand px-2 py-1 rounded text-xs font-bold uppercase">Admin</span>
                            <?php elseif($user['role'] === 'advertiser'): ?>
                                <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded text-xs font-bold uppercase">Anunciante</span>
                            <?php else: ?>
                                <span class="bg-gray-500/20 text-gray-400 px-2 py-1 rounded text-xs font-bold uppercase">Cliente</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-secundario"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td class="px-6 py-4 text-right">
                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                            <form action="<?= $baseUrl ?>/admin/users.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este usuário permanentemente? Isso apagará todos os seus anúncios.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-sm transition">Excluir</button>
                            </form>
                            <?php else: ?>
                                <span class="text-secundario text-sm italic">Você</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-secundario">Nenhum usuário encontrado.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
