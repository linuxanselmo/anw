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
                <a href="<?= $baseUrl ?>/pages/cookies.php" class="text-gray-500 hover:text-white transition">Cookies</a>
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

<!-- LGPD Cookie Modal -->
<div id="lgpd-modal" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm hidden">
    <div class="bg-[#18181b] p-8 rounded-2xl max-w-md w-full mx-4 text-center border border-gray-800 shadow-2xl">
        <div class="mb-4 flex justify-center">
            <svg class="w-12 h-12 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-4">Privacidade e Cookies</h2>
        <p class="text-gray-300 text-sm mb-6 leading-relaxed">
            Nós utilizamos cookies e tecnologias semelhantes para melhorar a sua experiência, personalizar anúncios e analisar nosso tráfego. Ao continuar navegando, você concorda com a nossa <a href="#" onclick="openPolicyModal('privacy')" class="text-brand hover:underline">Política de Privacidade</a> e <a href="#" onclick="openPolicyModal('cookies')" class="text-brand hover:underline">Política de Cookies</a>.
        </p>
        <button onclick="acceptCookies()" class="bg-brand bg-brand-hover text-white px-6 py-3 rounded-lg font-bold w-full transition">
            Entendi e Aceito
        </button>
    </div>
<!-- Policy Modals -->
<div id="policy-modal" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 backdrop-blur-sm hidden">
    <div class="bg-[#18181b] p-8 rounded-2xl max-w-3xl w-full mx-4 border border-gray-800 shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-6">
            <h2 id="policy-modal-title" class="text-2xl font-bold text-white">Política</h2>
            <button onclick="closePolicyModal()" class="text-gray-400 hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="policy-modal-content" class="text-gray-300 text-sm overflow-y-auto pr-4 space-y-4 prose prose-invert">
            <!-- Content injected via JS -->
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closePolicyModal()" class="bg-gray-800 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-bold transition">Fechar</button>
        </div>
    </div>
</div>

<script>
    const policyTexts = {
        'terms': `
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">1. Aceitação dos Termos</h3>
            <p>Ao utilizar nosso serviço, você confirma que tem pelo menos 18 anos de idade e concorda com todos os termos e condições aqui estabelecidos.</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">2. Uso do Serviço</h3>
            <p>O site é uma plataforma de anúncios. Não nos responsabilizamos pelo conteúdo dos anúncios ou por qualquer interação entre os usuários.</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">3. Modificações</h3>
            <p>Reservamo-nos o direito de modificar estes termos a qualquer momento. O uso contínuo do site após as alterações constitui a aceitação dos novos termos.</p>
        `,
        'privacy': `
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">1. Coleta de Dados</h3>
            <p>Coletamos informações que você nos fornece diretamente, como ao criar uma conta (nome, e-mail) e dados coletados automaticamente (como endereço IP e cookies).</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">2. Uso das Informações</h3>
            <p>Utilizamos seus dados para fornecer, manter e melhorar nossos serviços, além de garantir a segurança da plataforma e cumprir obrigações legais.</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">3. Seus Direitos (LGPD)</h3>
            <p>Você tem o direito de acessar, corrigir, anonimizar ou excluir seus dados pessoais. Para exercer esses direitos, entre em contato conosco.</p>
        `,
        'cookies': `
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">1. O que são cookies?</h3>
            <p>Cookies são pequenos arquivos de dados que são colocados no seu computador ou dispositivo móvel quando você visita um site.</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">2. Como usamos os cookies?</h3>
            <p>Usamos cookies para melhorar sua experiência em nosso site, incluindo lembrar suas preferências de consentimento, manter você conectado de forma segura e analisar o tráfego.</p>
            <h3 class="text-xl font-bold text-brand mt-4 mb-2">3. Controle de Cookies</h3>
            <p>Você tem o direito de decidir se aceita ou rejeita os cookies ajustando os controles do seu navegador.</p>
        `
    };
    
    const policyTitles = {
        'terms': 'Termos de Uso',
        'privacy': 'Política de Privacidade',
        'cookies': 'Política de Cookies'
    };

    function openPolicyModal(type) {
        document.getElementById('policy-modal-title').innerText = policyTitles[type];
        document.getElementById('policy-modal-content').innerHTML = policyTexts[type];
        document.getElementById('policy-modal').classList.remove('hidden');
        document.getElementById('policy-modal').classList.add('flex');
    }

    function closePolicyModal() {
        document.getElementById('policy-modal').classList.add('hidden');
        document.getElementById('policy-modal').classList.remove('flex');
    }

    function acceptTerms() {
        document.getElementById('age-modal').style.display = 'none';
        sessionStorage.setItem('age_verified', 'true');
    }
    
    function acceptCookies() {
        localStorage.setItem('lgpd_accepted', 'true');
        document.getElementById('lgpd-modal').classList.add('hidden');
        document.getElementById('lgpd-modal').classList.remove('flex');
    }

    window.onload = function() {
        if(sessionStorage.getItem('age_verified')) {
            document.getElementById('age-modal').style.display = 'none';
        }
        
        if(!localStorage.getItem('lgpd_accepted')) {
            document.getElementById('lgpd-modal').classList.remove('hidden');
            document.getElementById('lgpd-modal').classList.add('flex');
        }
    }
</script>
</body>
</html>
