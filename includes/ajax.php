<?php
if (!defined('ABSPATH')) { exit; }

// includes/ajax.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_pf_save_positions', 'kami_form_ajax_save_positions');

function kami_form_ajax_save_positions() {
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message'=>'forbidden'], 403);
    }

    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce($nonce, 'pf_save_positions_'.$template_id) ) {
        wp_send_json_error(['message'=>'bad_nonce'], 400);
    }

    $items = $_POST['items'] ?? [];
    if ( ! is_array($items) ) $items = [];

    global $wpdb; $p = $wpdb->prefix;

    foreach ($items as $it) {
        $fid = isset($it['id']) ? (int)$it['id'] : 0;
        if ($fid <= 0) continue;

        $x = isset($it['x_pct']) ? (float)$it['x_pct'] : 0;
        $y = isset($it['y_pct']) ? (float)$it['y_pct'] : 0;
        $w = isset($it['w_pct']) ? (float)$it['w_pct'] : 10;
        $h = isset($it['h_pct']) ? (float)$it['h_pct'] : 3;

        // clamp to sensible range
        $x = max(0, min(100, $x));
        $y = max(0, min(100, $y));
        $w = max(0.1, min(100, $w));
        $h = max(0.1, min(100, $h));

        $wpdb->update(
            "{$p}kami_fields",
            ['x_pct'=>$x,'y_pct'=>$y,'w_pct'=>$w,'h_pct'=>$h],
            ['id'=>$fid,'template_id'=>$template_id],
            ['%f','%f','%f','%f'],
            ['%d','%d']
        );
    }

    wp_send_json_success(['message'=>'saved']);
}
