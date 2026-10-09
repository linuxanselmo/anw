<?php
require_once __DIR__ . '/../includes/header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'client';

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Todos os campos são obrigatórios.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "E-mail já está em uso.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $initialStatus = ($role === 'advertiser') ? 'pending_verification' : 'active';
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role, $initialStatus]);

            // Sessão automática
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_role'] = $role;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_status'] = $initialStatus;

            if ($role === 'advertiser' && $initialStatus !== 'active') {
                header("Location: {$baseUrl}/painel/verificacao.php");
            } else {
                header("Location: {$baseUrl}/painel/");
            }
            exit;
        }
    }
}
?>

<div class="max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <h2 class="text-3xl font-bold text-center mb-6 text-texto">Criar Conta</h2>

    <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-center">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-4">
        <div>
            <label class="block text-secundario mb-1">Nome Completo</label>
            <input type="text" name="name" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
        </div>
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
        <div>
            <label class="block text-secundario mb-1">Tipo de Conta</label>
            <select name="role" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                <option value="client">Cliente (Apenas visualização e contato)</option>
                <option value="advertiser">Anunciante (Quero postar anúncios)</option>
            </select>
        </div>
        <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded transition shadow-lg shadow-rose-900/50 mt-4">Cadastrar</button>
    </form>

    <div class="mt-6 text-center text-secundario text-sm">
        Já tem uma conta? <a href="<?= $baseUrl ?>/login/" class="text-brand hover:underline">Fazer login</a>
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
