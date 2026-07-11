<?php
/** Campos de um dia na escala — incluir com variáveis: $funcionarioId, $dia, $celula, $turnos, $funcionariosSubstituicao, $tiposDia, $layout */
$layout = $layout ?? 'grid';
$celulaId = 'escala' . (int) $funcionarioId . '_' . (int) $dia;
?>
<select name="escala[<?php echo (int) $funcionarioId; ?>][<?php echo (int) $dia; ?>][tipo_dia]" class="form-control form-control-sm escala-tipo" title="O que acontece neste dia">
    <option value="" <?php echo $celula['tipo_dia'] === '' ? 'selected' : ''; ?>>— O que faz neste dia? —</option>
    <?php foreach ($tiposDia as $tipo): ?>
        <option value="<?php echo e($tipo); ?>" <?php echo $tipo === $celula['tipo_dia'] ? 'selected' : ''; ?>>
            <?php echo e(tipo_label($tipo)); ?>
        </option>
    <?php endforeach; ?>
</select>

<select name="escala[<?php echo (int) $funcionarioId; ?>][<?php echo (int) $dia; ?>][turno_id]" class="form-control form-control-sm mt-1 escala-turno" title="Horário de trabalho">
    <option value="">Escolher turno…</option>
    <?php foreach ($turnos as $turno): ?>
        <option value="<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['id'] === $celula['turno_id'] ? 'selected' : ''; ?>>
            <?php echo e($turno['codigo'] ?: $turno['nome']); ?>
            <?php if (!empty($turno['nome']) && !empty($turno['codigo'])): ?>
                — <?php echo e($turno['nome']); ?>
            <?php endif; ?>
        </option>
    <?php endforeach; ?>
</select>

<div class="escala-campos-extra <?php echo $layout === 'simples' ? 'escala-campos-extra--simples' : ''; ?>">
    <select name="escala[<?php echo (int) $funcionarioId; ?>][<?php echo (int) $dia; ?>][substitui_funcionario_id]" class="form-control form-control-sm mt-1 escala-substitui">
        <option value="">Substitui quem?</option>
        <?php foreach ($funcionariosSubstituicao as $opcaoFuncionario): ?>
            <?php if ((int) $opcaoFuncionario['id'] === (int) $funcionarioId) {
                continue;
            } ?>
            <option value="<?php echo (int) $opcaoFuncionario['id']; ?>" <?php echo (int) $opcaoFuncionario['id'] === $celula['substitui_id'] ? 'selected' : ''; ?>>
                <?php echo e($opcaoFuncionario['nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="form-check mt-1 escala-folga-check">
        <input class="form-check-input escala-folga-trabalhada-check" type="checkbox" name="escala[<?php echo (int) $funcionarioId; ?>][<?php echo (int) $dia; ?>][folga_trabalhada]" id="folga<?php echo e($celulaId); ?>" <?php echo $celula['folga_trabalhada'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="folga<?php echo e($celulaId); ?>">Folga trabalhada</label>
    </div>

    <input type="text" name="escala[<?php echo (int) $funcionarioId; ?>][<?php echo (int) $dia; ?>][observacoes]" class="form-control form-control-sm mt-1 escala-obs" placeholder="Nota (opcional)" value="<?php echo e($celula['observacoes']); ?>">
</div>

<?php if ($layout === 'simples'): ?>
    <button type="button" class="btn btn-link btn-sm p-0 mt-1 escala-toggle-extra" data-target="<?php echo e($celulaId); ?>">Mais opções</button>
<?php endif; ?>
