<?php
/**
 * Título: pages_admin.php — painel de curadoria
 * Autoria: ADDAM S. C
 *
 * Aprova cadastros, edita o site, gerencia artigos e senha administrativa.
 */

/** Área privada: portão de senha, organizações, conteúdo e manual. */
function page_admin(PDO $pdo, array $content): void
{
    $user = current_user();
    render_header('admin', $content);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        login_prompt('Área de administração', 'Esta área é reservada exclusivamente ao administrador principal.', '/area-privada-mapazerolixo');
        render_footer($content);
        return;
    }

    if (is_post() && post('action') === 'unlock') {
        verify_csrf();
        if (unlock_admin_gate((int) $user['id'], (string) ($_POST['password'] ?? ''))) {
            flash('success', 'Administração desbloqueada por duas horas.');
        } else {
            flash('error', 'Senha administrativa incorreta.');
        }
        redirect('/area-privada-mapazerolixo');
    }

    if (!admin_gate_ok((int) $user['id'])) {
        ?>
        <main>
          <section class="content-page admin-gate-page">
            <div class="container admin-gate-card">
              <span class="section-kicker">Área reservada</span>
              <h1>Entrada discreta.</h1>
              <p>Use a mesma senha da sua conta de administrador para continuar.</p>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unlock" />
                <label class="form-field"><span>Senha administrativa</span><input type="password" name="password" autocomplete="current-password" /></label>
                <button class="button button-primary" type="submit">Desbloquear administração →</button>
              </form>
              <small class="field-help">É a senha do login (addamschagas@gmail.com).</small>
            </div>
          </section>
        </main>
        <?php
        render_footer($content);
        return;
    }

    if (is_post()) {
        verify_csrf();
        $action = post('action');
        // Aprova, suspende ou rejeita um cadastro no mapa.
        if ($action === 'set_status') {
            $id = (int) post('id');
            $status = post('status');
            if (in_array($status, ['approved', 'suspended', 'rejected'], true)) {
                $before = $pdo->query('SELECT * FROM organizations WHERE id = ' . $id)->fetch();
                $pdo->prepare('UPDATE organizations SET status = ? WHERE id = ?')->execute([$status, $id]);
                record_org_change($pdo, $id, (int) $user['id'], 'status', 'Status alterado para ' . $status . '.', $before, ['status' => $status]);
                flash('success', 'Status atualizado.');
            }
        // Publica ou recusa a personalização enviada pelo responsável.
        } elseif ($action === 'review_branding') {
            $id = (int) post('id');
            $decision = post('decision');
            $item = $pdo->query('SELECT * FROM organizations WHERE id = ' . $id)->fetch();
            if ($item && $decision === 'approved') {
                $pdo->prepare("UPDATE organizations SET
                    heroTitle = COALESCE(pendingHeroTitle, heroTitle),
                    heroText = COALESCE(pendingHeroText, heroText),
                    imageUrl = COALESCE(pendingImageUrl, imageUrl),
                    primaryColor = COALESCE(pendingPrimaryColor, primaryColor),
                    accentColor = COALESCE(pendingAccentColor, accentColor),
                    tagline = COALESCE(pendingTagline, tagline),
                    aboutText = COALESCE(pendingAboutText, aboutText),
                    servicesText = COALESCE(pendingServicesText, servicesText),
                    contactCta = COALESCE(pendingContactCta, contactCta),
                    instagramUrl = COALESCE(pendingInstagramUrl, instagramUrl),
                    facebookUrl = COALESCE(pendingFacebookUrl, facebookUrl),
                    pendingHeroTitle = NULL, pendingHeroText = NULL, pendingImageUrl = NULL, pendingPrimaryColor = NULL, pendingAccentColor = NULL,
                    pendingTagline = NULL, pendingAboutText = NULL, pendingServicesText = NULL, pendingContactCta = NULL, pendingInstagramUrl = NULL, pendingFacebookUrl = NULL,
                    brandingStatus = 'published' WHERE id = ?")->execute([$id]);
                flash('success', 'Personalização publicada.');
            } elseif ($item && $decision === 'rejected') {
                $pdo->prepare("UPDATE organizations SET brandingStatus = 'rejected' WHERE id = ?")->execute([$id]);
                flash('success', 'Personalização rejeitada.');
            }
            if ($item) {
                record_org_change($pdo, $id, (int) $user['id'], 'branding_' . $decision, $decision === 'approved' ? 'Personalização publicada.' : 'Personalização rejeitada.', $item, null);
            }
        // Textos da home, do manual e do rodapé.
        } elseif ($action === 'save_content') {
            $payload = [];
            foreach (array_keys(CONTENT_DEFAULTS) as $key) {
                $payload[$key] = (string) ($_POST[$key] ?? '');
            }
            save_site_content($pdo, $payload, (int) $user['id']);
            flash('success', 'Conteúdo publicado no site.');
        // Cria ou atualiza artigo do Manual de Reciclagem.
        } elseif ($action === 'save_article') {
            if (!empty($_FILES['cover']['tmp_name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
                $uploaded = save_upload('manual', 'cover');
                if (!empty($uploaded['url'])) {
                    $_POST['imageUrl'] = $uploaded['url'];
                } elseif (!empty($uploaded['error'])) {
                    flash('error', $uploaded['error']);
                }
            }
            $saved = save_manual_article($pdo, $_POST, (int) $user['id']);
            if (isset($saved['error'])) {
                flash('error', $saved['error']);
                $back = (int) post('id');
                redirect('/area-privada-mapazerolixo' . ($back ? '?artigo=' . $back : '') . '#artigos');
            }
            $published = ($saved['status'] ?? '') === 'published';
            flash('success', $published
                ? 'Artigo publicado. Ele já aparece no Manual de Reciclagem.'
                : 'Rascunho salvo. Publique para exibir na página pública.');
            redirect('/area-privada-mapazerolixo?artigo=' . (int) $saved['id'] . '#artigos');
        // Cadastro de plano (não cobrado nesta fase).
        } elseif ($action === 'save_plan') {
            $name = post('name');
            $description = post('description');
            $price = (int) round((float) str_replace(['.', ','], ['', '.'], post('price')) * 100);
            $trial = (int) post('trialDays');
            $pdo->prepare('INSERT INTO hosting_plans (name, description, priceCents, trialDays, isActive) VALUES (?,?,?,?,1)')->execute([$name, $description, $price, $trial]);
            flash('success', 'Plano salvo.');
        // Troca a senha do administrador e sincroniza o config.php.
        } elseif ($action === 'change_admin_password') {
            $error = change_admin_password(
                $pdo,
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            if ($error) {
                flash('error', $error);
            } else {
                flash('success', 'Senha do administrador atualizada. Use a nova senha no próximo login e nesta área.');
            }
            redirect('/area-privada-mapazerolixo#senha-admin');
        }
        redirect('/area-privada-mapazerolixo');
    }

    $pending = $pdo->query("SELECT * FROM organizations WHERE status = 'pending' ORDER BY createdAt DESC")->fetchAll();
    $allOrgs = $pdo->query('SELECT * FROM organizations ORDER BY updatedAt DESC')->fetchAll();
    $pendingBrand = $pdo->query("SELECT * FROM organizations WHERE status = 'approved' AND brandingStatus = 'pending' ORDER BY updatedAt DESC")->fetchAll();
    $articles = all_articles($pdo);
    $selectedOrg = (int) ($_GET['org'] ?? 0);
    $history = [];
    if ($selectedOrg) {
        $st = $pdo->prepare('SELECT * FROM organization_change_log WHERE organizationId = ? ORDER BY createdAt DESC');
        $st->execute([$selectedOrg]);
        $history = $st->fetchAll();
    }
    $form = $content;
    $articleId = (int) ($_GET['artigo'] ?? 0);
    $article = ['id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'imageUrl' => '', 'linkUrl' => '', 'status' => 'published'];
    foreach ($articles as $a) {
        if ((int) $a['id'] === $articleId) {
            $article = $a;
        }
    }
    ?>
    <main>
      <section class="content-page admin-custom-surface" style="background: <?= e($content['adminPanelPrimaryColor']) ?>">
        <div class="container dashboard-page">
          <div class="dashboard-heading">
            <div>
              <div class="eyebrow"><span class="eyebrow-line"></span> Área restrita</div>
              <h1><?= e($content['adminPanelTitle']) ?></h1>
              <div class="rich-text-output"><?= rich($content['adminPanelIntro']) ?></div>
            </div>
            <div class="dashboard-stat" style="border:2px solid <?= e($content['adminPanelAccentColor']) ?>"><span><?= count($pending) ?></span><small>aguardando análise</small></div>
          </div>

          <section class="content-editor admin-password-panel" id="senha-admin">
            <div class="card-header">
              <div>
                <span class="section-kicker">Conta do administrador</span>
                <h2>Trocar senha</h2>
              </div>
              <span class="tag"><?= e((string) ($user['email'] ?? '')) ?></span>
            </div>
            <p class="admin-password-hint">A senha nova vale para o login e para desbloquear este painel. Ela é gravada só no banco, em hash.</p>
            <form method="post" autocomplete="off">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_admin_password" />
              <div class="form-grid">
                <label class="form-field full"><span>Senha atual</span><input type="password" name="current_password" required autocomplete="current-password" /></label>
                <label class="form-field"><span>Nova senha</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password" /></label>
                <label class="form-field"><span>Confirmar nova senha</span><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" /></label>
              </div>
              <button class="button button-primary" type="submit">Salvar nova senha</button>
            </form>
          </section>

          <div class="admin-grid">
            <div class="dashboard-card wide">
              <div class="card-header"><div><span class="section-kicker">Novos cadastros</span><h2>Pedidos de hospedagem</h2></div></div>
              <?php if ($pending): ?>
                <div class="application-list">
                  <?php foreach ($pending as $item): ?>
                    <div class="application-item">
                      <div class="application-main"><span class="tag"><?= e($item['category']) ?></span><h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p><small><?= e($item['address']) ?></small></div>
                      <div class="application-actions">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="status" value="approved" /><button class="button button-primary button-small">Aprovar</button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="status" value="rejected" /><button class="button button-danger button-small">Recusar</button></form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-dashboard"><strong>Nenhum cadastro pendente.</strong></div>
              <?php endif; ?>
            </div>
            <div class="dashboard-card">
              <span class="section-kicker">Status do site</span>
              <h2>Operação saudável</h2>
              <div class="status-row"><span class="status-dot"></span> Mapa online</div>
              <div class="status-row"><span class="status-dot"></span> Banco conectado</div>
              <div class="dashboard-tip"><?= rich($content['adminPanelNotice']) ?></div>
            </div>
          </div>

          <section class="content-editor branding-review">
            <div class="card-header"><div><span class="section-kicker">Curadoria de páginas</span><h2>Personalizações aguardando aprovação</h2></div><span class="tag"><?= count($pendingBrand) ?> pendente(s)</span></div>
            <?php if ($pendingBrand): ?>
              <div class="application-list">
                <?php foreach ($pendingBrand as $item): ?>
                  <div class="application-item">
                    <div class="application-main">
                      <span class="tag"><?= e($item['name']) ?></span>
                      <h3><?= e($item['pendingHeroTitle'] ?: ($item['heroTitle'] ?: $item['name'])) ?></h3>
                    </div>
                    <div class="application-actions">
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_branding" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="decision" value="approved" /><button class="button button-primary button-small">Publicar</button></form>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_branding" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="decision" value="rejected" /><button class="button button-danger button-small">Rejeitar</button></form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-dashboard"><strong>Nenhuma personalização pendente.</strong></div>
            <?php endif; ?>
          </section>

          <section class="content-editor admin-organizations-panel">
            <div class="card-header"><div><span class="section-kicker">Gestão e auditoria</span><h2>Organizações hospedadas</h2></div><span class="tag"><?= count($allOrgs) ?> registro(s)</span></div>
            <div class="admin-org-layout">
              <div class="admin-org-list">
                <?php foreach ($allOrgs as $item): ?>
                  <article class="<?= $selectedOrg === (int) $item['id'] ? 'admin-org-item active' : 'admin-org-item' ?>">
                    <a class="admin-org-main" href="/area-privada-mapazerolixo?org=<?= (int) $item['id'] ?>"><strong><?= e($item['name']) ?></strong><small><?= e($item['category']) ?> · <?= e($item['status']) ?></small></a>
                    <?php if ($item['status'] === 'approved'): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="status" value="suspended" /><button class="button button-danger button-small" type="submit">Suspender</button></form>
                    <?php elseif ($item['status'] === 'suspended'): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status" /><input type="hidden" name="id" value="<?= (int) $item['id'] ?>" /><input type="hidden" name="status" value="approved" /><button class="button button-primary button-small" type="submit">Reativar</button></form>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
              <?php if ($selectedOrg): ?>
                <div class="audit-panel">
                  <span class="section-kicker">Histórico completo</span>
                  <h3>Alterações registradas</h3>
                  <div class="audit-list">
                    <?php foreach ($history as $change): ?>
                      <details class="audit-entry"><summary><strong><?= e($change['summary']) ?></strong><small><?= e($change['createdAt']) ?></small></summary>
                        <div class="audit-diff"><div><span>Antes</span><pre><?= e($change['beforeData'] ?: '—') ?></pre></div><div><span>Depois</span><pre><?= e($change['afterData'] ?: '—') ?></pre></div></div>
                      </details>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="content-editor">
            <div class="card-header"><div><span class="section-kicker">Conteúdo do site</span><h2>Edite tudo pelo navegador</h2></div><span class="tag">CMS ativo</span></div>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_content" />
              <?php
              $fields = [
                  'homeHeroTitle' => 'Título principal', 'homeHeroEmphasis' => 'Frase em destaque', 'homeDescription' => 'Descrição',
                  'homeCta' => 'Texto do botão', 'homeTrust' => 'Texto de confiança',
                  'manualIntro' => 'Introdução do manual', 'manualCard1Title' => 'Card 1 título', 'manualCard1Text' => 'Card 1 texto',
                  'manualCard2Title' => 'Card 2 título', 'manualCard2Text' => 'Card 2 texto',
                  'manualCard3Title' => 'Card 3 título', 'manualCard3Text' => 'Card 3 texto',
                  'manualCard4Title' => 'Card 4 título', 'manualCard4Text' => 'Card 4 texto',
                  'manualCalloutTitle' => 'Destaque final', 'manualCalloutText' => 'Texto do destaque',
                  'adminPanelTitle' => 'Título do painel', 'adminPanelIntro' => 'Introdução do painel', 'adminPanelNotice' => 'Aviso interno',
                  'adminPanelPrimaryColor' => 'Cor principal', 'adminPanelAccentColor' => 'Cor de destaque',
                  'footerDescription' => 'Descrição do rodapé', 'footerHostDescription' => 'Descrição para organizações', 'footerCity' => 'Texto de localização',
              ];
              echo '<div class="form-grid">';
              foreach ($fields as $key => $label) {
                  $multi = in_array($key, ['homeDescription', 'manualIntro', 'manualCard1Text', 'manualCard2Text', 'manualCard3Text', 'manualCard4Text', 'manualCalloutText', 'adminPanelIntro', 'adminPanelNotice', 'footerDescription', 'footerHostDescription'], true);
                  $color = in_array($key, ['adminPanelPrimaryColor', 'adminPanelAccentColor'], true);
                  $value = (string) ($form[$key] ?? '');
                  if ($color) {
                      $hex = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : ($key === 'adminPanelAccentColor' ? '#b7cf73' : '#173d32');
                      echo '<label class="form-field"><span>' . e($label) . '</span>';
                      echo '<div class="color-input"><input type="color" name="' . e($key) . '" value="' . e($hex) . '" /><code>' . e($hex) . '</code><span class="color-swatch" style="background:' . e($hex) . '"></span></div>';
                      echo '</label>';
                  } else {
                      echo '<label class="form-field full"><span>' . e($label) . '</span>';
                      if ($multi) {
                          echo '<textarea name="' . e($key) . '">' . e($value) . '</textarea>';
                      } else {
                          echo '<input name="' . e($key) . '" value="' . e($value) . '" />';
                      }
                      echo '</label>';
                  }
              }
              echo '</div>';
              ?>
              <button class="button button-primary" type="submit">Salvar e publicar conteúdo</button>
            </form>
          </section>

          <section class="content-editor manual-articles-editor" id="artigos">
            <div class="card-header"><div><span class="section-kicker">Manual de Reciclagem</span><h2>Artigos e referências</h2></div><a class="button button-ghost button-small" href="/area-privada-mapazerolixo#artigos">Novo artigo</a></div>
            <p class="editor-help">Publique para o texto aparecer na página pública <a href="/manual" target="_blank" rel="noreferrer">/manual</a>.</p>
            <div class="articles-admin-layout">
              <aside class="article-admin-list">
                <?php if (!$articles): ?>
                  <div class="empty-dashboard"><strong>Nenhum artigo ainda.</strong><span>Preencha o formulário ao lado e publique.</span></div>
                <?php endif; ?>
                <?php foreach ($articles as $a): ?>
                  <a class="<?= $articleId === (int) $a['id'] ? 'article-admin-item active' : 'article-admin-item' ?>" href="/area-privada-mapazerolixo?artigo=<?= (int) $a['id'] ?>#artigos"><strong><?= e($a['title']) ?></strong><small><?= ($a['status'] === 'published') ? 'publicado no site' : 'rascunho' ?></small></a>
                <?php endforeach; ?>
              </aside>
              <form class="article-admin-form" method="post" enctype="multipart/form-data" id="article-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_article" />
                <input type="hidden" name="id" value="<?= (int) $article['id'] ?>" />
                <div class="form-grid">
                  <label class="form-field full"><span>Título</span><input name="title" required maxlength="180" value="<?= e($article['title']) ?>" /></label>
                  <label class="form-field"><span>Endereço (slug)</span><input name="slug" value="<?= e($article['slug']) ?>" placeholder="gerado a partir do título" autocomplete="off" /></label>
                  <label class="form-field"><span>Status</span><select name="status"><option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option><option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>Publicado</option></select></label>
                  <label class="form-field full"><span>Resumo</span><textarea name="excerpt" placeholder="Opcional: se vazio, usamos o início do conteúdo."><?= e($article['excerpt']) ?></textarea></label>
                  <label class="form-field full"><span>Conteúdo</span><textarea name="body" class="article-body-input js-rich" data-placeholder="Escreva o artigo com títulos, listas e destaques."><?= e($article['body']) ?></textarea></label>
                  <div class="form-field full cover-upload">
                    <span>Imagem do artigo</span>
                    <?php $articleImg = public_media_url($article['imageUrl'] ?? ''); ?>
                    <div class="cover-upload-box">
                      <input class="cover-file-input" type="file" name="cover" id="article-cover-file" accept="image/jpeg,image/png,image/webp" data-upload="/api/upload.php?kind=manual" data-target="articleImage" />
                      <label class="cover-upload-trigger" for="article-cover-file">Escolher imagem</label>
                      <input type="hidden" name="imageUrl" id="articleImage" value="<?= e($articleImg) ?>" />
                      <small class="field-help">JPG, PNG ou WEBP. Até 5 MB. Publique depois do envio para aparecer no /manual com o efeito de capa.</small>
                      <img class="image-upload-preview<?= $articleImg ? '' : ' is-empty' ?>" <?= $articleImg ? 'src="' . e($articleImg) . '"' : '' ?> alt="Prévia" />
                    </div>
                  </div>
                  <label class="form-field full"><span>Link de referência</span><input type="text" name="linkUrl" inputmode="url" placeholder="https://..." value="<?= e($article['linkUrl']) ?>" /></label>
                </div>
                <div class="article-admin-actions">
                  <button class="button button-primary" type="submit" name="intent" value="publish">Publicar no manual</button>
                  <button class="button button-ghost" type="submit" name="intent" value="draft">Salvar rascunho</button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}
