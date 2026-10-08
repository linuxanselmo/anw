<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_visits` (
      `visit_date` DATE NOT NULL,
      `ip_hash` VARCHAR(64) NOT NULL,
      `page_views` INT DEFAULT 1,
      PRIMARY KEY (`visit_date`, `ip_hash`)
    )");
} catch (\Exception $e) {}

// Temporary route to clear analytics
if (isset($_GET['clear_analytics']) && $_GET['clear_analytics'] == '1') {
    $pdo->exec("TRUNCATE TABLE site_visits");
    $pdo->exec("UPDATE ads SET views = 0");
    header("Location: analytics.php");
    exit;
}

$filter = $_GET['filter'] ?? 'week';

$dateConditionVisits = "";
$dateParamsVisits = [];
$daysForChart = 7;
$labelFilter = "Últimos 7 dias";

if ($filter === 'day') {
    $dateConditionVisits = "WHERE visit_date = ?";
    $dateParamsVisits = [date('Y-m-d')];
    $daysForChart = 1;
    $labelFilter = "Hoje";
} elseif ($filter === 'month') {
    $dateConditionVisits = "WHERE visit_date >= ?";
    $dateParamsVisits = [date('Y-m-d', strtotime('-29 days'))];
    $daysForChart = 30;
    $labelFilter = "Últimos 30 dias";
} else {
    // week
    $filter = 'week';
    $dateConditionVisits = "WHERE visit_date >= ?";
    $dateParamsVisits = [date('Y-m-d', strtotime('-6 days'))];
    $daysForChart = 7;
    $labelFilter = "Últimos 7 dias";
}

// Fetch total visits (Filtered)
$stmtTotalVisits = $pdo->prepare("SELECT SUM(page_views) FROM site_visits $dateConditionVisits");
$stmtTotalVisits->execute($dateParamsVisits);
$totalVisits = (int) $stmtTotalVisits->fetchColumn();

// Fetch unique visitors (Filtered)
$stmtUniqueVisits = $pdo->prepare("SELECT COUNT(ip_hash) FROM site_visits $dateConditionVisits");
$stmtUniqueVisits->execute($dateParamsVisits);
$uniqueVisits = (int) $stmtUniqueVisits->fetchColumn();

// Fetch total users (Global)
$stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int) $stmtUsers->fetchColumn();

// Fetch total ads (Global)
$stmtAds = $pdo->query("SELECT COUNT(*) FROM ads");
$totalAds = (int) $stmtAds->fetchColumn();

// Most visited ad (Global)
$stmtMostVisited = $pdo->query("SELECT id, title, views, status FROM ads ORDER BY views DESC LIMIT 1");
$mostVisitedAd = $stmtMostVisited->fetch(PDO::FETCH_ASSOC);

// Top 10 Ads (Global)
$stmtTopAds = $pdo->query("SELECT id, title, views, status, created_at FROM ads ORDER BY views DESC LIMIT 10");
$topAds = $stmtTopAds->fetchAll(PDO::FETCH_ASSOC);

// Chart Data (Based on filter)
$chartData = [];
$labels = [];
$views = [];
$uniques = [];

if ($daysForChart === 1) {
    // If it's just today, let's show the last 7 days anyway so the chart isn't a single dot
    $daysForChart = 7;
}

for ($i = $daysForChart - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($date));
    
    // Fetch stats for this date
    $stmtDate = $pdo->prepare("SELECT SUM(page_views) as views, COUNT(ip_hash) as uniques FROM site_visits WHERE visit_date = ?");
    $stmtDate->execute([$date]);
    $res = $stmtDate->fetch(PDO::FETCH_ASSOC);
    
    $views[] = $res['views'] ? (int)$res['views'] : 0;
    $uniques[] = $res['uniques'] ? (int)$res['uniques'] : 0;
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Visão Geral do <span class="text-blue-500">Analytics</span></h1>
        <a href="<?= $baseUrl ?>/admin/" class="text-brand hover:text-brand/80 underline font-medium">Voltar ao Painel</a>
    </div>

    <!-- GA-like Main Chart Card -->
    <div class="bg-sup1 border border-borda rounded-2xl p-6 shadow-lg mb-8">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-texto">Resumo de tráfego</h2>
            <form id="filterForm" method="GET" action="">
                <div class="mt-4 md:mt-0 bg-sup2 border border-borda text-secundario rounded-lg px-2 py-1 text-sm flex items-center gap-2 focus-within:ring-1 focus-within:ring-blue-500 transition">
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <select name="filter" onchange="document.getElementById('filterForm').submit()" class="bg-transparent border-none focus:ring-0 outline-none text-texto cursor-pointer py-1 pr-6">
                        <option value="day" class="bg-sup2" <?= $filter === 'day' ? 'selected' : '' ?>>Hoje</option>
                        <option value="week" class="bg-sup2" <?= $filter === 'week' ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="month" class="bg-sup2" <?= $filter === 'month' ? 'selected' : '' ?>>Últimos 30 dias</option>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- KPI Tabs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 border-b border-borda pb-6">
            <div class="border-t-4 border-blue-500 pt-4 rounded-tl hover:bg-sup2 transition p-2 cursor-pointer">
                <div class="text-secundario text-sm font-medium mb-1 flex items-center gap-1 hover:text-texto transition">
                    Visitantes Únicos
                </div>
                <div class="text-3xl font-bold text-texto"><?= number_format($uniqueVisits, 0, ',', '.') ?></div>
            </div>
            <div class="border-t-4 border-transparent pt-4 hover:bg-sup2 transition p-2 cursor-pointer group">
                <div class="text-secundario text-sm font-medium mb-1 flex items-center gap-1 group-hover:text-texto transition">
                    Visualizações Totais
                </div>
                <div class="text-3xl font-bold text-texto"><?= number_format($totalVisits, 0, ',', '.') ?></div>
            </div>
            <div class="border-t-4 border-transparent pt-4 hover:bg-sup2 transition p-2 cursor-pointer group">
                <div class="text-secundario text-sm font-medium mb-1 flex items-center gap-1 group-hover:text-texto transition">
                    Total de Anúncios
                </div>
                <div class="text-3xl font-bold text-texto"><?= number_format($totalAds, 0, ',', '.') ?></div>
            </div>
            <div class="border-t-4 border-transparent pt-4 hover:bg-sup2 transition p-2 cursor-pointer group">
                <div class="text-secundario text-sm font-medium mb-1 flex items-center gap-1 group-hover:text-texto transition">
                    Usuários Cadastrados
                </div>
                <div class="text-3xl font-bold text-texto"><?= number_format($totalUsers, 0, ',', '.') ?></div>
            </div>
        </div>

        <!-- Chart Area -->
        <div class="relative h-80 w-full">
            <canvas id="gaChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Ranking de Anúncios (Takes 2 columns) -->
        <div class="lg:col-span-2 bg-sup1 border border-borda rounded-2xl overflow-hidden shadow-lg">
            <div class="p-6 border-b border-borda flex justify-between items-center">
                <h3 class="text-lg font-bold text-texto">Páginas e telas principais</h3>
                <span class="text-xs text-secundario">Visualizações</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-texto text-sm">
                    <thead class="bg-sup2 text-secundario">
                        <tr>
                            <th class="px-6 py-3 font-medium">Título da Página (Anúncio)</th>
                            <th class="px-6 py-3 font-medium text-right">Visualizações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-borda">
                        <?php foreach ($topAds as $ad): ?>
                        <tr class="hover:bg-sup2 transition group">
                            <td class="px-6 py-3">
                                <a href="<?= $baseUrl ?>/anuncio/?id=<?= $ad['id'] ?>" target="_blank" class="flex items-center gap-2 text-blue-400 hover:underline">
                                    <?= htmlspecialchars($ad['title']) ?>
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>
                            </td>
                            <td class="px-6 py-3 text-right font-medium">
                                <?= number_format($ad['views'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topAds)): ?>
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-secundario">Nenhum dado disponível.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Anúncio Mais Visitado (Insights) -->
        <div class="bg-sup1 border border-borda rounded-2xl shadow-lg flex flex-col">
            <div class="p-6 border-b border-borda">
                <h3 class="text-lg font-bold text-texto flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    Insights
                </h3>
            </div>
            <div class="p-6 flex-grow flex flex-col justify-center">
                <?php if ($mostVisitedAd): ?>
                    <p class="text-secundario text-sm mb-4">O anúncio com melhor performance nos últimos tempos foi:</p>
                    <h4 class="text-xl font-bold text-texto mb-2"><?= htmlspecialchars($mostVisitedAd['title']) ?></h4>
                    <div class="text-3xl font-bold text-blue-500 mb-6"><?= number_format($mostVisitedAd['views'], 0, ',', '.') ?> <span class="text-sm font-normal text-secundario">visitas</span></div>
                    
                    <a href="<?= $baseUrl ?>/anuncio/?id=<?= $mostVisitedAd['id'] ?>" target="_blank" class="w-full bg-sup2 hover:bg-borda text-texto text-center font-medium py-3 rounded-lg transition border border-borda">
                        Ver detalhes do anúncio
                    </a>
                <?php else: ?>
                    <p class="text-secundario text-center">Ainda não há dados suficientes para gerar insights.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const ctx = document.getElementById('gaChart').getContext('2d');
    
    // GA4 Style Blue Gradient
    const gradientBlue = ctx.createLinearGradient(0, 0, 0, 400);
    gradientBlue.addColorStop(0, 'rgba(66, 133, 244, 0.4)'); // Light blue
    gradientBlue.addColorStop(1, 'rgba(66, 133, 244, 0.0)');
    
    // GA4 Style Secondary (Cyan/Teal)
    const gradientTeal = ctx.createLinearGradient(0, 0, 0, 400);
    gradientTeal.addColorStop(0, 'rgba(38, 166, 154, 0.4)');
    gradientTeal.addColorStop(1, 'rgba(38, 166, 154, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Visitantes Únicos',
                    data: <?= json_encode($uniques) ?>,
                    borderColor: '#4285F4', // Google Blue
                    backgroundColor: gradientBlue,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#4285F4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Visualizações Totais',
                    data: <?= json_encode($views) ?>,
                    borderColor: '#26A69A', // Teal
                    backgroundColor: gradientTeal,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#26A69A',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: {
                        color: '#FFF7FA',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 13
                        },
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(23, 11, 29, 0.95)',
                    titleColor: '#FFF7FA',
                    bodyColor: '#C9B8C6',
                    borderColor: '#45204F',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 14, family: "'Inter', sans-serif" },
                    bodyFont: { size: 13, family: "'Inter', sans-serif" }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(69, 32, 79, 0.0)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#C9B8C6',
                        font: { family: "'Inter', sans-serif" }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(69, 32, 79, 0.4)',
                        drawBorder: false,
                        borderDash: [5, 5]
                    },
                    ticks: {
                        color: '#C9B8C6',
                        stepSize: 1,
                        font: { family: "'Inter', sans-serif" }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
