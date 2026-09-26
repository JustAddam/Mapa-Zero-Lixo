<?php
/**
 * Título: upload.php — envio de imagens (capa, manual e painel)
 * Autoria: ADDAM S. C
 *
 * Legenda: grava JPG/PNG/WEBP em /uploads; admin precisa do portão desbloqueado.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$user = current_user();
if (!$user) {
    json_out(['error' => 'Entre para enviar uma imagem.'], 401);
}

$kind = $_GET['kind'] ?? 'org';
if ($kind === 'manual' || $kind === 'admin') {
    // Imagens do site e do manual só pelo administrador autenticado no painel.
    if (($user['role'] ?? '') !== 'admin' || !admin_gate_ok((int) $user['id'])) {
        json_out(['error' => 'Desbloqueie a Administração para enviar imagens.'], 403);
    }
    $folder = $kind === 'manual' ? 'manual' : 'admin';
} else {
    // Cada organização guarda arquivos na própria pasta.
    $folder = 'org-' . (int) $user['id'];
}

$result = save_upload($folder, 'file');
if (isset($result['error'])) {
    json_out($result, 400);
}
json_out($result);
