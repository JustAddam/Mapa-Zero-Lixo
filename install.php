<?php
/**
 * Título: install.php — instalação única no servidor
 * Autoria: ADDAM S. C
 *
 * Cria tabelas, conta admin e textos iniciais. Só deve rodar uma vez.
 */

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    exit('Copie config.sample.php para config.php antes de instalar.');
}
if (is_file(__DIR__ . '/uploads/.installed')) {
    exit('Instalação já concluída. Apague uploads/.installed somente se souber o que está fazendo.');
}

$GLOBALS['APP_CONFIG'] = require $configPath;
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/sanitize.php';

$cfg = $GLOBALS['APP_CONFIG'];
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['db']['host'], $cfg['db']['name'], $cfg['db']['charset'] ?? 'utf8mb4');
$pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 1) Cria as tabelas se ainda não existirem.
install_schema($pdo);

// 2) Garante a conta do administrador definida em config.php.
$app = $cfg['app'];
$email = strtolower((string) $app['owner_email']);
$hash = password_hash((string) $app['owner_password'], PASSWORD_DEFAULT);
$exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$email]);
if (!$exists->fetch()) {
    $pdo->prepare('INSERT INTO users (openId, name, email, loginMethod, role, passwordHash) VALUES (?,?,?,?,?,?)')
        ->execute(['local-owner', $app['owner_name'], $email, 'password', 'admin', $hash]);
} else {
    $pdo->prepare('UPDATE users SET name = ?, role = ?, loginMethod = ?, passwordHash = ? WHERE email = ?')
        ->execute([$app['owner_name'], 'admin', 'password', $hash, $email]);
}

// 3) Insere os textos padrão da home, do manual e do rodapé.
$stmt = $pdo->prepare('INSERT IGNORE INTO site_content (contentKey, contentValue) VALUES (?, ?)');
foreach (CONTENT_DEFAULTS as $key => $value) {
    $stmt->execute([$key, $value]);
}

if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
}
// 4) Marca a instalação como concluída para não repetir este script.
file_put_contents(__DIR__ . '/uploads/.installed', date('c'));

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Instalado</title><body style="font-family:sans-serif;padding:40px">';
echo '<h1>mapazerolixo instalado</h1><p>Tabelas criadas. Entre com <strong>' . htmlspecialchars($email) . '</strong> em <a href="/login">/login</a>.</p>';
echo '<p>Painel: <a href="/area-privada-mapazerolixo">/area-privada-mapazerolixo</a> (senha da conta + senha administrativa do config).</p>';
echo '<p>Por segurança, apague ou proteja <code>install.php</code> depois.</p></body>';
