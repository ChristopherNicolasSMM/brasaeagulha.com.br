<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$pdo = ba_db();
$rows = $pdo->query('SELECT volume_id, COUNT(*) AS n FROM stock_interest GROUP BY volume_id')->fetchAll();

$out = [];
foreach ($rows as $row) {
    $out[$row['volume_id']] = (int) $row['n'];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
