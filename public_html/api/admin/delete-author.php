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
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID do autor é obrigatório.']);
    exit;
}

$pdo = ba_db();
// ON DELETE SET NULL na coluna volumes.author_id garante que os livros
// desse autor continuam existindo, só ficam sem autor até você reatribuir.
$stmt = $pdo->prepare('DELETE FROM authors WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Autor não encontrado.']);
    exit;
}

echo json_encode(['ok' => true]);
