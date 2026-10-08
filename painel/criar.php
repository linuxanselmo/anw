<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$baseUrl}/login/");
    exit;
}

if ($_SESSION['user_role'] === 'advertiser') {
    $stmtStatus = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtStatus->execute([$_SESSION['user_id']]);
    $userStatus = $stmtStatus->fetchColumn();
    if ($userStatus !== 'active' && $userStatus !== 'pending_approval') {
        header("Location: {$baseUrl}/painel/verificacao.php");
        exit;
    }
} else if ($_SESSION['user_role'] === 'client') {
    // Clientes não podem criar anúncios
    header("Location: {$baseUrl}/painel/");
    exit;
}

// Limitar a 1 anúncio por anunciante
$stmtCountAd = $pdo->prepare("SELECT COUNT(*) FROM ads WHERE user_id = ?");
$stmtCountAd->execute([$_SESSION['user_id']]);
if ($stmtCountAd->fetchColumn() > 0) {
    header("Location: {$baseUrl}/painel/?msg=limit_reached");
    exit;
}

$error = null;

// Fetch categories for the form
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "Erro: O tamanho total dos arquivos enviados excede o limite permitido pelo servidor.";
    } else {
        $userId = $_SESSION['user_id'];
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $phone = preg_replace('/\D/', '', isset($_POST['phone']) ? $_POST['phone'] : '');
    $state = isset($_POST['state']) ? $_POST['state'] : '';
    $cities = isset($_POST['cities']) ? $_POST['cities'] : [];
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $available_now = isset($_POST['available_now']) ? 1 : 0;
    $attends_to = isset($_POST['attends_to']) ? implode(', ', $_POST['attends_to']) : '';
    $payment_methods = isset($_POST['payment_methods']) ? implode(', ', $_POST['payment_methods']) : '';
    $service_locations = isset($_POST['service_locations']) ? implode(', ', $_POST['service_locations']) : '';
    $plan = isset($_POST['plan']) ? $_POST['plan'] : 'padrao';
    $service_type = isset($_POST['service_type']) ? $_POST['service_type'] : 'both';
    
    $characteristics = isset($_POST['characteristics']) && is_array($_POST['characteristics']) ? json_encode($_POST['characteristics']) : null;

    if (empty($title) || empty($phone)) {
        $error = "Preencha os campos obrigatórios.";
    } elseif ($service_type !== 'video' && (empty($state) || empty($cities))) {
        $error = "Preencha o estado e a cidade.";
    } else {
        try {
            try {
                $pdo->exec("ALTER TABLE ads MODIFY COLUMN status ENUM('active', 'inactive', 'pending_approval', 'rejected', 'pending') DEFAULT 'active'");
                $pdo->exec("ALTER TABLE ads ADD COLUMN plan VARCHAR(50) DEFAULT 'organic'");
                $pdo->exec("ALTER TABLE ads ADD COLUMN service_type ENUM('video', 'program', 'both') DEFAULT 'both'");
            } catch (\Exception $e) {}

            $pdo->beginTransaction();

            $adStatus = 'pending_approval';

            $stmt = $pdo->prepare("INSERT INTO ads (user_id, title, description, age, price, phone, attends_to, payment_methods, service_locations, characteristics, available_now, status, plan, service_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $description, $age, $price, $phone, $attends_to, $payment_methods, $service_locations, $characteristics, $available_now, $adStatus, $plan, $service_type]);
            $adId = $pdo->lastInsertId();

            if (!empty($cities)) {
                $stmtLoc = $pdo->prepare("INSERT INTO ad_locations (ad_id, state, city) VALUES (?, ?, ?)");
                foreach ($cities as $city) {
                    $stmtLoc->execute([$adId, $state, $city]);
                }
            }

            if (!empty($selectedCategories)) {
                $stmtCat = $pdo->prepare("INSERT INTO ad_categories (ad_id, category_id) VALUES (?, ?)");
                foreach ($selectedCategories as $catId) {
                    $stmtCat->execute([$adId, $catId]);
                }
            }

            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $stmtMedia = $pdo->prepare("INSERT INTO ad_media (ad_id, media_type, file_path, is_primary) VALUES (?, ?, ?, ?)");

            if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['cover_photo']['tmp_name'];
                $fileName = time() . '_cover_' . rand(1000, 9999) . '_' . basename($_FILES['cover_photo']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmtMedia->execute([$adId, 'image', $baseUrl . '/uploads/' . $fileName, 1]);
                }
            }

            if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $fileCount = min(count($_FILES['photos']['name']), 30);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['photos']['tmp_name'][$i];
                        $fileName = time() . '_photo_' . rand(1000, 9999) . '_' . basename($_FILES['photos']['name'][$i]);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $stmtMedia->execute([$adId, 'image', $baseUrl . '/uploads/' . $fileName, 0]);
                        }
                    }
                }
            }

            if (isset($_FILES['videos']) && !empty($_FILES['videos']['name'][0])) {
                $fileCount = min(count($_FILES['videos']['name']), 5);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['videos']['tmp_name'][$i];
                        $fileName = time() . '_video_' . rand(1000, 9999) . '_' . basename($_FILES['videos']['name'][$i]);
                        $targetPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $stmtMedia->execute([$adId, 'video', $baseUrl . '/uploads/' . $fileName, 0]);
                        }
                    }
                }
            }

            $pdo->commit();
            header("Location: {$baseUrl}/painel/sucesso.php?id=" . $adId);
            exit;

        } catch (\Exception $e) {
            try { $pdo->rollBack(); } catch (\Exception $ex) {}
            $error = "Ocorreu um erro ao salvar o anúncio: " . $e->getMessage();
        }
    }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-texto mb-8">Criar Novo Anúncio</h1>

    <?php if($error): ?>
        <div class="bg-red-500/20 border border-red-500 text-red-300 p-4 rounded mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8 bg-sup1 p-8 rounded-2xl border border-borda">
        
        <!-- Info Básica -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Informações Básicas</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-secundario mb-1">Tipo de Atendimento *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="service_type" value="program" class="peer sr-only" onchange="toggleLocationFields()" checked>
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Presencial</span>
                                <span class="text-xs text-secundario">Atendimento com local</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="service_type" value="video" class="peer sr-only" onchange="toggleLocationFields()">
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Chamada de Vídeo</span>
                                <span class="text-xs text-secundario">Atendimento virtual</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="service_type" value="both" class="peer sr-only" onchange="toggleLocationFields()">
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Ambos</span>
                                <span class="text-xs text-secundario">Presencial e Virtual</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="md:col-span-2 mt-2">
                    <label class="block text-secundario mb-1">Título do Anúncio *</label>
                    <input type="text" name="title" required placeholder="Ex: Loira carinhosa com local" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                </div>
                

                <div class="md:col-span-2">
                    <label class="block text-secundario mb-1">Descrição</label>
                    <textarea name="description" rows="4" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition"></textarea>
                </div>
                <div>
                    <label class="block text-secundario mb-1">Idade</label>
                    <input type="number" name="age" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                </div>
                <div>
                    <label class="block text-secundario mb-1">Valor (Cachê Base)</label>
                    <input type="number" step="0.01" name="price" placeholder="R$" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Contato</h2>
            <div class="md:col-span-2">
                <label class="block text-secundario mb-1">WhatsApp / Telefone *</label>
                <input type="text" name="phone" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
            </div>
        </div>

        <!-- Localização -->
        <div id="location_section">
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Localização</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div id="state_container">
                    <label class="block text-secundario mb-1">Estado (UF) *</label>
                    <select id="state_select" name="state" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                        <option value="">Selecione o Estado...</option>
                    </select>
                </div>
                <div id="city_container">
                    <label class="block text-secundario mb-1">Cidades de Atendimento *</label>
                    
                    <!-- Select dropdown -->
                    <select id="city_select" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                        <option value="" disabled selected>Selecione o estado primeiro</option>
                    </select>

                    <!-- Area to hold the selected tags/badges -->
                    <div id="selected_cities_container" class="flex flex-wrap gap-2 mt-3"></div>
                    
                    <!-- Hidden container to store the actual form data array -->
                    <div id="hidden_cities_inputs"></div>
                    
                    <input type="hidden" id="cities_validator" name="cities_validator" />
                </div>
            </div>
        </div>
        
        <!-- Script to hide/show location -->
        <script>
            function toggleLocationFields() {
                const serviceType = document.querySelector('input[name="service_type"]:checked').value;
                const locationSection = document.getElementById('location_section');
                const stateSelect = document.getElementById('state_select');
                const citySelect = document.getElementById('city_select');
                const videoCallSection = document.getElementById('video_call_section');
                const availableNowCheckbox = document.getElementById('available_now_checkbox');
                
                if (serviceType === 'video') {
                    locationSection.style.display = 'none';
                    stateSelect.removeAttribute('required');
                    citySelect.removeAttribute('required');
                    
                    videoCallSection.style.display = 'none';
                    availableNowCheckbox.checked = true;
                } else if (serviceType === 'both') {
                    locationSection.style.display = 'block';
                    stateSelect.setAttribute('required', 'required');
                    
                    videoCallSection.style.display = 'none';
                    availableNowCheckbox.checked = true;
                } else {
                    locationSection.style.display = 'block';
                    stateSelect.setAttribute('required', 'required');
                    
                    videoCallSection.style.display = 'block';
                    availableNowCheckbox.checked = false;
                }
            }
            // Run on init
            document.addEventListener("DOMContentLoaded", function() {
                toggleLocationFields();
            });
        </script>

        <!-- Media Upload -->
        <!-- Media Upload UX -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Fotos e Vídeos</h2>
            <div class="grid grid-cols-1 gap-6">
                
                <!-- Foto de Capa -->
                <div class="bg-sup2 border border-brand/30 rounded-lg p-5">
                    <label class="block text-brand font-bold mb-2">Foto de Capa (Principal) *</label>
                    <p class="text-secundario text-sm mb-3">Esta será a foto principal do seu anúncio na página inicial.</p>
                    
                    <div class="relative border-2 border-dashed border-brand/50 hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" id="dropzone_cover" onclick="document.getElementById('input_cover').click()">
                        <input type="file" id="input_cover" name="cover_photo" accept="image/*" class="hidden" onchange="handleFileSelect(event, 'preview_cover', true)">
                        <svg class="w-10 h-10 text-brand mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-brand font-bold block">Clique ou arraste a Foto de Capa</span>
                        <span class="text-secundario text-sm block mt-1">Apenas imagens (JPG, PNG)</span>
                    </div>
                    <div id="preview_cover" class="mt-4 flex flex-wrap gap-4"></div>
                </div>

                <!-- Galeria de Fotos Extras -->
                <div class="bg-sup2 border border-borda rounded-lg p-5">
                    <label class="block text-texto font-bold mb-2">Galeria de Fotos Extras</label>
                    <p class="text-secundario text-sm mb-3">Adicione fotos para o seu perfil. Você pode selecionar várias ou adicionar uma por uma.</p>
                    
                    <div class="relative border-2 border-dashed border-borda hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" onclick="document.getElementById('input_photos').click()">
                        <input type="file" id="input_photos" accept="image/*" multiple class="opacity-0 absolute w-1 h-1 -z-10" tabindex="-1" onchange="handleFileSelect(event, 'preview_photos', false)">
                        <svg class="w-10 h-10 text-secundario mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span class="text-texto font-bold block">Clique ou arraste para adicionar fotos</span>
                    </div>
                    <input type="file" id="real_input_photos" name="photos[]" multiple class="hidden">
                    <div id="preview_photos" class="mt-4 flex flex-wrap gap-4"></div>
                </div>

                <!-- Galeria de Vídeos -->
                <div class="bg-sup2 border border-borda rounded-lg p-5">
                    <label class="block text-texto font-bold mb-2">Galeria de Vídeos</label>
                    <p class="text-secundario text-sm mb-3">Adicione vídeos curtos ao seu perfil.</p>
                    
                    <div class="relative border-2 border-dashed border-borda hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" onclick="document.getElementById('input_videos').click()">
                        <input type="file" id="input_videos" accept="video/*" multiple class="opacity-0 absolute w-1 h-1 -z-10" tabindex="-1" onchange="handleFileSelect(event, 'preview_videos', false, true)">
                        <svg class="w-10 h-10 text-secundario mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        <span class="text-texto font-bold block">Clique ou arraste para adicionar vídeos</span>
                    </div>
                    <input type="file" id="real_input_videos" name="videos[]" multiple class="hidden">
                    <div id="preview_videos" class="mt-4 flex flex-wrap gap-4"></div>
                </div>

            </div>
        </div>

        <!-- Categorias (Tags) -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Categorias e Tags</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach($categories as $cat): ?>
                    <label class="flex items-center space-x-2 text-texto cursor-pointer">
                        <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" class="form-checkbox bg-sup2 border-borda text-brand rounded focus:ring-brand">
                        <span><?= htmlspecialchars($cat['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Plano de Destaque -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Plano de Destaque</h2>
            <div class="bg-sup2 border border-brand/30 p-5 rounded-lg">
                <p class="text-secundario text-sm mb-4">Escolha um plano de destaque para o seu anúncio. Isso ajudará você a aparecer nas primeiras posições e ter mais visibilidade!</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="organic" class="peer sr-only" checked>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-brand peer-checked:border-brand peer-checked:ring-1 peer-checked:ring-brand peer-checked:bg-brand/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-texto">Anúncios</span>
                                <span class="mt-1 text-sm text-secundario block">Sem destaque adicional nas buscas</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="paid_highlight" class="peer sr-only">
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#cd7f32] peer-checked:border-[#cd7f32] peer-checked:ring-1 peer-checked:ring-[#cd7f32] peer-checked:bg-[#cd7f32]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#cd7f32]">⭐ Destaques</span>
                                <span class="mt-1 text-sm text-secundario block">Destaque leve e borda especial</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="super_highlight" class="peer sr-only">
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#c0c0c0] peer-checked:border-[#c0c0c0] peer-checked:ring-1 peer-checked:ring-[#c0c0c0] peer-checked:bg-[#c0c0c0]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#c0c0c0]">⭐⭐ Super Destaques</span>
                                <span class="mt-1 text-sm text-secundario block">Mais visibilidade na tela inicial</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="super_top" class="peer sr-only">
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#ffd700] peer-checked:border-[#ffd700] peer-checked:ring-1 peer-checked:ring-[#ffd700] peer-checked:bg-[#ffd700]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#ffd700]">💎 Super Top</span>
                                <span class="mt-1 text-sm text-secundario block">Destaque premium e alto nas buscas</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="ultra_top" class="peer sr-only">
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#00bfff] peer-checked:border-[#00bfff] peer-checked:ring-1 peer-checked:ring-[#00bfff] peer-checked:bg-[#00bfff]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#00bfff]">👑 Ultra Top</span>
                                <span class="mt-1 text-sm text-secundario block">Visibilidade máxima no topo do site!</span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Chamada de Vídeo -->
        <div id="video_call_section">
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Chamadas de Vídeo</h2>
            <div class="bg-brand/10 border border-brand/30 p-5 rounded-lg flex items-center justify-between">
                <div>
                    <h3 class="text-texto font-bold text-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        Aceita Chamadas de Vídeo?
                    </h3>
                    <p class="text-secundario text-sm">Marque esta opção se você realiza atendimentos por chamada de vídeo.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="available_now_checkbox" name="available_now" value="1" class="sr-only peer">
                    <div class="w-14 h-7 bg-sup2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-brand border border-borda"></div>
                </label>
            </div>
        </div>


        <div class="pt-6 border-t border-borda">
            <!-- Additional Info (Checkboxes) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <div>
                <label class="block text-secundario mb-2">Quem você atende?</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Homens" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Homens
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Mulheres" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Mulheres
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Casais" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Casais
                    </label>
                </div>
            </div>
            
            <div>
                <label class="block text-secundario mb-2">Formas de Pagamento</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Pix" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Pix
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Dinheiro" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Dinheiro
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Cartão de Crédito" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand">
                        Cartão de Crédito
                    </label>
                </div>
            </div>


        </div>

        <!-- Características e Serviços -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Posição e serviços</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php 
                $extra_characteristics = [
                    'Atendimento 24h', 'Faz anal', 'Dupla penetração'
                ];
                foreach ($extra_characteristics as $char): 
                ?>
                <label class="flex items-center gap-3 p-3 bg-sup2 border border-borda rounded-lg cursor-pointer hover:border-brand transition">
                    <input type="checkbox" name="characteristics[]" value="<?= htmlspecialchars($char) ?>" class="w-5 h-5 bg-sup1 border-borda rounded text-brand focus:ring-brand">
                    <span class="text-texto font-medium text-sm"><?= htmlspecialchars($char) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

            <div class="pt-6 flex justify-end">
            <button type="submit" id="submit_btn" class="bg-brand text-white font-bold py-3 px-8 rounded-lg hover:bg-brand/90 transition shadow-lg shadow-brand/20">
                Publicar Anúncio
            </button>
        </div>
        </div>
    </form>
</div>

<!-- Custom Form Validation -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const coverInput = document.getElementById('input_cover');
        const citiesInput = document.getElementById('cities_validator');
        const stateSelect = document.getElementById('state_select');
        
        const serviceType = document.querySelector('input[name="service_type"]:checked')?.value;
        
        if (serviceType !== 'video') {
            if (!stateSelect.value) {
                e.preventDefault();
                alert("Por favor, selecione um Estado.");
                stateSelect.focus();
                return;
            }

            if (!citiesInput.value) {
                e.preventDefault();
                alert("Por favor, adicione pelo menos uma Cidade de Atendimento.");
                return;
            }
        }

        if (coverInput.files.length === 0) {
            e.preventDefault();
            alert("Por favor, adicione uma Foto de Capa (Principal).");
            document.getElementById('dropzone_cover').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    });
});
</script>

<!-- IBGE API Script & City Tags Logic -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const stateSelect = document.getElementById('state_select');
    const citySelect = document.getElementById('city_select');
    const tagsContainer = document.getElementById('selected_cities_container');
    const inputsContainer = document.getElementById('hidden_cities_inputs');
    const validator = document.getElementById('cities_validator');
    
    // Store selected cities locally in memory
    let selectedCities = [];

    // Function to render the tags
    function renderCities() {
        tagsContainer.innerHTML = '';
        inputsContainer.innerHTML = '';
        
        selectedCities.forEach((city, index) => {
            // Render Tag
            const tag = document.createElement('div');
            tag.className = "flex items-center gap-1 bg-brand/20 text-brand border border-brand/40 px-3 py-1.5 rounded-full text-sm font-semibold animate-fade-in";
            tag.innerHTML = `
                ${city}
                <button type="button" class="text-brand hover:text-white transition ml-1" data-index="${index}">&times;</button>
            `;
            tagsContainer.appendChild(tag);
            
            // Render Hidden Input
            const hidden = document.createElement('input');
            hidden.type = "hidden";
            hidden.name = "cities[]";
            hidden.value = city;
            inputsContainer.appendChild(hidden);
        });

        // Add remove event listeners to the X buttons
        tagsContainer.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = parseInt(e.currentTarget.getAttribute('data-index'));
                selectedCities.splice(idx, 1);
                renderCities();
            });
        });

        // Validate
        validator.value = selectedCities.length > 0 ? 'valid' : '';
    }

    // Handle City Selection
    citySelect.addEventListener('change', (e) => {
        const city = e.target.value;
        if (city && !selectedCities.includes(city)) {
            selectedCities.push(city);
            renderCities();
        }
        // Reset select back to placeholder
        e.target.selectedIndex = 0;
    });

    // Fetch States
    fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome')
        .then(response => response.json())
        .then(states => {
            states.forEach(state => {
                const option = document.createElement('option');
                option.value = state.sigla; // UF
                option.textContent = state.nome;
                stateSelect.appendChild(option);
            });
        })
        .catch(error => console.error("Erro ao carregar estados:", error));

    // Fetch Cities on State change
    stateSelect.addEventListener('change', () => {
        const uf = stateSelect.value;
        citySelect.innerHTML = '';
        
        // Clear selected cities when state changes
        selectedCities = [];
        renderCities();
        
        if (!uf) {
            citySelect.innerHTML = '<option value="" disabled selected>Selecione o estado primeiro</option>';
            return;
        }

        citySelect.innerHTML = '<option value="" disabled selected>Carregando cidades...</option>';

        fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`)
            .then(response => response.json())
            .then(cities => {
                citySelect.innerHTML = '<option value="" disabled selected>Clique para adicionar uma cidade...</option>';
                cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.nome;
                    option.textContent = city.nome;
                    citySelect.appendChild(option);
                });
            })
            .catch(error => console.error("Erro ao carregar cidades:", error));
    });
});

// Media Upload UX Logic
const filesData = {
    'preview_photos': new DataTransfer(),
    'preview_videos': new DataTransfer()
};

const MAX_PHOTOS = 30;
const MAX_VIDEOS = 5;

function handleFileSelect(event, previewContainerId, isSingle = false, isVideo = false) {
    const files = event.target.files;
    const previewContainer = document.getElementById(previewContainerId);
    
    if (isSingle) {
        previewContainer.innerHTML = ''; // Clear for single files (cover)
    }

    // For multiple files, we append to our DataTransfer object
    let dt = filesData[previewContainerId];
    
    // Calculate how many slots are remaining
    const currentCount = isSingle ? 0 : dt.items.length;
    const maxAllowed = isSingle ? 1 : (isVideo ? MAX_VIDEOS : MAX_PHOTOS);
    let slotsRemaining = maxAllowed - currentCount;
    
    if (!isSingle && slotsRemaining <= 0) {
        alert(`Você já atingiu o limite máximo de ${maxAllowed} ${isVideo ? 'vídeos' : 'fotos'}.`);
        event.target.value = '';
        return;
    }

    let filesToAdd = files.length;
    if (!isSingle && files.length > slotsRemaining) {
        alert(`Você tentou adicionar ${files.length} arquivos, mas só tem espaço para mais ${slotsRemaining} ${isVideo ? 'vídeos' : 'fotos'}. O excedente foi ignorado.`);
        filesToAdd = slotsRemaining;
    }

    for (let i = 0; i < filesToAdd; i++) {
        const file = files[i];
        
        if (!isSingle) {
            dt.items.add(file);
        }

        const reader = new FileReader();
        
        reader.onload = function(e) {
            const wrap = document.createElement('div');
            wrap.className = "relative w-24 h-24 rounded-lg overflow-hidden border border-borda group bg-sup1 flex items-center justify-center";
            
            if (isVideo) {
                wrap.innerHTML = `
                    <svg class="w-8 h-8 text-brand opacity-80" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path></svg>
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <button type="button" class="text-white hover:text-brand" onclick="removeFile(this, '${file.name}', '${previewContainerId}', '${isSingle}')">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                `;
            } else {
                wrap.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <button type="button" class="text-white hover:text-brand" onclick="removeFile(this, '${file.name}', '${previewContainerId}', '${isSingle}')">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                `;
            }
            previewContainer.appendChild(wrap);
        }
        
        if (file) {
            reader.readAsDataURL(file);
        }
    }

    // Sync back to hidden real inputs for form submission
    if (!isSingle) {
        if (previewContainerId === 'preview_photos') {
            document.getElementById('real_input_photos').files = dt.files;
        } else if (previewContainerId === 'preview_videos') {
            document.getElementById('real_input_videos').files = dt.files;
        }
        // Clear the click-input so it can fire onchange again for the same file if needed
        event.target.value = '';
    }
}

function removeFile(btnElement, fileName, previewContainerId, isSingle) {
    const wrap = btnElement.closest('.relative');
    wrap.remove();

    if (isSingle === 'true') {
        document.getElementById('input_cover').value = '';
    } else {
        const dt = filesData[previewContainerId];
        const newDt = new DataTransfer();
        
        for (let i = 0; i < dt.files.length; i++) {
            if (dt.files[i].name !== fileName) {
                newDt.items.add(dt.files[i]);
            }
        }
        filesData[previewContainerId] = newDt;
        
        if (previewContainerId === 'preview_photos') {
            document.getElementById('real_input_photos').files = newDt.files;
        } else if (previewContainerId === 'preview_videos') {
            document.getElementById('real_input_videos').files = newDt.files;
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
