<?php
require_once __DIR__ . '/../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo "<div class='text-center py-12 text-white'>Anúncio não especificado.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle Add Review
if (isset($_GET['action']) && $_GET['action'] === 'addReview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: {$baseUrl}/login/");
        exit;
    }
    $userId = $_SESSION['user_id'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $pdo->prepare("INSERT INTO reviews (ad_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $userId, $rating, $comment]);
    }
    header("Location: {$baseUrl}/anuncio/?id=" . $id);
    exit;
}

// Fetch Ad details
$stmt = $pdo->prepare("
    SELECT a.*, u.name as advertiser_name 
    FROM ads a 
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$ad = $stmt->fetch();

if (!$ad) {
    echo "<div class='text-center py-12 text-white'>Anúncio não encontrado.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $ad['user_id'];
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if ($ad['status'] !== 'active' && !$isOwner && !$isAdmin) {
    echo "<div class='text-center py-12 text-white'>Anúncio não encontrado ou inativo.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Increment views
$stmtViews = $pdo->prepare("UPDATE ads SET views = views + 1 WHERE id = ?");
$stmtViews->execute([$id]);

// Fetch media
$stmtMedia = $pdo->prepare("SELECT * FROM ad_media WHERE ad_id = ?");
$stmtMedia->execute([$id]);
$media = $stmtMedia->fetchAll();

// Fetch categories
$stmtCats = $pdo->prepare("
    SELECT c.name FROM categories c 
    JOIN ad_categories ac ON c.id = ac.category_id 
    WHERE ac.ad_id = ?
");
$stmtCats->execute([$id]);
$categories = $stmtCats->fetchAll();

// Fetch ad locations
$stmtLocs = $pdo->prepare("SELECT city, state FROM ad_locations WHERE ad_id = ?");
$stmtLocs->execute([$id]);
$ad_locations = $stmtLocs->fetchAll();

// Fetch reviews
$stmtReviews = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.ad_id = ?
    ORDER BY r.created_at DESC
");
$stmtReviews->execute([$id]);
$reviews = $stmtReviews->fetchAll();

// Calculate dynamic rating and reviews count
$reviews_count = count($reviews);
$rating = 0;
if ($reviews_count > 0) {
    $sum = 0;
    foreach ($reviews as $rev) {
        $sum += $rev['rating'];
    }
    $rating = $sum / $reviews_count;
}

// Main images preparation
$coverPhoto = 'https://via.placeholder.com/1200x400/333/666?text=Capa';
$profilePhoto = 'https://via.placeholder.com/400x400/222/666?text=Perfil';
$gallery = [];
$videos = [];

foreach ($media as $index => $m) {
    if ($m['media_type'] === 'video') {
        $videos[] = htmlspecialchars($m['file_path']);
    } else {
        if ($m['is_primary']) {
            $profilePhoto = htmlspecialchars($m['file_path']);
        } else {
            $gallery[] = htmlspecialchars($m['file_path']);
        }
    }
}

// Fallback to first gallery item for cover if available
if (!empty($gallery)) {
    $coverPhoto = $gallery[0];
} else if ($profilePhoto !== 'https://via.placeholder.com/400x400/222/666?text=Perfil') {
    $coverPhoto = $profilePhoto;
}
?>

<div class="max-w-4xl mx-auto bg-sup1 border-x border-borda min-h-screen shadow-lg pb-12">

    <!-- Header / Cover Photo -->
    <div class="relative w-full h-48 md:h-64 bg-gray-300">
        <img src="<?= $coverPhoto ?>" class="w-full h-full object-cover cursor-pointer" alt="Capa" onclick="openLightbox('<?= $coverPhoto ?>')">

        <!-- Floating Top Buttons -->
        <div class="absolute top-4 left-4 z-20 flex gap-2">
            <a href="<?= $baseUrl ?>/" class="bg-sup2/80 hover:bg-black/80 text-texto rounded-full p-2 backdrop-blur transition"
                title="Voltar para a página inicial">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
        </div>

        <div class="absolute top-4 right-4 z-20 flex gap-2">
            <?php
            $isLoggedIn = isset($_SESSION['user_id']) ? 'true' : 'false';
            ?>
            <button onclick="handleAction('favorite', <?= $isLoggedIn ?>)"
                class="bg-sup2/80 hover:bg-black/80 text-texto rounded-full p-2 backdrop-blur transition"
                title="Favoritar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                    </path>
                </svg>
            </button>
            <button onclick="handleAction('report', <?= $isLoggedIn ?>)"
                class="bg-sup2/80 hover:bg-black/80 text-texto rounded-full p-2 backdrop-blur transition"
                title="Denunciar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Profile Info Section -->
    <div class="px-4 md:px-8 relative">

        <div class="flex flex-col md:flex-row items-center md:items-start gap-4 -mt-16 md:-mt-20 mb-6">
            <!-- Profile Avatar -->
            <div
                class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-sup1 overflow-hidden bg-sup1 shadow-md flex-shrink-0 relative z-20">
                <img src="<?= $profilePhoto ?>" class="w-full h-full object-cover" alt="Perfil">
            </div>

            <!-- Name and Badges -->
            <div class="pt-2 md:pt-24 text-center md:text-left flex-1 relative z-10 w-full">
                <div
                    class="inline-block bg-sup2/80 backdrop-blur border border-borda px-3 py-1 rounded-full text-xs font-bold text-secundario mb-2 shadow-sm">
                    Modificado em: <?= date('d/m/Y H:i', strtotime($ad['created_at'])) ?>
                </div>

                <h1 class="text-3xl md:text-4xl font-extrabold text-texto flex flex-col md:flex-row items-center gap-2">
                    <?= htmlspecialchars(isset($ad['advertiser_name']) ? $ad['advertiser_name'] : $ad['title']) ?>
                    <?php if ($ad['age']): ?>
                        <span class="text-secundario text-2xl"><?= $ad['age'] ?> anos</span>
                    <?php endif; ?>
                </h1>

                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mt-3">
                    <?php if ($ad['is_verified']): ?>
                        <span
                            class="bg-[#1ebd5a] text-texto text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1 shadow-sm">
                            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                            Documentos verificados ✓
                        </span>
                    <?php endif; ?>

                    <?php if ($ad['is_premium']): ?>
                        <span
                            class="bg-blue-100 text-blue-600 border border-blue-200 text-xs font-bold px-3 py-1 rounded flex items-center gap-1 shadow-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                </path>
                            </svg>
                            Premium
                        </span>
                    <?php endif; ?>

                    <?php if (in_array($ad['highlight_level'], ['ultra_top', 'super_top', 'super_highlight', 'paid_highlight'])): ?>
                        <span
                            class="bg-brand/20 text-brand border border-brand/50 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1 shadow-sm uppercase tracking-wide">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                </path>
                            </svg>
                            <?php 
                            $highlightNames = [
                                'ultra_top' => 'Ultra Top',
                                'super_top' => 'Super Top',
                                'super_highlight' => 'Super Destaque',
                                'paid_highlight' => 'Destaque',
                                'organic' => 'Orgânico'
                            ];
                            echo isset($highlightNames[$ad['highlight_level']]) ? $highlightNames[$ad['highlight_level']] : 'Destaque';
                            ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($ad['available_now']): ?>
                        <span class="bg-purple-100 text-purple-600 border border-purple-200 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1 shadow-sm">
                            <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            Faz Chamada de Vídeo
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-4 md:pt-24 md:text-right hidden md:block">
                <?php if ($reviews_count > 0): ?>
                    <div class="text-sm text-secundario font-bold">Avaliações reais <span class="text-yellow-500">★
                            <?= number_format($rating, 1) ?></span> (<?= $reviews_count ?>)</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Cards (Cachet & Location) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div
                class="border border-borda bg-sup2 rounded-lg p-4 flex items-center gap-3 shadow-sm hover:border-brand transition">
                <div class="bg-brand/10 p-3 rounded-full text-brand">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <div>
                    <div class="text-xs text-secundario font-bold uppercase tracking-wider">Cachê</div>
                    <div class="font-extrabold text-texto text-lg">A partir de R$
                        <?= number_format($ad['price'], 2, ',', '.') ?></div>
                </div>
            </div>

            <?php if (isset($ad['service_type']) && $ad['service_type'] === 'video'): ?>
            <div class="border border-borda bg-sup2 rounded-lg p-4 flex items-center gap-3 shadow-sm hover:border-brand transition">
                <div class="bg-brand/10 p-3 rounded-full text-brand">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <div class="text-xs text-secundario font-bold uppercase tracking-wider">Atendimento</div>
                    <div class="font-extrabold text-brand text-lg">100% Online (Vídeo)</div>
                </div>
            </div>
            <?php else: ?>
            <div
                class="border border-borda bg-sup2 rounded-lg p-4 flex items-center gap-3 shadow-sm hover:border-brand transition">
                <div class="bg-brand/10 p-3 rounded-full text-brand">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <div>
                    <div class="text-xs text-secundario font-bold uppercase tracking-wider">Localização</div>
                    <?php if (!empty($ad_locations)): ?>
                        <div class="font-extrabold text-texto text-lg"><?= htmlspecialchars($ad_locations[0]['city']) ?> - <?= htmlspecialchars($ad_locations[0]['state']) ?></div>
                        <a href="http://maps.google.com/maps?q=<?= urlencode($ad_locations[0]['city'] . ' - ' . $ad_locations[0]['state']) ?>"
                            target="_blank"
                            class="text-xs text-brand hover:text-texto font-medium cursor-pointer hover:underline">Ver mapa ></a>
                    <?php else: ?>
                        <div class="font-extrabold text-texto text-lg">A Combinar</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Big WhatsApp Action -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="https://wa.me/55<?= htmlspecialchars($ad['phone']) ?>" target="_blank"
                class="w-full flex items-center justify-center gap-3 bg-green-500 hover:bg-green-600 text-texto text-lg font-bold py-4 rounded-lg shadow-lg transition">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path>
</svg>
WhatsApp
</a>

<button id="btn-reveal-phone" onclick="revealPhone('<?= htmlspecialchars($ad['phone']) ?>')"
class="w-full mt-3 flex items-center justify-center gap-2 border border-borda bg-sup2 text-texto font-bold py-3 rounded-lg hover:border-brand transition">
<svg class="w-5 h-5 text-secundario" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
</path>
</svg>
Ver telefone: <span id="phone-display" class="blur-sm select-none text-brand">(XX) XXXXX-XXXX</span>
</button>
<?php else: ?>
<button onclick="handleAction('whatsapp', false)"
class="w-full flex items-center justify-center gap-3 bg-[#25D366] hover:bg-green-600 text-texto text-lg font-bold py-4 rounded-lg shadow-lg transition">
<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
<path
d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path>
</svg>
</button>

<button onclick="handleAction('phone', false)"
class="w-full mt-3 flex items-center justify-center gap-2 border border-borda bg-sup2 text-texto font-bold py-3 rounded-lg hover:border-brand transition">
<svg class="w-5 h-5 text-secundario" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
</path>
</svg>
Ver telefone: <span class="blur-sm select-none text-brand">(XX) XXXXX-XXXX</span>
</button>
<?php endif; ?>

<!-- Tabs -->
<div class="flex items-center gap-6 border-b border-borda mt-8 mb-6 overflow-x-auto">
<button onclick="switchTab('fotos')" id="tab-fotos"
class="whitespace-nowrap pb-3 text-brand font-bold border-b-2 border-brand">Fotos</button>
<button onclick="switchTab('videos')" id="tab-videos"
class="whitespace-nowrap pb-3 text-secundario hover:text-texto font-bold border-b-2 border-transparent">Vídeos</button>
<button onclick="switchTab('avaliacoes')" id="tab-avaliacoes"
class="whitespace-nowrap pb-3 text-secundario hover:text-texto font-bold border-b-2 border-transparent">Avaliações</button>
</div>

<div id="content-fotos" class="tab-content">
<!-- Gallery Grid (Masonry) -->
<?php if (!empty($gallery)): ?>
<div class="columns-2 md:columns-3 lg:columns-4 gap-2 space-y-2">
<?php foreach ($gallery as $img): ?>
<div class="aspect-[3/4] relative bg-gray-200 rounded overflow-hidden">
<img src="<?= $img ?>" class="w-full h-full object-cover cursor-pointer hover:scale-110 transition duration-500" alt="Galeria" onclick="openLightbox('<?= $img ?>')">
<!-- Discreet Watermark -->
<div class="absolute bottom-1 right-1 pointer-events-none opacity-50 z-10">
<span class="text-texto font-bold text-[8px] tracking-wider drop-shadow-md">ANW.COM.BR</span>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-sup2 rounded-lg p-10 text-center border border-borda">
<svg class="w-12 h-12 text-secundario mx-auto mb-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2z">
</path>
</svg>
<p class="text-secundario font-medium">Este perfil ainda não adicionou fotos na galeria.</p>
</div>
<?php endif; ?>
</div>

<div id="content-videos" class="tab-content hidden">
<?php if (!empty($videos)): ?>
<div class="columns-1 md:columns-2 lg:columns-3 gap-2 space-y-2">
<?php foreach ($videos as $vid): ?>
<div class="aspect-[9/16] relative bg-black rounded overflow-hidden">
<video src="<?= $vid ?>" controls controlsList="nodownload"
class="w-full h-full object-contain"></video>
<div class="absolute bottom-1 right-1 pointer-events-none opacity-50 z-10">
<span class="text-texto font-bold text-[8px] tracking-wider drop-shadow-md">ANW.COM.BR</span>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-sup2 rounded-lg p-10 text-center border border-borda">
<svg class="w-12 h-12 text-secundario mx-auto mb-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
</path>
</svg>
<p class="text-secundario font-medium">Este perfil ainda não adicionou vídeos.</p>
</div>
<?php endif; ?>
</div>

<div id="content-avaliacoes" class="tab-content hidden">
<!-- Review List -->
<?php if (!empty($reviews)): ?>
<div class="space-y-4 mb-8">
<?php foreach ($reviews as $rev): ?>
<div class="bg-sup2 border border-borda rounded-lg p-5">
<div class="flex items-center justify-between mb-2">
<div class="font-bold text-texto"><?= htmlspecialchars($rev['user_name']) ?></div>
<div class="text-sm text-secundario"><?= date('d/m/Y', strtotime($rev['created_at'])) ?></div>
</div>
<div class="flex text-yellow-400 text-sm mb-2">
<?= str_repeat('⭐', $rev['rating']) ?>        <?= str_repeat('☆', 5 - $rev['rating']) ?>
</div>
<p class="text-secundario text-sm leading-relaxed"><?= nl2br(htmlspecialchars($rev['comment'])) ?>
</p>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-sup2 rounded-lg p-10 text-center border border-borda mb-8">
<svg class="w-12 h-12 text-secundario mx-auto mb-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
</path>
</svg>
<h3 class="text-xl font-bold text-texto mb-2">Nenhuma avaliação</h3>
<p class="text-secundario font-medium">Seja o primeiro a avaliar este perfil.</p>
</div>
<?php endif; ?>

<!-- Add Review Form -->
<div class="bg-sup2 border border-borda rounded-lg p-6 shadow-sm">
<h3 class="text-lg font-bold text-texto mb-4">Deixe sua avaliação</h3>
<?php if (isset($_SESSION['user_id'])): ?>
<form action="<?= $baseUrl ?>/anuncio/?id=<?= $ad['id'] ?>&action=addReview" method="POST" class="space-y-4">
<div>
<label class="block text-sm font-bold text-secundario mb-1">Nota (1 a 5 estrelas)</label>
<select name="rating" required
class="w-full bg-sup1 border-borda text-texto rounded-lg shadow-sm focus:ring-brand focus:border-brand">
<option value="5">⭐⭐⭐⭐⭐ Excelente (5)</option>
<option value="4">⭐⭐⭐⭐ Muito Bom (4)</option>
<option value="3">⭐⭐⭐ Bom (3)</option>
<option value="2">⭐⭐ Regular (2)</option>
<option value="1">⭐ Ruim (1)</option>
</select>
</div>
<div>
<label class="block text-sm font-bold text-secundario mb-1">Seu Comentário</label>
<textarea name="comment" rows="3" placeholder="Como foi sua experiência?"
class="w-full bg-sup1 border-borda text-texto rounded-lg shadow-sm focus:ring-brand focus:border-brand"></textarea>
</div>
<button type="submit"
class="bg-brand hover:bg-brand-hover text-texto font-bold py-3 px-6 rounded-lg transition w-full md:w-auto">
Publicar Avaliação
</button>
</form>
<?php else: ?>
<div
class="bg-yellow-500/10 border border-yellow-500/30 p-4 rounded-lg flex items-center justify-between">
<div class="text-yellow-500 text-sm">Você precisa estar logado para deixar uma avaliação.</div>
<a href="<?= $baseUrl ?>/login/"
class="bg-yellow-500 hover:bg-yellow-600 text-texto font-bold py-2 px-4 rounded text-sm transition">Fazer
Login</a>
</div>
<?php endif; ?>
</div>
</div>

<!-- Sobre Mim Section -->
<div class="mt-12 mb-12">
<h2 class="text-xl font-extrabold text-texto flex items-center gap-2 mb-4">
<svg class="w-6 h-6 text-brand" fill="currentColor" viewBox="0 0 20 20">
<path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
clip-rule="evenodd"></path>
</svg>
Sobre Mim
</h2>

<div class="space-y-4">
<!-- Description Box -->
<div class="bg-sup2 border border-borda rounded-lg p-5">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-2">Descrição</h3>
<p class="text-texto text-sm leading-relaxed whitespace-pre-wrap font-medium">
<?= htmlspecialchars($ad['description'] ?: 'Sem descrição informada.') ?>
</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<!-- Características Box -->
<div class="bg-sup2 border border-borda rounded-lg p-5">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-3">Informações</h3>
<div class="space-y-3">
<div>
<div class="text-xs text-secundario">Idade</div>
<div class="text-texto font-bold"><?= htmlspecialchars($ad['age']) ?> anos</div>
</div>
<div>
<div class="text-xs text-secundario">Atende</div>
<div class="text-texto font-bold">
<?= !empty($ad['attends_to']) ? htmlspecialchars($ad['attends_to']) : 'Homens, Mulheres, Casais' ?>
</div>
</div>
<div>
<div class="text-xs text-secundario">Local</div>
<div class="text-texto font-bold">
<?= (isset($ad['service_type']) && $ad['service_type'] === 'video') ? 'Online (Vídeo)' : (!empty($ad['service_locations']) ? htmlspecialchars($ad['service_locations']) : 'Local próprio / Motel') ?>
</div>
</div>
<div>
<div class="text-xs text-secundario">Cachê Inicial</div>
<div class="text-texto font-bold text-lg text-brand">R$
<?= number_format($ad['price'], 2, ',', '.') ?></div>
</div>
</div>
</div>

<!-- Cidades Atendidas Box -->
<div class="bg-sup2 border border-borda rounded-lg p-5">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-3">Cidades de Atendimento</h3>
<div class="flex flex-wrap gap-2">
<?php if (isset($ad['service_type']) && $ad['service_type'] === 'video'): ?>
<span class="text-brand border border-brand/30 bg-brand/10 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">
Atendimento Online (Vídeo)
</span>
<?php elseif (!empty($ad_locations)): ?>
<?php foreach ($ad_locations as $loc): ?>
<span class="text-brand border border-brand/30 bg-brand/10 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">
<?= htmlspecialchars($loc['city'] . ' - ' . $loc['state']) ?>
</span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-secundario text-sm">Não informadas.</span>
<?php endif; ?>
</div>
</div>

<!-- Categorias Box -->
<div class="bg-sup2 border border-borda rounded-lg p-5">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-3">Categorias</h3>
<div class="flex flex-wrap gap-2">
<?php if (!empty($categories)): ?>
<?php foreach ($categories as $cat): ?>
<span
class="text-brand border border-brand/30 bg-brand/10 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">
<?= htmlspecialchars($cat['name']) ?>
</span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-secundario text-sm">Não informadas.</span>
<?php endif; ?>
</div>
</div>

<!-- Características Especiais Box -->
<?php
$chars = !empty($ad['characteristics']) ? json_decode($ad['characteristics'], true) : [];
if (!empty($chars)):
?>
<div class="bg-sup2 border border-borda rounded-lg p-5 md:col-span-2">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-3">Serviços e
Características Especiais</h3>
<div class="grid grid-cols-2 md:grid-cols-3 gap-3">
<?php foreach ($chars as $key => $val): ?>
<?php $char_name = is_numeric($key) ? $val : $key; ?>
<div class="flex items-center gap-2 text-sm text-texto">
<svg class="w-4 h-4 text-[#1ebd5a]" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M5 13l4 4L19 7"></path>
</svg>
<?= htmlspecialchars($char_name) ?>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<!-- Atendimento Box -->
<div class="bg-sup2 border border-borda rounded-lg p-5">
<h3 class="text-xs font-bold text-secundario uppercase tracking-wider mb-3">Detalhes do
Atendimento</h3>
<ul class="space-y-2 text-sm text-texto">
<?php
$attends = !empty($ad['attends_to']) ? explode(',', $ad['attends_to']) : [];
foreach ($attends as $att):
?>
<li class="flex items-center gap-2"><svg class="w-4 h-4 text-[#1ebd5a]" fill="none"
stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M5 13l4 4L19 7"></path>
</svg> Atende <?= htmlspecialchars(trim($att)) ?></li>
<?php endforeach; ?>

<?php
$payments = !empty($ad['payment_methods']) ? explode(',', $ad['payment_methods']) : [];
if (!empty($payments)):
?>
<li class="flex items-center gap-2 mt-4 text-secundario font-bold"><svg
class="w-4 h-4 text-secundario" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
</path>
</svg> Formas de Pagamento</li>
<?php foreach ($payments as $pay): ?>
<li class="flex items-center gap-2 pl-6"><svg class="w-3 h-3 text-[#1ebd5a]" fill="none"
stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M5 13l4 4L19 7"></path>
</svg> <?= htmlspecialchars(trim($pay)) ?></li>
<?php endforeach; ?>
<?php endif; ?>

<?php if (empty($attends) && empty($payments)): ?>
<li class="text-secundario">Detalhes não informados.</li>
<?php endif; ?>
</ul>
</div>
</div>
</div>
</div>

<div class="mt-12 mb-8 text-center pb-8">
<button onclick="handleAction('report', <?= $isLoggedIn ?>)"
class="text-secundario hover:text-brand transition text-sm flex items-center justify-center gap-2 mx-auto">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
</path>
</svg>
Denunciar Perfil
</button>
</div>
</div>
</div>

<!-- Custom Modal -->
<div id="action-modal"
class="fixed inset-0 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
<div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
<div class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 relative z-10 shadow-2xl transform transition-transform duration-300 translate-y-8 scale-95"
id="modal-content">

<!-- Unauthenticated State -->
<div class="text-center hidden" id="modal-unauth">
<div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
</path>
</svg>
</div>
<h3 class="text-xl font-extrabold text-gray-900 mb-2">Acesso Restrito</h3>
<p class="text-secundario text-sm mb-6">Você precisa ter uma conta e estar logado para realizar esta ação.
</p>
<div class="flex gap-3">
<button onclick="closeModal()"
class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg transition">Cancelar</button>
<a href="<?= $baseUrl ?>/login/"
class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-texto font-bold rounded-lg transition flex items-center justify-center">Fazer
Login</a>
</div>
</div>

<!-- Success State -->
<div class="text-center hidden" id="modal-success">
<div
class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
</svg>
</div>
<h3 class="text-xl font-extrabold text-gray-900 mb-2" id="success-title">Sucesso!</h3>
<p class="text-secundario text-sm mb-6" id="success-message">Ação realizada com sucesso.</p>
<button onclick="closeModal()"
class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg transition">Fechar</button>
</div>

</div>
</div>
</div>

<!-- Lightbox Modal -->
<div id="lightbox-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/95 backdrop-blur-sm transition-opacity duration-300 opacity-0">
    <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white hover:text-brand bg-black/50 hover:bg-black rounded-full p-2 transition z-50">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
    <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain p-4 transition-transform duration-300 scale-95" alt="Fullscreen Image">
</div>
<script>
function openModal() {
const modal = document.getElementById('action-modal');
const content = document.getElementById('modal-content');
modal.classList.remove('hidden');
setTimeout(() => {
modal.classList.remove('opacity-0');
content.classList.remove('translate-y-8', 'scale-95');
}, 10);
}

function closeModal() {
const modal = document.getElementById('action-modal');
const content = document.getElementById('modal-content');
modal.classList.add('opacity-0');
content.classList.add('translate-y-8', 'scale-95');
setTimeout(() => {
modal.classList.add('hidden');
}, 300);
}

function handleAction(type, isLoggedIn) {
document.getElementById('modal-unauth').classList.add('hidden');
document.getElementById('modal-success').classList.add('hidden');

if (!isLoggedIn) {
document.getElementById('modal-unauth').classList.remove('hidden');
openModal();
return;
}

document.getElementById('modal-success').classList.remove('hidden');

if (type === 'favorite') {
document.getElementById('success-title').innerText = 'Adicionado aos Favoritos';
document.getElementById('success-message').innerText = 'O perfil foi salvo na sua lista de favoritos!';
} else if (type === 'report') {
document.getElementById('success-title').innerText = 'Denúncia Registrada';
document.getElementById('success-message').innerText = 'Nossa equipe verificará o perfil em breve.';
}

openModal();
}

function revealPhone(phone) {
let formatted = phone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
document.getElementById('phone-display').textContent = formatted;
document.getElementById('phone-display').classList.remove('blur-sm');
document.getElementById('btn-reveal-phone').classList.add('bg-green-50');
}

function switchTab(tabId) {
document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
document.getElementById('content-' + tabId).classList.remove('hidden');

const tabs = ['fotos', 'videos', 'avaliacoes'];
tabs.forEach(t => {
const btn = document.getElementById('tab-' + t);
btn.classList.remove('text-brand', 'border-brand');
btn.classList.add('text-secundario', 'border-transparent');
});

const activeBtn = document.getElementById('tab-' + tabId);
activeBtn.classList.remove('text-secundario', 'border-transparent');
activeBtn.classList.add('text-brand', 'border-brand');
}

function openLightbox(imgSrc) {
    const lightbox = document.getElementById('lightbox-modal');
    const lightboxImg = document.getElementById('lightbox-img');
    lightboxImg.src = imgSrc;
    lightbox.classList.remove('hidden');
    lightbox.classList.add('flex');
    setTimeout(() => {
        lightbox.classList.remove('opacity-0');
        lightboxImg.classList.remove('scale-95');
        lightboxImg.classList.add('scale-100');
    }, 10);
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox-modal');
    const lightboxImg = document.getElementById('lightbox-img');
    lightbox.classList.add('opacity-0');
    lightboxImg.classList.remove('scale-100');
    lightboxImg.classList.add('scale-95');
    setTimeout(() => {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
    }, 300);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

