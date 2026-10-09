<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

$success = null;
$error = null;

// Ensure plan column exists
try { $pdo->exec("ALTER TABLE ads ADD COLUMN plan VARCHAR(50) DEFAULT 'organic'"); } catch (Exception $e) {}

// Auto-reset advertisers with incomplete data so they resubmit the full form
try {
    $pdo->exec("
        UPDATE users 
        SET status = 'pending_verification', phone = NULL, birth_date = NULL, cpf = NULL, verification_media = NULL
        WHERE role = 'advertiser' 
          AND id IN (SELECT user_id FROM ads WHERE status = 'pending_approval')
          AND (phone IS NULL OR phone = '' OR birth_date IS NULL OR birth_date = '')
    ");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($userId && in_array($action, ['approve', 'reject', 'reset'])) {
        if ($action === 'reset') {
            $stmtDeleteAd = $pdo->prepare("DELETE FROM ads WHERE user_id = ? AND status = 'pending_approval'");
            $stmtDeleteAd->execute([$userId]);
            
            $stmt = $pdo->prepare("UPDATE users SET status = 'pending_verification', phone = NULL, birth_date = NULL, cpf = NULL, verification_media = NULL WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $success = "Verificação resetada. O usuário deverá reenviar os dados completos.";
            } else {
                $error = "Erro ao resetar verificação.";
            }
        } else {
            $newStatus = ($action === 'approve') ? 'active' : 'rejected';
            $plan = isset($_POST['plan']) ? $_POST['plan'] : null;
            
            if ($action === 'approve' && $plan) {
                $stmt = $pdo->prepare("UPDATE ads SET status = ?, plan = ? WHERE user_id = ? AND status = 'pending_approval'");
                $executed = $stmt->execute([$newStatus, $plan, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE ads SET status = ? WHERE user_id = ? AND status = 'pending_approval'");
                $executed = $stmt->execute([$newStatus, $userId]);
            }
            
            if ($executed) {
                $stmtName = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmtName->execute([$userId]);
                $userName = $stmtName->fetchColumn();
                $userNameStr = $userName ? " de " . $userName : "";
                
                $success = "Anúncio" . $userNameStr . ($action === 'approve' ? " aprovado" : " rejeitado") . " com sucesso.";
            } else {
                $error = "Erro ao atualizar status do anúncio.";
            }
        }
    }
}

// Fetch pending verifications
$stmt = $pdo->query("SELECT u.id, u.name, u.email, u.cpf, u.phone, u.birth_date, u.verification_media, a.created_at, a.id as ad_id, a.title, a.price, a.description, a.plan 
                     FROM users u 
                     JOIN ads a ON u.id = a.user_id 
                     WHERE u.role = 'advertiser' AND a.status = 'pending_approval' 
                     ORDER BY a.created_at ASC");
$verifications = $stmt->fetchAll();

foreach ($verifications as &$v) {
    $v['ad'] = [
        'id' => $v['ad_id'],
        'title' => $v['title'],
        'price' => $v['price'],
        'description' => $v['description'],
        'plan' => $v['plan'] ?? 'organic'
    ];
}
unset($v);
?>

<div class="flex flex-col md:flex-row gap-6 p-6 max-w-7xl mx-auto mt-6 mb-20">
    <!-- Sidebar admin -->
    <div class="w-full md:w-64 bg-sup1 rounded-2xl border border-borda shadow-2xl p-6 h-fit shrink-0">
        <h2 class="text-xl font-bold text-texto mb-6">Menu Admin</h2>
        <nav class="space-y-2 flex flex-col">
            <a href="<?= $baseUrl ?>/admin/" class="text-secundario hover:text-texto hover:bg-sup2 px-4 py-2 rounded transition">Início</a>
            <a href="<?= $baseUrl ?>/admin/ads.php" class="text-secundario hover:text-texto hover:bg-sup2 px-4 py-2 rounded transition">Anúncios</a>
            <a href="<?= $baseUrl ?>/admin/users.php" class="text-secundario hover:text-texto hover:bg-sup2 px-4 py-2 rounded transition">Usuários</a>
            <a href="<?= $baseUrl ?>/admin/verificacoes.php" class="bg-brand/10 text-brand font-bold px-4 py-2 rounded transition border border-brand/20">Aprovar Anúncios</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 bg-sup1 rounded-2xl border border-borda shadow-2xl p-6 md:p-8 overflow-hidden">
        <h1 class="text-3xl font-extrabold text-texto mb-8">Aprovar Anúncios</h1>
        
        <?php if($success): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-4 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if(empty($verifications)): ?>
            <div class="text-center py-10 bg-sup2 rounded-xl border border-dashed border-borda">
                <p class="text-secundario">Não há nenhum anúncio pendente no momento.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach($verifications as $v): ?>
                    <div class="bg-sup2 border border-borda rounded-xl p-6 flex flex-col md:flex-row gap-6 items-start">
                        <!-- Media Preview -->
                        <div class="w-full md:w-48 shrink-0">
                            <?php 
                            $ext = strtolower(pathinfo($v['verification_media'], PATHINFO_EXTENSION));
                            if ($ext === 'mp4'):
                            ?>
                                <video src="<?= htmlspecialchars($v['verification_media']) ?>" controls class="w-full h-auto rounded bg-black border border-borda"></video>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($v['verification_media']) ?>" target="_blank" class="block group relative">
                                    <img src="<?= htmlspecialchars($v['verification_media']) ?>" class="w-full h-auto rounded object-cover border border-borda">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition rounded flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">Ver imagem completa</span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h3 class="text-xl font-bold text-texto"><?= htmlspecialchars($v['name']) ?></h3>
                                <?php if (empty($v['phone']) || empty($v['birth_date'])): ?>
                                <span class="inline-flex items-center gap-1 bg-yellow-500/20 border border-yellow-500/50 text-yellow-400 text-xs font-bold px-2 py-1 rounded-full">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    Dados incompletos
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-secundario text-sm">E-mail: <span class="text-texto"><?= htmlspecialchars($v['email']) ?></span></p>
                            <p class="text-secundario text-sm">CPF: <span class="text-texto font-mono"><?= !empty($v['cpf']) ? htmlspecialchars($v['cpf']) : '<span class="text-yellow-500 italic">Não informado</span>' ?></span></p>

                            <?php if (!empty($v['birth_date'])): 
                                $age = date_diff(date_create($v['birth_date']), date_create('now'))->y;
                            ?>
                            <p class="text-secundario text-sm">Idade: <span class="text-texto font-bold"><?= $age ?> anos</span> <span class="text-secundario font-mono text-xs">(<?= date('d/m/Y', strtotime($v['birth_date'])) ?>)</span></p>
                            <?php else: ?>
                            <p class="text-secundario text-sm">Idade: <span class="text-yellow-500 italic">Não informada</span></p>
                            <?php endif; ?>

                            <?php if (!empty($v['phone'])): 
                                $waNumber = preg_replace('/\D/', '', $v['phone']);
                            ?>
                            <p class="text-secundario text-sm">Telefone: <span class="text-texto font-mono"><?= htmlspecialchars($v['phone']) ?></span> &nbsp;
                                <a href="https://wa.me/55<?= $waNumber ?>" target="_blank" class="inline-flex items-center gap-1 text-green-400 font-bold hover:underline">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.136.561 4.14 1.535 5.875L0 24l6.335-1.51A11.955 11.955 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.81 9.81 0 01-5.031-1.388l-.361-.214-3.741.981.999-3.648-.235-.374A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182c5.43 0 9.818 4.388 9.818 9.818 0 5.43-4.388 9.818-9.818 9.818z"/></svg>
                                    WhatsApp
                                </a>
                            </p>
                            <?php else: ?>
                            <p class="text-secundario text-sm">Telefone: <span class="text-yellow-500 italic">Não informado</span></p>
                            <?php endif; ?>

                            <p class="text-secundario text-sm">Data de envio: <span class="text-texto"><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></span></p>
                            
                            <!-- Ad Data -->
                            <?php if (!empty($v['ad'])): ?>
                            <div class="mt-4 p-4 bg-sup1 border border-borda rounded-lg">
                                <h4 class="font-bold text-brand mb-2">Dados do Anúncio</h4>
                                <p class="text-secundario text-sm">Título: <span class="text-texto"><?= htmlspecialchars($v['ad']['title']) ?></span></p>
                                <p class="text-secundario text-sm">Preço: <span class="text-texto">R$ <?= number_format($v['ad']['price'], 2, ',', '.') ?></span></p>
                                <p class="text-secundario text-sm">Plano de Destaque: <span class="text-brand font-bold uppercase"><?= htmlspecialchars($v['ad']['plan']) ?></span></p>
                                <a href="<?= $baseUrl ?>/anuncio/?id=<?= $v['ad']['id'] ?>" target="_blank" class="text-blue-400 hover:underline text-sm inline-block mt-2">Visualizar Anúncio (Prévia)</a>
                            </div>
                            <?php else: ?>
                            <div class="mt-4 p-4 bg-sup1 border border-borda rounded-lg border-dashed">
                                <p class="text-yellow-500 text-sm">Nenhum anúncio criado ainda. O usuário não completou o passo 4.</p>
                            </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="flex flex-wrap gap-3 pt-4 border-t border-borda mt-4 items-center">
                                <form action="" method="POST" class="inline-flex gap-2 items-center flex-wrap">
                                    <input type="hidden" name="user_id" value="<?= $v['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <select name="plan" class="bg-sup2 border border-borda text-texto text-sm rounded px-3 py-2 outline-none focus:border-brand h-[40px]">
                                        <option value="organic" <?= $v['ad']['plan'] == 'organic' ? 'selected' : '' ?>>Anúncios</option>
                                        <option value="paid_highlight" <?= $v['ad']['plan'] == 'paid_highlight' ? 'selected' : '' ?>>⭐ Destaques</option>
                                        <option value="super_highlight" <?= $v['ad']['plan'] == 'super_highlight' ? 'selected' : '' ?>>⭐⭐ Super Destaques</option>
                                        <option value="super_top" <?= $v['ad']['plan'] == 'super_top' ? 'selected' : '' ?>>💎 Super Top</option>
                                        <option value="ultra_top" <?= $v['ad']['plan'] == 'ultra_top' ? 'selected' : '' ?>>👑 Ultra Top</option>
                                    </select>
                                    <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-6 py-2 rounded transition font-bold shadow-lg shadow-green-900/20 h-[40px]">Aprovar Anúncio</button>
                                </form>
                                <form action="" method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja rejeitar este anúncio? A conta da anunciante continuará ativa.');">
                                    <input type="hidden" name="user_id" value="<?= $v['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-6 py-2 rounded transition font-bold shadow-lg shadow-red-900/20">Rejeitar</button>
                                </form>
                                <form action="" method="POST" class="inline" onsubmit="return confirm('Isso irá resetar a verificação da usuária. Ela precisará reenviar todos os dados (telefone, data de nascimento, CPF e vídeo). Continuar?');">
                                    <input type="hidden" name="user_id" value="<?= $v['id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-500 text-white px-4 py-2 rounded transition font-bold shadow-lg shadow-yellow-900/20 text-sm">↺ Resetar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
