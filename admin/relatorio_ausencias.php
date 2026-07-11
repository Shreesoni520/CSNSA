<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/funcoes/ausencias_funcoes.php';
require_page_permission();

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$funcionarioId = (int) ($_GET['funcionario_id'] ?? 0);

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$anoFiltroRelatorio = (int) ($_GET['ano'] ?? $anoAtual);
if ($anoFiltroRelatorio < 2000 || $anoFiltroRelatorio > 2100) {
    $anoFiltroRelatorio = $anoAtual;
}
$mesesPassadosRelatorio = ausencias_meses_passados($anoFiltroRelatorio, $anoAtual, $mesAtual);

$funcionarios = [];
if (fe_table_exists($conn, 'funcionarios')) {
    $stmt = mysqli_prepare($conn, "SELECT id, nome, numero_mecanografico FROM funcionarios WHERE estado NOT IN ('arquivado') ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$linhas = [];
if (fe_table_exists($conn, 'pedidos_ausencia')) {
    $sql = "SELECT pa.*,
                   COALESCE(f.nome, u.nome) AS funcionario_nome,
                   COALESCE(f.numero_mecanografico, '-') AS numero_mecanografico,
                   ta.nome AS tipo_nome,
                   ta.exige_justificativo
            FROM pedidos_ausencia pa
            LEFT JOIN funcionarios f ON f.id = pa.funcionario_id
            LEFT JOIN utilizadores u ON u.id = pa.utilizador_id
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            WHERE pa.data_inicio <= ? AND pa.data_fim >= ?";
    $params = [$dataFim, $dataInicio];
    $types = 'ss';

    if ($funcionarioId > 0) {
        $sql .= ' AND pa.funcionario_id = ?';
        $params[] = $funcionarioId;
        $types .= 'i';
    }

    $sql .= " ORDER BY f.nome ASC, pa.data_inicio ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $linhas[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Relatório de ausências';
$useDataTables = true;
include __DIR__ . '/includes/layout-start.php';
?>
<div class="page-header">
    <h3 class="fw-bold mb-3">Relatório de ausências</h3>
    <ul class="breadcrumbs mb-3">
        <li class="nav-home"><a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>"><i class="icon-home"></i></a></li>
        <li class="separator"><i class="icon-arrow-right"></i></li>
        <li class="nav-item"><a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>">Ausências</a></li>
        <li class="separator"><i class="icon-arrow-right"></i></li>
        <li class="nav-item">Relatório</li>
    </ul>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars(admin_url('relatorio_ausencias')); ?>" class="row g-3 align-items-end" id="formRelatorioAusencias">
            <div class="col-md-3">
                <label class="form-label">Data início</label>
                <input type="date" name="data_inicio" class="form-control js-auto-filter" value="<?php echo e($dataInicio); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data fim</label>
                <input type="date" name="data_fim" class="form-control js-auto-filter" value="<?php echo e($dataFim); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Funcionário</label>
                <select name="funcionario_id" class="form-control js-auto-filter">
                    <option value="0">Todos</option>
                    <?php foreach ($funcionarios as $func): ?>
                        <option value="<?php echo (int) $func['id']; ?>" <?php echo (int) $func['id'] === $funcionarioId ? 'selected' : ''; ?>>
                            <?php echo e($func['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Ano</label>
                <input type="number" name="ano" class="form-control js-auto-filter" min="2000" max="2100" value="<?php echo (int) $anoFiltroRelatorio; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mês (só passados)</label>
                <select class="form-control js-mes-passado">
                    <option value="">—</option>
                    <?php foreach ($mesesPassadosRelatorio as $item): ?>
                        <option value="<?php echo (int) $item['mes']; ?>"><?php echo e(month_name((int) $item['mes'])); ?> <?php echo (int) $item['ano']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Faltas no período</h4></div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabela-relatorio-ausencias" class="display table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Tipo</th>
                        <th>Início</th>
                        <th>Fim</th>
                        <th>Dias</th>
                        <th>Horas (escala)</th>
                        <th>Estado</th>
                        <th>Justificar</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhas as $linha):
                        $horasLinha = isset($linha['total_horas']) && $linha['total_horas'] !== null
                            ? (float) $linha['total_horas']
                            : ausencias_calcular_impacto_escala(
                                $conn,
                                $linha['funcionario_id'] ? (int) $linha['funcionario_id'] : null,
                                $linha['data_inicio'],
                                $linha['data_fim']
                            )['horas'];
                        $docEstado = ausencias_estado_documento($linha);
                        ?>
                        <tr>
                            <td><?php echo e($linha['funcionario_nome'] ?: '-'); ?></td>
                            <td><?php echo e($linha['tipo_nome']); ?></td>
                            <td><?php echo e(date('d/m/Y', strtotime($linha['data_inicio']))); ?></td>
                            <td><?php echo e(date('d/m/Y', strtotime($linha['data_fim']))); ?></td>
                            <td><?php echo e(ausencias_formatar_dias((float) ($linha['total_dias'] ?? 0))); ?></td>
                            <td><?php echo e(ausencias_formatar_horas($horasLinha)); ?></td>
                            <td><?php echo e(ucfirst($linha['estado'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo e(ausencias_badge_documento($docEstado)); ?>">
                                    <?php echo e(ausencias_label_documento($docEstado)); ?>
                                </span>
                                <?php if (!empty($linha['ficheiro_justificativo'])): ?>
                                    <a href="<?php echo e(admin_asset($linha['ficheiro_justificativo'])); ?>" target="_blank" class="btn btn-sm btn-outline-success ml-1">Ver</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($linha['motivo'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$pageScripts = '<script>
$(document).ready(function () {
    $("#tabela-relatorio-ausencias").DataTable({
        pageLength: 25,
        language: {
            search: "Pesquisar:",
            lengthMenu: "Mostrar _MENU_ registos",
            info: "A mostrar _START_ a _END_ de _TOTAL_ registos",
            zeroRecords: "Sem ausências no período"
        }
    });
    $(".js-auto-filter").on("change", function () {
        $("#formRelatorioAusencias").submit();
    });
    $(".js-mes-passado").on("change", function () {
        var m = parseInt(this.value, 10);
        if (!m) return;
        var y = parseInt($("input[name=ano]").val(), 10) || ' . (int) $anoAtual . ';
        var inicio = new Date(y, m - 1, 1);
        var fim = new Date(y, m, 0);
        var pad = function (n) { return String(n).padStart(2, "0"); };
        $("input[name=data_inicio]").val(inicio.getFullYear() + "-" + pad(inicio.getMonth() + 1) + "-01");
        $("input[name=data_fim]").val(fim.getFullYear() + "-" + pad(fim.getMonth() + 1) + "-" + pad(fim.getDate()));
        $("#formRelatorioAusencias").submit();
    });
});
</script>';
include __DIR__ . '/includes/layout-end.php';
