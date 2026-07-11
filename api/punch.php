<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ponto_api_respond_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$serial = trim((string) ($payload['device_serial'] ?? $payload['sn'] ?? ''));
$token = trim((string) ($payload['api_token'] ?? $payload['token'] ?? $_SERVER['HTTP_X_PONTO_TOKEN'] ?? ''));
$identificador = trim((string) ($payload['user_id'] ?? $payload['identificador'] ?? $payload['pin'] ?? ''));
$dataHora = trim((string) ($payload['datetime'] ?? $payload['data_hora'] ?? ''));
$tipo = isset($payload['tipo']) ? trim((string) $payload['tipo']) : null;

if ($serial === '' || $identificador === '') {
    ponto_api_respond_json(['ok' => false, 'error' => 'missing_device_or_user'], 400);
}

$dispositivo = ponto_dispositivo_por_serie($conn, $serial, true);
if (!$dispositivo) {
    ponto_api_respond_json(['ok' => false, 'error' => 'device_not_found'], 403);
}

if ((int) ($dispositivo['ativo'] ?? 0) !== 1) {
    ponto_api_respond_json(['ok' => false, 'error' => 'device_disabled'], 403);
}

if (!ponto_validar_token_dispositivo($dispositivo, $token !== '' ? $token : null)) {
    ponto_api_respond_json(['ok' => false, 'error' => 'invalid_token'], 403);
}

if ($dataHora === '') {
    $dataHora = date('Y-m-d H:i:s');
} else {
    $parsed = ponto_parse_datetime_flex($dataHora);
    if ($parsed === null) {
        ponto_api_respond_json(['ok' => false, 'error' => 'invalid_datetime'], 400);
    }
    $dataHora = $parsed;
}

$eventoUid = ponto_evento_uid('json', [$serial, $identificador, $dataHora, (string) $tipo]);

$result = ponto_registar_movimento($conn, [
    'identificador' => $identificador,
    'data_hora' => $dataHora,
    'tipo' => $tipo,
    'dispositivo_id' => (int) $dispositivo['id'],
    'origem' => 'dispositivo',
    'evento_uid' => $eventoUid,
    'pin_utilizado' => !empty($payload['pin_utilizado']),
]);

if (empty($result['ok'])) {
    ponto_api_respond_json([
        'ok' => false,
        'error' => $result['error'] ?? 'failed',
        'identificador' => $identificador,
    ], 422);
}

ponto_api_respond_json([
    'ok' => true,
    'duplicate' => !empty($result['duplicate']),
    'registo_id' => $result['registo_id'] ?? null,
    'funcionario_id' => $result['funcionario_id'] ?? null,
    'funcionario_nome' => $result['funcionario_nome'] ?? null,
    'tipo' => $result['tipo'] ?? null,
    'data_hora' => $result['data_hora'] ?? $dataHora,
]);
