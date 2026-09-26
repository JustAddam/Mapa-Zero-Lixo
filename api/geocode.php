<?php
/**
 * Título: geocode.php — converte endereço em latitude/longitude
 * Autoria: ADDAM S. C
 *
 * Legenda: consulta o Nominatim (OpenStreetMap) restringindo a busca a Macapá.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

// Só quem está logado pode posicionar o pino no cadastro.
if (!current_user()) {
    json_out(['error' => 'Login necessário.'], 401);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 3) {
    json_out(['error' => 'Endereço muito curto.'], 400);
}

// Acrescenta a cidade para reduzir resultados fora do Amapá.
$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($q . ', Macapá, Amapá, Brasil');
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: mapazerolixo/1.0 (contato@local)\r\nAccept: application/json\r\n",
        'timeout' => 8,
    ],
]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    json_out(['error' => 'Não foi possível geocodificar agora.'], 502);
}
$data = json_decode($raw, true);
if (!is_array($data) || !$data) {
    json_out(['error' => 'Endereço não encontrado.'], 404);
}
json_out(['lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon'], 'label' => $data[0]['display_name'] ?? $q]);
