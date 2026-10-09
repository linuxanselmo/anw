<?php
require_once __DIR__ . '/../includes/header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $role  = $_POST['role'] ?? '';

    $validRoles = ['client', 'advertiser'];

    if (empty($email) || !in_array($role, $validRoles)) {
        $error = "E-mail e Tipo de Conta são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail inválido.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Este e-mail já está em uso.";
        } else {
            $token = bin2hex(random_bytes(32));
            $initialStatus = 'pending_verification';
            
            try {
                // Insert user without password and name initially
                $stmt = $pdo->prepare("INSERT INTO users (email, role, status, verification_token) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$email, $role, $initialStatus, $token])) {
                    
                    // Enviar email via Resend
                    $link = $baseUrl . "/cadastro/senha.php?token=" . $token;
                    
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
                                        <h1 class='title'>Confirme seu e-mail</h1>
                                        <p class='text'>Falta muito pouco para você começar a aproveitar nossa plataforma! Clique no botão abaixo para definir sua senha e concluir seu cadastro de forma segura.</p>
                                        <div class='btn-container'>
                                            <a href='{$link}' class='btn'>CRIAR MINHA SENHA</a>
                                        </div>
                                        <p class='link-fallback'>
                                            Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                                            <a href='{$link}'>{$link}</a>
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
                        'to' => [$email],
                        'subject' => 'Confirme seu cadastro na plataforma',
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
                    
                    if (curl_errno($ch)) {
                        $error = "Erro no envio do e-mail: " . curl_error($ch);
                    } else {
                        $success = true;
                    }
                    curl_close($ch);
                } else {
                    $error = "Erro ao criar cadastro. A inserção falhou.";
                }
            } catch (Exception $e) {
                $error = "Erro no banco de dados (Cadastro): " . $e->getMessage();
            }
        }
    }
}
?>

<div class="max-w-md mx-auto mt-12 mb-16 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl">
    
    <?php if ($success): ?>
        <div class="text-center">
            <div class="w-20 h-20 bg-green-500/10 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="text-3xl font-bold text-center mb-4 text-texto">Quase lá!</h2>
            <p class="text-secundario text-sm text-center mb-7">Enviamos um link de confirmação para o e-mail informado. Verifique sua caixa de entrada (e a pasta de spam) para criar sua senha e acessar sua conta.</p>
        </div>
    <?php else: ?>
        <h2 class="text-3xl font-bold text-center mb-2 text-texto">Criar Conta</h2>
        <p class="text-secundario text-sm text-center mb-7">Junte-se à plataforma e comece agora</p>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-300 px-4 py-3 rounded-xl mb-5 text-sm flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="registerForm" onsubmit="return validateRegister()" class="space-y-5">
            
            <div id="step-1">
                <!-- Tipo de Conta — Cards -->
                <div>
                    <label class="block text-[11px] text-secundario uppercase font-bold tracking-wider mb-3 text-center">
                        Qual é o seu perfil?
                    </label>

                    <!-- Input oculto que guarda o valor selecionado -->
                    <input type="hidden" name="role" id="role-input" value="<?= htmlspecialchars($_POST['role'] ?? $_GET['role'] ?? '') ?>">

                    <div class="grid grid-cols-2 gap-3" id="role-cards">
                        <!-- Card: Cliente -->
                        <button type="button" id="card-client" onclick="selectRole('client')"
                                class="role-card group relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2
                                       border-borda bg-sup2 hover:border-brand/50 hover:bg-brand/5 transition-all duration-200
                                       text-left cursor-pointer focus:outline-none">
                            <span class="role-check absolute top-3 right-3 w-5 h-5 rounded-full bg-brand
                                         flex items-center justify-center opacity-0 scale-75 transition-all duration-200">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center
                                        group-hover:bg-blue-500/20 transition">
                                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-texto font-bold text-sm">Cliente</p>
                                <p class="text-secundario text-[10px] mt-1 leading-snug">Buscar e contatar</p>
                            </div>
                        </button>

                        <!-- Card: Anunciante -->
                        <button type="button" id="card-advertiser" onclick="selectRole('advertiser')"
                                class="role-card group relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2
                                       border-borda bg-sup2 hover:border-brand/50 hover:bg-brand/5 transition-all duration-200
                                       text-left cursor-pointer focus:outline-none">
                            <span class="role-check absolute top-3 right-3 w-5 h-5 rounded-full bg-brand
                                         flex items-center justify-center opacity-0 scale-75 transition-all duration-200">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <div class="w-12 h-12 rounded-xl bg-brand/10 flex items-center justify-center
                                        group-hover:bg-brand/20 transition">
                                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-texto font-bold text-sm">Acompanhante</p>
                                <p class="text-secundario text-[10px] mt-1 leading-snug">Publicar anúncios</p>
                            </div>
                        </button>
                    </div>

                    <p id="role-error" class="hidden text-red-400 text-xs mt-2 flex items-center gap-1 justify-center">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Selecione um tipo de conta.
                    </p>
                </div>

                <button type="button" onclick="nextStep()"
                        class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded-xl transition
                               shadow-lg shadow-rose-900/40 mt-6 text-sm tracking-wide">
                    Continuar
                </button>
            </div>

            <div id="step-2" class="hidden space-y-5">
                <button type="button" onclick="prevStep()" class="text-secundario text-sm flex items-center hover:text-white transition mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Voltar
                </button>

                <!-- E-mail -->
                <div>
                    <label class="block text-[11px] text-secundario uppercase font-bold tracking-wider mb-1.5">Qual é o seu melhor E-mail?</label>
                    <input type="email" name="email" id="reg-email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? '') ?>"
                           class="w-full bg-sup2 border border-borda text-texto rounded-xl px-4 py-3 text-sm
                                  focus:outline-none focus:border-brand transition placeholder-secundario"
                           placeholder="seu@email.com">
                </div>

                <!-- Submit -->
                <button type="submit" id="submit-btn"
                        class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded-xl transition
                               shadow-lg shadow-rose-900/40 mt-2 text-sm tracking-wide">
                    Finalizar e Receber Link
                </button>
            </div>
            
        </form>

        <div class="mt-6 text-center text-secundario text-sm">
            Já tem uma conta? <a href="<?= $baseUrl ?>/login/" class="text-brand hover:underline font-medium">Fazer login</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function selectRole(role) {
    document.getElementById('role-input').value = role;

    document.querySelectorAll('.role-card').forEach(card => {
        const check = card.querySelector('.role-check');
        const isThis = card.id === 'card-' + role;

        card.classList.toggle('border-brand',      isThis);
        card.classList.toggle('bg-brand/5',        isThis);
        card.classList.toggle('border-borda',      !isThis);
        card.classList.toggle('bg-sup2',           !isThis);

        check.classList.toggle('opacity-0',   !isThis);
        check.classList.toggle('scale-75',    !isThis);
        check.classList.toggle('opacity-100', isThis);
        check.classList.toggle('scale-100',   isThis);
    });

    document.getElementById('role-error').classList.add('hidden');
}

function nextStep() {
    if (!document.getElementById('role-input').value) {
        document.getElementById('role-error').classList.remove('hidden');
        return;
    }
    document.getElementById('step-1').classList.add('hidden');
    document.getElementById('step-2').classList.remove('hidden');
    // focar no email
    setTimeout(() => document.getElementById('reg-email').focus(), 100);
}

function prevStep() {
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-1').classList.remove('hidden');
}

function validateRegister() {
    const role = document.getElementById('role-input').value;
    if (!role) {
        prevStep();
        document.getElementById('role-error').classList.remove('hidden');
        return false;
    }
    const btn = document.getElementById('submit-btn');
    btn.innerHTML = 'Enviando...';
    btn.disabled = true;
    btn.classList.add('opacity-70', 'cursor-not-allowed');
    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    const preRole = document.getElementById('role-input').value;
    if (preRole === 'client' || preRole === 'advertiser') {
        selectRole(preRole);
        // se tiver postado com erro, já vai pra step 2
        <?php if ($error || isset($_GET['email'])): ?>
        nextStep();
        <?php endif; ?>
    }
});
</script>
