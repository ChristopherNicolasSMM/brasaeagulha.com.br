<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !ba_csrf_verify($input['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$collections = $input['collections'] ?? null;
if (!is_array($collections)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato inválido: esperado { collections: [...] }.']);
    exit;
}

$allowedTypes = ['livro', 'monografia', 'liturgico'];
$pdo = ba_db();
$pdo->beginTransaction();

try {
    $pdo->exec('DELETE FROM volume_tags');
    $pdo->exec('DELETE FROM volumes');
    $pdo->exec('DELETE FROM collections');

    $insCol = $pdo->prepare('INSERT INTO collections (id, title, type, description, accent_color, sort_order) VALUES (?,?,?,?,?,?)');
    $insVol = $pdo->prepare(
        'INSERT INTO volumes
         (id, collection_id, volume_label, subtitle, description, price, promo_active, promo_type, promo_value, promo_label, promo_start_date, promo_end_date, sort_order,
          isbn, language, page_count, publication_date, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insTag = $pdo->prepare('INSERT INTO volume_tags (volume_id, tag) VALUES (?, ?)');

    foreach ($collections as $ci => $col) {
        if (!is_array($col) || empty($col['id']) || empty($col['title'])) {
            throw new InvalidArgumentException('Cada coleção precisa de "id" e "title".');
        }
        $type = in_array($col['type'] ?? '', $allowedTypes, true) ? $col['type'] : 'livro';
        $insCol->execute([
            (string) $col['id'], (string) $col['title'], $type,
            (string) ($col['description'] ?? ''), (string) ($col['accentColor'] ?? '#d4af37'), (int) $ci,
        ]);

        foreach (($col['volumes'] ?? []) as $vi => $vol) {
            if (!is_array($vol) || empty($vol['id'])) {
                throw new InvalidArgumentException('Cada volume precisa de "id".');
            }
            $promo = is_array($vol['promotion'] ?? null) ? $vol['promotion'] : [];
            $promoType = in_array($promo['type'] ?? '', ['percent', 'fixed'], true) ? $promo['type'] : 'percent';
            $author = is_array($vol['author'] ?? null) ? $vol['author'] : null;
            $authorId = $author['id'] ?? null;
            $pageCount = ($vol['pageCount'] ?? null) !== null ? (int) $vol['pageCount'] : null;

            $insVol->execute([
                (string) $vol['id'], (string) $col['id'],
                (string) ($vol['volumeLabel'] ?? ''), (string) ($vol['subtitle'] ?? ''), (string) ($vol['description'] ?? ''),
                (float) ($vol['price'] ?? 0),
                !empty($promo['active']) ? 1 : 0,
                $promoType,
                (float) ($promo['value'] ?? 0),
                (string) ($promo['label'] ?? ''),
                (string) ($promo['startDate'] ?? ''),
                (string) ($promo['endDate'] ?? ''),
                (int) $vi,
                (string) ($vol['isbn'] ?? ''),
                (string) ($vol['language'] ?? ''),
                $pageCount,
                (string) ($vol['publicationDate'] ?? ''),
                $authorId,
            ]);

            foreach (($vol['tags'] ?? []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $insTag->execute([(string) $vol['id'], $tag]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => 'Falha ao importar: ' . $e->getMessage()]);
}
