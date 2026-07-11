<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/ponto_ingest.php';
require_page_permission();

ponto_ensure_device_schema($conn);

$temDispositivos = fe_table_exists($conn, 'dispositivos');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $temDispositivos) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $tipo = nullable_text($_POST['tipo'] ?? 'biometrico');
        $numeroSerie = get_post_value('numero_serie');
        $localizacao = nullable_text($_POST['localizacao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $token = ponto_gerar_token();

        if ($nome === '' || $numeroSerie === '') {
            admin_redirect_msg('dispositivos', 'danger', 'Preencha o nome e o número de série do relógio.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]{2,64}$/', $numeroSerie)) {
            admin_redirect_msg('dispositivos', 'danger', 'O número de série deve ter 2–64 caracteres (letras, números, ponto, hífen ou underscore).');
        }

        $stmt = mysqli_prepare($conn, 'INSERT INTO dispositivos (nome, tipo, numero_serie, localizacao, api_token, ativo) VALUES (?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'sssssi', $nome, $tipo, $numeroSerie, $localizacao, $token, $ativo);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            admin_redirect_msg('dispositivos', 'danger', 'Não foi possível criar o dispositivo. Verifique se o número de série já existe.');
        }
        mysqli_stmt_close($stmt);
        admin_redirect_msg('dispositivos', 'success', 'Dispositivo registado com sucesso.');
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $tipo = nullable_text($_POST['tipo'] ?? 'biometrico');
        $numeroSerie = get_post_value('numero_serie');
        $localizacao = nullable_text($_POST['localizacao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '' || $numeroSerie === '') {
            admin_redirect_msg('dispositivos', 'danger', 'Preencha os campos obrigatórios.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]{2,64}$/', $numeroSerie)) {
            admin_redirect_msg('dispositivos', 'danger', 'O número de série deve ter 2–64 caracteres (letras, números, ponto, hífen ou underscore).');
        }

        $stmt = mysqli_prepare($conn, 'UPDATE dispositivos SET nome = ?, tipo = ?, numero_serie = ?, localizacao = ?, ativo = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssssii', $nome, $tipo, $numeroSerie, $localizacao, $ativo, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            admin_redirect_msg('dispositivos', 'danger', 'Não foi possível atualizar o dispositivo.');
        }
        mysqli_stmt_close($stmt);
        admin_redirect_msg('dispositivos', 'success', 'Dispositivo atualizado com sucesso.');
    }

    if ($acao === 'regenerar_token') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            admin_redirect_msg('dispositivos', 'danger', 'Dispositivo inválido.');
        }
        $token = ponto_gerar_token();
        $stmt = mysqli_prepare($conn, 'UPDATE dispositivos SET api_token = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $token, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        admin_redirect_msg('dispositivos', 'success', 'Token do dispositivo regenerado.');
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            admin_redirect_msg('dispositivos', 'danger', 'Dispositivo inválido.');
        }
        $stmt = mysqli_prepare($conn, 'DELETE FROM dispositivos WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        admin_redirect_msg('dispositivos', 'success', 'Dispositivo removido.');
    }
}

$dispositivos = [];
if ($temDispositivos) {
    $result = mysqli_query($conn, 'SELECT * FROM dispositivos ORDER BY ativo DESC, nome ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $dispositivos[] = $row;
        }
    }
}

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Relógios de Ponto';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
    <h3 class="fw-bold mb-3">Relógios de Ponto</h3>
    <ul class="breadcrumbs mb-3">
        <li class="nav-home"><a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a></li>
        <li class="separator"><i class="icon-arrow-right"></i></li>
        <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('dispositivos')); ?>">Dispositivos</a></li>
    </ul>
</div>

<?php render_flash_alert(); ?>

<?php if ($temDispositivos): ?>
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <h4 class="card-title mb-0">Dispositivos registados</h4>
            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#modalCriarDispositivo">
                <i class="fe fe-plus"></i> Adicionar relógio
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabela-dispositivos" class="display table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>N.º série (SN)</th>
                            <th>Localização</th>
                            <th>Último contacto</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dispositivos as $dispositivo): ?>
                            <tr>
                                <td class="font-weight-bold"><?php echo e($dispositivo['nome']); ?></td>
                                <td><code><?php echo e($dispositivo['numero_serie']); ?></code></td>
                                <td><?php echo e($dispositivo['localizacao'] ?: '-'); ?></td>
                                <td>
                                    <?php if (!empty($dispositivo['ultimo_contacto_at'])): ?>
                                        <?php echo e(date('d/m/Y H:i', strtotime($dispositivo['ultimo_contacto_at']))); ?>
                                        <?php if (!empty($dispositivo['ip_ultimo'])): ?>
                                            <small class="text-muted d-block"><?php echo e($dispositivo['ip_ultimo']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo (int) $dispositivo['ativo'] === 1 ? 'success' : 'secondary'; ?>">
                                        <?php echo (int) $dispositivo['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modalEditarDispositivo"
                                        data-id="<?php echo (int) $dispositivo['id']; ?>"
                                        data-nome="<?php echo e($dispositivo['nome']); ?>"
                                        data-tipo="<?php echo e($dispositivo['tipo'] ?? 'biometrico'); ?>"
                                        data-serie="<?php echo e($dispositivo['numero_serie']); ?>"
                                        data-localizacao="<?php echo e($dispositivo['localizacao'] ?? ''); ?>"
                                        data-ativo="<?php echo (int) $dispositivo['ativo']; ?>"
                                        data-token="<?php echo e($dispositivo['api_token'] ?? ''); ?>">
                                        Editar
                                    </button>
                                    <form method="post" action="<?php echo htmlspecialchars(admin_url('dispositivos')); ?>" class="d-inline" onsubmit="return confirm('Regenerar o token deste dispositivo?');">
                                        <input type="hidden" name="acao" value="regenerar_token">
                                        <input type="hidden" name="id" value="<?php echo (int) $dispositivo['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Novo token</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCriarDispositivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('dispositivos')); ?>" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar relógio</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Ex: Entrada principal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de série (SN) *</label>
                        <input type="text" name="numero_serie" class="form-control" required placeholder="Ex: ABC123456">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" name="tipo" class="form-control" value="biometrico">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Localização</label>
                        <input type="text" name="localizacao" class="form-control" placeholder="Ex: Receção">
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="criarAtivo" name="ativo" checked>
                        <label class="custom-control-label" for="criarAtivo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalEditarDispositivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('dispositivos')); ?>" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editarId">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Editar relógio</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="editarNome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de série (SN) *</label>
                        <input type="text" name="numero_serie" id="editarSerie" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" name="tipo" id="editarTipo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Localização</label>
                        <input type="text" name="localizacao" id="editarLocalizacao" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Token API</label>
                        <input type="text" id="editarToken" class="form-control" readonly>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="editarAtivo" name="ativo">
                        <label class="custom-control-label" for="editarAtivo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <button type="button" class="btn btn-outline-danger" id="btnRemoverDispositivo">Remover relógio</button>
                    <div>
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
            <form method="post" action="<?php echo htmlspecialchars(admin_url('dispositivos')); ?>" id="formRemoverDispositivo" class="d-none">
                <input type="hidden" name="acao" value="remover">
                <input type="hidden" name="id" id="removerId">
            </form>
        </div>
    </div>
<?php endif; ?>

</div>
    </div>
<?php
$pageScripts = '<script>
$(function () {
    $("#tabela-dispositivos").DataTable({
        pageLength: 25,
        order: [[0, "asc"]],
        language: {
            search: "Pesquisar:",
            lengthMenu: "Mostrar _MENU_ registos",
            info: "A mostrar _START_ a _END_ de _TOTAL_ registos",
            infoEmpty: "Sem registos",
            zeroRecords: "Nenhum dispositivo encontrado"
        }
    });

    $("#modalEditarDispositivo").on("show.bs.modal", function (event) {
        var button = $(event.relatedTarget);
        $("#editarId").val(button.data("id"));
        $("#editarNome").val(button.data("nome"));
        $("#editarTipo").val(button.data("tipo"));
        $("#editarSerie").val(button.data("serie"));
        $("#editarLocalizacao").val(button.data("localizacao"));
        $("#editarToken").val(button.data("token"));
        $("#editarAtivo").prop("checked", parseInt(button.data("ativo"), 10) === 1);
        $("#removerId").val(button.data("id"));
    });

    $("#btnRemoverDispositivo").on("click", function () {
        if (confirm("Tem a certeza que pretende remover este relógio?")) {
            $("#formRemoverDispositivo").submit();
        }
    });
});
</script>';
include __DIR__ . '/includes/layout-end.php';
