<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!ba_csrf_verify($input['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$imageId = (int) ($input['image_id'] ?? 0);
if ($imageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Imagem inválida.']);
    exit;
}

$pdo = ba_db();
$find = $pdo->prepare('SELECT * FROM volume_images WHERE id = ?');
$find->execute([$imageId]);
$row = $find->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Imagem não encontrada.']);
    exit;
}

$path = ba_volume_image_dir($row['volume_id']) . '/' . $row['filename'];
if (is_file($path)) {
    unlink($path);
}

$del = $pdo->prepare('DELETE FROM volume_images WHERE id = ?');
$del->execute([$imageId]);

// Se a imagem apagada era a principal, promove a próxima da fila (se houver).
if ((int) $row['is_primary'] === 1) {
    $next = $pdo->prepare('SELECT id FROM volume_images WHERE volume_id = ? ORDER BY sort_order ASC LIMIT 1');
    $next->execute([$row['volume_id']]);
    $nextRow = $next->fetch();
    if ($nextRow) {
        $promote = $pdo->prepare('UPDATE volume_images SET is_primary = 1 WHERE id = ?');
        $promote->execute([$nextRow['id']]);
    }
}

echo json_encode(['ok' => true]);
