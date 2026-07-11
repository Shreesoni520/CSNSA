(function (window, $) {
    'use strict';

    var presets = window.CSNSA_HORARIO_PRESETS || { equipas: {}, servicos: {} };

    function formatCarga(value) {
        var n = parseFloat(value);
        if (Number.isNaN(n)) {
            return '';
        }
        return n.toFixed(2);
    }

    function findPreset(codigo) {
        if (!codigo) {
            return null;
        }
        return presets.equipas[codigo] || presets.equipas[codigo.toUpperCase()] || null;
    }

    function resolveCodigoFromForm($form) {
        var $equipa = $form.find('.js-equipa-select');
        if ($equipa.length) {
            var codigo = ($equipa.find('option:selected').data('codigo') || '').toString().trim();
            if (codigo) {
                return codigo.toUpperCase();
            }
        }

        var servico = ($form.find('.js-servico-select').val() || '').toString();
        if (servico && presets.servicos[servico]) {
            return presets.servicos[servico];
        }

        return '';
    }

    function rebuildHorarioOptions($form, codigo, selectedValue) {
        var $select = $form.find('.js-horario-select');
        var $input = $form.find('.js-horario-input');
        var $hint = $form.find('.js-horario-hint');
        var preset = findPreset(codigo);
        var current = selectedValue != null ? selectedValue : ($input.val() || '').trim();

        $select.empty();
        $select.append($('<option>', { value: '', text: 'Selecione um horário' }));

        var matched = false;
        if (preset && preset.horarios) {
            preset.horarios.forEach(function (item) {
                var isSelected = current !== '' && current === item.value;
                if (isSelected) {
                    matched = true;
                }
                $select.append($('<option>', {
                    value: item.value,
                    text: item.label,
                    selected: isSelected
                }));
            });

            if ($hint.length) {
                $hint.text('Opções para a equipa/serviço selecionado. Pode alterar a carga semanal se necessário.');
            }
        } else if ($hint.length) {
            $hint.text('Escolha a equipa ou o serviço para ver as opções de horário.');
        }

        var useCustom = current !== '' && !matched;
        $select.append($('<option>', {
            value: '__custom__',
            text: 'Personalizado (escrever)',
            selected: useCustom || current === ''
        }));

        if (!useCustom && matched) {
            $input.val(current);
            $input.addClass('d-none');
        } else if (!useCustom && preset && preset.horarios && preset.horarios.length === 1 && current === '') {
            $select.val(preset.horarios[0].value);
            $input.val(preset.horarios[0].value);
            $input.addClass('d-none');
        } else {
            $input.removeClass('d-none');
        }
    }

    function applyPresetToForm($form, codigo, keepHorario) {
        var preset = findPreset(codigo);
        if (!preset) {
            rebuildHorarioOptions($form, codigo, keepHorario ? $form.find('.js-horario-input').val() : '');
            return;
        }

        if (preset.carga_semanal) {
            $form.find('.js-carga-semanal').val(formatCarga(preset.carga_semanal));
        }

        rebuildHorarioOptions($form, codigo, keepHorario ? $form.find('.js-horario-input').val() : '');
    }

    function initFuncionarioHorarioForm($form) {
        if (!$form.length || $form.data('horarioInit')) {
            return;
        }
        $form.data('horarioInit', true);

        var codigo = resolveCodigoFromForm($form);
        applyPresetToForm($form, codigo, true);

        $form.on('change', '.js-equipa-select', function () {
            applyPresetToForm($form, resolveCodigoFromForm($form), false);
        });

        $form.on('change', '.js-servico-select', function () {
            if (!$form.find('.js-equipa-select').val()) {
                applyPresetToForm($form, resolveCodigoFromForm($form), false);
            }
        });

        $form.on('change', '.js-horario-select', function () {
            var value = ($(this).val() || '').toString();
            var $input = $form.find('.js-horario-input');
            if (value === '__custom__') {
                $input.removeClass('d-none').focus();
                return;
            }
            if (value === '') {
                $input.removeClass('d-none');
                return;
            }
            $input.val(value).addClass('d-none');
        });
    }

    window.initFuncionarioHorarioForm = initFuncionarioHorarioForm;

    $(function () {
        $('.js-funcionario-form').each(function () {
            initFuncionarioHorarioForm($(this));
        });

        $(document).on('shown.bs.modal', '.modal', function () {
            $(this).find('.js-funcionario-form').each(function () {
                initFuncionarioHorarioForm($(this));
            });
        });
    });
}(window, jQuery));
