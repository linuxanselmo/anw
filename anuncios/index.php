<?php
require_once __DIR__ . '/../includes/header.php';

// Fetch all active ads
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;

$sql = "SELECT a.*, l.city, l.state, u.name as advertiser_name 
        FROM ads a 
        LEFT JOIN (
            SELECT ad_id, MIN(city) as city, MIN(state) as state 
            FROM ad_locations 
            GROUP BY ad_id
        ) l ON a.id = l.ad_id 
        JOIN users u ON a.user_id = u.id";
        
$params = [];

if ($category_id) {
    $sql .= " JOIN ad_categories ac ON a.id = ac.ad_id AND ac.category_id = ?";
    $params[] = $category_id;
}

$sql .= " WHERE a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at > NOW())";

if (!empty($_GET['state'])) {
    $sql .= " AND l.state = ?";
    $params[] = $_GET['state'];
}

if (!empty($_GET['city'])) {
    $sql .= " AND l.city LIKE ?";
    $params[] = '%' . trim($_GET['city']) . '%';
}

if (!empty($_GET['min_price'])) {
    $sql .= " AND a.price >= ?";
    $params[] = (float)$_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $sql .= " AND a.price <= ?";
    $params[] = (float)$_GET['max_price'];
}

if (!empty($_GET['min_age'])) {
    $sql .= " AND a.age >= ?";
    $params[] = (int)$_GET['min_age'];
}
if (!empty($_GET['max_age'])) {
    $sql .= " AND a.age <= ?";
    $params[] = (int)$_GET['max_age'];
}

if (isset($_GET['online'])) {
    $sql .= " AND a.available_now = 1";
}
if (isset($_GET['verified'])) {
    $sql .= " AND a.is_verified = 1";
}
if (isset($_GET['premium'])) {
    $sql .= " AND a.is_premium = 1";
}

$sql .= " ORDER BY FIELD(a.highlight_level, 'ultra_top', 'super_top', 'super_highlight', 'paid_highlight', 'organic'), a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ads = $stmt->fetchAll();

// Fetch categories for the quick filters
$stmtCats = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmtCats->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-texto border-b border-borda pb-2">Nossos Anúncios</h1>
    </div>

    <!-- Filtros Avançados -->
    <div class="bg-sup1 border border-borda rounded-2xl p-6 mb-8 shadow-2xl">
        <h2 class="text-xl font-bold text-texto mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Filtros Avançados
        </h2>
        
        <form action="" method="GET" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- Categoria -->
                <div>
                    <label class="block text-secundario text-sm font-bold mb-2">Categoria</label>
                    <select name="category" class="w-full bg-sup2 text-texto border border-borda rounded-lg px-4 py-2 outline-none focus:border-brand transition">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Estado -->
                <div>
                    <label class="block text-secundario text-sm font-bold mb-2">Estado</label>
                    <select name="state" class="w-full bg-sup2 text-texto border border-borda rounded-lg px-4 py-2 outline-none focus:border-brand transition">
                        <option value="">Qualquer Estado</option>
                        <?php 
                            $states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach($states as $st): 
                                $selected = (isset($_GET['state']) && $_GET['state'] === $st) ? 'selected' : '';
                        ?>
                            <option value="<?= $st ?>" <?= $selected ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Cidade -->
                <div>
                    <label class="block text-secundario text-sm font-bold mb-2">Cidade</label>
                    <input type="text" name="city" value="<?= isset($_GET['city']) ? htmlspecialchars($_GET['city']) : '' ?>" placeholder="Ex: São Paulo" class="w-full bg-sup2 text-texto border border-borda rounded-lg px-4 py-2 outline-none focus:border-brand transition">
                </div>
                
                <!-- Faixa de Preço -->
                <div>
                    <label class="block text-secundario text-sm font-bold mb-2">Preço (R$)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" value="<?= isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '' ?>" placeholder="Min" min="0" step="10" class="w-1/2 bg-sup2 text-texto border border-borda rounded-lg px-3 py-2 outline-none focus:border-brand transition">
                        <span class="text-secundario">-</span>
                        <input type="number" name="max_price" value="<?= isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '' ?>" placeholder="Max" min="0" step="10" class="w-1/2 bg-sup2 text-texto border border-borda rounded-lg px-3 py-2 outline-none focus:border-brand transition">
                    </div>
                </div>
                
                <!-- Faixa de Idade -->
                <div>
                    <label class="block text-secundario text-sm font-bold mb-2">Idade (Anos)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_age" value="<?= isset($_GET['min_age']) ? htmlspecialchars($_GET['min_age']) : '' ?>" placeholder="Min" min="18" max="99" class="w-1/2 bg-sup2 text-texto border border-borda rounded-lg px-3 py-2 outline-none focus:border-brand transition">
                        <span class="text-secundario">-</span>
                        <input type="number" name="max_age" value="<?= isset($_GET['max_age']) ? htmlspecialchars($_GET['max_age']) : '' ?>" placeholder="Max" min="18" max="99" class="w-1/2 bg-sup2 text-texto border border-borda rounded-lg px-3 py-2 outline-none focus:border-brand transition">
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row md:items-center justify-between border-t border-borda pt-6 gap-6">
                <!-- Checkboxes Rápidos -->
                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="online" value="1" <?= isset($_GET['online']) ? 'checked' : '' ?> class="h-5 w-5 accent-brand rounded bg-sup2 border-borda focus:ring-brand text-brand">
                        <span class="ml-2 text-sm text-secundario group-hover:text-white transition">Chamada de Vídeo</span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="verified" value="1" <?= isset($_GET['verified']) ? 'checked' : '' ?> class="h-5 w-5 accent-brand rounded bg-sup2 border-borda focus:ring-brand text-brand">
                        <span class="ml-2 text-sm text-secundario group-hover:text-white transition flex items-center gap-1">
                            Verificado
                            <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="checkbox" name="premium" value="1" <?= isset($_GET['premium']) ? 'checked' : '' ?> class="h-5 w-5 accent-brand rounded bg-sup2 border-borda focus:ring-brand text-brand">
                        <span class="ml-2 text-sm text-secundario group-hover:text-white transition flex items-center gap-1">
                            Premium
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        </span>
                    </label>
                </div>
                
                <!-- Ações -->
                <div class="flex items-center gap-4">
                    <?php if (!empty($_GET)): ?>
                        <a href="<?= $baseUrl ?>/anuncios/" class="text-secundario hover:text-white font-bold transition">Limpar Filtros</a>
                    <?php endif; ?>
                    <button type="submit" class="bg-brand text-white px-8 py-3 rounded-lg font-bold hover:bg-brand-hover transition shadow-lg shadow-brand/30 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista agrupada por destaque -->
    <?php
    $tiers = [
        'ultra_top' => '👑 Ultra Top',
        'super_top' => '💎 Super Top',
        'super_highlight' => '⭐⭐ Super Destaques',
        'paid_highlight' => '⭐ Destaques',
        'organic' => 'Anúncios'
    ];

    if (empty($ads)) {
        echo '<div class="col-span-full text-center py-12 text-secundario bg-sup1 rounded-2xl border border-borda">Nenhum anúncio encontrado para estes filtros.</div>';
    } else {
        foreach ($tiers as $level => $title):
            $tierAds = array_filter($ads, function ($a) use ($level) {
                return $a['highlight_level'] === $level;
            });
            if (empty($tierAds))
                continue;
            ?>

            <h2 class="text-3xl font-bold mb-6 mt-12 text-texto border-b border-borda pb-2"><?= $title ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($tierAds as $ad):
                    $borderClass = 'border-borda hover:border-brand';
                    $badgeHtml = '';
                    $shadowClass = 'hover:shadow-lg';

                    switch ($ad['highlight_level']) {
                        case 'ultra_top':
                            $borderClass = 'border-yellow-400 hover:border-yellow-300';
                            $shadowClass = 'shadow-[0_0_15px_rgba(250,204,21,0.5)] hover:shadow-[0_0_25px_rgba(250,204,21,0.8)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-yellow-400 text-black text-[10px] font-black uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg> Ultra Top</div>';
                            break;
                        case 'super_top':
                            $borderClass = 'border-blue-400 hover:border-blue-300';
                            $shadowClass = 'shadow-[0_0_10px_rgba(96,165,250,0.3)] hover:shadow-[0_0_20px_rgba(96,165,250,0.6)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-blue-500 text-white text-[10px] font-bold uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"></path></svg> Super Top</div>';
                            break;
                        case 'super_highlight':
                            $borderClass = 'border-brand hover:border-brand-hover';
                            $shadowClass = 'shadow-[0_0_10px_rgba(219,39,119,0.3)] hover:shadow-[0_0_20px_rgba(219,39,119,0.6)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-brand text-white text-[10px] font-bold uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20">Super Destaque</div>';
                            break;
                        case 'paid_highlight':
                            $borderClass = 'border-pink-300 hover:border-pink-400';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-pink-500 text-white text-[10px] font-bold uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20">Destaque</div>';
                            break;
                    }
                    ?>
                    <!-- Ad Card -->
                    <a href="<?= $baseUrl ?>/anuncio/?id=<?= $ad['id'] ?>"
                        class="group bg-sup1 rounded-2xl overflow-hidden border-2 <?= $borderClass ?> <?= $shadowClass ?> transition relative">
                        <?= $badgeHtml ?>

                        <!-- Fetch the primary image dynamically -->
                        <?php
                        $stmtMedia = $pdo->prepare("SELECT file_path FROM ad_media WHERE ad_id = ? AND is_primary = 1");
                        $stmtMedia->execute([$ad['id']]);
                        $media = $stmtMedia->fetchColumn();
                        $imagePath = $media ?: 'https://via.placeholder.com/300x400/222/666?text=Foto';
                        ?>

                        <div class="aspect-[3/4] bg-sup2 relative overflow-hidden">
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                                class="object-cover w-full h-full group-hover:scale-105 transition duration-500">
                            
                            <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4">
                                <h3 class="text-xl font-bold text-texto">
                                    <?= htmlspecialchars(isset($ad['advertiser_name']) ? $ad['advertiser_name'] : $ad['title']) ?>
                                </h3>
                                <p class="text-sm text-secundario">
                                    <?= htmlspecialchars($ad['city']) ?>, <?= htmlspecialchars($ad['state']) ?>
                                    <?php if ($ad['age'])
                                        echo " • " . $ad['age'] . " anos"; ?>
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach;
    }
    ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
