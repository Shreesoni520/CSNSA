<?php

require_once __DIR__ . '/funcionarios_estado.php';

function site_run_migrations($conn): void
{
    if (!fe_table_exists($conn, 'funcionarios')) {
        return;
    }

    $columns = [];
    $result = mysqli_query($conn, 'SHOW COLUMNS FROM funcionarios');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = true;
        }
    }

    $alters = [];
    if (!isset($columns['data_nascimento'])) {
        $alters[] = 'ADD COLUMN data_nascimento date DEFAULT NULL AFTER data_admissao';
    }
    if (!isset($columns['data_diuturnidade'])) {
        $alters[] = 'ADD COLUMN data_diuturnidade date DEFAULT NULL AFTER data_nascimento';
    }
    if (!isset($columns['arquivado_em'])) {
        $alters[] = 'ADD COLUMN arquivado_em datetime DEFAULT NULL AFTER observacoes';
    }

    if ($alters !== []) {
        mysqli_query($conn, 'ALTER TABLE funcionarios ' . implode(', ', $alters));
    }

    if (fe_table_exists($conn, 'horarios_turno') && !fe_column_exists($conn, 'horarios_turno', 'equipa_id')) {
        mysqli_query($conn, 'ALTER TABLE horarios_turno ADD COLUMN equipa_id int(11) DEFAULT NULL AFTER turno_id');
    }

    if (fe_table_exists($conn, 'pedidos_ausencia') && !fe_column_exists($conn, 'pedidos_ausencia', 'total_horas')) {
        mysqli_query($conn, 'ALTER TABLE pedidos_ausencia ADD COLUMN total_horas decimal(6,2) DEFAULT NULL AFTER total_dias');
    }

    if (!fe_table_exists($conn, 'papel_permissoes')) {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS papel_permissoes (
            id int(11) NOT NULL AUTO_INCREMENT,
            papel_id int(11) NOT NULL,
            permissao varchar(80) NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY uk_papel_permissao (papel_id, permissao),
            FOREIGN KEY (papel_id) REFERENCES papeis (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    require_once __DIR__ . '/permissions.php';
    permissoes_seed_defaults($conn);
}

function site_seed_equipas_horarios($conn): void
{
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM equipas');
    if (!$result) {
        return;
    }

    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $presets = [
        ['ACAO_DIRETA', 'Ação direta', '7h24 diárias (7.4h) e 37 horas semanais. 0.6h = 36 minutos.', 37.00, 7.40],
        ['ACAO_DOM', 'Ação direta domicílios', 'Segunda a sexta das 08:30 às 16:30.', 37.50, 7.50],
        ['COPA_SG', 'Copa / Serviços gerais', '8h diárias, 40h semanais. Turnos 08:00-16:00 ou 12:00-20:00. Todos os dias.', 40.00, 8.00],
        ['COZINHA', 'Cozinha', '8h diárias, 40h semanais. Turnos 08:00-16:00 ou 12:00-20:00. Todos os dias.', 40.00, 8.00],
        ['MOTORISTAS', 'Motoristas', 'Segunda a sexta. 08:00-12:00 e 18:00-20:00 OU 08:00-13:00 e 17:00-20:00.', 40.00, 8.00],
        ['LAVANDARIA', 'Lavandaria', 'Segunda a sexta das 08:00 às 16:00.', 40.00, 8.00],
        ['SERV_TEC', 'Serviços técnicos', '7 horas por dia. Horário individual por funcionário.', 35.00, 7.00],
    ];

    foreach ($presets as [$codigo, $nome, $descricao, $cargaSemanal, $cargaDiaria]) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM equipas WHERE codigo = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $codigo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($exists) {
            continue;
        }

        $horarioResumo = $descricao;
        $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (nome, codigo, descricao, ativo) VALUES (?, ?, ?, 1)');
        mysqli_stmt_bind_param($stmt, 'sss', $nome, $codigo, $descricao);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
