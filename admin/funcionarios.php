<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/funcionarios_actions.php';
require_once __DIR__ . '/includes/funcionario_horario_presets.php';
require_once __DIR__ . '/includes/upload_field.php';
require_page_permission();

process_funcionarios_post($conn, 'funcionarios');

$missingTables = funcionarios_missing_tables($conn);
$temEquipas = funcionarios_tem_equipas($conn);
$equipas = funcionarios_load_equipas($conn);
$mostrarArquivados = isset($_GET['arquivados']);
$funcionarios = funcionarios_load_list($conn, $mostrarArquivados);
$listagemHiddenField = $mostrarArquivados
    ? '<input type="hidden" name="listagem" value="arquivados">'
    : '';

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Funcionários';
$useDataTables = true;
$dataNascimentoMax = funcionario_data_nascimento_maxima();
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">'
    . '<style>.js-funcionario-form .iti { width: 100%; } .js-funcionario-form .iti__flag-container { z-index: 5; }</style>';
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Funcionários</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>">Funcionários</a></li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <?php if ($mostrarArquivados): ?>
                        <?php render_alert('Estes funcionários foram removidos da lista principal. Use o botão azul para os trazer de volta, ou o vermelho para apagar definitivamente do sistema.', 'info', false, 'mb-3'); ?>
                    <?php endif; ?>

                    <div class="card shadow">
                        <div class="card-header">
                            <div class="d-flex align-items-center flex-wrap">
                                <h4 class="card-title mb-0">
                                    <?php echo $mostrarArquivados ? 'Funcionários arquivados' : 'Lista de funcionários'; ?>
                                    <?php if ($mostrarArquivados): ?>
                                        <span class="badge badge-dark ml-2">Reciclagem</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="ml-auto d-flex flex-wrap align-items-center">
                                    <a href="<?php echo htmlspecialchars(admin_url('funcionarios', $mostrarArquivados ? [] : ['arquivados' => '1'])); ?>" class="btn btn-light btn-round mr-2 mb-2 mb-md-0">
                                        <i class="fe fe-archive"></i>
                                        <?php echo $mostrarArquivados ? 'Ocultar arquivados' : 'Ver arquivados'; ?>
                                    </a>
                                    <?php if (empty($missingTables)): ?>
                                    <div class="btn-group mr-2 mb-2 mb-md-0">
                                        <button type="button" class="btn btn-light btn-round dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fe fe-download fe-16"></i> Exportar
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <h6 class="dropdown-header">Lista de funcionários</h6>
                                            <a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('gerar_pdf_funcionarios', ['tipo' => 'lista', 'formato' => 'pdf'])); ?>">PDF (todos)</a>
                                            <a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('gerar_pdf_funcionarios', ['tipo' => 'lista', 'formato' => 'csv'])); ?>">Excel / CSV (todos)</a>
                                            <div class="dropdown-divider"></div>
                                            <h6 class="dropdown-header">Com seleção</h6>
                                            <button type="button" class="dropdown-item js-export-selecionados" data-tipo="lista" data-formato="pdf">PDF (selecionados)</button>
                                            <button type="button" class="dropdown-item js-export-selecionados" data-tipo="lista" data-formato="csv">Excel / CSV (selecionados)</button>
                                            <div class="dropdown-divider"></div>
                                            <h6 class="dropdown-header">Picagens</h6>
                                            <button type="button" class="dropdown-item js-export-picagens" data-formato="pdf">PDF de picagens</button>
                                            <button type="button" class="dropdown-item js-export-picagens" data-formato="csv">Excel / CSV de picagens</button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!$mostrarArquivados): ?>
                                    <button class="btn btn-primary btn-round" data-toggle="modal" data-target="#modalCriarFuncionario" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                        <i class="fa fa-plus"></i>
                                        Adicionar funcionário
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-funcionarios" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 36px">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="selecionarTodosFuncionarios">
                                                    <label class="custom-control-label" for="selecionarTodosFuncionarios"></label>
                                                </div>
                                            </th>
                                            <th>N.º mec.</th>
                                            <th>Nome</th>
                                            <th>Função</th>
                                            <th>Equipa</th>
                                            <th>Código picagem</th>
                                            <th>Estado</th>
                                            <th style="width: <?php echo $mostrarArquivados ? '180px' : '150px'; ?>">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td>
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input funcionario-select" id="funcionarioSel<?php echo (int) $funcionario['id']; ?>" name="funcionarios[]" value="<?php echo (int) $funcionario['id']; ?>">
                                                        <label class="custom-control-label" for="funcionarioSel<?php echo (int) $funcionario['id']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                    <small class="text-muted"><?php echo e($funcionario['email'] ?: $funcionario['telefone'] ?: 'Sem contacto'); ?></small>
                                                </td>
                                                <td><?php echo e($funcionario['funcao'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['codigo_biometrico'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(funcionario_estado_badge($funcionario['estado'])); ?>">
                                                        <?php echo e(ucfirst($funcionario['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-toggle="modal" data-target="#modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" title="Editar">
                                                            <span class="fe fe-edit"></span>
                                                        </button>
                                                        <?php if ($mostrarArquivados): ?>
                                                            <button type="button"
                                                                class="btn btn-link btn-primary btn-lg js-restaurar-funcionario"
                                                                title="Voltar à lista principal"
                                                                data-id="<?php echo (int) $funcionario['id']; ?>"
                                                                data-nome="<?php echo e($funcionario['nome']); ?>">
                                                                <span class="fe fe-rotate-ccw"></span>
                                                            </button>
                                                            <button type="button"
                                                                class="btn btn-link btn-danger btn-lg js-eliminar-permanente"
                                                                title="Apagar definitivamente"
                                                                data-id="<?php echo (int) $funcionario['id']; ?>"
                                                                data-nome="<?php echo e($funcionario['nome']); ?>"
                                                                data-tem-historico="<?php echo funcionario_tem_dependencias($conn, (int) $funcionario['id']) ? '1' : '0'; ?>">
                                                                <span class="fe fe-trash-2"></span>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button"
                                                                class="btn btn-link btn-danger btn-lg js-arquivar-funcionario"
                                                                title="Remover funcionário"
                                                                data-id="<?php echo (int) $funcionario['id']; ?>"
                                                                data-nome="<?php echo e($funcionario['nome']); ?>">
                                                                <span class="fe fe-trash-2"></span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

    <div class="modal fade" id="modalCriarFuncionario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="modal-content needs-validation js-funcionario-form" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="acao" value="criar">
                <?php echo $listagemHiddenField; ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar funcionário</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php
                    $funcionarioForm = [
                        'id' => 0,
                        'nome' => '',
                        'foto' => '',
                        'numero_mecanografico' => '',
                        'email' => '',
                        'telefone' => '',
                        'funcao' => '',
                        'equipa_id' => '',
                        'data_admissao' => '',
                        'data_cessacao' => '',
                        'tipo_contrato' => '',
                        'carga_horaria_semanal' => '40.00',
                        'pin_ponto' => '',
                        'codigo_biometrico' => '',
                        'estado' => 'ativo',
                        'observacoes' => '',
                    ];
                    $includeLegacyFields = true;
                    $telefoneFieldId = 'funcionarioTelefoneCreate';
                    $dataNascimentoObrigatoria = true;
    include __DIR__ . '/includes/funcionario_form_campos.php';
                    ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($funcionarios as $funcionario): ?>
        <div class="modal fade" id="modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="modal-content needs-validation js-funcionario-form" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                    <?php echo $listagemHiddenField; ?>
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar funcionário</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $funcionarioForm = $funcionario;
                        $fotoPreviewId = 'funcionarioFotoPreview' . (int) $funcionario['id'];
                        $fotoInputId = 'funcionarioFotoInput' . (int) $funcionario['id'];
                        $telefoneFieldId = 'funcionarioTelefone' . (int) $funcionario['id'];
                        $dataNascimentoObrigatoria = false;
                        $includeLegacyFields = true;
    include __DIR__ . '/includes/funcionario_form_campos.php';
                        ?>
                    </div>
                    <div class="modal-footer border-0 d-flex flex-wrap align-items-center">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <?php if (($funcionario['estado'] ?? '') !== 'arquivado'): ?>
                            <button type="button" class="btn btn-outline-danger ml-auto" data-toggle="collapse" data-target="#arquivarFuncionario<?php echo (int) $funcionario['id']; ?>">
                                Arquivar funcionário
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (($funcionario['estado'] ?? '') === 'arquivado'): ?>
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="border-top px-3 py-3 d-flex justify-content-end mb-0">
                        <input type="hidden" name="acao" value="restaurar">
                        <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                        <button type="submit" class="btn btn-outline-success">Restaurar da reciclagem</button>
                    </form>
                <?php endif; ?>
                <?php if (($funcionario['estado'] ?? '') !== 'arquivado'): ?>
                <div class="collapse border-top px-3 pb-3" id="arquivarFuncionario<?php echo (int) $funcionario['id']; ?>">
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="pt-3" onsubmit="return confirm('Tem a certeza que pretende arquivar este funcionário? O registo permanece no sistema.');">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                        <input type="hidden" name="confirmar_arquivar" value="1">
                        <?php echo $listagemHiddenField; ?>
                        <button type="submit" class="btn btn-danger btn-sm">Confirmar arquivamento</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endforeach; ?>

    <form id="formArquivarFuncionario" method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="d-none">
        <input type="hidden" name="acao" value="remover">
        <input type="hidden" name="id" id="arquivarFuncionarioId" value="">
        <input type="hidden" name="confirmar_arquivar" value="1">
        <?php echo $listagemHiddenField; ?>
    </form>

    <form id="formRestaurarFuncionario" method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="d-none">
        <input type="hidden" name="acao" value="restaurar">
        <input type="hidden" name="id" id="restaurarFuncionarioId" value="">
        <input type="hidden" name="listagem" value="arquivados">
    </form>

    <form id="formEliminarPermanente" method="post" action="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="d-none">
        <input type="hidden" name="acao" value="eliminar_permanente">
        <input type="hidden" name="id" id="eliminarPermanenteId" value="">
        <input type="hidden" name="confirmar_eliminar" value="1">
        <input type="hidden" name="listagem" value="arquivados">
    </form>

    <div class="modal fade" id="modalArquivarFuncionario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Remover da lista?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Remover <strong id="arquivarFuncionarioNome"></strong> da lista principal?</p>
                    <p class="mb-0 small text-muted">Não é apagado do sistema — fica em <em>Ver arquivados</em>. Picagens e escala mantêm-se. Para apagar de vez, vá aos arquivados e use o botão vermelho.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarArquivarFuncionario">Remover da lista</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRestaurarFuncionario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Voltar à lista principal?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Trazer <strong id="restaurarFuncionarioNome"></strong> de volta à lista de funcionários?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmarRestaurarFuncionario">Voltar à lista</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEliminarPermanente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Apagar definitivamente?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Apagar <strong id="eliminarPermanenteNome"></strong> de forma permanente?</p>
                    <p class="mb-2 small text-danger" id="eliminarPermanenteAvisoHistorico" style="display:none;">Este funcionário tem picagens, escala ou outros registos. Ao apagar, esse histórico também será removido.</p>
                    <p class="mb-0 small text-muted">Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminarPermanente">Apagar definitivamente</button>
                </div>
            </div>
        </div>
    </div>

    <form id="formExportFuncionarios" method="post" action="<?php echo htmlspecialchars(admin_url('gerar_pdf_funcionarios')); ?>" target="_blank" class="d-none">
        <input type="hidden" name="tipo" id="exportTipo" value="lista">
        <input type="hidden" name="formato" id="exportFormato" value="pdf">
        <input type="hidden" name="tipo_periodo" id="exportTipoPeriodo" value="semana_atual">
        <input type="hidden" name="data_inicio" id="exportDataInicio" value="">
        <input type="hidden" name="data_fim" id="exportDataFim" value="">
        <div id="exportFuncionariosSelecionados"></div>
    </form>

    <div class="modal fade" id="modalExportPicagens" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Exportar picagens</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Período</label>
                        <select id="picagensPeriodo" class="form-control">
                            <option value="semana_atual">Semana atual</option>
                            <option value="semana_passada">Semana passada</option>
                            <option value="mes_atual">Mês atual</option>
                            <option value="personalizado">Intervalo personalizado</option>
                        </select>
                    </div>
                    <div id="picagensIntervaloPersonalizado" class="d-none">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label">Data início</label>
                                <input type="date" id="picagensDataInicio" class="form-control">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="form-label">Data fim</label>
                                <input type="date" id="picagensDataFim" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary" id="confirmarExportPicagens">Exportar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
<?php
$editFotoInits = '';
foreach ($funcionarios as $funcionario) {
    $fid = (int) $funcionario['id'];
    $fotoUrl = !empty($funcionario['foto']) ? '../' . ltrim((string) $funcionario['foto'], '/') : '';
    $editFotoInits .= '                initFuncionarioFotoPreview({'
        . 'previewId: ' . json_encode('funcionarioFotoPreview' . $fid) . ','
        . 'inputId: ' . json_encode('funcionarioFotoInput' . $fid) . ','
        . 'nomeInput: ' . json_encode('#modalEditarFuncionario' . $fid . ' input[name="nome"]') . ','
        . 'currentImageUrl: ' . json_encode($fotoUrl) . ','
        . 'defaultName: ' . json_encode($funcionario['nome'] ?? '') . ''
        . "});\n";
}
$dtZeroRecords = $mostrarArquivados ? 'Nenhum funcionário arquivado' : 'Nenhum funcionário encontrado';
$horarioPresetsJson = json_encode(funcionario_horario_presets(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$pageScripts = '<script>window.CSNSA_HORARIO_PRESETS = ' . $horarioPresetsJson . ';</script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="js/funcionario-telefone.js"></script>
<script src="js/funcionario-horario.js"></script>
<script src="js/funcionario-foto.js"></script>
<script>
        $(document).ready(function () {
            var exportForm = document.getElementById("formExportFuncionarios");
            var exportSelecionadosWrap = document.getElementById("exportFuncionariosSelecionados");
            var exportFormatoInput = document.getElementById("exportFormato");
            var exportTipoInput = document.getElementById("exportTipo");
            var exportTipoPeriodoInput = document.getElementById("exportTipoPeriodo");
            var exportDataInicioInput = document.getElementById("exportDataInicio");
            var exportDataFimInput = document.getElementById("exportDataFim");
            var pendingPicagensFormato = "pdf";

            function selectedFuncionarioIds() {
                return $(".funcionario-select:checked").map(function () {
                    return $(this).val();
                }).get();
            }

            function fillExportSelection(ids) {
                exportSelecionadosWrap.innerHTML = "";
                ids.forEach(function (id) {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "funcionarios[]";
                    input.value = id;
                    exportSelecionadosWrap.appendChild(input);
                });
            }

            function submitExport(tipo, formato, ids) {
                exportTipoInput.value = tipo;
                exportFormatoInput.value = formato;
                fillExportSelection(ids);
                exportForm.submit();
            }

            $(".js-export-selecionados").on("click", function () {
                var ids = selectedFuncionarioIds();
                if (!ids.length) {
                    alert("Selecione pelo menos um funcionário.");
                    return;
                }
                submitExport($(this).data("tipo"), $(this).data("formato"), ids);
            });

            $(".js-export-picagens").on("click", function () {
                pendingPicagensFormato = $(this).data("formato") || "pdf";
                $("#modalExportPicagens").modal("show");
            });

            $("#picagensPeriodo").on("change", function () {
                $("#picagensIntervaloPersonalizado").toggleClass("d-none", this.value !== "personalizado");
            });

            $("#confirmarExportPicagens").on("click", function () {
                var periodo = $("#picagensPeriodo").val();
                exportTipoPeriodoInput.value = periodo;
                exportDataInicioInput.value = $("#picagensDataInicio").val();
                exportDataFimInput.value = $("#picagensDataFim").val();

                if (periodo === "personalizado" && (!$("#picagensDataInicio").val() || !$("#picagensDataFim").val())) {
                    alert("Indique a data de início e de fim.");
                    return;
                }

                submitExport("picagens", pendingPicagensFormato, selectedFuncionarioIds());
                $("#modalExportPicagens").modal("hide");
            });

            var arquivarForm = document.getElementById("formArquivarFuncionario");
            var arquivarIdInput = document.getElementById("arquivarFuncionarioId");
            var arquivarNomeEl = document.getElementById("arquivarFuncionarioNome");

            $(".js-arquivar-funcionario").on("click", function () {
                arquivarIdInput.value = $(this).data("id");
                arquivarNomeEl.textContent = $(this).data("nome") || "este funcionário";
                $("#modalArquivarFuncionario").modal("show");
            });

            $("#confirmarArquivarFuncionario").on("click", function () {
                if (arquivarForm && arquivarIdInput.value) {
                    arquivarForm.submit();
                }
            });

            var restaurarForm = document.getElementById("formRestaurarFuncionario");
            var restaurarIdInput = document.getElementById("restaurarFuncionarioId");
            var restaurarNomeEl = document.getElementById("restaurarFuncionarioNome");

            $(".js-restaurar-funcionario").on("click", function () {
                restaurarIdInput.value = $(this).data("id");
                restaurarNomeEl.textContent = $(this).data("nome") || "este funcionário";
                $("#modalRestaurarFuncionario").modal("show");
            });

            $("#confirmarRestaurarFuncionario").on("click", function () {
                if (restaurarForm && restaurarIdInput.value) {
                    restaurarForm.submit();
                }
            });

            var eliminarForm = document.getElementById("formEliminarPermanente");
            var eliminarIdInput = document.getElementById("eliminarPermanenteId");
            var eliminarNomeEl = document.getElementById("eliminarPermanenteNome");
            var eliminarAvisoHistorico = document.getElementById("eliminarPermanenteAvisoHistorico");

            $(".js-eliminar-permanente").on("click", function () {
                eliminarIdInput.value = $(this).data("id");
                eliminarNomeEl.textContent = $(this).data("nome") || "este funcionário";
                eliminarAvisoHistorico.style.display = $(this).data("tem-historico") === 1 || $(this).data("tem-historico") === "1" ? "block" : "none";
                $("#modalEliminarPermanente").modal("show");
            });

            $("#confirmarEliminarPermanente").on("click", function () {
                if (eliminarForm && eliminarIdInput.value) {
                    eliminarForm.submit();
                }
            });

            $(".js-funcionario-form").on("submit", function (event) {
                var form = this;
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add("was-validated");
            });

            var tabelaFuncionarios = $(\'#tabela-funcionarios\').DataTable({
                pageLength: 10,
                order: [[2, "asc"]],
                columnDefs: [{ orderable: false, targets: [0, 7] }],
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: ' . json_encode($dtZeroRecords, JSON_UNESCAPED_UNICODE) . ',
                    paginate: {
                        first: \'Primeiro\',
                        last: \'Último\',
                        next: \'Seguinte\',
                        previous: \'Anterior\'
                    }
                }
            });

            $("#selecionarTodosFuncionarios").on("change", function () {
                var checked = this.checked;
                tabelaFuncionarios.rows({ search: "applied" }).nodes().to$().find(".funcionario-select").prop("checked", checked);
            });

            if (window.initFuncionarioFotoPreview) {
                initFuncionarioFotoPreview({
                    previewId: \'funcionarioFotoPreview\',
                    inputId: \'funcionarioFotoInput\',
                    nomeInput: \'#modalCriarFuncionario input[name="nome"]\'
                });
' . $editFotoInits . '            }
        });
    </script>';
include __DIR__ . '/includes/layout-end.php';
