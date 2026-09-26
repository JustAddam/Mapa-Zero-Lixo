<?php
/**
 * Título: bootstrap.php — inicialização da aplicação
 * Autoria: ADDAM S. C
 *
 * Carrega config.php, inicia a sessão segura e inclui os módulos comuns.
 */

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
// Sem config.php o site ainda não foi instalado: pede cópia do arquivo de exemplo.
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Instalação</title><body style="font-family:sans-serif;padding:40px;max-width:640px">';
    echo '<h1>mapazerolixo</h1><p>Copie <code>config.sample.php</code> para <code>config.php</code>, preencha o MySQL da Hostinger e abra <a href="/install.php">/install.php</a>.</p></body>';
    exit;
}

$GLOBALS['APP_CONFIG'] = require $configPath;

$app = $GLOBALS['APP_CONFIG']['app'];
session_name($app['session_name'] ?? 'mapazerolixo_sess');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/sanitize.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/content.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
