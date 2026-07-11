<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . admin_url(permissoes_primeira_pagina_permitida()));
    exit;
}

$error = '';
$login = '';
$notice = $_GET['message'] ?? '';
$redirect = auth_safe_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'inicio');
$registrationOpen = registration_is_open();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $remember = isset($_POST['remember']);

    if ($login === '' || $password === '') {
        $error = 'Preenche o nome de utilizador ou email e a palavra-passe.';
    } elseif (!login_user($login, $password)) {
        $error = 'Credenciais inválidas ou conta sem permissões de acesso.';
    } else {
        if ($remember) {
            remember_login();
        }
        header('Location: ' . $redirect);
        exit;
    }
}
?>
<!doctype html>
<html lang="pt">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">
    <title>CSNSA - Iniciar sessão</title>
    <!-- Simple bar CSS -->
    <link rel="stylesheet" href="css/simplebar.css">
    <!-- Fonts CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- Icons CSS -->
    <link rel="stylesheet" href="css/feather.css">
    <!-- Date Range Picker CSS -->
    <!-- App CSS -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <link rel="stylesheet" href="css/csnsa-light-fixes.css" id="lightFixes">
    <link rel="stylesheet" href="css/csnsa-alerts.css">
  </head>
  <body class="light ">
    <div class="wrapper vh-100">
      <div class="row align-items-center h-100">
        <form class="col-lg-4 col-md-6 col-10 mx-auto text-center" method="POST" action="">
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
          <a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="<?php echo htmlspecialchars(admin_url('auth-login')); ?>">
            <?php $brandClass = 'brand-md'; include __DIR__ . '/includes/brand-logo.php'; ?>
          </a>
          <h1 class="h6 mb-3">Iniciar sessão</h1>
          <?php if ($error): ?>
            <?php render_alert($error, 'danger', false); ?>
          <?php elseif ($notice): ?>
            <?php render_alert($notice, 'success', false); ?>
          <?php endif; ?>
          <div class="form-group text-left">
            <label for="inputLogin">Nome de utilizador ou email</label>
            <input type="text" id="inputLogin" name="login" class="form-control form-control-lg" placeholder="admin ou email@dominio.pt" required autofocus value="<?php echo htmlspecialchars($login); ?>">
          </div>
          <div class="form-group text-left">
            <label for="inputPassword">Palavra-passe</label>
            <input type="password" id="inputPassword" name="password" class="form-control form-control-lg" placeholder="Palavra-passe" required>
          </div>
          <div class="form-group text-left">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="rememberMe" name="remember">
              <label class="custom-control-label" for="rememberMe">Manter sessão iniciada</label>
            </div>
          </div>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Entrar</button>
          <div class="mt-4">
            <?php if ($registrationOpen): ?>
              <a href="<?php echo htmlspecialchars(admin_url('auth-register')); ?>" class="btn btn-outline-primary btn-sm">Criar conta de administrador</a>
            <?php else: ?>
              <span class="text-muted small d-block">Já existe uma conta no sistema. Peça acesso ao administrador.</span>
            <?php endif; ?>
          </div>
          <p class="mt-5 mb-3 text-muted">© 2026</p>
        </form>
      </div>
    </div>
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/simplebar.min.js"></script>
    <script src='js/jquery.stickOnScroll.js'></script>
    <script src="js/tinycolor-min.js"></script>
    <script src="js/config.js"></script>
    <script src="js/apps.js"></script>
  </body>
</html>
