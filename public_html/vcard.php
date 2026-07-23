<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$s = ba_get_settings();

/**
 * Escapa texto conforme RFC 2426 (vCard 3.0): barra invertida, vírgula,
 * ponto-e-vírgula e quebra de linha precisam de \ antes.
 */
function vcard_escape(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    $value = str_replace(',', '\\,', $value);
    $value = str_replace(';', '\\;', $value);
    return $value;
}

$orgName = $s['org_name'] ?? 'Brasa & Agulha Editorial';
$editorName = $s['editor_name'] ?? '';
$editorTitle = $s['editor_title'] ?? '';
$note = $s['note'] ?? '';
$phone = $s['phone'] ?? '';
$email = $s['email'] ?? '';
$addressLine = $s['address_line'] ?? '';
$siteUrl = $s['site_url'] ?? '';
$photoUrl = $s['photo_url'] ?? '';
$logoUrl = $s['logo_url'] ?? '';

// FN é o que aparece em destaque na lista de contatos quando X-ABShowAs é
// COMPANY (Apple) — por isso usamos o nome da editora aqui, e colocamos o
// responsável no TITLE. Se preferir mostrar como pessoa física com a
// editora como empresa secundária, é só remover a linha X-ABShowAs abaixo
// e trocar FN para o nome do editor.
$fn = vcard_escape($orgName);
$titleLine = trim($editorTitle . ($editorName !== '' ? ' — ' . $editorName : ''));

// ADR: 7 componentes separados por ; (caixa postal;complemento;rua;cidade;estado;cep;país)
// Aqui simplificamos guardando a linha pronta e colocando tudo no componente "rua".
$adrParts = array_map('vcard_escape', ['', '', $addressLine, '', '', '', 'Brasil']);
$adr = implode(';', $adrParts);

$lines = [];
$lines[] = 'BEGIN:VCARD';
$lines[] = 'VERSION:3.0';
$lines[] = 'PRODID:-//Brasa & Agulha Editorial//vCard 3.0//PT-BR';
$lines[] = 'LANG:pt-BR';
$lines[] = 'UID:' . vcard_escape($siteUrl !== '' ? rtrim($siteUrl, '/') . '/vcard' : 'brasaagulha.com.br');
$lines[] = 'REV:' . gmdate('Ymd\THis\Z');
$lines[] = 'FN:' . $fn;
$lines[] = 'X-ABShowAs:COMPANY';
$lines[] = 'ORG:' . vcard_escape($orgName);
if ($titleLine !== '') {
    $lines[] = 'TITLE:' . vcard_escape($titleLine);
}
if ($phone !== '') {
    $lines[] = 'TEL;TYPE=CELL,VOICE:' . vcard_escape($phone);
}
if ($email !== '') {
    $lines[] = 'EMAIL;TYPE=WORK:' . vcard_escape($email);
}
if ($addressLine !== '') {
    $lines[] = 'ADR;TYPE=WORK:' . $adr;
}
if ($siteUrl !== '') {
    $lines[] = 'URL:' . vcard_escape($siteUrl);
}
if ($photoUrl !== '') {
    $lines[] = 'PHOTO;VALUE=URI:' . vcard_escape($photoUrl);
}
if ($logoUrl !== '') {
    $lines[] = 'LOGO;VALUE=URI:' . vcard_escape($logoUrl);
}
if ($note !== '') {
    $lines[] = 'NOTE:' . vcard_escape($note);
}
$lines[] = 'CATEGORIES:Editorial,Livros,Publicações';
$lines[] = 'END:VCARD';

$body = implode("\r\n", $lines) . "\r\n";

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="brasa-agulha.vcf"');
header('Content-Length: ' . strlen($body));
echo $body;
