<?php
/**
 * Título: layout.php — cabeçalho, rodapé e blocos visuais reutilizáveis
 * Autoria: ADDAM S. C
 *
 * Monta o HTML comum de todas as páginas (menu, título do site e rodapé).
 */

/** Exibe avisos de sucesso ou erro guardados na sessão. */
function render_flash(): void
{
    foreach (flash() as $item) {
        $color = ($item['type'] ?? '') === 'error' ? '#a7543e' : '#173d32';
        echo '<div class="demo-notice" style="justify-content:center;border-bottom:1px solid #dbe2d8;color:' . e($color) . '">' . e($item['message']) . '</div>';
    }
}

/** Abre o HTML da página: título, CSS e barra de navegação. */
function render_header(string $section, array $content): void
{
    $user = current_user();
    $menuOpen = false;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
  <title>mapazerolixo</title>
  <meta name="author" content="ADDAM S. C" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Ephesis&family=Kumbh+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <link rel="stylesheet" href="/css/app.css?v=<?= (int) (@filemtime(__DIR__ . '/../css/app.css') ?: time()) ?>" />
</head>
<body class="min-h-screen bg-sand text-ink">
  <header class="site-header">
    <div class="container flex-row items-center justify-between gap-6 py-4">
      <a class="brand-lockup" href="/" aria-label="Voltar para o mapa da mapazerolixo">
        <span class="brand-mark">🌿</span>
        <span>
          <span class="brand-name">mapazerolixo</span>
          <span class="brand-caption">Macapá, AP</span>
        </span>
      </a>
      <nav class="main-nav" id="main-nav" aria-label="Navegação principal">
        <a class="<?= nav_active('mapa', $section) ?>" href="/">Mapa de coleta</a>
        <a class="<?= nav_active('manual', $section) ?>" href="/manual">Manual de Reciclagem</a>
        <a class="<?= nav_active('cadastro', $section) ?>" href="/cadastro">Mais opções</a>
        <?php if ($user && ($user['role'] ?? '') === 'user'): ?>
          <a class="<?= nav_active('workspace', $section) ?>" href="/workspace">Minha página</a>
        <?php endif; ?>
      </nav>
      <div class="header-actions">
        <?php if ($user): ?>
          <a class="user-chip" href="/sair" title="Sair da conta">
            <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
            <span class="hidden sm-inline"><?= e(explode(' ', (string) $user['name'])[0] ?: 'Conta') ?></span>
            <span>Sair</span>
          </a>
        <?php else: ?>
          <a class="button button-ghost button-small" href="/login">Entrar</a>
        <?php endif; ?>
        <button class="menu-trigger" type="button" id="menu-trigger" aria-label="Abrir menu">☰</button>
      </div>
    </div>
  </header>
  <?php render_flash(); ?>
    <?php
}

/** Fecha a página com o rodapé institucional e os scripts do mapa. */
function render_footer(array $content): void
{
    ?>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div><span class="brand-name light">mapazerolixo</span><div class="rich-text-output"><?= rich($content['footerDescription']) ?></div></div>
      <div>
        <span class="footer-label">Navegue</span>
        <a href="/">Mapa de coleta</a>
        <a href="/manual">Manual de Reciclagem</a>
      </div>
      <div>
        <span class="footer-label">Hospede sua iniciativa</span>
        <div class="rich-text-output"><?= rich($content['footerHostDescription']) ?></div>
        <a class="footer-cta" href="/cadastro">Quero fazer parte →</a>
      </div>
    </div>
    <div class="container footer-bottom">
      <span>© <?= date('Y') ?> mapazerolixo</span>
      <!-- Autoria do site e dos scripts -->
      <span class="footer-credit">Autoria: ADDAM S. C</span>
      <span><?= e($content['footerCity']) ?></span>
    </div>
  </footer>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="/js/app.js"></script>
</body>
</html>
    <?php
}

/** Convite para entrar na conta quando a página exige login. */
function login_prompt(string $title, string $text, string $next): void
{
    ?>
    <section class="content-page">
      <div class="container login-prompt">
        <span class="card-icon" style="width:54px;height:54px;font-size:22px">🔐</span>
        <div>
          <span class="section-kicker">Acesso protegido</span>
          <h1><?= e($title) ?></h1>
          <p><?= e($text) ?></p>
          <a class="button button-primary" href="/login?next=<?= e(urlencode($next)) ?>">Entrar com minha conta →</a>
          <p class="field-help" style="margin-top:12px">Ainda não tem conta? <a class="text-link" href="/conta?next=<?= e(urlencode($next)) ?>">Criar conta</a></p>
        </div>
      </div>
    </section>
    <?php
}

/** Desenha 1 a 5 estrelas conforme a nota da avaliação. */
function stars(int $value, bool $small = false): string
{
    $html = '<span class="' . ($small ? 'star-row small' : 'star-row') . '">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $value ? '★' : '☆';
    }
    return $html . '</span>';
}

/** Lista avaliações do ponto e, se permitido, o formulário para nova nota. */
function render_reviews(array $data, int $orgId, bool $canPost): void
{
    $average = $data['average'] ?? 0;
    $count = $data['count'] ?? 0;
    ?>
    <div class="reviews-panel">
      <div class="reviews-heading">
        <div>
          <span class="section-kicker">Avaliações da comunidade</span>
          <div class="rating-summary">
            <span class="rating-number"><?= $average ? number_format($average, 1, ',', '.') : '—' ?></span>
            <?= stars((int) round($average)) ?>
            <span class="rating-count"><?= (int) $count ?> <?= $count === 1 ? 'avaliação' : 'avaliações' ?></span>
          </div>
        </div>
      </div>
      <div class="review-list">
        <?php if (!empty($data['reviews'])): ?>
          <?php foreach ($data['reviews'] as $review): ?>
            <article class="review-item">
              <div class="review-meta"><strong><?= e($review['authorName'] ?: 'Pessoa da comunidade') ?></strong><?= stars((int) $review['rating'], true) ?></div>
              <p><?= e($review['comment']) ?></p>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="reviews-empty"><span>Seja a primeira pessoa a contar como foi usar este ponto.</span></div>
        <?php endif; ?>
      </div>
      <?php if ($orgId < 1): ?>
        <div class="reviews-empty"><span>As avaliações aparecem quando este ponto de demonstração for cadastrado.</span></div>
      <?php elseif ($canPost): ?>
        <form class="review-form" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="review" />
          <input type="hidden" name="organizationId" value="<?= (int) $orgId ?>" />
          <span class="form-label">Como foi sua experiência?</span>
          <div class="interactive-stars" data-stars>
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" class="star-button" data-value="<?= $i ?>" aria-label="<?= $i ?> estrelas">★</button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" value="0" />
          <textarea name="comment" required minlength="3" maxlength="1000" placeholder="Compartilhe uma informação útil para quem vai ao ponto..."></textarea>
          <button class="button button-primary button-small" type="submit">Publicar avaliação</button>
        </form>
      <?php else: ?>
        <a class="button button-ghost button-small" href="/login?next=<?= e(urlencode($_SERVER['REQUEST_URI'] ?? '/')) ?>">Entrar para avaliar</a>
      <?php endif; ?>
    </div>
    <?php
}
