<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensure_users_table(): void
{
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        role ENUM('admin','funcionario') NOT NULL DEFAULT 'funcionario',
        telefone VARCHAR(50) NOT NULL UNIQUE,
        endereco VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $conn->query($sql);

    $existingColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM users");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existingColumns[$row['Field']] = $row;
        }
    }

    $alter = [];
    if (!isset($existingColumns['username'])) {
        $alter[] = "ADD COLUMN username VARCHAR(100) NULL AFTER email";
    }
    if (!isset($existingColumns['telefone'])) {
        $alter[] = "ADD COLUMN telefone VARCHAR(50) NULL AFTER role";
    }

    if (!empty($alter)) {
        $conn->query('ALTER TABLE users ' . implode(', ', $alter));
    }
}

function normalize_phone(string $telefone): string
{
    $cleaned = preg_replace('/\D+/', '', $telefone);
    if (strpos($telefone, '+') !== false) {
        return '+' . $cleaned;
    }
    return $cleaned;
}

function is_username_taken(string $username): bool
{
    global $conn;
    $sql = "SELECT 1 FROM users WHERE username = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $found = (bool)$result->fetch_assoc();
    $stmt->close();
    return $found;
}

function is_email_taken(string $email, int $excludeUserId = 0): bool
{
    global $conn;
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    if ($excludeUserId > 0) {
        $sql = 'SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $email, $excludeUserId);
    } else {
        $sql = 'SELECT 1 FROM users WHERE email = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $found = (bool) $result->fetch_assoc();
    $stmt->close();
    return $found;
}

function is_utilizador_email_taken(string $email, int $excludeUtilizadorId = 0): bool
{
    global $conn;
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    if ($excludeUtilizadorId > 0) {
        $sql = 'SELECT 1 FROM utilizadores WHERE email = ? AND id <> ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $email, $excludeUtilizadorId);
    } else {
        $sql = 'SELECT 1 FROM utilizadores WHERE email = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $found = (bool) $result->fetch_assoc();
    $stmt->close();
    return $found;
}

function is_telefone_taken(string $telefone): bool
{
    global $conn;
    $sql = "SELECT 1 FROM users WHERE telefone = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    $result = $stmt->get_result();
    $found = (bool)$result->fetch_assoc();
    $stmt->close();
    return $found;
}

ensure_users_table();

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function count_utilizadores(): int
{
    global $conn;
    $sql = 'SELECT COUNT(*) AS total FROM utilizadores';
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return intval($row['total'] ?? 0);
}

function registration_is_open(): bool
{
    // First-time setup only: open while both account tables are empty.
    return count_users() === 0 && count_utilizadores() === 0;
}

function require_admin(): void
{
    require_login();

    if (!user_is_admin()) {
        logout_user();
        header('Location: ' . admin_url('auth-login', ['message' => 'Acesso restrito ao administrador.']));
        exit;
    }
}

function get_papel_id_by_slug(string $slug): ?int
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM papeis WHERE slug = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : null;
}

function utilizador_has_admin_papel(int $utilizadorId): bool
{
    global $conn;
    if ($utilizadorId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        WHERE up.utilizador_id = ? AND p.slug = 'administrador'");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $utilizadorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function assign_admin_papel_to_utilizador(int $utilizadorId): void
{
    global $conn;
    $papelId = get_papel_id_by_slug('administrador');
    if ($papelId === null || $utilizadorId <= 0) {
        return;
    }

    $stmt = $conn->prepare('INSERT IGNORE INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $utilizadorId, $papelId);
    $stmt->execute();
    $stmt->close();
}

function remove_admin_papel_from_utilizador(int $utilizadorId): void
{
    global $conn;
    $papelId = get_papel_id_by_slug('administrador');
    if ($papelId === null || $utilizadorId <= 0) {
        return;
    }

    $stmt = $conn->prepare('DELETE FROM utilizador_papeis WHERE utilizador_id = ? AND papel_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $utilizadorId, $papelId);
    $stmt->execute();
    $stmt->close();
}

function set_user_role_by_email(string $email, string $role): void
{
    global $conn;
    if (!in_array($role, ['admin', 'funcionario'], true)) {
        return;
    }

    $email = trim($email);
    if ($email === '') {
        return;
    }

    $stmt = $conn->prepare('UPDATE users SET role = ? WHERE email = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $role, $email);
    $stmt->execute();
    $stmt->close();
}

function sync_user_role_with_papel(string $email, ?int $papelId): void
{
    $adminPapelId = get_papel_id_by_slug('administrador');
    $isAdmin = $adminPapelId !== null && $papelId === $adminPapelId;
    set_user_role_by_email($email, $isAdmin ? 'admin' : 'funcionario');
}

function find_utilizador_id_by_email(string $email): int
{
    global $conn;
    $email = trim($email);
    if ($email === '') {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM utilizadores WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : 0;
}

function current_utilizador_id(): int
{
    $utilizadorId = (int) ($_SESSION['utilizador_id'] ?? 0);
    if ($utilizadorId > 0) {
        return $utilizadorId;
    }

    $user = current_user();
    if (!$user) {
        return 0;
    }

    $found = find_utilizador_id_by_email((string) ($user['email'] ?? ''));
    if ($found > 0) {
        $_SESSION['utilizador_id'] = $found;
    }

    return $found;
}

function user_is_admin(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        return true;
    }

    return utilizador_has_admin_papel(current_utilizador_id());
}

function account_email_is_valid(?array $user): bool
{
    if (!$user || trim((string) ($user['email'] ?? '')) === '') {
        return false;
    }

    return validate_email_address((string) $user['email']) === '';
}

function refresh_session_permissions(): void
{
    global $conn;

    if (!is_logged_in()) {
        return;
    }

    if (count_users() === 0) {
        logout_user();
        return;
    }

    $sessionUser = $_SESSION['user'] ?? null;
    if (!account_email_is_valid($sessionUser)) {
        logout_user();
        return;
    }

    $email = trim((string) ($sessionUser['email'] ?? ''));
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        permissoes_atualizar_sessao();
        return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $usersRow = $result->fetch_assoc();
    $stmt->close();

    if (!$usersRow) {
        $utilizadorId = current_utilizador_id();
        if ($utilizadorId <= 0 || !permissoes_utilizador_pode_entrar($conn, $utilizadorId)) {
            logout_user();
            return;
        }
        permissoes_atualizar_sessao();
        return;
    }

    $fresh = get_user_by_id((int) $usersRow['id']);
    if (!$fresh) {
        logout_user();
        return;
    }

    if (!account_email_is_valid($fresh)) {
        logout_user();
        return;
    }

    $_SESSION['user'] = $fresh;
    permissoes_atualizar_sessao();
}

function user_has_role(string $role): bool
{
    if ($role === 'admin') {
        return user_is_admin();
    }

    return is_logged_in() && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $role;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $destino = $_SERVER['REQUEST_URI'] ?? admin_url('inicio');
        header('Location: ' . admin_url('auth-login', ['redirect' => $destino]));
        exit;
    }

    refresh_session_permissions();
}

function auth_safe_redirect(string $redirect): string
{
    $redirect = trim($redirect);

    if ($redirect === '' || preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
        return admin_url('inicio');
    }

    // Absolute local path (e.g. /CSNSA/admin/index.php?csnsa=funcionarios)
    if (str_starts_with($redirect, '/')) {
        return $redirect;
    }

    // Already a built relative URL (e.g. index.php?csnsa=inicio) — return as-is so we
    // don't wrap it again inside another csnsa= parameter (which caused a 404).
    if (str_contains($redirect, 'index.php') || str_contains($redirect, '?') || str_contains($redirect, '=')) {
        return $redirect;
    }

    // Bare page slug (e.g. inicio)
    return admin_url($redirect);
}

function sync_utilizador_from_user(array $user): ?int
{
    global $conn;

    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '') {
        return null;
    }

    $nome = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if ($nome === '') {
        $nome = (string) ($user['username'] ?? $email);
    }

    $stmt = $conn->prepare('SELECT id, estado FROM utilizadores WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $utilizador = $result->fetch_assoc();
    $stmt->close();

    if ($utilizador) {
        $utilizadorId = (int) $utilizador['id'];
        if ($utilizador['estado'] !== 'ativo') {
            $stmt = $conn->prepare("UPDATE utilizadores SET estado = 'ativo' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $utilizadorId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (isset($user['role']) && $user['role'] === 'admin') {
            assign_admin_papel_to_utilizador($utilizadorId);
        }
    } else {
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO utilizadores (nome, email, password_hash, estado) VALUES (?, ?, ?, 'ativo')");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('sss', $nome, $email, $passwordHash);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $utilizadorId = (int) $conn->insert_id;
        $stmt->close();

        if (isset($user['role']) && $user['role'] === 'admin') {
            assign_admin_papel_to_utilizador($utilizadorId);
        }
    }

    $_SESSION['utilizador_id'] = $utilizadorId;
    $_SESSION['utilizador_nome'] = $nome;

    return $utilizadorId;
}

function login_user_utilizador(string $email, string $password): bool
{
    global $conn;

    $email = strtolower(trim($email));
    if ($email === '' || validate_email_address($email) !== '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT id, nome, email, password_hash, estado FROM utilizadores WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $utilizador = $result->fetch_assoc();
    $stmt->close();

    if (!$utilizador || $utilizador['estado'] !== 'ativo' || !password_verify($password, $utilizador['password_hash'])) {
        return false;
    }

    $utilizadorId = (int) $utilizador['id'];
    $_SESSION['utilizador_id'] = $utilizadorId;
    $_SESSION['utilizador_nome'] = $utilizador['nome'];

    $stmt = $conn->prepare('UPDATE utilizadores SET ultimo_login_at = NOW() WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $utilizadorId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            return true;
        }
    }

    $nomePartes = preg_split('/\s+/', trim($utilizador['nome']), 2);
    $_SESSION['user'] = [
        'id' => $utilizadorId,
        'email' => $utilizador['email'],
        'username' => strstr($utilizador['email'], '@', true) ?: $utilizador['email'],
        'first_name' => $nomePartes[0] ?? $utilizador['nome'],
        'last_name' => $nomePartes[1] ?? '',
        'role' => 'funcionario',
        'telefone' => '',
        'endereco' => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    return true;
}

function login_email_for_attempt(string $login, ?array $user = null): ?string
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }

    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        return $login;
    }

    if ($user && !empty($user['email'])) {
        return (string) $user['email'];
    }

    global $conn;
    $stmt = $conn->prepare('SELECT email FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return !empty($row['email']) ? (string) $row['email'] : null;
}

function login_user(string $login, string $password): bool
{
    global $conn;
    $login = trim($login);
    $phone = normalize_phone($login);

    // Allow sign in by username, email OR telefone (raw or normalized).
    $sql = "SELECT * FROM users WHERE username = ? OR email = ? OR telefone = ? OR telefone = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $login, $login, $login, $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        if (!account_email_is_valid($user)) {
            return false;
        }

        // When an account exists in `users`, that table is the single source of
        // truth for its password. We must NOT fall back to the utilizadores
        // bridge here, otherwise a drifted utilizadores.password_hash would let
        // the same account log in with a second, different password.
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            sync_utilizador_from_user($user);
            return finalize_admin_login();
        }

        return false;
    }

    // Legacy accounts that only exist in the utilizadores table (never migrated
    // into users) can still authenticate through the bridge.
    $email = login_email_for_attempt($login, $user);
    if ($email && login_user_utilizador($email, $password)) {
        session_regenerate_id(true);
        return finalize_admin_login();
    }

    return false;
}

function finalize_admin_login(): bool
{
    global $conn;

    permissoes_atualizar_sessao();

    if (user_is_admin()) {
        return true;
    }

    $utilizadorId = current_utilizador_id();
    if ($utilizadorId > 0 && permissoes_utilizador_pode_entrar($conn, $utilizadorId)) {
        return true;
    }

    logout_user();
    return false;
}

function remember_login(): void
{
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        session_id(),
        time() + 60 * 60 * 24 * 30,
        $params['path'] ?: '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function count_users(): int
{
    global $conn;
    $sql = "SELECT COUNT(*) AS total FROM users";
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return intval($row['total'] ?? 0);
}

function register_user(array $data, string $role = 'admin'): bool
{
    global $conn;

    if (!registration_is_open()) {
        return false;
    }

    $role = 'admin';

    $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
    if (validate_email_address($data['email']) !== '') {
        return false;
    }

    $sql = "INSERT INTO users (email, username, password, first_name, last_name, role, telefone, endereco) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $normalizedPhone = normalize_phone($data['telefone']);
    $stmt->bind_param(
        'ssssssss',
        $data['email'],
        $data['username'],
        $hash,
        $data['first_name'],
        $data['last_name'],
        $role,
        $normalizedPhone,
        $data['endereco']
    );
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        $userId = (int) $conn->insert_id;
        $fresh = get_user_by_id($userId);
        if ($fresh) {
            $utilizadorId = sync_utilizador_from_user($fresh);
            if ($utilizadorId && $role === 'admin') {
                $stmt = $conn->prepare("SELECT id FROM papeis WHERE slug = 'administrador' LIMIT 1");
                if ($stmt) {
                    $stmt->execute();
                    $papelResult = $stmt->get_result();
                    $papel = $papelResult->fetch_assoc();
                    $stmt->close();
                    if ($papel) {
                        $papelId = (int) $papel['id'];
                        $stmt = $conn->prepare('INSERT IGNORE INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
                        if ($stmt) {
                            $stmt->bind_param('ii', $utilizadorId, $papelId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }

            $stmt = $conn->prepare('UPDATE utilizadores SET password_hash = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $hash, $utilizadorId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    return $result;
}

function get_user_by_id(int $id): ?array
{
    global $conn;
    require_once __DIR__ . '/avatar_helpers.php';
    if (fe_table_exists($conn, 'users')) {
        ensure_users_avatar_column($conn);
    }
    $sql = "SELECT id, email, username, first_name, last_name, role, telefone, endereco, avatar, created_at FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function refresh_current_user(): ?array
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    $fresh = get_user_by_id((int)$user['id']);
    if ($fresh) {
        $_SESSION['user'] = $fresh;
    }
    return $fresh;
}

function update_user_profile(int $userId, array $data): bool
{
    global $conn;

    $current = get_user_by_id($userId);
    if (!$current) {
        return false;
    }

    $newEmail = strtolower(trim((string) ($data['email'] ?? $current['email'] ?? '')));
    $oldEmail = strtolower(trim((string) ($current['email'] ?? '')));

    $sql = 'UPDATE users SET first_name = ?, last_name = ?, email = ?, telefone = ?, endereco = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param(
        'sssssi',
        $data['first_name'],
        $data['last_name'],
        $newEmail,
        $data['telefone'],
        $data['endereco'],
        $userId
    );
    $result = $stmt->execute();
    $stmt->close();

    if (!$result) {
        return false;
    }

    if ($newEmail !== $oldEmail && $oldEmail !== '') {
        $stmt = $conn->prepare('UPDATE utilizadores SET email = ? WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('ss', $newEmail, $oldEmail);
            $stmt->execute();
            $stmt->close();
        }
    }

    return true;
}

function change_user_password(int $userId, string $currentPassword, string $newPassword): bool
{
    global $conn;

    $sql = "SELECT email, password FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    $email = (string) $row['email'];

    // The current password may live in users.password OR (for accounts that came
    // in through the utilizadores bridge) in utilizadores.password_hash. Accept
    // either so the change is not wrongly rejected when the two tables differ.
    $currentOk = !empty($row['password']) && password_verify($currentPassword, $row['password']);

    if (!$currentOk && $email !== '') {
        $stmt = $conn->prepare('SELECT password_hash FROM utilizadores WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $uResult = $stmt->get_result();
            $uRow = $uResult->fetch_assoc();
            $stmt->close();
            if ($uRow && !empty($uRow['password_hash']) && password_verify($currentPassword, $uRow['password_hash'])) {
                $currentOk = true;
            }
        }
    }

    if (!$currentOk) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $hash, $userId);
    $result = $stmt->execute();
    $stmt->close();

    if (!$result) {
        return false;
    }

    // Keep the utilizadores bridge in sync so future logins work via either path.
    if ($email !== '') {
        $stmt = $conn->prepare('UPDATE utilizadores SET password_hash = ? WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('ss', $hash, $email);
            $stmt->execute();
            $stmt->close();
        }
    }

    return true;
}
