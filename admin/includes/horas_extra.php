<?php

function he_carga_diaria_minutos($funcionario): int
{
    $semanal = (float) ($funcionario['carga_horaria_semanal'] ?? 40);
    if ($semanal <= 0) {
        return 480;
    }

    return (int) round(($semanal / 5) * 60);
}

function he_minutos_no_periodo_noturno($inicio, $fim): int
{
    $cursor = strtotime($inicio);
    $fimTs = strtotime($fim);
    $minutos = 0;

    while ($cursor < $fimTs) {
        $hora = (int) date('G', $cursor);
        $noturno = $hora >= 22 || $hora < 7;
        $cursor += 60;
        if ($noturno && $cursor <= $fimTs) {
            $minutos++;
        }
    }

    return $minutos;
}

function he_calcular_extra_minutos(int $minutosTrabalhados, int $cargaDiariaMinutos): int
{
    return max(0, $minutosTrabalhados - $cargaDiariaMinutos);
}

function he_percentagem_extra(int $minutoIndice, bool $noturno): float
{
    if ($noturno) {
        return 2.0;
    }

    return $minutoIndice === 0 ? 1.5 : 1.75;
}

function he_formatar_percentagem(float $valor): string
{
    return rtrim(rtrim(number_format($valor * 100, 0), '0'), '.') . '%';
}
