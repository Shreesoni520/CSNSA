<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_page_permission();

function equipa_setor_padrao($conn)
{
    if (!fe_table_exists($conn, 'setores')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM setores WHERE ativo = 1 ORDER BY id ASC LIMIT 1');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return isset($row['id']) ? (int) $row['id'] : null;
}

$temEquipas = fe_table_exists($conn, 'equipas');
$temSetorEquipa = $temEquipas && fe_column_exists($conn, 'equipas', 'setor_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            admin_redirect_msg('departamentos', 'danger', 'Preencha o nome da equipa.');
        }

        try {
            if ($temSetorEquipa) {
                $setorPadraoId = equipa_setor_padrao($conn);
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (setor_id, nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isssi', $setorPadraoId, $nome, $codigo, $descricao, $ativo);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sssi', $nome, $codigo, $descricao, $ativo);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('departamentos', 'success', 'Equipa criada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('departamentos', 'danger', 'Não foi possível criar a equipa. Verifique se o código já existe.');
        }
    } elseif ($acao === 'editar') {
        $id = (int) ($_POST['equipa_id'] ?? $_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0) {
            admin_redirect_msg('departamentos', 'danger', 'Equipa inválida. Feche o modal e tente editar novamente.');
        }

        if ($nome === '') {
            admin_redirect_msg('departamentos', 'danger', 'Preencha o nome da equipa.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM equipas WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$existe) {
            admin_redirect_msg('departamentos', 'danger', 'Equipa não encontrada.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE equipas SET nome = ?, codigo = ?, descricao = ?, ativo = ? WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'sssii', $nome, $codigo, $descricao, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('departamentos', 'success', 'Equipa atualizada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('departamentos', 'danger', 'Não foi possível atualizar a equipa. Verifique se o código já existe.');
        }
    } elseif ($acao === 'atribuir_equipa') {
        $equipaId = (int) ($_POST['equipa_id'] ?? 0);
        $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);

        if ($equipaId <= 0 || $funcionarioId <= 0) {
            admin_redirect_msg('departamentos', 'danger', 'Selecione a equipa e o funcionário.');
        }

        if (!fe_column_exists($conn, 'funcionarios', 'equipa_id')) {
            admin_redirect_msg('departamentos', 'danger', 'A base de dados não suporta equipas em funcionários.');
        }

        $stmtCheck = mysqli_prepare($conn, "SELECT id, equipa_id FROM funcionarios WHERE id = ? AND estado NOT IN ('arquivado') LIMIT 1");
        mysqli_stmt_bind_param($stmtCheck, 'i', $funcionarioId);
        mysqli_stmt_execute($stmtCheck);
        $funcRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCheck));
        mysqli_stmt_close($stmtCheck);

        if (!$funcRow) {
            admin_redirect_msg('departamentos', 'danger', 'Funcionário inválido ou arquivado.');
        }

        if ((int) ($funcRow['equipa_id'] ?? 0) === $equipaId) {
            admin_redirect_msg('departamentos', 'warning', 'Este funcionário já pertence a esta equipa.');
        }

        $stmt = mysqli_prepare($conn, 'UPDATE funcionarios SET equipa_id = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $equipaId, $funcionarioId);
        mysqli_stmt_execute($stmt);
        $afetados = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($afetados < 1) {
            admin_redirect_msg('departamentos', 'danger', 'Não foi possível atribuir o funcionário à equipa.');
        }

        admin_redirect_msg('departamentos', 'success', 'Funcionário atribuído à equipa com sucesso.');
    } elseif ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            admin_redirect_msg('departamentos', 'danger', 'Equipa inválida.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM funcionarios WHERE equipa_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            admin_redirect_msg('departamentos', 'danger', 'Não é possível remover esta equipa porque existem funcionários associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM equipas WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('departamentos', 'success', 'Equipa removida com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('departamentos', 'danger', 'Não foi possível remover a equipa.');
        }
    }
}

$departamentos = [];
$sql = "SELECT
            d.id,
            d.nome,
            d.codigo,
            d.descricao,
            d.ativo,
            d.created_at,
            COUNT(u.id) AS total_funcionarios
        FROM equipas d
        LEFT JOIN funcionarios u ON u.equipa_id = d.id
        GROUP BY d.id, d.nome, d.codigo, d.descricao, d.ativo, d.created_at
        ORDER BY d.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    admin_redirect_msg('departamentos', 'danger', 'Nao foi possivel carregar as equipas.');
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $departamentos[] = $row;
}
mysqli_stmt_close($stmt);

$funcionariosAtivos = [];
if (fe_table_exists($conn, 'funcionarios')) {
    $stmt = mysqli_prepare($conn, "SELECT id, nome, numero_mecanografico, equipa_id FROM funcionarios WHERE estado NOT IN ('arquivado') ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionariosAtivos[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$membrosPorEquipa = [];
foreach ($funcionariosAtivos as $func) {
    $eid = (int) ($func['equipa_id'] ?? 0);
    if ($eid > 0) {
        $membrosPorEquipa[$eid][] = $func;
    }
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Equipas';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Equipas</h3>
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
                                <a href="<?php echo htmlspecialchars(admin_url('departamentos')); ?>">Equipas</a>
                            </li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de equipas</h4>
                                <button class="btn btn-primary btn-round ml-auto" data-toggle="modal" data-target="#modalCriarEquipa">
                                    <i class="fa fa-plus"></i>
                                    Adicionar equipa
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-equipas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Funcionários</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead> 
                                    <tbody>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <tr>
                                                <td><?php echo e($departamento['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($departamento['nome']); ?></td>
                                                <td><?php echo e($departamento['descricao'] ?: '-'); ?></td>
                                                <td><?php echo (int) $departamento['total_funcionarios']; ?></td>
                                                <td>
                                                    <?php if ((int) $departamento['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button"
                                                            class="btn btn-link btn-primary btn-lg js-editar-equipa"
                                                            title="Editar"
                                                            data-id="<?php echo (int) $departamento['id']; ?>">
                                                            <span class="fe fe-edit"></span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                
</div>
    </div>

    <div class="modal fade" id="modalCriarEquipa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('departamentos')); ?>" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar equipa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                        <div class="invalid-feedback">Indique o nome da equipa.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" id="criarAtivo" checked>
                        <label class="form-check-label" for="criarAtivo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalEditarEquipa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('departamentos')); ?>" id="formEditarEquipa" class="needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="equipa_id" id="editarEquipaId" value="">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar equipa</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" id="editarEquipaNome" class="form-control" required>
                            <div class="invalid-feedback">Indique o nome da equipa.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" id="editarEquipaCodigo" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" id="editarEquipaDescricao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="ativo" id="editarEquipaAtivo">
                            <label class="form-check-label" for="editarEquipaAtivo">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 flex-wrap">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-danger ml-auto d-none" id="btnMostrarEliminarEquipa">Eliminar equipa</button>
                    </div>
                </form>
                <div class="border-top px-3 py-3">
                    <h6 class="mb-2">Membros da equipa</h6>
                    <ul class="list-unstyled small mb-3" id="editarEquipaMembros"></ul>
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('departamentos')); ?>" class="row g-2 align-items-end">
                        <input type="hidden" name="acao" value="atribuir_equipa">
                        <input type="hidden" name="equipa_id" id="editarEquipaAtribuirId" value="">
                        <div class="col-md-8">
                            <label class="form-label">Atribuir funcionário</label>
                            <select name="funcionario_id" class="form-control" required>
                                <option value="">Selecionar...</option>
                                <?php foreach ($funcionariosAtivos as $func): ?>
                                    <option value="<?php echo (int) $func['id']; ?>"><?php echo e($func['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-outline-primary w-100">Atribuir</button>
                        </div>
                    </form>
                </div>
                <div class="collapse border-top px-3 pb-3 d-none" id="painelEliminarEquipa">
                    <form method="post" action="<?php echo htmlspecialchars(admin_url('departamentos')); ?>" class="pt-3" onsubmit="return confirm('Tem a certeza que pretende eliminar esta equipa?');">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="id" id="eliminarEquipaId" value="">
                        <button type="submit" class="btn btn-danger btn-sm">Confirmar eliminação</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
$equipasParaJs = [];
foreach ($departamentos as $departamento) {
    $id = (int) $departamento['id'];
    $equipasParaJs[$id] = [
        'id' => $id,
        'nome' => $departamento['nome'],
        'codigo' => $departamento['codigo'] ?? '',
        'descricao' => $departamento['descricao'] ?? '',
        'ativo' => (int) $departamento['ativo'],
        'total_funcionarios' => (int) $departamento['total_funcionarios'],
    ];
}
$equipasJson = json_encode($equipasParaJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$membrosJson = json_encode($membrosPorEquipa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$pageScripts = '<script>
        var equipasData = ' . $equipasJson . ';
        var equipaMembros = ' . $membrosJson . ';

        $(document).ready(function () {
            var tabelaEquipas = $(\'#tabela-equipas\').DataTable({
                pageLength: 10,
                columnDefs: [{ orderable: false, targets: [5] }],
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: \'Nenhuma equipa encontrada\',
                    paginate: {
                        first: \'Primeiro\',
                        last: \'Último\',
                        next: \'Seguinte\',
                        previous: \'Anterior\'
                    }
                }
            });

            $(\'#tabela-equipas tbody\').on(\'click\', \'.js-editar-equipa\', function () {
                var id = parseInt($(this).attr(\'data-id\'), 10);
                var equipa = equipasData[id];
                if (!equipa || !id) {
                    alert(\'Não foi possível carregar esta equipa. Atualize a página.\');
                    return;
                }

                var membros = equipaMembros[id] || equipaMembros[String(id)] || [];

                $(\'#editarEquipaId\').val(id);
                $(\'#editarEquipaAtribuirId\').val(id);
                $(\'#eliminarEquipaId\').val(id);
                $(\'#editarEquipaNome\').val(equipa.nome || \'\');
                $(\'#editarEquipaCodigo\').val(equipa.codigo || \'\');
                $(\'#editarEquipaDescricao\').val(equipa.descricao || \'\');
                $(\'#editarEquipaAtivo\').prop(\'checked\', parseInt(equipa.ativo, 10) === 1);

                var lista = $(\'#editarEquipaMembros\');
                lista.empty();
                if (!membros.length) {
                    lista.append(\'<li class="text-muted">Nenhum funcionário nesta equipa.</li>\');
                } else {
                    membros.forEach(function (m) {
                        var extra = m.numero_mecanografico ? \' <span class="text-muted">(\' + m.numero_mecanografico + \')</span>\' : \'\';
                        lista.append(\'<li>\' + m.nome + extra + \'</li>\');
                    });
                }

                var podeEliminar = parseInt(equipa.total_funcionarios, 10) === 0;
                $(\'#btnMostrarEliminarEquipa\').toggleClass(\'d-none\', !podeEliminar);
                $(\'#painelEliminarEquipa\').addClass(\'d-none\').removeClass(\'show\');

                $(\'#formEditarEquipa\').removeClass(\'was-validated\');
                $(\'#modalEditarEquipa\').modal(\'show\');
            });

            $(\'#btnMostrarEliminarEquipa\').on(\'click\', function () {
                $(\'#painelEliminarEquipa\').toggleClass(\'d-none\').toggleClass(\'show\');
            });

            $(\'#formEditarEquipa\').on(\'submit\', function (event) {
                var equipaId = parseInt($(\'#editarEquipaId\').val(), 10);
                if (!equipaId) {
                    event.preventDefault();
                    alert(\'Equipa inválida. Feche o modal e clique em editar novamente.\');
                    return false;
                }
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                $(this).addClass(\'was-validated\');
            });

            $(\'#modalCriarEquipa form\').on(\'submit\', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                $(this).addClass(\'was-validated\');
            });
        });
    </script>';
include __DIR__ . '/includes/layout-end.php';
