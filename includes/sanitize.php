<?php
/**
 * Título: sanitize.php — limpeza de HTML e escape para a tela
 * Autoria: ADDAM S. C
 *
 * Remove scripts perigosos do texto rico e escapa texto simples (XSS).
 */

/** URL pública de mídia (converte domínio antigo/errado para /uploads/...). */
function public_media_url(?string $url): string
{
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') {
        return '';
    }
    $url = str_replace('\\', '/', $url);
    if (preg_match('#(?:^|/)(uploads/.+)$#i', $url, $m)) {
        return '/' . ltrim($m[1], '/');
    }
    return $url;
}

/** Mantém só tags e atributos seguros no HTML do editor. */
function sanitize_rich_text(string $input): string
{
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'font', 'div', 'span', 'figure'];
    $text = preg_replace('/<!--[\s\S]*?-->/', '', $input) ?? '';
    $text = preg_replace('/<\s*(script|style|iframe|object|embed)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/i', '', $text) ?? '';
    $text = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text) ?? '';

    return preg_replace_callback('/<\/?([a-z0-9]+)([^>]*)>/i', function ($m) use ($allowed) {
        $tag = strtolower($m[1]);
        if (!in_array($tag, $allowed, true)) {
            return '';
        }
        if (str_starts_with($m[0], '</')) {
            return '</' . $tag . '>';
        }
        $attrs = preg_replace_callback('/\s([a-z-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', function ($a) {
            $name = strtolower($a[1]);
            $value = trim($a[2], "\"'");
            $ok = ['href', 'src', 'alt', 'target', 'rel', 'face', 'size', 'color', 'style', 'class', 'width', 'height'];
            if (!in_array($name, $ok, true)) {
                return '';
            }
            if ($name === 'src') {
                $value = public_media_url($value);
            }
            if (in_array($name, ['href', 'src'], true) && !preg_match('/^(https?:|\/|#|mailto:)/i', $value)) {
                return '';
            }
            if ($name === 'class' && !preg_match('/^[a-z0-9\s_-]+$/i', $value)) {
                return '';
            }
            if ($name === 'face' && !preg_match('/^(Kumbh Sans|Georgia|Arial|Verdana)$/i', $value)) {
                return '';
            }
            if ($name === 'size' && !preg_match('/^[2-5]$/', $value)) {
                return '';
            }
            if ($name === 'color' && !preg_match('/^#[0-9a-f]{3,8}$/i', $value) && !preg_match('/^[a-z]+$/i', $value)) {
                return '';
            }
            if ($name === 'style') {
                $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                if (preg_match('/expression|javascript\s*:|behavior|@import/i', $value)) {
                    return '';
                }
                $value = preg_replace_callback('/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', function ($u) {
                    $src = public_media_url(trim($u[1]));
                    if ($src !== '' && preg_match('#^(https?:|/uploads/)#i', $src)) {
                        return 'url(' . $src . ')';
                    }
                    return 'none';
                }, $value) ?? $value;
                $safe = preg_replace('/[^a-z0-9\s:;#(),.%\/\'"-]/i', '', $value) ?? '';
                $safe = preg_replace('/(position|behavior|expression|javascript|z-index|display\s*:\s*none)[^;]*;?/i', '', $safe) ?? '';
                return $safe !== '' ? ' style="' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . '"' : '';
            }
            return ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }, $m[2]) ?? '';
        return '<' . $tag . $attrs . '>';
    }, $text) ?? '';
}

/** Escape para imprimir texto na página sem HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** HTML rico já sanitizado, pronto para echo. */
function rich(?string $value): string
{
    return sanitize_rich_text((string) $value);
}
