<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/funcoes/ausencias_funcoes.php';
require_once __DIR__ . '/includes/upload_field.php';
require_page_permission();

function ausencias_go(string $type, string $message): void
{
    admin_redirect_msg('ausencias', $type, $message, $GLOBALS['ausencias_filtro_query'] ?? []);
}

function estado_label($estado)
{
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    return $labels[$estado] ?? ucfirst((string) $estado);
}

function estado_badge($estado)
{
    $classes = [
        'pendente' => 'warning',
        'aprovado' => 'success',
        'rejeitado' => 'danger',
        'cancelado' => 'secondary',
    ];

    return $classes[$estado] ?? 'secondary';
}

function pode_aprovar($conn, $utilizadorId)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        WHERE up.utilizador_id = ? AND p.slug IN ('administrador', 'chefia', 'recursos-humanos', 'gestor_rh', 'supervisor')");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function guardar_anexo_seguro($file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível carregar o anexo.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('O anexo não pode exceder 5 MB.');
    }

    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $mimePermitidos = ['application/pdf', 'image/jpeg', 'image/png'];
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new RuntimeException('Formato de anexo inválido. Use PDF, JPG ou PNG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $mimePermitidos, true)) {
        throw new RuntimeException('O conteúdo do ficheiro não corresponde a um formato permitido.');
    }

    $diretorio = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ausencias';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar a pasta de anexos.');
    }

    $nomeSeguro = bin2hex(random_bytes(16)) . '.' . $extensao;
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeSeguro;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível guardar o anexo.');
    }

    return 'uploads/ausencias/' . $nomeSeguro;
}

function ausencias_resolver_funcionario_id($conn, int $utilizadorId, ?string $email): ?int
{
    if (!fe_table_exists($conn, 'funcionarios')) {
        return null;
    }

    if (fe_column_exists($conn, 'funcionarios', 'utilizador_id') && $utilizadorId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM funcionarios WHERE utilizador_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($row) {
            return (int) $row['id'];
        }
    }

    if ($email && fe_column_exists($conn, 'funcionarios', 'email')) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM funcionarios WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($row) {
            return (int) $row['id'];
        }
    }

    return null;
}

function ausencias_periodo_mes($ano, $mes): array
{
    $inicio = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);
    $fim = date('Y-m-t', strtotime($inicio));

    return [$inicio, $fim];
}

function garantir_tipos_ausencia($conn)
{
    $tipos = [
        ['Férias', 'ferias', 'Pedido de férias', 1, 1, 0],
        ['Falta Justificada', 'falta-justificada', 'Falta com justificação', 1, 0, 1],
        ['Baixa Médica', 'baixa-medica', 'Baixa por motivo de saúde', 1, 0, 1],
        ['Folga', 'folga', 'Pedido de folga', 1, 0, 0],
    ];

    foreach ($tipos as $tipo) {
        [$nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo] = $tipo;

        $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE slug = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existe = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$existe) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO tipos_ausencia (nome, slug, descricao, remunerada, desconta_ferias, exige_justificativo) VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssiii', $nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

$user = current_user();
$utilizadorAutenticadoId = 0;
$utilizadorAutenticado = null;
$temPermissaoAprovar = user_has_role('admin');

if ($user) {
    $utilizadorAutenticadoId = find_utilizador_id_by_email((string) ($user['email'] ?? ''));
    if ($utilizadorAutenticadoId <= 0 && user_is_admin()) {
        sync_utilizador_from_user($user);
        $utilizadorAutenticadoId = find_utilizador_id_by_email((string) ($user['email'] ?? ''));
    }

    if ($utilizadorAutenticadoId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT id, nome, email, estado FROM utilizadores WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $utilizadorAutenticado = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }
    }

    if ($utilizadorAutenticado) {
        $temPermissaoAprovar = $temPermissaoAprovar || pode_aprovar($conn, $utilizadorAutenticadoId);
    }
}

garantir_tipos_ausencia($conn);

[$anoFiltro, $mesFiltro, $mesesPassados] = ausencias_normalizar_filtro(
    isset($_REQUEST['ano']) ? (int) $_REQUEST['ano'] : null,
    isset($_REQUEST['mes']) ? (int) $_REQUEST['mes'] : null
);
$GLOBALS['ausencias_filtro_query'] = ausencias_filtro_query($anoFiltro, $mesFiltro);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!$utilizadorAutenticado || $utilizadorAutenticado['estado'] !== 'ativo') {
        ausencias_go( 'danger', 'Precisa de estar autenticado com um utilizador ativo.');
    }

    if ($acao === 'pedir') {
        $tipoAusenciaId = (int) ($_POST['tipo_ausencia_id'] ?? 0);
        $dataInicio = trim($_POST['data_inicio'] ?? '');
        $dataFim = trim($_POST['data_fim'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($tipoAusenciaId <= 0 || $dataInicio === '' || $dataFim === '' || $motivo === '') {
            ausencias_go( 'danger', 'Preencha todos os campos obrigatórios.');
        }

        if ($dataFim < $dataInicio) {
            ausencias_go( 'danger', 'A data fim não pode ser anterior a data início.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT id, exige_justificativo, tipo_dias FROM tipos_ausencia WHERE id = ? AND ativo = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $tipoAusenciaId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tipoExiste = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$tipoExiste) {
            ausencias_go( 'danger', 'Tipo de ausência inválido.');
        }

        try {
            $anexo = guardar_anexo_seguro($_FILES['anexo'] ?? null);

            if ((int) ($tipoExiste['exige_justificativo'] ?? 0) === 1 && $anexo === null) {
                ausencias_go( 'danger', 'Este tipo de ausência exige anexo justificativo.');
            }

            $funcionarioId = null;
            if ($temPermissaoAprovar) {
                $funcionarioIdPost = (int) ($_POST['funcionario_id'] ?? 0);
                if ($funcionarioIdPost > 0) {
                    $funcionarioId = $funcionarioIdPost;
                }
            }
            if (!$funcionarioId) {
                $funcionarioId = ausencias_resolver_funcionario_id(
                    $conn,
                    $utilizadorAutenticadoId,
                    (string) ($utilizadorAutenticado['email'] ?? '')
                );
            }

            if (!$funcionarioId) {
                ausencias_go( 'danger', 'Selecione o funcionário ou associe o utilizador a um funcionário.');
            }

            $impacto = ausencias_calcular_impacto_escala(
                $conn,
                $funcionarioId,
                $dataInicio,
                $dataFim,
                (string) ($tipoExiste['tipo_dias'] ?? 'corridos')
            );

            if ($impacto['dias'] <= 0) {
                ausencias_go( 'danger', 'No período indicado não existem dias de trabalho previstos na escala.');
            }

            $totalDias = $impacto['dias'];
            $totalHoras = $impacto['horas'];

            $stmt = mysqli_prepare($conn, "INSERT INTO pedidos_ausencia
                (funcionario_id, utilizador_id, tipo_ausencia_id, data_inicio, data_fim, total_dias, total_horas, motivo, ficheiro_justificativo, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
            mysqli_stmt_bind_param(
                $stmt,
                'iiissddss',
                $funcionarioId,
                $utilizadorAutenticadoId,
                $tipoAusenciaId,
                $dataInicio,
                $dataFim,
                $totalDias,
                $totalHoras,
                $motivo,
                $anexo
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            ausencias_go( 'success', 'Pedido registado com sucesso. Ficou pendente de aprovação.');
        } catch (RuntimeException $e) {
            ausencias_go( 'danger', $e->getMessage());
        } catch (mysqli_sql_exception $e) {
            ausencias_go( 'danger', 'Não foi possível registar o pedido.');
        }
    }

    if ($acao === 'aprovar' || $acao === 'recusar') {
        $feriasAviso = '';
        if (!$temPermissaoAprovar) {
            ausencias_go( 'danger', 'Não tem permissão para aprovar ou recusar pedidos.');
        }

        $pedidoId = (int) ($_POST['id'] ?? 0);
        $novoEstado = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
        $observacoes = trim($_POST['observacoes_aprovacao'] ?? '');

        if ($pedidoId <= 0) {
            ausencias_go( 'danger', 'Pedido inválido.');
        }

        if ($acao === 'aprovar') {
            $stmt = mysqli_prepare($conn, 'SELECT pa.ficheiro_justificativo, ta.exige_justificativo FROM pedidos_ausencia pa INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id WHERE pa.id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $pedidoId);
            mysqli_stmt_execute($stmt);
            $pedidoValidar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($pedidoValidar && (int) ($pedidoValidar['exige_justificativo'] ?? 0) === 1 && empty($pedidoValidar['ficheiro_justificativo'])) {
                ausencias_go('danger', 'Anexe o documento justificativo antes de aprovar este pedido.');
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE pedidos_ausencia
            SET estado = ?, aprovado_por = ?, aprovado_at = NOW(), observacoes_aprovacao = ?
            WHERE id = ? AND estado = 'pendente'");
        mysqli_stmt_bind_param($stmt, 'sisi', $novoEstado, $utilizadorAutenticadoId, $observacoes, $pedidoId);
        mysqli_stmt_execute($stmt);
        $linhasAfetadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($linhasAfetadas < 1) {
            ausencias_go( 'danger', 'O pedido já foi tratado ou não existe.');
        }

        if ($acao === 'aprovar' && fe_table_exists($conn, 'ferias_ausencias')) {
            $feriasAviso = ausencias_sincronizar_ferias_de_pedido($conn, $pedidoId, $utilizadorAutenticadoId) ?? '';
        }

        $msgSucesso = $acao === 'aprovar' ? 'Pedido aprovado com sucesso.' : 'Pedido recusado com sucesso.';
        if (!empty($feriasAviso)) {
            $msgSucesso .= $feriasAviso;
        }
        ausencias_go($acao === 'aprovar' && !empty($feriasAviso) ? 'warning' : 'success', $msgSucesso);
    }

    if ($acao === 'registar_justificacao') {
        if (!$temPermissaoAprovar) {
            ausencias_go('danger', 'Não tem permissão para registar justificações.');
        }

        $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
        $dataInicio = trim($_POST['data_inicio'] ?? '');
        $dataFim = trim($_POST['data_fim'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($funcionarioId <= 0 || $dataInicio === '' || $dataFim === '' || $motivo === '') {
            ausencias_go('danger', 'Preencha funcionário, datas e motivo.');
        }

        if ($dataFim < $dataInicio) {
            ausencias_go('danger', 'A data fim não pode ser anterior à data início.');
        }

        $tipoAusenciaId = ausencias_tipo_id_por_slug($conn, 'falta-justificada');
        if (!$tipoAusenciaId) {
            ausencias_go('danger', 'Tipo de ausência "Falta Justificada" não encontrado.');
        }

        try {
            $anexo = guardar_anexo_seguro($_FILES['anexo'] ?? null);
            if ($anexo === null) {
                ausencias_go('danger', 'Anexe o documento justificativo (PDF ou imagem).');
            }

            $impacto = ausencias_calcular_impacto_escala($conn, $funcionarioId, $dataInicio, $dataFim, 'corridos');
            if ($impacto['dias'] <= 0) {
                $diasCalc = (int) floor((strtotime($dataFim) - strtotime($dataInicio)) / 86400) + 1;
                $impacto = ['dias' => (float) max(1, $diasCalc), 'horas' => 0.0];
            }
            $totalDiasReg = $impacto['dias'];
            $totalHorasReg = $impacto['horas'];

            $stmt = mysqli_prepare($conn, "INSERT INTO pedidos_ausencia
                (funcionario_id, utilizador_id, tipo_ausencia_id, data_inicio, data_fim, total_dias, total_horas, motivo, ficheiro_justificativo, estado, aprovado_por, aprovado_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprovado', ?, NOW())");
            mysqli_stmt_bind_param(
                $stmt,
                'iiissddssi',
                $funcionarioId,
                $utilizadorAutenticadoId,
                $tipoAusenciaId,
                $dataInicio,
                $dataFim,
                $totalDiasReg,
                $totalHorasReg,
                $motivo,
                $anexo,
                $utilizadorAutenticadoId
            );
            mysqli_stmt_execute($stmt);
            $pedidoId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $feriasAviso = $pedidoId > 0 ? ausencias_sincronizar_ferias_de_pedido($conn, $pedidoId, $utilizadorAutenticadoId) : null;
            $msg = 'Justificação registada e aprovada com sucesso.';
            if ($feriasAviso) {
                $msg .= $feriasAviso;
            }
            ausencias_go($feriasAviso ? 'warning' : 'success', $msg);
        } catch (RuntimeException $e) {
            ausencias_go('danger', $e->getMessage());
        } catch (mysqli_sql_exception $e) {
            ausencias_go('danger', 'Não foi possível registar a justificação.');
        }
    }

    if ($acao === 'anexar_justificativo') {
        $pedidoId = (int) ($_POST['id'] ?? 0);
        if ($pedidoId <= 0) {
            ausencias_go('danger', 'Pedido inválido.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT pa.*, ta.exige_justificativo FROM pedidos_ausencia pa INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id WHERE pa.id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $pedidoId);
        mysqli_stmt_execute($stmt);
        $pedidoAnexo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$pedidoAnexo) {
            ausencias_go('danger', 'Pedido não encontrado.');
        }

        $podeAnexar = $temPermissaoAprovar
            || ((int) $pedidoAnexo['utilizador_id'] === $utilizadorAutenticadoId && $pedidoAnexo['estado'] === 'pendente');

        if (!$podeAnexar) {
            ausencias_go('danger', 'Não tem permissão para anexar documento a este pedido.');
        }

        try {
            $anexo = guardar_anexo_seguro($_FILES['anexo'] ?? null);
            if ($anexo === null) {
                ausencias_go('danger', 'Selecione o documento (PDF ou imagem).');
            }

            $stmt = mysqli_prepare($conn, 'UPDATE pedidos_ausencia SET ficheiro_justificativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $anexo, $pedidoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if (fe_table_exists($conn, 'ferias_ausencias')) {
                $stmt = mysqli_prepare($conn, 'UPDATE ferias_ausencias SET ficheiro_justificativo = ? WHERE pedido_ausencia_id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $anexo, $pedidoId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            ausencias_go('success', 'Documento justificativo guardado com sucesso.');
        } catch (RuntimeException $e) {
            ausencias_go('danger', $e->getMessage());
        }
    }
}

$tiposAusencia = [];
$stmt = mysqli_prepare($conn, "SELECT id, nome, slug
    FROM tipos_ausencia
    WHERE ativo = 1 AND slug IN ('ferias', 'falta-justificada', 'baixa-medica', 'folga')
    ORDER BY FIELD(slug, 'ferias', 'falta-justificada', 'baixa-medica', 'folga')");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $tiposAusencia[] = $row;
}
mysqli_stmt_close($stmt);

$pedidos = [];

if ($utilizadorAutenticado || $temPermissaoAprovar) {
    $filtroPeriodo = '';
    $paramsFiltro = [];
    $typesFiltro = '';
    if ($mesFiltro >= 1 && $mesFiltro <= 12) {
        [$inicioMes, $fimMes] = ausencias_periodo_mes($anoFiltro, $mesFiltro);
        $filtroPeriodo = ' AND pa.data_inicio <= ? AND pa.data_fim >= ?';
        $paramsFiltro = [$fimMes, $inicioMes];
        $typesFiltro = 'ss';
    }

    if ($temPermissaoAprovar) {
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, ta.exige_justificativo, ta.slug AS tipo_slug,
                   u.nome AS utilizador_nome, aprovador.nome AS aprovador_nome,
                   COALESCE(f.nome, u.nome) AS colaborador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            LEFT JOIN funcionarios f ON f.id = pa.funcionario_id
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            WHERE 1=1{$filtroPeriodo}
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, ta.exige_justificativo, ta.slug AS tipo_slug,
                   u.nome AS utilizador_nome, aprovador.nome AS aprovador_nome,
                   COALESCE(f.nome, u.nome) AS colaborador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            LEFT JOIN funcionarios f ON f.id = pa.funcionario_id
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            WHERE pa.utilizador_id = ?{$filtroPeriodo}
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        $typesFiltro = 'i' . $typesFiltro;
        array_unshift($paramsFiltro, $utilizadorAutenticadoId);
    }

    if ($paramsFiltro !== []) {
        mysqli_stmt_bind_param($stmt, $typesFiltro, ...$paramsFiltro);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $pedidos[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$totalPedidos = count($pedidos);
$totalPendentes = 0;
$totalAprovados = 0;
$totalRecusados = 0;
$totalSemDocumento = 0;
foreach ($pedidos as $pedidoResumo) {
    switch ($pedidoResumo['estado']) {
        case 'pendente':
            $totalPendentes++;
            break;
        case 'aprovado':
            $totalAprovados++;
            break;
        case 'rejeitado':
            $totalRecusados++;
            break;
    }
    $docEstado = ausencias_estado_documento($pedidoResumo);
    if (in_array($docEstado, ['aguarda_documento', 'sem_documento'], true)) {
        $totalSemDocumento++;
    }
}

$funcionariosAtivos = $temPermissaoAprovar ? ausencias_listar_funcionarios_ativos($conn) : [];
$faltasSemJustificacao = $temPermissaoAprovar ? ausencias_listar_faltas_sem_justificacao($conn, 90) : [];
$tipoFaltaJustificadaId = ausencias_tipo_id_por_slug($conn, 'falta-justificada');

$pageTitle = 'Ausências';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Ausências</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>">Ausências</a>
                            </li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="card mb-3">
                        <div class="card-body py-3">
                            <form method="get" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="row g-2 align-items-end" id="formFiltroAusencias">
                                <div class="col-md-3">
                                    <label class="form-label mb-1">Mês (só meses já passados)</label>
                                    <select name="mes" class="form-control js-ausencias-filtro">
                                        <option value="0" <?php echo $mesFiltro === 0 ? 'selected' : ''; ?>>Todos os meses</option>
                                        <?php foreach ($mesesPassados as $item): ?>
                                            <option value="<?php echo (int) $item['mes']; ?>" <?php echo $mesFiltro === (int) $item['mes'] ? 'selected' : ''; ?>>
                                                <?php echo e(month_name((int) $item['mes'])); ?> <?php echo (int) $item['ano']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1">Ano</label>
                                    <input type="number" name="ano" class="form-control js-ausencias-filtro" min="2000" max="2100" value="<?php echo (int) $anoFiltro; ?>">
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mb-3">
                        <a href="<?php echo htmlspecialchars(admin_url('relatorio_ausencias')); ?>" class="btn btn-outline-primary">
                            <i class="fe fe-file-text"></i> Relatório de faltas por período
                        </a>
                    </div>

                    <div class="row aus-stats">
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-total">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-inbox"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalPedidos; ?></div>
                                        <div class="stat-label"><?php echo $temPermissaoAprovar ? 'Total de pedidos' : 'Os meus pedidos'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-pendente">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-clock"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalPendentes; ?></div>
                                        <div class="stat-label">Pendentes</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-aprovado">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-check-circle"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalAprovados; ?></div>
                                        <div class="stat-label">Aprovados</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-recusado">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-x-circle"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalRecusados; ?></div>
                                        <div class="stat-label">Recusados</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($temPermissaoAprovar): ?>
                    <div class="card mb-4 aus-faltas-card">
                        <div class="card-header d-flex align-items-center">
                            <h4 class="card-title mb-0">Faltas por justificar</h4>
                            <a href="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>" class="btn btn-sm btn-outline-secondary ml-2">Escala Mensal</a>
                            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#modalRegistarJustificacao">
                                <i class="fe fe-file-plus mr-1"></i> Registar justificação
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($faltasSemJustificacao)): ?>
                                <p class="mb-0">Sem faltas registadas nos últimos 90 dias que careçam de documento.</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Data da falta</th>
                                            <th style="width: 160px">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faltasSemJustificacao as $falta): ?>
                                            <tr>
                                                <td class="font-weight-bold"><?php echo e($falta['funcionario_nome']); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($falta['data']))); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary js-preencher-justificacao"
                                                        data-funcionario-id="<?php echo (int) $falta['funcionario_id']; ?>"
                                                        data-data="<?php echo e($falta['data']); ?>">
                                                        Registar justificação
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <span class="aus-header-icon mr-2"><i class="fe fe-calendar"></i></span>
                                <h4 class="card-title mb-0">
                                    Histórico de pedidos
                                    <?php if ($temPermissaoAprovar): ?>
                                        <span class="badge badge-primary ml-2">Aprovação</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="ml-auto d-flex flex-wrap">
                                    <?php if ($temPermissaoAprovar): ?>
                                        <button type="button" class="btn btn-outline-primary btn-round mr-2 mb-2 mb-md-0" data-toggle="modal" data-target="#modalRegistarJustificacao">
                                            <i class="fe fe-file-plus mr-1"></i>
                                            Registar justificação
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-round mb-2 mb-md-0" data-toggle="modal" data-target="#modalPedirAusencia" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                        <i class="fe fe-plus mr-1"></i>
                                        Novo pedido
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pedidos)): ?>
                                <div class="aus-empty text-center">
                                    <div class="aus-empty-icon"><i class="fe fe-calendar"></i></div>
                                    <?php if ($mesFiltro > 0): ?>
                                        <h5 class="aus-empty-title mb-3">Nenhum pedido neste período</h5>
                                    <?php else: ?>
                                        <h5 class="aus-empty-title mb-3">Ainda não existem pedidos</h5>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-round" data-toggle="modal" data-target="#modalPedirAusencia" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                        <i class="fe fe-plus mr-1"></i>
                                        Novo pedido
                                    </button>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tabela-ausencias" class="display table table-hover aus-table">
                                    <thead>
                                        <tr>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th>Colaborador</th>
                                            <?php endif; ?>
                                            <th>Tipo</th>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th>Dias</th>
                                            <th>Horas (escala)</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                            <th>Justificar</th>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th style="width: 150px">Ações</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido):
                                            $horasPedido = isset($pedido['total_horas']) && $pedido['total_horas'] !== null
                                                ? (float) $pedido['total_horas']
                                                : ausencias_calcular_impacto_escala(
                                                    $conn,
                                                    $pedido['funcionario_id'] ? (int) $pedido['funcionario_id'] : null,
                                                    $pedido['data_inicio'],
                                                    $pedido['data_fim']
                                                )['horas'];
                                            $docEstado = ausencias_estado_documento($pedido);
                                            ?>
                                            <tr>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td class="font-weight-bold"><?php echo e($pedido['colaborador_nome'] ?? $pedido['utilizador_nome']); ?></td>
                                                <?php endif; ?>
                                                <td><span class="badge badge-outline badge-info"><?php echo e($pedido['tipo_nome']); ?></span></td>
                                                <td class="text-nowrap" data-order="<?php echo e($pedido['data_inicio']); ?>"><i class="fe fe-calendar fe-12 mr-1 text-muted"></i><?php echo e(date('d/m/Y', strtotime($pedido['data_inicio']))); ?></td>
                                                <td class="text-nowrap" data-order="<?php echo e($pedido['data_fim']); ?>"><i class="fe fe-calendar fe-12 mr-1 text-muted"></i><?php echo e(date('d/m/Y', strtotime($pedido['data_fim']))); ?></td>
                                                <td><span class="aus-count-pill"><?php echo e(ausencias_formatar_dias((float) $pedido['total_dias'])); ?></span></td>
                                                <td class="text-nowrap"><?php echo e(ausencias_formatar_horas($horasPedido)); ?></td>
                                                <td class="small text-muted" style="max-width: 220px"><?php echo e($pedido['motivo'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(estado_badge($pedido['estado'])); ?>">
                                                        <?php echo e(estado_label($pedido['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td class="aus-justificar-cell">
                                                    <div class="d-flex flex-wrap align-items-center">
                                                        <span class="badge badge-<?php echo e(ausencias_badge_documento($docEstado)); ?> mr-1 mb-1">
                                                            <?php echo e(ausencias_label_documento($docEstado)); ?>
                                                        </span>
                                                        <?php if (!empty($pedido['ficheiro_justificativo'])): ?>
                                                            <a href="<?php echo e(admin_asset($pedido['ficheiro_justificativo'])); ?>" target="_blank" class="btn btn-sm btn-outline-success aus-anexo-btn mb-1">
                                                                <i class="fe fe-eye fe-12"></i> Ver
                                                            </a>
                                                        <?php elseif (in_array($docEstado, ['aguarda_documento', 'sem_documento'], true)): ?>
                                                            <button type="button" class="btn btn-sm btn-warning aus-anexo-btn mb-1" data-toggle="modal" data-target="#modalAnexarPedido<?php echo (int) $pedido['id']; ?>">
                                                                <i class="fe fe-file-plus fe-12"></i> Justificar
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td>
                                                        <?php if ($pedido['estado'] === 'pendente'): ?>
                                                            <div class="form-button-action">
                                                                <button type="button" class="btn btn-link btn-success btn-lg" data-toggle="modal" data-target="#modalAprovarPedido<?php echo (int) $pedido['id']; ?>" title="Aprovar">
                                                                    <span class="fe fe-check"></span>
                                                                </button>
                                                                <button type="button" class="btn btn-link btn-danger" data-toggle="modal" data-target="#modalRecusarPedido<?php echo (int) $pedido['id']; ?>" title="Recusar">
                                                                    <span class="fe fe-x"></span>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tratado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

    <div class="modal fade" id="modalPedirAusencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="pedir">
                <input type="hidden" name="ano" value="<?php echo (int) $anoFiltro; ?>">
                <input type="hidden" name="mes" value="<?php echo (int) $mesFiltro; ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Novo pedido de ausência</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <?php if ($temPermissaoAprovar): ?>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Funcionário *</label>
                            <select name="funcionario_id" class="form-control" required>
                                <option value="">Selecionar funcionário</option>
                                <?php foreach ($funcionariosAtivos as $func): ?>
                                    <option value="<?php echo (int) $func['id']; ?>">
                                        <?php echo e($func['nome']); ?><?php echo $func['numero_mecanografico'] ? ' (' . e($func['numero_mecanografico']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo_ausencia_id" class="form-control" required>
                                <option value="">Selecionar tipo</option>
                                <?php foreach ($tiposAusencia as $tipo): ?>
                                    <option value="<?php echo (int) $tipo['id']; ?>"><?php echo e($tipo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de ausência.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data início *</label>
                            <input type="date" name="data_inicio" class="form-control" lang="pt-PT" required>
                            <div class="invalid-feedback">Indique a data início.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data fim *</label>
                            <input type="date" name="data_fim" class="form-control" lang="pt-PT" required>
                            <div class="invalid-feedback">Indique a data fim.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Justificação / motivo *</label>
                            <textarea name="motivo" class="form-control" rows="4" required placeholder="Descreva o motivo da ausência"></textarea>
                            <div class="invalid-feedback">Indique o motivo.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <?php
                            upload_field([
                                'id' => 'ausenciaAnexo',
                                'name' => 'anexo',
                                'accept' => '.pdf,.jpg,.jpeg,.png',
                                'label' => 'Anexo',
                                'variant' => 'inline',
                                'button_text' => 'Escolher ficheiro',
                                'hint' => 'PDF, JPG ou PNG até 5 MB. Obrigatório para falta justificada e baixa médica.',
                            ]);
                            ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Submeter pedido</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($temPermissaoAprovar): ?>
    <div class="modal fade" id="modalRegistarJustificacao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="registar_justificacao">
                <input type="hidden" name="ano" value="<?php echo (int) $anoFiltro; ?>">
                <input type="hidden" name="mes" value="<?php echo (int) $mesFiltro; ?>">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Registar justificação de falta</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Funcionário *</label>
                            <select name="funcionario_id" id="justificacaoFuncionarioId" class="form-control" required>
                                <option value="">Selecionar funcionário</option>
                                <?php foreach ($funcionariosAtivos as $func): ?>
                                    <option value="<?php echo (int) $func['id']; ?>">
                                        <?php echo e($func['nome']); ?><?php echo $func['numero_mecanografico'] ? ' (' . e($func['numero_mecanografico']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data início *</label>
                            <input type="date" name="data_inicio" id="justificacaoDataInicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data fim *</label>
                            <input type="date" name="data_fim" id="justificacaoDataFim" class="form-control" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Motivo / notas *</label>
                            <textarea name="motivo" class="form-control" rows="3" required placeholder="Ex.: Atestado médico entregue na receção"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <?php
                            upload_field([
                                'id' => 'justificacaoAnexo',
                                'name' => 'anexo',
                                'accept' => '.pdf,.jpg,.jpeg,.png',
                                'label' => 'Ficheiro justificativo *',
                                'variant' => 'document',
                                'required' => true,
                                'button_text' => 'Escolher PDF ou imagem',
                                'zone_title' => 'Anexe o papel entregue pelo funcionário',
                                'hint' => 'PDF, JPG ou PNG até 5 MB.',
                            ]);
                            ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar justificação</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($temPermissaoAprovar): ?>
        <?php foreach ($pedidos as $pedido): ?>
            <?php if ($pedido['estado'] !== 'pendente') {
                continue;
            } ?>
            <div class="modal fade" id="modalAprovarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="modal-content">
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $anoFiltro; ?>">
                        <input type="hidden" name="mes" value="<?php echo (int) $mesFiltro; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Aprovar pedido</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar aprovação do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-success">Aprovar</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalRecusarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="modal-content">
                        <input type="hidden" name="acao" value="recusar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $anoFiltro; ?>">
                        <input type="hidden" name="mes" value="<?php echo (int) $mesFiltro; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Recusar pedido</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar recusa do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <label class="form-label">Motivo da recusa</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-danger">Recusar</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($pedidos as $pedidoAnexar):
            $docEstadoAnexar = ausencias_estado_documento($pedidoAnexar);
            if (!in_array($docEstadoAnexar, ['aguarda_documento', 'sem_documento'], true)) {
                continue;
            }
            ?>
            <div class="modal fade" id="modalAnexarPedido<?php echo (int) $pedidoAnexar['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" enctype="multipart/form-data" class="modal-content">
                        <input type="hidden" name="acao" value="anexar_justificativo">
                        <input type="hidden" name="id" value="<?php echo (int) $pedidoAnexar['id']; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $anoFiltro; ?>">
                        <input type="hidden" name="mes" value="<?php echo (int) $mesFiltro; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Justificar falta</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3"><strong><?php echo e($pedidoAnexar['colaborador_nome'] ?? $pedidoAnexar['utilizador_nome']); ?></strong> — <?php echo e(date('d/m/Y', strtotime($pedidoAnexar['data_inicio']))); ?> a <?php echo e(date('d/m/Y', strtotime($pedidoAnexar['data_fim']))); ?></p>
                            <?php
                            upload_field([
                                'id' => 'anexoPedido' . (int) $pedidoAnexar['id'],
                                'name' => 'anexo',
                                'accept' => '.pdf,.jpg,.jpeg,.png',
                                'label' => 'Ficheiro justificativo *',
                                'variant' => 'document',
                                'required' => true,
                                'button_text' => 'Escolher ficheiro',
                            ]);
                            ?>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-primary">Guardar justificação</button>
                            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php
$ordemColunaData = $temPermissaoAprovar ? 2 : 1;
$colunasSemOrdem = $temPermissaoAprovar ? '6, 7, 8, 9' : '5, 6, 7';
$pageScripts = '<style>
        /* ---- Ausências : stat cards + table polish ---- */
        .aus-stats .stat-card {
            border: none;
            border-radius: .65rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 10px rgba(20, 30, 60, .06);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .aus-stats .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(20, 30, 60, .12);
        }

        .aus-stats .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .aus-stats .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .aus-stats .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .aus-stats .stat-label {
            font-size: .76rem;
            color: #8a93a5;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-total::before { background: #1b68ff; }
        .stat-total .stat-icon { background: rgba(27, 104, 255, .14); color: #1b68ff; }
        .stat-pendente::before { background: #f0ad22; }
        .stat-pendente .stat-icon { background: rgba(240, 173, 34, .16); color: #d99312; }
        .stat-aprovado::before { background: #36b88d; }
        .stat-aprovado .stat-icon { background: rgba(54, 184, 141, .14); color: #2a9d78; }
        .stat-recusado::before { background: #f25961; }
        .stat-recusado .stat-icon { background: rgba(242, 89, 97, .14); color: #e1434b; }

        body.dark .aus-stats .stat-label { color: #9aa3b2; }

        .aus-header-icon {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(27, 104, 255, .12);
            color: #1b68ff;
        }

        .aus-table td { vertical-align: middle; }
        .aus-table .badge { font-size: .72rem; padding: .35em .6em; }
        .aus-anexo-btn { padding: .15rem .5rem; font-size: .72rem; }
        .aus-justificar-cell { min-width: 11rem; }

        .aus-count-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            padding: .2rem .5rem;
            border-radius: 999px;
            font-weight: 700;
            background: rgba(27, 104, 255, .10);
            color: #1b68ff;
        }

        .badge-outline {
            background: transparent !important;
            border: 1px solid currentColor;
        }
        .badge-outline.badge-info { color: #17a2b8; }

        /* empty state */
        .aus-empty {
            padding: 3rem 1rem;
        }
        .aus-empty-icon {
            width: 4.5rem;
            height: 4.5rem;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #1b68ff;
            background: rgba(27, 104, 255, .10);
        }
        .aus-empty-title {
            font-weight: 700;
        }
        body.dark .aus-empty-icon {
            background: rgba(72, 171, 247, .16);
            color: #6ea8ff;
        }
    </style>
    <script>
        $(document).ready(function () {
            if ($(\'#tabela-ausencias\').length) {
            $(\'#tabela-ausencias\').DataTable({
                pageLength: 10,
                order: [[' . $ordemColunaData . ', \'desc\']],
                columnDefs: [
                    { orderable: false, targets: [' . $colunasSemOrdem . '] }
                ],
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: \'Nenhum pedido encontrado\',
                    paginate: {
                        first: \'Primeiro\',
                        last: \'Último\',
                        next: \'Seguinte\',
                        previous: \'Anterior\'
                    }
                }
            });
            }

            $(\'.js-ausencias-filtro\').on(\'change\', function () {
                $(\'#formFiltroAusencias\').submit();
            });

            $(\'.js-preencher-justificacao\').on(\'click\', function () {
                var funcionarioId = $(this).data(\'funcionario-id\');
                var data = $(this).data(\'data\');
                $(\'#justificacaoFuncionarioId\').val(funcionarioId);
                $(\'#justificacaoDataInicio\').val(data);
                $(\'#justificacaoDataFim\').val(data);
                $(\'#modalRegistarJustificacao\').modal(\'show\');
            });

            $(\'.needs-validation\').on(\'submit\', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                $(this).addClass(\'was-validated\');
            });
        });
    </script>';
include __DIR__ . '/includes/layout-end.php';
