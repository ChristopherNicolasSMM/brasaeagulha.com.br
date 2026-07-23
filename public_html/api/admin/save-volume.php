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
$collectionId = (string) ($input['collectionId'] ?? '');
$volumeLabel = trim((string) ($input['volumeLabel'] ?? ''));
$subtitle = trim((string) ($input['subtitle'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$price = (float) ($input['price'] ?? 0);
$tagsRaw = (string) ($input['tags'] ?? '');
$tags = array_values(array_filter(array_map('trim', explode(',', $tagsRaw)), fn($t) => $t !== ''));
$isbn = trim((string) ($input['isbn'] ?? ''));
$language = trim((string) ($input['language'] ?? 'Português (Brasil)'));
$pageCount = ($input['pageCount'] ?? '') !== '' ? (int) $input['pageCount'] : null;
$publicationDate = trim((string) ($input['publicationDate'] ?? ''));
$authorId = trim((string) ($input['authorId'] ?? ''));
$authorId = $authorId !== '' ? $authorId : null;

$promo = is_array($input['promotion'] ?? null) ? $input['promotion'] : [];
$promoActive = !empty($promo['active']) ? 1 : 0;
$promoType = in_array($promo['type'] ?? '', ['percent', 'fixed'], true) ? $promo['type'] : 'percent';
$promoValue = (float) ($promo['value'] ?? 0);
$promoLabel = trim((string) ($promo['label'] ?? ''));
$promoStart = trim((string) ($promo['startDate'] ?? ''));
$promoEnd = trim((string) ($promo['endDate'] ?? ''));

if ($collectionId === '' || $volumeLabel === '' || $subtitle === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha coleção, rótulo do volume e subtítulo.']);
    exit;
}
if ($price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Preço não pode ser negativo.']);
    exit;
}

$pdo = ba_db();

$checkCol = $pdo->prepare('SELECT id FROM collections WHERE id = ?');
$checkCol->execute([$collectionId]);
if (!$checkCol->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Coleção não encontrada.']);
    exit;
}

if ($authorId !== null) {
    $checkAuthor = $pdo->prepare('SELECT 1 FROM authors WHERE id = ?');
    $checkAuthor->execute([$authorId]);
    if (!$checkAuthor->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Autor selecionado não existe.']);
        exit;
    }
}

$isNew = $id === '';

if ($isNew) {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM volumes WHERE collection_id = ?');
    $countStmt->execute([$collectionId]);
    $sortOrder = (int) $countStmt->fetchColumn();
    $id = $collectionId . '-v' . bin2hex(random_bytes(4));

    $stmt = $pdo->prepare(
        'INSERT INTO volumes
         (id, collection_id, volume_label, subtitle, description, price, sort_order,
          isbn, language, page_count, publication_date, author_id,
          promo_active, promo_type, promo_value, promo_label, promo_start_date, promo_end_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $id, $collectionId, $volumeLabel, $subtitle, $description, $price, $sortOrder,
        $isbn, $language, $pageCount, $publicationDate, $authorId,
        $promoActive, $promoType, $promoValue, $promoLabel, $promoStart, $promoEnd,
    ]);
} else {
    $checkVol = $pdo->prepare('SELECT id FROM volumes WHERE id = ?');
    $checkVol->execute([$id]);
    if (!$checkVol->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Volume não encontrado.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'UPDATE volumes SET
            collection_id = ?, volume_label = ?, subtitle = ?, description = ?, price = ?,
            isbn = ?, language = ?, page_count = ?, publication_date = ?, author_id = ?,
            promo_active = ?, promo_type = ?, promo_value = ?, promo_label = ?, promo_start_date = ?, promo_end_date = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $collectionId, $volumeLabel, $subtitle, $description, $price,
        $isbn, $language, $pageCount, $publicationDate, $authorId,
        $promoActive, $promoType, $promoValue, $promoLabel, $promoStart, $promoEnd,
        $id,
    ]);
}

// Tags: substitui tudo (mais simples e previsível do que tentar diff).
$del = $pdo->prepare('DELETE FROM volume_tags WHERE volume_id = ?');
$del->execute([$id]);
$insTag = $pdo->prepare('INSERT INTO volume_tags (volume_id, tag) VALUES (?, ?)');
foreach ($tags as $tag) {
    $insTag->execute([$id, $tag]);
}

echo json_encode(['ok' => true, 'id' => $id]);
