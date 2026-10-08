<?php
require_once __DIR__ . '/../../includes/header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, insira um e-mail válido.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmtUpdate = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $expires, $user['id']]);

            $resetLink = "{$baseUrl}/login/redefinir-senha/?token=" . $token;

            $to = $user['email'];
            $subject = "Recuperação de Senha - ANW";
            
            $message = "Olá " . htmlspecialchars($user['name']) . ",\n\n";
            $message .= "Você solicitou a redefinição de sua senha.\n";
            $message .= "Acesse o link abaixo para criar uma nova senha. Este link é válido por 1 hora.\n\n";
            $message .= $resetLink . "\n\n";
            $message .= "Se você não solicitou esta alteração, ignore este e-mail.\n";

            $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (mail($to, $subject, $message, $headers)) {
                $success = "Um e-mail com instruções para redefinir sua senha foi enviado. (Verifique sua caixa de spam)";
            } else {
                $error = "Não foi possível enviar o e-mail no momento. Tente novamente mais tarde.";
            }
        } else {
            // Não revelar se o email existe por segurança
            $success = "Se o e-mail estiver cadastrado, você receberá um link de recuperação.";
        }
    }
}
?>

<div class="max-w-md mx-auto mt-12 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    <h2 class="text-3xl font-bold text-center mb-6 text-texto">Recuperar Senha</h2>
    <p class="text-secundario text-center mb-6 text-sm">Insira seu e-mail abaixo e enviaremos um link para você redefinir sua senha.</p>

    <?php if ($success): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-4 text-center text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-center text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success || $error): ?>
    <form action="" method="POST" class="space-y-4">
        <div>
            <label class="block text-secundario mb-1">E-mail</label>
            <input type="email" name="email" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition" placeholder="seu@email.com">
        </div>
        <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded transition shadow-lg shadow-rose-900/50 mt-4">Enviar Link</button>
    </form>
    <?php endif; ?>

    <div class="mt-6 text-center text-secundario text-sm">
        Lembrou da senha? <a href="<?= $baseUrl ?>/login/" class="text-brand hover:underline">Voltar para o login</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
