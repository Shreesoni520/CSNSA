<?php

function ponto_ensure_device_schema($conn): void
{
    if (!fe_table_exists($conn, 'dispositivos')) {
        return;
    }

    $columns = [];
    $result = mysqli_query($conn, 'SHOW COLUMNS FROM dispositivos');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = true;
        }
    }

    $alters = [];
    if (!isset($columns['api_token'])) {
        $alters[] = 'ADD COLUMN api_token VARCHAR(64) DEFAULT NULL';
    }
    if (!isset($columns['ultimo_contacto_at'])) {
        $alters[] = 'ADD COLUMN ultimo_contacto_at DATETIME DEFAULT NULL';
    }
    if (!isset($columns['ip_ultimo'])) {
        $alters[] = 'ADD COLUMN ip_ultimo VARCHAR(45) DEFAULT NULL';
    }

    if ($alters !== []) {
        mysqli_query($conn, 'ALTER TABLE dispositivos ' . implode(', ', $alters));
    }

    if (!fe_table_exists($conn, 'registos_ponto')) {
        return;
    }

    $rpColumns = [];
    $result = mysqli_query($conn, 'SHOW COLUMNS FROM registos_ponto');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rpColumns[$row['Field']] = true;
        }
    }

    if (!isset($rpColumns['evento_uid'])) {
        mysqli_query($conn, 'ALTER TABLE registos_ponto ADD COLUMN evento_uid VARCHAR(100) DEFAULT NULL');
        mysqli_query($conn, 'ALTER TABLE registos_ponto ADD UNIQUE KEY uk_registos_evento_uid (evento_uid)');
    }
}

function ponto_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}

function ponto_gerar_token(): string
{
    return bin2hex(random_bytes(16));
}

function ponto_dispositivo_por_serie($conn, string $numeroSerie, bool $autoRegistar = true): ?array
{
    $numeroSerie = trim($numeroSerie);
    if ($numeroSerie === '' || !fe_table_exists($conn, 'dispositivos')) {
        return null;
    }

    ponto_ensure_device_schema($conn);

    $stmt = mysqli_prepare($conn, 'SELECT * FROM dispositivos WHERE numero_serie = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $numeroSerie);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        return $row;
    }

    if (!$autoRegistar) {
        return null;
    }

    global $ponto_auto_registar_dispositivos;
    if (isset($ponto_auto_registar_dispositivos) && !$ponto_auto_registar_dispositivos) {
        return null;
    }

    $nome = 'Relógio ' . $numeroSerie;
    $tipo = 'biometrico';
    $token = ponto_gerar_token();
    $stmt = mysqli_prepare($conn, 'INSERT INTO dispositivos (nome, tipo, numero_serie, api_token, ativo) VALUES (?, ?, ?, ?, 1)');
    mysqli_stmt_bind_param($stmt, 'ssss', $nome, $tipo, $numeroSerie, $token);
    mysqli_stmt_execute($stmt);
    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return [
        'id' => $id,
        'nome' => $nome,
        'tipo' => $tipo,
        'numero_serie' => $numeroSerie,
        'api_token' => $token,
        'ativo' => 1,
    ];
}

function ponto_dispositivo_por_id($conn, int $id): ?array
{
    if ($id <= 0 || !fe_table_exists($conn, 'dispositivos')) {
        return null;
    }

    ponto_ensure_device_schema($conn);

    $stmt = mysqli_prepare($conn, 'SELECT * FROM dispositivos WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

function ponto_atualizar_contacto_dispositivo($conn, int $dispositivoId, ?string $ip = null): void
{
    if ($dispositivoId <= 0) {
        return;
    }

    ponto_ensure_device_schema($conn);
    $ip = $ip ?? ponto_client_ip();

    $stmt = mysqli_prepare($conn, 'UPDATE dispositivos SET ultimo_contacto_at = NOW(), ip_ultimo = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $ip, $dispositivoId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function ponto_validar_token_dispositivo(array $dispositivo, ?string $token): bool
{
    global $ponto_api_secret;

    if (!empty($ponto_api_secret) && hash_equals((string) $ponto_api_secret, (string) $token)) {
        return true;
    }

    $deviceToken = (string) ($dispositivo['api_token'] ?? '');
    if ($deviceToken !== '' && $token !== null && hash_equals($deviceToken, $token)) {
        return true;
    }

    // ZKTeco devices often connect without a token on first setup.
    return $token === null || $token === '';
}

function ponto_resolve_funcionario($conn, string $identificador): ?array
{
    $identificador = trim($identificador);
    if ($identificador === '' || !fe_table_exists($conn, 'funcionarios')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "SELECT id, nome, estado, codigo_biometrico, pin_ponto, numero_mecanografico
        FROM funcionarios
        WHERE estado = 'ativo'
          AND (
            codigo_biometrico = ?
            OR pin_ponto = ?
            OR numero_mecanografico = ?
          )
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'sss', $identificador, $identificador, $identificador);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

function ponto_map_inout_mode($mode): ?string
{
    $map = [
        0 => 'entrada',
        1 => 'saida',
        2 => 'inicio_pausa',
        3 => 'fim_pausa',
        '0' => 'entrada',
        '1' => 'saida',
        '2' => 'inicio_pausa',
        '3' => 'fim_pausa',
    ];

    return $map[$mode] ?? null;
}

function ponto_inferir_tipo($conn, int $funcionarioId, string $dataHoraSql, ?string $tipoExplicito = null): string
{
    if ($tipoExplicito !== null && in_array($tipoExplicito, ['entrada', 'saida', 'inicio_pausa', 'fim_pausa'], true)) {
        return $tipoExplicito;
    }

    $stmt = mysqli_prepare($conn, "SELECT tipo
        FROM registos_ponto
        WHERE funcionario_id = ?
          AND estado = 'valido'
          AND DATE(data_hora) = DATE(?)
          AND data_hora < ?
        ORDER BY data_hora DESC, id DESC
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iss', $funcionarioId, $dataHoraSql, $dataHoraSql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $ultimo = $row['tipo'] ?? null;

    if ($ultimo === null) {
        return 'entrada';
    }

    if (in_array($ultimo, ['entrada', 'fim_pausa'], true)) {
        return 'saida';
    }

    if ($ultimo === 'inicio_pausa') {
        return 'fim_pausa';
    }

    return 'entrada';
}

function ponto_evento_uid(string $prefix, array $parts): string
{
    return $prefix . ':' . hash('sha256', implode('|', $parts));
}

function ponto_registar_movimento($conn, array $input): array
{
    ponto_ensure_device_schema($conn);

    $identificador = trim((string) ($input['identificador'] ?? ''));
    $dataHoraSql = trim((string) ($input['data_hora'] ?? ''));
    $dispositivoId = isset($input['dispositivo_id']) ? (int) $input['dispositivo_id'] : null;
    $origem = trim((string) ($input['origem'] ?? 'dispositivo'));
    $tipoExplicito = isset($input['tipo']) ? trim((string) $input['tipo']) : null;
    $eventoUid = isset($input['evento_uid']) ? trim((string) $input['evento_uid']) : null;
    $pinUtilizado = !empty($input['pin_utilizado']) ? 1 : 0;
    $observacoes = isset($input['observacoes']) ? nullable_text($input['observacoes']) : null;

    if ($identificador === '' || $dataHoraSql === '') {
        return ['ok' => false, 'error' => 'identificador_ou_data_invalidos'];
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dataHoraSql);
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d H:i', $dataHoraSql);
    }
    if (!$dt) {
        return ['ok' => false, 'error' => 'data_hora_invalida'];
    }
    $dataHoraSql = $dt->format('Y-m-d H:i:s');
    $dataReferencia = $dt->format('Y-m-d');

    $funcionario = ponto_resolve_funcionario($conn, $identificador);
    if (!$funcionario) {
        return ['ok' => false, 'error' => 'funcionario_nao_encontrado', 'identificador' => $identificador];
    }

    $tipo = ponto_inferir_tipo($conn, (int) $funcionario['id'], $dataHoraSql, $tipoExplicito);

    if ($eventoUid === null) {
        $eventoUid = ponto_evento_uid('auto', [
            (string) $dispositivoId,
            (string) $funcionario['id'],
            $dataHoraSql,
            $tipo,
            $identificador,
        ]);
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM registos_ponto WHERE evento_uid = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $eventoUid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($existing) {
        return [
            'ok' => true,
            'duplicate' => true,
            'registo_id' => (int) $existing['id'],
            'funcionario_id' => (int) $funcionario['id'],
            'tipo' => $tipo,
        ];
    }

    $funcionarioId = (int) $funcionario['id'];
    $dispositivoIdParam = $dispositivoId > 0 ? $dispositivoId : null;

    $temDataReferencia = fe_column_exists($conn, 'registos_ponto', 'data_referencia');
    $temDispositivo = fe_column_exists($conn, 'registos_ponto', 'dispositivo_id');
    $temPin = fe_column_exists($conn, 'registos_ponto', 'pin_utilizado');
    $temEventoUid = fe_column_exists($conn, 'registos_ponto', 'evento_uid');

    if ($temDataReferencia && $temDispositivo && $temPin && $temEventoUid) {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, data_referencia, origem, estado, observacoes, dispositivo_id, pin_utilizado, evento_uid)
            VALUES (?, ?, ?, ?, ?, 'valido', ?, ?, ?, ?)");
        mysqli_stmt_bind_param(
            $stmt,
            'isssssiis',
            $funcionarioId,
            $tipo,
            $dataHoraSql,
            $dataReferencia,
            $origem,
            $observacoes,
            $dispositivoIdParam,
            $pinUtilizado,
            $eventoUid
        );
    } elseif ($temDataReferencia) {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, data_referencia, origem, estado, observacoes)
            VALUES (?, ?, ?, ?, ?, 'valido', ?)");
        mysqli_stmt_bind_param($stmt, 'isssss', $funcionarioId, $tipo, $dataHoraSql, $dataReferencia, $origem, $observacoes);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, origem, estado, observacoes)
            VALUES (?, ?, ?, ?, 'valido', ?)");
        mysqli_stmt_bind_param($stmt, 'issss', $funcionarioId, $tipo, $dataHoraSql, $origem, $observacoes);
    }

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
        return ['ok' => false, 'error' => 'falha_insercao'];
    }

    $registoId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if ($dispositivoId > 0) {
        ponto_atualizar_contacto_dispositivo($conn, $dispositivoId);
    }

    return [
        'ok' => true,
        'duplicate' => false,
        'registo_id' => $registoId,
        'funcionario_id' => $funcionarioId,
        'funcionario_nome' => $funcionario['nome'],
        'tipo' => $tipo,
        'data_hora' => $dataHoraSql,
    ];
}

function ponto_parse_datetime_flex(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y/m/d H:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function ponto_api_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = '';

    if (strpos($script, '/admin/') !== false) {
        $basePath = substr($script, 0, strpos($script, '/admin/'));
    } elseif (strpos($script, '/api/') !== false) {
        $basePath = substr($script, 0, strpos($script, '/api/'));
    } else {
        $basePath = rtrim(dirname($script), '/\\');
    }

    $basePath = str_replace('\\', '/', $basePath);
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function ponto_iclock_urls(): array
{
    $base = ponto_api_base_url() . '/api/iclock';

    return [
        'cdata' => $base . '/cdata.php',
        'getrequest' => $base . '/getrequest.php',
        'punch_json' => ponto_api_base_url() . '/api/punch.php',
    ];
}
