(function (window, $) {
    'use strict';

    function validarDataNascimento($form) {
        var $input = $form.find('.js-data-nascimento');
        if (!$input.length) {
            return true;
        }

        var valor = ($input.val() || '').trim();
        if (!valor) {
            return $input.prop('required') ? false : true;
        }

        var max = $input.attr('max');
        if (max && valor > max) {
            $input.addClass('is-invalid');
            alert('O funcionário tem de ter pelo menos 18 anos.');
            return false;
        }

        $input.removeClass('is-invalid');
        return true;
    }

    function initFuncionarioTelefoneForm($form) {
        if (!$form.length || $form.data('telefoneInit')) {
            return;
        }

        var $display = $form.find('.js-funcionario-telefone-display').first();
        var $hidden = $form.find('.js-funcionario-telefone-hidden').first();
        if (!$display.length) {
            return;
        }

        $form.data('telefoneInit', true);

        var iti = null;
        if (window.intlTelInput) {
            iti = window.intlTelInput($display[0], {
                initialCountry: 'pt',
                preferredCountries: ['pt', 'br', 'es', 'fr', 'uk'],
                separateDialCode: false,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
            });

            var existente = ($hidden.val() || $display.val() || '').trim();
            if (existente) {
                iti.setNumber(existente);
            }
        }

        $form.on('submit.funcionarioTelefone', function (event) {
            if (!validarDataNascimento($form)) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                return false;
            }

            if (!iti) {
                return true;
            }

            var valor = ($display.val() || '').trim();
            if (!valor) {
                $hidden.val('');
                $display.removeClass('is-invalid');
                return true;
            }

            if (!iti.isValidNumber()) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                $display.addClass('is-invalid');
                alert('Por favor insira um número de telefone válido.');
                return false;
            }

            $hidden.val(iti.getNumber());
            $display.removeClass('is-invalid');
            return true;
        });
    }

    window.initFuncionarioTelefoneForm = initFuncionarioTelefoneForm;

    $(function () {
        $('.js-funcionario-form').each(function () {
            initFuncionarioTelefoneForm($(this));
        });

        $(document).on('shown.bs.modal', '.modal', function () {
            $(this).find('.js-funcionario-form').each(function () {
                initFuncionarioTelefoneForm($(this));
            });
        });
    });
}(window, jQuery));
