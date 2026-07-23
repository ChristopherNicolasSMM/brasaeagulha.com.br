<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$volumeId = trim((string) ($input['volume_id'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$whatsapp = trim((string) ($input['whatsapp'] ?? ''));
$birthday = trim((string) ($input['birthday'] ?? ''));

if ($volumeId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Informe um e-mail válido.']);
    exit;
}
if ($whatsapp === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Informe um WhatsApp para contato.']);
    exit;
}
if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
    $birthday = '';
}

$pdo = ba_db();

$check = $pdo->prepare('SELECT id FROM volumes WHERE id = ?');
$check->execute([$volumeId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Título não encontrado.']);
    exit;
}

// INSERT OR IGNORE (UNIQUE volume_id+email evita duplicar); se já existia,
// atualiza whatsapp/aniversário caso a pessoa mande de novo com dado novo.
$stmt = $pdo->prepare('INSERT INTO stock_interest (volume_id, email, whatsapp, birthday) VALUES (?, ?, ?, ?)
    ON CONFLICT(volume_id, email) DO UPDATE SET whatsapp = excluded.whatsapp, birthday = CASE WHEN excluded.birthday != \'\' THEN excluded.birthday ELSE stock_interest.birthday END');
$stmt->execute([$volumeId, $email, $whatsapp, $birthday]);

echo json_encode(['ok' => true]);
