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
    echo json_encode(['error' => 'ID do volume é obrigatório.']);
    exit;
}

$pdo = ba_db();
// Apaga as tags explicitamente (além do ON DELETE CASCADE já existente na
// tabela, por garantia) e o volume, em uma transação.
$pdo->beginTransaction();
try {
    $delTags = $pdo->prepare('DELETE FROM volume_tags WHERE volume_id = ?');
    $delTags->execute([$id]);

    $delVol = $pdo->prepare('DELETE FROM volumes WHERE id = ?');
    $delVol->execute([$id]);

    if ($delVol->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Volume não encontrado.']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao excluir: ' . $e->getMessage()]);
}
