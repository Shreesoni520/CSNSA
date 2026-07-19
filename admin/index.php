<?php
/**
 * Admin router — all pages load through ?csnsa=page_name
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    refresh_session_permissions();
}

$page = isset($_GET['csnsa']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['csnsa']) : 'inicio';

$legacyRedirects = [
    'listar_funcionario' => 'funcionarios',
    'add_funcionario' => 'funcionarios',
    'get_funcionario' => 'funcionarios',
    'update_funcionario' => 'funcionarios',
    'ver_semana_ponto' => 'ponto',
];
if (isset($legacyRedirects[$page])) {
    $target = $legacyRedirects[$page];
    $query = $_GET;
    unset($query['csnsa']);
    $query['csnsa'] = $target;
    $location = 'index.php?' . http_build_query($query);
    header('Location: ' . $location);
    exit;
}

$routes = [
    'inicio'           => 'inicio.php',
    'profile'          => 'profile.php',
    'page-404'         => 'page-404.php',
    'funcionarios'     => 'funcionarios.php',
    'gerar_pdf_funcionarios' => 'gerar_pdf_funcionarios.php',
    'departamentos'    => 'departamentos.php',
    'ponto'            => 'ponto.php',
    'dispositivos'     => 'dispositivos.php',
    'turnos'           => 'turnos.php',
    'escala_mensal'    => 'escala_mensal.php',
    'banco_horas'      => 'banco_horas.php',
    'relatorios_horas' => 'relatorios_horas.php',
    'ausencias'        => 'ausencias.php',
    'relatorio_ausencias' => 'relatorio_ausencias.php',
    'utilizadores'     => 'utilizadores.php',
    'calcular_resumo'  => 'calcular_resumo_diario_assiduidade.php',
    'auth-login'       => 'auth-login.php',
    'auth-register'    => 'auth-register.php',
    'auth-logout'      => 'auth-logout.php',
];

$authPages = ['auth-login', 'auth-register'];

if ($page === 'auth-register' && !registration_is_open()) {
    header('Location: ' . admin_url('auth-login'));
    exit;
}

if (!is_logged_in() && !in_array($page, $authPages, true)) {
    // First visit with no accounts → one-time admin setup.
    $destino = registration_is_open() ? 'auth-register' : 'auth-login';
    header('Location: ' . admin_url($destino));
    exit;
}

if (is_logged_in() && in_array($page, $authPages, true)) {
    header('Location: ' . admin_url(permissoes_primeira_pagina_permitida()));
    exit;
}

$paginasSemprePermitidas = ['profile', 'auth-logout', 'page-404'];

if (is_logged_in() && !in_array($page, $authPages, true) && !in_array($page, $paginasSemprePermitidas, true)) {
    $slug = permissoes_slug_pagina($page);
    if ($slug !== null && !current_user_can($slug)) {
        $destino = permissoes_primeira_pagina_permitida();
        header('Location: ' . admin_url($destino, [
            'message' => 'Não tem permissão para aceder a esta página.',
            'type' => 'warning',
        ]));
        exit;
    }
}

if ($page === 'profile-settings') {
    $_GET['tab'] = 'settings';
    $page = 'profile';
}
if ($page === 'profile-security') {
    $_GET['tab'] = 'security';
    $page = 'profile';
}
$file = $routes[$page] ?? 'page-404.php';

if (!is_file(__DIR__ . '/' . $file)) {
    $file = 'page-404.php';
}

include __DIR__ . '/' . $file;
