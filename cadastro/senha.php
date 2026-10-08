<?php
require_once __DIR__ . '/../includes/header.php';

$error = null;
$success = null;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    die("<div class='max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl text-center text-texto'>Link inválido ou expirado.</div>");
}

// Verifica o token no banco
$stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("<div class='max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl text-center text-texto'>Link inválido ou expirado. Verifique se você já definiu sua senha.</div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($password)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif (strlen($password) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Define status dependendo do perfil
        $newStatus = ($user['role'] === 'advertiser') ? 'pending_verification' : 'active';

        $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, verification_token = NULL, status = ? WHERE id = ?");

        try {
            if ($stmt->execute([$name, $hash, $newStatus, $user['id']])) {

                // Login automático
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $name;
                $_SESSION['user_status'] = $newStatus;

                // Redirecionamento
                if ($user['role'] === 'advertiser') {
                    // Eles podem querer ir direto para a criação do anúncio
                    header("Location: {$baseUrl}/painel/criar.php");
                } else {
                    header("Location: {$baseUrl}/painel/");
                }
                exit;
            } else {
                $error = "Erro ao salvar os dados. A atualização falhou.";
            }
        } catch (Exception $e) {
            $error = "Erro no banco de dados (Definir Senha): " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-md mx-auto mt-12 mb-16 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <h2 class="text-3xl font-bold text-center mb-2 text-texto">Quase pronto!</h2>
    <p class="text-secundario text-sm text-center mb-7">Defina seu nome e uma senha segura para acessar sua conta.</p>

    <?php if ($error): ?>
        <div
            class="bg-red-500/20 border border-red-500/40 text-red-300 px-4 py-3 rounded-xl mb-5 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="space-y-5">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <!-- Nome -->
        <div>
            <label class="block text-[11px] text-secundario uppercase font-bold tracking-wider mb-1.5">Como devemos te
                chamar?</label>
            <input type="text" name="name" id="reg-name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                class="w-full bg-sup2 border border-borda text-texto rounded-xl px-4 py-3 text-sm
                          focus:outline-none focus:border-brand transition placeholder-secundario"
                placeholder="Seu nome (pode ser fictício)">
        </div>

        <!-- Senha -->
        <div>
            <label class="block text-[11px] text-secundario uppercase font-bold tracking-wider mb-1.5">Crie sua
                Senha</label>
            <div class="relative">
                <input type="password" id="password" name="password" required class="w-full bg-sup2 border border-borda text-texto rounded-xl px-4 py-3 text-sm pr-11
                              focus:outline-none focus:border-brand transition" placeholder="Mínimo 6 caracteres">
                <button type="button" onclick="togglePassword('password')"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-secundario hover:text-texto transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Submit -->
        <button type="submit" id="submit-btn" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded-xl transition
                       shadow-lg shadow-rose-900/40 mt-2 text-sm tracking-wide">
            Finalizar Cadastro
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
    function togglePassword(fieldId) {
        const f = document.getElementById(fieldId);
        f.type = f.type === 'password' ? 'text' : 'password';
    }
</script>