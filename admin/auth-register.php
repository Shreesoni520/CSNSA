<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . admin_url('inicio'));
    exit;
}

if (!registration_is_open()) {
    header('Location: ' . admin_url('auth-login'));
    exit;
}

$error = '';
$username = '';
$email = '';
$firstName = '';
$lastName = '';
$telefone = '';
$endereco = '';
$password = '';
$confirm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $telefone === '' || $password === '' || $confirm === '') {
        $error = 'Preenche o nome de utilizador, email, telefone e as duas palavras-passe.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
        $error = 'Nome de utilizador inválido. Use 3-30 caracteres, letras, números, . _ ou -.';
    } elseif (($emailError = validate_email_address($email)) !== '') {
        $error = $emailError;
    } elseif (is_username_taken($username)) {
        $error = 'Esse nome de utilizador já existe. Escolhe outro.';
    } elseif (is_email_taken($email)) {
        $error = 'Esse email já está registado.';
    } else {
        $normalizedPhone = normalize_phone($telefone);
        if (strlen(preg_replace('/\D+/', '', $normalizedPhone)) < 8 || strlen(preg_replace('/\D+/', '', $normalizedPhone)) > 15) {
            $error = 'Telefone inválido. Introduz um número real com pelo menos 8 dígitos.';
        } elseif (is_telefone_taken($telefone)) {
            $error = 'Esse telefone já está registado.';
        } elseif ($password !== $confirm) {
            $error = 'As palavras-passe não coincidem.';
        } elseif (strlen($password) < 8) {
            $error = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        } else {
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'telefone' => $telefone,
                'endereco' => $endereco,
            ];

            if (register_user($userData, 'admin')) {
                header('Location: ' . admin_url('auth-login', [
                    'message' => 'Conta de administrador criada. Já podes iniciar sessão.',
                ]));
                exit;
            } else {
                $error = 'Não foi possível criar a conta. O nome de utilizador, email ou telefone pode já estar registado.';
            }
        }
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
    <title>CSNSA - Configuração inicial</title>
    <link rel="stylesheet" href="css/simplebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/feather.css">
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <link rel="stylesheet" href="css/csnsa-light-fixes.css" id="lightFixes">
    <link rel="stylesheet" href="css/csnsa-alerts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">
  </head>
  <body class="light ">
    <div class="wrapper vh-100">
      <div class="row align-items-center h-100">
        <form class="col-lg-6 col-md-8 col-10 mx-auto" method="POST" action="">
          <div class="mx-auto text-center my-4">
            <a class="navbar-brand mx-auto mt-2 flex-fill text-center" href="<?php echo htmlspecialchars(admin_url('auth-register')); ?>">
              <?php $brandClass = 'brand-md'; include __DIR__ . '/includes/brand-logo.php'; ?>
            </a>
            <h2 class="my-3">Configuração inicial</h2>
            <p class="text-muted small mb-0">Primeiro acesso: cria a conta de administrador. Esta página só aparece uma vez.</p>
          </div>
            <?php if ($error): ?>
            <?php render_alert($error, 'danger', false); ?>
            <?php endif; ?>
          <div class="form-group">
            <label for="username">Nome de utilizador</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="usuario123" value="<?php echo htmlspecialchars($username); ?>" required>
          </div>
          <div class="form-group">
            <label for="inputEmail4">Correio eletrónico</label>
            <input type="email" class="form-control" id="inputEmail4" name="email" placeholder="email@dominio.pt" value="<?php echo htmlspecialchars($email); ?>" required>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="firstname">Nome próprio</label>
              <input type="text" id="firstname" name="first_name" class="form-control" placeholder="João" value="<?php echo htmlspecialchars($firstName); ?>">
            </div>
            <div class="form-group col-md-6">
              <label for="lastname">Apelido</label>
              <input type="text" id="lastname" name="last_name" class="form-control" placeholder="Silva" value="<?php echo htmlspecialchars($lastName); ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="telefone">Telefone</label>
              <input
                type="tel"
                id="telefone_display"
                name="telefone_display"
                class="form-control"
                placeholder="912 345 678"
                required>
              <input type="hidden" name="telefone" id="telefone_hidden">
              <small class="text-muted">Selecione o país e insira o número</small>
            </div>
            <div class="form-group col-md-6">
              <label for="endereco">Morada</label>
              <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua Principal 123, Lisboa" value="<?php echo htmlspecialchars($endereco); ?>">
            </div>
          </div>
          <hr class="my-4">
          <div class="row mb-4">
            <div class="col-md-6">
              <div class="form-group">
                <label for="inputPassword5">Nova palavra-passe</label>
                <input type="password" class="form-control" id="inputPassword5" name="password" placeholder="Palavra-passe" required>
              </div>
              <div class="form-group">
                <label for="inputPassword6">Confirmar palavra-passe</label>
                <input type="password" class="form-control" id="inputPassword6" name="confirm_password" placeholder="Confirmar palavra-passe" required>
              </div>
            </div>
            <div class="col-md-6">
              <p class="mb-2">Requisitos da palavra-passe</p>
              <ul class="small text-muted pl-4 mb-0">
                <li>Mínimo de 8 caracteres</li>
                <li>Pelo menos um carácter especial</li>
                <li>Pelo menos um número</li>
              </ul>
            </div>
          </div>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Criar administrador</button>
          <p class="mt-5 mb-3 text-muted text-center">© 2026</p>
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
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.querySelector('#telefone_display');
        const hiddenInput = document.querySelector('#telefone_hidden');
        const form = document.querySelector('form');

        if (phoneInput) {
            const iti = window.intlTelInput(phoneInput, {
              initialCountry: 'pt',
              preferredCountries: ['pt', 'br', 'es', 'fr', 'uk'],
              separateDialCode: false,
              utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
            });

            form.addEventListener('submit', function(e) {
                if (!phoneInput.value.trim()) {
                    e.preventDefault();
                    alert('Por favor insira um número de telefone.');
                    return;
                }

                if (!iti.isValidNumber()) {
                    e.preventDefault();
                    alert('Por favor insira um número de telefone válido.');
                    return;
                }

                hiddenInput.value = iti.getNumber();
            });
        }
    });
    </script>
  </body>
</html>
