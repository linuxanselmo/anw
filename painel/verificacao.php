<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    header("Location: {$baseUrl}/login/");
    exit;
}

$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentStatus = $stmt->fetchColumn();

// Check if user has an ad
$stmtAd = $pdo->prepare("SELECT COUNT(*) FROM ads WHERE user_id = ?");
$stmtAd->execute([$_SESSION['user_id']]);
$hasAd = $stmtAd->fetchColumn() > 0;

// If active, redirect based on ad
if ($currentStatus === 'active') {
    $_SESSION['user_status'] = 'active';
    if (!$hasAd) {
        header("Location: {$baseUrl}/painel/criar.php");
    } else {
        header("Location: {$baseUrl}/painel/");
    }
    exit;
}

// Just in case someone is stuck in pending_approval
if ($currentStatus === 'pending_approval') {
    if (!$hasAd) {
        header("Location: {$baseUrl}/painel/criar.php");
    } else {
        header("Location: {$baseUrl}/painel/");
    }
    exit;
}

$error = null;
$success = null;

// Auto-create columns if missing
try { $pdo->exec("ALTER TABLE users ADD COLUMN birth_date DATE DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentStatus === 'pending_verification') {
    $cpf = preg_replace('/\D/', '', $_POST['cpf']);
    $phone = preg_replace('/\D/', '', $_POST['phone']);
    $birthDate = $_POST['birth_date'];
    
    // Check age
    $age = date_diff(date_create($birthDate), date_create('now'))->y;
    
    if ($age < 18) {
        $error = "Você precisa ter no mínimo 18 anos para se cadastrar.";
    } elseif (strlen($cpf) !== 11) {
        $error = "CPF inválido. Digite apenas números (11 dígitos).";
    } elseif (empty($phone)) {
        $error = "O telefone é obrigatório.";
    } elseif (!isset($_FILES['verification_media']) || $_FILES['verification_media']['error'] !== UPLOAD_ERR_OK) {
        if (isset($_FILES['verification_media']) && ($_FILES['verification_media']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['verification_media']['error'] === UPLOAD_ERR_FORM_SIZE)) {
            $error = "O arquivo enviado é muito grande. O limite máximo permitido é de 1GB.";
        } else {
            $error = "Você precisa enviar a foto ou vídeo segurando o documento.";
        }
    } else {
        $file = $_FILES['verification_media'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'mp4', 'mov'];
        
        if (!in_array($ext, $allowed)) {
            $error = "Formato inválido. Envie JPG, PNG, MP4 ou MOV.";
        } else {
            $uploadDir = __DIR__ . '/../uploads/verifications/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . $_SESSION['user_id'] . '.' . $ext;
            $dest = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $dbPath = "{$baseUrl}/uploads/verifications/{$fileName}";
                
                $updateStmt = $pdo->prepare("UPDATE users SET cpf = ?, phone = ?, birth_date = ?, verification_media = ?, status = 'active' WHERE id = ?");
                if ($updateStmt->execute([$cpf, $phone, $birthDate, $dbPath, $_SESSION['user_id']])) {
                    $_SESSION['user_status'] = 'active';
                    $currentStatus = 'active';
                    header("Location: {$baseUrl}/painel/criar.php");
                    exit;
                } else {
                    $error = "Erro ao salvar no banco de dados.";
                }
            } else {
                $error = "Erro ao fazer upload do arquivo.";
            }
        }
    }
}
?>

<div class="max-w-3xl mx-auto mt-12 bg-sup1 p-8 rounded-2xl border border-borda shadow-2xl mb-20">
    
    <!-- Progresso -->
    <div class="mb-12 relative px-4">
        <div class="absolute left-0 top-5 w-full h-1 bg-sup2 z-0"></div>
        <div class="flex items-center justify-between relative z-10">
            <!-- Step 1 -->
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold bg-green-500 text-white border-4 border-sup1">✓</div>
                <span class="text-xs font-bold text-green-500 hidden sm:block">Cadastro</span>
            </div>
            
            <!-- Step 2 -->
            <?php 
                $s2_done = in_array($currentStatus, ['pending_approval', 'rejected', 'active']);
                $s2_class = $s2_done ? 'bg-green-500 text-white' : 'bg-brand text-white shadow-[0_0_15px_rgba(2db,39,119,0.5)]';
                $s2_text = $s2_done ? 'text-green-500' : 'text-brand';
            ?>
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold border-4 border-sup1 <?= $s2_class ?>"><?= $s2_done ? '✓' : '2' ?></div>
                <span class="text-xs font-bold <?= $s2_text ?> hidden sm:block">Documentos</span>
            </div>
            
            <!-- Step 3 -->
            <?php 
                $s3_active = $currentStatus === 'pending_approval';
                $s3_class = $s3_active ? 'bg-yellow-500 text-white shadow-[0_0_15px_rgba(234,179,8,0.5)]' : 'bg-sup2 text-secundario';
                $s3_text = $s3_active ? 'text-yellow-500' : 'text-secundario';
            ?>
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold border-4 border-sup1 <?= $s3_class ?>">3</div>
                <span class="text-xs font-bold <?= $s3_text ?> hidden sm:block">Análise</span>
            </div>
            
            <!-- Step 4 -->
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold border-4 border-sup1 bg-sup2 text-secundario">4</div>
                <span class="text-xs font-bold text-secundario hidden sm:block">Criar Anúncio</span>
            </div>
        </div>
    </div>

    <?php if ($currentStatus === 'pending_approval'): ?>
        <!-- Aguardando Aprovação -->
        <div class="text-center py-10">
            <svg class="w-20 h-20 text-yellow-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-3xl font-bold text-center mb-4 text-texto">Em Análise</h2>
            <p class="text-center text-secundario text-lg">
                Recebemos seus documentos! Nossa equipe está analisando sua conta no momento.<br>
                Retorne a esta página mais tarde para conferir seu status de aprovação.
            </p>
            <div class="mt-8">
                <a href="<?= $baseUrl ?>/sair/" class="text-brand hover:underline">Sair da conta</a>
            </div>
        </div>
    <?php elseif ($currentStatus === 'rejected'): ?>
        <!-- Rejeitado -->
        <div class="text-center py-10">
            <svg class="w-20 h-20 text-red-500 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-3xl font-bold text-center mb-4 text-red-500">Cadastro Rejeitado</h2>
            <p class="text-center text-secundario text-lg">
                Infelizmente não pudemos aprovar o seu cadastro com os dados fornecidos. Entre em contato com o nosso suporte para mais detalhes.
            </p>
            <div class="mt-8">
                <a href="<?= $baseUrl ?>/sair/" class="text-brand hover:underline">Sair da conta</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Pendente de Verificação -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-brand/20 text-brand rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-texto">Verificação de Identidade</h2>
            <p class="text-secundario mt-2">
                Para garantir a segurança da nossa plataforma, precisamos verificar sua identidade antes de você começar a anunciar.
            </p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-4 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block text-secundario font-bold mb-2">Data de Nascimento (Obrigatório +18)</label>
                <input type="date" name="birth_date" required class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
            </div>
            
            <div>
                <label class="block text-secundario font-bold mb-2">Telefone de Contato (WhatsApp)</label>
                <input type="text" name="phone" required placeholder="(00) 00000-0000" class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
            </div>
            
            <div>
                <label class="block text-secundario font-bold mb-2">Seu CPF (apenas números)</label>
                <input type="text" name="cpf" required placeholder="000.000.000-00" maxlength="14" class="w-full bg-sup2 border border-borda text-texto rounded-lg px-4 py-3 focus:outline-none focus:border-brand transition">
            </div>
            
            <div class="bg-sup2 border-2 border-dashed border-brand/50 hover:border-brand transition rounded-xl p-8 text-center cursor-pointer relative">
                <input type="file" name="verification_media" accept="video/mp4,video/quicktime,video/webm" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" id="file-upload">
                
                <svg class="w-12 h-12 text-secundario mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <label for="file-upload" class="block text-brand font-bold mb-2">Clique para enviar ou arraste o arquivo</label>
                <p class="text-sm text-secundario">Envie um vídeo curto e nítido mostrando o seu rosto e corpo para confirmar sua identidade (Formatos: MP4, MOV). <br><span class="font-bold text-yellow-500">Tamanho máximo: 1GB</span></p>
                <div id="file-name" class="mt-4 text-texto font-bold hidden">Nenhum arquivo selecionado</div>
            </div>
            
            <button type="submit" class="w-full bg-brand hover:bg-brand-hover text-white font-bold py-4 rounded-lg transition shadow-lg shadow-brand/30 text-lg uppercase tracking-wider mt-4">
                Enviar para Análise
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
    const fileUpload = document.getElementById('file-upload');
    const fileName = document.getElementById('file-name');

    if (fileUpload) {
        fileUpload.addEventListener('change', function(e) {
            if (this.files && this.files.length > 0) {
                const maxSizeBytes = 1024 * 1024 * 1024; // 1GB
                if (this.files[0].size > maxSizeBytes) {
                    alert('O arquivo selecionado é muito grande. O tamanho máximo permitido é 1GB.');
                    this.value = ''; // Clear the input
                    fileName.classList.add('hidden');
                    return;
                }
                
                fileName.textContent = "Arquivo selecionado: " + this.files[0].name;
                fileName.classList.remove('hidden');
                fileName.classList.add('text-green-400');
            } else {
                fileName.classList.add('hidden');
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
