<?php

function permissoes_disponiveis(): array
{
    return [
        'dashboard' => 'Painel / Dashboard',
        'funcionarios' => 'Funcionários',
        'equipas' => 'Equipas',
        'ponto' => 'Registo de ponto',
        'turnos' => 'Turnos',
        'escala_mensal' => 'Escala mensal',
        'ausencias' => 'Ausências',
        'banco_horas' => 'Banco de horas',
        'relatorios' => 'Relatórios de horas',
        'utilizadores' => 'Utilizadores e permissões',
        'dispositivos' => 'Relógios de ponto',
    ];
}

function permissoes_grupos(): array
{
    return [
        'Geral' => ['dashboard'],
        'Pessoal' => ['funcionarios', 'equipas'],
        'Assiduidade' => ['ponto', 'dispositivos', 'turnos', 'escala_mensal', 'ausencias', 'banco_horas'],
        'Relatórios' => ['relatorios'],
        'Administração' => ['utilizadores'],
    ];
}

function permissoes_slug_pagina(string $page): ?string
{
    $map = [
        'inicio' => 'dashboard',
        'funcionarios' => 'funcionarios',
        'gerar_pdf_funcionarios' => 'funcionarios',
        'departamentos' => 'equipas',
        'ponto' => 'ponto',
        'dispositivos' => 'dispositivos',
        'turnos' => 'turnos',
        'escala_mensal' => 'escala_mensal',
        'ausencias' => 'ausencias',
        'relatorio_ausencias' => 'ausencias',
        'banco_horas' => 'banco_horas',
        'relatorios_horas' => 'relatorios',
        'utilizadores' => 'utilizadores',
        'calcular_resumo' => 'relatorios',
    ];

    return $map[$page] ?? null;
}

function permissoes_carregar_papel($conn, int $papelId): array
{
    $permissoes = [];

    if (!fe_table_exists($conn, 'papel_permissoes')) {
        return $permissoes;
    }

    $stmt = mysqli_prepare($conn, 'SELECT permissao FROM papel_permissoes WHERE papel_id = ? ORDER BY permissao ASC');
    mysqli_stmt_bind_param($stmt, 'i', $papelId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $permissoes[] = $row['permissao'];
    }

    mysqli_stmt_close($stmt);

    return $permissoes;
}

function permissoes_guardar_papel($conn, int $papelId, array $permissoes): void
{
    if (!fe_table_exists($conn, 'papel_permissoes')) {
        return;
    }

    $validas = array_keys(permissoes_disponiveis());
    $permissoes = array_values(array_unique(array_intersect($permissoes, $validas)));

    $stmt = mysqli_prepare($conn, 'DELETE FROM papel_permissoes WHERE papel_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $papelId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($permissoes === []) {
        return;
    }

    $stmt = mysqli_prepare($conn, 'INSERT INTO papel_permissoes (papel_id, permissao) VALUES (?, ?)');

    foreach ($permissoes as $permissao) {
        mysqli_stmt_bind_param($stmt, 'is', $papelId, $permissao);
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
}

function permissoes_carregar_utilizador($conn, int $utilizadorId): array
{
    if ($utilizadorId <= 0) {
        return [];
    }

    if (function_exists('utilizador_has_admin_papel') && utilizador_has_admin_papel($utilizadorId)) {
        return array_keys(permissoes_disponiveis());
    }

    if (!fe_table_exists($conn, 'papel_permissoes')) {
        return [];
    }

    $permissoes = [];
    $stmt = mysqli_prepare($conn, 'SELECT DISTINCT pp.permissao
        FROM utilizador_papeis up
        INNER JOIN papel_permissoes pp ON pp.papel_id = up.papel_id
        WHERE up.utilizador_id = ?
        ORDER BY pp.permissao ASC');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $permissoes[] = $row['permissao'];
    }

    mysqli_stmt_close($stmt);

    return $permissoes;
}

function permissoes_atualizar_sessao(): void
{
    global $conn;

    if (!is_logged_in()) {
        unset($_SESSION['permissoes']);
        return;
    }

    if (user_is_admin()) {
        $_SESSION['permissoes'] = array_keys(permissoes_disponiveis());
        return;
    }

    $utilizadorId = current_utilizador_id();
    $_SESSION['permissoes'] = permissoes_carregar_utilizador($conn, $utilizadorId);
}

function current_user_can(string $permissao): bool
{
    if (!is_logged_in()) {
        return false;
    }

    if (user_is_admin()) {
        return true;
    }

    $permissoes = $_SESSION['permissoes'] ?? [];

    return in_array($permissao, $permissoes, true);
}

function utilizador_tem_permissao($conn, ?int $utilizadorId, string $permissao): bool
{
    if (function_exists('user_is_admin') && user_is_admin()) {
        return true;
    }

    if ($utilizadorId === null || $utilizadorId <= 0) {
        return false;
    }

    if (function_exists('utilizador_has_admin_papel') && utilizador_has_admin_papel((int) $utilizadorId)) {
        return true;
    }

    return in_array($permissao, permissoes_carregar_utilizador($conn, (int) $utilizadorId), true);
}

function permissoes_primeira_pagina_permitida(): string
{
    $ordem = [
        'dashboard' => 'inicio',
        'funcionarios' => 'funcionarios',
        'equipas' => 'departamentos',
        'ponto' => 'ponto',
        'ausencias' => 'ausencias',
        'escala_mensal' => 'escala_mensal',
        'turnos' => 'turnos',
        'banco_horas' => 'banco_horas',
        'relatorios' => 'relatorios_horas',
        'dispositivos' => 'dispositivos',
        'utilizadores' => 'utilizadores',
    ];

    foreach ($ordem as $slug => $page) {
        if (current_user_can($slug)) {
            return $page;
        }
    }

    return 'profile';
}

function permissoes_utilizador_pode_entrar($conn, int $utilizadorId): bool
{
    if ($utilizadorId <= 0) {
        return false;
    }

    if (function_exists('utilizador_has_admin_papel') && utilizador_has_admin_papel($utilizadorId)) {
        return true;
    }

    return permissoes_carregar_utilizador($conn, $utilizadorId) !== [];
}

function permissoes_slug_script(string $script): ?string
{
    $base = basename($script, '.php');
    $map = [
        'inicio' => 'dashboard',
        'funcionarios' => 'funcionarios',
        'gerar_pdf_funcionarios' => 'funcionarios',
        'departamentos' => 'equipas',
        'ponto' => 'ponto',
        'dispositivos' => 'dispositivos',
        'turnos' => 'turnos',
        'escala_mensal' => 'escala_mensal',
        'banco_horas' => 'banco_horas',
        'relatorios_horas' => 'relatorios',
        'ausencias' => 'ausencias',
        'relatorio_ausencias' => 'ausencias',
        'utilizadores' => 'utilizadores',
        'calcular_resumo_diario_assiduidade' => 'relatorios',
    ];

    return $map[$base] ?? null;
}

function require_page_permission(?string $slug = null): void
{
    require_login();

    if ($slug === null) {
        $slug = permissoes_slug_script($_SERVER['SCRIPT_FILENAME'] ?? '');
    }

    if ($slug !== null && !current_user_can($slug)) {
        header('Location: ' . admin_url(permissoes_primeira_pagina_permitida(), [
            'message' => 'Não tem permissão para aceder a esta página.',
            'type' => 'warning',
        ]));
        exit;
    }
}

function permissoes_seed_defaults($conn): void
{
    if (!fe_table_exists($conn, 'papeis')) {
        return;
    }

    $presets = [
        ['Administrador', 'administrador', 'Acesso total ao sistema', array_keys(permissoes_disponiveis())],
        ['Gestor RH', 'gestor_rh', 'Gestão de pessoal e assiduidade', ['dashboard', 'funcionarios', 'equipas', 'ponto', 'turnos', 'escala_mensal', 'ausencias', 'banco_horas', 'relatorios', 'dispositivos']],
        ['Supervisor', 'supervisor', 'Consulta e aprovações', ['dashboard', 'ponto', 'ausencias', 'escala_mensal', 'banco_horas', 'relatorios']],
        ['Colaborador', 'colaborador', 'Consulta do próprio ponto e ausências', ['dashboard', 'ponto', 'ausencias']],
    ];

    foreach ($presets as [$nome, $slug, $descricao, $perms]) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM papeis WHERE slug = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($row) {
            $papelId = (int) $row['id'];
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO papeis (nome, slug, descricao, ativo) VALUES (?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'sss', $nome, $slug, $descricao);
            mysqli_stmt_execute($stmt);
            $papelId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        if (!fe_table_exists($conn, 'papel_permissoes')) {
            continue;
        }

        $existentes = permissoes_carregar_papel($conn, $papelId);
        if ($existentes === []) {
            permissoes_guardar_papel($conn, $papelId, $perms);
        }
    }
}
