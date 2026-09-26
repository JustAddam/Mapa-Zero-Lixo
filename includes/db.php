<?php
/**
 * Título: db.php — conexão MySQL e consultas de organizações
 * Autoria: ADDAM S. C
 *
 * PDO único, pontos aprovados, avaliações, artigos do manual e dados de demonstração.
 */

/** Conexão reutilizável com o banco (uma por requisição). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $cfg = $GLOBALS['APP_CONFIG']['db'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/** Registra no histórico o que mudou em uma organização. */
function record_org_change(PDO $pdo, int $orgId, ?int $actorId, string $type, string $summary, $before, $after): void
{
    $stmt = $pdo->prepare('INSERT INTO organization_change_log (organizationId, actorUserId, changeType, summary, beforeData, afterData) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $orgId,
        $actorId,
        $type,
        $summary,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
    ]);
}

/** Pontos de coleta já aprovados para o mapa público. */
function list_approved_orgs(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM organizations WHERE status = 'approved' ORDER BY createdAt DESC")->fetchAll();
}

/** Um ponto aprovado pelo id, ou null. */
function get_approved_org(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM organizations WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Iniciativas cadastradas pelo dono da conta. */
function list_own_orgs(PDO $pdo, int $ownerId): array
{
    $stmt = $pdo->prepare('SELECT * FROM organizations WHERE ownerId = ? ORDER BY createdAt DESC');
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

/** Organização do dono (para edição no workspace). */
function get_org_for_owner(PDO $pdo, int $id, int $ownerId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM organizations WHERE id = ? AND ownerId = ? LIMIT 1');
    $stmt->execute([$id, $ownerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Avaliações e média de um ponto. */
function list_org_reviews(PDO $pdo, int $orgId): array
{
    $stmt = $pdo->prepare('SELECT r.*, u.name AS authorName FROM reviews r LEFT JOIN users u ON u.id = r.userId WHERE r.organizationId = ? ORDER BY r.createdAt DESC');
    $stmt->execute([$orgId]);
    $reviews = $stmt->fetchAll();
    $agg = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS average, COUNT(*) AS count FROM reviews WHERE organizationId = ?');
    $agg->execute([$orgId]);
    $stats = $agg->fetch();
    return [
        'reviews' => $reviews,
        'average' => (float) ($stats['average'] ?? 0),
        'count' => (int) ($stats['count'] ?? 0),
    ];
}

/** Cria ou atualiza a avaliação da pessoa neste ponto. */
function upsert_review(PDO $pdo, int $orgId, int $userId, int $rating, string $comment): void
{
    $stmt = $pdo->prepare('INSERT INTO reviews (organizationId, userId, rating, comment) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updatedAt = CURRENT_TIMESTAMP');
    $stmt->execute([$orgId, $userId, $rating, $comment]);
}

/** Artigos publicados do Manual de Reciclagem. */
function published_articles(PDO $pdo): array
{
    $rows = $pdo->query("SELECT * FROM manual_articles WHERE status = 'published' ORDER BY updatedAt DESC")->fetchAll();
    foreach ($rows as &$row) {
        $row['body'] = sanitize_rich_text($row['body']);
    }
    return $rows;
}

/** Todos os artigos (rascunho e publicados) para o admin. */
function all_articles(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM manual_articles ORDER BY updatedAt DESC')->fetchAll();
    foreach ($rows as &$row) {
        $row['body'] = sanitize_rich_text($row['body']);
    }
    return $rows;
}

/** Pontos fictícios exibidos no mapa quando ainda não há cadastros reais. */
function demo_organizations(): array
{
    return [
        ['id' => -1, 'name' => 'Coletivo Maré Limpa', 'category' => 'Coleta seletiva', 'description' => 'Ponto comunitário para papel, plástico, vidro e metal.', 'address' => 'Centro, Macapá — AP', 'phone' => '(96) 3223-1040', 'latitude' => -0.0354, 'longitude' => -51.0664, 'status' => 'approved'],
        ['id' => -2, 'name' => 'Instituto Amapá Verde', 'category' => 'Educação ambiental', 'description' => 'Oficinas, compostagem e formação para escolas e grupos locais.', 'address' => 'Buritizal, Macapá — AP', 'phone' => '(96) 99118-2042', 'latitude' => -0.0505, 'longitude' => -51.0723, 'status' => 'approved'],
        ['id' => -3, 'name' => 'Recicla Norte', 'category' => 'Resíduos eletrônicos', 'description' => 'Recebimento responsável de pilhas, cabos e pequenos eletrônicos.', 'address' => 'Jesus de Nazaré, Macapá — AP', 'phone' => '(96) 98842-8801', 'latitude' => -0.0272, 'longitude' => -51.0638, 'status' => 'approved'],
        ['id' => -4, 'name' => 'Horta do Laguinho', 'category' => 'Compostagem', 'description' => 'Entrega de resíduos orgânicos e retirada de composto para hortas.', 'address' => 'Laguinho, Macapá — AP', 'phone' => '(96) 98112-7044', 'latitude' => -0.0206, 'longitude' => -51.0589, 'status' => 'approved'],
    ];
}

const CATEGORIES = ['Coleta seletiva', 'Educação ambiental', 'Resíduos eletrônicos', 'Compostagem'];
