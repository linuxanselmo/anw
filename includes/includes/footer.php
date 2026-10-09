</div>
<!-- Footer -->
<footer class="bg-[#0a0a0b] py-8 border-t border-gray-900 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <a href="<?= $baseUrl ?>/" class="inline-block mb-2">
                    <img src="<?= $baseUrl ?>/img/logo.png" alt="ANW.com.br" class="h-12 w-auto opacity-80 hover:opacity-100 transition drop-shadow-md">
                </a>
                <p class="text-gray-500 text-sm mt-2">&copy; <?= date('Y') ?> Acompanhantes na Web. Todos os direitos reservados.</p>
                <p class="text-gray-600 text-xs mt-1">Plataforma destinada a maiores de 18 anos.</p>
            </div>
            <div class="flex space-x-6">
                <a href="<?= $baseUrl ?>/pages/terms.php" class="text-gray-500 hover:text-white transition">Termos</a>
                <a href="<?= $baseUrl ?>/pages/privacy.php" class="text-gray-500 hover:text-white transition">Privacidade</a>
                <a href="<?= $baseUrl ?>/pages/contact.php" class="text-gray-500 hover:text-white transition">Contato</a>
            </div>
        </div>
    </div>
</footer>

<!-- 18+ Modal (MVP basic version) -->
<div id="age-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-sm">
    <div class="bg-[#18181b] p-8 rounded-2xl max-w-md text-center border border-gray-800 shadow-2xl">
        <h2 class="text-3xl font-bold text-brand mb-4">Aviso Importante</h2>
        <p class="text-gray-300 mb-6">Este site contém conteúdo adulto explícito. Você deve ter 18 anos ou mais para acessar.</p>
        <div class="flex space-x-4 justify-center">
            <button onclick="acceptTerms()" class="bg-brand bg-brand-hover text-white px-6 py-3 rounded-lg font-bold w-full transition">Tenho +18 Anos</button>
        </div>
        <div class="mt-4">
            <a href="https://google.com" class="text-gray-500 text-sm hover:text-white underline">Sou menor de idade (Sair)</a>
        </div>
    </div>
</div>

<script>
    function acceptTerms() {
        document.getElementById('age-modal').style.display = 'none';
        sessionStorage.setItem('age_verified', 'true');
    }
    
    window.onload = function() {
        if(sessionStorage.getItem('age_verified')) {
            document.getElementById('age-modal').style.display = 'none';
        }
    }
</script>
</body>
</html>
