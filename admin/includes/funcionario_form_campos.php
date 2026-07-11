<?php
if (!function_exists('upload_field')) {
    require_once __DIR__ . '/upload_field.php';
}
$horarioFormUid = $horarioFormUid ?? ('fh' . preg_replace('/\D+/', '', (string) ($fotoPreviewId ?? 'create')));
?>
<div class="row">
<?php
$showFotoField = $showFotoField ?? true;
$fotoPreviewId = $fotoPreviewId ?? 'funcionarioFotoPreview';
$fotoInputId = $fotoInputId ?? 'funcionarioFotoInput';
$fotoNomeAtual = $funcionarioForm['nome'] ?? '';
$fotoAtual = $funcionarioForm['foto'] ?? '';
?>
<?php if ($showFotoField): ?>
    <div class="col-md-12 mb-3">
        <label class="form-label">Fotografia do funcionário</label>
        <div class="funcionario-foto-wrapper">
            <div class="funcionario-foto-preview" id="<?php echo e($fotoPreviewId); ?>">
                <?php echo avatar_render_html($fotoAtual ?: null, $fotoNomeAtual); ?>
            </div>
            <div>
                <?php
                upload_field([
                    'id' => $fotoInputId,
                    'name' => 'foto',
                    'accept' => '.jpg,.jpeg,.png',
                    'variant' => 'avatar',
                    'button_text' => 'Escolher fotografia',
                    'hint' => 'JPG ou PNG, até 5 MB.',
                    'input_class' => 'js-funcionario-foto-input',
                ]);
                ?>
            </div>
        </div>
    </div>
<?php endif; ?>
    <div class="col-md-6 mb-3">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" value="<?php echo e($funcionarioForm['nome'] ?? ''); ?>" required>
        <div class="invalid-feedback">Indique o nome do funcionário.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Número mecanográfico</label>
        <input type="text" name="numero_mecanografico" class="form-control" value="<?php echo e($funcionarioForm['numero_mecanografico'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Função</label>
        <input type="text" name="funcao" class="form-control" value="<?php echo e($funcionarioForm['funcao'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?php echo e($funcionarioForm['email'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Telefone</label>
        <?php
        $telefoneFieldId = $telefoneFieldId ?? ('funcionarioTelefone' . (int) ($funcionarioForm['id'] ?? 0));
        $telefoneAtual = (string) ($funcionarioForm['telefone'] ?? '');
        ?>
        <input
            type="tel"
            id="<?php echo e($telefoneFieldId); ?>"
            class="form-control js-funcionario-telefone-display"
            value="<?php echo e($telefoneAtual); ?>"
            placeholder="912 345 678"
            autocomplete="tel">
        <input type="hidden" name="telefone" class="js-funcionario-telefone-hidden" value="<?php echo e($telefoneAtual); ?>">
        <small class="text-muted d-block mt-1">Selecione o país e insira o número.</small>
        <div class="invalid-feedback">Introduza um número de telefone válido.</div>
    </div>
    <?php if ($temEquipas): ?>
        <div class="col-md-6 mb-3">
            <label class="form-label">Equipa</label>
            <select name="equipa_id" class="form-control js-equipa-select">
                <option value="">Sem equipa</option>
                <?php foreach ($equipas as $equipa): ?>
                    <?php $eqCodigo = strtoupper(trim((string) ($equipa['codigo'] ?? ''))); ?>
                    <option value="<?php echo (int) $equipa['id']; ?>"
                        data-codigo="<?php echo e($eqCodigo); ?>"
                        <?php echo (int) ($funcionarioForm['equipa_id'] ?? 0) === (int) $equipa['id'] ? 'selected' : ''; ?>>
                        <?php echo e($equipa['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Ao escolher a equipa, a carga semanal e o horário são sugeridos automaticamente.</small>
        </div>
    <?php endif; ?>
    <div class="col-md-4 mb-3">
        <?php
        $dataNascimentoMax = $dataNascimentoMax ?? funcionario_data_nascimento_maxima();
        $dataNascimentoObrigatoria = !empty($dataNascimentoObrigatoria);
        ?>
        <label class="form-label">Data de nascimento<?php echo $dataNascimentoObrigatoria ? ' *' : ''; ?></label>
        <input
            type="date"
            name="data_nascimento"
            class="form-control js-data-nascimento"
            max="<?php echo e($dataNascimentoMax); ?>"
            value="<?php echo e($funcionarioForm['data_nascimento'] ?? ''); ?>"
            <?php echo $dataNascimentoObrigatoria ? 'required' : ''; ?>>
        <small class="text-muted d-block mt-1">O funcionário tem de ter pelo menos 18 anos.</small>
        <div class="invalid-feedback">Indique uma data válida — mínimo 18 anos.</div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Data admissão</label>
        <input type="date" name="data_admissao" class="form-control" value="<?php echo e($funcionarioForm['data_admissao'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Data diuturnidade</label>
        <input type="date" name="data_diuturnidade" class="form-control" value="<?php echo e($funcionarioForm['data_diuturnidade'] ?? ''); ?>">
        <small class="text-muted">Para notificações de aniversário de serviço. Se vazio, usa a data de admissão.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Data cessação</label>
        <input type="date" name="data_cessacao" class="form-control" value="<?php echo e($funcionarioForm['data_cessacao'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Carga semanal (horas)</label>
        <input type="number" step="0.25" min="0.25" max="60" name="carga_horaria_semanal" class="form-control js-carga-semanal" list="cargaHorariaSugestoes<?php echo e($horarioFormUid); ?>" value="<?php echo e($funcionarioForm['carga_horaria_semanal'] ?? '40.00'); ?>">
        <datalist id="cargaHorariaSugestoes<?php echo e($horarioFormUid); ?>">
            <option value="35.00"></option>
            <option value="37.00"></option>
            <option value="37.50"></option>
            <option value="40.00"></option>
        </datalist>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tipo contrato</label>
        <input type="text" name="tipo_contrato" class="form-control" value="<?php echo e($funcionarioForm['tipo_contrato'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-control">
            <option value="ativo" <?php echo ($funcionarioForm['estado'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
            <option value="suspenso" <?php echo ($funcionarioForm['estado'] ?? '') === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
            <option value="inativo" <?php echo ($funcionarioForm['estado'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
            <option value="arquivado" <?php echo ($funcionarioForm['estado'] ?? '') === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Código de picagem</label>
        <input type="text" name="codigo_biometrico" class="form-control" value="<?php echo e($funcionarioForm['codigo_biometrico'] ?? ''); ?>" placeholder="Código no relógio biométrico">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">PIN ponto (alternativo)</label>
        <input type="text" name="pin_ponto" class="form-control" value="<?php echo e($funcionarioForm['pin_ponto'] ?? ''); ?>">
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control" rows="3"><?php echo e($funcionarioForm['observacoes'] ?? ''); ?></textarea>
    </div>
    <?php if (!empty($includeLegacyFields)): ?>
    <div class="col-md-6 mb-3">
        <label class="form-label">Serviço</label>
        <select name="servico" class="form-control js-servico-select">
            <option value="">Sem serviço</option>
            <?php
            $servicoAtual = (string) ($funcionarioForm['servico'] ?? '');
            $servicos = [
                'acao_direta' => 'Ação Direta',
                'servicos_gerais' => 'Serviços Gerais / Copa',
                'refeitorio' => 'Refeitório / Copa',
                'cozinha' => 'Cozinha',
                'lavandaria' => 'Lavandaria',
                'motorista' => 'Motorista',
                'tecnico_adm' => 'Serviços Técnicos e Administrativos',
            ];
            foreach ($servicos as $valor => $rotulo):
            ?>
                <option value="<?php echo e($valor); ?>" <?php echo $servicoAtual === $valor ? 'selected' : ''; ?>><?php echo e($rotulo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Horário</label>
        <?php $horarioAtual = trim((string) ($funcionarioForm['horario'] ?? '')); ?>
        <select class="form-control js-horario-select mb-2">
            <option value="">Selecione um horário</option>
            <option value="__custom__" <?php echo $horarioAtual !== '' ? '' : 'selected'; ?>>Personalizado (escrever)</option>
        </select>
        <input type="text" name="horario" class="form-control js-horario-input" value="<?php echo e($horarioAtual); ?>" placeholder="Ex.: Segunda a sexta 08:00-16:00">
        <small class="text-muted js-horario-hint d-block mt-1">Escolha a equipa ou o serviço para ver as opções de horário.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Morada</label>
        <input type="text" name="endereco" class="form-control" value="<?php echo e($funcionarioForm['endereco'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2"><?php echo e($funcionarioForm['notas'] ?? ''); ?></textarea>
    </div>
    <?php endif; ?>
</div>
