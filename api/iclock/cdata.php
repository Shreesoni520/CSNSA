<?php

require_once __DIR__ . '/../bootstrap.php';

$serial = trim((string) ($_GET['SN'] ?? $_GET['sn'] ?? ''));
$table = strtoupper(trim((string) ($_GET['table'] ?? '')));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_PONTO_TOKEN'] ?? ''));

if ($serial === '') {
    ponto_api_respond_text('ERROR: missing SN', 400);
}

$dispositivo = ponto_dispositivo_por_serie($conn, $serial, true);
if (!$dispositivo) {
    ponto_api_respond_text('ERROR: device not allowed', 403);
}

if ((int) ($dispositivo['ativo'] ?? 0) !== 1) {
    ponto_api_respond_text('ERROR: device disabled', 403);
}

if (!ponto_validar_token_dispositivo($dispositivo, $token !== '' ? $token : null)) {
    ponto_api_respond_text('ERROR: invalid token', 403);
}

ponto_atualizar_contacto_dispositivo($conn, (int) $dispositivo['id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stamp = time();
    $lines = [
        'GET OPTION FROM: ' . $serial,
        'ATTLOGStamp=' . $stamp,
        'OPERLOGStamp=' . $stamp,
        'ERRORLOGStamp=' . $stamp,
        'Delay=10',
        'TransInterval=1',
        'Realtime=1',
        'Encrypt=0',
        'TimeZone=0',
    ];
    ponto_api_respond_text(implode("\n", $lines) . "\nOK");
}

if ($table !== 'ATTLOG') {
    ponto_api_respond_text('OK');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || trim($rawBody) === '') {
    $rawBody = trim((string) ($_POST['content'] ?? ''));
}

if ($rawBody === '') {
    ponto_api_respond_text('OK');
}

$lines = preg_split('/\R+/', $rawBody) ?: [];
$imported = 0;
$duplicates = 0;
$errors = 0;

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || stripos($line, 'PIN') === 0) {
        continue;
    }

    $parts = preg_split('/\t+/', $line);
    if (!$parts || count($parts) < 2) {
        $errors++;
        continue;
    }

    $pin = trim((string) $parts[0]);
    $dateTimeRaw = trim((string) $parts[1]);
    $inOutMode = $parts[4] ?? ($parts[3] ?? null);

    $dataHoraSql = ponto_parse_datetime_flex($dateTimeRaw);
    if ($pin === '' || $dataHoraSql === null) {
        $errors++;
        continue;
    }

    $tipo = ponto_map_inout_mode($inOutMode);
    $eventoUid = ponto_evento_uid('zk', [$serial, $pin, $dataHoraSql, (string) $inOutMode, $line]);

    $result = ponto_registar_movimento($conn, [
        'identificador' => $pin,
        'data_hora' => $dataHoraSql,
        'tipo' => $tipo,
        'dispositivo_id' => (int) $dispositivo['id'],
        'origem' => 'dispositivo',
        'evento_uid' => $eventoUid,
        'pin_utilizado' => true,
    ]);

    if (!empty($result['ok']) && !empty($result['duplicate'])) {
        $duplicates++;
    } elseif (!empty($result['ok'])) {
        $imported++;
    } else {
        $errors++;
    }
}

ponto_api_respond_text('OK:' . $imported);
