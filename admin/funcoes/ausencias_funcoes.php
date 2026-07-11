<?php

require_once __DIR__ . '/calcular_resumo_diario_assiduidade.php';

function ausencias_meses_passados(int $anoFiltro, ?int $anoReferencia = null, ?int $mesReferencia = null): array
{
    $anoReferencia = $anoReferencia ?? (int) date('Y');
    $mesReferencia = $mesReferencia ?? (int) date('n');

    if ($anoFiltro > $anoReferencia) {
        return [];
    }

    $limite = $anoFiltro < $anoReferencia ? 12 : $mesReferencia;
    $meses = [];

    for ($mes = 1; $mes <= $limite; $mes++) {
        $meses[] = ['mes' => $mes, 'ano' => $anoFiltro];
    }

    return $meses;
}

function ausencias_filtro_query(int $anoFiltro, int $mesFiltro): array
{
    $params = ['ano' => $anoFiltro];
    if ($mesFiltro >= 1 && $mesFiltro <= 12) {
        $params['mes'] = $mesFiltro;
    }

    return $params;
}

function ausencias_normalizar_filtro(?int $ano, ?int $mes): array
{
    $anoAtual = (int) date('Y');
    $mesAtual = (int) date('n');
    $anoFiltro = (int) ($ano ?? $anoAtual);
    $mesFiltro = (int) ($mes ?? 0);

    if ($anoFiltro < 2000 || $anoFiltro > 2100) {
        $anoFiltro = $anoAtual;
    }

    if ($mesFiltro < 0 || $mesFiltro > 12) {
        $mesFiltro = 0;
    }

    $mesesPassados = ausencias_meses_passados($anoFiltro, $anoAtual, $mesAtual);
    if ($mesFiltro > 0 && !ausencias_mes_filtro_valido($mesFiltro, $mesesPassados)) {
        $mesFiltro = 0;
    }

    return [$anoFiltro, $mesFiltro, $mesesPassados];
}

function ausencias_mes_filtro_valido(int $mesFiltro, array $mesesPassados): bool
{
    if ($mesFiltro < 1 || $mesFiltro > 12) {
        return false;
    }

    foreach ($mesesPassados as $item) {
        if ((int) $item['mes'] === $mesFiltro) {
            return true;
        }
    }

    return false;
}

function ausencias_horas_turno($conn, ?int $turnoId): float
{
    if (!$turnoId) {
        return 0.0;
    }

    $periodos = assiduidade_obter_turno_periodos($conn, $turnoId);
    if ($periodos === []) {
        return 0.0;
    }

    $previsto = assiduidade_calcular_previsto('2000-01-01', $periodos);

    return round($previsto['minutos_previstos'] / 60, 2);
}

function ausencias_dia_conta_trabalho_escala(array $linhaEscala): bool
{
    $tipoDia = (string) ($linhaEscala['tipo_dia'] ?? '');
    $turnoId = !empty($linhaEscala['turno_id']) ? (int) $linhaEscala['turno_id'] : null;
    $folgaTrabalhada = (int) ($linhaEscala['folga_trabalhada'] ?? 0) === 1;

    if (in_array($tipoDia, ['turno', 'substituicao', 'falta', 'baixa'], true) && $turnoId) {
        return true;
    }

    if ($folgaTrabalhada && $turnoId) {
        return true;
    }

    return false;
}

function ausencias_horas_diarias_fallback($conn, ?int $funcionarioId): float
{
    if (!$funcionarioId || !fe_table_exists($conn, 'funcionarios')) {
        return 8.0;
    }

    $stmt = mysqli_prepare($conn, 'SELECT carga_horaria_semanal FROM funcionarios WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $semanal = (float) ($row['carga_horaria_semanal'] ?? 40);

    return $semanal > 0 ? round($semanal / 5, 2) : 8.0;
}

function ausencias_dia_util(DateTime $data): bool
{
    $diaSemana = (int) $data->format('N');

    return $diaSemana >= 1 && $diaSemana <= 5;
}

/**
 * Calcula dias e horas de ausência com base na escala horária do funcionário.
 * Se não existir escala no período, usa dias corridos ou úteis conforme o tipo.
 */
function ausencias_calcular_impacto_escala($conn, ?int $funcionarioId, string $dataInicio, string $dataFim, string $tipoDias = 'corridos'): array
{
    $inicio = new DateTime($dataInicio);
    $fim = new DateTime($dataFim);

    if ($fim < $inicio) {
        return ['dias' => 0.0, 'horas' => 0.0, 'com_escala' => false];
    }

    $escalaPorData = [];

    if ($funcionarioId && fe_table_exists($conn, 'escala_funcionarios')) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT data_escala, tipo_dia, turno_id, folga_trabalhada
             FROM escala_funcionarios
             WHERE funcionario_id = ? AND data_escala BETWEEN ? AND ?'
        );
        mysqli_stmt_bind_param($stmt, 'iss', $funcionarioId, $dataInicio, $dataFim);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $escalaPorData[$row['data_escala']] = $row;
        }

        mysqli_stmt_close($stmt);
    }

    if ($escalaPorData !== []) {
        $dias = 0;
        $horas = 0.0;
        $cursor = clone $inicio;

        while ($cursor <= $fim) {
            $data = $cursor->format('Y-m-d');

            if (isset($escalaPorData[$data]) && ausencias_dia_conta_trabalho_escala($escalaPorData[$data])) {
                $dias++;
                $horas += ausencias_horas_turno($conn, (int) ($escalaPorData[$data]['turno_id'] ?? 0));
            }

            $cursor->modify('+1 day');
        }

        return ['dias' => (float) $dias, 'horas' => round($horas, 2), 'com_escala' => true];
    }

    $dias = 0;
    $horas = 0.0;
    $horasFallback = ausencias_horas_diarias_fallback($conn, $funcionarioId);
    $cursor = clone $inicio;
    $somenteUteis = $tipoDias === 'uteis';

    while ($cursor <= $fim) {
        if (!$somenteUteis || ausencias_dia_util($cursor)) {
            $dias++;
            $horas += $horasFallback;
        }

        $cursor->modify('+1 day');
    }

    return ['dias' => (float) $dias, 'horas' => round($horas, 2), 'com_escala' => false];
}

function ausencias_formatar_dias(float $dias): string
{
    if (fmod($dias, 1.0) === 0.0) {
        return (string) (int) $dias;
    }

    return number_format($dias, 1, ',', '');
}

function ausencias_formatar_horas(float $horas): string
{
    return number_format($horas, 2, ',', '') . ' h';
}

function ausencias_tipo_id_por_slug($conn, string $slug): ?int
{
    if (!fe_table_exists($conn, 'tipos_ausencia')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE slug = ? AND ativo = 1 LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ? (int) $row['id'] : null;
}

function ausencias_listar_funcionarios_ativos($conn): array
{
    if (!fe_table_exists($conn, 'funcionarios')) {
        return [];
    }

    $lista = [];
    $stmt = mysqli_prepare($conn, "SELECT id, nome, numero_mecanografico FROM funcionarios WHERE estado NOT IN ('arquivado', 'inativo') ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function ausencias_data_tem_justificacao_aprovada($conn, int $funcionarioId, string $data): bool
{
    if (!fe_table_exists($conn, 'pedidos_ausencia') || $funcionarioId <= 0 || $data === '') {
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM pedidos_ausencia
        WHERE funcionario_id = ?
          AND estado = 'aprovado'
          AND data_inicio <= ?
          AND data_fim >= ?
          AND ficheiro_justificativo IS NOT NULL
          AND ficheiro_justificativo <> ''
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iss', $funcionarioId, $data, $data);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (bool) $row;
}

function ausencias_listar_faltas_sem_justificacao($conn, int $dias = 90): array
{
    $lista = [];

    if (fe_table_exists($conn, 'resumo_diario_assiduidade') && fe_table_exists($conn, 'funcionarios')) {
        $stmt = mysqli_prepare($conn, "SELECT r.funcionario_id, r.data, f.nome AS funcionario_nome, f.numero_mecanografico
            FROM resumo_diario_assiduidade r
            INNER JOIN funcionarios f ON f.id = r.funcionario_id
            WHERE r.falta = 1
              AND r.data >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND r.data <= CURDATE()
              AND f.estado NOT IN ('arquivado', 'inativo')
            ORDER BY r.data DESC, f.nome ASC");
        mysqli_stmt_bind_param($stmt, 'i', $dias);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data = (string) $row['data'];
            $funcionarioId = (int) $row['funcionario_id'];
            if (!ausencias_data_tem_justificacao_aprovada($conn, $funcionarioId, $data)) {
                $lista[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $lista;
}

function ausencias_sincronizar_ferias_de_pedido($conn, int $pedidoId, int $aprovadorId): ?string
{
    if (!fe_table_exists($conn, 'ferias_ausencias')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT pa.* FROM pedidos_ausencia pa WHERE pa.id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $pedidoId);
    mysqli_stmt_execute($stmt);
    $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$pedido || ($pedido['estado'] ?? '') !== 'aprovado') {
        return null;
    }

    $funcionarioId = $pedido['funcionario_id'] ? (int) $pedido['funcionario_id'] : null;
    if (!$funcionarioId) {
        return ' Pedido aprovado, mas o funcionário não está associado.';
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM ferias_ausencias WHERE pedido_ausencia_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $pedidoId);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existe) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO ferias_ausencias
        (pedido_ausencia_id, funcionario_id, utilizador_id, tipo_ausencia_id, data_inicio, data_fim,
         dia_completo, estado, motivo, ficheiro_justificativo, aprovado_por, aprovado_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, 'aprovado', ?, ?, ?, NOW())");
    mysqli_stmt_bind_param(
        $stmt,
        'iiiissssi',
        $pedidoId,
        $funcionarioId,
        $pedido['utilizador_id'],
        $pedido['tipo_ausencia_id'],
        $pedido['data_inicio'],
        $pedido['data_fim'],
        $pedido['motivo'],
        $pedido['ficheiro_justificativo'],
        $aprovadorId
    );

    try {
        mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $e) {
        mysqli_stmt_close($stmt);
        return ' Pedido aprovado, mas não foi possível registar em férias/ausências.';
    }
    mysqli_stmt_close($stmt);

    return null;
}

function ausencias_estado_documento(array $pedido): string
{
    $temAnexo = !empty($pedido['ficheiro_justificativo']);
    $exige = (int) ($pedido['exige_justificativo'] ?? 0) === 1;
    $estado = (string) ($pedido['estado'] ?? '');

    if ($temAnexo) {
        return 'com_documento';
    }

    if ($estado === 'pendente' && $exige) {
        return 'aguarda_documento';
    }

    if ($estado === 'aprovado' && $exige) {
        return 'sem_documento';
    }

    return 'nao_aplica';
}

function ausencias_label_documento(string $estado): string
{
    $labels = [
        'com_documento' => 'Justificada',
        'aguarda_documento' => 'Por justificar',
        'sem_documento' => 'Sem justificação',
        'nao_aplica' => '—',
    ];

    return $labels[$estado] ?? '—';
}

function ausencias_badge_documento(string $estado): string
{
    $classes = [
        'com_documento' => 'success',
        'aguarda_documento' => 'warning',
        'sem_documento' => 'danger',
        'nao_aplica' => 'secondary',
    ];

    return $classes[$estado] ?? 'secondary';
}
