<?php
/**
 * Título: upload.php — envio de imagens (capa, manual e painel)
 * Autoria: ADDAM S. C
 *
 * Legenda: grava JPG/PNG/WEBP em /uploads; admin precisa do portão desbloqueado.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

handle_image_upload();
