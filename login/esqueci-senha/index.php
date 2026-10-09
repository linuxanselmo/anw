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

            $html = "
            <!DOCTYPE html>
            <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0D0711; -webkit-font-smoothing: antialiased; }
                    .wrapper { width: 100%; table-layout: fixed; background-color: #0D0711; padding-bottom: 60px; }
                    .main { margin: 0 auto; width: 100%; max-width: 600px; background-color: #170B1D; border: 1px solid #45204F; border-radius: 16px; margin-top: 40px; border-spacing: 0; color: #FFF7FA; }
                    .padding { padding: 40px; text-align: center; }
                    .logo { max-width: 200px; margin-bottom: 25px; }
                    .title { font-size: 26px; font-weight: bold; margin: 0 0 15px 0; color: #FFF7FA; }
                    .text { font-size: 16px; line-height: 1.6; color: #C9B8C6; margin: 0 0 30px 0; }
                    .btn-container { text-align: center; margin-bottom: 30px; }
                    .btn { display: inline-block; background-color: #E91E63; color: #ffffff !important; text-decoration: none; padding: 15px 35px; border-radius: 12px; font-weight: bold; font-size: 16px; letter-spacing: 1px; }
                    .link-fallback { font-size: 13px; color: #C9B8C6; margin-top: 20px; line-height: 1.5; }
                    .link-fallback a { color: #E91E63; word-break: break-all; }
                    .footer { padding-top: 20px; text-align: center; font-size: 12px; color: #7B238F; }
                </style>
            </head>
            <body>
                <center class='wrapper'>
                    <table class='main' width='100%'>
                        <tr>
                            <td class='padding'>
                                <img src='{$baseUrl}/img/logo.png' alt='Acompanhantes Na Web' class='logo'>
                                <h1 class='title'>Recuperação de Senha</h1>
                                <p class='text'>Olá! Você solicitou a redefinição de sua senha. Clique no botão abaixo para criar uma nova senha. Este link é válido por 1 hora.</p>
                                <div class='btn-container'>
                                    <a href='{$resetLink}' class='btn'>REDEFINIR MINHA SENHA</a>
                                </div>
                                <p class='text' style='font-size: 14px;'>Se você não solicitou esta alteração, pode ignorar este e-mail em segurança.</p>
                                <p class='link-fallback'>
                                    Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                                    <a href='{$resetLink}'>{$resetLink}</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <div class='footer'>
                        &copy; " . date('Y') . " Acompanhantes Na Web. Todos os direitos reservados.
                    </div>
                </center>
            </body>
            </html>";

            $data = [
                'from' => 'Acompanhantes Na Web <' . (defined('MAIL_FROM') ? MAIL_FROM : 'nao-responda@anw.com.br') . '>',
                'to' => [$user['email']],
                'subject' => 'Recuperação de Senha - ANW',
                'html' => $html
            ];

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . (defined('RESEND_API_KEY') ? RESEND_API_KEY : ''),
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            curl_close($ch);

            $success = "Um e-mail com instruções para redefinir sua senha foi enviado. (Verifique sua caixa de spam)";
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
