<?php

function escala_mensal_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function escala_mensal_redirect($type, $message, $params = [])
{
    $params = array_merge($params, [
        'type' => $type,
        'message' => $message,
    ]);

    if (!function_exists('admin_url')) { require_once __DIR__ . '/../includes/urls.php'; }
    header('Location: ' . admin_url('escala_mensal', $params));
    exit;
}

function escala_mensal_table_exists($conn, $table)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function escala_mensal_month_name($month)
{
    $months = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Marco',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    return $months[(int) $month] ?? '';
}

function escala_mensal_weekday_short($date)
{
    $weekdays = [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sab',
        7 => 'Dom',
    ];

    return $weekdays[(int) date('N', strtotime($date))] ?? '';
}

function escala_mensal_tipo_label($tipo)
{
    $labels = [
        'turno' => 'Turno',
        'folga' => 'Folga',
        'ferias' => 'Férias',
        'falta' => 'Falta',
        'baixa' => 'Baixa',
        'substituicao' => 'Substituicao',
        'licenca_amamentacao' => 'Lic. amamentação',
    ];

    return $labels[$tipo] ?? $tipo;
}

if (!function_exists('e')) {
    function e($value)
    {
        return escala_mensal_e($value);
    }
}

if (!function_exists('month_name')) {
    function month_name($month)
    {
        return escala_mensal_month_name($month);
    }
}

if (!function_exists('weekday_short')) {
    function weekday_short($date)
    {
        return escala_mensal_weekday_short($date);
    }
}

if (!function_exists('tipo_label')) {
    function tipo_label($tipo)
    {
        return escala_mensal_tipo_label($tipo);
    }
}

function escala_mensal_tipos_dia()
{
    return ['turno', 'folga', 'ferias', 'falta', 'baixa', 'substituicao', 'licenca_amamentacao'];
}

function escala_mensal_contexto_request()
{
    $anoAtual = (int) date('Y');
    $mesAtual = (int) date('n');
    $ano = (int) ($_REQUEST['ano'] ?? $anoAtual);
    $mes = (int) ($_REQUEST['mes'] ?? $mesAtual);
    $equipaId = (int) ($_REQUEST['equipa_id'] ?? 0);
    $funcionarioId = (int) ($_REQUEST['funcionario_id'] ?? 0);

    if ($ano < 2000 || $ano > 2100) {
        $ano = $anoAtual;
    }

    if ($mes < 1 || $mes > 12) {
        $mes = $mesAtual;
    }

    return [
        'ano' => $ano,
        'mes' => $mes,
        'setor_id' => 0,
        'equipa_id' => $equipaId,
        'funcionario_id' => $funcionarioId,
        'dias_no_mes' => cal_days_in_month(CAL_GREGORIAN, $mes, $ano),
    ];
}

function escala_mensal_base_params($contexto)
{
    return [
        'ano' => $contexto['ano'],
        'mes' => $contexto['mes'],
        'equipa_id' => $contexto['equipa_id'],
        'funcionario_id' => $contexto['funcionario_id'],
    ];
}

function escala_mensal_tabelas_em_falta($conn)
{
    $requiredTables = ['funcionarios', 'equipas', 'turnos', 'escala_funcionarios'];
    $missingTables = [];

    foreach ($requiredTables as $table) {
        if (!escala_mensal_table_exists($conn, $table)) {
            $missingTables[] = $table;
        }
    }

    return $missingTables;
}

function escala_mensal_processar_post($conn, $contexto, $missingTables)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $acao = $_POST['acao'] ?? '';
    $baseParams = escala_mensal_base_params($contexto);

    if ($acao === 'importar_excel') {
        escala_mensal_importar_csv($conn, $contexto, $missingTables, $baseParams);
        return;
    }

    if ($acao !== 'guardar') {
        return;
    }

    if (!empty($missingTables)) {
        escala_mensal_redirect('danger', 'Importe o ficheiro csnsa.sql na base de dados antes de usar a escala mensal.', $baseParams);
    }

    $escala = $_POST['escala'] ?? [];

    if (!is_array($escala)) {
        escala_mensal_redirect('danger', 'Dados da escala inválidos.', $baseParams);
    }

    mysqli_begin_transaction($conn);

    try {
        $stmtFuncionario = mysqli_prepare($conn, 'SELECT id, utilizador_id, equipa_id FROM funcionarios WHERE id = ? AND estado = "ativo" LIMIT 1');
        $stmtGuardar = mysqli_prepare($conn, "INSERT INTO escala_funcionarios
            (funcionario_id, utilizador_id, setor_id, equipa_id, ano, mes, data_escala, dia, tipo_dia, turno_id, substitui_funcionario_id, folga_trabalhada, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                utilizador_id = VALUES(utilizador_id),
                setor_id = VALUES(setor_id),
                equipa_id = VALUES(equipa_id),
                ano = VALUES(ano),
                mes = VALUES(mes),
                dia = VALUES(dia),
                tipo_dia = VALUES(tipo_dia),
                turno_id = VALUES(turno_id),
                substitui_funcionario_id = VALUES(substitui_funcionario_id),
                folga_trabalhada = VALUES(folga_trabalhada),
                observacoes = VALUES(observacoes)");

        foreach ($escala as $funcionarioId => $dias) {
            escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, (int) $funcionarioId, $dias);
        }

        mysqli_stmt_close($stmtFuncionario);
        mysqli_stmt_close($stmtGuardar);
        mysqli_commit($conn);

        escala_mensal_redirect('success', 'Escala mensal guardada com sucesso.', $baseParams);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        escala_mensal_redirect('danger', 'Não foi possível guardar a escala mensal.', $baseParams);
    }
}

function escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, $funcionarioId, $dias)
{
    if ($funcionarioId <= 0 || !is_array($dias)) {
        return;
    }

    mysqli_stmt_bind_param($stmtFuncionario, 'i', $funcionarioId);
    mysqli_stmt_execute($stmtFuncionario);
    $resultFuncionario = mysqli_stmt_get_result($stmtFuncionario);
    $funcionario = mysqli_fetch_assoc($resultFuncionario);

    if (!$funcionario) {
        return;
    }

    foreach ($dias as $dia => $dadosDia) {
        escala_mensal_guardar_dia($conn, $stmtGuardar, $contexto, $funcionarioId, $funcionario, (int) $dia, $dadosDia);
    }
}

function escala_mensal_apagar_dia($conn, int $funcionarioId, int $ano, int $mes, int $dia): void
{
    $stmt = mysqli_prepare($conn, 'DELETE FROM escala_funcionarios WHERE funcionario_id = ? AND ano = ? AND mes = ? AND dia = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'iiii', $funcionarioId, $ano, $mes, $dia);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function escala_mensal_dia_deve_guardar(array $dadosDia): bool
{
    $tipo = trim((string) ($dadosDia['tipo_dia'] ?? ''));
    if ($tipo === '' || !in_array($tipo, escala_mensal_tipos_dia(), true)) {
        return false;
    }

    $turnoId = (int) ($dadosDia['turno_id'] ?? 0);
    $folgaTrabalhada = isset($dadosDia['folga_trabalhada']);

    if ($tipo === 'turno' || $tipo === 'substituicao' || $folgaTrabalhada) {
        return $turnoId > 0;
    }

    return true;
}

function escala_mensal_guardar_dia($conn, $stmtGuardar, $contexto, $funcionarioId, $funcionario, $dia, $dadosDia)
{
    if ($dia < 1 || $dia > $contexto['dias_no_mes'] || !is_array($dadosDia)) {
        return;
    }

    if (!escala_mensal_dia_deve_guardar($dadosDia)) {
        escala_mensal_apagar_dia($conn, $funcionarioId, $contexto['ano'], $contexto['mes'], $dia);
        return;
    }

    $tipoDia = trim((string) ($dadosDia['tipo_dia'] ?? ''));

    $turnoId = isset($dadosDia['turno_id']) && (int) $dadosDia['turno_id'] > 0 ? (int) $dadosDia['turno_id'] : null;
    $substituiFuncionarioId = isset($dadosDia['substitui_funcionario_id']) && (int) $dadosDia['substitui_funcionario_id'] > 0 ? (int) $dadosDia['substitui_funcionario_id'] : null;
    $folgaTrabalhada = isset($dadosDia['folga_trabalhada']) ? 1 : 0;
    $observacoes = trim($dadosDia['observacoes'] ?? '');
    $observacoes = $observacoes === '' ? null : $observacoes;
    $dataEscala = sprintf('%04d-%02d-%02d', $contexto['ano'], $contexto['mes'], $dia);
    $utilizadorId = $funcionario['utilizador_id'] === null ? null : (int) $funcionario['utilizador_id'];
    $setorFuncionarioId = null;
    $equipaFuncionarioId = $funcionario['equipa_id'] === null ? null : (int) $funcionario['equipa_id'];

    if ($tipoDia !== 'turno' && $tipoDia !== 'substituicao' && $folgaTrabalhada === 0) {
        $turnoId = null;
    }

    if ($tipoDia !== 'substituicao') {
        $substituiFuncionarioId = null;
    }

    mysqli_stmt_bind_param(
        $stmtGuardar,
        'iiiiiisisiiis',
        $funcionarioId,
        $utilizadorId,
        $setorFuncionarioId,
        $equipaFuncionarioId,
        $contexto['ano'],
        $contexto['mes'],
        $dataEscala,
        $dia,
        $tipoDia,
        $turnoId,
        $substituiFuncionarioId,
        $folgaTrabalhada,
        $observacoes
    );
    mysqli_stmt_execute($stmtGuardar);
}

function escala_mensal_carregar_dados($conn, $contexto, $missingTables)
{
    $dados = [
        'equipas' => [],
        'turnos' => [],
        'funcionarios' => [],
        'funcionarios_filtro' => [],
        'funcionarios_substituicao' => [],
        'escala_guardada' => [],
        'funcionario_filtro_invalido' => false,
    ];

    if (!empty($missingTables)) {
        return $dados;
    }

    $dados['equipas'] = escala_mensal_carregar_equipas($conn);
    $dados['turnos'] = escala_mensal_carregar_turnos($conn);
    $dados['funcionarios_filtro'] = escala_mensal_carregar_funcionarios($conn, $contexto['equipa_id'], 0);
    $dados['funcionarios_substituicao'] = $dados['funcionarios_filtro'];
    $dados['funcionarios'] = escala_mensal_carregar_funcionarios($conn, $contexto['equipa_id'], $contexto['funcionario_id']);
    $dados['escala_guardada'] = escala_mensal_carregar_escala_guardada($conn, $contexto['ano'], $contexto['mes']);

    if ($contexto['funcionario_id'] > 0 && empty($dados['funcionarios'])) {
        $dados['funcionario_filtro_invalido'] = true;
    }

    return $dados;
}

function escala_mensal_carregar_equipas($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_turnos($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, codigo, hora_entrada, hora_saida FROM turnos WHERE ativo = 1 ORDER BY hora_entrada ASC, nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_funcionarios($conn, $equipaId, $funcionarioId = 0)
{
    $rows = [];
    $sql = "SELECT f.id, f.utilizador_id, f.nome, f.numero_mecanografico, f.funcao, f.equipa_id,
                   e.nome AS equipa_nome
            FROM funcionarios f
            LEFT JOIN equipas e ON e.id = f.equipa_id
            WHERE f.estado = 'ativo'";

    if ($equipaId > 0) {
        $sql .= ' AND f.equipa_id = ?';
    }

    if ($funcionarioId > 0) {
        $sql .= ' AND f.id = ?';
    }

    $sql .= ' ORDER BY e.nome ASC, f.nome ASC';
    $stmt = mysqli_prepare($conn, $sql);

    if ($equipaId > 0 && $funcionarioId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $equipaId, $funcionarioId);
    } elseif ($equipaId > 0) {
        mysqli_stmt_bind_param($stmt, 'i', $equipaId);
    } elseif ($funcionarioId > 0) {
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_escala_guardada($conn, $ano, $mes)
{
    $escala = [];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM escala_funcionarios WHERE ano = ? AND mes = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $ano, $mes);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $escala[(int) $row['funcionario_id']][(int) $row['dia']] = $row;
    }

    mysqli_stmt_close($stmt);
    return $escala;
}

function escala_mensal_csv_delimitador(string $content): string
{
    $firstLine = strtok($content, "\r\n");
    if ($firstLine === false || $firstLine === '') {
        return ',';
    }

    return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
}

function escala_mensal_normalizar_coluna_csv(string $col): string
{
    $col = trim($col);
    $col = preg_replace('/^\x{FEFF}/u', '', $col) ?? $col;

    return strtolower($col);
}

function escala_mensal_import_linha_e_cabecalho(array $data): bool
{
    $dia = trim((string) ($data['dia'] ?? ''));
    $numero = trim((string) ($data['numero_mecanografico'] ?? $data['numero'] ?? ''));

    return $dia === 'dia'
        || $numero === 'numero_mecanografico'
        || $numero === 'numero';
}

function escala_mensal_parse_dia_import(string $raw, int $mes, int $ano, int $diasNoMes): ?int
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    if (ctype_digit($raw)) {
        $dia = (int) $raw;
        return ($dia >= 1 && $dia <= $diasNoMes) ? $dia : null;
    }

    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $raw, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += 2000;
        }
        if ($mo === $mes && $y === $ano && $d >= 1 && $d <= $diasNoMes) {
            return $d;
        }

        return null;
    }

    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $raw, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if ($mo === $mes && $y === $ano && $d >= 1 && $d <= $diasNoMes) {
            return $d;
        }

        return null;
    }

    if (is_numeric($raw)) {
        $dia = (int) $raw;
        return ($dia >= 1 && $dia <= $diasNoMes) ? $dia : null;
    }

    return null;
}

function escala_mensal_importar_csv($conn, $contexto, $missingTables, $baseParams): void
{
    if (!empty($missingTables)) {
        escala_mensal_redirect('danger', 'Importe o ficheiro csnsa.sql antes de importar escalas.', $baseParams);
    }

    $file = $_FILES['ficheiro_escala'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        escala_mensal_redirect('danger', 'Selecione um ficheiro CSV.', $baseParams);
    }

    $extensao = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($extensao, ['xlsx', 'xls'], true)) {
        escala_mensal_redirect(
            'danger',
            'Ficheiros Excel (.xlsx/.xls) ainda não são lidos directamente. No Excel: Ficheiro → Guardar como → CSV UTF-8, depois importe o .csv.',
            $baseParams
        );
    }

    $funcionarioIdImport = (int) ($_POST['funcionario_id'] ?? 0);
    $funcionarioFixo = null;

    if ($funcionarioIdImport > 0) {
        $funcionarioFixo = escala_mensal_carregar_funcionario_ativo($conn, $funcionarioIdImport, (int) $contexto['equipa_id']);
        if (!$funcionarioFixo) {
            escala_mensal_redirect('danger', 'Funcionário selecionado não encontrado ou não pertence à equipa filtrada.', $baseParams);
        }
        $baseParams['funcionario_id'] = $funcionarioIdImport;
    }

    $conteudo = file_get_contents($file['tmp_name']);
    if ($conteudo === false || trim($conteudo) === '') {
        escala_mensal_redirect('danger', 'Ficheiro vazio ou inválido.', $baseParams);
    }

    $delim = escala_mensal_csv_delimitador($conteudo);
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        escala_mensal_redirect('danger', 'Não foi possível ler o ficheiro.', $baseParams);
    }

    $header = fgetcsv($handle, 0, $delim);
    if (!$header) {
        fclose($handle);
        escala_mensal_redirect('danger', 'Não foi possível ler o cabeçalho do ficheiro.', $baseParams);
    }

    $header = array_map('escala_mensal_normalizar_coluna_csv', $header);
    $header = array_values(array_filter($header, static fn ($col) => $col !== ''));

    if (!in_array('dia', $header, true)) {
        fclose($handle);
        escala_mensal_redirect(
            'danger',
            'Coluna obrigatória em falta: dia. Cabeçalho encontrado: ' . implode(', ', $header) . '.',
            $baseParams
        );
    }

    if (!$funcionarioFixo && !in_array('numero_mecanografico', $header, true) && !in_array('numero', $header, true)) {
        fclose($handle);
        escala_mensal_redirect(
            'danger',
            'Falta a coluna numero_mecanografico. Ou seleccione um funcionário em "Importar para" e use só dia, turno_codigo, tipo_dia.',
            $baseParams
        );
    }

    $importados = 0;
    $ignorados = 0;
    $semFuncionario = 0;
    $errosDetalhe = [];
    $ano = (int) $contexto['ano'];
    $mes = (int) $contexto['mes'];
    $numLinha = 1;

    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        $numLinha++;
        if ($row === [null] || $row === false || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }

        $padded = array_pad($row, count($header), '');
        if (count($padded) > count($header)) {
            $padded = array_slice($padded, 0, count($header));
        }

        try {
            $data = array_combine($header, $padded);
        } catch (ValueError) {
            $ignorados++;
            if (count($errosDetalhe) < 4) {
                $errosDetalhe[] = "Linha {$numLinha}: número de colunas incorrecto";
            }
            continue;
        }

        if (!$data || escala_mensal_import_linha_e_cabecalho($data)) {
            continue;
        }

        $diaRaw = trim((string) ($data['dia'] ?? ''));
        $dia = escala_mensal_parse_dia_import($diaRaw, $mes, $ano, (int) $contexto['dias_no_mes']);
        if ($dia === null) {
            $ignorados++;
            if (count($errosDetalhe) < 4) {
                $errosDetalhe[] = "Linha {$numLinha}: dia inválido «{$diaRaw}» — use 1 a {$contexto['dias_no_mes']} (não use datas completas tipo 01/07/2026 a menos que seja desse mês)";
            }
            continue;
        }

        if ($funcionarioFixo) {
            $funcionario = $funcionarioFixo;
        } else {
            $numero = trim((string) ($data['numero_mecanografico'] ?? $data['numero'] ?? ''));
            if ($numero === '') {
                $ignorados++;
                if (count($errosDetalhe) < 4) {
                    $errosDetalhe[] = "Linha {$numLinha}: numero_mecanografico em falta";
                }
                continue;
            }

            $stmt = mysqli_prepare($conn, 'SELECT id, utilizador_id, equipa_id FROM funcionarios WHERE numero_mecanografico = ? AND estado = "ativo" LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $numero);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $funcionario = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$funcionario) {
                $semFuncionario++;
                if (count($errosDetalhe) < 4) {
                    $errosDetalhe[] = "Linha {$numLinha}: funcionário «{$numero}» não existe (crie-o em Funcionários)";
                }
                continue;
            }

            if ((int) $contexto['equipa_id'] > 0 && (int) ($funcionario['equipa_id'] ?? 0) !== (int) $contexto['equipa_id']) {
                $ignorados++;
                if (count($errosDetalhe) < 4) {
                    $errosDetalhe[] = "Linha {$numLinha}: funcionário «{$numero}» não pertence à equipa filtrada";
                }
                continue;
            }
        }

        $tipoDia = trim((string) ($data['tipo_dia'] ?? 'turno'));
        if (!in_array($tipoDia, escala_mensal_tipos_dia(), true)) {
            $tipoDia = 'turno';
        }

        $turnoCodigo = trim((string) ($data['turno_codigo'] ?? ''));
        $turnoId = null;
        if ($turnoCodigo !== '') {
            $stmt = mysqli_prepare($conn, 'SELECT id FROM turnos WHERE codigo = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $turnoCodigo);
            mysqli_stmt_execute($stmt);
            $resT = mysqli_stmt_get_result($stmt);
            $turno = mysqli_fetch_assoc($resT);
            mysqli_stmt_close($stmt);
            $turnoId = $turno ? (int) $turno['id'] : null;
            if ($turnoId === null && count($errosDetalhe) < 4) {
                $errosDetalhe[] = "Linha {$numLinha}: turno «{$turnoCodigo}» não encontrado (crie-o em Turnos)";
            }
        }

        if (in_array($tipoDia, ['turno', 'substituicao'], true) && $turnoId === null) {
            $ignorados++;
            if (count($errosDetalhe) < 4) {
                $errosDetalhe[] = "Linha {$numLinha}: dia {$dia} é turno mas falta turno_codigo na coluna";
            }
            continue;
        }

        $dataEscala = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $funcionarioId = (int) $funcionario['id'];
        $utilizadorId = $funcionario['utilizador_id'] ? (int) $funcionario['utilizador_id'] : null;
        $equipaId = $funcionario['equipa_id'] ? (int) $funcionario['equipa_id'] : null;
        $setorId = null;
        $folgaTrabalhada = 0;
        $observacoes = null;
        $substituiId = null;

        $stmt = mysqli_prepare($conn, "INSERT INTO escala_funcionarios
            (funcionario_id, utilizador_id, setor_id, equipa_id, ano, mes, data_escala, dia, tipo_dia, turno_id, substitui_funcionario_id, folga_trabalhada, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE tipo_dia = VALUES(tipo_dia), turno_id = VALUES(turno_id), equipa_id = VALUES(equipa_id)");
        mysqli_stmt_bind_param(
            $stmt,
            'iiiiiisisiiis',
            $funcionarioId,
            $utilizadorId,
            $setorId,
            $equipaId,
            $ano,
            $mes,
            $dataEscala,
            $dia,
            $tipoDia,
            $turnoId,
            $substituiId,
            $folgaTrabalhada,
            $observacoes
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $importados++;
    }

    fclose($handle);

    if ($importados === 0) {
        $detalhe = [];
        if ($semFuncionario > 0) {
            $detalhe[] = "{$semFuncionario} linha(s) com número mecanográfico desconhecido";
        }
        if ($ignorados > 0) {
            $detalhe[] = "{$ignorados} linha(s) inválida(s)";
        }
        $msg = 'Nenhum horário foi importado.';
        if ($detalhe !== []) {
            $msg .= ' ' . implode('. ', $detalhe) . '.';
        }
        if ($errosDetalhe !== []) {
            $msg .= ' Detalhe: ' . implode('; ', $errosDetalhe) . '.';
        }
        $msg .= ' Use o ficheiro exemplo (link abaixo) ou guarde o Excel como CSV UTF-8.';
        escala_mensal_redirect('warning', $msg, $baseParams);
    }

    escala_mensal_guardar_log_importacao([
        'quando' => date('Y-m-d H:i:s'),
        'ficheiro' => (string) ($file['name'] ?? 'import.csv'),
        'importados' => $importados,
        'ignorados' => $ignorados,
        'sem_funcionario' => $semFuncionario,
        'ano' => $ano,
        'mes' => $mes,
        'funcionario_id' => $funcionarioFixo ? (int) $funcionarioFixo['id'] : 0,
        'funcionario_nome' => $funcionarioFixo ? (string) $funcionarioFixo['nome'] : '',
    ]);

    $msg = "Importação concluída: {$importados} dia(s) guardado(s) na escala de " . month_name($mes) . " {$ano}.";
    $msg .= ' Veja os horários na grelha abaixo';
    if ($funcionarioFixo) {
        $msg .= ' (funcionário: ' . $funcionarioFixo['nome'] . ')';
        $baseParams['funcionario_id'] = (int) $funcionarioFixo['id'];
    } else {
        $msg .= ' — filtre por funcionário se necessário';
    }
    $msg .= '.';
    if ($semFuncionario > 0 || $ignorados > 0) {
        $msg .= " ({$ignorados} linha(s) ignorada(s)";
        if ($semFuncionario > 0) {
            $msg .= ", {$semFuncionario} sem funcionário correspondente";
        }
        if ($errosDetalhe !== []) {
            $msg .= ' — ' . implode('; ', array_slice($errosDetalhe, 0, 3));
        }
        $msg .= ')';
    }

    escala_mensal_redirect('success', $msg, array_merge($baseParams, ['ver' => 'importado']));
}

function escala_mensal_carregar_funcionario_ativo($conn, $funcionarioId, $equipaId = 0)
{
    if ($funcionarioId <= 0) {
        return null;
    }

    $sql = 'SELECT f.id, f.utilizador_id, f.equipa_id, f.nome, f.numero_mecanografico
            FROM funcionarios f
            WHERE f.id = ? AND f.estado = "ativo"';
    if ($equipaId > 0) {
        $sql .= ' AND f.equipa_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if ($equipaId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $funcionarioId, $equipaId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function escala_mensal_contar_dias_guardados($escalaGuardada, $funcionarioId)
{
    if ($funcionarioId <= 0 || empty($escalaGuardada[$funcionarioId])) {
        return 0;
    }

    return count($escalaGuardada[$funcionarioId]);
}

function escala_mensal_guardar_log_importacao(array $log): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['escala_import_log'] = $log;
}

function escala_mensal_obter_log_importacao(int $ano, int $mes): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['escala_import_log'])) {
        return null;
    }

    $log = $_SESSION['escala_import_log'];
    if ((int) ($log['ano'] ?? 0) !== $ano || (int) ($log['mes'] ?? 0) !== $mes) {
        return null;
    }

    return $log;
}

function escala_mensal_resumo_periodo(array $escalaGuardada, array $funcionarios, bool $incluirSemEscala = false): array
{
    $porTipo = array_fill_keys(escala_mensal_tipos_dia(), 0);
    $porFuncionario = [];
    $totalRegistos = 0;

    foreach ($funcionarios as $funcionario) {
        $funcId = (int) $funcionario['id'];
        $diasFunc = $escalaGuardada[$funcId] ?? [];

        if ($diasFunc === [] && !$incluirSemEscala) {
            continue;
        }

        $tiposFunc = [];
        foreach ($diasFunc as $registo) {
            $tipo = (string) ($registo['tipo_dia'] ?? 'turno');
            if (!isset($porTipo[$tipo])) {
                $porTipo[$tipo] = 0;
            }
            $porTipo[$tipo]++;
            $tiposFunc[$tipo] = ($tiposFunc[$tipo] ?? 0) + 1;
            $totalRegistos++;
        }

        $partes = [];
        foreach ($tiposFunc as $tipo => $qtd) {
            $partes[] = $qtd . ' ' . strtolower(escala_mensal_tipo_label($tipo));
        }

        $porFuncionario[] = [
            'id' => $funcId,
            'nome' => $funcionario['nome'],
            'numero_mecanografico' => $funcionario['numero_mecanografico'] ?? '',
            'dias' => count($diasFunc),
            'resumo' => $partes !== [] ? implode(', ', $partes) : 'Sem dias planeados',
            'tem_escala' => $diasFunc !== [],
        ];
    }

    usort($porFuncionario, static function ($a, $b) {
        if ($a['tem_escala'] !== $b['tem_escala']) {
            return $b['tem_escala'] <=> $a['tem_escala'];
        }

        return strcmp($a['nome'], $b['nome']);
    });

    return [
        'total_registos' => $totalRegistos,
        'funcionarios_com_escala' => count(array_filter($porFuncionario, static fn ($linha) => $linha['tem_escala'])),
        'por_tipo' => array_filter($porTipo, static fn ($n) => $n > 0),
        'por_funcionario' => $porFuncionario,
    ];
}

function escala_mensal_listar_detalhe_importado($conn, int $ano, int $mes, int $equipaId = 0, int $funcionarioId = 0, int $limite = 80): array
{
    if (!escala_mensal_table_exists($conn, 'escala_funcionarios')) {
        return [];
    }

    $sql = "SELECT ef.dia, ef.data_escala, ef.tipo_dia, ef.turno_id,
                   f.id AS funcionario_id, f.nome AS funcionario_nome, f.numero_mecanografico,
                   t.codigo AS turno_codigo, t.nome AS turno_nome
            FROM escala_funcionarios ef
            INNER JOIN funcionarios f ON f.id = ef.funcionario_id
            LEFT JOIN turnos t ON t.id = ef.turno_id
            WHERE ef.ano = ? AND ef.mes = ?";
    $params = [$ano, $mes];
    $types = 'ii';

    if ($equipaId > 0) {
        $sql .= ' AND f.equipa_id = ?';
        $params[] = $equipaId;
        $types .= 'i';
    }

    if ($funcionarioId > 0) {
        $sql .= ' AND f.id = ?';
        $params[] = $funcionarioId;
        $types .= 'i';
    }

    $sql .= ' ORDER BY f.nome ASC, ef.dia ASC LIMIT ' . (int) $limite;

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $linhas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $linhas[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $linhas;
}

function escala_mensal_badge_tipo(string $tipo): string
{
    $map = [
        'turno' => 'primary',
        'folga' => 'secondary',
        'ferias' => 'info',
        'falta' => 'danger',
        'baixa' => 'warning',
        'substituicao' => 'success',
        'licenca_amamentacao' => 'dark',
    ];

    return $map[$tipo] ?? 'light';
}

function escala_mensal_contar_turnos_em_falta(array $escalaGuardada, int $funcionarioId = 0): int
{
    $count = 0;

    foreach ($escalaGuardada as $fid => $dias) {
        if ($funcionarioId > 0 && (int) $fid !== $funcionarioId) {
            continue;
        }

        foreach ($dias as $registo) {
            $tipo = (string) ($registo['tipo_dia'] ?? 'turno');
            $turnoId = (int) ($registo['turno_id'] ?? 0);
            $folgaTrabalhada = (int) ($registo['folga_trabalhada'] ?? 0) === 1;

            if (in_array($tipo, ['turno', 'substituicao'], true) || $folgaTrabalhada) {
                if ($turnoId <= 0) {
                    $count++;
                }
            }
        }
    }

    return $count;
}

function escala_mensal_dados_celula(array $registo = []): array
{
    $guardado = $registo !== [];

    return [
        'tipo_dia' => $guardado ? (string) ($registo['tipo_dia'] ?? 'turno') : '',
        'turno_id' => (int) ($registo['turno_id'] ?? 0),
        'substitui_id' => (int) ($registo['substitui_funcionario_id'] ?? 0),
        'folga_trabalhada' => (int) ($registo['folga_trabalhada'] ?? 0) === 1,
        'observacoes' => (string) ($registo['observacoes'] ?? ''),
        'guardado' => $guardado,
    ];
}

function escala_mensal_render_aviso_incompleto(int $count, bool $vistaSimples, bool $hasTurnos): void
{
    if ($count <= 0) {
        return;
    }

    $titulo = $count === 1
        ? 'Falta completar 1 dia'
        : 'Falta completar ' . $count . ' dias';

    $html = '<p class="escala-ajuda-titulo mb-1"><strong>' . e($titulo) . '</strong></p>';
    $html .= '<p class="mb-2 small">Há dias guardados como <strong>Turno</strong> sem horário (Manhã, Tarde, etc.). Estão a amarelo abaixo — complete-os ou mude para Folga/Férias.</p>';
    $html .= '<p class="mb-1 small mb-0"><strong>Como resolver:</strong></p>';
    $html .= '<ol class="escala-ajuda-steps mb-2 pl-3 small">';
    $html .= '<li>Abra cada dia a amarelo e escolha o horário em <em>Escolher turno…</em></li>';
    $html .= '<li>Ou escolha <em>Folga</em> / <em>Férias</em> se não trabalha</li>';
    $html .= '<li>Ou use <em>— O que faz neste dia? —</em> para deixar o dia em branco (será apagado ao guardar)</li>';
    $html .= '<li>Clique <em>Guardar escala</em> quando terminar</li>';
    $html .= '</ol>';

    if (!$hasTurnos) {
        $html .= '<p class="mb-0 small">Ainda não há horários criados. Comece por <a href="' . e(admin_url('turnos')) . '" class="alert-link">criar turnos</a> (ex.: Manhã, Tarde).</p>';
    } else {
        $html .= '<div class="escala-ajuda-actions">';
        $html .= '<button type="button" class="btn btn-sm btn-outline-warning js-scroll-incompleto"><i class="fe fe-arrow-down mr-1"></i> Ir ao primeiro dia</button>';
        if ($vistaSimples) {
            $html .= '<button type="button" class="btn btn-sm btn-outline-secondary js-aplicar-folga-fds">Fins de semana → Folga</button>';
        }
        $html .= '<a href="' . e(admin_url('turnos')) . '" class="btn btn-sm btn-outline-primary">Ver turnos</a>';
        $html .= '</div>';
    }

    render_alert_html($html, 'warning', true, 'escala-ajuda-incompleto mb-0');
}
