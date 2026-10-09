<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appConfig = __DIR__ . '/../config/app.php';
if (!file_exists($appConfig)) {
    require_once __DIR__ . '/../install.php';
    exit;
}
require_once $appConfig;
require_once __DIR__ . '/../config/database.php';

// Track Site Visits (Filtered for real users)
try {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $isApiOrAction = strpos($_SERVER['REQUEST_URI'] ?? '', 'check_email') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', 'api') !== false;
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Basic bot detection
    $isBot = preg_match('/bot|crawl|spider|slurp|facebook|google|bing|yandex|yahoo/i', $userAgent);

    // Só rastreia se NÃO for admin, NÃO for bot, NÃO for ajax/api
    if (!$isAdmin && !$isBot && !$isAjax && !$isApiOrAction) {
        $today = date('Y-m-d');
        $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . $userAgent);
        
        // Create table if not exists (fallback)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `site_visits` (
          `visit_date` DATE NOT NULL,
          `ip_hash` VARCHAR(64) NOT NULL,
          `page_views` INT DEFAULT 1,
          PRIMARY KEY (`visit_date`, `ip_hash`)
        )");

        $stmtVisit = $pdo->prepare("INSERT INTO site_visits (visit_date, ip_hash, page_views) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE page_views = page_views + 1");
        $stmtVisit->execute([$today, $ipHash]);
    }
} catch (\Exception $e) {
    // Ignore error
}

// Auto-login via remember_me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmtToken = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmtToken->execute([$token]);
    $userByToken = $stmtToken->fetch();
    
    if ($userByToken) {
        $_SESSION['user_id'] = $userByToken['id'];
        $_SESSION['user_name'] = $userByToken['name'];
        $_SESSION['user_role'] = $userByToken['role'];
        $_SESSION['user_status'] = $userByToken['status'];
    }
}

// Redirecionamento seguro caso a URL base mude na produção
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Link de ação principal: vai para o painel de criação ou abre o modal
$acaoPrincipalLink = isset($_SESSION['user_id']) ? $baseUrl . '/painel/criar.php' : 'javascript:openAuthModal()';

// Fetch tracking tags
$gaId = '';
$gAdsId = '';
$metaPixelId = '';
$gscId = '';
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'ga4_id') $gaId = trim($row['setting_value']);
        if ($row['setting_key'] === 'google_ads_id') $gAdsId = trim($row['setting_value']);
        if ($row['setting_key'] === 'meta_pixel_id') $metaPixelId = trim($row['setting_value']);
        if ($row['setting_key'] === 'google_search_console_id') $gscId = trim($row['setting_value']);
    }
} catch (\Exception $e) {
    // Tabela pode ainda não existir
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Tracking Tags -->
    <?php if (!empty($gscId)): ?>
    <!-- Google Search Console -->
    <meta name="google-site-verification" content="<?= htmlspecialchars($gscId) ?>" />
    <?php endif; ?>

    <?php if (!empty($gaId)): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($gaId) ?>');
    </script>
    <?php endif; ?>

    <?php if (!empty($gAdsId)): ?>
    <!-- Google Ads -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gAdsId) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($gAdsId) ?>');
    </script>
    <?php endif; ?>

    <?php if (!empty($metaPixelId)): ?>
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?= htmlspecialchars($metaPixelId) ?>');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= htmlspecialchars($metaPixelId) ?>&ev=PageView&noscript=1"/></noscript>
    <!-- End Meta Pixel Code -->
    <?php endif; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhantes na Web - ANW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#E91E63', // Rosa
                            hover: '#F43B50', // Coral
                        },
                        coral: '#F43B50',
                        roxo: '#7B238F',
                        fundo: '#0D0711',
                        sup1: '#170B1D',
                        sup2: '#25112D',
                        texto: '#FFF7FA',
                        secundario: '#C9B8C6',
                        borda: '#45204F'
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Premium Adult Theme Styles */
        body { background-color: #0D0711; color: #FFF7FA; }
        .glass { background: rgba(23, 11, 29, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid #45204F; }
        .glass-bottom { border-bottom: none; border-top: 1px solid #45204F; }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col bg-fundo text-texto pb-20 md:pb-0">

<!-- Navigation -->
<nav class="glass fixed w-full z-50 top-0">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0 flex items-center">
                <a href="<?= $baseUrl ?>/" class="flex items-center">
                    <img src="<?= $baseUrl ?>/img/logo.png" alt="ANW.com.br" class="h-12 w-auto drop-shadow-md hover:scale-105 transition-transform duration-300">
                </a>
            </div>
            
            <!-- Mobile Menu Actions -->
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="flex md:hidden items-center space-x-5">
                <a href="<?= $baseUrl ?>/painel/configuracoes.php" class="text-secundario hover:text-white transition" aria-label="Configurações">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </a>
                <a href="<?= $baseUrl ?>/sair/" class="text-brand hover:text-white transition" aria-label="Sair">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
            <?php endif; ?>
            <div class="hidden md:flex space-x-8 items-center">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?= $baseUrl ?>/anuncios/" class="text-gray-300 hover:text-white transition">Anúncios</a>
                    <?php if($_SESSION['user_role'] === 'admin'): ?>
                        <a href="<?= $baseUrl ?>/admin/" class="text-brand hover:text-white font-bold transition">Admin</a>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>/painel/" class="text-gray-300 hover:text-white transition">Meu Painel</a>
                    <a href="<?= $baseUrl ?>/painel/configuracoes.php" class="text-gray-300 hover:text-white transition">Configurações</a>
                    <a href="<?= $baseUrl ?>/sair/" class="text-gray-300 hover:text-brand transition">Sair</a>
                <?php else: ?>
                    <button onclick="openAuthModal()" class="bg-brand hover:bg-brand-hover text-white px-6 py-2 rounded-full font-bold transition shadow-lg shadow-rose-900/50 uppercase tracking-wide">ENTRAR</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Bottom Navigation -->
<nav class="glass glass-bottom fixed bottom-0 w-full z-[60] md:hidden pb-safe">
    <div class="flex justify-around items-center h-16 relative">
        <!-- Início -->
        <a href="<?= $baseUrl ?>/" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span class="text-[10px] font-medium">Início</span>
        </a>
        
        <!-- Buscar -->
        <a href="<?= $baseUrl ?>/anuncios/" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <span class="text-[10px] font-medium">Buscar</span>
        </a>
        
        <!-- Anunciar (FAB Style) -->
        <div class="relative w-full flex justify-center">
            <a href="<?= $acaoPrincipalLink ?>" class="absolute -top-8 flex items-center justify-center w-14 h-14 bg-brand rounded-full shadow-[0_0_15px_rgba(233,30,99,0.5)] text-white hover:scale-105 transition transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </a>
        </div>
        
        <!-- Categorias -->
        <a href="<?= $baseUrl ?>/categorias/" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            <span class="text-[10px] font-medium">Categorias</span>
        </a>
        
        <!-- Perfil -->
        <?php $perfilLink = isset($_SESSION['user_id']) ? $baseUrl . '/painel/' : 'javascript:openAuthModal()'; ?>
        <a href="<?= $perfilLink ?>" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span class="text-[10px] font-medium">Perfil</span>
        </a>
    </div>
</nav>

<!-- Main content push down for fixed nav -->
<div class="pt-16 flex-grow">

<!-- ========================= MODAIS GLOBAIS DE AUTENTICAÇÃO ========================= -->
<!-- Modal 1: Autenticação (E-mail First) -->
<div id="authModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeAuthModal()"></div>
    
    <div class="relative w-full max-w-md bg-sup1 border border-borda rounded-2xl shadow-2xl flex flex-col transform transition-all" style="animation: modalIn 0.2s ease-out both;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-xl font-bold text-texto" id="auth-modal-title">Entrar ou Cadastrar</h3>
                <button onclick="closeAuthModal()" class="text-secundario hover:text-white transition p-1 rounded-lg hover:bg-sup2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Step 1: Email -->
            <div id="auth-step-email" class="space-y-4">
                <p class="text-secundario text-sm mb-4">Informe seu e-mail para entrar ou criar uma nova conta.</p>
                <div>
                    <label class="block text-secundario mb-1 font-medium">E-mail</label>
                    <input type="email" id="auth_email_input" placeholder="seu@email.com" class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
                </div>
                <div id="auth-email-error" class="hidden text-red-400 text-sm mt-1"></div>
                <button onclick="submitEmailStep()" id="btn-submit-email" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded-lg transition shadow-lg mt-4 flex items-center justify-center gap-2">
                    <span>Continuar</span>
                </button>
            </div>

            <!-- Step 2: Password (Registered) -->
            <div id="auth-step-password" class="space-y-4 hidden">
                <p class="text-secundario text-sm mb-4">Bem-vindo(a) de volta! Digite sua senha.</p>
                <form action="<?= $baseUrl ?>/login/index.php" method="POST" class="space-y-4">
                    <input type="hidden" name="email" id="auth_hidden_email">
                    <div>
                        <label class="block text-secundario mb-1 font-medium">Senha</label>
                        <div class="relative">
                            <input type="password" name="password" required class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition pr-10">
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center">
                            <input id="modal_remember_me" name="remember_me" type="checkbox" class="h-4 w-4 accent-brand rounded bg-sup2 border-borda">
                            <label for="modal_remember_me" class="ml-2 block text-sm text-secundario">Lembrar-me</label>
                        </div>
                        <a href="<?= $baseUrl ?>/login/esqueci-senha/" class="text-sm text-brand hover:underline">Esqueceu a senha?</a>
                    </div>
                    <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-3 rounded-lg transition shadow-lg mt-4">Entrar</button>
                </form>
                <button onclick="goToStep('email')" class="w-full text-secundario hover:text-white mt-4 text-sm">Voltar</button>
            </div>

            <!-- Step 3: Unregistered choice -->
            <div id="auth-step-unregistered" class="space-y-4 hidden">
                <p class="text-secundario text-sm mb-4">Parece que você ainda não tem cadastro. Como deseja usar a plataforma?</p>
                
                <a id="link-register-advertiser" href="<?= $baseUrl ?>/cadastro/?role=advertiser" class="group relative flex items-center gap-4 p-4 rounded-xl border border-borda bg-sup2 hover:border-brand hover:bg-brand/5 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-brand/10 flex items-center justify-center group-hover:bg-brand/20 transition shrink-0">
                        <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    </div>
                    <div>
                        <p class="text-texto font-bold">Fazer Anúncio (Anunciante)</p>
                        <p class="text-secundario text-xs mt-0.5">Quero oferecer meus serviços.</p>
                    </div>
                </a>

                <a id="link-register-client" href="<?= $baseUrl ?>/cadastro/?role=client" class="group relative flex items-center gap-4 p-4 rounded-xl border border-borda bg-sup2 hover:border-blue-500 hover:bg-blue-500/5 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition shrink-0">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-texto font-bold">Visitante (CADASTRAR)</p>
                        <p class="text-secundario text-xs mt-0.5">Benefícios: recebe novidades, pode compartilhar, salvar, favoritar, denunciar perfis.</p>
                    </div>
                </a>
                
                <button onclick="goToStep('email')" class="w-full text-secundario hover:text-white mt-4 text-sm">Voltar</button>
            </div>
            
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script>
function openAuthModal() {
    document.getElementById('authModal').classList.remove('hidden');
    goToStep('email');
    document.getElementById('auth_email_input').value = '';
    document.getElementById('auth-email-error').classList.add('hidden');
}

function closeAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
}

function goToStep(step) {
    document.getElementById('auth-step-email').classList.add('hidden');
    document.getElementById('auth-step-password').classList.add('hidden');
    document.getElementById('auth-step-unregistered').classList.add('hidden');
    
    document.getElementById('auth-step-' + step).classList.remove('hidden');
    
    if (step === 'email') {
        document.getElementById('auth-modal-title').innerText = 'Entrar ou Cadastrar';
    } else if (step === 'password') {
        document.getElementById('auth-modal-title').innerText = 'Fazer Login';
    } else if (step === 'unregistered') {
        document.getElementById('auth-modal-title').innerText = 'Criar Conta';
    }
}

async function submitEmailStep() {
    const emailInput = document.getElementById('auth_email_input').value;
    const errorDiv = document.getElementById('auth-email-error');
    const btn = document.getElementById('btn-submit-email');
    
    if (!emailInput || !emailInput.includes('@')) {
        errorDiv.innerText = 'Por favor, informe um e-mail válido.';
        errorDiv.classList.remove('hidden');
        return;
    }
    errorDiv.classList.add('hidden');
    
    btn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-3 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Verificando...';
    btn.disabled = true;
    
    try {
        const response = await fetch('<?= $baseUrl ?>/login/check_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: emailInput })
        });
        const data = await response.json();
        
        btn.innerHTML = '<span>Continuar</span>';
        btn.disabled = false;
        
        if (data.status === 'registered') {
            document.getElementById('auth_hidden_email').value = emailInput;
            goToStep('password');
        } else if (data.status === 'unregistered') {
            document.getElementById('link-register-advertiser').href = '<?= $baseUrl ?>/cadastro/?role=advertiser&email=' + encodeURIComponent(emailInput);
            document.getElementById('link-register-client').href = '<?= $baseUrl ?>/cadastro/?role=client&email=' + encodeURIComponent(emailInput);
            goToStep('unregistered');
        } else {
            errorDiv.innerText = data.message || 'Ocorreu um erro. Tente novamente.';
            errorDiv.classList.remove('hidden');
        }
    } catch (err) {
        btn.innerHTML = '<span>Continuar</span>';
        btn.disabled = false;
        errorDiv.innerText = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    }
}

function openRegisterModal() {
    document.getElementById('authModal').classList.remove('hidden');
    goToStep('unregistered');
}
</script>
