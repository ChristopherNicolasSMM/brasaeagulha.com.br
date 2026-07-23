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

$id = trim((string) ($input['id'] ?? ''));
$name = trim((string) ($input['name'] ?? ''));
$active = !empty($input['active']) ? 1 : 0;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Nome é obrigatório.']);
    exit;
}

$pdo = ba_db();

if ($id === '') {
    // Reaproveita find-or-create (evita duplicar se já existir com esse nome)
    // e depois garante que o status "active" pedido seja aplicado.
    $newId = ba_find_or_create_tag($pdo, $name);
    $upd = $pdo->prepare('UPDATE tags SET active = ? WHERE id = ?');
    $upd->execute([$active, $newId]);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

$check = $pdo->prepare('SELECT id FROM tags WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Tag não encontrada.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE tags SET name = ?, active = ? WHERE id = ?');
$stmt->execute([$name, $active, $id]);

// Mantém volume_tags.tag (texto) em sincronia com o nome, já que várias
// partes do site ainda leem o texto direto (sem precisar de JOIN).
$updText = $pdo->prepare('UPDATE volume_tags SET tag = ? WHERE tag_id = ?');
$updText->execute([$name, $id]);

echo json_encode(['ok' => true, 'id' => $id]);
