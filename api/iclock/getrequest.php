<?php

require_once __DIR__ . '/../bootstrap.php';

$serial = trim((string) ($_GET['SN'] ?? $_GET['sn'] ?? ''));
if ($serial !== '') {
    $dispositivo = ponto_dispositivo_por_serie($conn, $serial, true);
    if ($dispositivo) {
        ponto_atualizar_contacto_dispositivo($conn, (int) $dispositivo['id']);
    }
}

ponto_api_respond_text('OK');
