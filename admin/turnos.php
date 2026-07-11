<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_page_permission();

function dia_semana_nome($dia)
{
    $dias = [
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    return $dias[(int) $dia] ?? 'Todos os dias';
}

$temTabelaFuncionarios = fe_table_exists($conn, 'funcionarios');
$temFuncionarioHorario = fe_table_exists($conn, 'horarios_turno') && fe_column_exists($conn, 'horarios_turno', 'funcionario_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $horaEntrada = get_post_value('hora_entrada');
        $horaSaida = get_post_value('hora_saida');
        $inicioPausa = nullable_time($_POST['inicio_pausa'] ?? '');
        $fimPausa = nullable_time($_POST['fim_pausa'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = isset($_POST['turno_noturno']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $horaEntrada === '' || $horaSaida === '') {
            admin_redirect_msg('turnos', 'danger', 'Preencha o nome, hora de entrada e hora de saída.');
        }

        if (($inicioPausa === null && $fimPausa !== null) || ($inicioPausa !== null && $fimPausa === null)) {
            admin_redirect_msg('turnos', 'danger', 'Preencha o início e o fim da pausa, ou deixe ambos vazios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO turnos (nome, codigo, hora_entrada, hora_saida, inicio_pausa, fim_pausa, tolerancia_entrada_min, tolerancia_saida_min, horas_previstas, turno_noturno, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssssiidii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('turnos', 'success', 'Turno criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('turnos', 'danger', 'Não foi possível criar o turno. Verifique se o código já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $horaEntrada = get_post_value('hora_entrada');
        $horaSaida = get_post_value('hora_saida');
        $inicioPausa = nullable_time($_POST['inicio_pausa'] ?? '');
        $fimPausa = nullable_time($_POST['fim_pausa'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = isset($_POST['turno_noturno']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '' || $horaEntrada === '' || $horaSaida === '') {
            admin_redirect_msg('turnos', 'danger', 'Preencha os campos obrigatórios.');
        }

        if (($inicioPausa === null && $fimPausa !== null) || ($inicioPausa !== null && $fimPausa === null)) {
            admin_redirect_msg('turnos', 'danger', 'Preencha o início e o fim da pausa, ou deixe ambos vazios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE turnos SET nome = ?, codigo = ?, hora_entrada = ?, hora_saida = ?, inicio_pausa = ?, fim_pausa = ?, tolerancia_entrada_min = ?, tolerancia_saida_min = ?, horas_previstas = ?, turno_noturno = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssssssiidiii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('turnos', 'success', 'Turno atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('turnos', 'danger', 'Não foi possível atualizar o turno. Verifique se o código já existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            admin_redirect_msg('turnos', 'danger', 'Turno inválido.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM horarios_turno WHERE turno_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            admin_redirect_msg('turnos', 'danger', 'Não é possível remover este turno porque existem funcionários associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM turnos WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('turnos', 'success', 'Turno removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('turnos', 'danger', 'Não foi possível remover o turno.');
        }
    }

    if ($acao === 'associar_equipa') {
        if (!$temTabelaFuncionarios || !$temFuncionarioHorario) {
            admin_redirect_msg('turnos', 'danger', 'Importe o ficheiro csnsa.sql na base de dados antes de associar turnos.');
        }

        $turnoId = (int) ($_POST['turno_id'] ?? 0);
        $equipaId = (int) ($_POST['equipa_id'] ?? 0);
        $diaSemana = nullable_int($_POST['dia_semana'] ?? '');
        $dataInicio = get_post_value('data_inicio');
        $dataFim = get_post_value('data_fim') ?: null;

        if ($turnoId <= 0 || $equipaId <= 0 || $dataInicio === '') {
            admin_redirect_msg('turnos', 'danger', 'Preencha a equipa e a data de início.');
        }

        if ($dataFim !== null && $dataFim < $dataInicio) {
            admin_redirect_msg('turnos', 'danger', 'A data fim não pode ser anterior a data início.');
        }

        $stmt = mysqli_prepare($conn, "SELECT id FROM funcionarios WHERE equipa_id = ? AND estado = 'ativo'");
        mysqli_stmt_bind_param($stmt, 'i', $equipaId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total = 0;
        $temEquipaCol = fe_column_exists($conn, 'horarios_turno', 'equipa_id');

        while ($funcionario = mysqli_fetch_assoc($result)) {
            $funcionarioId = (int) $funcionario['id'];
            $stmtF = mysqli_prepare($conn, 'SELECT utilizador_id FROM funcionarios WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmtF, 'i', $funcionarioId);
            mysqli_stmt_execute($stmtF);
            $resF = mysqli_stmt_get_result($stmtF);
            $rowF = mysqli_fetch_assoc($resF);
            mysqli_stmt_close($stmtF);
            $utilizadorId = $rowF['utilizador_id'] === null ? null : (int) $rowF['utilizador_id'];

            if ($temEquipaCol) {
                $stmtI = mysqli_prepare($conn, 'INSERT INTO horarios_turno (funcionario_id, utilizador_id, turno_id, equipa_id, data_inicio, data_fim, dia_semana, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmtI, 'iiiissi', $funcionarioId, $utilizadorId, $turnoId, $equipaId, $dataInicio, $dataFim, $diaSemana);
            } else {
                $stmtI = mysqli_prepare($conn, 'INSERT INTO horarios_turno (funcionario_id, utilizador_id, turno_id, data_inicio, data_fim, dia_semana, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)');
                mysqli_stmt_bind_param($stmtI, 'iiissi', $funcionarioId, $utilizadorId, $turnoId, $dataInicio, $dataFim, $diaSemana);
            }
            mysqli_stmt_execute($stmtI);
            mysqli_stmt_close($stmtI);
            $total++;
        }
        mysqli_stmt_close($stmt);

        if ($total === 0) {
            admin_redirect_msg('turnos', 'warning', 'Nenhum funcionário ativo encontrado nesta equipa.');
        }

        admin_redirect_msg('turnos', 'success', "Turno associado a {$total} funcionário(s) da equipa.");
    }

    if ($acao === 'associar') {
        if (!$temTabelaFuncionarios || !$temFuncionarioHorario) {
            admin_redirect_msg('turnos', 'danger', 'Importe o ficheiro csnsa.sql na base de dados antes de associar turnos a funcionários.');
        }

        $turnoId = (int) ($_POST['turno_id'] ?? 0);
        $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
        $diaSemana = nullable_int($_POST['dia_semana'] ?? '');
        $dataInicio = get_post_value('data_inicio');
        $dataFim = get_post_value('data_fim') ?: null;

        if ($turnoId <= 0 || $funcionarioId <= 0 || $dataInicio === '') {
            admin_redirect_msg('turnos', 'danger', 'Preencha o funcionário e a data de início.');
        }

        if ($diaSemana !== null && ($diaSemana < 1 || $diaSemana > 7)) {
            admin_redirect_msg('turnos', 'danger', 'Dia da semana inválido.');
        }

        if ($dataFim !== null && $dataFim < $dataInicio) {
            admin_redirect_msg('turnos', 'danger', 'A data fim não pode ser anterior a data início.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'SELECT utilizador_id FROM funcionarios WHERE id = ? AND estado = "ativo" LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $funcionario = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$funcionario) {
                admin_redirect_msg('turnos', 'danger', 'Funcionário inválido ou inativo.');
            }

            $utilizadorId = $funcionario['utilizador_id'] === null ? null : (int) $funcionario['utilizador_id'];

            $stmt = mysqli_prepare($conn, 'INSERT INTO horarios_turno (funcionario_id, utilizador_id, turno_id, data_inicio, data_fim, dia_semana, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iiissi', $funcionarioId, $utilizadorId, $turnoId, $dataInicio, $dataFim, $diaSemana);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('turnos', 'success', 'Turno associado ao funcionário com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('turnos', 'danger', 'Não foi possível associar o turno ao funcionário. Importe o ficheiro csnsa.sql se a base de dados ainda não estiver atualizada.');
        }
    }

    if ($acao === 'remover_associacao') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            admin_redirect_msg('turnos', 'danger', 'Associação inválida.');
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM horarios_turno WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $removidos = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($removidos < 1) {
            admin_redirect_msg('turnos', 'danger', 'Associação não encontrada ou já removida.');
        }

        admin_redirect_msg('turnos', 'success', 'Associacao removida com sucesso.');
    }
}

$funcionarios = [];
if ($temTabelaFuncionarios) {
    $stmt = mysqli_prepare($conn, "SELECT id, nome, numero_mecanografico FROM funcionarios WHERE estado = 'ativo' ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$turnos = [];
$sql = "SELECT
            t.*,
            COUNT(ht.id) AS total_associacoes
        FROM turnos t
        LEFT JOIN horarios_turno ht ON ht.turno_id = t.id
        GROUP BY t.id
        ORDER BY t.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    admin_redirect_msg('turnos', 'danger', 'Nao foi possivel carregar os turnos.');
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $turnos[] = $row;
}
mysqli_stmt_close($stmt);

$equipas = [];
if (fe_table_exists($conn, 'equipas')) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $equipas[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$associacoes = [];
$associacaoNomeSelect = $temFuncionarioHorario ? 'f.nome AS funcionario_nome' : 'u.nome AS funcionario_nome';
$associacaoJoin = $temFuncionarioHorario
    ? 'INNER JOIN funcionarios f ON f.id = ht.funcionario_id LEFT JOIN utilizadores u ON u.id = ht.utilizador_id'
    : 'INNER JOIN utilizadores u ON u.id = ht.utilizador_id';

$sql = "SELECT ht.id, ht.turno_id, ht.data_inicio, ht.data_fim, ht.dia_semana, ht.ativo,
               $associacaoNomeSelect
        FROM horarios_turno ht
        $associacaoJoin
        ORDER BY ht.data_inicio DESC, funcionario_nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $associacoes[(int) $row['turno_id']][] = $row;
}
mysqli_stmt_close($stmt);

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Turnos';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Turnos</h3>
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
                                <a href="<?php echo htmlspecialchars(admin_url('turnos')); ?>">Turnos</a>
                            </li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de turnos</h4>
                                <button class="btn btn-primary btn-round ml-auto" data-toggle="modal" data-target="#modalCriarTurno">
                                    <i class="fa fa-plus"></i>
                                    Adicionar turno
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-turnos" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Entrada</th>
                                            <th>Saída</th>
                                            <th>Pausa</th>
                                            <th>Tolerância</th>
                                            <th>Associações</th>
                                            <th>Estado</th>
                                            <th style="width: 160px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($turnos as $turno): ?>
                                            <tr>
                                                <td><?php echo e($turno['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($turno['nome']); ?></td>
                                                <td><?php echo e(substr($turno['hora_entrada'], 0, 5)); ?></td>
                                                <td><?php echo e(substr($turno['hora_saida'], 0, 5)); ?></td>
                                                <td>
                                                    <?php if ($turno['inicio_pausa'] && $turno['fim_pausa']): ?>
                                                        <?php echo e(substr($turno['inicio_pausa'], 0, 5)); ?> - <?php echo e(substr($turno['fim_pausa'], 0, 5)); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int) $turno['tolerancia_entrada_min']; ?> min</td>
                                                <td><?php echo (int) $turno['total_associacoes']; ?></td>
                                                <td>
                                                    <?php if ((int) $turno['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-info btn-lg" data-toggle="modal" data-target="#modalAssociarTurno<?php echo (int) $turno['id']; ?>" title="Associar">
                                                            <span class="fe fe-user-plus"></span>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-toggle="modal" data-target="#modalEditarTurno<?php echo (int) $turno['id']; ?>" title="Editar">
                                                            <span class="fe fe-edit"></span>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-toggle="modal" data-target="#modalRemoverTurno<?php echo (int) $turno['id']; ?>" title="Remover">
                                                            <span class="fe fe-x"></span>
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

    <div class="modal fade" id="modalCriarTurno" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar turno</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php include __DIR__ . '/includes/turnos_form_campos.php'; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($turnos as $turno): ?>
        <div class="modal fade" id="modalEditarTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $turno['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar turno</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo e($turno['nome']); ?>" required>
                                <div class="invalid-feedback">Indique o nome do turno.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" name="codigo" class="form-control" value="<?php echo e($turno['codigo']); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora de entrada *</label>
                                <input type="time" name="hora_entrada" class="form-control" value="<?php echo e(substr($turno['hora_entrada'], 0, 5)); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora de saída *</label>
                                <input type="time" name="hora_saida" class="form-control" value="<?php echo e(substr($turno['hora_saida'], 0, 5)); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Início pausa</label>
                                <input type="time" name="inicio_pausa" class="form-control" value="<?php echo e($turno['inicio_pausa'] ? substr($turno['inicio_pausa'], 0, 5) : ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fim pausa</label>
                                <input type="time" name="fim_pausa" class="form-control" value="<?php echo e($turno['fim_pausa'] ? substr($turno['fim_pausa'], 0, 5) : ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tolerância de atraso (min)</label>
                                <input type="number" name="tolerancia_entrada_min" class="form-control" min="0" value="<?php echo (int) $turno['tolerancia_entrada_min']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tolerância de saída (min)</label>
                                <input type="number" name="tolerancia_saida_min" class="form-control" min="0" value="<?php echo (int) $turno['tolerancia_saida_min']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Horas previstas</label>
                                <input type="number" step="0.25" name="horas_previstas" class="form-control" min="0" value="<?php echo e($turno['horas_previstas']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="turno_noturno" id="editarNoturno<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['turno_noturno'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="editarNoturno<?php echo (int) $turno['id']; ?>">Turno noturno</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="editarAtivo<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['ativo'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="editarAtivo<?php echo (int) $turno['id']; ?>">Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalAssociarTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Associar turno: <?php echo e($turno['nome']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="needs-validation mb-4" novalidate>
                            <input type="hidden" name="acao" value="associar">
                            <input type="hidden" name="turno_id" value="<?php echo (int) $turno['id']; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Funcionário *</label>
                                    <select name="funcionario_id" class="form-control" required>
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
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dia da semana</label>
                                    <select name="dia_semana" class="form-control">
                                        <option value="">Todos os dias</option>
                                        <?php for ($dia = 1; $dia <= 7; $dia++): ?>
                                            <option value="<?php echo $dia; ?>"><?php echo e(dia_semana_nome($dia)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data início *</label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                    <div class="invalid-feedback">Indique a data de início.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <span class="fe fe-user-plus"></span>
                                Associar funcionário
                            </button>
                        </form>

                        <?php if (!empty($equipas)): ?>
                        <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="needs-validation mb-4 border-top pt-3" novalidate>
                            <input type="hidden" name="acao" value="associar_equipa">
                            <input type="hidden" name="turno_id" value="<?php echo (int) $turno['id']; ?>">
                            <h6 class="mb-3">Associar a equipa inteira</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Equipa *</label>
                                    <select name="equipa_id" class="form-control" required>
                                        <option value="">Selecionar equipa</option>
                                        <?php foreach ($equipas as $equipa): ?>
                                            <option value="<?php echo (int) $equipa['id']; ?>"><?php echo e($equipa['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dia da semana</label>
                                    <select name="dia_semana" class="form-control">
                                        <option value="">Todos os dias</option>
                                        <?php for ($dia = 1; $dia <= 7; $dia++): ?>
                                            <option value="<?php echo $dia; ?>"><?php echo e(dia_semana_nome($dia)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data início *</label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">
                                <span class="fe fe-users"></span>
                                Associar equipa
                            </button>
                        </form>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Dia</th>
                                        <th>Início</th>
                                        <th>Fim</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($associacoes[(int) $turno['id']] ?? []) as $associacao): ?>
                                        <tr>
                                            <td><?php echo e($associacao['funcionario_nome']); ?></td>
                                            <td><?php echo e(dia_semana_nome($associacao['dia_semana'])); ?></td>
                                            <td><?php echo e(date('d/m/Y', strtotime($associacao['data_inicio']))); ?></td>
                                            <td><?php echo $associacao['data_fim'] ? e(date('d/m/Y', strtotime($associacao['data_fim']))) : '-'; ?></td>
                                            <td class="text-end">
                                                <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" onsubmit="return confirm('Remover esta associação?');">
                                                    <input type="hidden" name="acao" value="remover_associacao">
                                                    <input type="hidden" name="id" value="<?php echo (int) $associacao['id']; ?>">
                                                    <button type="submit" class="btn btn-link btn-danger btn-sm">
                                                        <span class="fe fe-x"></span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($associacoes[(int) $turno['id']])): ?>
                                        <tr>
                                            <td colspan="5" class="text-muted">Sem associações registadas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('turnos')); ?>" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $turno['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover turno</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php if ((int) $turno['total_associacoes'] > 0): ?>
                            <p class="mb-0">
                                Este turno tem <strong><?php echo (int) $turno['total_associacoes']; ?></strong>
                                associação(ões), por isso não pode ser removido.
                            </p>
                        <?php else: ?>
                            <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($turno['nome']); ?></strong>?</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ((int) $turno['total_associacoes'] === 0): ?>
                            <button type="submit" class="btn btn-danger">Remover</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php
$pageScripts = '<script>
        $(document).ready(function () {
            $(\'#tabela-turnos\').DataTable({
                pageLength: 10,
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: \'Nenhum turno encontrado\',
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
