<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/funcoes/escala_mensal_funcoes.php';
require_once __DIR__ . '/includes/upload_field.php';
require_page_permission();

if (isset($_GET['exemplo_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="escala_import_exemplo.csv"');
    $exFuncionarioId = (int) ($_GET['funcionario_id'] ?? 0);
    if ($exFuncionarioId > 0) {
        echo "dia,turno_codigo,tipo_dia\n";
        echo "1,,turno\n";
        echo "2,,folga\n";
        echo "3,,turno\n";
    } else {
        echo "numero_mecanografico,dia,turno_codigo,tipo_dia\n";
        echo "1001,1,M,turno\n";
        echo "1001,2,,folga\n";
        echo "1001,3,T,turno\n";
    }
    exit;
}

$contexto = escala_mensal_contexto_request();
$ano = $contexto['ano'];
$mes = $contexto['mes'];
$equipaId = $contexto['equipa_id'];
$funcionarioId = $contexto['funcionario_id'];
$diasNoMes = $contexto['dias_no_mes'];
$baseParams = escala_mensal_base_params($contexto);
$tiposDia = escala_mensal_tipos_dia();
$missingTables = escala_mensal_tabelas_em_falta($conn);

escala_mensal_processar_post($conn, $contexto, $missingTables);

$dadosEscala = escala_mensal_carregar_dados($conn, $contexto, $missingTables);
$equipas = $dadosEscala['equipas'];
$turnos = $dadosEscala['turnos'];
$funcionariosFiltro = $dadosEscala['funcionarios_filtro'];
$funcionariosSubstituicao = $dadosEscala['funcionarios_substituicao'];
$escalaGuardada = $dadosEscala['escala_guardada'];
$funcionarioFiltroInvalido = !empty($dadosEscala['funcionario_filtro_invalido']);

$vistaModo = $_GET['vista'] ?? ($funcionarioId > 0 ? 'simples' : 'grelha');
$vistaSimples = $funcionarioId > 0 && $vistaModo === 'simples';

$funcionariosTodos = $funcionariosFiltro;
$resumoEscala = escala_mensal_resumo_periodo($escalaGuardada, $funcionariosTodos, true);

if ($vistaSimples && $funcionarioId > 0) {
    $funcionarios = array_values(array_filter(
        $funcionariosTodos,
        static fn ($f) => (int) $f['id'] === $funcionarioId
    ));
    $funcionarioFiltroInvalido = $funcionarios === [];
} else {
    $funcionarios = $funcionariosTodos;
}

$diasGuardadosFuncionario = escala_mensal_contar_dias_guardados($escalaGuardada, $funcionarioId);
$logImportacao = escala_mensal_obter_log_importacao($ano, $mes);
$detalheImportado = escala_mensal_listar_detalhe_importado($conn, $ano, $mes, $equipaId, $funcionarioId);
$mostrarDetalheImportado = isset($_GET['ver']) && $_GET['ver'] === 'importado';
$turnosEmFalta = escala_mensal_contar_turnos_em_falta($escalaGuardada, $funcionarioId);
$funcionarioEdicaoNome = '';
if ($funcionarioId > 0) {
    foreach ($funcionariosFiltro as $funcionarioOpcao) {
        if ((int) $funcionarioOpcao['id'] === $funcionarioId) {
            $funcionarioEdicaoNome = (string) $funcionarioOpcao['nome'];
            break;
        }
    }
}
$expandEquipaCard = $funcionarioId > 0 || $resumoEscala['total_registos'] > 0;
$expandPreencherCard = true;
$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Escala Mensal';
$useDataTables = false;
$extraHead = '<link rel="stylesheet" href="css/csnsa-escala.css">';
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Escala Mensal</h3>
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
                                <a href="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>">Escala Mensal</a>
                            </li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="d-flex flex-wrap mb-3 escala-quick-links">
                        <a href="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-clock mr-1"></i> Turnos</a>
                        <a href="<?php echo htmlspecialchars(admin_url('ponto')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-check-square mr-1"></i> Registo de Ponto</a>
                        <a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-calendar mr-1"></i> Ausências</a>
                        <a href="<?php echo htmlspecialchars(admin_url('banco_horas', ['mes' => $mes, 'ano' => $ano])); ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-2"><i class="fe fe-bar-chart-2 mr-1"></i> Banco de Horas</a>
                    </div>

                    <?php if (!empty($funcionariosFiltro)): ?>
                    <div class="card mb-4 escala-resumo-card escala-panel-card">
                        <div class="card-header escala-collapse-header d-flex align-items-center flex-wrap" data-toggle="collapse" data-target="#escalaEquipaCollapse" aria-expanded="<?php echo $expandEquipaCard ? 'true' : 'false'; ?>">
                            <h4 class="card-title mb-0">Equipa — <?php echo e(month_name($mes)); ?> <?php echo (int) $ano; ?></h4>
                            <?php if ($resumoEscala['total_registos'] > 0): ?>
                                <span class="escala-count-badge ml-2"><?php echo (int) $resumoEscala['total_registos']; ?> dia(s) planeados</span>
                            <?php endif; ?>
                            <span class="escala-count-badge ml-1"><?php echo count($funcionariosFiltro); ?> funcionário(s)</span>
                            <i class="fe fe-chevron-down escala-collapse-chevron ml-auto" aria-hidden="true"></i>
                        </div>
                        <div id="escalaEquipaCollapse" class="collapse<?php echo $expandEquipaCard ? ' show' : ''; ?>">
                        <div class="card-body">
                            <?php if (!empty($resumoEscala['por_tipo'])): ?>
                            <div class="row mb-3">
                                <?php foreach ($resumoEscala['por_tipo'] as $tipo => $qtd): ?>
                                    <div class="col-auto mb-2">
                                        <span class="badge badge-<?php echo e(escala_mensal_badge_tipo($tipo)); ?> escala-badge-tipo">
                                            <?php echo e(escala_mensal_tipo_label($tipo)); ?>: <?php echo (int) $qtd; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="escala-equipa-picker mb-3">
                                <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', ['mes' => $mes, 'ano' => $ano, 'equipa_id' => $equipaId, 'funcionario_id' => 0, 'vista' => 'grelha'])); ?>" class="btn btn-sm escala-equipa-all-btn <?php echo $funcionarioId <= 0 ? 'is-active' : ''; ?>">
                                    <i class="fe fe-users mr-1"></i> Toda a equipa
                                </a>
                                <div class="escala-equipa-picker__field">
                                    <label class="escala-equipa-picker__label" for="escalaEquipaSearch">Ir para funcionário</label>
                                    <div class="escala-equipa-picker__controls">
                                        <div class="escala-equipa-picker__search-wrap">
                                            <i class="fe fe-search escala-equipa-picker__icon" aria-hidden="true"></i>
                                            <input type="search" id="escalaEquipaSearch" class="form-control form-control-sm js-equipa-filter" placeholder="Pesquisar nome ou n.º mecanográfico…" autocomplete="off" value="<?php echo $funcionarioId > 0 ? e($funcionarioEdicaoNome) : ''; ?>">
                                        </div>
                                        <select class="form-control form-control-sm js-equipa-jump" aria-label="Selecionar funcionário">
                                            <option value="">— Selecionar —</option>
                                            <?php foreach ($funcionariosFiltro as $funcionarioOpcao): ?>
                                                <?php
                                                $diasOpcao = count($escalaGuardada[(int) $funcionarioOpcao['id']] ?? []);
                                                $jumpUrl = admin_url('escala_mensal', [
                                                    'mes' => $mes,
                                                    'ano' => $ano,
                                                    'equipa_id' => $equipaId,
                                                    'funcionario_id' => (int) $funcionarioOpcao['id'],
                                                    'vista' => 'simples',
                                                ]);
                                                $jumpLabel = $funcionarioOpcao['nome'];
                                                if ($diasOpcao > 0) {
                                                    $jumpLabel .= ' (' . $diasOpcao . ' dia' . ($diasOpcao === 1 ? '' : 's') . ')';
                                                }
                                                $jumpSearch = strtolower(trim($funcionarioOpcao['nome'] . ' ' . ($funcionarioOpcao['numero_mecanografico'] ?? '')));
                                                ?>
                                                <option value="<?php echo htmlspecialchars($jumpUrl); ?>"
                                                    data-search="<?php echo e($jumpSearch); ?>"
                                                    <?php echo (int) $funcionarioOpcao['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                    <?php echo e($jumpLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-primary js-equipa-jump-go">Abrir</button>
                                    </div>
                                </div>
                            </div>

                            <?php if (count($resumoEscala['por_funcionario']) > 5): ?>
                            <div class="escala-resumo-filter mb-2">
                                <i class="fe fe-filter escala-resumo-filter__icon" aria-hidden="true"></i>
                                <input type="search" class="form-control form-control-sm js-resumo-table-filter" placeholder="Filtrar lista de funcionários…" autocomplete="off">
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($resumoEscala['por_funcionario'])): ?>
                            <div class="table-responsive escala-resumo-wrap">
                                <table class="table table-sm table-hover mb-0 escala-resumo-table">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Dias na escala</th>
                                            <th>Distribuição</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resumoEscala['por_funcionario'] as $linhaResumo): ?>
                                            <?php $isActive = (int) $linhaResumo['id'] === $funcionarioId && $funcionarioId > 0; ?>
                                            <tr class="escala-resumo-row <?php echo $isActive ? 'escala-resumo-active' : ''; ?><?php echo empty($linhaResumo['tem_escala']) ? ' escala-resumo-empty' : ''; ?>" data-search="<?php echo e(strtolower($linhaResumo['nome'] . ' ' . ($linhaResumo['numero_mecanografico'] ?? ''))); ?>">
                                                <td class="font-weight-bold"><?php echo e($linhaResumo['nome']); ?></td>
                                                <td><?php echo (int) $linhaResumo['dias']; ?></td>
                                                <td class="small escala-resumo-dist"><?php echo e($linhaResumo['resumo']); ?></td>
                                                <td class="text-nowrap">
                                                    <?php if ($isActive): ?>
                                                        <span class="escala-resumo-editing">A editar</span>
                                                    <?php else: ?>
                                                        <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', ['mes' => $mes, 'ano' => $ano, 'equipa_id' => $equipaId, 'funcionario_id' => $linhaResumo['id'], 'vista' => 'simples'])); ?>" class="btn btn-sm escala-resumo-btn">
                                                            <?php echo $linhaResumo['tem_escala'] ? 'Editar' : 'Planear'; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            <?php if ((int) ($resumoEscala['por_tipo']['falta'] ?? 0) > 0): ?>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fe fe-file-text mr-1"></i> Gerir justificações de faltas
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header d-flex align-items-center">
                            <h4 class="card-title mb-0">Escolher mês e funcionário</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" action="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>" class="row g-3 align-items-end">
                                <input type="hidden" name="vista" value="<?php echo e($vistaModo); ?>">
                                <div class="col-md-2">
                                    <label class="form-label">Mês</label>
                                    <select name="mes" class="form-control">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>>
                                                <?php echo e(month_name($i)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Equipa</label>
                                    <select name="equipa_id" class="form-control">
                                        <option value="0">Todas as equipas</option>
                                        <?php foreach ($equipas as $equipa): ?>
                                            <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) $equipa['id'] === $equipaId ? 'selected' : ''; ?>>
                                                <?php echo e($equipa['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Funcionário</label>
                                    <select name="funcionario_id" class="form-control">
                                        <option value="0">Toda a equipa</option>
                                        <?php foreach ($funcionariosFiltro as $funcionarioFiltro): ?>
                                            <option value="<?php echo (int) $funcionarioFiltro['id']; ?>" <?php echo (int) $funcionarioFiltro['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                <?php echo e($funcionarioFiltro['nome']); ?>
                                                <?php if ($funcionarioFiltro['numero_mecanografico']): ?>
                                                    (<?php echo e($funcionarioFiltro['numero_mecanografico']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa fa-check"></i>
                                        Aplicar
                                    </button>
                                </div>
                            </form>
                            <?php if ($funcionarioId <= 0): ?>
                                <p class="text-muted small mb-0 mt-3">
                                    <i class="fe fe-info mr-1"></i>
                                    Clique num funcionário na lista acima ou escolha aqui para planear dia a dia.
                                </p>
                            <?php elseif ($funcionarioId > 0 && $vistaSimples && $funcionarioEdicaoNome !== ''): ?>
                                <p class="text-muted small mb-0 mt-3">
                                    <i class="fe fe-user mr-1"></i>
                                    A editar: <strong><?php echo e($funcionarioEdicaoNome); ?></strong>
                                    · <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', ['mes' => $mes, 'ano' => $ano, 'equipa_id' => $equipaId, 'funcionario_id' => 0, 'vista' => 'grelha'])); ?>">Ver toda a equipa</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-4 escala-panel-card">
                        <div class="card-header escala-collapse-header" data-toggle="collapse" data-target="#escalaImportCollapse" aria-expanded="<?php echo $logImportacao ? 'true' : 'false'; ?>">
                            <div class="d-flex align-items-center flex-wrap w-100">
                                <h4 class="card-title mb-0">Importar de ficheiro CSV <span class="text-muted font-weight-normal small">(opcional)</span></h4>
                                <i class="fe fe-chevron-down escala-collapse-chevron ml-auto" aria-hidden="true"></i>
                            </div>
                        </div>
                        <div id="escalaImportCollapse" class="collapse<?php echo $logImportacao ? ' show' : ''; ?>">
                        <div class="card-body py-3">
                            <form method="post" action="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="importar_excel">
                                <input type="hidden" name="mes" value="<?php echo (int) $mes; ?>">
                                <input type="hidden" name="ano" value="<?php echo (int) $ano; ?>">
                                <input type="hidden" name="equipa_id" value="<?php echo (int) $equipaId; ?>">
                                <div class="row g-2 align-items-end mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label mb-1" for="importFuncionarioId">Importar para</label>
                                        <select name="funcionario_id" id="importFuncionarioId" class="form-control js-import-funcionario">
                                            <option value="0">Vários funcionários (ficheiro com numero_mecanografico)</option>
                                            <?php foreach ($funcionariosFiltro as $funcionarioFiltro): ?>
                                                <option value="<?php echo (int) $funcionarioFiltro['id']; ?>" <?php echo (int) $funcionarioFiltro['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                    <?php echo e($funcionarioFiltro['nome']); ?>
                                                    <?php if ($funcionarioFiltro['numero_mecanografico']): ?>
                                                        (<?php echo e($funcionarioFiltro['numero_mecanografico']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="escala-import-context mb-0">
                                            Período: <strong><?php echo e(month_name($mes)); ?> <?php echo (int) $ano; ?></strong>
                                            <?php if ($equipaId > 0): ?>
                                                · Equipa filtrada
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="escala-import-bar">
                                    <div class="escala-import-bar__upload">
                                        <?php
                                        upload_field([
                                            'id' => 'ficheiroEscalaImport',
                                            'name' => 'ficheiro_escala',
                                            'accept' => '.csv,text/csv',
                                            'required' => true,
                                            'variant' => 'inline',
                                            'wrap_class' => 'csnsa-upload--escala-import',
                                            'button_text' => 'Escolher ficheiro',
                                        ]);
                                        ?>
                                    </div>
                                    <div class="escala-import-bar__action">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fe fe-upload-cloud"></i> Importar
                                        </button>
                                    </div>
                                </div>
                                <p class="escala-import-hint mb-0 js-import-hint-multi">
                                    Ficheiro CSV · <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', ['exemplo_csv' => '1', 'mes' => $mes, 'ano' => $ano, 'equipa_id' => $equipaId, 'funcionario_id' => $funcionarioId])); ?>">Descarregar exemplo</a>
                                    · Uma linha por dia, com número mecanográfico do funcionário
                                </p>
                                <p class="escala-import-hint mb-0 js-import-hint-single d-none">
                                    Ficheiro CSV · <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', array_filter(['exemplo_csv' => '1', 'mes' => $mes, 'ano' => $ano, 'funcionario_id' => $funcionarioId ?: null]))); ?>">Descarregar exemplo</a>
                                    · Uma linha por dia, só para este funcionário
                                </p>
                                <?php if ($logImportacao): ?>
                                    <div class="alert alert-success csnsa-alert escala-import-log mt-3 mb-0 py-2 px-3">
                                        <strong>Importação concluída:</strong>
                                        <?php echo e(date('d/m/Y H:i', strtotime($logImportacao['quando']))); ?>
                                        · <?php echo (int) $logImportacao['importados']; ?> dia(s)
                                        <?php if (!empty($logImportacao['funcionario_nome'])): ?>
                                            · <?php echo e($logImportacao['funcionario_nome']); ?>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', array_merge($baseParams, ['ver' => 'importado', 'vista' => $vistaModo]))); ?>" class="alert-link ml-1">Ver detalhes</a>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                        </div>
                    </div>

                    <?php if ($detalheImportado !== [] && $mostrarDetalheImportado): ?>
                    <div class="card mb-4 escala-panel-card" id="escala-importado-detalhe">
                        <div class="card-header escala-collapse-header d-flex align-items-center flex-wrap" data-toggle="collapse" data-target="#escalaDetalheCollapse" aria-expanded="true">
                            <h4 class="card-title mb-0">Detalhe guardado <span class="text-muted font-weight-normal small">(consulta)</span></h4>
                            <span class="escala-count-badge ml-2"><?php echo count($detalheImportado); ?><?php echo count($detalheImportado) >= 80 ? '+' : ''; ?> dia(s)</span>
                            <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', $baseParams + ['vista' => $vistaModo])); ?>" class="btn btn-sm btn-outline-secondary ml-2 escala-panel-close" onclick="event.stopPropagation();">Fechar</a>
                            <i class="fe fe-chevron-down escala-collapse-chevron ml-auto" aria-hidden="true"></i>
                        </div>
                        <div id="escalaDetalheCollapse" class="collapse show">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 escala-detalhe-table">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Dia</th>
                                            <th>Data</th>
                                            <th>Tipo</th>
                                            <th>Turno</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalheImportado as $item): ?>
                                            <tr>
                                                <td><?php echo e($item['funcionario_nome']); ?></td>
                                                <td><?php echo (int) $item['dia']; ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($item['data_escala']))); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(escala_mensal_badge_tipo((string) $item['tipo_dia'])); ?>">
                                                        <?php echo e(escala_mensal_tipo_label((string) $item['tipo_dia'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo e($item['turno_codigo'] ?: $item['turno_nome'] ?: '—'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>">
                        <input type="hidden" name="acao" value="guardar">
                        <input type="hidden" name="mes" value="<?php echo (int) $mes; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $ano; ?>">
                        <input type="hidden" name="equipa_id" value="<?php echo (int) $equipaId; ?>">
                        <input type="hidden" name="funcionario_id" value="<?php echo (int) $funcionarioId; ?>">

                        <div class="card escala-grelha-card escala-panel-card escala-preencher-panel" id="escala-grelha">
                            <div class="card-header escala-collapse-header d-flex align-items-center flex-wrap" data-toggle="collapse" data-target="#escalaPreencherCollapse" aria-expanded="<?php echo $expandPreencherCard ? 'true' : 'false'; ?>">
                                <h4 class="card-title mb-0">Preencher dias — <?php echo e(month_name($mes)); ?> <?php echo (int) $ano; ?></h4>
                                <?php if ($funcionarioId > 0 && $funcionarioEdicaoNome !== ''): ?>
                                    <span class="escala-emp-badge ml-2"><?php echo e($funcionarioEdicaoNome); ?></span>
                                    <?php if ($diasGuardadosFuncionario > 0): ?>
                                        <span class="escala-count-badge ml-1"><?php echo (int) $diasGuardadosFuncionario; ?> dia(s) guardado(s)</span>
                                    <?php endif; ?>
                                <?php elseif ($funcionarioId <= 0 && !empty($funcionarios)): ?>
                                    <span class="escala-count-badge ml-2">Toda a equipa</span>
                                <?php endif; ?>
                                <i class="fe fe-chevron-down escala-collapse-chevron ml-auto" aria-hidden="true"></i>
                            </div>
                            <div id="escalaPreencherCollapse" class="collapse<?php echo $expandPreencherCard ? ' show' : ''; ?>">
                            <div class="escala-preencher-toolbar">
                                <div class="escala-toolbar-strip">
                                    <?php if ($funcionarioId > 0): ?>
                                        <div class="escala-view-toggle" role="group" aria-label="Vista da escala">
                                            <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', array_merge($baseParams, ['vista' => 'simples']))); ?>" class="escala-view-toggle__btn <?php echo $vistaSimples ? 'is-active' : ''; ?>">Lista dia a dia</a>
                                            <a href="<?php echo htmlspecialchars(admin_url('escala_mensal', array_merge($baseParams, ['vista' => 'grelha']))); ?>" class="escala-view-toggle__btn <?php echo !$vistaSimples ? 'is-active' : ''; ?>">Grelha</a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($vistaSimples && !empty($funcionarios)): ?>
                                        <div class="escala-atalhos">
                                            <select class="form-control form-control-sm js-turno-rapido">
                                                <option value="">Turno para dias úteis…</option>
                                                <?php foreach ($turnos as $turno): ?>
                                                    <option value="<?php echo (int) $turno['id']; ?>"><?php echo e($turno['codigo'] ?: $turno['nome']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-primary js-aplicar-turno-uteis">Aplicar</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-aplicar-folga-fds">Fins de semana → Folga</button>
                                        </div>
                                    <?php endif; ?>
                                    <label class="escala-chip-advanced mb-0">
                                        <input type="checkbox" class="js-mostrar-avancado"> Opções avançadas
                                    </label>
                                    <button type="submit" class="btn btn-success btn-sm escala-save-btn ml-auto" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                        <i class="fa fa-save mr-1"></i>
                                        Guardar escala
                                    </button>
                                </div>
                                <?php if (!$vistaSimples): ?>
                                <div class="d-flex align-items-center flex-wrap escala-legend escala-preencher-legend">
                                    <span class="escala-chip escala-chip-folga"><span class="escala-dot"></span>Folga trabalhada</span>
                                    <span class="escala-chip escala-chip-subs"><span class="escala-dot"></span>Substituição</span>
                                    <span class="escala-chip escala-chip-weekend"><span class="escala-dot"></span>Fim de semana</span>
                                    <span class="escala-chip escala-chip-guardado"><span class="escala-dot"></span>Já guardado</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($turnosEmFalta > 0): ?>
                                    <?php escala_mensal_render_aviso_incompleto($turnosEmFalta, $vistaSimples, !empty($turnos)); ?>
                                <?php endif; ?>
                                <?php if ($funcionarioFiltroInvalido): ?>
                                    <p class="mb-0">Funcionário inválido para o filtro selecionado.</p>
                                <?php elseif (empty($funcionarios)): ?>
                                    <div class="text-center py-4">
                                        <p class="mb-2">Nenhum funcionário encontrado com estes filtros.</p>
                                        <p class="text-muted small mb-0">Verifique a equipa ou registe funcionários em Funcionários.</p>
                                    </div>
                                <?php elseif ($funcionarioId <= 0): ?>
                                    <?php render_alert('Está a ver toda a equipa de uma vez. Para editar com mais calma, escolha um funcionário nos filtros acima.', 'info', false, 'mb-3'); ?>
                                    <?php include __DIR__ . '/includes/escala_vista_grelha.php'; ?>
                                <?php elseif ($vistaSimples): ?>
                                    <?php if (empty($turnos)): ?>
                                        <?php render_alert('Ainda não há turnos criados. Crie turnos (ex.: Manhã, Tarde) na página Turnos e depois volte aqui.', 'info', false, 'mb-3'); ?>
                                    <?php endif; ?>
                                    <?php
                                    $funcionario = $funcionarios[0];
                                    $funcId = (int) $funcionario['id'];
                                    ?>
                                    <div class="escala-simples-list">
                                        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                            <?php
                                            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                            $fimDeSemana = in_array(date('N', strtotime($data)), [6, 7], true);
                                            $registo = $escalaGuardada[$funcId][$dia] ?? [];
                                            $celula = escala_mensal_dados_celula($registo);
                                            $cellClasses = ['escala-cell', 'escala-simples-row'];
                                            if ($fimDeSemana) {
                                                $cellClasses[] = 'escala-fim-semana';
                                            }
                                            if ($celula['guardado']) {
                                                $cellClasses[] = 'escala-guardado';
                                            }
                                            if ($celula['guardado'] && $celula['tipo_dia'] === 'turno' && $celula['turno_id'] <= 0) {
                                                $cellClasses[] = 'escala-incompleto';
                                            }
                                            if ($celula['tipo_dia'] !== '') {
                                                $cellClasses[] = 'escala-tipo-' . preg_replace('/[^a-z_]/', '', $celula['tipo_dia']);
                                            }
                                            ?>
                                            <div class="<?php echo e(implode(' ', $cellClasses)); ?>" data-dia="<?php echo (int) $dia; ?>" data-fds="<?php echo $fimDeSemana ? '1' : '0'; ?>">
                                                <div class="escala-simples-data">
                                                    <span class="escala-simples-dia"><?php echo (int) $dia; ?></span>
                                                    <span class="escala-simples-meta">
                                                        <?php echo e(weekday_short($data)); ?> · <?php echo e(date('d/m/Y', strtotime($data))); ?>
                                                    </span>
                                                </div>
                                                <div class="escala-simples-campos">
                                                    <?php $layout = 'simples'; include __DIR__ . '/includes/escala_celula_campos.php'; ?>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <?php include __DIR__ . '/includes/escala_vista_grelha.php'; ?>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                    </form>
                
</div>
    </div>
<?php
$pageScripts = '<style>
        /* ---- Escala Mensal : adaptive light/dark palette ---- */
        .escala-wrapper {
            --esc-surface: #ffffff;
            --esc-surface-alt: #f4f6fb;
            --esc-weekend: #eef2fb;
            --esc-weekend-head: #e3e9f7;
            --esc-border: #e4e8f0;
            --esc-head-text: #344056;
            --esc-day-num: #1b2a4a;
            --esc-folga: rgba(255, 193, 7, 0.18);
            --esc-folga-bar: #f0ad22;
            --esc-subs: rgba(23, 162, 184, 0.18);
            --esc-subs-bar: #17a2b8;
            max-height: 74vh;
            overflow: auto;
            border-radius: 0.5rem;
            border: 1px solid var(--esc-border);
        }

        body.dark .escala-wrapper {
            --esc-surface: #2b303b;
            --esc-surface-alt: #333a47;
            --esc-weekend: #2f3947;
            --esc-weekend-head: #36425a;
            --esc-border: #3a4150;
            --esc-head-text: #aeb7c6;
            --esc-day-num: #f1f4f9;
            --esc-folga: rgba(255, 193, 7, 0.20);
            --esc-folga-bar: #f0ad22;
            --esc-subs: rgba(72, 171, 247, 0.22);
            --esc-subs-bar: #48abf7;
        }

        .escala-table {
            min-width: 1800px;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .escala-table th,
        .escala-table td {
            vertical-align: top;
            border: 1px solid var(--esc-border) !important;
        }

        /* sticky funcionario column */
        .escala-sticky-col {
            background: var(--esc-surface);
            left: 0;
            min-width: 240px;
            position: sticky;
            z-index: 3;
            box-shadow: 6px 0 8px -6px rgba(0, 0, 0, 0.18);
        }

        .escala-table thead .escala-sticky-col {
            z-index: 5;
            background: var(--esc-surface-alt);
            color: var(--esc-day-num);
            font-weight: 700;
            letter-spacing: .02em;
        }

        /* day header */
        .escala-dia-header {
            min-width: 170px;
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--esc-surface-alt);
            color: var(--esc-head-text);
            padding: .4rem .3rem;
        }

        .escala-dia-header > div {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--esc-day-num);
        }

        .escala-dia-header small {
            text-transform: uppercase;
            font-size: .62rem;
            letter-spacing: .06em;
            font-weight: 700;
            color: var(--esc-head-text);
        }

        body.dark .escala-dia-header small {
            opacity: .85;
        }

        thead .escala-fim-semana {
            background: var(--esc-weekend-head);
        }

        thead .escala-fim-semana > div,
        thead .escala-fim-semana small {
            color: #1b68ff;
        }

        body.dark thead .escala-fim-semana > div,
        body.dark thead .escala-fim-semana small {
            color: #6ea8ff;
        }

        /* body cells */
        .escala-cell {
            min-width: 170px;
            background: var(--esc-surface);
            padding: .35rem;
            transition: background .15s ease;
        }

        .escala-cell.escala-fim-semana {
            background: var(--esc-weekend);
        }

        .escala-folga-trabalhada {
            background: var(--esc-folga) !important;
            box-shadow: inset 3px 0 0 0 var(--esc-folga-bar);
        }

        .escala-substituicao {
            background: var(--esc-subs) !important;
            box-shadow: inset 3px 0 0 0 var(--esc-subs-bar);
        }

        .escala-folga-trabalhada.escala-substituicao {
            background: linear-gradient(135deg, var(--esc-folga) 0%, var(--esc-folga) 50%, var(--esc-subs) 50%, var(--esc-subs) 100%) !important;
            box-shadow: inset 3px 0 0 0 var(--esc-folga-bar);
        }

        .escala-funcionario {
            white-space: normal;
        }

        .escala-funcionario .fw-bold {
            color: var(--esc-day-num);
        }

        .escala-cell .form-control {
            font-size: 0.75rem;
            padding: .2rem .4rem;
            height: auto;
        }

        .escala-cell .escala-tipo {
            font-weight: 600;
        }

        .escala-folga-check {
            font-size: 0.72rem;
            min-height: auto;
        }

        /* legend chips */
        .escala-header-row { gap: .5rem; row-gap: .5rem; }
        .escala-legend { gap: .5rem; }

        .escala-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
            border: 1px solid var(--esc-border, #e4e8f0);
            background: rgba(127, 127, 127, 0.06);
        }

        .escala-chip .escala-dot {
            width: .7rem;
            height: .7rem;
            border-radius: 50%;
            display: inline-block;
        }

        .escala-chip-folga .escala-dot { background: #f0ad22; }
        .escala-chip-subs .escala-dot { background: #17a2b8; }
        .escala-chip-weekend .escala-dot { background: #1b68ff; }
        .escala-chip-guardado .escala-dot { background: #28a745; }

        .escala-guardado {
            box-shadow: inset 0 -3px 0 0 rgba(40, 167, 69, 0.55);
        }

        .escala-tipo-falta.escala-guardado { box-shadow: inset 0 -3px 0 0 #dc3545; }
        .escala-tipo-ferias.escala-guardado { box-shadow: inset 0 -3px 0 0 #17a2b8; }
        .escala-tipo-baixa.escala-guardado { box-shadow: inset 0 -3px 0 0 #f0ad22; }
        .escala-tipo-folga.escala-guardado { background: rgba(108, 117, 125, 0.08); }

        .escala-resumo-card .escala-badge-tipo { font-size: .8rem; padding: .35rem .55rem; }
        .escala-import-log { font-size: .85rem; }
        .escala-detalhe-table th { white-space: nowrap; }

        .escala-save-btn { margin-left: .25rem; }

        .escala-collapse-header { cursor: pointer; user-select: none; }

        body.escala-avancado .escala-campos-extra:not(.escala-campos-extra--simples) { display: block !important; }
        body:not(.escala-avancado) .escala-campos-extra:not(.escala-campos-extra--simples) { display: none; }
        .escala-campos-extra--simples { display: none; margin-top: .35rem; }
        .escala-campos-extra--simples.is-open { display: block; }
    </style>
    <script>
        $(document).ready(function () {
            function atualizarCelula($cell) {
                var tipo = $cell.find(\'.escala-tipo\').val();
                var folgaTrabalhada = $cell.find(\'.escala-folga-trabalhada-check\').is(\':checked\');
                var turnoId = $cell.find(\'.escala-turno\').val();
                var mostrarSubstitui = tipo === \'substituicao\';
                var mostrarTurno = tipo === \'turno\' || tipo === \'substituicao\' || folgaTrabalhada;
                var incompleto = tipo !== \'\' && mostrarTurno && (!turnoId || turnoId === \'\');

                $cell.toggleClass(\'escala-substituicao\', mostrarSubstitui);
                $cell.toggleClass(\'escala-folga-trabalhada\', folgaTrabalhada);
                $cell.toggleClass(\'escala-incompleto\', incompleto);
                $cell.removeClass(function (i, c) {
                    return (c.match(/\\bescala-tipo-\\S+/g) || []).join(\' \');
                });
                if (tipo) {
                    $cell.addClass(\'escala-tipo-\' + tipo.replace(/[^a-z_]/g, \'\'));
                }
                $cell.find(\'.escala-substitui\').toggle(mostrarSubstitui);
                $cell.find(\'.escala-turno\').toggle(mostrarTurno);
            }

            $(\'.escala-cell\').each(function () {
                atualizarCelula($(this));
            });

            $(\'.escala-tipo, .escala-folga-trabalhada-check, .escala-turno\').on(\'change\', function () {
                atualizarCelula($(this).closest(\'.escala-cell\'));
            });

            $(\'.js-mostrar-avancado\').on(\'change\', function () {
                $(\'body\').toggleClass(\'escala-avancado\', $(this).is(\':checked\'));
            });

            $(\'.escala-toggle-extra\').on(\'click\', function () {
                var $extra = $(this).siblings(\'.escala-campos-extra--simples\');
                $extra.toggleClass(\'is-open\');
                $(this).text($extra.hasClass(\'is-open\') ? \'Menos opções\' : \'Mais opções\');
            });

            $(\'.js-aplicar-folga-fds\').on(\'click\', function () {
                $(\'.escala-simples-row[data-fds="1"]\').each(function () {
                    $(this).find(\'.escala-tipo\').val(\'folga\').trigger(\'change\');
                });
            });

            $(\'.js-aplicar-turno-uteis\').on(\'click\', function () {
                var turnoId = $(\'.js-turno-rapido\').val();
                if (!turnoId) {
                    return;
                }
                $(\'.escala-simples-row[data-fds="0"]\').each(function () {
                    var $row = $(this);
                    $row.find(\'.escala-tipo\').val(\'turno\');
                    $row.find(\'.escala-turno\').val(turnoId);
                    atualizarCelula($row);
                });
            });

            function atualizarImportHint() {
                var single = $(\'.js-import-funcionario\').val() !== \'0\';
                $(\'.js-import-hint-single\').toggleClass(\'d-none\', !single);
                $(\'.js-import-hint-multi\').toggleClass(\'d-none\', single);
            }

            $(\'.js-import-funcionario\').on(\'change\', atualizarImportHint);
            atualizarImportHint();

            function filtrarOpcoesEquipa() {
                var q = $(\'.js-equipa-filter\').val().toLowerCase().trim();
                $(\'.js-equipa-jump option\').each(function () {
                    if (!this.value) {
                        this.hidden = false;
                        return;
                    }
                    var hay = (this.textContent + \' \' + (this.dataset.search || \'\')).toLowerCase();
                    this.hidden = q !== \'\' && hay.indexOf(q) === -1;
                });
            }

            function irParaFuncionarioSelecionado() {
                var url = $(\'.js-equipa-jump\').val();
                if (url) {
                    window.location.href = url;
                }
            }

            $(\'.js-equipa-filter\').on(\'input\', filtrarOpcoesEquipa);
            $(\'.js-equipa-jump\').on(\'change\', irParaFuncionarioSelecionado);
            $(\'.js-equipa-jump-go\').on(\'click\', irParaFuncionarioSelecionado);
            $(\'.js-equipa-filter\').on(\'keydown\', function (e) {
                if (e.key === \'Enter\') {
                    e.preventDefault();
                    var q = $(this).val().toLowerCase().trim();
                    var $match = $(\'.js-equipa-jump option\').filter(function () {
                        return this.value && !this.hidden && (this.dataset.search || \'\').indexOf(q) >= 0;
                    }).first();
                    if ($match.length) {
                        window.location.href = $match.val();
                    }
                }
            });
            filtrarOpcoesEquipa();

            $(\'.js-resumo-table-filter\').on(\'input\', function () {
                var q = $(this).val().toLowerCase().trim();
                $(\'.escala-resumo-row\').each(function () {
                    var hay = ($(this).data(\'search\') || \'\').toString();
                    $(this).toggle(q === \'\' || hay.indexOf(q) >= 0);
                });
            });

            $(\'.js-scroll-incompleto\').on(\'click\', function () {
                var $first = $(\'.escala-incompleto\').first();
                if ($first.length) {
                    $(\'#escalaPreencherCollapse\').collapse(\'show\');
                    $(\'html, body\').animate({ scrollTop: $first.offset().top - 100 }, 400);
                }
            });

            if (window.location.hash === \'#escala-grelha\') {
                var $alvo = $(\'#escala-grelha\');
                if ($alvo.length) {
                    $(\'html, body\').animate({ scrollTop: $alvo.offset().top - 80 }, 400);
                }
            }
        });
    </script>';
include __DIR__ . '/includes/layout-end.php';
