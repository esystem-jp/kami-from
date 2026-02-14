<?php
/**
 * Plugin Name: Paper Form
 * Description: Paper form input plugin.
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: paper-form
 */
/*
Plugin Name: Paper Form System
Description: 紙の用紙画像を背景にした業務用入力フォーム（ログイン不要の入力画面 + 管理画面でテンプレ/マスター登録）
Version: 1.1.0
Author: eSystem
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Guard: prevent accidental output breaking activation/redirects
if (!function_exists('pf_ob_start_guard')) {
    function pf_ob_start_guard() {
        static $started = false;
        if (!$started) {
            $started = true;
            if (!ob_get_level()) { @ob_start(); }
        }
    }
}
add_action('plugins_loaded', 'pf_ob_start_guard', 0);


if (!defined('PAPER_FORM_VERSION')) { define('PAPER_FORM_VERSION', '1.1.0'); }
define('PAPER_FORM_DIR', plugin_dir_path(__FILE__));
define('PAPER_FORM_URL', plugin_dir_url(__FILE__));

require_once PAPER_FORM_DIR . 'includes/install.php';
require_once PAPER_FORM_DIR . 'includes/save-record.php';
require_once PAPER_FORM_DIR . 'includes/shortcode-form.php';
if (is_admin()) require_once PAPER_FORM_DIR . 'includes/admin-menu.php';
if (is_admin()) require_once PAPER_FORM_DIR . 'includes/admin-templates.php';
if (is_admin()) {
    // In case of accidental BOM/whitespace in file on some FTP clients, swallow any output.
    ob_start();
    require_once PAPER_FORM_DIR . 'includes/admin-masters.php';
    ob_end_clean();
}

require_once PAPER_FORM_DIR . 'includes/ajax.php';
if (is_admin()) require_once PAPER_FORM_DIR . 'includes/admin-records.php';
if (is_admin()) require_once PAPER_FORM_DIR . 'includes/export-csv.php';

register_activation_hook( __FILE__, 'paper_form_install_tables' );




// 左メニューから「項目設定」を見えなくする（ページ自体は利用可）
add_action('admin_head', function(){
    echo '<style>#toplevel_page_paper-forms .wp-submenu a[href="admin.php?page=paper-form-fields"]{display:none !important;}</style>';
});
