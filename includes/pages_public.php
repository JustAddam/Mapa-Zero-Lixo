<?php
/**
 * Título: pages_public.php — páginas abertas ao público
 * Autoria: ADDAM S. C
 *
 * Mapa de coleta, manual, login e criação de conta.
 */

/** Recebe POST de avaliação (nota + comentário) e grava no banco. */
function handle_review_post(PDO $pdo): void
{
    verify_csrf();
    $user = current_user();
    if (!$user) {
        flash('error', 'Entre para avaliar.');
        redirect('/login');
    }
    $orgId = (int) post('organizationId');
    $rating = (int) post('rating');
    $comment = post('comment');
    if ($orgId < 1 || $rating < 1 || $rating > 5 || mb_strlen($comment) < 3) {
        flash('error', 'Preencha nota e comentário.');
        redirect($_SERVER['REQUEST_URI'] ?? '/');
    }
    if (!get_approved_org($pdo, $orgId)) {
        flash('error', 'Ponto de coleta não encontrado.');
        redirect('/');
    }
    upsert_review($pdo, $orgId, (int) $user['id'], $rating, $comment);
    flash('success', 'Avaliação publicada.');
    redirect($_SERVER['REQUEST_URI'] ?? '/');
}

/** Página inicial: mapa, filtros, lista de pontos e detalhe selecionado. */
function page_map(PDO $pdo, array $content): void
{
    if (is_post() && post('action') === 'review') {
        handle_review_post($pdo);
    }
    $real = list_approved_orgs($pdo);
    // Sem cadastros reais, o mapa mostra pontos de demonstração.
    $isDemo = count($real) === 0;
    $all = $isDemo ? demo_organizations() : $real;
    $category = $_GET['categoria'] ?? 'Todos';
    $search = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
    $visible = array_values(array_filter($all, function ($point) use ($category, $search) {
        $matchesCat = $category === 'Todos' || ($point['category'] ?? '') === $category;
        $hay = mb_strtolower(($point['name'] ?? '') . ' ' . ($point['address'] ?? '') . ' ' . ($point['category'] ?? ''));
        $matchesSearch = $search === '' || mb_strpos($hay, $search) !== false;
        return $matchesCat && $matchesSearch;
    }));
    $selectedId = (int) ($_GET['ponto'] ?? 0);
    $selected = null;
    foreach ($visible as $point) {
        if ((int) $point['id'] === $selectedId) {
            $selected = $point;
            break;
        }
    }
    render_header('mapa', $content);
    $cats = array_merge(['Todos'], CATEGORIES);
    ?>
    <main>
      <section class="map-page">
        <div class="container hero-map">
          <div class="hero-copy">
            <div class="eyebrow"><span class="eyebrow-line"></span> Território em movimento</div>
            <h1><?= e($content['homeHeroTitle']) ?><br /><em><?= e($content['homeHeroEmphasis']) ?></em></h1>
            <div class="hero-lede rich-text-output"><?= rich($content['homeDescription']) ?></div>
            <div class="hero-actions">
              <a class="button button-primary" href="/cadastro"><?= e($content['homeCta']) ?> →</a>
              <span class="hero-note">✔ <?= e($content['homeTrust']) ?></span>
            </div>
          </div>
          <div class="hero-aside"><span class="hero-number"><?= str_pad((string) count($all), 2, '0', STR_PAD_LEFT) ?></span><span>pontos mapeados<br />na experiência atual</span></div>
        </div>
        <div class="container map-layout">
          <aside class="map-sidebar">
            <div class="section-kicker">Explore os pontos</div>
            <h2>Onde descartar?</h2>
            <p class="muted">Filtre por tipo de iniciativa ou busque pelo nome do ponto.</p>
            <form method="get" class="search-field">
              <input name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Buscar no mapa" aria-label="Buscar no mapa" />
              <?php if ($category !== 'Todos'): ?><input type="hidden" name="categoria" value="<?= e($category) ?>" /><?php endif; ?>
            </form>
            <div class="filter-row" aria-label="Filtros por categoria">
              <?php foreach ($cats as $item): ?>
                <a class="<?= $category === $item ? 'filter-pill active' : 'filter-pill' ?>" href="/?categoria=<?= e(urlencode($item)) ?><?= $search !== '' ? '&q=' . e(urlencode((string) ($_GET['q'] ?? ''))) : '' ?>"><?= e($item) ?></a>
              <?php endforeach; ?>
            </div>
            <div class="results-count"><?= count($visible) ?> <?= count($visible) === 1 ? 'resultado' : 'resultados' ?></div>
            <div class="point-list">
              <?php foreach ($visible as $point): ?>
                <a class="<?= $selected && (int) $selected['id'] === (int) $point['id'] ? 'point-item active' : 'point-item' ?>" href="/?ponto=<?= (int) $point['id'] ?>&categoria=<?= e(urlencode($category)) ?><?= $search !== '' ? '&q=' . e(urlencode((string) ($_GET['q'] ?? '')) ) : '' ?>">
                  <span class="point-icon">📍</span>
                  <span class="point-copy"><strong><?= e($point['name']) ?></strong><span><?= e($point['category']) ?></span><small><?= e($point['address']) ?></small></span>
                </a>
              <?php endforeach; ?>
              <?php if (!$visible): ?>
                <div class="empty-state"><strong>Nenhum ponto encontrado</strong><span>Tente trocar o filtro ou a busca.</span></div>
              <?php endif; ?>
            </div>
          </aside>
          <div class="map-shell">
            <div class="map-toolbar"><span><span class="live-dot"></span> Mapa interativo</span><span>Macapá, Amapá</span></div>
            <div class="map-canvas"><div id="map" class="map-frame" data-points='<?= e(json_encode(array_map(function ($p) {
                return ['id' => (int) $p['id'], 'name' => $p['name'], 'lat' => (float) $p['latitude'], 'lng' => (float) $p['longitude']];
            }, $visible), JSON_UNESCAPED_UNICODE)) ?>' data-selected="<?= $selected ? (int) $selected['id'] : 0 ?>"></div></div>
            <?php if ($isDemo): ?>
              <div class="demo-notice"><span>Visualização de demonstração: os pontos reais aparecem aqui assim que forem aprovados.</span></div>
            <?php endif; ?>
            <?php if ($selected): ?>
              <div class="map-detail-card">
                <div class="map-detail-copy">
                  <span class="tag"><?= e($selected['category']) ?></span>
                  <h3><?= e($selected['name']) ?></h3>
                  <p><?= e($selected['address']) ?></p>
                  <?php if ((int) $selected['id'] > 0): ?>
                    <a class="text-link" href="/perfil/<?= (int) $selected['id'] ?>">Ver perfil completo →</a>
                    <?php render_reviews(list_org_reviews($pdo, (int) $selected['id']), (int) $selected['id'], (bool) current_user()); ?>
                  <?php else: ?>
                    <?php render_reviews(['reviews' => [], 'average' => 0, 'count' => 0], (int) $selected['id'], false); ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="container trust-strip"><div><span><strong>Descarte melhor.</strong> Comece pelo ponto mais próximo.</span></div><span class="trust-rule"></span><span class="muted">Uma rede local com espaço para crescer.</span></div>
      </section>
    </main>
    <?php
    render_footer($content);
}

/** Manual de Reciclagem: cards de orientação e artigos publicados. */
function page_manual(PDO $pdo, array $content): void
{
    $articles = published_articles($pdo);
    render_header('manual', $content);
    $cards = [
        ['01', $content['manualCard1Title'], $content['manualCard1Text']],
        ['02', $content['manualCard2Title'], $content['manualCard2Text']],
        ['03', $content['manualCard3Title'], $content['manualCard3Text']],
        ['04', $content['manualCard4Title'], $content['manualCard4Text']],
    ];
    ?>
    <main>
      <section class="content-page">
        <div class="container narrow-content">
          <a class="back-link" href="/">← Voltar ao mapa</a>
          <div class="eyebrow"><span class="eyebrow-line"></span> Manual de Reciclagem</div>
          <h1>Pequenas escolhas,<br /><em>grandes ciclos.</em></h1>
          <div class="intro-copy rich-text-output"><?= rich($content['manualIntro']) ?></div>
          <div class="manual-grid">
            <?php foreach ($cards as $card): ?>
              <article class="manual-card">
                <div class="manual-card-top"><span class="card-number"><?= e($card[0]) ?></span><span class="card-icon">♻</span></div>
                <h2><?= e($card[1]) ?></h2>
                <div class="rich-text-output"><?= rich($card[2]) ?></div>
              </article>
            <?php endforeach; ?>
          </div>
          <section class="manual-articles" id="leituras">
            <div class="section-kicker">Leituras da rede</div>
            <h2>Conteúdos para continuar o cuidado.</h2>
            <?php if ($articles): ?>
              <div class="manual-article-grid">
                <?php foreach ($articles as $article): ?>
                  <?php
                    $excerptHtml = (string) ($article['excerpt'] ?? '');
                    $bodyHtml = (string) ($article['body'] ?? '');
                    $cover = public_media_url($article['imageUrl'] ?? '');
                    if ($cover === '') {
                        foreach ([$bodyHtml, $excerptHtml] as $htmlSource) {
                            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $htmlSource, $imgMatch)) {
                                $cover = public_media_url($imgMatch[1]);
                                if ($cover !== '') {
                                    break;
                                }
                            }
                        }
                    }
                    if ($cover !== '') {
                        $excerptHtml = preg_replace('/<img\b[^>]*>/i', '', $excerptHtml) ?? $excerptHtml;
                        $bodyHtml = preg_replace('/<img\b[^>]*>/i', '', $bodyHtml) ?? $bodyHtml;
                    }
                  ?>
                  <article class="manual-article-card<?= $cover === '' ? ' no-cover' : '' ?>" id="artigo-<?= e($article['slug']) ?>">
                    <?php if ($cover !== ''): ?>
                      <figure class="manual-article-cover">
                        <img src="<?= e($cover) ?>" alt="<?= e($article['title']) ?>" width="220" height="220" />
                      </figure>
                    <?php endif; ?>
                    <div class="manual-article-copy">
                      <span class="tag">Artigo</span>
                      <h3><?= e($article['title']) ?></h3>
                      <?php if (trim(strip_tags($excerptHtml)) !== ''): ?><div class="article-excerpt rich-text-output"><?= rich($excerptHtml) ?></div><?php endif; ?>
                      <?php if (trim(strip_tags($bodyHtml)) !== ''): ?>
                        <div class="article-body rich-text-output"><?= rich($bodyHtml) ?></div>
                      <?php endif; ?>
                      <?php if ($article['linkUrl']): ?><a class="text-link" href="<?= e($article['linkUrl']) ?>" target="_blank" rel="noreferrer">Ver referência</a><?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="muted">Novos artigos publicados no painel aparecem aqui.</p>
            <?php endif; ?>
          </section>
          <div class="manual-callout">
            <div>
              <span class="section-kicker">Para lembrar</span>
              <h2><?= e($content['manualCalloutTitle']) ?></h2>
              <div class="rich-text-output"><?= rich($content['manualCalloutText']) ?></div>
            </div>
            <div class="callout-stamp">cuidar<br /><em>é agir</em></div>
          </div>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}

/** Formulário de entrada com e-mail e senha. */
function page_login(PDO $pdo, array $content): void
{
    $next = (string) ($_GET['next'] ?? '/');
    if (is_post()) {
        verify_csrf();
        $email = strtolower(post('email'));
        $password = (string) ($_POST['password'] ?? '');
        $user = find_user_by_email($pdo, $email);
        if (!$user || empty($user['passwordHash']) || !password_verify($password, $user['passwordHash'])) {
            flash('error', 'E-mail ou senha incorretos.');
            redirect('/login?next=' . urlencode($next));
        }
        $pdo->prepare('UPDATE users SET lastSignedIn = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $user['id']]);
        login_user($user);
        redirect($next !== '' ? $next : '/');
    }
    render_header('mapa', $content);
    ?>
    <main>
      <section class="content-page">
        <div class="container" style="max-width:480px">
          <div class="eyebrow"><span class="eyebrow-line"></span> Acesso</div>
          <h1>Entrar</h1>
          <p class="intro-copy">Use o e-mail e a senha cadastrados. No plano Hostinger o login Manus foi substituído por contas locais.</p>
          <form class="registration-form" method="post" style="margin-top:24px">
            <?= csrf_field() ?>
            <label class="form-field full"><span>E-mail</span><input type="email" name="email" required /></label>
            <label class="form-field full"><span>Senha</span><input type="password" name="password" required /></label>
            <button class="button button-primary full-button" type="submit">Entrar</button>
            <p class="form-legal">Não tem conta? <a href="/conta?next=<?= e(urlencode($next)) ?>">Criar conta</a></p>
          </form>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}

/** Cadastro de nova conta de usuário da comunidade. */
function page_register_user(PDO $pdo, array $content): void
{
    $next = (string) ($_GET['next'] ?? '/');
    if (is_post()) {
        verify_csrf();
        $name = post('name');
        $email = strtolower(post('email'));
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Informe nome, e-mail válido e senha com no mínimo 8 caracteres.');
            redirect('/conta?next=' . urlencode($next));
        }
        if (find_user_by_email($pdo, $email)) {
            flash('error', 'Já existe uma conta com este e-mail.');
            redirect('/login?next=' . urlencode($next));
        }
        $role = strcasecmp($email, (string) $GLOBALS['APP_CONFIG']['app']['owner_email']) === 0 ? 'admin' : 'user';
        $id = create_user($pdo, $name, $email, $password, $role);
        $user = db()->query('SELECT * FROM users WHERE id = ' . (int) $id)->fetch();
        login_user($user);
        flash('success', 'Conta criada.');
        redirect($next !== '' ? $next : '/');
    }
    render_header('mapa', $content);
    ?>
    <main>
      <section class="content-page">
        <div class="container" style="max-width:480px">
          <h1>Criar conta</h1>
          <form class="registration-form" method="post" style="margin-top:24px">
            <?= csrf_field() ?>
            <label class="form-field full"><span>Nome</span><input name="name" required minlength="2" /></label>
            <label class="form-field full"><span>E-mail</span><input type="email" name="email" required /></label>
            <label class="form-field full"><span>Senha</span><input type="password" name="password" required minlength="8" /></label>
            <button class="button button-primary full-button" type="submit">Cadastrar</button>
          </form>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}
