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
$find = $pdo->prepare('SELECT volume_id FROM volume_images WHERE id = ?');
$find->execute([$imageId]);
$row = $find->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Imagem não encontrada.']);
    exit;
}

$pdo->beginTransaction();
$clear = $pdo->prepare('UPDATE volume_images SET is_primary = 0 WHERE volume_id = ?');
$clear->execute([$row['volume_id']]);
$set = $pdo->prepare('UPDATE volume_images SET is_primary = 1 WHERE id = ?');
$set->execute([$imageId]);
$pdo->commit();

echo json_encode(['ok' => true]);
