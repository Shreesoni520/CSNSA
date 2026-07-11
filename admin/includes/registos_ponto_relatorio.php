<?php

function registos_ponto_diarios_por_funcionario(mysqli $conn, int $funcionarioId, string $dataInicio, string $dataFim): array
{
    if ($funcionarioId <= 0) {
        return [];
    }

    $sql = "SELECT DATE(COALESCE(data_referencia, data_hora)) AS data,
                   MIN(CASE WHEN tipo = 'entrada' THEN TIME(data_hora) END) AS hora_entrada,
                   MAX(CASE WHEN tipo = 'saida' THEN TIME(data_hora) END) AS hora_saida
            FROM registos_ponto
            WHERE funcionario_id = ?
              AND estado = 'valido'
              AND DATE(COALESCE(data_referencia, data_hora)) BETWEEN ? AND ?
            GROUP BY DATE(COALESCE(data_referencia, data_hora))
            ORDER BY data ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iss', $funcionarioId, $dataInicio, $dataFim);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'data' => $row['data'],
            'hora_entrada' => $row['hora_entrada'] ?: '-',
            'hora_saida' => $row['hora_saida'] ?: '-',
        ];
    }

    $stmt->close();

    return $rows;
}
