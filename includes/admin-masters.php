<?php
if (!defined('ABSPATH')) { exit; }

// includes/admin-masters.php
if ( ! defined( 'ABSPATH' ) ) exit;

function paper_form_admin_masters_page() {
    if ( ! current_user_can('edit_posts') ) {
        wp_die('権限がありません');
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $view = sanitize_text_field($_GET['view'] ?? 'list'); // list|values
    $master_id = (int)($_GET['master_id'] ?? 0);

    // --- Actions: delete master/value ---
    if (($view === 'list') && (($_GET['action'] ?? '') === 'delete_master')) {
        $did = (int)($_GET['id'] ?? 0);
        if ($did > 0 && check_admin_referer('pf_delete_master_'.$did)) {
            $wpdb->delete("{$p}paper_master_values", ['master_id'=>$did], ['%d']);
            $wpdb->delete("{$p}paper_masters", ['id'=>$did], ['%d']);
            echo '<div class="notice notice-success"><p>マスターを削除しました。</p></div>';
        }
    }
    if (($view === 'values') && (($_GET['action'] ?? '') === 'delete_value')) {
        $vid = (int)($_GET['id'] ?? 0);
        $mid = (int)($_GET['master_id'] ?? 0);
        if ($vid > 0 && $mid > 0 && check_admin_referer('pf_delete_value_'.$mid.'_'.$vid)) {
            $wpdb->delete("{$p}paper_master_values", ['id'=>$vid, 'master_id'=>$mid], ['%d','%d']);
            echo '<div class="notice notice-success"><p>値を削除しました。</p></div>';
        }
        $master_id = $mid;
    }

    // --- Save master (definition) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['pf_action'] ?? '') === 'save_master')) {
        check_admin_referer('pf_save_master');
        $id = (int)($_POST['id'] ?? 0);
        $master_key  = sanitize_text_field($_POST['master_key'] ?? '');
        $master_name = sanitize_text_field($_POST['master_name'] ?? '');
        $is_active = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;

        if ($master_key === '' || $master_name === '') {
            echo '<div class="notice notice-error"><p>master_key と master_name は必須です。</p></div>';
        } else {
            if ($id > 0) {
                $wpdb->update("{$p}paper_masters", [
                    'master_key' => $master_key,
                    'master_name' => $master_name,
                    'is_active' => $is_active,
                ], ['id'=>$id], ['%s','%s','%d'], ['%d']);
                echo '<div class="notice notice-success"><p>マスターを更新しました。</p></div>';
            } else {
                $wpdb->insert("{$p}paper_masters", [
                    'master_key' => $master_key,
                    'master_name' => $master_name,
                    'is_active' => $is_active,
                ], ['%s','%s','%d']);
                echo '<div class="notice notice-success"><p>マスターを追加しました。</p></div>';
            }
        }
    }

    // --- Save master value ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['pf_action'] ?? '') === 'save_value')) {
        check_admin_referer('pf_save_value');
        $master_id = (int)($_POST['master_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        $value_code = sanitize_text_field($_POST['value_code'] ?? '');
        $value_name = sanitize_text_field($_POST['value_name'] ?? '');
        $is_active = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;

        if ($master_id <= 0) {
            echo '<div class="notice notice-error"><p>master_id が不正です。</p></div>';
        } elseif ($value_code === '' || $value_name === '') {
            echo '<div class="notice notice-error"><p>コードと名称は必須です。</p></div>';
        } else {
            if ($id > 0) {
                $wpdb->update("{$p}paper_master_values", [
                    'value_code' => $value_code,
                    'value_name' => $value_name,
                    'is_active' => $is_active,
                ], ['id'=>$id, 'master_id'=>$master_id], ['%s','%s','%d'], ['%d','%d']);
                echo '<div class="notice notice-success"><p>値を更新しました。</p></div>';
            } else {
                $wpdb->insert("{$p}paper_master_values", [
                    'master_id' => $master_id,
                    'value_code' => $value_code,
                    'value_name' => $value_name,
                    'is_active' => $is_active,
                ], ['%d','%s','%s','%d']);
                echo '<div class="notice notice-success"><p>値を追加しました。</p></div>';
            }
        }
        $view = 'values';
    }

    echo '<div class="wrap"><h1>マスター管理</h1>';

    // --- Values view ---
    if ($view === 'values') {
        if ($master_id <= 0) {
            wp_die('master_id が不正です');
        }

        $master = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_masters WHERE id=%d", $master_id), ARRAY_A);
        if (!$master) wp_die('マスターが見つかりません');

        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=paper-form-masters')).'">← マスター一覧へ</a></p>';
        echo '<h2>値一覧：'.esc_html($master['master_name']).'（'.esc_html($master['master_key']).'）</h2>';

        $edit_value_id = (int)($_GET['edit_value_id'] ?? 0);
        $edit_value = null;
        if ($edit_value_id > 0) {
            $edit_value = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_master_values WHERE id=%d AND master_id=%d", $edit_value_id, $master_id), ARRAY_A);
        }

        // Add/Edit form
        echo '<h3>'.($edit_value ? '値を編集' : '値を追加').'</h3>';
        echo '<form method="post">';
        wp_nonce_field('pf_save_value');
        echo '<input type="hidden" name="pf_action" value="save_value">';
        echo '<input type="hidden" name="master_id" value="'.esc_attr($master_id).'">';
        echo '<input type="hidden" name="id" value="'.esc_attr($edit_value['id'] ?? 0).'">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>コード</th><td><input type="text" name="value_code" value="'.esc_attr($edit_value['value_code'] ?? '').'" required></td></tr>';
        echo '<tr><th>名称</th><td><input type="text" name="value_name" value="'.esc_attr($edit_value['value_name'] ?? '').'" required></td></tr>';
        $checked = (!isset($edit_value['is_active']) || (int)$edit_value['is_active'] === 1) ? 'checked' : '';
        echo '<tr><th>有効</th><td><label><input type="checkbox" name="is_active" value="1" '.$checked.'> 有効</label></td></tr>';
        echo '</tbody></table>';
        submit_button($edit_value ? '保存' : '追加');
        echo '</form>';

        $values = $wpdb->get_results($wpdb->prepare(
            "SELECT id, value_code, value_name, is_active FROM {$p}paper_master_values WHERE master_id=%d ORDER BY value_code ASC, id ASC",
            $master_id
        ), ARRAY_A);

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>コード</th><th>名称</th><th>有効</th><th>操作</th></tr></thead><tbody>';
        if (empty($values)) {
            echo '<tr><td colspan="5">データがありません</td></tr>';
        } else {
            foreach ($values as $v) {
                $del_url = wp_nonce_url(
                    admin_url('admin.php?page=paper-form-masters&view=values&master_id='.$master_id.'&action=delete_value&id='.$v['id']),
                    'pf_delete_value_'.$master_id.'_'.$v['id']
                );
                $edit_url = admin_url('admin.php?page=paper-form-masters&view=values&master_id='.$master_id.'&edit_value_id='.$v['id']);
                echo '<tr>';
                echo '<td>'.esc_html($v['id']).'</td>';
                echo '<td>'.esc_html($v['value_code']).'</td>';
                echo '<td>'.esc_html($v['value_name']).'</td>';
                echo '<td>'.esc_html((int)$v['is_active']).'</td>';
                echo '<td>';
                echo '<a class="button" href="'.esc_url($edit_url).'">Edit</a> ';
                echo '<a class="button button-link-delete" onclick="return confirm(\'Delete?\')" href="'.esc_url($del_url).'">Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        return;
    }

    // --- Master list view ---
    $masters = $wpdb->get_results("SELECT id, master_key, master_name, is_active FROM {$p}paper_masters ORDER BY id DESC", ARRAY_A);
    $edit_master_id = (int)($_GET['edit_master_id'] ?? 0);
    $edit_master = null;
    if ($edit_master_id > 0) {
        $edit_master = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_masters WHERE id=%d", $edit_master_id), ARRAY_A);
    }

    echo '<h2>マスター一覧</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>キー</th><th>名称</th><th>有効</th><th>操作</th></tr></thead><tbody>';
    if (empty($masters)) {
        echo '<tr><td colspan="5">データがありません</td></tr>';
    } else {
        foreach ($masters as $m) {
            $values_url = admin_url('admin.php?page=paper-form-masters&view=values&master_id='.$m['id']);
            $edit_url   = admin_url('admin.php?page=paper-form-masters&edit_master_id='.$m['id']);
            $del_url    = wp_nonce_url(
                admin_url('admin.php?page=paper-form-masters&action=delete_master&id='.$m['id']),
                'pf_delete_master_'.$m['id']
            );
            echo '<tr>';
            echo '<td>'.esc_html($m['id']).'</td>';
            echo '<td>'.esc_html($m['master_key']).'</td>';
            echo '<td>'.esc_html($m['master_name']).'</td>';
            echo '<td>'.esc_html((int)$m['is_active']).'</td>';
            echo '<td>';
            echo '<a class="button" href="'.esc_url($values_url).'">値を編集</a> ';
            echo '<a class="button" href="'.esc_url($edit_url).'">Edit</a> ';
            echo '<a class="button button-link-delete" onclick="return confirm(\'Delete?\')" href="'.esc_url($del_url).'">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    echo '<h2>'.($edit_master ? 'マスターを編集' : 'マスターを追加').'</h2>';
    echo '<form method="post">';
    wp_nonce_field('pf_save_master');
    echo '<input type="hidden" name="pf_action" value="save_master">';
    echo '<input type="hidden" name="id" value="'.esc_attr($edit_master['id'] ?? 0).'">';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>master_key</th><td><input type="text" name="master_key" value="'.esc_attr($edit_master['master_key'] ?? '').'" required></td></tr>';
    echo '<tr><th>master_name</th><td><input type="text" name="master_name" value="'.esc_attr($edit_master['master_name'] ?? '').'" required></td></tr>';
    $checked = (!isset($edit_master['is_active']) || (int)$edit_master['is_active'] === 1) ? 'checked' : '';
    echo '<tr><th>有効</th><td><label><input type="checkbox" name="is_active" value="1" '.$checked.'> 有効</label></td></tr>';
    echo '</tbody></table>';
    submit_button($edit_master ? '保存' : '追加');
    echo '</form>';

    echo '</div>';
}