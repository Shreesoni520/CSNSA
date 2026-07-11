<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/avatar_helpers.php';

$homeUrl = admin_url(permissoes_primeira_pagina_permitida());
?>
<style>
  .topnav .nav-item .avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background: #435ebe;
    vertical-align: middle;
  }
  .topnav .nav-item .avatar .avatar-img,
  .topnav .nav-item .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }
  .topnav .nav-item .avatar .avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #435ebe;
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1;
    text-transform: uppercase;
  }
  .topnav .nav-item .nav-link.dropdown-toggle {
    display: inline-flex;
    align-items: center;
    padding-top: 0.35rem;
    padding-bottom: 0.35rem;
  }
  .protected-avatar,
  .avatar-img,
  .profile-avatar-box img,
  .funcionario-foto-preview img,
  .topnav .avatar img {
    -webkit-user-drag: none;
    user-drag: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
    pointer-events: none;
  }
  .avatar-placeholder.protected-avatar,
  .profile-avatar-box .avatar-placeholder,
  .funcionario-foto-preview .avatar-placeholder {
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
  }
</style>
<script src="js/protected-images.js" defer></script>
<?php
$currentPage = $_GET['csnsa'] ?? 'inicio';
$navUser = current_user();
$navUserName = trim(($navUser['first_name'] ?? '') . ' ' . ($navUser['last_name'] ?? ''));
if ($navUserName === '') {
    $navUserName = $navUser['username'] ?? $navUser['email'] ?? 'Utilizador';
}
?>
<style>
  .topnav .dropdown-menu {
    min-width: 12rem;
    padding: 0.5rem 0;
    margin-top: 0.625rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.35);
  }
  .topnav .dropdown-menu .dropdown-item {
    padding: 0.5rem 1.25rem;
  }
  .topnav .dropdown-menu .dropdown-divider {
    margin: 0.35rem 0;
    border-top-color: rgba(255, 255, 255, 0.08);
  }
  .vertnav .nav-link {
    display: flex;
    align-items: center;
  }
  .vertnav .nav-link > i,
  .vertnav .nav-link > span.fe {
    width: 1.25rem;
    min-width: 1.25rem;
    text-align: center;
  }
</style>
<nav class="topnav navbar navbar-light">
  <button type="button" class="navbar-toggler text-muted mt-2 p-0 mr-3 collapseSidebar">
    <i class="fe fe-menu navbar-toggler-icon"></i>
  </button>
  <ul class="nav ml-auto">
    <li class="nav-item">
      <a class="nav-link text-muted my-2" href="#" id="modeSwitcher" data-mode="light">
        <i class="fe fe-sun fe-16"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-muted my-2" href="#" data-toggle="modal" data-target=".modal-shortcut" title="Atalhos">
        <span class="fe fe-grid fe-16"></span>
      </a>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle text-muted pr-0" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="avatar avatar-sm">
          <?php echo avatar_render_html($navUser['avatar'] ?? null, $navUserName); ?>
        </span>
      </a>
      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
        <a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('profile')); ?>">Perfil</a>
        <a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('inicio')); ?>">Início</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('auth-logout')); ?>">Sair</a>
      </div>
    </li>
  </ul>
</nav>
<?php include __DIR__ . '/includes/header-modals.php'; ?>
<aside class="sidebar-left border-right bg-white shadow" id="leftSidebar" data-simplebar>
  <a href="#" class="btn collapseSidebar toggle-btn d-lg-none text-muted ml-2 mt-3" data-toggle="toggle">
    <i class="fe fe-x"><span class="sr-only"></span></i>
  </a>
  <nav class="vertnav navbar navbar-light">
    <div class="w-100 mb-4 d-flex">
      <a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="<?php echo htmlspecialchars($homeUrl); ?>">
        <?php $brandClass = 'brand-sm'; include __DIR__ . '/includes/brand-logo.php'; ?>
      </a>
    </div>

    <ul class="navbar-nav flex-fill w-100 mb-2">
      <?php if (current_user_can('dashboard')): ?>
      <li class="nav-item w-100">
        <a class="nav-link <?php echo $currentPage === 'inicio' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('inicio')); ?>">
          <i class="fe fe-home fe-16"></i>
          <span class="ml-3 item-text">Dashboard</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <?php
    $rhItems = [
        ['page' => 'funcionarios', 'perm' => 'funcionarios', 'url' => 'funcionarios', 'icon' => 'fe-users', 'label' => 'Funcionários'],
        ['page' => 'departamentos', 'perm' => 'equipas', 'url' => 'departamentos', 'icon' => 'fe-layers', 'label' => 'Equipas'],
        ['page' => 'ponto', 'perm' => 'ponto', 'url' => 'ponto', 'icon' => 'fe-clock', 'label' => 'Registo de Ponto'],
        ['page' => 'dispositivos', 'perm' => 'dispositivos', 'url' => 'dispositivos', 'icon' => 'fe-hard-drive', 'label' => 'Relógios de Ponto'],
        ['page' => 'ausencias', 'perm' => 'ausencias', 'url' => 'ausencias', 'icon' => 'fe-calendar', 'label' => 'Ausências'],
        ['page' => 'turnos', 'perm' => 'turnos', 'url' => 'turnos', 'icon' => 'fe-repeat', 'label' => 'Turnos'],
        ['page' => 'escala_mensal', 'perm' => 'escala_mensal', 'url' => 'escala_mensal', 'icon' => 'fe-calendar', 'label' => 'Escala Mensal'],
        ['page' => 'banco_horas', 'perm' => 'banco_horas', 'url' => 'banco_horas', 'icon' => 'fe-watch', 'label' => 'Banco de Horas'],
        ['page' => 'relatorios_horas', 'perm' => 'relatorios', 'url' => 'relatorios_horas', 'icon' => 'fe-file-text', 'label' => 'Relatórios de Horas'],
    ];
    $rhVisible = array_filter($rhItems, static fn(array $item): bool => current_user_can($item['perm']));
    ?>
    <?php if ($rhVisible !== []): ?>
    <p class="text-muted nav-heading mt-4 mb-1"><span>RH &amp; Assiduidade</span></p>
    <ul class="navbar-nav flex-fill w-100 mb-2">
      <?php foreach ($rhVisible as $item): ?>
      <li class="nav-item w-100">
        <a class="nav-link <?php echo $currentPage === $item['page'] ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url($item['url'])); ?>">
          <i class="fe <?php echo e($item['icon']); ?> fe-16"></i>
          <span class="ml-3 item-text"><?php echo e($item['label']); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (current_user_can('utilizadores')): ?>
    <p class="text-muted nav-heading mt-4 mb-1"><span>Administração</span></p>
    <ul class="navbar-nav flex-fill w-100 mb-2">
      <li class="nav-item w-100">
        <a class="nav-link <?php echo $currentPage === 'utilizadores' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('utilizadores')); ?>">
          <i class="fe fe-shield fe-16"></i>
          <span class="ml-3 item-text">Utilizadores</span>
        </a>
      </li>
    </ul>
    <?php endif; ?>

    <p class="text-muted nav-heading mt-4 mb-1"><span>Conta</span></p>
    <ul class="navbar-nav flex-fill w-100 mb-2">
      <li class="nav-item w-100">
        <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('profile')); ?>">
          <i class="fe fe-user fe-16"></i>
          <span class="ml-3 item-text">Perfil</span>
        </a>
      </li>
      <li class="nav-item w-100">
        <a class="nav-link" href="<?php echo htmlspecialchars(admin_url('auth-logout')); ?>">
          <i class="fe fe-power fe-16"></i>
          <span class="ml-3 item-text">Sair</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
