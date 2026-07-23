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
$title = trim((string) ($input['title'] ?? ''));
$type = (string) ($input['type'] ?? 'livro');
$description = trim((string) ($input['description'] ?? ''));
$accentColor = trim((string) ($input['accentColor'] ?? ''));
$active = !empty($input['active']) ? 1 : 0;

$allowedTypes = ['livro', 'monografia', 'liturgico', 'apostila'];

if ($id === '' || $title === '' || !in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Informe título e tipo válido.']);
    exit;
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cor inválida.']);
    exit;
}

$pdo = ba_db();
$check = $pdo->prepare('SELECT id FROM collections WHERE id = ?');
$check->execute([$id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Coleção não encontrada.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE collections SET title = ?, type = ?, description = ?, accent_color = ?, active = ? WHERE id = ?');
$stmt->execute([$title, $type, $description, $accentColor, $active, $id]);

echo json_encode(['ok' => true]);
