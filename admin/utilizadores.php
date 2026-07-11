<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/permissions.php';
require_page_permission('utilizadores');

$utilizadorSessao = current_user();
$utilizadorSessaoId = current_utilizador_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $estado = get_post_value('estado') ?: 'ativo';
        $papelId = nullable_int($_POST['papel_id'] ?? '');

        if ($nome === '' || $email === '' || $password === '') {
            admin_redirect_msg('utilizadores', 'danger', 'Preencha nome, email e palavra-passe.');
        }

        if ($papelId === null) {
            admin_redirect_msg('utilizadores', 'danger', 'Selecione um papel para o utilizador.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            admin_redirect_msg('utilizadores', 'danger', 'Introduza um email válido.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO utilizadores (nome, email, password_hash, estado) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $nome, $email, $passwordHash, $estado);
            mysqli_stmt_execute($stmt);
            $novoUtilizadorId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if ($papelId !== null) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ii', $novoUtilizadorId, $papelId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            sync_user_role_with_papel($email, $papelId);

            mysqli_commit($conn);
            admin_redirect_msg('utilizadores', 'success', 'Utilizador criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            admin_redirect_msg('utilizadores', 'danger', 'Não foi possível criar o utilizador. Verifique se o email já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $estado = get_post_value('estado') ?: 'ativo';
        $papelId = nullable_int($_POST['papel_id'] ?? '');

        if ($id <= 0 || $nome === '' || $email === '') {
            admin_redirect_msg('utilizadores', 'danger', 'Preencha nome e email.');
        }

        if ($papelId === null || $papelId <= 0) {
            admin_redirect_msg('utilizadores', 'danger', 'Selecione um papel para o utilizador.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            admin_redirect_msg('utilizadores', 'danger', 'Introduza um email válido.');
        }

        mysqli_begin_transaction($conn);

        try {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, password_hash = ?, estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssssi', $nome, $email, $passwordHash, $estado, $id);
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET nome = ?, email = ?, estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'sssi', $nome, $email, $estado, $id);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizador_papeis WHERE utilizador_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($papelId !== null) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ii', $id, $papelId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            sync_user_role_with_papel($email, $papelId);

            mysqli_commit($conn);
            admin_redirect_msg('utilizadores', 'success', 'Utilizador atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            admin_redirect_msg('utilizadores', 'danger', 'Não foi possível atualizar o utilizador.');
        }
    }

    if ($acao === 'guardar_permissoes_papel') {
        $papelId = (int) ($_POST['papel_id'] ?? 0);
        $permissoes = $_POST['permissoes'] ?? [];

        if ($papelId <= 0) {
            admin_redirect_msg('utilizadores', 'danger', 'Papel inválido.');
        }

        if (!is_array($permissoes)) {
            $permissoes = [];
        }

        $stmt = mysqli_prepare($conn, 'SELECT slug FROM papeis WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $papelId);
        mysqli_stmt_execute($stmt);
        $papelRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (($papelRow['slug'] ?? '') === 'administrador') {
            $permissoes = array_keys(permissoes_disponiveis());
        }

        permissoes_guardar_papel($conn, $papelId, $permissoes);
        admin_redirect_msg('utilizadores', 'success', 'Permissões do papel atualizadas.');
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            admin_redirect_msg('utilizadores', 'danger', 'Utilizador inválido.');
        }

        if ($id === $utilizadorSessaoId) {
            admin_redirect_msg('utilizadores', 'danger', 'Não pode remover o utilizador com sessão iniciada.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizadores WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            admin_redirect_msg('utilizadores', 'success', 'Utilizador removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            admin_redirect_msg('utilizadores', 'danger', 'Não foi possível remover este utilizador.');
        }
    }
}

$papeis = [];
$stmt = mysqli_prepare($conn, 'SELECT id, nome, slug, descricao FROM papeis WHERE ativo = 1 ORDER BY nome ASC');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $papeis[] = $row;
}
mysqli_stmt_close($stmt);

$permissoesPorPapel = [];
$labelsPermissoes = permissoes_disponiveis();
foreach ($papeis as $papel) {
    $permissoesPorPapel[(int) $papel['id']] = permissoes_carregar_papel($conn, (int) $papel['id']);
}

$utilizadores = [];
$sql = "SELECT u.id, u.nome, u.email, u.estado, u.ultimo_login_at,
               p.id AS papel_id, p.nome AS papel_nome
        FROM utilizadores u
        LEFT JOIN utilizador_papeis up ON up.utilizador_id = u.id
        LEFT JOIN papeis p ON p.id = up.papel_id
        ORDER BY u.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $utilizadores[] = $row;
}
mysqli_stmt_close($stmt);

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';

$pageTitle = 'Utilizadores';
$useDataTables = true;
$extraHead = <<<'HTML'
<style>
    .perm-permissoes,
    .perm-modal {
        --perm-surface: #ffffff;
        --perm-surface-alt: #f6f8fc;
        --perm-border: #e2e8f0;
        --perm-text: #2d3748;
        --perm-text-muted: #6c757d;
        --perm-badge-bg: rgba(27, 104, 255, 0.09);
        --perm-badge-text: #1a56c4;
        --perm-badge-border: rgba(27, 104, 255, 0.22);
        --perm-info-bg: rgba(27, 104, 255, 0.07);
        --perm-info-border: rgba(27, 104, 255, 0.18);
        --perm-info-icon: #1b68ff;
        --perm-accent: #1b68ff;
    }

    body.dark .perm-permissoes,
    body.dark .perm-modal {
        --perm-surface: #343a40;
        --perm-surface-alt: #2b3038;
        --perm-border: #454d57;
        --perm-text: #edf0f4;
        --perm-text-muted: #9aa3b2;
        --perm-badge-bg: rgba(72, 171, 247, 0.16);
        --perm-badge-text: #b8d4ff;
        --perm-badge-border: rgba(72, 171, 247, 0.32);
        --perm-info-bg: rgba(72, 171, 247, 0.11);
        --perm-info-border: rgba(72, 171, 247, 0.26);
        --perm-info-icon: #6ea8ff;
        --perm-accent: #6ea8ff;
    }

    .perm-info-box {
        background: var(--perm-info-bg);
        border: 1px solid var(--perm-info-border);
        border-radius: 0.5rem;
        padding: 1rem 1.15rem;
        color: var(--perm-text);
    }

    .perm-info-box strong {
        color: var(--perm-text);
        font-weight: 700;
    }

    .perm-info-box .perm-info-text {
        color: var(--perm-text-muted);
    }

    .perm-info-box .perm-info-icon {
        color: var(--perm-info-icon);
        flex-shrink: 0;
    }

    .perm-papel-card {
        background: var(--perm-surface-alt);
        border: 1px solid var(--perm-border);
        border-radius: 0.55rem;
        height: 100%;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .perm-papel-card:hover {
        border-color: var(--perm-info-border);
    }

    body.dark .perm-papel-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.22);
    }

    .perm-papel-card .card-title {
        color: var(--perm-text);
        font-weight: 700;
    }

    .perm-papel-card .perm-papel-desc {
        color: var(--perm-text-muted);
    }

    .perm-badge {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.3;
        padding: 0.38em 0.7em;
        border-radius: 999px;
        background: var(--perm-badge-bg);
        color: var(--perm-badge-text);
        border: 1px solid var(--perm-badge-border);
        margin: 0 0.3rem 0.35rem 0;
    }

    .perm-badge-empty {
        background: rgba(108, 117, 125, 0.14);
        color: var(--perm-text-muted);
        border-color: var(--perm-border);
    }

    body.dark .perm-badge-empty {
        background: rgba(108, 117, 125, 0.22);
    }

    .perm-papel-card .btn-outline-primary {
        border-color: var(--perm-accent);
        color: var(--perm-accent);
    }

    .perm-papel-card .btn-outline-primary:hover {
        background: var(--perm-badge-bg);
        color: var(--perm-accent);
        border-color: var(--perm-accent);
    }

    .perm-modal .perm-group-title {
        color: var(--perm-text-muted);
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    body.dark .perm-modal .custom-control-label {
        color: #d8dde6;
    }

    body.dark .perm-modal .custom-control-input:disabled ~ .custom-control-label {
        color: #8b95a5;
    }

    body.dark .perm-modal .alert-info {
        background: rgba(72, 171, 247, 0.14);
        border-color: rgba(72, 171, 247, 0.28);
        color: #c5dcff;
    }

    body.dark .perm-permissoes .text-muted {
        color: var(--perm-text-muted) !important;
    }
</style>
HTML;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
                        <h3 class="fw-bold mb-3">Utilizadores</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>">Acessos</a></li>
                        </ul>
                    </div>

                    <?php render_flash_alert(); ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Contas de acesso</h4>
                                <button class="btn btn-primary btn-round ml-auto" data-toggle="modal" data-target="#modalCriarUtilizador">
                                    <i class="fa fa-plus"></i>
                                    Novo utilizador
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-utilizadores" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Papel</th>
                                            <th>Último acesso</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($utilizadores as $utilizador): ?>
                                            <tr>
                                                <td><?php echo e($utilizador['nome']); ?></td>
                                                <td><?php echo e($utilizador['email']); ?></td>
                                                <td><?php echo e($utilizador['papel_nome'] ?: '-'); ?></td>
                                                <td><?php echo $utilizador['ultimo_login_at'] ? e(date('d/m/Y H:i', strtotime($utilizador['ultimo_login_at']))) : '-'; ?></td>
                                                <td>
                                                    <?php $badgeClass = $utilizador['estado'] === 'ativo' ? 'success' : ($utilizador['estado'] === 'suspenso' ? 'warning' : 'secondary'); ?>
                                                    <span class="badge badge-<?php echo e($badgeClass); ?>"><?php echo e(ucfirst($utilizador['estado'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-toggle="modal" data-target="#modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" title="Editar">
                                                            <span class="fe fe-edit"></span>
                                                        </button>
                                                        <?php if ((int) $utilizador['id'] !== $utilizadorSessaoId): ?>
                                                            <button type="button" class="btn btn-link btn-danger" data-toggle="modal" data-target="#modalRemoverUtilizador<?php echo (int) $utilizador['id']; ?>" title="Remover">
                                                                <span class="fe fe-x"></span>
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

                    <div class="card mt-4">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Permissões por papel</h4>
                        </div>
                        <div class="card-body perm-permissoes">
                            <?php if ($papeis !== []): ?>
                                <div class="row">
                                    <?php foreach ($papeis as $papel): ?>
                                        <?php
                                        $papelId = (int) $papel['id'];
                                        $permsPapel = $permissoesPorPapel[$papelId] ?? [];
                                        $isAdminPapel = ($papel['slug'] ?? '') === 'administrador';
                                        ?>
                                        <div class="col-lg-6 mb-3">
                                            <div class="perm-papel-card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <h5 class="card-title mb-1"><?php echo e($papel['nome']); ?></h5>
                                                            <?php if (!empty($papel['descricao'])): ?>
                                                                <p class="perm-papel-desc small mb-0"><?php echo e($papel['descricao']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($isAdminPapel): ?>
                                                            <span class="badge badge-primary">Acesso total</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <?php if ($permsPapel === []): ?>
                                                            <span class="perm-badge perm-badge-empty">Sem permissões definidas</span>
                                                        <?php else: ?>
                                                            <?php foreach ($permsPapel as $slug): ?>
                                                                <span class="perm-badge"><?php echo e($labelsPermissoes[$slug] ?? $slug); ?></span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modalPermissoesPapel<?php echo $papelId; ?>">
                                                        <span class="fe fe-settings"></span> Configurar permissões
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                
</div>
    </div>

    <?php foreach ($papeis as $papel): ?>
        <?php
        $papelId = (int) $papel['id'];
        $permissoesPapel = $permissoesPorPapel[$papelId] ?? [];
        $isAdminPapel = ($papel['slug'] ?? '') === 'administrador';
        if ($isAdminPapel) {
            $permissoesPapel = array_keys(permissoes_disponiveis());
        }
        ?>
        <div class="modal fade perm-modal" id="modalPermissoesPapel<?php echo $papelId; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>" class="modal-content">
                    <input type="hidden" name="acao" value="guardar_permissoes_papel">
                    <input type="hidden" name="papel_id" value="<?php echo $papelId; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Permissões — <?php echo e($papel['nome']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <?php foreach (permissoes_grupos() as $grupoNome => $slugsGrupo): ?>
                            <h6 class="perm-group-title text-uppercase small mt-3 mb-2"><?php echo e($grupoNome); ?></h6>
                            <div class="row">
                                <?php foreach ($slugsGrupo as $slug): ?>
                                    <?php if (!isset($labelsPermissoes[$slug])) { continue; } ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="perm<?php echo $papelId . preg_replace('/[^a-z]/', '', $slug); ?>" name="permissoes[]" value="<?php echo e($slug); ?>" <?php echo in_array($slug, $permissoesPapel, true) ? 'checked' : ''; ?> <?php echo $isAdminPapel ? 'disabled' : ''; ?>>
                                            <label class="custom-control-label" for="perm<?php echo $papelId . preg_replace('/[^a-z]/', '', $slug); ?>"><?php echo e($labelsPermissoes[$slug]); ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($isAdminPapel): ?>
                            <?php foreach (array_keys($labelsPermissoes) as $slug): ?>
                                <input type="hidden" name="permissoes[]" value="<?php echo e($slug); ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="modal fade" id="modalCriarUtilizador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Novo utilizador</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Palavra-passe *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Papel *</label>
                        <select name="papel_id" class="form-control" required>
                            <option value="">Selecione um papel</option>
                            <?php foreach ($papeis as $papel): ?>
                                <option value="<?php echo (int) $papel['id']; ?>"><?php echo e($papel['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="suspenso">Suspenso</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($utilizadores as $utilizador): ?>
        <div class="modal fade" id="modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $utilizador['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar utilizador</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo e($utilizador['nome']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($utilizador['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nova palavra-passe</label>
                            <input type="password" name="password" class="form-control" placeholder="Manter atual se ficar vazio">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Papel</label>
                            <select name="papel_id" class="form-control">
                                <option value="">Sem papel</option>
                                <?php foreach ($papeis as $papel): ?>
                                    <option value="<?php echo (int) $papel['id']; ?>" <?php echo ((int) $utilizador['papel_id'] === (int) $papel['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($papel['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-control">
                                <option value="ativo" <?php echo $utilizador['estado'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="suspenso" <?php echo $utilizador['estado'] === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                                <option value="inativo" <?php echo $utilizador['estado'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverUtilizador<?php echo (int) $utilizador['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" action="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $utilizador['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover utilizador</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($utilizador['nome']); ?></strong>?</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-danger">Remover</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php
$pageScripts = '<script>
        $(document).ready(function () {
            $(\'#tabela-utilizadores\').DataTable({
                pageLength: 10,
                language: {
                    search: \'Pesquisar:\',
                    lengthMenu: \'Mostrar _MENU_ registos\',
                    info: \'A mostrar _START_ a _END_ de _TOTAL_ registos\',
                    infoEmpty: \'Sem registos\',
                    zeroRecords: \'Nenhum utilizador encontrado\',
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
