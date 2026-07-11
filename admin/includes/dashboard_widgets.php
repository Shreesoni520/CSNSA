<?php

function dash_notificacoes($conn): array
{
    $items = [];

    if (!fe_table_exists($conn, 'funcionarios')) {
        return $items;
    }

    site_run_migrations($conn);

    $temNasc = fe_column_exists($conn, 'funcionarios', 'data_nascimento');
    $temDiut = fe_column_exists($conn, 'funcionarios', 'data_diuturnidade');
    $hoje = date('Y-m-d');
    $mesDia = date('m-d');

    $sql = "SELECT id, nome, data_nascimento, data_diuturnidade, data_admissao
            FROM funcionarios
            WHERE estado NOT IN ('arquivado', 'inativo')";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $items;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        if ($temNasc && !empty($row['data_nascimento'])) {
            $nasc = date('m-d', strtotime($row['data_nascimento']));
            $diff = (int) ((strtotime(date('Y') . '-' . $nasc) - strtotime($hoje)) / 86400);
            if ($diff >= 0 && $diff <= 14) {
                $items[] = [
                    'tipo' => 'aniversario',
                    'nome' => $row['nome'],
                    'data' => date('d/m', strtotime($row['data_nascimento'])),
                    'dias' => $diff,
                ];
            }
        }

        $diutDate = null;
        if ($temDiut && !empty($row['data_diuturnidade'])) {
            $diutDate = $row['data_diuturnidade'];
        } elseif (!empty($row['data_admissao'])) {
            $diutDate = $row['data_admissao'];
        }

        if ($diutDate) {
            $diutMd = date('m-d', strtotime($diutDate));
            $diff = (int) ((strtotime(date('Y') . '-' . $diutMd) - strtotime($hoje)) / 86400);
            if ($diff >= 0 && $diff <= 14) {
                $items[] = [
                    'tipo' => 'diuturnidade',
                    'nome' => $row['nome'],
                    'data' => date('d/m', strtotime($diutDate)),
                    'dias' => $diff,
                ];
            }
        }
    }

    usort($items, static function ($a, $b) {
        return $a['dias'] <=> $b['dias'];
    });

    return $items;
}

function dash_verificacao_ponto($conn): array
{
    $pendentes = [];

    if (!fe_table_exists($conn, 'registos_ponto') || !fe_table_exists($conn, 'funcionarios')) {
        return $pendentes;
    }

    $sql = "SELECT f.nome, f.numero_mecanografico, rp.tipo, rp.data_hora
            FROM registos_ponto rp
            INNER JOIN funcionarios f ON f.id = rp.funcionario_id
            WHERE DATE(rp.data_hora) = CURDATE()
              AND f.estado = 'ativo'
            ORDER BY rp.data_hora DESC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $pendentes;
    }

    $ultimo = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $nome = $row['nome'];
        if (!isset($ultimo[$nome])) {
            $ultimo[$nome] = $row;
        }
    }

    foreach ($ultimo as $row) {
        if (!in_array($row['tipo'], ['entrada', 'fim_pausa'], true)) {
            $pendentes[] = [
                'nome' => $row['nome'],
                'numero' => $row['numero_mecanografico'] ?: '-',
                'estado' => 'Sem entrada ativa',
                'hora' => date('H:i', strtotime($row['data_hora'])),
            ];
        }
    }

    $sqlAtivos = "SELECT f.nome, f.numero_mecanografico
        FROM funcionarios f
        WHERE f.estado = 'ativo'
          AND NOT EXISTS (
            SELECT 1 FROM registos_ponto rp
            WHERE rp.funcionario_id = f.id AND DATE(rp.data_hora) = CURDATE()
          )
        ORDER BY f.nome ASC
        LIMIT 20";
    $result2 = mysqli_query($conn, $sqlAtivos);
    if ($result2) {
        while ($row = mysqli_fetch_assoc($result2)) {
            $pendentes[] = [
                'nome' => $row['nome'],
                'numero' => $row['numero_mecanografico'] ?: '-',
                'estado' => 'Sem picagem hoje',
                'hora' => '-',
            ];
        }
    }

    return $pendentes;
}

function dash_mapa_presencas($conn): array
{
    $mapa = ['presentes' => 0, 'ausentes' => 0, 'pausa' => 0, 'total' => 0];

    if (!fe_table_exists($conn, 'funcionarios')) {
        return $mapa;
    }

    $estado = fe_carregar_funcionarios_estado($conn);
    foreach ($estado['funcionarios'] as $f) {
        if (($f['estado'] ?? '') !== 'ativo') {
            continue;
        }
        $mapa['total']++;
        switch ($f['estado_trabalho'] ?? '') {
            case 'a_trabalhar':
                $mapa['presentes']++;
                break;
            case 'em_pausa':
                $mapa['pausa']++;
                break;
            default:
                $mapa['ausentes']++;
        }
    }

    return $mapa;
}
