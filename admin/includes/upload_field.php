<?php

/**
 * Render a styled file upload control.
 *
 * Options:
 * - id, name, accept, required
 * - variant: document | import | avatar | inline | default
 * - label, button_text, hint, filename_empty, zone_title
 * - input_class, wrap_class
 */
function upload_field(array $options): void
{
    $id = $options['id'] ?? ('upload_' . preg_replace('/[^a-z0-9_]/', '', uniqid('up', true)));
    $name = $options['name'] ?? 'ficheiro';
    $accept = $options['accept'] ?? '';
    $required = !empty($options['required']);
    $variant = $options['variant'] ?? 'inline';
    $label = $options['label'] ?? '';
    $buttonText = $options['button_text'] ?? 'Escolher ficheiro';
    $hint = $options['hint'] ?? '';
    $emptyLabel = $options['filename_empty'] ?? 'Nenhum ficheiro selecionado';
    $zoneTitle = $options['zone_title'] ?? 'Arraste o ficheiro ou clique para procurar';
    $wrapClass = trim('csnsa-upload csnsa-upload--' . preg_replace('/[^a-z]/', '', $variant) . ' ' . ($options['wrap_class'] ?? ''));
    $inputClass = trim('csnsa-upload__input ' . ($options['input_class'] ?? ''));

    $dataAttrs = ' data-empty-label="' . e($emptyLabel) . '"';
    if ($variant === 'document' || $variant === 'import') {
        $dataAttrs .= ' data-zone-title="' . e($zoneTitle) . '"';
    }

    if ($label !== '') {
        echo '<label class="form-label" for="' . e($id) . '">' . e($label) . '</label>';
    }

    echo '<div class="' . e($wrapClass) . '"' . $dataAttrs . '>';

    $requiredAttr = $required ? ' required' : '';
    $acceptAttr = $accept !== '' ? ' accept="' . e($accept) . '"' : '';

    echo '<input type="file" id="' . e($id) . '" name="' . e($name) . '" class="' . e(trim($inputClass)) . '"' . $acceptAttr . $requiredAttr . '>';

    if ($variant === 'avatar') {
        echo '<label for="' . e($id) . '" class="csnsa-upload__btn csnsa-upload__btn--pill">';
        echo '<span class="csnsa-upload__icon"><i class="fe fe-camera"></i></span>';
        echo '<span class="csnsa-upload__btn-text">' . e($buttonText) . '</span>';
        echo '</label>';
        echo '<div class="csnsa-upload__meta">';
        echo '<span class="csnsa-upload__filename">' . e($emptyLabel) . '</span>';
        if ($hint !== '') {
            echo '<span class="csnsa-upload__hint">' . e($hint) . '</span>';
        }
        echo '</div>';
    } elseif ($variant === 'document' || $variant === 'import') {
        $icon = $variant === 'import' ? 'fe-upload-cloud' : 'fe-paperclip';
        echo '<label for="' . e($id) . '" class="csnsa-upload__zone mb-0">';
        echo '<span class="csnsa-upload__zone-icon"><i class="fe ' . e($icon) . '"></i></span>';
        echo '<p class="csnsa-upload__zone-title">' . e($zoneTitle) . '</p>';
        echo '<p class="csnsa-upload__zone-text">' . e($buttonText) . '</p>';
        echo '</label>';
        echo '<div class="csnsa-upload__meta">';
        echo '<span class="csnsa-upload__filename">' . e($emptyLabel) . '</span>';
        if ($hint !== '') {
            echo '<span class="csnsa-upload__hint">' . e($hint) . '</span>';
        }
        echo '</div>';
    } else {
        echo '<div class="csnsa-upload__inline-row">';
        echo '<label for="' . e($id) . '" class="csnsa-upload__btn">';
        echo '<span class="csnsa-upload__icon"><i class="fe fe-upload"></i></span>';
        echo '<span class="csnsa-upload__btn-text">' . e($buttonText) . '</span>';
        echo '</label>';
        echo '<span class="csnsa-upload__filename csnsa-upload__filename--inline">' . e($emptyLabel) . '</span>';
        echo '</div>';
        if ($hint !== '') {
            echo '<span class="csnsa-upload__hint csnsa-upload__hint--inline">' . e($hint) . '</span>';
        }
    }

    echo '</div>';
}
