<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/horas_trabalhadas.php';
require_once __DIR__ . '/includes/horas_extra.php';
require_page_permission();

$missingTables = [];
foreach (['funcionarios', 'registos_ponto'] as $table) {
    if (!fe_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$ano = (int) ($_GET['ano'] ?? $anoAtual);
$mes = (int) ($_GET['mes'] ?? $mesAtual);
$periodo = $_GET['periodo'] ?? 'mes';

if ($ano < 2000 || $ano > 2100) {
    $ano = $anoAtual;
}

if ($mes < 1 || $mes > 12) {
    $mes = $mesAtual;
}

$dataInicio = null;
$dataFim = null;

if ($periodo !== 'total') {
    [$dataInicio, $dataFim] = ht_periodo_mensal($ano, $mes);
}

$funcionarios = empty($missingTables) ? ht_carregar_horas_trabalhadas($conn, $dataInicio, $dataFim) : [];
$totalMinutos = 0;
$totalDias = 0;

foreach ($funcionarios as $funcionario) {
    $totalMinutos += (int) $funcionario['minutos_trabalhados'];
    $totalDias += (int) $funcionario['dias_trabalhados'];
}

$pageTitle = 'Banco de Horas';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Banco de Horas</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('banco_horas')); ?>">Banco de Horas</a></li>
                        </ul>
                    </div>

                    <div class="row bh-stats">
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-funcionarios">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-users"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo count($funcionarios); ?></div>
                                        <div class="stat-label">Funcionários</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-horas">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-clock"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo e(ht_formatar_minutos($totalMinutos)); ?></div>
                                        <div class="stat-label">Horas trabalhadas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-dias">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-calendar"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalDias; ?></div>
                                        <div class="stat-label">Dias com trabalho</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3 mb-4">
                            <div class="card stat-card stat-periodo">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-bar-chart-2"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo $periodo === 'total' ? 'Total' : e(sprintf('%02d/%04d', $mes, $ano)); ?></div>
                                        <div class="stat-label">Período</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex align-items-center">
                            <span class="bh-header-icon mr-2"><i class="fe fe-sliders"></i></span>
                            <h4 class="card-title mb-0">Filtros</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" action="<?php echo htmlspecialchars(admin_url('banco_horas')); ?>" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Período</label>
                                    <select name="periodo" class="form-control">
                                        <option value="mes" <?php echo $periodo !== 'total' ? 'selected' : ''; ?>>Mês</option>
                                        <option value="total" <?php echo $periodo === 'total' ? 'selected' : ''; ?>>Total acumulado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Mês</label>
                                    <select name="mes" class="form-control">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>>
                                                <?php echo e(sprintf('%02d', $i)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa fa-filter"></i>
                                        Filtrar
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <a class="btn btn-secondary w-100" href="<?php echo htmlspecialchars(admin_url('relatorios_horas', ['ano' => $ano, 'mes' => $mes])); ?>">
                                        <i class="fa fa-file-alt"></i>
                                        Relatório mensal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4 class="card-title mb-0">Horas trabalhadas por funcionário</h4>
                            <span class="badge badge-primary ml-2 bh-period-badge"><?php echo $periodo === 'total' ? 'Total acumulado' : e(sprintf('%02d/%04d', $mes, $ano)); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-banco-horas" class="display table table-hover bh-table">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>N.º mec.</th>
                                            <th>Equipa</th>
                                            <th>Dias trabalhados</th>
                                            <th>Horas trabalhadas</th>
                                            <th>Horas extra</th>
                                            <th>Primeira picagem</th>
                                            <th>Última picagem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td class="font-weight-bold"><?php echo e($funcionario['nome']); ?></td>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <?php if ($funcionario['equipa_nome']): ?>
                                                        <span class="badge badge-outline badge-info"><?php echo e($funcionario['equipa_nome']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="bh-dias"><?php echo (int) $funcionario['dias_trabalhados']; ?></span></td>
                                                <td><span class="badge badge-primary bh-horas"><i class="fe fe-clock fe-12 mr-1"></i><?php echo e(ht_formatar_minutos($funcionario['minutos_trabalhados'])); ?></span></td>
                                                <td><span class="badge badge-warning bh-horas"><i class="fe fe-trending-up fe-12 mr-1"></i><?php echo e(ht_formatar_minutos((int) ($funcionario['minutos_extra'] ?? 0))); ?></span></td>
                                                <td class="text-nowrap"><?php echo $funcionario['primeira_picagem'] ? e(date('d/m/Y H:i', strtotime($funcionario['primeira_picagem']))) : '<span class="text-muted">-</span>'; ?></td>
                                                <td class="text-nowrap"><?php echo $funcionario['ultima_picagem'] ? e(date('d/m/Y H:i', strtotime($funcionario['ultima_picagem']))) : '<span class="text-muted">-</span>'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                
</div>
    </div>
<?php
$pageScripts = '<style>
        /* ---- Banco de Horas : stat cards + table polish ---- */
        .bh-stats .stat-card {
            border: none;
            border-radius: .65rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 10px rgba(20, 30, 60, .06);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .bh-stats .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(20, 30, 60, .12);
        }

        .bh-stats .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .bh-stats .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .bh-stats .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .bh-stats .stat-label {
            font-size: .76rem;
            color: #8a93a5;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-funcionarios::before { background: #1b68ff; }
        .stat-funcionarios .stat-icon { background: rgba(27, 104, 255, .14); color: #1b68ff; }
        .stat-horas::before { background: #36b88d; }
        .stat-horas .stat-icon { background: rgba(54, 184, 141, .14); color: #2a9d78; }
        .stat-dias::before { background: #f0ad22; }
        .stat-dias .stat-icon { background: rgba(240, 173, 34, .16); color: #d99312; }
        .stat-periodo::before { background: #6861ce; }
        .stat-periodo .stat-icon { background: rgba(104, 97, 206, .16); color: #6861ce; }

        body.dark .bh-stats .stat-label { color: #9aa3b2; }

        .bh-header-icon {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(27, 104, 255, .12);
            color: #1b68ff;
        }

        .bh-table td { vertical-align: middle; }
        .bh-table .bh-horas { font-size: .76rem; padding: .4em .7em; }
        .bh-table .bh-dias { font-weight: 600; }

        .badge-outline {
            background: transparent !important;
            border: 1px solid currentColor;
        }
        .badge-outline.badge-info { color: #17a2b8; }

        .bh-period-badge { font-weight: 600; }
    </style>
    <script>
        $(document).ready(function () {
            $(\'#tabela-banco-horas\').DataTable({
                pageLength: 10,
                order: [[4, \'desc\']],
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
        });
    </script>';
include __DIR__ . '/includes/layout-end.php';
