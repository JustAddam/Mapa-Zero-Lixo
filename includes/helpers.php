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
    return ['url' => app_url() . '/uploads/' . $folder . '/' . $name];
}

/** Classe CSS do item de menu da seção atual. */
function nav_active(string $section, string $current): string
{
    return $section === $current ? 'nav-link active' : 'nav-link';
}
