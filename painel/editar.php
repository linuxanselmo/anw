<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$baseUrl}/login/");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: {$baseUrl}/painel/");
    exit;
}

$adId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Fetch the ad
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
    $stmt->execute([$adId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND user_id = ?");
    $stmt->execute([$adId, $userId]);
}
$ad = $stmt->fetch();

if (!$ad) {
    header("Location: {$baseUrl}/painel/");
    exit;
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$stmtCat = $pdo->prepare("SELECT category_id FROM ad_categories WHERE ad_id = ?");
$stmtCat->execute([$adId]);
$adCategories = $stmtCat->fetchAll(PDO::FETCH_COLUMN);

// Fetch locations
$stmtLoc = $pdo->prepare("SELECT state, city FROM ad_locations WHERE ad_id = ?");
$stmtLoc->execute([$adId]);
$locations = $stmtLoc->fetchAll();
$adState = !empty($locations) ? $locations[0]['state'] : '';
$adCities = array_column($locations, 'city');

// Fetch media
$stmtMedia = $pdo->prepare("SELECT id, media_type, file_path, is_primary FROM ad_media WHERE ad_id = ?");
$stmtMedia->execute([$adId]);
$mediaItems = $stmtMedia->fetchAll();

$coverPhoto = null;
$photos = [];
$videos = [];
foreach ($mediaItems as $media) {
    if ($media['is_primary']) {
        $coverPhoto = $media;
    } elseif ($media['media_type'] === 'image') {
        $photos[] = $media;
    } elseif ($media['media_type'] === 'video') {
        $videos[] = $media;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $error = "Erro: O tamanho total dos arquivos enviados excede o limite permitido pelo servidor.";
    } else {
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $phone = preg_replace('/\D/', '', $phone);
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
            $pdo->beginTransaction();

            $adStatus = 'pending_approval';

            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE ads SET title=?, description=?, age=?, price=?, phone=?, attends_to=?, payment_methods=?, service_locations=?, characteristics=?, available_now=?, status=?, plan=?, service_type=? WHERE id=?");
                $stmt->execute([$title, $description, $age, $price, $phone, $attends_to, $payment_methods, $service_locations, $characteristics, $available_now, $adStatus, $plan, $service_type, $adId]);
            } else {
                $stmt = $pdo->prepare("UPDATE ads SET title=?, description=?, age=?, price=?, phone=?, attends_to=?, payment_methods=?, service_locations=?, characteristics=?, available_now=?, status=?, plan=?, service_type=? WHERE id=? AND user_id=?");
                $stmt->execute([$title, $description, $age, $price, $phone, $attends_to, $payment_methods, $service_locations, $characteristics, $available_now, $adStatus, $plan, $service_type, $adId, $userId]);
            }

            // Update Locations
            $pdo->prepare("DELETE FROM ad_locations WHERE ad_id = ?")->execute([$adId]);
            if (!empty($cities)) {
                $stmtLoc = $pdo->prepare("INSERT INTO ad_locations (ad_id, state, city) VALUES (?, ?, ?)");
                foreach ($cities as $city) {
                    $stmtLoc->execute([$adId, $state, $city]);
                }
            }

            // Update Categories
            $pdo->prepare("DELETE FROM ad_categories WHERE ad_id = ?")->execute([$adId]);
            if (!empty($selectedCategories)) {
                $stmtCat = $pdo->prepare("INSERT INTO ad_categories (ad_id, category_id) VALUES (?, ?)");
                foreach ($selectedCategories as $catId) {
                    $stmtCat->execute([$adId, $catId]);
                }
            }

            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $stmtMediaIn = $pdo->prepare("INSERT INTO ad_media (ad_id, media_type, file_path, is_primary) VALUES (?, ?, ?, ?)");

            if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
                // Remove old cover
                if ($coverPhoto) {
                    $pdo->prepare("DELETE FROM ad_media WHERE id = ?")->execute([$coverPhoto['id']]);
                    $oldPath = __DIR__ . '/../uploads/' . basename($coverPhoto['file_path']);
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $tmpName = $_FILES['cover_photo']['tmp_name'];
                $fileName = time() . '_cover_' . rand(1000, 9999) . '_' . basename($_FILES['cover_photo']['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmtMediaIn->execute([$adId, 'image', $baseUrl . '/uploads/' . $fileName, 1]);
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
                            $stmtMediaIn->execute([$adId, 'image', $baseUrl . '/uploads/' . $fileName, 0]);
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
                            $stmtMediaIn->execute([$adId, 'video', $baseUrl . '/uploads/' . $fileName, 0]);
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

$adAttendsTo = $ad['attends_to'] ? explode(', ', $ad['attends_to']) : [];
$adPaymentMethods = $ad['payment_methods'] ? explode(', ', $ad['payment_methods']) : [];
$adCharacteristics = $ad['characteristics'] ? json_decode($ad['characteristics'], true) : [];
if (!is_array($adCharacteristics)) $adCharacteristics = [];
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-texto mb-8">Editar Anúncio</h1>

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
                            <input type="radio" name="service_type" value="program" class="peer sr-only" onchange="toggleLocationFields()" <?= $ad['service_type'] == 'program' ? 'checked' : '' ?>>
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Presencial</span>
                                <span class="text-xs text-secundario">Atendimento com local</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="service_type" value="video" class="peer sr-only" onchange="toggleLocationFields()" <?= $ad['service_type'] == 'video' ? 'checked' : '' ?>>
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Chamada de Vídeo</span>
                                <span class="text-xs text-secundario">Atendimento virtual</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="service_type" value="both" class="peer sr-only" onchange="toggleLocationFields()" <?= $ad['service_type'] == 'both' ? 'checked' : '' ?>>
                            <div class="rounded-lg border border-borda bg-sup2 p-3 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand/10 text-center">
                                <span class="block font-bold text-texto">Ambos</span>
                                <span class="text-xs text-secundario">Presencial e Virtual</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="md:col-span-2 mt-2">
                    <label class="block text-secundario mb-1">Título do Anúncio *</label>
                    <input type="text" name="title" required placeholder="Ex: Loira carinhosa com local" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition" value="<?= htmlspecialchars($ad['title']) ?>">
                </div>
                

                <div class="md:col-span-2">
                    <label class="block text-secundario mb-1">Descrição</label>
                    <textarea name="description" rows="4" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition"><?= htmlspecialchars($ad['description']) ?></textarea>
                </div>
                <div>
                    <label class="block text-secundario mb-1">Idade</label>
                    <input type="number" name="age" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition" value="<?= htmlspecialchars($ad['age']) ?>">
                </div>
                <div>
                    <label class="block text-secundario mb-1">Valor (Cachê Base)</label>
                    <input type="number" step="0.01" name="price" placeholder="R$" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition" value="<?= htmlspecialchars($ad['price']) ?>">
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Contato</h2>
            <div class="md:col-span-2">
                <label class="block text-secundario mb-1">WhatsApp / Telefone *</label>
                <input type="text" name="phone" required class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition" value="<?= htmlspecialchars($ad['phone']) ?>">
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
                        <!-- Options will be populated and selected by JS -->
                    </select>
                </div>
                <div id="city_container">
                    <label class="block text-secundario mb-1">Cidades de Atendimento *</label>
                    
                    <select id="city_select" class="w-full bg-sup2 border border-borda text-texto rounded px-4 py-3 focus:outline-none focus:border-brand transition">
                        <option value="" disabled selected>Selecione o estado primeiro</option>
                    </select>

                    <div id="selected_cities_container" class="flex flex-wrap gap-2 mt-3"></div>
                    <div id="hidden_cities_inputs"></div>
                    
                    <input type="hidden" id="cities_validator" name="cities_validator" />
                </div>
            </div>
        </div>
        
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
                    // keep current db value for available_now when both
                }
            }
            document.addEventListener("DOMContentLoaded", function() {
                toggleLocationFields();
            });
        </script>

        <!-- Media Upload -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Fotos e Vídeos</h2>
            <div class="grid grid-cols-1 gap-6">
                
                <!-- Foto de Capa -->
                <div class="bg-sup2 border border-brand/30 rounded-lg p-5">
                    <label class="block text-brand font-bold mb-2">Foto de Capa (Principal)</label>
                    <p class="text-secundario text-sm mb-3">Esta será a foto principal do seu anúncio na página inicial. Envie uma nova para substituir a atual.</p>
                    
                    <div class="relative border-2 border-dashed border-brand/50 hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" id="dropzone_cover" onclick="document.getElementById('input_cover').click()">
                        <input type="file" id="input_cover" name="cover_photo" accept="image/*" class="hidden" onchange="handleFileSelect(event, 'preview_cover', true)">
                        <svg class="w-10 h-10 text-brand mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-brand font-bold block">Clique ou arraste a nova Foto de Capa</span>
                        <span class="text-secundario text-sm block mt-1">Apenas imagens (JPG, PNG)</span>
                    </div>
                    <div id="preview_cover" class="mt-4 flex flex-wrap gap-4">
                        <?php if($coverPhoto): ?>
                            <div class="relative w-24 h-24 rounded-lg overflow-hidden border border-borda group bg-sup1 flex items-center justify-center">
                                <img src="<?= $coverPhoto['file_path'] ?>" class="w-full h-full object-cover">
                                <div class="absolute bottom-0 w-full bg-black/60 text-white text-xs text-center py-1">Atual</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Galeria de Fotos Extras -->
                <div class="bg-sup2 border border-borda rounded-lg p-5">
                    <label class="block text-texto font-bold mb-2">Galeria de Fotos Extras</label>
                    <p class="text-secundario text-sm mb-3">Suas fotos atuais. Adicione novas ou exclua existentes.</p>
                    
                    <div class="relative border-2 border-dashed border-borda hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" onclick="document.getElementById('input_photos').click()">
                        <input type="file" id="input_photos" accept="image/*" multiple class="opacity-0 absolute w-1 h-1 -z-10" tabindex="-1" onchange="handleFileSelect(event, 'preview_photos', false)">
                        <svg class="w-10 h-10 text-secundario mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span class="text-texto font-bold block">Clique ou arraste para adicionar fotos</span>
                    </div>
                    <input type="file" id="real_input_photos" name="photos[]" multiple class="hidden">
                    <div id="preview_photos" class="mt-4 flex flex-wrap gap-4">
                        <?php foreach($photos as $p): ?>
                            <div class="relative w-24 h-24 rounded-lg overflow-hidden border border-borda group bg-sup1 flex items-center justify-center" id="media_<?= $p['id'] ?>">
                                <img src="<?= $p['file_path'] ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <button type="button" class="text-white hover:text-brand" onclick="deleteExistingMedia(<?= $p['id'] ?>)">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Galeria de Vídeos -->
                <div class="bg-sup2 border border-borda rounded-lg p-5">
                    <label class="block text-texto font-bold mb-2">Galeria de Vídeos</label>
                    <p class="text-secundario text-sm mb-3">Seus vídeos atuais. Adicione novos ou exclua existentes.</p>
                    
                    <div class="relative border-2 border-dashed border-borda hover:border-brand rounded-xl p-8 text-center cursor-pointer transition" onclick="document.getElementById('input_videos').click()">
                        <input type="file" id="input_videos" accept="video/*" multiple class="opacity-0 absolute w-1 h-1 -z-10" tabindex="-1" onchange="handleFileSelect(event, 'preview_videos', false, true)">
                        <svg class="w-10 h-10 text-secundario mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        <span class="text-texto font-bold block">Clique ou arraste para adicionar vídeos</span>
                    </div>
                    <input type="file" id="real_input_videos" name="videos[]" multiple class="hidden">
                    <div id="preview_videos" class="mt-4 flex flex-wrap gap-4">
                        <?php foreach($videos as $v): ?>
                            <div class="relative w-24 h-24 rounded-lg overflow-hidden border border-borda group bg-sup1 flex items-center justify-center" id="media_<?= $v['id'] ?>">
                                <video src="<?= $v['file_path'] ?>" class="w-full h-full object-cover"></video>
                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <button type="button" class="text-white hover:text-brand" onclick="deleteExistingMedia(<?= $v['id'] ?>)">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <script>
        function deleteExistingMedia(mediaId) {
            if(!confirm('Tem certeza que deseja excluir esta mídia? Ela será removida imediatamente.')) return;
            
            fetch('excluir_midia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ media_id: mediaId })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('media_' + mediaId).remove();
                } else {
                    alert('Erro ao excluir mídia: ' + data.error);
                }
            });
        }
        </script>

        <!-- Categorias (Tags) -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Categorias e Tags</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach($categories as $cat): ?>
                    <label class="flex items-center space-x-2 text-texto cursor-pointer">
                        <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" class="form-checkbox bg-sup2 border-borda text-brand rounded focus:ring-brand" <?= in_array($cat['id'], $adCategories) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($cat['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Plano de Destaque -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Plano de Destaque</h2>
            <div class="bg-sup2 border border-brand/30 p-5 rounded-lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="organic" class="peer sr-only" <?= $ad['plan'] == 'organic' ? 'checked' : '' ?>>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-brand peer-checked:border-brand peer-checked:ring-1 peer-checked:ring-brand peer-checked:bg-brand/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-texto">Anúncios</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="paid_highlight" class="peer sr-only" <?= $ad['plan'] == 'paid_highlight' ? 'checked' : '' ?>>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#cd7f32] peer-checked:border-[#cd7f32] peer-checked:ring-1 peer-checked:ring-[#cd7f32] peer-checked:bg-[#cd7f32]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#cd7f32]">⭐ Destaques</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="super_highlight" class="peer sr-only" <?= $ad['plan'] == 'super_highlight' ? 'checked' : '' ?>>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#c0c0c0] peer-checked:border-[#c0c0c0] peer-checked:ring-1 peer-checked:ring-[#c0c0c0] peer-checked:bg-[#c0c0c0]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#c0c0c0]">⭐⭐ Super Destaques</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="super_top" class="peer sr-only" <?= $ad['plan'] == 'super_top' ? 'checked' : '' ?>>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#ffd700] peer-checked:border-[#ffd700] peer-checked:ring-1 peer-checked:ring-[#ffd700] peer-checked:bg-[#ffd700]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#ffd700]">💎 Super Top</span>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="plan" value="ultra_top" class="peer sr-only" <?= $ad['plan'] == 'ultra_top' ? 'checked' : '' ?>>
                        <div class="rounded-xl border border-borda bg-sup1 p-4 transition hover:border-[#00bfff] peer-checked:border-[#00bfff] peer-checked:ring-1 peer-checked:ring-[#00bfff] peer-checked:bg-[#00bfff]/10 h-full flex flex-col justify-between">
                            <div>
                                <span class="block text-lg font-bold text-[#00bfff]">👑 Ultra Top</span>
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
                    <h3 class="text-texto font-bold text-lg">Aceita Chamadas de Vídeo?</h3>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="available_now_checkbox" name="available_now" value="1" class="sr-only peer" <?= $ad['available_now'] ? 'checked' : '' ?>>
                    <div class="w-14 h-7 bg-sup2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-brand border border-borda"></div>
                </label>
            </div>
        </div>


        <div class="pt-6 border-t border-borda">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <div>
                <label class="block text-secundario mb-2">Quem você atende?</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Homens" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Homens', $adAttendsTo) ? 'checked' : '' ?>> Homens
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Mulheres" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Mulheres', $adAttendsTo) ? 'checked' : '' ?>> Mulheres
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="attends_to[]" value="Casais" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Casais', $adAttendsTo) ? 'checked' : '' ?>> Casais
                    </label>
                </div>
            </div>
            
            <div>
                <label class="block text-secundario mb-2">Formas de Pagamento</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Pix" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Pix', $adPaymentMethods) ? 'checked' : '' ?>> Pix
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Dinheiro" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Dinheiro', $adPaymentMethods) ? 'checked' : '' ?>> Dinheiro
                    </label>
                    <label class="flex items-center gap-2 text-texto cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="Cartão de Crédito" class="w-4 h-4 bg-sup2 border-borda rounded text-brand focus:ring-brand" <?= in_array('Cartão de Crédito', $adPaymentMethods) ? 'checked' : '' ?>> Cartão de Crédito
                    </label>
                </div>
            </div>
        </div>

        <!-- Características -->
        <div>
            <h2 class="text-xl font-bold text-brand mb-4 border-b border-borda pb-2">Posição e serviços</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php 
                $extra_characteristics = ['Atendimento 24h', 'Faz anal', 'Dupla penetração'];
                foreach ($extra_characteristics as $char): 
                ?>
                <label class="flex items-center gap-3 p-3 bg-sup2 border border-borda rounded-lg cursor-pointer hover:border-brand transition">
                    <input type="checkbox" name="characteristics[]" value="<?= htmlspecialchars($char) ?>" class="w-5 h-5 bg-sup1 border-borda rounded text-brand focus:ring-brand" <?= in_array($char, $adCharacteristics) ? 'checked' : '' ?>>
                    <span class="text-texto font-medium text-sm"><?= htmlspecialchars($char) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

            <div class="pt-6 flex justify-end">
            <button type="submit" class="bg-brand text-white font-bold py-3 px-8 rounded-lg hover:bg-brand/90 transition shadow-lg shadow-brand/20">
                Salvar Alterações
            </button>
        </div>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
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
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const stateSelect = document.getElementById('state_select');
    const citySelect = document.getElementById('city_select');
    const tagsContainer = document.getElementById('selected_cities_container');
    const inputsContainer = document.getElementById('hidden_cities_inputs');
    const validator = document.getElementById('cities_validator');
    
    let selectedCities = <?= json_encode($adCities) ?>;
    let preSelectedState = '<?= $adState ?>';

    function renderCities() {
        tagsContainer.innerHTML = '';
        inputsContainer.innerHTML = '';
        
        selectedCities.forEach((city, index) => {
            const tag = document.createElement('div');
            tag.className = "flex items-center gap-1 bg-brand/20 text-brand border border-brand/40 px-3 py-1.5 rounded-full text-sm font-semibold animate-fade-in";
            tag.innerHTML = `
                ${city}
                <button type="button" class="text-brand hover:text-white transition ml-1" data-index="${index}">&times;</button>
            `;
            tagsContainer.appendChild(tag);
            
            const hidden = document.createElement('input');
            hidden.type = "hidden";
            hidden.name = "cities[]";
            hidden.value = city;
            inputsContainer.appendChild(hidden);
        });

        tagsContainer.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = parseInt(e.currentTarget.getAttribute('data-index'));
                selectedCities.splice(idx, 1);
                renderCities();
            });
        });

        validator.value = selectedCities.length > 0 ? 'valid' : '';
    }

    renderCities();

    citySelect.addEventListener('change', (e) => {
        const city = e.target.value;
        if (city && !selectedCities.includes(city)) {
            selectedCities.push(city);
            renderCities();
        }
        e.target.selectedIndex = 0;
    });

    fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome')
        .then(response => response.json())
        .then(states => {
            states.forEach(state => {
                const option = document.createElement('option');
                option.value = state.sigla;
                option.textContent = state.nome;
                if(state.sigla === preSelectedState) option.selected = true;
                stateSelect.appendChild(option);
            });
            if(preSelectedState) {
                loadCities(preSelectedState);
            }
        });

    stateSelect.addEventListener('change', () => {
        const uf = stateSelect.value;
        citySelect.innerHTML = '';
        selectedCities = [];
        renderCities();
        
        if (!uf) {
            citySelect.innerHTML = '<option value="" disabled selected>Selecione o estado primeiro</option>';
            return;
        }
        loadCities(uf);
    });

    function loadCities(uf) {
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
            });
    }
});

const filesData = { 'preview_photos': new DataTransfer(), 'preview_videos': new DataTransfer() };

function handleFileSelect(event, previewContainerId, isSingle = false, isVideo = false) {
    const files = event.target.files;
    const previewContainer = document.getElementById(previewContainerId);
    
    if (isSingle) {
        previewContainer.innerHTML = '';
    }

    let dt = filesData[previewContainerId];
    if (!isSingle) {
        for(let i=0; i<files.length; i++){ dt.items.add(files[i]); }
    }

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const wrap = document.createElement('div');
            wrap.className = "relative w-24 h-24 rounded-lg overflow-hidden border border-borda group bg-sup1 flex items-center justify-center";
            
            if (isVideo) {
                wrap.innerHTML = `
                    <svg class="w-8 h-8 text-brand opacity-80" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path></svg>
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <button type="button" class="text-white hover:text-brand" onclick="removeFile(this, '${file.name}', '${previewContainerId}', '${isSingle}')">X</button>
                    </div>
                `;
            } else {
                wrap.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute bottom-0 w-full bg-brand text-white text-xs text-center py-1 font-bold">Novo</div>
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <button type="button" class="text-white hover:text-brand" onclick="removeFile(this, '${file.name}', '${previewContainerId}', '${isSingle}')">X</button>
                    </div>
                `;
            }
            previewContainer.appendChild(wrap);
        }
        if (file) reader.readAsDataURL(file);
    }

    if (!isSingle) {
        if (previewContainerId === 'preview_photos') document.getElementById('real_input_photos').files = dt.files;
        else if (previewContainerId === 'preview_videos') document.getElementById('real_input_videos').files = dt.files;
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
            if (dt.files[i].name !== fileName) newDt.items.add(dt.files[i]);
        }
        filesData[previewContainerId] = newDt;
        
        if (previewContainerId === 'preview_photos') document.getElementById('real_input_photos').files = newDt.files;
        else if (previewContainerId === 'preview_videos') document.getElementById('real_input_videos').files = newDt.files;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
