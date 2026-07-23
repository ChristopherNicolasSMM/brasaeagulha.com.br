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

// Lista branca: só estas chaves podem ser gravadas, nunca uma vinda livre do cliente.
$allowedKeys = [
    'org_name', 'editor_name', 'editor_title', 'note',
    'phone', 'email', 'address_line', 'site_url',
    'photo_url', 'logo_url',
    'whatsapp_number', 'whatsapp_message',
    'pix_key', 'pix_key_type',
    'instagram_url', 'youtube_url', 'location_maps_url',
    'catalogo_url', 'loja_url',
    'runomante_label', 'runomante_url',
    'site_oficial_url',
];

$values = $input['values'] ?? null;
if (!is_array($values)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato inválido: esperado { values: {...} }.']);
    exit;
}

$pdo = ba_db();
$upsert = $pdo->prepare(
    'INSERT INTO site_settings (key, value) VALUES (?, ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value'
);

$saved = [];
foreach ($allowedKeys as $key) {
    if (array_key_exists($key, $values)) {
        $value = (string) $values[$key];
        $upsert->execute([$key, $value]);
        $saved[] = $key;
    }
}

echo json_encode(['ok' => true, 'saved' => $saved]);
