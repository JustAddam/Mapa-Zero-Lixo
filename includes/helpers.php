<?php
/**
 * Título: helpers.php — funções auxiliares de HTTP, sessão e upload
 * Autoria: ADDAM S. C
 *
 * Mensagens flash, redirecionamento, CSRF, JSON e gravação de imagens.
 */

/** Guarda ou devolve avisos temporários (sucesso/erro) após um redirecionamento. */
function flash(?string $type = null, ?string $message = null)
{
    if ($type && $message) {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        return null;
    }
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Envia o visitante para outra URL e interrompe o script. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Endereço público do site (config ou protocolo + host atuais). */
function app_url(): string
{
    $cfg = rtrim((string) ($GLOBALS['APP_CONFIG']['app']['url'] ?? ''), '/');
    if ($cfg !== '') {
        return $cfg;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Caminho da URL atual, sem barra final (exceto a raiz). */
function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = $uri === '' ? '/' : $uri;
    if ($uri !== '/' && str_ends_with($uri, '/')) {
        $uri = rtrim($uri, '/');
    }
    return $uri;
}

/** True quando o formulário foi enviado via POST. */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Lê e limpa um campo enviado no POST. */
function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/** Token secreto da sessão para impedir envio de formulário de outro site. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto HTML com o token CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Interrompe a requisição se o token CSRF for inválido. */
function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Requisição inválida.');
    }
}

/** Responde JSON (APIs de upload, geocode e webhook). */
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Valida e grava imagem (JPG/PNG/WEBP, até 5 MB) em /uploads. */
function save_upload(string $folder, string $field = 'file'): array
{
    if (empty($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return ['error' => 'Arquivo vazio.'];
    }
    $file = $_FILES[$field];
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['error' => 'Arquivo maior que 5 MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return ['error' => 'Formato não suportado. Use JPG, PNG ou WEBP.'];
    }
    $dir = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['error' => 'Não foi possível criar a pasta de upload.'];
    }
    $name = time() . '-' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['error' => 'Falha ao gravar o arquivo.'];
    }
    // Caminho relativo: a imagem abre no mesmo domínio, mesmo se app.url estiver errado.
    return ['url' => '/uploads/' . $folder . '/' . $name];
}

/** Grava imagens coladas em data:URL e devolve o HTML com src em /uploads. */
function persist_inline_images(string $html, string $folder = 'manual'): string
{
    return preg_replace_callback(
        '/(<img\b[^>]*?\bsrc\s*=\s*)([\'"])data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=]+)\2/i',
        function ($m) use ($folder) {
            $bin = base64_decode($m[4], true);
            if ($bin === false || $bin === '' || strlen($bin) > 5 * 1024 * 1024) {
                return $m[0];
            }
            $ext = strtolower($m[3]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $folder = preg_replace('/[^a-z0-9-]+/i', '', $folder) ?: 'manual';
            $dir = dirname(__DIR__) . '/uploads/' . $folder;
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return $m[0];
            }
            $name = time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (file_put_contents($dir . '/' . $name, $bin) === false) {
                return $m[0];
            }
            return $m[1] . $m[2] . '/uploads/' . $folder . '/' . $name . $m[2];
        },
        $html
    ) ?? $html;
}

/** Entrega arquivo de /uploads com MIME correto (quando o rewrite manda para o index). */
function serve_public_upload(string $path): bool
{
    if (!preg_match('#^/uploads/([a-z0-9-]+)/([A-Za-z0-9._-]+\.(jpe?g|png|webp))$#i', $path, $m)) {
        return false;
    }
    $file = dirname(__DIR__) . '/uploads/' . $m[1] . '/' . $m[2];
    if (!is_file($file)) {
        return false;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    return true;
}

/** Upload JSON usado por /api/upload.php e pelo roteador do index. */
function handle_image_upload(): void
{
    $user = current_user();
    if (!$user) {
        json_out(['error' => 'Entre para enviar uma imagem.'], 401);
    }
    $kind = $_GET['kind'] ?? 'org';
    if ($kind === 'manual' || $kind === 'admin') {
        if (($user['role'] ?? '') !== 'admin' || !admin_gate_ok((int) $user['id'])) {
            json_out(['error' => 'Desbloqueie a Administração para enviar imagens.'], 403);
        }
        $folder = $kind === 'manual' ? 'manual' : 'admin';
    } else {
        $folder = 'org-' . (int) $user['id'];
    }
    $result = save_upload($folder, 'file');
    if (isset($result['error'])) {
        json_out($result, 400);
    }
    json_out($result);
}

/** Classe CSS do item de menu da seção atual. */
function nav_active(string $section, string $current): string
{
    return $section === $current ? 'nav-link active' : 'nav-link';
}

/** Endereço amigável do artigo a partir do título (ou do slug digitado). */
function article_slug(string $title, string $slug = ''): string
{
    $base = trim($slug) !== '' ? $slug : $title;
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
    $base = is_string($ascii) && $ascii !== '' ? $ascii : $base;
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'artigo';
    }
    return substr($base, 0, 220);
}

/** Completa http(s) em links de referência; vazio vira null. */
function normalize_optional_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (preg_match('#^(https?:|mailto:|/)#i', $url)) {
        return $url;
    }
    return 'https://' . $url;
}
