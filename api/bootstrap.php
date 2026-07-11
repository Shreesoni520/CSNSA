<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/includes/funcionarios_estado.php';
require_once __DIR__ . '/../admin/includes/helpers.php';
require_once __DIR__ . '/../admin/includes/ponto_ingest.php';

ponto_ensure_device_schema($conn);

function ponto_api_respond_text(string $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
    exit;
}

function ponto_api_respond_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
