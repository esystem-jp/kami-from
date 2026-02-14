<?php
if (!defined('ABSPATH')) exit;

/**
 * 入力データ一覧（管理画面）
 * - 絞り込み：テンプレート / 日付範囲のみ（作業者・ステータスは削除）
 * - マスター項目は「コード」「名称」を別列で表示
 * - 保存値（JSON / id|code / id）から master_values を参照して表示
 */

if (!function_exists('paper_form_master_decode')) {
    function paper_form_master_decode($raw) {
        $raw = (string)$raw;
        $idv = 0; $code = '';
        if ($raw === '') return [0,''];
        $tmp = json_decode($raw, true);
        if (is_array($tmp) && isset($tmp['id'])) {
            $idv = (int)$tmp['id'];
            $code = (string)($tmp['code'] ?? '');
            return [$idv,$code];
        }
        if (strpos($raw, '|') !== false) {
            $parts = explode('|', $raw, 3);
            $idv = (int)($parts[0] ?? 0);
            $code = (string)($parts[1] ?? '');
            return [$idv,$code];
        }
        if (ctype_digit($raw)) {
            return [(int)$raw,''];
        }
        return [0,''];
    }
}

if (!function_exists('paper_form_admin_records_page')) {
function paper_form_admin_records_page() {
    if (!current_user_can('read')) {
        wp_die('このページにアクセスする権限がありません。');
    }

    global $wpdb;
    $p = $wpdb->prefix;

    $template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
    $date_from   = isset($_GET['date_from']) ? sanitize_text_field((string)$_GET['date_from']) : '';
    $date_to     = isset($_GET['date_to']) ? sanitize_text_field((string)$_GET['date_to']) : '';
    $paged       = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
    $per_page    = 50;
    $offset      = ($paged - 1) * $per_page;

    // テンプレート一覧
    $templates = $wpdb->get_results("SELECT id, name FROM {$p}paper_templates ORDER BY id DESC", ARRAY_A);

    // 対象テンプレートの項目一覧
    if ($template_id > 0) {
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT id, template_id, label, type, master_id, sort_order
             FROM {$p}paper_fields
             WHERE template_id=%d AND is_active=1 AND type<>'label'
             ORDER BY sort_order ASC, id ASC", $template_id
        ), ARRAY_A);
    } else {
        // template_id=0 の場合、全テンプレの項目をまとめて表示（テンプレ外の列は空欄）
        $fields = $wpdb->get_results(
            "SELECT id, template_id, label, type, master_id, sort_order
             FROM {$p}paper_fields
             WHERE is_active=1 AND type<>'label'
             ORDER BY template_id DESC, sort_order ASC, id ASC", ARRAY_A
        );
    }

    // where 条件
    $where_sql = " WHERE 1=1 ";
$params = [];
    $params = [];

    if ($template_id > 0) {
        $where_sql .= "r.template_id=%d";
        $params[] = $template_id;
    }
    if ($date_from !== '') {
        $where_sql .= "DATE(r.created_at) >= %s";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where_sql .= "DATE(r.created_at) <= %s";
        $params[] = $date_to;
    }
    // 件数
    $sql_count = "SELECT COUNT(*) FROM {$p}paper_records r {$where_sql}";
$total = (int)($params ? $wpdb->get_var($wpdb->prepare($sql_count, $params)) : $wpdb->get_var($sql_count));

    // レコード一覧
    $sql_list = "SELECT r.id, r.template_id, r.record_no, r.user_id, r.created_at
                 FROM {$p}paper_records r
                 {$where_sql}
                 ORDER BY r.id DESC
                 LIMIT %d OFFSET %d";
    $list_params = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($sql_list, $list_params), ARRAY_A);

    // 値（縦持ち）をまとめて取得
    $values_map = [];
    $record_ids = [];
    foreach ($rows as $r) $record_ids[] = (int)$r['id'];
    if (!empty($record_ids)) {
        $in = implode(',', array_fill(0, count($record_ids), '%d'));
        $vals = $wpdb->get_results($wpdb->prepare(
            "SELECT record_id, field_id, value_long FROM {$p}paper_record_values WHERE record_id IN ($in)",
            $record_ids
        ), ARRAY_A);
        foreach ($vals as $v) {
            $rid = (int)$v['record_id'];
            $fid = (int)$v['field_id'];
            if (!isset($values_map[$rid])) $values_map[$rid] = [];
            $values_map[$rid][$fid] = $v['value_long'];
        }
    }

    // マスター値マップ（テンプレートが参照する master_id の全値を取得）
    $master_value_map = [];
    $master_ids = [];
    foreach ($fields as $f) {
        $mid = (int)($f['master_id'] ?? 0);
        if ($mid > 0) $master_ids[$mid] = true;
    }
    if (!empty($master_ids)) {
        $mids = array_keys($master_ids);
        $inm = implode(',', array_fill(0, count($mids), '%d'));
        $all = $wpdb->get_results($wpdb->prepare(
            "SELECT id, master_id, value_code, value_name FROM {$p}paper_master_values WHERE master_id IN ($inm)",
            $mids
        ), ARRAY_A);
        foreach ($all as $mv) {
            $master_value_map[(int)$mv['id']] = [
                'code' => (string)($mv['value_code'] ?? ''),
                'name' => (string)($mv['value_name'] ?? ''),
            ];
        }
    }

    // 動的列数（マスターは2列）
    $field_col_count = 0;
    foreach ($fields as $f) {
        $is_master = ((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0);
        $field_col_count += $is_master ? 2 : 1;
    }

    // CSVエクスポートURL
    
// CSVエクスポートURL（admin-post.php 経由）
$export_url = wp_nonce_url(
    admin_url('admin-post.php?action=paper_form_export_csv'
        . '&template_id=' . (int)$template_id
        . '&date_from=' . urlencode((string)$date_from)
        . '&date_to=' . urlencode((string)$date_to)
    ),
    'paper_form_export_csv'
);


    echo '<div class="wrap"><h1>入力データ一覧</h1>';

    // フィルタ
    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="paper-records" />';

    echo '<label style="margin-right:12px;">テンプレート ';
    echo '<select name="template_id">';
    echo '<option value="0"' . selected($template_id, 0, false) . '>すべて</option>';
    foreach ($templates as $t) {
        echo '<option value="'.(int)$t['id'].'"'. selected($template_id, (int)$t['id'], false) .'>'.esc_html($t['name']).'</option>';
    }
    echo '</select></label>';

    echo '<label style="margin-right:12px;">日付From <input type="date" name="date_from" value="'.esc_attr($date_from).'" /></label>';
    echo '<label style="margin-right:12px;">日付To <input type="date" name="date_to" value="'.esc_attr($date_to).'" /></label>';

    echo '<button class="button button-primary">絞り込み</button> ';
    echo '<a class="button" href="'.esc_url($export_url).'">CSVダウンロード</a>';
    echo '</form>';

    // テーブル
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>テンプレ</th><th>番号</th><th>登録日時</th>';
    foreach ($fields as $f) {
        $is_master = ((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0);
        if ($is_master) {
            echo '<th>'.esc_html($f['label']).'（コード）</th>';
            echo '<th>'.esc_html($f['label']).'（名称）</th>';
        } else {
            echo '<th>'.esc_html($f['label']).'</th>';
        }
    }
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="'.(5 + $field_col_count).'">データがありません</td></tr>';
    } else {
        foreach ($rows as $r) {
            $rid = (int)$r['id'];
            echo '<tr>';
            echo '<td>'.(int)$r['id'].'</td>';
            echo '<td>'.(int)$r['template_id'].'</td>';
            echo '<td>'.(int)$r['record_no'].'</td>';
            echo '<td>'.esc_html($r['created_at']).'</td>';
            

            foreach ($fields as $f) {
                $fid = (int)$f['id'];
                $val = $values_map[$rid][$fid] ?? '';
                $raw = (string)$val;

                $is_master = ((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0);

                // template_id=0 で別テンプレ項目は空欄
                if ($template_id === 0 && (int)$f['template_id'] !== (int)$r['template_id']) {
                    echo $is_master ? '<td></td><td></td>' : '<td></td>';
                    continue;
                }

                if ($is_master) {
                    [$idv, $code] = paper_form_master_decode($raw);
                    $name = '';
                    if ($idv > 0 && isset($master_value_map[$idv])) {
                        $code = $code !== '' ? $code : (string)($master_value_map[$idv]['code'] ?? '');
                        $name = (string)($master_value_map[$idv]['name'] ?? '');
                    }
                    echo '<td>'.esc_html($code).'</td>';
                    echo '<td>'.esc_html($name).'</td>';
                } else {
                    echo '<td>'.esc_html($raw).'</td>';
                }
            }

            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    // ページネーション
    $total_pages = ($per_page > 0) ? (int)ceil($total / $per_page) : 1;
    if ($total_pages < 1) $total_pages = 1;

    if ($total_pages > 1) {
        $base_url = admin_url('admin.php?page=paper-records'
            . '&template_id='.(int)$template_id
            . '&date_from='.urlencode((string)$date_from)
            . '&date_to='.urlencode((string)$date_to)
        );
        echo '<div style="margin-top:12px;">';
        for ($i=1; $i<=$total_pages; $i++) {
            if ($i === $paged) {
                echo '<strong style="margin-right:6px;">'.$i.'</strong>';
            } else {
                echo '<a style="margin-right:6px;" href="'.esc_url($base_url.'&paged='.$i).'">'.$i.'</a>';
            }
        }
        echo '</div>';
    }

    echo '</div>';
}
}
