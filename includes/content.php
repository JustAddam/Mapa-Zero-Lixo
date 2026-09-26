<?php
/**
 * Título: content.php — textos editáveis do site
 * Autoria: ADDAM S. C
 *
 * Valores padrão da home, manual, rodapé e painel; leitura e gravação em site_content.
 */

const CONTENT_DEFAULTS = [
    'homeHeroTitle' => 'Encontre quem cuida',
    'homeHeroEmphasis' => 'do nosso lugar.',
    'homeDescription' => 'Um mapa vivo de iniciativas, empresas e grupos que fazem a coleta, a educação ambiental e o descarte correto acontecerem em Macapá.',
    'homeCta' => 'Quero hospedar minha iniciativa',
    'homeTrust' => 'Cadastros analisados pela equipe',
    'manualIntro' => 'Cuidar do ambiente não precisa ser complicado. Este manual reúne atitudes práticas para reduzir resíduos, separar materiais e fortalecer uma cultura de cuidado em Macapá.',
    'manualCard1Title' => 'Separe na origem',
    'manualCard1Text' => 'Tenha dois recipientes em casa: um para recicláveis secos e outro para orgânicos e rejeitos. Lave embalagens antes de encaminhar.',
    'manualCard2Title' => 'Composte o que volta à terra',
    'manualCard2Text' => 'Cascas, borra de café e folhas podem virar adubo. A compostagem reduz o volume de lixo e devolve nutrientes ao solo.',
    'manualCard3Title' => 'Descarte com responsabilidade',
    'manualCard3Text' => 'Pilhas, lâmpadas, eletrônicos e óleo de cozinha precisam de pontos específicos. Nunca despeje óleo no ralo.',
    'manualCard4Title' => 'Consuma com intenção',
    'manualCard4Text' => 'Antes de comprar, pergunte se precisa. Prefira itens duráveis, reparáveis, retornáveis e com menos embalagem.',
    'manualCalloutTitle' => 'O resíduo certo, no lugar certo.',
    'manualCalloutText' => 'Quando cada material encontra o destino adequado, toda a cidade ganha: o trabalho de quem coleta é valorizado, os recursos permanecem em circulação e os rios respiram melhor.',
    'footerDescription' => 'Conectando cuidado, território e ação ambiental em Macapá.',
    'footerHostDescription' => 'Uma presença digital simples para quem transforma o lugar onde vive.',
    'footerCity' => 'Feito para Macapá',
    'adminPanelTitle' => 'Central de curadoria.',
    'adminPanelIntro' => 'Analise cadastros, revise alterações e edite o conteúdo da mapazerolixo.',
    'adminPanelNotice' => 'As decisões administrativas ficam registradas no histórico.',
    'adminPanelPrimaryColor' => '#173d32',
    'adminPanelAccentColor' => '#b7cf73',
];

/** Junta o conteúdo salvo no banco com os textos padrão. */
function merge_content(?array $values = null): array
{
    return array_merge(CONTENT_DEFAULTS, $values ?? []);
}

/** Carrega todos os textos do site a partir da tabela site_content. */
function get_site_content(PDO $pdo): array
{
    $rows = $pdo->query('SELECT contentKey, contentValue FROM site_content')->fetchAll();
    $values = [];
    foreach ($rows as $row) {
        $values[$row['contentKey']] = $row['contentValue'];
    }
    return merge_content($values);
}

/** Salva no banco os campos de conteúdo enviados pelo admin. */
function save_site_content(PDO $pdo, array $values, int $userId): void
{
    $stmt = $pdo->prepare('INSERT INTO site_content (contentKey, contentValue, updatedBy) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE contentValue = VALUES(contentValue), updatedBy = VALUES(updatedBy), updatedAt = CURRENT_TIMESTAMP');
    foreach ($values as $key => $value) {
        if (!array_key_exists($key, CONTENT_DEFAULTS) || !is_string($value)) {
            continue;
        }
        $stmt->execute([$key, sanitize_rich_text($value), $userId]);
    }
}
