<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/dashboard_widgets.php';
require_page_permission();

$estadoFuncionarios = fe_carregar_funcionarios_estado($conn);
$funcionarios = $estadoFuncionarios['funcionarios'];
$totais = $estadoFuncionarios['totais'];
$missingTables = $estadoFuncionarios['missing_tables'];
$notificacoes = dash_notificacoes($conn);
$verificacaoPonto = dash_verificacao_ponto($conn);
$mapaPresencas = dash_mapa_presencas($conn);

$pageTitle = 'Painel de gestão';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<h2 class="h5 page-title">Presenças de Funcionários</h2>

<div class="row mb-4 inicio-stats">
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card stat-card stat-ativos h-100">
            <div class="card-body d-flex align-items-center">
                <span class="stat-icon"><i class="fe fe-users"></i></span>
                <div class="ml-3">
                    <div class="stat-value"><?php echo (int) $totais['ativos']; ?></div>
                    <div class="stat-label">Funcionários ativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card stat-card stat-trabalhar h-100">
            <div class="card-body d-flex align-items-center">
                <span class="stat-icon"><i class="fe fe-user-check"></i></span>
                <div class="ml-3">
                    <div class="stat-value"><?php echo (int) $totais['a_trabalhar']; ?></div>
                    <div class="stat-label">A trabalhar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card stat-card stat-pausa h-100">
            <div class="card-body d-flex align-items-center">
                <span class="stat-icon"><i class="fe fe-coffee"></i></span>
                <div class="ml-3">
                    <div class="stat-value"><?php echo (int) $totais['em_pausa']; ?></div>
                    <div class="stat-label">Em pausa</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-3">
        <div class="card stat-card stat-fora h-100">
            <div class="card-body d-flex align-items-center">
                <span class="stat-icon"><i class="fe fe-user-x"></i></span>
                <div class="ml-3">
                    <div class="stat-value"><?php echo (int) $totais['nao_trabalhar']; ?></div>
                    <div class="stat-label">Não a trabalhar</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 dash-widgets">
    <div class="col-lg-4 mb-3">
        <div class="card dash-widget h-100" tabindex="0">
            <div class="card-body">
                <div class="dash-widget-compact">
                    <span class="dash-widget-icon text-primary"><i class="fe fe-gift"></i></span>
                    <div>
                        <h6 class="mb-0">Notificações</h6>
                        <div class="dash-widget-value"><?php echo count($notificacoes); ?></div>
                    </div>
                </div>
                <div class="dash-widget-expanded">
                    <h6 class="mb-2">Aniversários e diuturnidades</h6>
                    <?php if (!empty($notificacoes)): ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach (array_slice($notificacoes, 0, 8) as $item): ?>
                                <li class="mb-1">
                                    <span class="badge badge-<?php echo $item['tipo'] === 'aniversario' ? 'info' : 'success'; ?> mr-1">
                                        <?php echo $item['tipo'] === 'aniversario' ? 'Aniv.' : 'Diut.'; ?>
                                    </span>
                                    <?php echo e($item['nome']); ?> — <?php echo e($item['data']); ?>
                                    <?php if ((int) $item['dias'] === 0): ?>
                                        <strong>(hoje)</strong>
                                    <?php else: ?>
                                        (em <?php echo (int) $item['dias']; ?> dias)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card dash-widget h-100" tabindex="0">
            <div class="card-body">
                <div class="dash-widget-compact">
                    <span class="dash-widget-icon text-warning"><i class="fe fe-alert-circle"></i></span>
                    <div>
                        <h6 class="mb-0">Verificação de ponto</h6>
                        <div class="dash-widget-value"><?php echo count($verificacaoPonto); ?></div>
                    </div>
                </div>
                <div class="dash-widget-expanded">
                    <h6 class="mb-2">Situações a rever hoje</h6>
                    <?php if (!empty($verificacaoPonto)): ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach (array_slice($verificacaoPonto, 0, 8) as $item): ?>
                                <li class="mb-1"><?php echo e($item['nome']); ?> — <?php echo e($item['estado']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card dash-widget h-100" tabindex="0">
            <div class="card-body">
                <div class="dash-widget-compact">
                    <span class="dash-widget-icon text-success"><i class="fe fe-map-pin"></i></span>
                    <div>
                        <h6 class="mb-0">Mapa de presenças</h6>
                        <div class="dash-widget-value"><?php echo (int) $mapaPresencas['presentes']; ?>/<?php echo (int) $mapaPresencas['total']; ?></div>
                    </div>
                </div>
                <div class="dash-widget-expanded">
                    <h6 class="mb-2">Estado da equipa hoje</h6>
                    <div class="d-flex justify-content-between small mb-1"><span>Presentes</span><strong><?php echo (int) $mapaPresencas['presentes']; ?></strong></div>
                    <div class="d-flex justify-content-between small mb-1"><span>Em pausa</span><strong><?php echo (int) $mapaPresencas['pausa']; ?></strong></div>
                    <div class="d-flex justify-content-between small"><span>Ausentes / fora</span><strong><?php echo (int) $mapaPresencas['ausentes']; ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 inicio-table-card">
    <div class="card-header d-flex align-items-center">
        <span class="inicio-table-icon mr-2"><i class="fe fe-activity"></i></span>
        <strong class="mb-0">Estado dos funcionários hoje</strong>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-funcionarios-estado" class="table table-hover">
                <thead>
                    <tr>
                        <th>N.º mec.</th>
                        <th>Funcionário</th>
                        <th>Equipa</th>
                        <th>Último movimento</th>
                        <th>Hora</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $funcionario): ?>
                    <tr>
                        <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                        <td>
                            <strong><?php echo e($funcionario['nome']); ?></strong><br>
                            <small class="text-muted"><?php echo e($funcionario['funcao'] ?: 'Sem função definida'); ?></small>
                        </td>
                        <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                        <td><?php echo e(fe_movimento_label($funcionario['ultimo_tipo'])); ?></td>
                        <td><?php echo $funcionario['ultimo_data_hora'] ? e(date('H:i', strtotime($funcionario['ultimo_data_hora']))) : '-'; ?></td>
                        <td>
                            <span class="badge badge-<?php echo e(fe_estado_badge($funcionario['estado_trabalho'])); ?>">
                                <?php echo e(fe_estado_label($funcionario['estado_trabalho'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$pageScripts = <<<'HTML'
<style>
    .inicio-stats .stat-card {
        border: none;
        border-radius: .75rem;
        overflow: hidden;
        position: relative;
        box-shadow: 0 2px 12px rgba(20, 30, 60, .07);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .inicio-stats .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(20, 30, 60, .12);
    }

    .inicio-stats .stat-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }

    .inicio-stats .stat-icon {
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex: 0 0 auto;
    }

    .inicio-stats .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.1;
        color: #1a2035;
    }

    .inicio-stats .stat-label {
        font-size: .8rem;
        color: #6b7280;
        font-weight: 500;
        margin-top: .15rem;
        line-height: 1.3;
    }

    .stat-ativos::before { background: #1b68ff; }
    .stat-ativos .stat-icon { background: rgba(27, 104, 255, .12); color: #1b68ff; }

    .stat-trabalhar::before { background: #36b88d; }
    .stat-trabalhar .stat-icon { background: rgba(54, 184, 141, .14); color: #2a9d78; }

    .stat-pausa::before { background: #f0ad22; }
    .stat-pausa .stat-icon { background: rgba(240, 173, 34, .16); color: #d99312; }

    .stat-fora::before { background: #8a93a5; }
    .stat-fora .stat-icon { background: rgba(138, 147, 165, .16); color: #6b7280; }

    body.dark .inicio-stats .stat-value { color: #f3f4f6; }
    body.dark .inicio-stats .stat-label { color: #9aa3b2; }

    .inicio-table-card {
        border: none;
        border-radius: .75rem;
        box-shadow: 0 2px 12px rgba(20, 30, 60, .07);
    }

    .inicio-table-icon {
        width: 2rem;
        height: 2rem;
        border-radius: .5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(27, 104, 255, .12);
        color: #1b68ff;
    }

    .dash-widgets .dash-widget {
        border: none;
        border-radius: .75rem;
        box-shadow: 0 2px 12px rgba(20, 30, 60, .07);
        transition: transform .2s ease, box-shadow .2s ease;
        overflow: hidden;
    }

    .dash-widgets .dash-widget:hover,
    .dash-widgets .dash-widget:focus-within {
        transform: scale(1.02);
        box-shadow: 0 10px 28px rgba(20, 30, 60, .14);
        z-index: 2;
    }

    .dash-widget-compact {
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .dash-widget-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        background: rgba(27, 104, 255, .1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .dash-widget-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .dash-widget-expanded {
        display: none;
        margin-top: 1rem;
        padding-top: .75rem;
        border-top: 1px solid rgba(0,0,0,.06);
    }

    .dash-widgets .dash-widget:hover .dash-widget-expanded,
    .dash-widgets .dash-widget:focus-within .dash-widget-expanded {
        display: block;
    }
</style>
<script>
$(document).ready(function () {
    $('#tabela-funcionarios-estado').DataTable({
        pageLength: 25,
        language: {
            search: 'Pesquisar:',
            lengthMenu: 'Mostrar _MENU_ registos',
            info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
            infoEmpty: 'Sem registos',
            zeroRecords: 'Nenhum funcionário encontrado',
            paginate: { first: 'Primeiro', last: 'Último', next: 'Seguinte', previous: 'Anterior' }
        }
    });
});
</script>
HTML;
include __DIR__ . '/includes/layout-end.php';
