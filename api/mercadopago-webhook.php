<?php
/**
 * Título: mercadopago-webhook.php — aviso de pagamento (assinatura)
 * Autoria: ADDAM S. C
 *
 * Legenda: recebe o status da assinatura e atualiza paymentStatus da organização.
 * Se o token estiver vazio, ignora o evento (hospedagem gratuita nesta fase).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$token = (string) ($GLOBALS['APP_CONFIG']['mercadopago']['access_token'] ?? '');
if ($token === '') {
    json_out(['received' => true, 'skipped' => true]);
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$resourceId = (string) ($payload['data']['id'] ?? $payload['id'] ?? '');
if ($resourceId === '') {
    json_out(['received' => true]);
}

// Confere o status real na API do Mercado Pago (não confia só no POST).
$ch = curl_init('https://api.mercadopago.com/preapproval/' . rawurlencode($resourceId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
curl_close($ch);
$sub = json_decode((string) $raw, true) ?: [];
$statusRaw = (string) ($sub['status'] ?? 'pending');
$map = ['authorized' => 'authorized', 'paused' => 'paused', 'cancelled' => 'cancelled'];
$status = $map[$statusRaw] ?? 'pending';

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM organizations WHERE mercadoPagoPreapprovalId = ? LIMIT 1');
$stmt->execute([$resourceId]);
$row = $stmt->fetch();
if ($row) {
    $pdo->prepare('UPDATE organizations SET paymentStatus = ? WHERE id = ?')->execute([$status, (int) $row['id']]);
}
json_out(['received' => true]);
