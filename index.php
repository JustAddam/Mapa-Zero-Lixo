<?php
/**
 * Título: index.php — entrada e roteamento do site
 * Autoria: ADDAM S. C
 *
 * Lê a URL e chama a página correspondente (mapa, manual, login, cadastro, admin).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/pages_public.php';
require __DIR__ . '/includes/pages_org.php';
require __DIR__ . '/includes/pages_admin.php';

$path = request_path();

// Fotos em /uploads e o endpoint de envio, mesmo se o rewrite cair no index.
if (serve_public_upload($path)) {
    exit;
}
if ($path === '/api/upload.php' || $path === '/api/upload') {
    handle_image_upload();
    exit;
}

$pdo = db();
$content = get_site_content($pdo);

// Encerra a sessão e volta ao mapa.
if ($path === '/sair') {
    logout_user();
    redirect('/');
}

// Página inicial: mapa de coleta.
if ($path === '/') {
    page_map($pdo, $content);
    exit;
}
// Manual de Reciclagem.
if ($path === '/manual') {
    page_manual($pdo, $content);
    exit;
}
// Entrar na conta.
if ($path === '/login') {
    page_login($pdo, $content);
    exit;
}
// Criar conta de usuário.
if ($path === '/conta') {
    page_register_user($pdo, $content);
    exit;
}
// Solicitar hospedagem da iniciativa no mapa.
if ($path === '/cadastro') {
    page_cadastro($pdo, $content);
    exit;
}
// Área do responsável pela página aprovada.
if ($path === '/workspace') {
    page_workspace($pdo, $content);
    exit;
}
// Painel de curadoria (administrador).
if ($path === '/area-privada-mapazerolixo') {
    page_admin($pdo, $content);
    exit;
}
// Perfil público: /perfil/123
if (preg_match('#^/perfil/(\d+)$#', $path, $m)) {
    page_perfil($pdo, $content, (int) $m[1]);
    exit;
}

// Qualquer outro caminho: página 404.
http_response_code(404);
render_header('mapa', $content);
echo '<main><section class="content-page"><div class="container"><h1>Página não encontrada.</h1><a class="back-link" href="/">← Voltar ao mapa</a></div></section></main>';
render_footer($content);
