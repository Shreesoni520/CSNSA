<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/funcionarios_export.php';

require_page_permission('funcionarios');

if (!empty(funcionarios_missing_tables($conn))) {
    admin_redirect_msg('funcionarios', 'danger', 'Importe o ficheiro csnsa.sql antes de exportar.');
}

$tipo = $_POST['tipo'] ?? $_GET['tipo'] ?? 'lista';
$formato = $_POST['formato'] ?? $_GET['formato'] ?? 'pdf';
$ids = isset($_POST['funcionarios']) && is_array($_POST['funcionarios']) ? $_POST['funcionarios'] : [];

if (!in_array($tipo, ['lista', 'picagens'], true)) {
    admin_redirect_msg('funcionarios', 'danger', 'Tipo de exportacao invalido.');
}

if (!in_array($formato, ['pdf', 'csv'], true)) {
    admin_redirect_msg('funcionarios', 'danger', 'Formato de exportacao invalido.');
}

$funcionarios = export_funcionarios_ids($conn, $ids);
if (empty($funcionarios)) {
    admin_redirect_msg('funcionarios', 'warning', 'Nao ha funcionarios para exportar.');
}

try {
    if ($tipo === 'lista') {
        if ($formato === 'csv') {
            export_lista_csv($funcionarios);
        }
        export_lista_pdf($funcionarios);
    }

    $periodo = export_picagens_periodo($_POST ?: $_GET);
    if ($formato === 'csv') {
        export_picagens_csv($conn, $funcionarios, $periodo);
    }
    export_picagens_pdf($conn, $funcionarios, $periodo);
} catch (InvalidArgumentException $e) {
    admin_redirect_msg('funcionarios', 'danger', $e->getMessage());
}
