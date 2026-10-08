<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$baseUrl}/login/");
    exit;
}

$success = null;
$error = null;

// Busca dados atuais
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($name) || empty($email)) {
        $error = "Nome e E-mail são obrigatórios.";
    } else {
        // Verifica se e-mail já existe em outra conta
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtCheck->execute([$email, $_SESSION['user_id']]);
        if ($stmtCheck->fetch()) {
            $error = "Este e-mail já está sendo usado por outra conta.";
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                if ($stmtUpdate->execute([$name, $email, $hash, $_SESSION['user_id']])) {
                    $success = "Dados e senha atualizados com sucesso!";
                    $_SESSION['user_name'] = $name;
                } else {
                    $error = "Erro ao atualizar os dados.";
                }
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                if ($stmtUpdate->execute([$name, $email, $_SESSION['user_id']])) {
                    $success = "Dados atualizados com sucesso!";
                    $_SESSION['user_name'] = $name;
                } else {
                    $error = "Erro ao atualizar os dados.";
                }
            }
            
            // Atualiza var $user para exibir no form
            $user['name'] = $name;
            $user['email'] = $email;
        }
    }
}
?>

<div class="max-w-2xl mx-auto mt-12 mb-20 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <div class="flex items-center gap-4 mb-8 border-b border-borda pb-6">
        <div class="w-16 h-16 bg-brand/20 text-brand rounded-full flex items-center justify-center shrink-0">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </div>
        <div>
            <h2 class="text-3xl font-extrabold text-texto">Configurações da Conta</h2>
            <p class="text-secundario text-sm mt-1">Atualize seu nome, e-mail e senha de acesso.</p>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-4 rounded-lg mb-6 text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-lg mb-6 text-center">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-6">
        <div>
            <label class="block text-secundario font-bold mb-2">Nome Completo</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
        </div>
        
        <div>
            <label class="block text-secundario font-bold mb-2">E-mail de Login</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
        </div>
        
        <div class="pt-4 border-t border-borda">
            <h3 class="text-lg font-bold text-texto mb-4">Alterar Senha</h3>
            <label class="block text-secundario font-bold mb-2">Nova Senha (opcional)</label>
            <div class="relative">
                <input type="password" id="password" name="password" placeholder="Deixe em branco para manter a senha atual" class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition pr-10">
                <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-secundario hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            <p class="text-xs text-secundario mt-2">Preencha apenas se desejar trocar a senha da sua conta.</p>
        </div>
        
        <div class="pt-6 flex justify-end gap-4">
            <a href="<?= $baseUrl ?>/painel/" class="bg-sup2 hover:bg-borda text-texto font-bold py-3 px-8 rounded-lg transition">Cancelar</a>
            <button type="submit" class="bg-brand hover:bg-brand-hover text-white font-bold py-3 px-8 rounded-lg transition shadow-lg shadow-brand/30">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function togglePassword(fieldId) {
    var field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text";
    } else {
        field.type = "password";
    }
}
</script>
