<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$pdo = ba_db();

$collections = $pdo->query('SELECT * FROM collections ORDER BY sort_order, title')->fetchAll();
$volumesStmt = $pdo->prepare('SELECT * FROM volumes WHERE collection_id = ? ORDER BY sort_order, volume_label');
$tagsStmt = $pdo->prepare('SELECT tag FROM volume_tags WHERE volume_id = ?');
$authorStmt = $pdo->prepare('SELECT id, name FROM authors WHERE id = ?');

$result = [];
foreach ($collections as $col) {
    $volumesStmt->execute([$col['id']]);
    $volumes = [];
    foreach ($volumesStmt->fetchAll() as $vol) {
        $tagsStmt->execute([$vol['id']]);
        $tags = array_column($tagsStmt->fetchAll(), 'tag');

        $author = null;
        if (!empty($vol['author_id'])) {
            $authorStmt->execute([$vol['author_id']]);
            $a = $authorStmt->fetch();
            if ($a) {
                $author = ['id' => $a['id'], 'name' => $a['name']];
            }
        }

        $volumes[] = [
            'id' => $vol['id'],
            'volumeLabel' => $vol['volume_label'],
            'subtitle' => $vol['subtitle'],
            'description' => $vol['description'],
            'price' => (float) $vol['price'],
            'promotion' => [
                'active' => (bool) $vol['promo_active'],
                'type' => $vol['promo_type'],
                'value' => (float) $vol['promo_value'],
                'label' => $vol['promo_label'],
                'startDate' => $vol['promo_start_date'],
                'endDate' => $vol['promo_end_date'],
            ],
            'tags' => $tags,
            'isbn' => $vol['isbn'] ?? '',
            'language' => $vol['language'] ?? '',
            'pageCount' => isset($vol['page_count']) && $vol['page_count'] !== null ? (int) $vol['page_count'] : null,
            'publicationDate' => $vol['publication_date'] ?? '',
            'availability' => $vol['availability'] ?? 'available',
            'images' => ba_get_volume_images($pdo, $vol['id']),
            'author' => $author,
        ];
    }
    $result[] = [
        'id' => $col['id'],
        'title' => $col['title'],
        'type' => $col['type'],
        'description' => $col['description'],
        'accentColor' => $col['accent_color'],
        'active' => (bool) $col['active'],
        'volumes' => $volumes,
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
