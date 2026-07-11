<?php

/**
 * Horários e cargas horárias predefinidos por equipa / serviço (CSNSA).
 */
function funcionario_horario_presets(): array
{
    return [
        'equipas' => [
            'ACAO_DIRETA' => [
                'carga_semanal' => 37.0,
                'horarios' => [
                    ['value' => '7h24 diárias (7,4h) — 37h semanais', 'label' => '7h24 diárias (7,4h) — 37h semanais'],
                ],
            ],
            'ACAO_DOM' => [
                'carga_semanal' => 37.5,
                'horarios' => [
                    ['value' => 'Segunda a sexta 08:30-16:30', 'label' => 'Segunda a sexta 08:30-16:30'],
                ],
            ],
            'COPA_SG' => [
                'carga_semanal' => 40.0,
                'horarios' => [
                    ['value' => '08:00-16:00 (todos os dias)', 'label' => '08:00-16:00 (todos os dias)'],
                    ['value' => '12:00-20:00 (todos os dias)', 'label' => '12:00-20:00 (todos os dias)'],
                ],
            ],
            'COZINHA' => [
                'carga_semanal' => 40.0,
                'horarios' => [
                    ['value' => '08:00-16:00 (todos os dias)', 'label' => '08:00-16:00 (todos os dias)'],
                    ['value' => '12:00-20:00 (todos os dias)', 'label' => '12:00-20:00 (todos os dias)'],
                ],
            ],
            'MOTORISTAS' => [
                'carga_semanal' => 40.0,
                'horarios' => [
                    ['value' => '08:00-12:00 e 18:00-20:00 (seg-sex)', 'label' => '08:00-12:00 e 18:00-20:00 (seg-sex)'],
                    ['value' => '08:00-13:00 e 17:00-20:00 (seg-sex)', 'label' => '08:00-13:00 e 17:00-20:00 (seg-sex)'],
                ],
            ],
            'LAVANDARIA' => [
                'carga_semanal' => 40.0,
                'horarios' => [
                    ['value' => 'Segunda a sexta 08:00-16:00', 'label' => 'Segunda a sexta 08:00-16:00'],
                ],
            ],
            'SERV_TEC' => [
                'carga_semanal' => 35.0,
                'horarios' => [
                    ['value' => 'Horário individual (7h/dia)', 'label' => 'Horário individual (7h/dia)'],
                ],
            ],
        ],
        'servicos' => [
            'acao_direta' => 'ACAO_DIRETA',
            'servicos_gerais' => 'COPA_SG',
            'refeitorio' => 'COPA_SG',
            'cozinha' => 'COZINHA',
            'lavandaria' => 'LAVANDARIA',
            'motorista' => 'MOTORISTAS',
            'tecnico_adm' => 'SERV_TEC',
        ],
    ];
}

function funcionario_horario_preset_por_equipa_id($conn, int $equipaId): ?string
{
    if ($equipaId <= 0 || !fe_table_exists($conn, 'equipas')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT codigo FROM equipas WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $equipaId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $codigo = strtoupper(trim((string) ($row['codigo'] ?? '')));

    return $codigo !== '' ? $codigo : null;
}
