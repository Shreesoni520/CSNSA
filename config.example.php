<?php
date_default_timezone_set('Europe/Lisbon');

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "csnsa";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na ligacao: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

require_once __DIR__ . '/admin/includes/funcionarios_estado.php';
require_once __DIR__ . '/admin/includes/site_migrations.php';
site_run_migrations($conn);

// Relógio de ponto / integração com máquina biométrica
$ponto_api_secret = '';
$ponto_auto_registar_dispositivos = true;
