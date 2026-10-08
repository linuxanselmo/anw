<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Não autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mediaId = isset($data['media_id']) ? (int)$data['media_id'] : 0;
    $userId = $_SESSION['user_id'];

    if ($mediaId > 0) {
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        
        if ($isAdmin) {
            $stmt = $pdo->prepare("
                SELECT m.id, m.file_path, m.is_primary 
                FROM ad_media m
                WHERE m.id = ?
            ");
            $stmt->execute([$mediaId]);
        } else {
            // Verificar se a mídia pertence a um anúncio do usuário
            $stmt = $pdo->prepare("
                SELECT m.id, m.file_path, m.is_primary 
                FROM ad_media m
                JOIN ads a ON m.ad_id = a.id
                WHERE m.id = ? AND a.user_id = ?
            ");
            $stmt->execute([$mediaId, $userId]);
        }
        $media = $stmt->fetch();

        if ($media) {
            if ($media['is_primary']) {
                echo json_encode(['success' => false, 'error' => 'Não é possível excluir a foto de capa sem substituí-la na edição.']);
                exit;
            }

            // Deletar do banco
            $stmtDel = $pdo->prepare("DELETE FROM ad_media WHERE id = ?");
            if ($stmtDel->execute([$mediaId])) {
                // Tentar deletar o arquivo físico
                $filePath = $media['file_path'];
                $fileName = basename($filePath);
                $physicalPath = __DIR__ . '/../uploads/' . $fileName;
                if (file_exists($physicalPath)) {
                    unlink($physicalPath);
                }

                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Requisição inválida.']);
