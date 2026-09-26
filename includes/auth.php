<?php
/**
 * Título: auth.php — login, sessão e acesso administrativo
 * Autoria: ADDAM S. C
 *
 * Identifica o usuário logado, protege rotas e valida a senha do painel.
 */

/** Usuário da sessão atual, ou null se ninguém estiver logado. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Exige login; senão redireciona para /login. */
function require_login(string $returnTo = '/'): void
{
    if (!current_user()) {
        flash('error', 'Entre na sua conta para continuar.');
        redirect('/login?next=' . urlencode($returnTo));
    }
}

/** Exige papel de administrador principal. */
function require_admin(): array
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        flash('error', 'Área reservada ao administrador principal.');
        redirect('/login?next=/area-privada-mapazerolixo');
    }
    return $user;
}

/** True se o painel admin foi desbloqueado nesta sessão (válido 2 horas). */
function admin_gate_ok(int $userId): bool
{
    $until = (int) ($_SESSION['admin_gate_until'] ?? 0);
    $uid = (int) ($_SESSION['admin_gate_user'] ?? 0);
    return $uid === $userId && $until > time();
}

/** Confere a senha do portão admin (config ou hash da conta). */
function verify_admin_secret(int $userId, string $password): bool
{
    if ($password === '') {
        return false;
    }
    $app = $GLOBALS['APP_CONFIG']['app'] ?? [];
    foreach (['admin_gate_password', 'owner_password'] as $key) {
        $expected = (string) ($app[$key] ?? '');
        if ($expected !== '' && hash_equals($expected, $password)) {
            return true;
        }
    }
    $stmt = db()->prepare('SELECT passwordHash, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return (bool) (
        $row
        && ($row['role'] ?? '') === 'admin'
        && is_string($row['passwordHash'] ?? null)
        && $row['passwordHash'] !== ''
        && password_verify($password, $row['passwordHash'])
    );
}

/** Libera o painel por duas horas após senha correta. */
function unlock_admin_gate(int $userId, string $password): bool
{
    if (!verify_admin_secret($userId, $password)) {
        return false;
    }
    $_SESSION['admin_gate_until'] = time() + 2 * 60 * 60;
    $_SESSION['admin_gate_user'] = $userId;
    return true;
}

/** Troca a senha do admin e devolve mensagem de erro, ou null se ok. */
function change_admin_password(PDO $pdo, int $userId, string $current, string $new, string $confirm): ?string
{
    if (!verify_admin_secret($userId, $current)) {
        return 'Senha atual incorreta.';
    }
    if (strlen($new) < 8) {
        return 'A nova senha precisa ter no mínimo 8 caracteres.';
    }
    if (!hash_equals($new, $confirm)) {
        return 'A confirmação não confere com a nova senha.';
    }
    if (hash_equals($current, $new)) {
        return 'A nova senha precisa ser diferente da atual.';
    }
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || ($row['role'] ?? '') !== 'admin') {
        return 'Somente o administrador principal pode alterar esta senha.';
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET passwordHash = ? WHERE id = ? AND role = ?')->execute([$hash, $userId, 'admin']);
    sync_config_app_passwords($new);
    return null;
}

/** Atualiza owner_password e admin_gate_password em config.php. */
function sync_config_app_passwords(string $newPassword): void
{
    $path = dirname(__DIR__) . '/config.php';
    if (!is_file($path) || !is_writable($path)) {
        return;
    }
    $src = file_get_contents($path);
    if (!is_string($src) || $src === '') {
        return;
    }
    $quoted = var_export($newPassword, true);
    $updated = preg_replace_callback(
        "/('(?:owner_password|admin_gate_password)'\\s*=>\\s*)('[^']*'|\"[^\"]*\")/",
        static function (array $m) use ($quoted): string {
            return $m[1] . $quoted;
        },
        $src
    );
    if (is_string($updated) && $updated !== $src) {
        file_put_contents($path, $updated, LOCK_EX);
    }
}

/** Grava o usuário na sessão após login bem-sucedido. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

/** Encerra a sessão e apaga o cookie. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Busca usuário pelo e-mail (login). */
function find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Cria conta com senha criptografada e devolve o id. */
function create_user(PDO $pdo, string $name, string $email, string $password, string $role = 'user'): int
{
    $openId = 'local-' . bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (openId, name, email, loginMethod, role, passwordHash) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$openId, $name, $email, 'password', $role, $hash]);
    return (int) $pdo->lastInsertId();
}
