<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_page_permission();

function movimento_label($tipo)
{
    $labels = [
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        'inicio_pausa' => 'Início de pausa',
        'fim_pausa' => 'Fim de pausa',
    ];

    return $labels[$tipo] ?? $tipo;
}

function movimento_badge($tipo)
{
    $classes = [
        'entrada' => 'success',
        'saida' => 'danger',
        'inicio_pausa' => 'warning',
        'fim_pausa' => 'info',
    ];

    return $classes[$tipo] ?? 'secondary';
}

$missingTables = [];

foreach (['funcionarios', 'registos_ponto'] as $table) {
    if (!fe_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

$dataFiltro = trim((string) ($_REQUEST['data'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFiltro) || !DateTime::createFromFormat('Y-m-d', $dataFiltro)) {
    $dataFiltro = date('Y-m-d');
}
$funcionarioFiltroId = (int) ($_REQUEST['funcionario_id'] ?? 0);
$pontoFiltroQuery = ['data' => $dataFiltro];
if ($funcionarioFiltroId > 0) {
    $pontoFiltroQuery['funcionario_id'] = $funcionarioFiltroId;
}

$temFuncionarioRegisto = empty($missingTables) && fe_column_exists($conn, 'registos_ponto', 'funcionario_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'registar_ponto') {
    if (!$temFuncionarioRegisto) {
        admin_redirect_msg('ponto', 'danger', 'Importe o ficheiro csnsa.sql na base de dados antes de registar ponto por funcionário.');
    }

    $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $dataHora = trim($_POST['data_hora'] ?? '');
    $dataPonto = trim($_POST['data_ponto'] ?? '');
    $horaPonto = trim($_POST['hora_ponto'] ?? '');
    if ($dataHora === '' && $dataPonto !== '' && $horaPonto !== '') {
        $dataHora = $dataPonto . 'T' . $horaPonto;
    }
    $observacoes = trim($_POST['observacoes'] ?? '');
    $tiposPermitidos = ['entrada', 'saida', 'inicio_pausa', 'fim_pausa'];

    if ($funcionarioId <= 0 || !in_array($tipo, $tiposPermitidos, true) || $dataHora === '') {
        admin_redirect_msg('ponto', 'danger', 'Preencha funcionário, movimento e data/hora.', $pontoFiltroQuery);
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
    if (!$dt) {
        admin_redirect_msg('ponto', 'danger', 'Data/hora inválida.', $pontoFiltroQuery);
    }

    $dataHoraSql = $dt->format('Y-m-d H:i:s');
    $dataReferencia = $dt->format('Y-m-d');
    $observacoes = $observacoes === '' ? null : $observacoes;

    $stmt = mysqli_prepare($conn, "SELECT id FROM funcionarios WHERE id = ? AND estado = 'ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcionarioExiste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcionarioExiste) {
        admin_redirect_msg('ponto', 'danger', 'Funcionário inválido ou inativo.', $pontoFiltroQuery);
    }

    $redirectQuery = ['data' => $dataReferencia];
    if ($funcionarioId > 0) {
        $redirectQuery['funcionario_id'] = $funcionarioId;
    }

    $temDataReferencia = fe_column_exists($conn, 'registos_ponto', 'data_referencia');

    if ($temDataReferencia) {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, data_referencia, origem, estado, observacoes)
            VALUES (?, ?, ?, ?, 'manual', 'valido', ?)");
        if (!$stmt) {
            admin_redirect_msg('ponto', 'danger', 'Não foi possível preparar o registo de ponto.');
        }
        mysqli_stmt_bind_param($stmt, 'issss', $funcionarioId, $tipo, $dataHoraSql, $dataReferencia, $observacoes);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, origem, estado, observacoes)
            VALUES (?, ?, ?, 'manual', 'valido', ?)");
        if (!$stmt) {
            admin_redirect_msg('ponto', 'danger', 'Não foi possível preparar o registo de ponto.');
        }
        mysqli_stmt_bind_param($stmt, 'isss', $funcionarioId, $tipo, $dataHoraSql, $observacoes);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        admin_redirect_msg('ponto', 'danger', 'Não foi possível guardar o movimento. Verifique os dados e tente novamente.');
    }
    mysqli_stmt_close($stmt);

    admin_redirect_msg('ponto', 'success', movimento_label($tipo) . ' registada com sucesso.', $redirectQuery);
}

$funcionarios = [];
$registos = [];

if (empty($missingTables)) {
    $stmt = mysqli_prepare($conn, "SELECT id, numero_mecanografico, nome, funcao, codigo_biometrico
        FROM funcionarios
        WHERE estado = 'ativo'
        ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

if ($temFuncionarioRegisto) {
    $sqlPonto = "SELECT rp.id, rp.tipo, rp.data_hora, rp.origem, rp.estado, rp.observacoes,
               f.nome AS funcionario_nome, f.numero_mecanografico
        FROM registos_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE DATE(rp.data_hora) = ?";
    if ($funcionarioFiltroId > 0) {
        $sqlPonto .= ' AND rp.funcionario_id = ?';
    }
    $sqlPonto .= ' ORDER BY rp.data_hora DESC, rp.id DESC';
    $stmt = mysqli_prepare($conn, $sqlPonto);
    if ($funcionarioFiltroId > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $dataFiltro, $funcionarioFiltroId);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $dataFiltro);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $registos[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$totalHoje = count($registos);
$totalEntradas = 0;
$totalSaidas = 0;
$totalPausas = 0;
foreach ($registos as $registoResumo) {
    switch ($registoResumo['tipo']) {
        case 'entrada':
            $totalEntradas++;
            break;
        case 'saida':
            $totalSaidas++;
            break;
        case 'inicio_pausa':
        case 'fim_pausa':
            $totalPausas++;
            break;
    }
}

$pageTitle = 'Registos de Ponto';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Registo de Ponto</h3>
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
                                <a href="<?php echo htmlspecialchars(admin_url('ponto')); ?>">Ponto</a>
                            </li>
                        </ul>
                    </div>

                    <div class="d-flex flex-wrap mb-3">
                        <a href="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-calendar mr-1"></i> Escala Mensal</a>
                        <a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-file-text mr-1"></i> Ausências</a>
                        <a href="<?php echo htmlspecialchars(admin_url('banco_horas')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-bar-chart-2 mr-1"></i> Banco de Horas</a>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="row ponto-stats">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card stat-entrada">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-log-in"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalEntradas; ?></div>
                                        <div class="stat-label"><?php echo $dataFiltro === date('Y-m-d') ? 'Entradas hoje' : 'Entradas'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card stat-saida">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-log-out"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalSaidas; ?></div>
                                        <div class="stat-label"><?php echo $dataFiltro === date('Y-m-d') ? 'Saídas hoje' : 'Saídas'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card stat-pausa">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-coffee"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalPausas; ?></div>
                                        <div class="stat-label"><?php echo $dataFiltro === date('Y-m-d') ? 'Pausas hoje' : 'Pausas'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card stat-total">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-clock"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalHoje; ?></div>
                                        <div class="stat-label">Total de registos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card ponto-form-card">
                                <div class="card-header d-flex align-items-center">
                                    <span class="ponto-form-icon mr-2"><i class="fe fe-edit-3"></i></span>
                                    <h4 class="card-title mb-0">Correção manual</h4>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="<?php echo htmlspecialchars(admin_url('ponto')); ?>" class="needs-validation" novalidate>
                                        <input type="hidden" name="acao" value="registar_ponto">
                                        <div class="mb-3">
                                            <label class="form-label">Funcionário *</label>
                                            <select name="funcionario_id" class="form-control" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar funcionário</option>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <option value="<?php echo (int) $funcionario['id']; ?>">
                                                        <?php echo e($funcionario['nome']); ?>
                                                        <?php if ($funcionario['numero_mecanografico']): ?>
                                                            (<?php echo e($funcionario['numero_mecanografico']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Selecione um funcionário.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Movimento *</label>
                                            <select name="tipo" class="form-control" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar movimento</option>
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                                <option value="inicio_pausa">Início de pausa</option>
                                                <option value="fim_pausa">Fim de pausa</option>
                                            </select>
                                            <div class="invalid-feedback">Selecione o movimento.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Data *</label>
                                            <input type="date" name="data_ponto" class="form-control" value="<?php echo e($dataFiltro); ?>" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Hora (24h) *</label>
                                            <input type="time" name="hora_ponto" class="form-control" value="<?php echo e(date('H:i')); ?>" step="60" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                            <small class="text-muted">Formato português — 24 horas (ex.: 14:30).</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="3" <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100" <?php echo !$temFuncionarioRegisto || empty($funcionarios) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-save"></i>
                                            Guardar movimento
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex align-items-center flex-wrap">
                                    <h4 class="card-title mb-0">Registos do dia</h4>
                                    <span class="badge badge-primary ml-2 ponto-date-badge"><?php echo e(date('d/m/Y', strtotime($dataFiltro))); ?></span>
                                    <form method="get" action="<?php echo htmlspecialchars(admin_url('ponto')); ?>" class="ml-auto d-flex flex-wrap align-items-end gap-2 mt-2 mt-md-0">
                                        <div class="mr-2 mb-2 mb-md-0">
                                            <label class="form-label mb-0 small">Data</label>
                                            <input type="date" name="data" class="form-control form-control-sm" value="<?php echo e($dataFiltro); ?>">
                                        </div>
                                        <div class="mr-2 mb-2 mb-md-0">
                                            <label class="form-label mb-0 small">Funcionário</label>
                                            <select name="funcionario_id" class="form-control form-control-sm">
                                                <option value="0">Todos</option>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <option value="<?php echo (int) $funcionario['id']; ?>" <?php echo (int) $funcionario['id'] === $funcionarioFiltroId ? 'selected' : ''; ?>>
                                                        <?php echo e($funcionario['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm mb-2 mb-md-0">
                                            <i class="fa fa-filter"></i> Filtrar
                                        </button>
                                    </form>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($registos)): ?>
                                        <p class="mb-0">Nenhum registo.</p>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="tabela-ponto" class="display table table-hover ponto-table">
                                            <thead>
                                                <tr>
                                                    <th>Funcionário</th>
                                                    <th>N.º mec.</th>
                                                    <th>Hora</th>
                                                    <th>Movimento</th>
                                                    <th>Origem</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($registos as $registo): ?>
                                                    <?php
                                                    $estado = strtolower($registo['estado'] ?? '');
                                                    $estadoBadge = $estado === 'valido' ? 'success' : ($estado === 'pendente' ? 'warning' : ($estado === 'anulado' || $estado === 'invalido' ? 'danger' : 'secondary'));
                                                    $origem = strtolower($registo['origem'] ?? '');
                                                    $origemBadge = $origem === 'manual' ? 'secondary' : 'info';
                                                    ?>
                                                    <tr>
                                                        <td class="font-weight-bold"><?php echo e($registo['funcionario_nome']); ?></td>
                                                        <td><?php echo e($registo['numero_mecanografico'] ?: '-'); ?></td>
                                                        <td class="text-nowrap"><i class="fe fe-clock fe-12 mr-1 text-muted"></i><?php echo e(date('H:i', strtotime($registo['data_hora']))); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo e(movimento_badge($registo['tipo'])); ?>">
                                                                <?php echo e(movimento_label($registo['tipo'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><span class="badge badge-outline badge-<?php echo e($origemBadge); ?>"><?php echo e(ucfirst($registo['origem'])); ?></span></td>
                                                        <td><span class="badge badge-<?php echo e($estadoBadge); ?>"><?php echo e(ucfirst($registo['estado'])); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                
</div>
    </div>
<?php
$pageScripts = '<style>
        /* ---- Registos de Ponto : stat cards + table polish ---- */
        .ponto-stats .stat-card {
            border: none;
            border-radius: .65rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 10px rgba(20, 30, 60, .06);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .ponto-stats .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(20, 30, 60, .12);
        }

        .ponto-stats .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .ponto-stats .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .ponto-stats .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .ponto-stats .stat-label {
            font-size: .78rem;
            color: #8a93a5;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-entrada::before { background: #36b88d; }
        .stat-entrada .stat-icon { background: rgba(54, 184, 141, .14); color: #2a9d78; }
        .stat-saida::before { background: #f25961; }
        .stat-saida .stat-icon { background: rgba(242, 89, 97, .14); color: #e1434b; }
        .stat-pausa::before { background: #f0ad22; }
        .stat-pausa .stat-icon { background: rgba(240, 173, 34, .16); color: #d99312; }
        .stat-total::before { background: #1b68ff; }
        .stat-total .stat-icon { background: rgba(27, 104, 255, .14); color: #1b68ff; }

        body.dark .ponto-stats .stat-label { color: #9aa3b2; }

        /* form card */
        .ponto-form-icon {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(27, 104, 255, .12);
            color: #1b68ff;
        }

        /* table */
        .ponto-table td { vertical-align: middle; }
        .ponto-table .badge { font-size: .72rem; padding: .35em .6em; }

        .badge-outline {
            background: transparent !important;
            border: 1px solid currentColor;
        }
        .badge-outline.badge-secondary { color: #8a93a5; }
        .badge-outline.badge-info { color: #17a2b8; }

        .ponto-date-badge { font-weight: 600; }
    </style>
    <script>
        $(document).ready(function () {
            $(\'#tabela-ponto\').DataTable({
                pageLength: 25,
                order: [[2, \'desc\']],
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: \'Nenhum registo encontrado\',
                    paginate: {
                        first: \'Primeiro\',
                        last: \'Último\',
                        next: \'Seguinte\',
                        previous: \'Anterior\'
                    }
                }
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
