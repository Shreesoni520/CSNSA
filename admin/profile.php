<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/urls.php';
require_once __DIR__ . '/includes/avatar_helpers.php';
require_once __DIR__ . '/includes/upload_field.php';

require_login();
ensure_users_avatar_column($GLOBALS['conn']);

$tab = isset($tab) ? $tab : (isset($_GET['tab']) ? $_GET['tab'] : 'settings');
$allowedTabs = ['settings', 'security'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'settings';
}

$user = refresh_current_user();
if (!$user) {
    logout_user();
    header('Location: ' . admin_url('auth-login'));
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'settings';

    if ($formType === 'settings') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $telefone = normalize_phone(trim($_POST['telefone'] ?? ''));
        $endereco = trim($_POST['endereco'] ?? '');

        $emailError = validate_email_address($email);
        if ($emailError !== '') {
            $error = $emailError;
        } elseif (is_email_taken($email, (int) $user['id'])) {
            $error = 'Esse email já está registado noutra conta.';
        } elseif (is_utilizador_email_taken($email, current_utilizador_id())) {
            $error = 'Esse email já está registado noutro utilizador.';
        } elseif (update_user_profile((int)$user['id'], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'telefone' => $telefone,
            'endereco' => $endereco,
        ])) {
            try {
                $avatarPath = avatar_save_image_upload($_FILES['avatar'] ?? [], 'users');
                if ($avatarPath !== null) {
                    update_user_avatar((int) $user['id'], $avatarPath);
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }

            if ($error === '') {
                $message = 'Perfil atualizado com sucesso.';
                $user = refresh_current_user();
            }
        } else {
            $error = 'Não foi possível atualizar o perfil.';
        }
    }

    if ($formType === 'security') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '' || $currentPassword === '' || $confirmPassword === '') {
            $error = 'Preenche todas as palavras-passe.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'A nova palavra-passe e a confirmação não coincidem.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        } elseif (!change_user_password((int)$user['id'], $currentPassword, $newPassword)) {
            $error = 'A palavra-passe actual não está correta.';
        } else {
            $message = 'Palavra-passe alterada com sucesso.';
            $user = refresh_current_user();
        }
    }

}

function tabActive($name, $current) {
    return $name === $current ? 'active' : '';
}
function ariaSelected($name, $current) {
    return $name === $current ? 'true' : 'false';
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
    <title>CSNSA — Definições do perfil</title>
    <!-- Simple bar CSS -->
    <link rel="stylesheet" href="css/simplebar.css">
    <!-- Fonts CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- Icons CSS -->
    <link rel="stylesheet" href="css/feather.css">
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <link rel="stylesheet" href="css/csnsa-light-fixes.css" id="lightFixes">
    <link rel="stylesheet" href="css/csnsa-alerts.css">
    <link rel="stylesheet" href="css/csnsa-upload.css">
    <style>
      .profile-hero {
        position: relative;
        border-radius: 18px;
        padding: 24px;
        background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 60%, #ffffff 100%);
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
      }
      body.dark .profile-hero {
        background: linear-gradient(135deg, #2a3040 0%, #252b36 100%);
        border-color: rgba(255, 255, 255, 0.06);
      }
      .profile-avatar-ring {
        width: 122px;
        height: 122px;
        margin: 0 auto;
        border-radius: 50%;
        padding: 4px;
        background: conic-gradient(from 220deg, #4f46e5, #22c1c3, #4f46e5);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 22px rgba(79, 70, 229, 0.28);
      }
      .profile-avatar-box {
        width: 100%;
        height: 100%;
        margin: 0 auto;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #ffffff;
        border: 3px solid #ffffff;
      }
      body.dark .profile-avatar-box {
        background: #252b36;
        border-color: #252b36;
      }
      .profile-avatar-box .avatar-img,
      .profile-avatar-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
      }
      .profile-avatar-box .avatar-placeholder {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #435ebe;
        color: #ffffff;
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
      }
      body.dark .profile-avatar-box,
      body.dark .profile-avatar-box .avatar-placeholder {
        background: #4f5eae;
        color: #ffffff;
      }
    </style>
  </head>
  <body class="vertical  light  ">
    <div class="wrapper">
      <aside class="left-sidebar bg-sidebar">
        <div id="sidebar" class="sidebar sidebar-with-footer">
          <!-- Aplica o menu -->
          <?php include "menu.php"; ?>
        </div>
      </aside>
      <main role="main" class="main-content">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-8">
              <h2 class="h3 mb-4 page-title">Definições</h2>
              <div class="my-4">
                <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link <?php echo tabActive('settings', $tab); ?>" id="home-tab" href="<?php echo htmlspecialchars(admin_url('profile', ['tab' => 'settings'])); ?>" role="tab" aria-controls="home" aria-selected="<?php echo ariaSelected('settings', $tab); ?>">Perfil</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link <?php echo tabActive('security', $tab); ?>" id="profile-tab" href="<?php echo htmlspecialchars(admin_url('profile', ['tab' => 'security'])); ?>" role="tab" aria-controls="profile" aria-selected="<?php echo ariaSelected('security', $tab); ?>">Segurança</a>
                  </li>
                </ul>
<?php if ($tab === 'settings'): ?>
                <?php if ($message): ?>
                  <?php render_alert($message, 'success', false); ?>
                <?php elseif ($error): ?>
                  <?php render_alert($error, 'danger', false); ?>
                <?php endif; ?>
                <form method="POST" action="<?php echo htmlspecialchars(admin_url('profile', ['tab' => 'settings'])); ?>" enctype="multipart/form-data">
                  <input type="hidden" name="form_type" value="settings">
                  <?php
                    $profileName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    if ($profileName === '') {
                        $profileName = $user['username'] ?? $user['email'] ?? 'Utilizador';
                    }
                  ?>
                  <div class="profile-hero mt-4 mb-4">
                    <div class="row align-items-center">
                      <div class="col-md-3 text-center mb-4 mb-md-0">
                        <div class="profile-avatar-ring">
                          <div class="profile-avatar-box" id="profileAvatarPreview">
                            <?php echo avatar_render_html($user['avatar'] ?? null, $profileName); ?>
                          </div>
                        </div>
                        <div class="profile-photo-upload justify-content-center mt-3">
                          <?php
                          upload_field([
                              'id' => 'profileAvatarInput',
                              'name' => 'avatar',
                              'accept' => '.jpg,.jpeg,.png',
                              'variant' => 'avatar',
                              'button_text' => 'Alterar foto',
                              'hint' => 'JPG ou PNG, até 5 MB.',
                          ]);
                          ?>
                        </div>
                      </div>
                      <div class="col">
                        <h3 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($profileName); ?></h3>
                        <p class="mb-3">
                          <span class="badge badge-primary px-2 py-1"><?php echo htmlspecialchars(user_is_admin() ? 'Administrador' : 'Funcionário'); ?></span>
                        </p>
                        <div class="d-flex flex-wrap" style="gap: 1.5rem;">
                          <div class="d-flex align-items-center text-muted small">
                            <i class="fe fe-map-pin mr-2"></i>
                            <?php echo htmlspecialchars($user['endereco'] ?: 'Endereço não definido'); ?>
                          </div>
                          <div class="d-flex align-items-center text-muted small">
                            <i class="fe fe-phone mr-2"></i>
                            <?php echo htmlspecialchars($user['telefone'] ?: 'Telefone não definido'); ?>
                          </div>
                          <div class="d-flex align-items-center text-muted small">
                            <i class="fe fe-mail mr-2"></i>
                            <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="firstname">Nome próprio</label>
                      <input type="text" id="firstname" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                    </div>
                    <div class="form-group col-md-6">
                      <label for="lastname">Apelido</label>
                      <input type="text" id="lastname" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="inputEmail4">Correio eletrónico</label>
                    <input type="email" class="form-control" id="inputEmail4" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="email@dominio.pt" required>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="inputAddress5">Morada</label>
                      <input type="text" class="form-control" id="inputAddress5" name="endereco" value="<?php echo htmlspecialchars($user['endereco']); ?>" placeholder="Morada completa">
                    </div>
                    <div class="form-group col-md-6">
                      <label for="inputPhone">Telefone</label>
                      <input type="tel" class="form-control" id="inputPhone" name="telefone" value="<?php echo htmlspecialchars($user['telefone']); ?>" placeholder="Número de telefone">
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Guardar alterações</button>
                </form>
<?php elseif ($tab === 'security'): ?>
                <?php if ($message): ?>
                  <?php render_alert($message, 'success', false); ?>
                <?php elseif ($error): ?>
                  <?php render_alert($error, 'danger', false); ?>
                <?php endif; ?>
                <form method="POST" action="<?php echo htmlspecialchars(admin_url('profile', ['tab' => 'security'])); ?>">
                  <input type="hidden" name="form_type" value="security">
                  <div class="row mt-5">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="currentPassword">Palavra-passe actual</label>
                        <input type="password" class="form-control" id="currentPassword" name="current_password" placeholder="Palavra-passe actual" required>
                      </div>
                      <div class="form-group">
                        <label for="newPassword">Nova palavra-passe</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Nova palavra-passe" required>
                      </div>
                      <div class="form-group">
                        <label for="confirmPassword">Confirmar nova palavra-passe</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Confirmar nova palavra-passe" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <h5 class="mb-3">Requisitos da palavra-passe</h5>
                      <ul class="small text-muted pl-4 mb-0">
                        <li> Mínimo de 8 caracteres </li>
                        <li> Pelo menos um número </li>
                        <li> Pelo menos um carácter especial </li>
                        <li> Não pode ser igual à palavra-passe actual </li>
                      </ul>
                      <div class="mt-4">
                        <p class="mb-2"><strong>Estado da conta</strong></p>
                        <p class="text-muted mb-1"><strong>Perfil:</strong> <?php echo htmlspecialchars(user_is_admin() ? 'Administrador' : 'Funcionário'); ?></p>
                        <p class="text-muted mb-0"><strong>Registado em:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))); ?></p>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Alterar palavra-passe</button>
                </form>
<?php endif; ?>
              </div> <!-- /.card-body -->
            </div> <!-- /.col-12 -->
          </div> <!-- .row -->
        </div> <!-- .container-fluid -->
      </main> <!-- main -->
    </div> <!-- .wrapper -->
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/simplebar.min.js"></script>
    <script src='js/jquery.stickOnScroll.js'></script>
    <script src="js/tinycolor-min.js"></script>
    <script src="js/config.js"></script>
    <script src="js/csnsa-upload.js"></script>
    <script src="js/apps.js"></script>
    <script src="js/funcionario-foto.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const phoneDisplay = document.querySelector('#inputPhone');
        const settingsForm = document.querySelector('input[name="form_type"][value="settings"]')?.closest('form');
        let itiProfile = null;
        if (phoneDisplay && settingsForm && window.intlTelInput) {
          itiProfile = window.intlTelInput(phoneDisplay, {
            initialCountry: 'pt',
            preferredCountries: ['pt', 'br', 'es', 'fr', 'uk'],
            separateDialCode: false,
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
          });
          if (phoneDisplay.value) {
            itiProfile.setNumber(phoneDisplay.value);
          }
          settingsForm.addEventListener('submit', function (event) {
            if (phoneDisplay.value.trim()) {
              if (!itiProfile.isValidNumber()) {
                event.preventDefault();
                alert('Por favor insira um número de telefone válido.');
                return;
              }
              phoneDisplay.value = itiProfile.getNumber();
            }
          });
        }

        const avatarInput = document.getElementById('profileAvatarInput');
        const avatarPreview = document.getElementById('profileAvatarPreview');
        const firstNameInput = document.getElementById('firstname');
        const lastNameInput = document.getElementById('lastname');
        if (!avatarPreview) {
          return;
        }

        function profileName() {
          return ((firstNameInput?.value || '') + ' ' + (lastNameInput?.value || '')).trim();
        }

        function showProfileInitials() {
          const letter = window.avatarInitialsFromName
            ? window.avatarInitialsFromName(profileName())
            : (profileName().charAt(0).toUpperCase() || '?');
          avatarPreview.innerHTML = '<div class="avatar-placeholder protected-avatar" oncontextmenu="return false;">' + letter + '</div>';
        }

        if (avatarInput) {
          avatarInput.addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (!file) {
              showProfileInitials();
              return;
            }
            const reader = new FileReader();
            reader.onload = function (loadEvent) {
              avatarPreview.innerHTML = '<img src="' + loadEvent.target.result + '" alt="Pré-visualização" class="avatar-img protected-avatar" draggable="false" oncontextmenu="return false;" ondragstart="return false;">';
            };
            reader.readAsDataURL(file);
          });
        }

        [firstNameInput, lastNameInput].forEach(function (input) {
          if (!input || avatarInput?.files?.length) {
            return;
          }
          input.addEventListener('input', function () {
            if (!avatarInput || !avatarInput.files.length) {
              const hasImg = avatarPreview.querySelector('img[src^="http"], img[src^="../"]');
              if (!hasImg) {
                showProfileInitials();
              }
            }
          });
        });
      });
    </script>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-56159088-1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];

      function gtag()
      {
        dataLayer.push(arguments);
      }
      gtag('js', new Date());
      gtag('config', 'UA-56159088-1');
    </script>
  </body>
</html>
