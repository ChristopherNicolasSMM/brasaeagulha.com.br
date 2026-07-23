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

$title = trim((string) ($input['title'] ?? ''));
$type = (string) ($input['type'] ?? 'livro');
$description = trim((string) ($input['description'] ?? ''));

$allowedTypes = ['livro', 'monografia', 'liturgico'];
$accentMap = ['livro' => '#d4af37', 'monografia' => '#a29c8f', 'liturgico' => '#4d7ea8'];

if ($title === '' || !in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Informe um título e um tipo válido.']);
    exit;
}

function ba_slugify_local(string $str): string
{
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = $transliterated !== false ? $transliterated : $str;
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str) ?? '';
    $str = trim($str, '-');
    return $str !== '' ? $str : ('colecao-' . time());
}

$pdo = ba_db();

$id = ba_slugify_local($title);
$orig = $id;
$n = 2;
$check = $pdo->prepare('SELECT 1 FROM collections WHERE id = ?');
while (true) {
    $check->execute([$id]);
    if (!$check->fetch()) {
        break;
    }
    $id = $orig . '-' . $n;
    $n++;
}

$countStmt = $pdo->query('SELECT COUNT(*) FROM collections');
$sortOrder = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare('INSERT INTO collections (id, title, type, description, accent_color, sort_order) VALUES (?,?,?,?,?,?)');
$stmt->execute([$id, $title, $type, $description, $accentMap[$type], $sortOrder]);

echo json_encode(['ok' => true, 'id' => $id]);
