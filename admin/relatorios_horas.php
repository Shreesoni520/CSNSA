<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/horas_trabalhadas.php';
require_page_permission();

function relatorios_horas_csv($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$ano = (int) ($_GET['ano'] ?? $anoAtual);
$mes = (int) ($_GET['mes'] ?? $mesAtual);
$funcionarioId = (int) ($_GET['funcionario_id'] ?? 0);
$exportar = ($_GET['exportar'] ?? '') === 'csv';

if ($ano < 2000 || $ano > 2100) {
    $ano = $anoAtual;
}

if ($mes < 1 || $mes > 12) {
    $mes = $mesAtual;
}

[$dataInicio, $dataFim] = ht_periodo_mensal($ano, $mes);
$todosFuncionarios = ht_carregar_funcionarios($conn);
$relatorio = ht_carregar_horas_trabalhadas($conn, $dataInicio, $dataFim, $funcionarioId > 0 ? $funcionarioId : null);
$totalMinutos = 0;
$totalDias = 0;

foreach ($relatorio as $funcionario) {
    $totalMinutos += (int) $funcionario['minutos_trabalhados'];
    $totalDias += (int) $funcionario['dias_trabalhados'];
}

if ($exportar) {
    if ($funcionarioId > 0) {
        $funcionario = reset($relatorio);
        if (!$funcionario) {
            admin_redirect_msg('relatorios_horas', 'warning', 'Funcionário não encontrado ou sem picagens neste período.', [
                'ano' => $ano,
                'mes' => $mes,
            ]);
        }
        $rows = [];
            foreach ($funcionario['dias'] as $dia) {
                $rows[] = [
                    date('d/m/Y', strtotime($dia['data'])),
                    ht_formatar_minutos($dia['minutos_trabalhados']),
                    $dia['picagens'],
                    $dia['primeira_picagem'] ? date('H:i', strtotime($dia['primeira_picagem'])) : '',
                    $dia['ultima_picagem'] ? date('H:i', strtotime($dia['ultima_picagem'])) : '',
                ];
            }

        relatorios_horas_csv(
            sprintf('relatorio_horas_%04d_%02d_funcionario_%d.csv', $ano, $mes, $funcionarioId),
            ['Data', 'Horas trabalhadas', 'Picagens', 'Primeira picagem', 'Ultima picagem'],
            $rows
        );
    }

    $rows = [];
    foreach ($relatorio as $funcionario) {
        $rows[] = [
            $funcionario['numero_mecanografico'],
            $funcionario['nome'],
            $funcionario['equipa_nome'],
            $funcionario['dias_trabalhados'],
            ht_formatar_minutos($funcionario['minutos_trabalhados']),
        ];
    }

    relatorios_horas_csv(
        sprintf('relatorio_geral_horas_%04d_%02d.csv', $ano, $mes),
        ['Numero mecanografico', 'Funcionario', 'Equipa', 'Dias trabalhados', 'Horas trabalhadas'],
        $rows
    );
}

$pageTitle = 'Relatórios de Horas';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Relatórios de Horas</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('relatorios_horas')); ?>">Relatórios de Horas</a></li>
                        </ul>
                    </div>

                    <div class="card mb-4 no-print">
                        <div class="card-header d-flex align-items-center">
                            <span class="rh-header-icon mr-2"><i class="fe fe-file-text"></i></span>
                            <h4 class="card-title mb-0">Relatório mensal</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" action="<?php echo htmlspecialchars(admin_url('relatorios_horas')); ?>" class="row g-3 align-items-end">
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
                                <div class="col-md-4">
                                    <label class="form-label">Funcionário</label>
                                    <select name="funcionario_id" class="form-control">
                                        <option value="0">Relatório geral</option>
                                        <?php foreach ($todosFuncionarios as $funcionario): ?>
                                            <option value="<?php echo (int) $funcionario['id']; ?>" <?php echo (int) $funcionario['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                <?php echo e($funcionario['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fe fe-filter mr-1"></i>
                                        Ver relatório
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="exportar" value="csv" class="btn btn-secondary w-100">
                                        <i class="fe fe-download mr-1"></i>
                                        Exportar CSV
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row rh-stats">
                        <div class="col-sm-6 col-xl-4 mb-4">
                            <div class="card stat-card stat-periodo">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-calendar"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo e(sprintf('%02d/%04d', $mes, $ano)); ?></div>
                                        <div class="stat-label">Período</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4 mb-4">
                            <div class="card stat-card stat-dias">
                                <div class="card-body d-flex align-items-center">
                                    <span class="stat-icon"><i class="fe fe-briefcase"></i></span>
                                    <div class="ml-3">
                                        <div class="stat-value"><?php echo (int) $totalDias; ?></div>
                                        <div class="stat-label">Dias trabalhados</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4 mb-4">
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
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title mb-0">
                                    <?php echo $funcionarioId > 0 ? 'Relatório mensal do funcionário' : 'Relatório geral mensal'; ?>
                                </h4>
                                <span class="badge badge-primary ml-2 rh-period-badge"><?php echo e(sprintf('%02d/%04d', $mes, $ano)); ?></span>
                                <button type="button" class="btn btn-secondary btn-round ml-auto no-print rh-print-btn" onclick="window.print()">
                                    <i class="fe fe-printer mr-1"></i>
                                    Imprimir
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($funcionarioId > 0): ?>
                                <?php $funcionario = reset($relatorio); ?>
                                <?php if (!$funcionario): ?>
                                    <p class="mb-0">Sem picagens neste período.</p>
                                <?php else: ?>
                                    <div class="rh-employee-summary mb-3">
                                        <h5 class="mb-1"><?php echo e($funcionario['nome']); ?></h5>
                                        <span class="text-muted">
                                            <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número mecanográfico'); ?>
                                            <?php if ($funcionario['equipa_nome']): ?>
                                                · <?php echo e($funcionario['equipa_nome']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="table-responsive">
                                        <table id="tabela-relatorio-funcionario" class="display table table-hover rh-table">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Horas trabalhadas</th>
                                                    <th>Picagens</th>
                                                    <th>Primeira picagem</th>
                                                    <th>Última picagem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($funcionario['dias'] as $dia): ?>
                                                    <tr>
                                                        <td class="font-weight-bold"><?php echo e(date('d/m/Y', strtotime($dia['data']))); ?></td>
                                                        <td><span class="badge badge-primary rh-hours-badge"><i class="fe fe-clock fe-12 mr-1"></i><?php echo e(ht_formatar_minutos($dia['minutos_trabalhados'])); ?></span></td>
                                                        <td><span class="rh-count-pill"><?php echo (int) $dia['picagens']; ?></span></td>
                                                        <td class="text-nowrap"><?php echo $dia['primeira_picagem'] ? e(date('H:i', strtotime($dia['primeira_picagem']))) : '<span class="text-muted">-</span>'; ?></td>
                                                        <td class="text-nowrap"><?php echo $dia['ultima_picagem'] ? e(date('H:i', strtotime($dia['ultima_picagem']))) : '<span class="text-muted">-</span>'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="tabela-relatorio-geral" class="display table table-hover rh-table">
                                        <thead>
                                            <tr>
                                                <th>N.º mec.</th>
                                                <th>Funcionário</th>
                                                <th>Equipa</th>
                                                <th>Dias trabalhados</th>
                                                <th>Horas trabalhadas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($relatorio as $funcionario): ?>
                                                <tr>
                                                    <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                    <td class="font-weight-bold"><?php echo e($funcionario['nome']); ?></td>
                                                    <td>
                                                        <?php if ($funcionario['equipa_nome']): ?>
                                                            <span class="badge badge-outline badge-info"><?php echo e($funcionario['equipa_nome']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="rh-count-pill"><?php echo (int) $funcionario['dias_trabalhados']; ?></span></td>
                                                    <td><span class="badge badge-primary rh-hours-badge"><i class="fe fe-clock fe-12 mr-1"></i><?php echo e(ht_formatar_minutos($funcionario['minutos_trabalhados'])); ?></span></td>
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
<?php
$pageScripts = '<style>
        /* ---- Relatórios de Horas : stat cards + report polish ---- */
        .rh-stats .stat-card {
            border: none;
            border-radius: .65rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 10px rgba(20, 30, 60, .06);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .rh-stats .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(20, 30, 60, .12);
        }

        .rh-stats .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .rh-stats .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .rh-stats .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .rh-stats .stat-label {
            font-size: .76rem;
            color: #8a93a5;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .rh-stats .stat-periodo::before { background: #6861ce; }
        .rh-stats .stat-periodo .stat-icon { background: rgba(104, 97, 206, .16); color: #6861ce; }
        .rh-stats .stat-dias::before { background: #f0ad22; }
        .rh-stats .stat-dias .stat-icon { background: rgba(240, 173, 34, .16); color: #d99312; }
        .rh-stats .stat-horas::before { background: #36b88d; }
        .rh-stats .stat-horas .stat-icon { background: rgba(54, 184, 141, .14); color: #2a9d78; }

        body.dark .rh-stats .stat-label { color: #9aa3b2; }

        .rh-header-icon {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(27, 104, 255, .12);
            color: #1b68ff;
        }

        .rh-table td { vertical-align: middle; }
        .rh-table .rh-hours-badge { font-size: .76rem; padding: .4em .7em; }
        .rh-period-badge { font-weight: 600; }
        .rh-print-btn { border-radius: .45rem; }

        .rh-count-pill {
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

        .rh-employee-summary {
            border-left: 4px solid #1b68ff;
            padding: .75rem 1rem;
            border-radius: .5rem;
            background: rgba(27, 104, 255, .06);
        }

        @media print {
            .no-print,
            .sidebar,
            .main-header,
            footer {
                display: none !important;
            }

            .main-panel,
            .container,
            .page-inner {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    <script>
        $(document).ready(function () {
            $(\'#tabela-relatorio-geral, #tabela-relatorio-funcionario\').DataTable({
                pageLength: 31,
                order: [[0, \'asc\']],
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
