<?php
/** Vista grelha — requer: $funcionarios, $diasNoMes, $ano, $mes, $escalaGuardada, $turnos, $funcionariosSubstituicao, $tiposDia */
?>
<div class="table-responsive escala-wrapper">
    <table class="table table-bordered table-sm align-middle escala-table">
        <thead>
            <tr>
                <th class="escala-sticky-col">Funcionário</th>
                <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                    <?php $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia); ?>
                    <th class="text-center escala-dia-header <?php echo in_array(date('N', strtotime($data)), [6, 7], true) ? 'escala-fim-semana' : ''; ?>">
                        <div><?php echo $dia; ?></div>
                        <small><?php echo e(weekday_short($data)); ?></small>
                    </th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($funcionarios as $funcionario): ?>
                <tr>
                    <th class="escala-sticky-col escala-funcionario">
                        <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                        <small class="text-muted">
                            <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número'); ?>
                            <?php if ($funcionario['equipa_nome']): ?>
                                · <?php echo e($funcionario['equipa_nome']); ?>
                            <?php endif; ?>
                        </small>
                        <?php $diasFunc = count($escalaGuardada[(int) $funcionario['id']] ?? []); ?>
                        <?php if ($diasFunc > 0): ?>
                            <span class="badge badge-light mt-1"><?php echo $diasFunc; ?> dia(s)</span>
                        <?php endif; ?>
                    </th>
                    <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                        <?php
                        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                        $fimDeSemana = in_array(date('N', strtotime($data)), [6, 7], true);
                        $registo = $escalaGuardada[(int) $funcionario['id']][$dia] ?? [];
                        $celula = escala_mensal_dados_celula($registo);
                        $cellClasses = ['escala-cell'];

                        if ($fimDeSemana) {
                            $cellClasses[] = 'escala-fim-semana';
                        }

                        if ($celula['folga_trabalhada']) {
                            $cellClasses[] = 'escala-folga-trabalhada';
                        }

                        if ($celula['tipo_dia'] === 'substituicao') {
                            $cellClasses[] = 'escala-substituicao';
                        }

                        if ($celula['guardado']) {
                            $cellClasses[] = 'escala-guardado';
                            if ($celula['tipo_dia'] !== '') {
                                $cellClasses[] = 'escala-tipo-' . preg_replace('/[^a-z_]/', '', $celula['tipo_dia']);
                            }
                        }

                        if ($celula['guardado'] && $celula['tipo_dia'] === 'turno' && $celula['turno_id'] <= 0) {
                            $cellClasses[] = 'escala-incompleto';
                        }

                        $funcionarioId = (int) $funcionario['id'];
                        $layout = 'grid';
                        ?>
                        <td class="<?php echo e(implode(' ', $cellClasses)); ?>" data-dia="<?php echo (int) $dia; ?>" data-fds="<?php echo $fimDeSemana ? '1' : '0'; ?>">
                            <?php include __DIR__ . '/escala_celula_campos.php'; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
