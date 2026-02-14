<?php
if (!defined('ABSPATH')) { exit; }

// includes/admin-menu.php
if ( ! defined( 'ABSPATH' ) ) exit;

function kami_form_admin_menu() {
    add_menu_page(
        '用紙フォーム',
        '用紙フォーム',
        'edit_posts',
        'kami-forms',
        'kami_form_admin_templates_page',
        'dashicons-media-spreadsheet',
        58
    );

    add_submenu_page(
        'kami-forms',
        'テンプレート',
        'テンプレート',
        'edit_posts',
        'kami-forms',
        'kami_form_admin_templates_page'
    );

    add_submenu_page(
        'kami-forms',
        '項目設定',
        '項目設定',
        'edit_posts',
        'kami-form-fields',
        'kami_form_admin_fields_page'
    );

    add_submenu_page(
        'kami-forms',
        '入力データ一覧',
        '入力データ一覧',
        'edit_posts',
        'kami-records',
        'kami_form_admin_records_page'
    );

    add_submenu_page(
        'kami-forms',
        'マスター',
        'マスター',
        'edit_posts',
        'kami-form-masters',
        'kami_form_admin_masters_page'
    );
}
add_action('admin_menu', 'kami_form_admin_menu');

function kami_form_admin_assets($hook) {
    // Only load on our pages
    if (strpos($hook, 'kami-forms') === false && strpos($hook, 'kami-form') === false) return;

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-resizable');

    wp_enqueue_script(
        'kami-form-admin',
        KAMI_FORM_URL . 'assets/admin.js',
        array('jquery'),
        KAMI_FORM_VERSION,
        true
    );

    wp_enqueue_script(
        'kami-form-designer',
        KAMI_FORM_URL . 'assets/admin-designer.js',
        array('jquery','jquery-ui-draggable','jquery-ui-resizable'),
        KAMI_FORM_VERSION,
        true
    );

    wp_enqueue_style(
        'kami-form-admin',
        KAMI_FORM_URL . 'assets/admin.css',
        array(),
        KAMI_FORM_VERSION
    );
}
add_action('admin_enqueue_scripts', 'kami_form_admin_assets');
