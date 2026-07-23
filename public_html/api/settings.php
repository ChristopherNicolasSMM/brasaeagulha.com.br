<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

// Todas as chaves aqui são informação que já é pública no próprio cartão
// (telefone, e-mail, redes sociais, chave PIX) — não há dado sensível
// neste endpoint, por isso ele não exige login.
echo json_encode(ba_get_settings(), JSON_UNESCAPED_UNICODE);
