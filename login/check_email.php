<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appConfig = __DIR__ . '/../config/app.php';
if (!file_exists($appConfig)) {
    echo json_encode(['status' => 'error', 'message' => 'Configuração não encontrada']);
    exit;
}
require_once $appConfig;
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = isset($data['email']) ? trim($data['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'E-mail inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'status' => 'registered',
            'role' => $user['role'],
            'user_status' => $user['status']
        ]);
    } else {
        echo json_encode([
            'status' => 'unregistered'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados']);
}
