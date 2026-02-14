<?php
if (!defined('ABSPATH')) { exit; }

// includes/admin-menu.php
if ( ! defined( 'ABSPATH' ) ) exit;

function paper_form_admin_menu() {
    add_menu_page(
        '用紙フォーム',
        '用紙フォーム',
        'edit_posts',
        'paper-forms',
        'paper_form_admin_templates_page',
        'dashicons-media-spreadsheet',
        58
    );

    add_submenu_page(
        'paper-forms',
        'テンプレート',
        'テンプレート',
        'edit_posts',
        'paper-forms',
        'paper_form_admin_templates_page'
    );

    add_submenu_page(
        'paper-forms',
        '項目設定',
        '項目設定',
        'edit_posts',
        'paper-form-fields',
        'paper_form_admin_fields_page'
    );

    add_submenu_page(
        'paper-forms',
        '入力データ一覧',
        '入力データ一覧',
        'edit_posts',
        'paper-records',
        'paper_form_admin_records_page'
    );

    add_submenu_page(
        'paper-forms',
        'マスター',
        'マスター',
        'edit_posts',
        'paper-form-masters',
        'paper_form_admin_masters_page'
    );
}
add_action('admin_menu', 'paper_form_admin_menu');

function paper_form_admin_assets($hook) {
    // Only load on our pages
    if (strpos($hook, 'paper-forms') === false && strpos($hook, 'paper-form') === false) return;

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-resizable');

    wp_enqueue_script(
        'paper-form-admin',
        PAPER_FORM_URL . 'assets/admin.js',
        array('jquery'),
        PAPER_FORM_VERSION,
        true
    );

    wp_enqueue_script(
        'paper-form-designer',
        PAPER_FORM_URL . 'assets/admin-designer.js',
        array('jquery','jquery-ui-draggable','jquery-ui-resizable'),
        PAPER_FORM_VERSION,
        true
    );

    wp_enqueue_style(
        'paper-form-admin',
        PAPER_FORM_URL . 'assets/admin.css',
        array(),
        PAPER_FORM_VERSION
    );
}
add_action('admin_enqueue_scripts', 'paper_form_admin_assets');
