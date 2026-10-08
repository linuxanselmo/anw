<?php
require_once __DIR__ . '/../includes/header.php';

// Fetch categories and count of active ads in each
$stmt = $pdo->query("
    SELECT c.*, COUNT(ac.ad_id) as total_ads 
    FROM categories c 
    LEFT JOIN ad_categories ac ON c.id = ac.category_id 
    LEFT JOIN ads a ON ac.ad_id = a.id AND a.status = 'active'
    GROUP BY c.id
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-20">
    <div class="text-center max-w-3xl mx-auto mb-12">
        <h1 class="text-4xl md:text-5xl font-extrabold text-texto mb-6 tracking-tighter">
            Encontre por <span class="text-brand">Categoria</span>
        </h1>
        <p class="text-lg text-secundario">
            Navegue por nossas categorias e encontre o acompanhante perfeito para o seu momento.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach($categories as $cat): ?>
            <a href="<?= $baseUrl ?>/anuncios/?category=<?= htmlspecialchars($cat['slug']) ?>" class="group block relative overflow-hidden rounded-2xl bg-sup1 border border-borda hover:border-brand transition-all duration-300 transform hover:-translate-y-1 shadow-lg shadow-black/50 aspect-square flex flex-col justify-center items-center">
                <!-- Background glow effect on hover -->
                <div class="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <h2 class="text-3xl font-extrabold text-texto group-hover:text-brand transition-colors z-10"><?= htmlspecialchars($cat['name']) ?></h2>
                <div class="mt-4 text-secundario bg-sup2 px-4 py-1 rounded-full text-sm font-bold border border-borda z-10">
                    <?= $cat['total_ads'] ?> anúncios
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
