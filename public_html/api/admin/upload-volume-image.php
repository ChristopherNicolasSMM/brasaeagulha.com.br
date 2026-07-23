<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

// Upload usa multipart/form-data (não JSON), então o CSRF vem em $_POST.
if (!ba_csrf_verify($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$volumeId = trim((string) ($_POST['volume_id'] ?? ''));
if ($volumeId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Volume não informado.']);
    exit;
}

$pdo = ba_db();
$check = $pdo->prepare('SELECT id FROM volumes WHERE id = ?');
$check->execute([$volumeId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Volume não encontrado.']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Falha no envio do arquivo.']);
    exit;
}

$tmpPath = $_FILES['image']['tmp_name'];
$maxBytes = 5 * 1024 * 1024; // 5 MB
if ($_FILES['image']['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['error' => 'Imagem maior que 5 MB.']);
    exit;
}

// Nunca confiar na extensão nem no Content-Type enviado pelo navegador —
// getimagesize() abre o arquivo de verdade e só reconhece formatos de
// imagem reais, o que barra qualquer tentativa de subir outra coisa
// disfarçada de .jpg (ex.: um .php renomeado).
$imageInfo = @getimagesize($tmpPath);
if ($imageInfo === false) {
    http_response_code(400);
    echo json_encode(['error' => 'O arquivo não é uma imagem válida.']);
    exit;
}

$allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = $imageInfo['mime'];
if (!isset($allowedMime[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato não suportado. Use JPG, PNG, WEBP ou GIF.']);
    exit;
}

$dir = ba_volume_image_dir($volumeId);
// Nome gerado (nunca o nome original do arquivo) — evita colisão e
// qualquer tentativa de manipular o caminho de destino.
$filename = bin2hex(random_bytes(10)) . '.' . $allowedMime[$mime];
$destPath = $dir . '/' . $filename;

if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o arquivo no servidor.']);
    exit;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM volume_images WHERE volume_id = ?');
$countStmt->execute([$volumeId]);
$existingCount = (int) $countStmt->fetchColumn();
$isPrimary = $existingCount === 0 ? 1 : 0; // primeira imagem do volume vira principal automaticamente

$ins = $pdo->prepare('INSERT INTO volume_images (volume_id, filename, is_primary, sort_order) VALUES (?, ?, ?, ?)');
$ins->execute([$volumeId, $filename, $isPrimary, $existingCount]);

echo json_encode([
    'ok' => true,
    'id' => (int) $pdo->lastInsertId(),
    'url' => ba_volume_image_url($volumeId, $filename),
    'isPrimary' => (bool) $isPrimary,
]);
