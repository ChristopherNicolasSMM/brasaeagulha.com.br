<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = ba_db();
$authors = $pdo->query('SELECT id, name, bio, photo_url FROM authors ORDER BY sort_order, name')->fetchAll();
echo json_encode($authors, JSON_UNESCAPED_UNICODE);
