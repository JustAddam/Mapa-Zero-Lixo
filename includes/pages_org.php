<?php
/**
 * Título: pages_org.php — cadastro, perfil público e workspace
 * Autoria: ADDAM S. C
 *
 * Solicitação de ponto no mapa, página da iniciativa e edição pelo responsável.
 */

/** Formulário para hospedar uma iniciativa (aguarda aprovação). */
function page_cadastro(PDO $pdo, array $content): void
{
    $user = current_user();
    // Envia o cadastro da iniciativa para análise da curadoria.
    if (is_post() && $user) {
        verify_csrf();
        $name = post('name');
        $category = post('category');
        $description = post('description');
        $address = post('address');
        $phone = post('phone');
        $email = post('email');
        $website = post('website');
        $lat = (float) str_replace(',', '.', post('latitude', '-0.0354'));
        $lng = (float) str_replace(',', '.', post('longitude', '-51.0664'));
        if (mb_strlen($name) < 2 || mb_strlen($description) < 10 || mb_strlen($address) < 4 || !in_array($category, CATEGORIES, true)) {
            flash('error', 'Preencha os campos obrigatórios.');
            redirect('/cadastro');
        }
        $stmt = $pdo->prepare('INSERT INTO organizations (ownerId, name, category, description, address, phone, email, website, latitude, longitude, status, isFreeOverride, paymentStatus) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([(int) $user['id'], $name, $category, $description, $address, $phone ?: null, $email ?: null, $website ?: null, $lat, $lng, 'pending', 1, 'not_required']);
        $id = (int) $pdo->lastInsertId();
        record_org_change($pdo, $id, (int) $user['id'], 'registration', 'Cadastro enviado para análise.', null, compact('name', 'category', 'description', 'address'));
        flash('success', 'Cadastro enviado para análise.');
        redirect('/');
    }
    render_header('cadastro', $content);
    if (!$user) {
        login_prompt('Cadastre sua iniciativa', 'Entre na sua conta para vincular a solicitação ao responsável e acompanhar a aprovação.', '/cadastro');
        render_footer($content);
        return;
    }
    ?>
    <main>
      <section class="content-page">
        <div class="container registration-layout">
          <div class="registration-copy">
            <a class="back-link" href="/">← Voltar ao mapa</a>
            <div class="eyebrow"><span class="eyebrow-line"></span> Mais opções</div>
            <h1>Seu trabalho merece<br /><em>ser encontrado.</em></h1>
            <p class="intro-copy">A mapazerolixo oferece uma página essencial para sua empresa, instituição ou grupo ambientalista divulgar sua atuação, contatos e localização.</p>
            <div class="benefit-list">
              <span>✔ Página própria dentro da rede local</span>
              <span>✔ Localização no mapa de coleta</span>
              <span>✔ Edição de textos, cores e imagem</span>
              <span>✔ Aprovação e curadoria humana</span>
            </div>
          </div>
          <form class="registration-form" method="post">
            <?= csrf_field() ?>
            <div class="form-heading"><span>01 / solicitação de hospedagem</span><h2>Conte sobre sua iniciativa</h2><p>Preencha os dados básicos. A equipe analisa o pedido.</p></div>
            <div class="form-grid">
              <label class="form-field"><span>Nome da iniciativa</span><input name="name" required minlength="2" /></label>
              <label class="form-field"><span>Categoria</span>
                <select name="category" required>
                  <?php foreach (CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
                </select>
              </label>
              <div class="address-picker">
                <label class="form-field full"><span>Endereço</span><input name="address" id="address-input" required minlength="4" /></label>
                <div class="address-search"><button class="button button-ghost button-small" type="button" id="geocode-btn">Localizar no mapa</button></div>
                <div id="address-map" class="address-map address-map-frame"></div>
                <input type="hidden" name="latitude" id="lat" value="-0.0354" />
                <input type="hidden" name="longitude" id="lng" value="-51.0664" />
              </div>
              <label class="form-field"><span>Telefone ou WhatsApp</span><input name="phone" /></label>
              <label class="form-field"><span>E-mail de contato</span><input type="email" name="email" /></label>
              <label class="form-field"><span>Site ou rede social</span><input name="website" /></label>
              <label class="form-field full"><span>O que vocês fazem?</span><textarea name="description" required minlength="10" placeholder="Descreva a coleta, serviço ou ação ambiental..."></textarea></label>
            </div>
            <button class="button button-primary full-button" type="submit">Enviar para análise →</button>
            <small class="form-legal">Ao enviar, você concorda que os dados possam ser usados para contato sobre a hospedagem.</small>
          </form>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}

/** Perfil público de um ponto aprovado, com avaliações. */
function page_perfil(PDO $pdo, array $content, int $id): void
{
    if (is_post() && post('action') === 'review') {
        handle_review_post($pdo);
    }
    $org = get_approved_org($pdo, $id);
    render_header('mapa', $content);
    if (!$org) {
        echo '<main><section class="content-page"><div class="container narrow-content"><a class="back-link" href="/">← Voltar ao mapa</a><h1>Perfil não encontrado.</h1><p class="intro-copy">Esta organização ainda não está aprovada ou o endereço não existe.</p></div></section></main>';
        render_footer($content);
        return;
    }
    $hero = $org['heroTitle'] ?: $org['name'];
    $lede = $org['tagline'] ?: ($org['heroText'] ?: $org['description']);
    $orgCover = public_media_url($org['imageUrl'] ?? '');
    $bg = $orgCover !== '' ? "linear-gradient(90deg, {$org['primaryColor']}ee, {$org['primaryColor']}66), url(" . e($orgCover) . ")" : '';
    ?>
    <main>
      <section class="content-page">
        <div class="container public-profile-page">
          <a class="back-link" href="/">← Voltar ao mapa</a>
          <div class="public-profile-hero" style="background: <?= e($org['primaryColor']) ?>; background-image: <?= $bg ?>; background-size:cover; background-position:center">
            <span class="tag"><?= e($org['category']) ?></span>
            <h1><?= e($hero) ?></h1>
            <div class="rich-text-output"><?= rich($lede) ?></div>
          </div>
          <div class="public-profile-grid">
            <article class="dashboard-card">
              <span class="section-kicker">Organização aprovada</span>
              <h2><?= e($org['name']) ?></h2>
              <div class="rich-text-output"><?= rich($org['aboutText'] ?: $org['description']) ?></div>
              <?php if ($org['servicesText']): ?><div class="profile-rich-section"><h3>Como atua</h3><div class="rich-text-output"><?= rich($org['servicesText']) ?></div></div><?php endif; ?>
              <?php if ($org['contactCta']): ?><div class="profile-cta rich-text-output"><?= rich($org['contactCta']) ?></div><?php endif; ?>
              <div class="profile-contact">
                <span>📍 <?= e($org['address']) ?></span>
                <?php if ($org['phone']): ?><span>☎ <?= e($org['phone']) ?></span><?php endif; ?>
                <?php if ($org['email']): ?><span>✉ <?= e($org['email']) ?></span><?php endif; ?>
                <?php if ($org['website']): ?><a href="<?= e($org['website']) ?>" target="_blank" rel="noreferrer">Visitar site</a><?php endif; ?>
                <?php if ($org['instagramUrl']): ?><a href="<?= e($org['instagramUrl']) ?>" target="_blank" rel="noreferrer">Instagram</a><?php endif; ?>
                <?php if ($org['facebookUrl']): ?><a href="<?= e($org['facebookUrl']) ?>" target="_blank" rel="noreferrer">Facebook</a><?php endif; ?>
              </div>
            </article>
            <article class="dashboard-card">
              <span class="section-kicker">Avaliações da comunidade</span>
              <?php render_reviews(list_org_reviews($pdo, (int) $org['id']), (int) $org['id'], (bool) current_user()); ?>
            </article>
          </div>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}

/** Área do responsável: identidade visual, textos e status do cadastro. */
function page_workspace(PDO $pdo, array $content): void
{
    $user = current_user();
    render_header('workspace', $content);
    if (!$user) {
        login_prompt('Seu espaço de organização', 'Entre na sua conta para acessar a página aprovada e editar seus conteúdos.', '/workspace');
        render_footer($content);
        return;
    }
    $orgs = list_own_orgs($pdo, (int) $user['id']);
    $approved = array_values(array_filter($orgs, fn ($o) => $o['status'] === 'approved'));
    $selectedId = (int) ($_GET['id'] ?? ($approved[0]['id'] ?? 0));
    $organization = null;
    foreach ($orgs as $item) {
        if ((int) $item['id'] === $selectedId) {
            $organization = $item;
        }
    }

    if (is_post() && $organization && $organization['status'] === 'approved') {
        // Guarda rascunho da identidade visual até a curadoria aprovar.
        verify_csrf();
        $imageUrl = public_media_url(post('imageUrl') ?: (string) ($organization['imageUrl'] ?? ''));
        if (!empty($_FILES['cover']['tmp_name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
            $uploaded = save_upload('org-' . (int) $user['id'], 'cover');
            if (!empty($uploaded['url'])) {
                $imageUrl = $uploaded['url'];
            } elseif (!empty($uploaded['error'])) {
                flash('error', $uploaded['error']);
                redirect('/workspace?id=' . (int) $organization['id']);
            }
        }
        $fields = [
            'pendingHeroTitle' => post('heroTitle'),
            'pendingTagline' => sanitize_rich_text(post('tagline')),
            'pendingHeroText' => sanitize_rich_text(post('heroText')),
            'pendingAboutText' => sanitize_rich_text(post('aboutText')),
            'pendingServicesText' => sanitize_rich_text(post('servicesText')),
            'pendingContactCta' => sanitize_rich_text(post('contactCta')),
            'pendingImageUrl' => $imageUrl,
            'pendingPrimaryColor' => post('primaryColor') ?: '#173d32',
            'pendingAccentColor' => post('accentColor') ?: '#b7cf73',
            'pendingInstagramUrl' => post('instagramUrl'),
            'pendingFacebookUrl' => post('facebookUrl'),
            'brandingStatus' => 'pending',
        ];
        $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
        $stmt = $pdo->prepare("UPDATE organizations SET $set WHERE id = ? AND ownerId = ? AND status = 'approved'");
        $stmt->execute([...array_values($fields), (int) $organization['id'], (int) $user['id']]);
        record_org_change($pdo, (int) $organization['id'], (int) $user['id'], 'branding_submitted', 'Personalização enviada para aprovação.', $organization, $fields);
        flash('success', 'Alteração enviada para aprovação.');
        redirect('/workspace?id=' . (int) $organization['id']);
    }

    echo '<main>';
    if (!$orgs) {
        echo '<section class="content-page"><div class="container empty-workspace"><h1>Ainda não há página aprovada.</h1><p>Depois que seu cadastro for analisado, esta área libera os controles de personalização.</p><a class="button button-primary" href="/">Voltar ao mapa</a></div></section>';
        echo '</main>';
        render_footer($content);
        return;
    }
    if (!$approved) {
        echo '<section class="content-page"><div class="container empty-workspace"><span class="tag">Aguardando aprovação</span><h1>Seu cadastro está em análise.</h1><p>A administração recebeu sua iniciativa. Assim que ela for aprovada, o mapa e o editor serão liberados.</p><a class="button button-primary" href="/">Voltar ao mapa</a></div></section>';
        echo '</main>';
        render_footer($content);
        return;
    }
    if ($organization && $organization['status'] !== 'approved') {
        echo '<section class="content-page"><div class="container empty-workspace"><span class="tag">Hospedagem suspensa</span><h1>' . e($organization['name']) . ' está temporariamente suspensa.</h1><a class="button button-primary" href="/workspace">Voltar</a></div></section>';
        echo '</main>';
        render_footer($content);
        return;
    }
    $form = $organization ?: [];
    $coverUrl = public_media_url($form['pendingImageUrl'] ?? $form['imageUrl'] ?? '');
    $primary = $form['pendingPrimaryColor'] ?? ($form['primaryColor'] ?? '#173d32');
    $accent = $form['pendingAccentColor'] ?? ($form['accentColor'] ?? '#b7cf73');
    $heroTitle = $form['pendingHeroTitle'] ?? ($form['heroTitle'] ?? '');
    $tagline = $form['pendingTagline'] ?? ($form['tagline'] ?? '');
    $heroText = $form['pendingHeroText'] ?? ($form['heroText'] ?? '');
    $aboutText = $form['pendingAboutText'] ?? ($form['aboutText'] ?? '');
    $servicesText = $form['pendingServicesText'] ?? ($form['servicesText'] ?? '');
    $contactCta = $form['pendingContactCta'] ?? ($form['contactCta'] ?? '');
    $instagramUrl = $form['pendingInstagramUrl'] ?? ($form['instagramUrl'] ?? '');
    $facebookUrl = $form['pendingFacebookUrl'] ?? ($form['facebookUrl'] ?? '');
    $primary = $primary ?: '#173d32';
    $accent = $accent ?: '#b7cf73';
    ?>
      <section class="content-page">
        <div class="container workspace-page">
          <div class="dashboard-heading">
            <div>
              <a class="back-link" href="/">← Voltar ao mapa</a>
              <div class="eyebrow"><span class="eyebrow-line"></span> Área da organização</div>
              <h1>Faça sua página<br /><em>ter sua cara.</em></h1>
              <p>Edite identidade, apresentação, serviços, contatos e redes. Tudo será revisado antes de aparecer publicamente.</p>
            </div>
          </div>
          <div class="workspace-layout">
            <aside class="workspace-list">
              <span class="section-kicker">Páginas aprovadas</span>
              <?php foreach ($approved as $item): ?>
                <a class="<?= (int) $item['id'] === $selectedId ? 'workspace-item active' : 'workspace-item' ?>" href="/workspace?id=<?= (int) $item['id'] ?>">
                  <span class="workspace-avatar"><?= e(mb_substr($item['name'], 0, 1)) ?></span>
                  <span><strong><?= e($item['name']) ?></strong><small><?= e($item['category']) ?></small></span>
                </a>
              <?php endforeach; ?>
            </aside>
            <?php if ($organization): ?>
              <form class="workspace-editor" method="post" enctype="multipart/form-data" id="workspace-form">
                <?= csrf_field() ?>
                <div class="form-heading"><span>Editor expandido da página</span><h2><?= e($organization['name']) ?></h2><p>Salvar envia um rascunho para curadoria; a versão atual continua pública até aprovação.</p></div>
                <div class="billing-card"><div><span class="section-kicker">Hospedagem gratuita</span><strong>Acesso liberado sem cobrança</strong><small>O mapazerolixo está hospedando organizações gratuitamente nesta fase.</small></div><span class="tag">Gratuito</span></div>
                <div class="form-grid">
                  <label class="form-field full"><span>Título principal</span><input name="heroTitle" value="<?= e($heroTitle) ?>" /></label>
                  <label class="form-field full"><span>Subtítulo curto</span><input name="tagline" value="<?= e($tagline) ?>" /></label>
                  <label class="form-field full"><span>Texto de destaque</span><textarea name="heroText" class="js-rich"><?= e($heroText) ?></textarea></label>
                  <label class="form-field full"><span>Sobre a iniciativa</span><textarea name="aboutText" class="js-rich"><?= e($aboutText) ?></textarea></label>
                  <label class="form-field full"><span>Serviços e ações</span><textarea name="servicesText" class="js-rich"><?= e($servicesText) ?></textarea></label>
                  <label class="form-field full"><span>Chamada de contato</span><textarea name="contactCta" class="js-rich"><?= e($contactCta) ?></textarea></label>
                  <div class="form-field full cover-upload">
                    <span>Imagem de capa</span>
                    <div class="cover-upload-box">
                      <input class="cover-file-input" type="file" name="cover" id="cover-file" accept="image/jpeg,image/png,image/webp" data-upload="/api/upload.php?kind=org" data-target="imageUrl" />
                      <label class="cover-upload-trigger" for="cover-file">Escolher imagem</label>
                      <input type="hidden" name="imageUrl" id="imageUrl" value="<?= e($coverUrl) ?>" />
                      <small class="field-help">JPG, PNG ou WEBP. Até 5 MB. A imagem aparece abaixo assim que o envio terminar.</small>
                      <img class="image-upload-preview<?= $coverUrl ? '' : ' is-empty' ?>" <?= $coverUrl ? 'src="' . e($coverUrl) . '"' : '' ?> alt="Prévia da capa" />
                    </div>
                  </div>
                  <label class="form-field"><span>Cor principal</span><div class="color-input"><input type="color" name="primaryColor" value="<?= e($primary) ?>" /><code><?= e($primary) ?></code><span class="color-swatch" style="background:<?= e($primary) ?>"></span></div></label>
                  <label class="form-field"><span>Cor de destaque</span><div class="color-input"><input type="color" name="accentColor" value="<?= e($accent) ?>" /><code><?= e($accent) ?></code><span class="color-swatch" style="background:<?= e($accent) ?>"></span></div></label>
                  <label class="form-field"><span>Instagram</span><input type="url" name="instagramUrl" value="<?= e($instagramUrl) ?>" /></label>
                  <label class="form-field"><span>Facebook</span><input type="url" name="facebookUrl" value="<?= e($facebookUrl) ?>" /></label>
                </div>
                <button class="button button-primary" type="submit">Enviar personalização para aprovação</button>
              </form>
            <?php else: ?>
              <div class="workspace-placeholder"><p>Escolha uma página aprovada para começar.</p></div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
    <?php
    render_footer($content);
}
