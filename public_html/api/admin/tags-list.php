<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$pdo = ba_db();
$tags = $pdo->query(
    'SELECT t.*, (SELECT COUNT(*) FROM volume_tags vt WHERE vt.tag_id = t.id) AS usage_count
     FROM tags t ORDER BY t.active DESC, t.name COLLATE NOCASE'
)->fetchAll();

echo json_encode($tags, JSON_UNESCAPED_UNICODE);
