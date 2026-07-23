<?php
declare(strict_types=1);

/**
 * Pasta física de imagens de um volume (cria se não existir).
 * Uma pasta por publicação: public_html/img/livros/{volume_id}/
 */
function ba_volume_image_dir(string $volumeId): string
{
    // BA_DB_PATH aponta pro arquivo do banco, que fica na raiz do projeto
    // (um nível acima de public_html) — usamos o mesmo ponto de referência
    // pra achar public_html/img/livros de forma confiável, não importa de
    // qual arquivo PHP esta função é chamada.
    $projectRoot = dirname(BA_DB_PATH);
    $dir = $projectRoot . '/public_html/img/livros/' . $volumeId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/** URL pública de uma imagem de volume. */
function ba_volume_image_url(string $volumeId, string $filename): string
{
    return '/img/livros/' . rawurlencode($volumeId) . '/' . rawurlencode($filename);
}

/** Lista as imagens de um volume (mais antiga primeiro), com a principal identificada. */
function ba_get_volume_images(PDO $pdo, string $volumeId): array
{
    $stmt = $pdo->prepare('SELECT * FROM volume_images WHERE volume_id = ? ORDER BY is_primary DESC, sort_order ASC');
    $stmt->execute([$volumeId]);
    $images = [];
    foreach ($stmt->fetchAll() as $row) {
        $images[] = [
            'id' => (int) $row['id'],
            'url' => ba_volume_image_url($volumeId, $row['filename']),
            'isPrimary' => (bool) $row['is_primary'],
        ];
    }
    return $images;
}
