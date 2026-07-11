<?php

require_once __DIR__ . '/funcionarios_estado.php';

function avatar_initials_from_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }

    $parts = preg_split('/\s+/u', $name);

    return mb_strtoupper(mb_substr($parts[0], 0, 1));
}

function avatar_public_url(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }

    $path = ltrim($path, '/');
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }

    return '../' . $path;
}

function avatar_render_html(?string $imagePath, string $name, string $sizeClass = '', string $extraClass = ''): string
{
    $classes = trim('avatar-img rounded-circle ' . $extraClass);
    $placeholderClasses = trim('avatar-placeholder ' . $sizeClass . ' ' . $extraClass);
    $url = avatar_public_url($imagePath);

    if ($url) {
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($name) . '" class="' . htmlspecialchars(trim($classes . ' protected-avatar')) . '" draggable="false" oncontextmenu="return false;" ondragstart="return false;">';
    }

    $initials = htmlspecialchars(avatar_initials_from_name($name));

    return '<div class="' . htmlspecialchars(trim($placeholderClasses . ' protected-avatar')) . '" aria-label="' . htmlspecialchars($name) . '" oncontextmenu="return false;">' . $initials . '</div>';
}

function avatar_save_image_upload(array $file, string $subdir): ?string
{
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível carregar a imagem.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('A imagem não pode exceder 5 MB.');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Formato de imagem inválido. Use JPG ou PNG.');
    }

    $mimePermitidos = ['image/jpeg', 'image/png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $mimePermitidos, true)) {
        throw new RuntimeException('O conteúdo do ficheiro não corresponde a um formato permitido.');
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destRel = 'uploads/' . trim($subdir, '/') . '/' . $newName;
    $destAbs = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destRel);

    $dir = dirname($destAbs);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
    }

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        throw new RuntimeException('Não foi possível guardar a imagem.');
    }

    return $destRel;
}

function ensure_users_avatar_column($conn): void
{
    if (!fe_table_exists($conn, 'users') || fe_column_exists($conn, 'users', 'avatar')) {
        return;
    }

    @mysqli_query($conn, 'ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER endereco');
}

function update_user_avatar(int $userId, ?string $avatarPath): bool
{
    global $conn;

    ensure_users_avatar_column($conn);

    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $avatarPath, $userId);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}
