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
$bio = trim((string) ($input['bio'] ?? ''));
$photoUrl = trim((string) ($input['photo_url'] ?? ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Nome é obrigatório.']);
    exit;
}

$pdo = ba_db();

if ($id === '') {
    // Autor novo: gera o id (slug) a partir do nome, evitando colisão.
    $id = ba_slugify($name);
    $orig = $id;
    $n = 2;
    $check = $pdo->prepare('SELECT 1 FROM authors WHERE id = ?');
    while (true) {
        $check->execute([$id]);
        if (!$check->fetch()) {
            break;
        }
        $id = $orig . '-' . $n;
        $n++;
    }
    $countStmt = $pdo->query('SELECT COUNT(*) FROM authors');
    $sortOrder = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO authors (id, name, bio, photo_url, sort_order) VALUES (?,?,?,?,?)');
    $stmt->execute([$id, $name, $bio, $photoUrl, $sortOrder]);
} else {
    $stmt = $pdo->prepare('UPDATE authors SET name = ?, bio = ?, photo_url = ? WHERE id = ?');
    $stmt->execute([$name, $bio, $photoUrl, $id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Autor não encontrado.']);
        exit;
    }
}

echo json_encode(['ok' => true, 'id' => $id]);
