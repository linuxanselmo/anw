<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: {$baseUrl}/login/");
    exit;
}

// ---- Ações POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Helper: apaga arquivos físicos de um usuário
    $deleteUserFiles = function(int $uid) use ($pdo): void {
        $uploadsDir = __DIR__ . '/../uploads/';

        // Míias dos anúncios
        $stmtM = $pdo->prepare(
            "SELECT am.file_path FROM ad_media am
              JOIN ads a ON am.ad_id = a.id
             WHERE a.user_id = ?"
        );
        $stmtM->execute([$uid]);
        foreach ($stmtM->fetchAll() as $m) {
            $file = $uploadsDir . basename($m['file_path']);
            if (file_exists($file)) @unlink($file);
        }

        // Mídia de verificação do usuário
        $stmtV = $pdo->prepare("SELECT verification_media FROM users WHERE id = ?");
        $stmtV->execute([$uid]);
        $vm = $stmtV->fetchColumn();
        if ($vm) {
            $vFile = $uploadsDir . 'verifications/' . basename($vm);
            if (file_exists($vFile)) @unlink($vFile);
            // tenta também sem subpasta (compatibilidade)
            $vFile2 = $uploadsDir . basename($vm);
            if (file_exists($vFile2)) @unlink($vFile2);
        }
    };

    // Exclusão em massa
    if ($_POST['action'] === 'bulk_delete' && !empty($_POST['ids'])) {
        $ids = array_filter(array_map('intval', (array)$_POST['ids']));
        $ids = array_values(array_diff($ids, [(int)$_SESSION['user_id']]));

        foreach ($ids as $uid) {
            $deleteUserFiles($uid);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$uid]);
        }
        header("Location: {$baseUrl}/admin/users.php");
        exit;
    }

    // Exclusão individual
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id != $_SESSION['user_id']) {
            $deleteUserFiles($id);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: {$baseUrl}/admin/users.php");
        exit;
    }

    // Alterar papel
    if ($_POST['action'] === 'change_role' && isset($_POST['id'], $_POST['role'])) {
        $id   = (int)$_POST['id'];
        $role = $_POST['role'];
        $validRolesPost = ['admin', 'advertiser', 'client'];
        if ($id != $_SESSION['user_id'] && in_array($role, $validRolesPost)) {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $id]);
        }
        // Redireciona preservando os filtros
        $qs = http_build_query(array_filter([
            'search'   => $_POST['search']   ?? '',
            'role'     => $_POST['filt_role'] ?? '',
            'status'   => $_POST['status']   ?? '',
            'ads'      => $_POST['ads']      ?? '',
            'per_page' => $_POST['per_page'] ?? '',
            'page'     => $_POST['page']     ?? '',
        ]));
        header("Location: {$baseUrl}/admin/users.php" . ($qs ? "?{$qs}" : ''));
        exit;
    }

    // Editar dados do usuário
    if ($_POST['action'] === 'edit_user' && isset($_POST['id'])) {
        $id       = (int)$_POST['id'];
        $name     = trim($_POST['name']  ?? '');
        $email    = trim($_POST['email'] ?? '');
        $cpf      = trim($_POST['cpf']   ?? '');
        $newPass  = $_POST['new_password']     ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        $errors = [];
        if ($name  === '') $errors[] = 'Nome é obrigatório.';
        if ($email === '') $errors[] = 'E-mail é obrigatório.';
        if ($newPass !== '' && $newPass !== $confPass) $errors[] = 'As senhas não coincidem.';
        if ($newPass !== '' && strlen($newPass) < 6)   $errors[] = 'A senha deve ter ao menos 6 caracteres.';

        // Verifica email duplicado (ignorando o próprio usuário)
        if ($email !== '') {
            $stmtChk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtChk->execute([$email, $id]);
            if ($stmtChk->fetchColumn()) $errors[] = 'E-mail já está em uso por outro usuário.';
        }

        if (empty($errors)) {
            if ($newPass !== '') {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, cpf=?, password=? WHERE id=?");
                $stmt->execute([$name, $email, $cpf ?: null, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, cpf=? WHERE id=?");
                $stmt->execute([$name, $email, $cpf ?: null, $id]);
            }
            $qs = http_build_query(array_filter([
                'search'   => $_GET['search']   ?? '',
                'role'     => $_GET['role']     ?? '',
                'status'   => $_GET['status']   ?? '',
                'ads'      => $_GET['ads']      ?? '',
                'per_page' => $_GET['per_page'] ?? '',
                'page'     => $_GET['page']     ?? '',
                'edited'   => $id,
            ]));
            header("Location: {$baseUrl}/admin/users.php" . ($qs ? "?{$qs}" : ''));
            exit;
        }
        // Se houver erros, armazena para exibir (via session)
        $_SESSION['edit_errors'] = $errors;
        $_SESSION['edit_uid']    = $id;
        header("Location: {$baseUrl}/admin/users.php");
        exit;
    }
} // fim if POST

// ---- Filtros ----
$search    = trim($_GET['search'] ?? '');
$filterRole   = $_GET['role']   ?? '';
$filterStatus = $_GET['status'] ?? '';

$filterAds = $_GET['ads'] ?? '';

$validRoles    = ['admin', 'advertiser', 'client'];
$validStatuses = ['active', 'pending_verification', 'pending_approval', 'rejected'];

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR cpf LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($filterRole, $validRoles)) {
    $where[]  = 'role = ?';
    $params[] = $filterRole;
}
if (in_array($filterStatus, $validStatuses)) {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterAds === 'com') {
    $where[] = 'EXISTS (SELECT 1 FROM ads WHERE ads.user_id = users.id)';
} elseif ($filterAds === 'sem') {
    $where[] = 'NOT EXISTS (SELECT 1 FROM ads WHERE ads.user_id = users.id)';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Paginação ----
$allowedPerPage = [20, 40, 80, 100, 200];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage)
    ? (int)$_GET['per_page']
    : 20;
$showAll = isset($_GET['per_page']) && $_GET['per_page'] === 'todos';

$stmtCount  = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
$stmtCount->execute($params);
$totalUsers = (int)$stmtCount->fetchColumn();

// total global (sem filtro) para exibir no label
$globalTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$totalPages  = $showAll ? 1 : max(1, (int)ceil($totalUsers / $perPage));
$currentPage = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset      = $showAll ? 0 : ($currentPage - 1) * $perPage;
$limitSql    = $showAll ? '' : "LIMIT {$perPage} OFFSET {$offset}";

$stmtUsers = $pdo->prepare("SELECT id, name, email, role, cpf, status, verification_media, created_at
                             FROM users {$whereSql} ORDER BY created_at DESC {$limitSql}");
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll();

// ---- Anúncios (para popup + badge) ----
$stmtAds = $pdo->query("SELECT id, user_id, title, status, highlight_level, phone, views, clicks FROM ads ORDER BY created_at DESC");
$allAds  = $stmtAds->fetchAll();
$adsByUser = [];
foreach ($allAds as $ad) {
    $adsByUser[$ad['user_id']][] = $ad;
}

// Helper: monta query string preservando filtros + parâmetro novo
function buildQs(array $override = []): string {
    $base = [
        'search'   => trim($_GET['search']   ?? ''),
        'role'     => $_GET['role']     ?? '',
        'status'   => $_GET['status']   ?? '',
        'ads'      => $_GET['ads']      ?? '',
        'per_page' => $_GET['per_page'] ?? '20',
        'page'     => $_GET['page']     ?? '1',
    ];
    $merged = array_merge($base, $override);
    $merged = array_filter($merged, fn($v) => $v !== '');
    return '?' . http_build_query($merged);
}

$hasFilter = ($search !== '' || in_array($filterRole, $validRoles) || in_array($filterStatus, $validStatuses) || in_array($filterAds, ['com', 'sem']));
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-12">
    <!-- Cabeçalho -->
    <div class="flex items-center mb-6">
        <a href="<?= $baseUrl ?>/admin/" class="text-secundario hover:text-brand transition mr-4">&larr; Voltar</a>
        <h1 class="text-3xl font-bold text-texto tracking-tighter">Gerenciar <span class="text-brand">Usuários</span></h1>
    </div>

    <!-- Barra de filtros -->
    <form method="GET" action="" id="filterForm"
          class="bg-sup1 border border-borda rounded-2xl p-4 mb-4 flex flex-col lg:flex-row gap-3 items-stretch lg:items-end">

        <!-- Busca -->
        <div class="flex-1 min-w-0">
            <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Buscar</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-secundario pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" id="search_input"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Nome, e-mail ou CPF…"
                       class="w-full bg-sup2 border border-borda rounded-lg pl-9 pr-3 py-2 text-sm text-texto placeholder-secundario outline-none focus:border-brand transition">
            </div>
        </div>

        <!-- Papel -->
        <div class="w-full lg:w-40">
            <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Papel</label>
            <select name="role" onchange="document.getElementById('filterForm').submit()"
                    class="w-full bg-sup2 border border-borda text-texto text-sm rounded-lg px-3 py-2 outline-none focus:border-brand transition cursor-pointer">
                <option value="">Todos</option>
                <option value="admin"      <?= $filterRole === 'admin'      ? 'selected' : '' ?>>Admin</option>
                <option value="advertiser" <?= $filterRole === 'advertiser' ? 'selected' : '' ?>>Anunciante</option>
                <option value="client"     <?= $filterRole === 'client'     ? 'selected' : '' ?>>Cliente</option>
            </select>
        </div>

        <!-- Status -->
        <div class="w-full lg:w-48">
            <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Status</label>
            <select name="status" onchange="document.getElementById('filterForm').submit()"
                    class="w-full bg-sup2 border border-borda text-texto text-sm rounded-lg px-3 py-2 outline-none focus:border-brand transition cursor-pointer">
                <option value="">Todos</option>
                <option value="active"               <?= $filterStatus === 'active'               ? 'selected' : '' ?>>Ativo</option>
                <option value="pending_verification" <?= $filterStatus === 'pending_verification' ? 'selected' : '' ?>>Pend. Verificação</option>
                <option value="pending_approval"     <?= $filterStatus === 'pending_approval'     ? 'selected' : '' ?>>Pend. Aprovação</option>
                <option value="rejected"             <?= $filterStatus === 'rejected'             ? 'selected' : '' ?>>Rejeitado</option>
            </select>
        </div>

        <!-- Anúncios -->
        <div class="w-full lg:w-40">
            <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Anúncios</label>
            <select name="ads" onchange="document.getElementById('filterForm').submit()"
                    class="w-full bg-sup2 border border-borda text-texto text-sm rounded-lg px-3 py-2 outline-none focus:border-brand transition cursor-pointer">
                <option value="">Todos</option>
                <option value="com" <?= $filterAds === 'com' ? 'selected' : '' ?>>Com anúncio</option>
                <option value="sem" <?= $filterAds === 'sem' ? 'selected' : '' ?>>Sem anúncio</option>
            </select>
        </div>

        <!-- Exibir por página -->
        <div class="w-full lg:w-36">
            <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Exibir</label>
            <select name="per_page" onchange="document.getElementById('filterForm').submit()"
                    class="w-full bg-sup2 border border-borda text-texto text-sm rounded-lg px-3 py-2 outline-none focus:border-brand transition cursor-pointer">
                <?php foreach ([20, 40, 80, 100, 200] as $opt): ?>
                    <option value="<?= $opt ?>" <?= (!$showAll && $perPage === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
                <option value="todos" <?= $showAll ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>

        <!-- Botões -->
        <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 lg:flex-none bg-brand hover:bg-brand-hover text-white text-sm font-bold px-4 py-2 rounded-lg transition whitespace-nowrap">
                Filtrar
            </button>
            <?php if ($hasFilter): ?>
            <a href="<?= $baseUrl ?>/admin/users.php?per_page=<?= $showAll ? 'todos' : $perPage ?>"
               class="flex-1 lg:flex-none bg-sup2 hover:bg-borda text-secundario hover:text-texto text-sm font-medium px-4 py-2 rounded-lg transition text-center whitespace-nowrap">
                Limpar
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Contador de resultados -->
    <div class="flex items-center gap-2 mb-3">
        <p class="text-secundario text-sm">
            <?php if ($hasFilter): ?>
                <span class="text-brand font-bold"><?= $totalUsers ?></span> resultado<?= $totalUsers !== 1 ? 's' : '' ?> encontrado<?= $totalUsers !== 1 ? 's' : '' ?>
                <span class="text-borda mx-1">·</span> de <?= $globalTotal ?> usuários no total
            <?php else: ?>
                <strong class="text-texto"><?= $totalUsers ?></strong> usuários cadastrados
            <?php endif; ?>
        </p>
        <?php if ($hasFilter): ?>
        <span class="bg-brand/10 border border-brand/30 text-brand text-[10px] font-bold uppercase px-2 py-0.5 rounded-full tracking-wider">Filtrado</span>
        <?php endif; ?>
    </div>

    <!-- Barra de seleção em massa (oculta até selecionar) -->
    <div id="bulkBar"
         class="hidden fixed bottom-24 md:bottom-6 left-1/2 -translate-x-1/2 z-[150]
                bg-sup1 border border-borda rounded-2xl shadow-2xl px-5 py-3
                flex items-center gap-4 min-w-[320px]">
        <span class="text-texto text-sm font-medium">
            <span id="bulkCount" class="text-brand font-bold">0</span> selecionado(s)
        </span>
        <div class="flex-1"></div>
        <button type="button" onclick="clearSelection()"
                class="text-secundario hover:text-texto text-sm transition">
            Cancelar
        </button>
        <button type="button" id="bulkDeleteBtn"
                onclick="submitBulkDelete()"
                class="bg-red-600 hover:bg-red-500 text-white text-sm font-bold px-4 py-2 rounded-lg transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Excluir selecionados
        </button>
    </div>

    <!-- Form oculto para exclusão em massa -->
    <form id="bulkDeleteForm" method="POST" action="<?= $baseUrl ?>/admin/users.php">
        <input type="hidden" name="action" value="bulk_delete">
        <div id="bulkIdsContainer"></div>
    </form>

    <div class="bg-sup1 border border-borda rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-sup2 text-secundario text-sm uppercase tracking-wider">
                        <!-- Checkbox selecionar todos -->
                        <th class="px-4 py-4 border-b border-borda w-10">
                            <input type="checkbox" id="selectAll"
                                   class="w-4 h-4 rounded border-borda bg-sup2 text-brand cursor-pointer"
                                   onclick="toggleAll(this)">
                        </th>
                        <th class="px-4 py-4 font-bold border-b border-borda">ID</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Nome</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">E-mail</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Papel</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Status</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Anúncios</th>
                        <th class="px-6 py-4 font-bold border-b border-borda">Data</th>
                        <th class="px-6 py-4 font-bold border-b border-borda text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-borda text-gray-300">
                    <?php foreach($users as $user): ?>
                    <?php
                        $statusLabels = [
                            'active'               => ['label' => 'Ativo',             'class' => 'bg-green-500/20 text-green-400'],
                            'pending_verification' => ['label' => 'Pend. Verificação', 'class' => 'bg-yellow-500/20 text-yellow-400'],
                            'pending_approval'     => ['label' => 'Pend. Aprovação',   'class' => 'bg-orange-500/20 text-orange-400'],
                            'rejected'             => ['label' => 'Rejeitado',          'class' => 'bg-red-500/20 text-red-400'],
                        ];
                        $s = $statusLabels[$user['status']] ?? ['label' => ($user['status'] ?? '—'), 'class' => 'bg-gray-500/20 text-gray-400'];
                        $isSelf = ($user['id'] == $_SESSION['user_id']);
                    ?>
                    <tr class="hover:bg-sup2/50 transition cursor-pointer group row-item <?= $isSelf ? 'opacity-60' : '' ?>"
                        data-id="<?= $user['id'] ?>"
                        onclick="handleRowClick(event, <?= $user['id'] ?>, <?= $isSelf ? 'true' : 'false' ?>)">
                        <!-- Checkbox -->
                        <td class="px-4 py-4" onclick="event.stopPropagation()">
                            <?php if (!$isSelf): ?>
                            <input type="checkbox" class="row-checkbox w-4 h-4 rounded border-borda bg-sup2 text-brand cursor-pointer"
                                   value="<?= $user['id'] ?>" onchange="updateBulkBar()">
                            <?php else: ?>
                            <span class="w-4 h-4 block"></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-texto">#<?= $user['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-texto group-hover:text-brand transition"><?= htmlspecialchars($user['name']) ?></td>
                        <td class="px-6 py-4 text-texto"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="px-6 py-4" onclick="event.stopPropagation()">
                            <?php if ($isSelf): ?>
                                <span class="bg-brand/20 text-brand px-2 py-1 rounded text-xs font-bold uppercase">Admin</span>
                            <?php else: ?>
                            <form method="POST" action="<?= $baseUrl ?>/admin/users.php">
                                <input type="hidden" name="action"    value="change_role">
                                <input type="hidden" name="id"        value="<?= $user['id'] ?>">
                                <input type="hidden" name="search"    value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="filt_role" value="<?= htmlspecialchars($filterRole) ?>">
                                <input type="hidden" name="status"    value="<?= htmlspecialchars($filterStatus) ?>">
                                <input type="hidden" name="ads"       value="<?= htmlspecialchars($filterAds) ?>">
                                <input type="hidden" name="per_page"  value="<?= $showAll ? 'todos' : $perPage ?>">
                                <input type="hidden" name="page"      value="<?= $currentPage ?>">
                                <select name="role" onchange="this.form.submit()"
                                        class="bg-sup2 border border-borda text-xs font-bold rounded-md px-2 py-1 outline-none
                                               focus:border-brand transition cursor-pointer
                                               <?= $user['role'] === 'admin' ? 'text-brand' : ($user['role'] === 'advertiser' ? 'text-blue-400' : 'text-gray-400') ?>">
                                    <option value="admin"      <?= $user['role'] === 'admin'      ? 'selected' : '' ?>>Admin</option>
                                    <option value="advertiser" <?= $user['role'] === 'advertiser' ? 'selected' : '' ?>>Anunciante</option>
                                    <option value="client"     <?= $user['role'] === 'client'     ? 'selected' : '' ?>>Cliente</option>
                                </select>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="<?= $s['class'] ?> px-2 py-1 rounded text-xs font-bold uppercase"><?= $s['label'] ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <?php $adsCount = count($adsByUser[$user['id']] ?? []); ?>
                            <?php if ($adsCount > 0): ?>
                                <span class="bg-green-500/20 text-green-400 px-2 py-1 rounded text-xs font-bold"><?= $adsCount ?> anúncio<?= $adsCount > 1 ? 's' : '' ?></span>
                            <?php else: ?>
                                <span class="bg-gray-500/10 text-secundario px-2 py-1 rounded text-xs">Nenhum</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-secundario"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-3">
                                <button onclick="openUserModal(<?= $user['id'] ?>)" class="text-blue-400 hover:text-blue-300 font-medium text-sm transition">
                                    Ver Detalhes
                                </button>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form action="<?= $baseUrl ?>/admin/users.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este usuário permanentemente? Isso apagará todos os seus anúncios.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-sm transition">Excluir</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-secundario text-sm italic">Você</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-secundario">Nenhum usuário encontrado.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <?php if (!$showAll && $totalPages > 1): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4">
        <p class="text-secundario text-sm">
            Página <strong class="text-texto"><?= $currentPage ?></strong> de <strong class="text-texto"><?= $totalPages ?></strong>
            &nbsp;·&nbsp; Mostrando <?= count($users) ?> de <?= $totalUsers ?> usuários
        </p>
        <div class="flex items-center gap-1">
            <!-- Primeira -->
            <a href="<?= buildQs(['page' => 1]) ?>"
               class="px-2 py-1.5 rounded-lg text-sm <?= $currentPage <= 1 ? 'text-borda pointer-events-none' : 'text-secundario hover:text-white hover:bg-sup2' ?> transition">«</a>
            <!-- Anterior -->
            <a href="<?= buildQs(['page' => max(1, $currentPage - 1)]) ?>"
               class="px-3 py-1.5 rounded-lg text-sm <?= $currentPage <= 1 ? 'text-borda pointer-events-none' : 'text-secundario hover:text-white hover:bg-sup2' ?> transition">
                ‹ Anterior
            </a>
            <?php
            $window = 2;
            $start  = max(1, $currentPage - $window);
            $end    = min($totalPages, $currentPage + $window);
            if ($start > 1) echo '<span class="text-secundario px-1">…</span>';
            for ($p = $start; $p <= $end; $p++):
            ?>
            <a href="<?= buildQs(['page' => $p]) ?>"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                      <?= $p === $currentPage ? 'bg-brand text-white shadow-lg shadow-brand/30' : 'text-secundario hover:text-white hover:bg-sup2' ?>">
                <?= $p ?>
            </a>
            <?php endfor;
            if ($end < $totalPages) echo '<span class="text-secundario px-1">…</span>';
            ?>
            <!-- Próxima -->
            <a href="<?= buildQs(['page' => min($totalPages, $currentPage + 1)]) ?>"
               class="px-3 py-1.5 rounded-lg text-sm <?= $currentPage >= $totalPages ? 'text-borda pointer-events-none' : 'text-secundario hover:text-white hover:bg-sup2' ?> transition">
                Próxima ›
            </a>
            <!-- Última -->
            <a href="<?= buildQs(['page' => $totalPages]) ?>"
               class="px-2 py-1.5 rounded-lg text-sm <?= $currentPage >= $totalPages ? 'text-borda pointer-events-none' : 'text-secundario hover:text-white hover:bg-sup2' ?> transition">»</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ========================= MODAL DE DETALHES / EDIÇÃO ========================= -->
<?php
// Feedback de edição
$editErrors  = $_SESSION['edit_errors'] ?? [];
$editUid     = $_SESSION['edit_uid']    ?? 0;
$editedUid   = (int)($_GET['edited']   ?? 0);
unset($_SESSION['edit_errors'], $_SESSION['edit_uid']);
?>
<div id="userModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 hidden" aria-modal="true" role="dialog">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeUserModal()"></div>

    <!-- Modal Panel -->
    <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-sup1 border border-borda rounded-2xl shadow-2xl flex flex-col" style="animation: modalIn 0.22s ease-out both;">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-borda sticky top-0 bg-sup1 z-10">
            <div class="flex items-center gap-3">
                <div id="modal-avatar" class="w-11 h-11 rounded-full bg-brand/20 flex items-center justify-center text-brand font-bold text-lg select-none flex-shrink-0"></div>
                <div>
                    <h2 id="modal-name" class="text-lg font-bold text-texto leading-tight"></h2>
                    <p id="modal-role-badge" class="text-xs mt-0.5"></p>
                </div>
            </div>
            <button onclick="closeUserModal()" class="text-secundario hover:text-white transition p-1.5 rounded-lg hover:bg-sup2 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Abas -->
        <div class="flex border-b border-borda px-6 gap-1 sticky top-[73px] bg-sup1 z-10">
            <button id="tab-details" onclick="switchTab('details')"
                    class="tab-btn px-4 py-3 text-sm font-medium border-b-2 transition -mb-px
                           border-brand text-brand">
                Detalhes
            </button>
            <button id="tab-edit" onclick="switchTab('edit')"
                    class="tab-btn px-4 py-3 text-sm font-medium border-b-2 transition -mb-px
                           border-transparent text-secundario hover:text-texto">
                Editar Dados
            </button>
        </div>

        <!-- Body: Detalhes -->
        <div id="panel-details" class="px-6 py-5 space-y-6">

            <!-- Info Grid -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-sup2 rounded-xl p-4 border border-borda/50">
                    <p class="text-[10px] text-secundario uppercase font-bold mb-1 tracking-wider">ID</p>
                    <p id="modal-id" class="text-texto font-mono font-bold"></p>
                </div>
                <div class="bg-sup2 rounded-xl p-4 border border-borda/50">
                    <p class="text-[10px] text-secundario uppercase font-bold mb-1 tracking-wider">Cadastro</p>
                    <p id="modal-created" class="text-texto font-medium"></p>
                </div>
                <div class="bg-sup2 rounded-xl p-4 border border-borda/50 col-span-2">
                    <p class="text-[10px] text-secundario uppercase font-bold mb-1 tracking-wider">E-mail</p>
                    <p id="modal-email" class="text-texto break-all"></p>
                </div>
                <div class="bg-sup2 rounded-xl p-4 border border-borda/50">
                    <p class="text-[10px] text-secundario uppercase font-bold mb-1 tracking-wider">CPF</p>
                    <p id="modal-cpf" class="text-texto font-mono"></p>
                </div>
                <div class="bg-sup2 rounded-xl p-4 border border-borda/50">
                    <p class="text-[10px] text-secundario uppercase font-bold mb-1 tracking-wider">Status</p>
                    <p id="modal-status" class="font-bold text-sm"></p>
                </div>
            </div>

            <!-- Verification Media -->
            <div id="modal-media-section" class="hidden">
                <p class="text-[10px] text-secundario uppercase font-bold mb-2 tracking-wider">Mídia de Verificação</p>
                <div id="modal-media-content" class="bg-sup2 rounded-xl border border-borda/50 overflow-hidden flex items-center justify-center"></div>
            </div>

            <!-- Ads Section -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <p class="text-[10px] text-secundario uppercase font-bold tracking-wider">Anúncios</p>
                    <span id="modal-ads-count" class="bg-brand/20 text-brand text-xs font-bold px-2 py-0.5 rounded-full"></span>
                </div>
                <div id="modal-ads-list" class="space-y-2"></div>
            </div>
        </div>

        <!-- Body: Editar -->
        <div id="panel-edit" class="px-6 py-5 hidden">
            <!-- Erros do servidor -->
            <div id="edit-server-errors" class="hidden mb-4 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl px-4 py-3 text-sm"></div>
            <!-- Sucesso -->
            <div id="edit-success" class="hidden mb-4 bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl px-4 py-3 text-sm">
                Dados salvos com sucesso!
            </div>

            <form id="editUserForm" method="POST" action="<?= $baseUrl ?>/admin/users.php"
                  onsubmit="return validateEditForm()">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="edit-user-id">

                <div class="space-y-4">
                    <!-- Nome -->
                    <div>
                        <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Nome</label>
                        <input type="text" name="name" id="edit-name" required
                               class="w-full bg-sup2 border border-borda rounded-xl px-4 py-2.5 text-sm text-texto
                                      outline-none focus:border-brand transition placeholder-secundario"
                               placeholder="Nome completo">
                    </div>

                    <!-- E-mail -->
                    <div>
                        <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">E-mail</label>
                        <input type="email" name="email" id="edit-email" required
                               class="w-full bg-sup2 border border-borda rounded-xl px-4 py-2.5 text-sm text-texto
                                      outline-none focus:border-brand transition placeholder-secundario"
                               placeholder="email@exemplo.com">
                    </div>

                    <!-- CPF -->
                    <div>
                        <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">CPF <span class="normal-case font-normal text-secundario">(opcional)</span></label>
                        <input type="text" name="cpf" id="edit-cpf"
                               class="w-full bg-sup2 border border-borda rounded-xl px-4 py-2.5 text-sm text-texto
                                      outline-none focus:border-brand transition placeholder-secundario font-mono"
                               placeholder="000.000.000-00">
                    </div>

                    <!-- Divider senha -->
                    <div class="border-t border-borda pt-4">
                        <p class="text-[10px] text-secundario uppercase font-bold tracking-wider mb-3">
                            Nova Senha <span class="normal-case font-normal">(deixe em branco para não alterar)</span>
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Nova senha</label>
                                <input type="password" name="new_password" id="edit-new-pass"
                                       autocomplete="new-password"
                                       class="w-full bg-sup2 border border-borda rounded-xl px-4 py-2.5 text-sm text-texto
                                              outline-none focus:border-brand transition"
                                       placeholder="Mín. 6 caracteres">
                            </div>
                            <div>
                                <label class="block text-[10px] text-secundario uppercase font-bold tracking-wider mb-1.5">Confirmar senha</label>
                                <input type="password" name="confirm_password" id="edit-conf-pass"
                                       autocomplete="new-password"
                                       class="w-full bg-sup2 border border-borda rounded-xl px-4 py-2.5 text-sm text-texto
                                              outline-none focus:border-brand transition"
                                       placeholder="Repita a senha">
                            </div>
                        </div>
                        <p id="edit-pass-error" class="text-red-400 text-xs mt-1.5 hidden"></p>
                    </div>

                    <!-- Salvar -->
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="switchTab('details')"
                                class="text-secundario hover:text-texto text-sm font-medium px-4 py-2 rounded-lg hover:bg-sup2 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="bg-brand hover:bg-brand-hover text-white text-sm font-bold px-6 py-2 rounded-lg transition">
                            Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

<script>
const usersData = <?= json_encode(array_values($users), JSON_HEX_TAG) ?>;
const adsByUser  = <?= json_encode($adsByUser, JSON_HEX_TAG) ?>;
const baseUrl    = "<?= $baseUrl ?>";

const statusConfig = {
    active:               { label: 'Ativo',             cls: 'text-green-400' },
    pending_verification: { label: 'Pend. Verificação', cls: 'text-yellow-400' },
    pending_approval:     { label: 'Pend. Aprovação',   cls: 'text-orange-400' },
    rejected:             { label: 'Rejeitado',          cls: 'text-red-400' },
};

const roleConfig = {
    admin:      { label: 'Admin',      cls: 'text-brand' },
    advertiser: { label: 'Anunciante', cls: 'text-blue-400' },
    client:     { label: 'Cliente',    cls: 'text-gray-400' },
};

const adStatusConfig = {
    active:   { label: 'Ativo',    cls: 'bg-green-500/20 text-green-400' },
    pending:  { label: 'Pendente', cls: 'bg-yellow-500/20 text-yellow-400' },
    inactive: { label: 'Inativo',  cls: 'bg-gray-500/20 text-gray-400' },
    deleted:  { label: 'Deletado', cls: 'bg-red-500/20 text-red-400' },
};

const highlightLabels = {
    organic:         'Orgânico',
    paid_highlight:  'Destaque',
    super_highlight: 'Super Destaque',
    super_top:       'Super Top',
    ultra_top:       'Ultra Top',
};

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleDateString('pt-BR');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function openUserModal(userId) {
    const user = usersData.find(u => Number(u.id) === Number(userId));
    if (!user) return;

    // Avatar
    const initials = user.name.split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
    document.getElementById('modal-avatar').textContent = initials;

    // Name & role
    document.getElementById('modal-name').textContent = user.name;
    const role = roleConfig[user.role] || { label: user.role, cls: 'text-gray-400' };
    document.getElementById('modal-role-badge').innerHTML =
        `<span class="font-bold uppercase ${role.cls}">${role.label}</span>`;

    // Info
    document.getElementById('modal-id').textContent      = '#' + user.id;
    document.getElementById('modal-created').textContent = formatDate(user.created_at);
    document.getElementById('modal-email').textContent   = user.email;
    document.getElementById('modal-cpf').textContent     = user.cpf || 'Não informado';

    const st = statusConfig[user.status] || { label: user.status || '—', cls: 'text-gray-400' };
    const statusEl = document.getElementById('modal-status');
    statusEl.textContent = st.label;
    statusEl.className   = 'font-bold text-sm ' + st.cls;

    // Pré-preenche o formulário de edição
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-name').value     = user.name  || '';
    document.getElementById('edit-email').value    = user.email || '';
    document.getElementById('edit-cpf').value      = user.cpf   || '';
    document.getElementById('edit-new-pass').value  = '';
    document.getElementById('edit-conf-pass').value = '';
    document.getElementById('edit-pass-error').classList.add('hidden');
    document.getElementById('edit-server-errors').classList.add('hidden');
    document.getElementById('edit-success').classList.add('hidden');

    // Aba padrão: Detalhes
    switchTab('details');

    // Verification media
    const mediaSection = document.getElementById('modal-media-section');
    const mediaContent  = document.getElementById('modal-media-content');
    if (user.verification_media) {
        mediaSection.classList.remove('hidden');
        const mediaUrl = baseUrl + '/uploads/' + user.verification_media;
        const isVideo  = /\.(mp4|webm|ogg|mov)$/i.test(user.verification_media);
        if (isVideo) {
            mediaContent.innerHTML = `<video src="${mediaUrl}" controls class="w-full max-h-72 object-contain bg-black rounded-xl"></video>`;
        } else {
            mediaContent.innerHTML = `<img src="${mediaUrl}" alt="Mídia de verificação" class="w-full max-h-72 object-contain rounded-xl cursor-zoom-in" onclick="window.open('${mediaUrl}','_blank')">`;
        }
    } else {
        mediaSection.classList.add('hidden');
        mediaContent.innerHTML = '';
    }

    // Ads
    const userAds = adsByUser[userId] || [];
    document.getElementById('modal-ads-count').textContent = userAds.length;
    const adsList = document.getElementById('modal-ads-list');

    if (userAds.length === 0) {
        adsList.innerHTML = '<p class="text-secundario text-sm italic py-2">Nenhum anúncio cadastrado.</p>';
    } else {
        adsList.innerHTML = userAds.map(ad => {
            const adSt   = adStatusConfig[ad.status] || { label: ad.status, cls: 'bg-gray-500/20 text-gray-400' };
            const hlLabel = highlightLabels[ad.highlight_level] || ad.highlight_level;
            return `
            <a href="${baseUrl}/anuncio/?id=${ad.id}" target="_blank" rel="noopener"
               class="flex items-center justify-between bg-sup2 hover:bg-borda/40 border border-borda/50 rounded-xl px-4 py-3 transition group">
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-texto text-sm font-medium group-hover:text-brand transition truncate">${escHtml(ad.title)}</span>
                    <span class="text-secundario text-xs">${hlLabel} · ${escHtml(ad.phone || 'Sem tel.')} · ${ad.views || 0} views · ${ad.clicks || 0} cliques</span>
                </div>
                <span class="text-[11px] font-bold uppercase px-2 py-1 rounded-md ${adSt.cls} ml-3 whitespace-nowrap flex-shrink-0">${adSt.label}</span>
            </a>`;
        }).join('');
    }

    document.getElementById('userModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}


function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeUserModal(); });

// ---- Seleção em massa ----
function getChecked() {
    return [...document.querySelectorAll('.row-checkbox:checked')];
}

function updateBulkBar() {
    const checked = getChecked();
    const bar     = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = checked.length;

    if (checked.length > 0) {
        bar.classList.remove('hidden');
        bar.style.animation = 'bulkBarIn 0.2s ease-out both';
    } else {
        bar.classList.add('hidden');
    }

    // Atualiza estado do "selecionar todos"
    const all = document.querySelectorAll('.row-checkbox');
    document.getElementById('selectAll').indeterminate =
        checked.length > 0 && checked.length < all.length;
    document.getElementById('selectAll').checked =
        all.length > 0 && checked.length === all.length;
}

function toggleAll(masterCb) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = masterCb.checked;
    });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
}

function submitBulkDelete() {
    const ids = getChecked().map(cb => cb.value);
    if (ids.length === 0) return;

    const msg = `Tem certeza que deseja excluir permanentemente ${ids.length} usuário(s)?\n\nIsso apagará todos os anúncios, mídias e arquivos desses usuários. Esta ação é irreversível.`;
    if (!confirm(msg)) return;

    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = ids.map(id =>
        `<input type="hidden" name="ids[]" value="${id}">`
    ).join('');
    document.getElementById('bulkDeleteForm').submit();
}

// Clique na linha: abre modal se não estiver no modo seleção, senão marca checkbox
function handleRowClick(event, userId, isSelf) {
    if (isSelf) { openUserModal(userId); return; }
    const anyChecked = getChecked().length > 0;
    if (anyChecked) {
        const cb = event.currentTarget.querySelector('.row-checkbox');
        if (cb) { cb.checked = !cb.checked; updateBulkBar(); }
    } else {
        openUserModal(userId);
    }
}

// ---- Abas do modal ----
function switchTab(tab) {
    const isDetails = tab === 'details';

    document.getElementById('panel-details').classList.toggle('hidden', !isDetails);
    document.getElementById('panel-edit').classList.toggle('hidden',  isDetails);

    const btnDetails = document.getElementById('tab-details');
    const btnEdit    = document.getElementById('tab-edit');

    btnDetails.className = 'tab-btn px-4 py-3 text-sm font-medium border-b-2 transition -mb-px ' +
        (isDetails ? 'border-brand text-brand' : 'border-transparent text-secundario hover:text-texto');
    btnEdit.className = 'tab-btn px-4 py-3 text-sm font-medium border-b-2 transition -mb-px ' +
        (!isDetails ? 'border-brand text-brand' : 'border-transparent text-secundario hover:text-texto');
}

// ---- Validação client-side do form de edição ----
function validateEditForm() {
    const np = document.getElementById('edit-new-pass').value;
    const cp = document.getElementById('edit-conf-pass').value;
    const errEl = document.getElementById('edit-pass-error');

    if (np !== '' && np !== cp) {
        errEl.textContent = 'As senhas não coincidem.';
        errEl.classList.remove('hidden');
        return false;
    }
    if (np !== '' && np.length < 6) {
        errEl.textContent = 'A senha deve ter ao menos 6 caracteres.';
        errEl.classList.remove('hidden');
        return false;
    }
    errEl.classList.add('hidden');
    return true;
}

// ---- Auto-abertura após edição ----
document.addEventListener('DOMContentLoaded', () => {
    // Erros do servidor: re-abre modal na aba Editar
    const editErrors = <?= json_encode($editErrors) ?>;
    const editUid    = <?= (int)$editUid ?>;
    if (editErrors.length > 0 && editUid > 0) {
        openUserModal(editUid);
        switchTab('edit');
        const errBox = document.getElementById('edit-server-errors');
        errBox.innerHTML = editErrors.map(e => `<div>• ${e}</div>`).join('');
        errBox.classList.remove('hidden');
    }

    // Sucesso: re-abre modal e mostra banner verde
    const editedUid = <?= (int)$editedUid ?>;
    if (editedUid > 0) {
        openUserModal(editedUid);
        switchTab('edit');
        document.getElementById('edit-success').classList.remove('hidden');
    }
});
</script>

<style>
@keyframes bulkBarIn {
    from { opacity: 0; transform: translateX(-50%) translateY(12px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
