<?php
require_once __DIR__ . '/../includes/header.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_status'] = $user['status'];

        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $stmtToken = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmtToken->execute([$token, $user['id']]);
            setcookie('remember_me', $token, time() + (86400 * 30), "/"); // 30 dias
        }

        session_write_close();

        if ($user['role'] === 'advertiser' && $user['status'] !== 'active') {
            header("Location: {$baseUrl}/painel/verificacao.php");
        } else {
            header("Location: {$baseUrl}/painel/");
        }
        exit;
    } else {
        $error = "E-mail ou senha inválidos.";
    }
}
?>

<div class="max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <h2 class="text-3xl font-bold text-center mb-6 text-texto">Login</h2>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-4 text-center">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-4">
        <div>
            <label class="block text-secundario mb-1">E-mail</label>
            <input type="email" name="email" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
        </div>
        <div>
            <label class="block text-secundario mb-1">Senha</label>
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
        <div class="flex items-center">
            <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 accent-brand rounded bg-sup2 border-borda">
            <label for="remember_me" class="ml-2 block text-sm text-secundario">Lembrar de mim</label>
        </div>
        <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded transition shadow-lg shadow-rose-900/50 mt-4">Entrar</button>
        <div class="mt-4 text-center">
            <a href="<?= $baseUrl ?>/login/esqueci-senha/" class="text-sm text-secundario hover:text-brand transition">Esqueci minha senha</a>
        </div>
    </form>

    <div class="mt-6 text-center text-secundario text-sm">
        Não tem uma conta? <a href="<?= $baseUrl ?>/cadastro/" class="text-brand hover:underline">Cadastre-se</a>
    </div>
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
