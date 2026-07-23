<?php
declare(strict_types=1);
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();

$pdo = ba_db();
$rows = $pdo->query(
    'SELECT si.*, v.volume_label, v.subtitle AS volume_subtitle, c.title AS collection_title
     FROM stock_interest si
     JOIN volumes v ON v.id = si.volume_id
     JOIN collections c ON c.id = v.collection_id
     ORDER BY si.campaign_sent ASC, si.created_at DESC'
)->fetchAll();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r['id'],
        'volumeId' => $r['volume_id'],
        'volumeLabel' => trim($r['collection_title'] . ' — ' . ($r['volume_subtitle'] ?: $r['volume_label'])),
        'email' => $r['email'],
        'whatsapp' => $r['whatsapp'],
        'birthday' => $r['birthday'],
        'campaignSent' => (bool) $r['campaign_sent'],
        'campaignSentAt' => $r['campaign_sent_at'],
        'createdAt' => $r['created_at'],
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
