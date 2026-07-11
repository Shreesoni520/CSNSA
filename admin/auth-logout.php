<?php
require_once __DIR__ . '/includes/auth.php';

logout_user();
header('Location: ' . admin_url('auth-login'));
exit;
