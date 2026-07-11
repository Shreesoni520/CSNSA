<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_redirect_msg(string $page, string $type, $message, array $query = []): void
{
    $params = array_merge($query, [
        'type' => $type,
        'message' => (string) $message,
    ]);
    header('Location: ' . admin_url($page, $params));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function nullable_date($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_time($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function month_name($month): string
{
    $months = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    return $months[(int) $month] ?? '';
}

/**
 * Common public email providers, used to suggest corrections for typos
 * like "gmai.com" -> "gmail.com".
 */
function email_known_domains(): array
{
    return [
        'gmail.com',
        'googlemail.com',
        'hotmail.com',
        'outlook.com',
        'outlook.pt',
        'live.com',
        'live.com.pt',
        'yahoo.com',
        'yahoo.pt',
        'icloud.com',
        'me.com',
        'sapo.pt',
        'protonmail.com',
        'proton.me',
    ];
}

/**
 * Suggest a corrected domain if the given one looks like a typo of a known
 * provider (Levenshtein distance of 1-2). Returns null when no close match.
 */
function email_suggest_domain(string $domain): ?string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return null;
    }

    foreach (email_known_domains() as $known) {
        if ($domain === $known) {
            return null; // already correct
        }
    }

    $best = null;
    $bestDistance = PHP_INT_MAX;
    foreach (email_known_domains() as $known) {
        $distance = levenshtein($domain, $known);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $known;
        }
    }

    return ($best !== null && $bestDistance > 0 && $bestDistance <= 2) ? $best : null;
}

/**
 * Professional email validation.
 *
 * Checks, in order:
 *  - basic syntax (RFC compliant via filter_var)
 *  - a single @, a real local part and a domain with a TLD
 *  - that the domain actually resolves (MX record, or A/AAAA as fallback)
 *  - suggests a correction for obvious provider typos (gmai.com, hotmial.com...)
 *
 * Returns an empty string when the email is valid, otherwise a human-readable
 * error message (in Portuguese) explaining what is wrong.
 */
function validate_email_address(string $email): string
{
    $email = trim($email);

    if ($email === '') {
        return 'Indique o endereço de email.';
    }

    if (mb_strlen($email) > 254) {
        return 'O email é demasiado longo.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Formato de email inválido. Exemplo: nome@dominio.com';
    }

    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return 'O email deve conter exatamente um símbolo "@".';
    }

    [$local, $domain] = $parts;
    $domain = strtolower($domain);

    if ($local === '' || $domain === '') {
        return 'Email incompleto. Exemplo: nome@dominio.com';
    }

    // Domain must contain a dot and a valid TLD (at least 2 letters).
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
        return 'O domínio do email não é válido. Exemplo: nome@dominio.com';
    }

    // Catch obvious typos of popular providers before hitting DNS.
    $suggestion = email_suggest_domain($domain);
    if ($suggestion !== null) {
        return 'Quis dizer ' . $local . '@' . $suggestion . '? Verifique o domínio do email.';
    }

    // Verify the domain can actually receive email. MX is the correct record;
    // fall back to A/AAAA because some valid domains accept mail without MX.
    $domainForDns = $domain . '.';
    $hasDns = false;
    if (function_exists('checkdnsrr')) {
        $hasDns = checkdnsrr($domainForDns, 'MX')
            || checkdnsrr($domainForDns, 'A')
            || checkdnsrr($domainForDns, 'AAAA');
    } else {
        // If DNS checks are unavailable, don't block valid users.
        $hasDns = true;
    }

    if (!$hasDns) {
        return 'O domínio "' . $domain . '" não existe ou não aceita emails.';
    }

    return '';
}

function csnsa_alert_type(string $type): string
{
    $allowed = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'];

    return in_array($type, $allowed, true) ? $type : 'info';
}

function csnsa_alert_icon(string $type): string
{
    $map = [
        'success' => 'check-circle',
        'danger' => 'alert-circle',
        'warning' => 'alert-triangle',
        'info' => 'info',
        'primary' => 'bell',
        'secondary' => 'help-circle',
    ];

    return $map[$type] ?? 'info';
}

function render_alert(string $message, string $type = 'info', bool $dismissible = true, string $extraClass = ''): void
{
    $message = trim($message);
    if ($message === '') {
        return;
    }

    $type = csnsa_alert_type($type);
    $classes = 'alert alert-' . $type . ' csnsa-alert';
    if ($dismissible) {
        $classes .= ' alert-dismissible fade show';
    }
    if ($extraClass !== '') {
        $classes .= ' ' . $extraClass;
    }

    echo '<div class="' . e($classes) . '" role="alert">';
    echo '<div class="csnsa-alert-body">';
    echo '<i class="fe fe-' . e(csnsa_alert_icon($type)) . ' csnsa-alert-icon" aria-hidden="true"></i>';
    echo '<div class="csnsa-alert-text">' . e($message) . '</div>';
    echo '</div>';
    if ($dismissible) {
        echo '<button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>';
    }
    echo '</div>';
}

function render_alert_html(string $html, string $type = 'info', bool $dismissible = true, string $extraClass = ''): void
{
    $html = trim($html);
    if ($html === '') {
        return;
    }

    $type = csnsa_alert_type($type);
    $classes = 'alert alert-' . $type . ' csnsa-alert';
    if ($dismissible) {
        $classes .= ' alert-dismissible fade show';
    }
    if ($extraClass !== '') {
        $classes .= ' ' . $extraClass;
    }

    echo '<div class="' . e($classes) . '" role="alert">';
    echo '<div class="csnsa-alert-body">';
    echo '<i class="fe fe-' . e(csnsa_alert_icon($type)) . ' csnsa-alert-icon" aria-hidden="true"></i>';
    echo '<div class="csnsa-alert-text">' . $html . '</div>';
    echo '</div>';
    if ($dismissible) {
        echo '<button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>';
    }
    echo '</div>';
}

function render_flash_alert(): void
{
    $message = trim($_GET['message'] ?? '');
    if ($message === '') {
        return;
    }

    render_alert($message, (string) ($_GET['type'] ?? 'info'), true);
}
