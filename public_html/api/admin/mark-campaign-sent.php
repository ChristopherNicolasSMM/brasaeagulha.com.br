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

$ids = $input['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum registro selecionado.']);
    exit;
}
$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum registro válido selecionado.']);
    exit;
}

$sent = !empty($input['sent']);

$pdo = ba_db();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sentAt = $sent ? date('Y-m-d H:i:s') : '';
$stmt = $pdo->prepare("UPDATE stock_interest SET campaign_sent = ?, campaign_sent_at = ? WHERE id IN ($placeholders)");
$stmt->execute([$sent ? 1 : 0, $sentAt, ...$ids]);

echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
