<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = ba_db();
$tags = $pdo->query('SELECT id, name FROM tags WHERE active = 1 ORDER BY name COLLATE NOCASE')->fetchAll();
echo json_encode($tags, JSON_UNESCAPED_UNICODE);
