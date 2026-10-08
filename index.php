<?php
require_once __DIR__ . '/includes/header.php';

// Logic from HomeController
$stmtCats = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmtCats->fetchAll();

$sql = "SELECT a.*, u.name as advertiser_name, al.city, al.state 
        FROM ads a 
        JOIN users u ON a.user_id = u.id 
        JOIN ad_categories ac ON a.id = ac.ad_id
        JOIN categories c ON ac.category_id = c.id AND c.slug = 'mulheres'
        LEFT JOIN (SELECT ad_id, MIN(city) as city, MIN(state) as state FROM ad_locations GROUP BY ad_id) al ON a.id = al.ad_id
        WHERE a.status = 'active' 
        AND a.service_type IN ('video', 'both')
        AND (a.expires_at IS NULL OR a.expires_at > NOW())";
$params = [];

if (!empty($_GET['state'])) {
    $sql .= " AND al.state = ?";
    $params[] = $_GET['state'];
}

if (!empty($_GET['city'])) {
    $sql .= " AND al.city LIKE ?";
    $params[] = '%' . $_GET['city'] . '%';
}

$sql .= " ORDER BY FIELD(a.plan, 'ultra_top', 'super_top', 'super_highlight', 'paid_highlight', 'organic'), a.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ads = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Hero / Title -->
    <div class="text-center mb-12">
        <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4 text-texto">
            <span class="text-brand">Chamadas de Vídeo</span> com Acompanhantes Reais
        </h1>
        <p class="text-secundario text-lg max-w-2xl mx-auto">
            Conecte-se com acompanhantes reais em tempo real. Perfis 100% verificados para garantir sua diversão e privacidade.
        </p>
    </div>

    <!-- Quick Categories -->
    <div class="flex overflow-x-auto pb-4 mb-8 gap-4 no-scrollbar">
        <?php foreach ($categories as $cat): ?>
            <a href="<?= $baseUrl ?>/anuncios/?category=<?= $cat['id'] ?>"
                class="flex-shrink-0 bg-sup1 text-texto hover:bg-brand transition px-6 py-3 rounded-full text-sm font-semibold border border-borda hover:border-brand">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Location Filter -->
    <div class="bg-sup1 border border-brand/30 rounded-2xl p-4 mb-8 shadow-lg shadow-brand/5">
        <form action="<?= $baseUrl ?>/" method="GET" class="flex flex-col md:flex-row gap-4 items-center">
            <div class="md:w-64 w-full relative">
                <select id="state_select" name="state"
                    class="w-full bg-sup2 border border-borda rounded-lg px-4 py-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand outline-none transition appearance-none cursor-pointer">
                    <option value="">Qualquer Estado</option>
                    <?php
                    $states = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                    foreach ($states as $st):
                        $selected = (isset($_GET['state']) && $_GET['state'] === $st) ? 'selected' : '';
                        ?>
                        <option value="<?= $st ?>" <?= $selected ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-secundario">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                        <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"></path>
                    </svg>
                </div>
            </div>

            <div class="flex-1 w-full relative">
                <select id="city_select" name="city"
                    class="w-full bg-sup2 border border-borda rounded-lg px-4 py-3 text-texto focus:border-brand focus:ring-1 focus:ring-brand outline-none transition appearance-none cursor-pointer">
                    <option value="">Qualquer Cidade</option>
                    <?php if (!empty($_GET['city'])): ?>
                        <option value="<?= htmlspecialchars($_GET['city']) ?>" selected>
                            <?= htmlspecialchars($_GET['city']) ?>
                        </option>
                    <?php endif; ?>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-secundario">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                        <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"></path>
                    </svg>
                </div>
            </div>
            <button type="submit"
                class="w-full md:w-auto bg-brand text-white font-bold py-3 px-8 rounded-lg hover:bg-brand-hover transition whitespace-nowrap flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Filtrar
            </button>

            <?php if (!empty($_GET['city']) || !empty($_GET['state'])): ?>
                <a href="<?= $baseUrl ?>/" class="w-full md:w-auto text-center text-secundario hover:text-white transition px-4 py-3">
                    Limpar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Video Calls Banner -->
    <div class="bg-gradient-to-r from-sup1 to-sup2 border border-brand/30 rounded-2xl p-6 mb-12 flex flex-col md:flex-row items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold flex items-center gap-2 mb-2 text-texto">
                <svg class="w-6 h-6 text-brand animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                Chamadas de Vídeo
            </h2>
            <p class="text-secundario">Anunciantes disponíveis para chamadas de vídeo neste exato momento.</p>
        </div>
        <a href="<?= $baseUrl ?>/anuncios/?online=1"
            class="mt-4 md:mt-0 bg-texto text-black hover:bg-gray-200 px-6 py-2 rounded-full font-bold transition">
            Ver Anunciantes
        </a>
    </div>

    <!-- Highlights Section -->
    <?php
    if (empty($ads)) {
        echo '<div class="col-span-full text-center py-12 text-secundario bg-sup1 rounded-2xl border border-borda">Nenhum anúncio no momento. <a href="' . $baseUrl . '/painel/criar.php" class="text-brand hover:underline">Seja a primeira!</a></div>';
    } else {
        $destaques = array_filter($ads, function ($a) {
            $plan = !empty($a['plan']) ? $a['plan'] : 'organic';
            return $plan !== 'organic';
        });

        $organicos = array_filter($ads, function ($a) {
            $plan = !empty($a['plan']) ? $a['plan'] : 'organic';
            return $plan === 'organic';
        });

        $sections = [
            ['title' => '⭐⭐ Destaques', 'ads' => $destaques],
            ['title' => 'Anúncios Orgânicos', 'ads' => $organicos]
        ];

        foreach ($sections as $section):
            if (empty($section['ads'])) continue;
            ?>

            <h2 class="text-3xl font-bold mb-6 mt-12 text-texto border-b border-borda pb-2"><?= $section['title'] ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($section['ads'] as $ad):
                    $borderClass = 'border-borda hover:border-brand';
                    $badgeHtml = '';
                    $shadowClass = 'hover:shadow-lg';

                    $plan = !empty($ad['plan']) ? $ad['plan'] : 'organic';
                    switch ($plan) {
                        case 'ultra_top':
                            $borderClass = 'border-[#00bfff] hover:border-[#00ffff]';
                            $shadowClass = 'shadow-[0_0_20px_rgba(0,191,255,0.6)] hover:shadow-[0_0_30px_rgba(0,255,255,0.9)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-gradient-to-r from-[#00bfff] to-[#00ffff] text-black text-[11px] font-black uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.5 5h5.5l-4.5 4.5 1.5 6.5-5-3.5-5 3.5 1.5-6.5-4.5-4.5h5.5z"></path></svg> Ultra Top</div>';
                            break;
                        case 'super_top':
                            $borderClass = 'border-yellow-400 hover:border-yellow-300';
                            $shadowClass = 'shadow-[0_0_15px_rgba(250,204,21,0.5)] hover:shadow-[0_0_25px_rgba(250,204,21,0.8)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-gradient-to-r from-yellow-600 to-yellow-400 text-black text-[10px] font-black uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg> Super Top</div>';
                            break;
                        case 'super_highlight':
                            $borderClass = 'border-gray-400 hover:border-gray-300';
                            $shadowClass = 'shadow-[0_0_10px_rgba(156,163,175,0.4)] hover:shadow-[0_0_20px_rgba(156,163,175,0.7)]';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-gradient-to-r from-gray-500 to-gray-300 text-black text-[10px] font-bold uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20">Super Destaque</div>';
                            break;
                        case 'paid_highlight':
                            $borderClass = 'border-amber-700 hover:border-amber-600';
                            $badgeHtml = '<div class="absolute top-0 right-0 bg-gradient-to-r from-amber-800 to-amber-600 text-white text-[10px] font-bold uppercase px-3 py-1 rounded-bl-lg shadow-lg z-20">Destaque</div>';
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

                        <!-- Image -->
                        <div class="aspect-[3/4] bg-sup2 relative overflow-hidden">
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                                class="object-cover w-full h-full group-hover:scale-105 transition duration-500">
                            <!-- Watermark -->
                            <div class="absolute bottom-2 right-2 pointer-events-none opacity-40 z-10">
                                <span class="text-texto/50 font-bold text-[10px] tracking-wider drop-shadow-md">ANW.COM.BR</span>
                            </div>
                            <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4">
                                <h3 class="text-xl font-bold text-texto">
                                    <?= htmlspecialchars(isset($ad['advertiser_name']) ? $ad['advertiser_name'] : $ad['title']) ?>
                                </h3>
                                <p class="text-sm text-secundario">
                                    <?php if (isset($ad['service_type']) && $ad['service_type'] === 'video'): ?>
                                        Atendimento Online
                                    <?php else: ?>
                                        <?= htmlspecialchars($ad['city']) ?>, <?= htmlspecialchars($ad['state']) ?>
                                    <?php endif; ?>
                                    <?php if ($ad['age'])
                                        echo $ad['age'] . " anos"; ?>
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php
        endforeach;
    }
    ?>
</div>

<!-- IBGE State/City Fetcher -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const stateSelect = document.getElementById('state_select');
        const citySelect = document.getElementById('city_select');

        if (stateSelect.value) {
            fetchCities(stateSelect.value, "<?= htmlspecialchars(isset($_GET['city']) ? $_GET['city'] : '') ?>");
        }

        stateSelect.addEventListener('change', () => {
            const uf = stateSelect.value;
            if (!uf) {
                citySelect.innerHTML = '<option value="">Qualquer Cidade</option>';
                return;
            }
            fetchCities(uf);
        });

        function fetchCities(uf, selectedCity = '') {
            const defaultText = selectedCity ? 'Carregando cidades...' : 'Selecione a cidade...';
            citySelect.innerHTML = `<option value="">${defaultText}</option>`;

            fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`)
                .then(response => response.json())
                .then(cities => {
                    citySelect.innerHTML = '<option value="">Qualquer Cidade</option>';
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.nome;
                        option.textContent = city.nome;
                        if (city.nome === selectedCity) {
                            option.selected = true;
                        }
                        citySelect.appendChild(option);
                    });
                })
                .catch(error => console.error("Erro ao carregar cidades:", error));
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
