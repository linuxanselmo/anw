<?php
require_once __DIR__ . '/../../includes/header.php';

$error = null;
$success = null;
$tokenValid = false;
$user_id = null;

$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

if ($token) {
    // Verificar token no banco de dados
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $tokenValid = true;
        $user_id = $user['id'];
    } else {
        $error = "Link de recuperação inválido ou expirado. Por favor, solicite um novo link.";
    }
} else {
    $error = "Nenhum token fornecido.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (strlen($password) < 6) {
        $error = "A senha deve ter no mínimo 6 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmtUpdate = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if ($stmtUpdate->execute([$hashedPassword, $user_id])) {
            $success = "Senha alterada com sucesso! Você já pode fazer login.";
            $tokenValid = false; // Hide form
        } else {
            $error = "Erro ao atualizar a senha. Tente novamente.";
        }
    }
}
?>

<div class="max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <h2 class="text-3xl font-bold text-center mb-6 text-texto">Redefinir Senha</h2>

    <?php if ($success): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-4 text-center text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
        <div class="mt-4 text-center">
            <a href="<?= $baseUrl ?>/login/" class="bg-brand hover:bg-brand-hover text-white font-bold py-3 px-6 rounded transition shadow-lg shadow-rose-900/50 inline-block">Ir para o Login</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-center text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php if (!$tokenValid && !$success): ?>
            <div class="mt-4 text-center">
                <a href="<?= $baseUrl ?>/login/esqueci-senha/" class="text-brand hover:underline">Solicitar novo link</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($tokenValid): ?>
    <p class="text-secundario text-center mb-6 text-sm">Crie uma nova senha para sua conta.</p>
    <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div>
            <label class="block text-secundario mb-1">Nova Senha</label>
            <div class="relative">
                <input type="password" id="password" name="password" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition pr-10">
                <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-secundario hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>
        <div>
            <label class="block text-secundario mb-1">Confirmar Senha</label>
            <div class="relative">
                <input type="password" id="confirm_password" name="confirm_password" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition pr-10">
                <button type="button" onclick="togglePassword('confirm_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-secundario hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded transition shadow-lg shadow-rose-900/50 mt-4">Salvar Nova Senha</button>
    </form>
    
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
