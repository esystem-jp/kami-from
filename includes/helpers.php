<?php
if (!defined('ABSPATH')) { exit; }



function paper_form_allowed_admin_html() {
    return [
        'option' => ['value' => true, 'selected' => true],
        'optgroup' => ['label' => true],
        'select' => ['name' => true, 'id' => true, 'class' => true, 'style' => true],
        'input' => ['type' => true, 'name' => true, 'value' => true, 'checked' => true, 'id' => true, 'class' => true],
        'span' => ['class' => true],
    ];
}
