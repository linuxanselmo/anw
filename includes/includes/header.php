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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
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
                <a href="<?= $baseUrl ?>/anuncios/" class="text-gray-300 hover:text-white transition">Anúncios</a>
                <a href="<?= $baseUrl ?>/categorias/" class="text-gray-300 hover:text-white transition">Categorias</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['user_role'] === 'admin'): ?>
                        <a href="<?= $baseUrl ?>/admin/" class="text-brand hover:text-white font-bold transition">Admin</a>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>/painel/" class="text-gray-300 hover:text-white transition">Meu Painel</a>
                    <a href="<?= $baseUrl ?>/painel/configuracoes.php" class="text-gray-300 hover:text-white transition">Configurações</a>
                    <a href="<?= $baseUrl ?>/sair/" class="text-gray-300 hover:text-brand transition">Sair</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/login/" class="text-gray-300 hover:text-white transition">Entrar</a>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>/painel/criar.php" class="bg-brand bg-brand-hover text-white px-4 py-2 rounded-full font-medium transition shadow-lg shadow-rose-900/50">Anuncie Aqui</a>
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
            <a href="<?= $baseUrl ?>/painel/criar.php" class="absolute -top-8 flex items-center justify-center w-14 h-14 bg-brand rounded-full shadow-[0_0_15px_rgba(233,30,99,0.5)] text-white hover:scale-105 transition transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </a>
        </div>
        
        <!-- Categorias -->
        <a href="<?= $baseUrl ?>/categorias/" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            <span class="text-[10px] font-medium">Categorias</span>
        </a>
        
        <!-- Perfil -->
        <?php $perfilLink = isset($_SESSION['user_id']) ? $baseUrl . '/painel/' : $baseUrl . '/login/'; ?>
        <a href="<?= $perfilLink ?>" class="flex flex-col items-center justify-center w-full text-secundario hover:text-brand transition">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span class="text-[10px] font-medium">Perfil</span>
        </a>
    </div>
</nav>

<!-- Main content push down for fixed nav -->
<div class="pt-16 flex-grow">
