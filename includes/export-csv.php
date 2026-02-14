<?php
if (!defined('ABSPATH')) { exit; }

function paper_form_csv_escape($v) {
    $v = (string) $v;
    $v = str_replace("\r", "", $v);
    $v = str_replace("\n", "\n", $v);
    if (preg_match('/[",\n]/', $v)) {
        $v = '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

// includes/export-csv.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_post_paper_form_export_csv', 'paper_form_export_csv');

/**
 * master_select の保存値（JSON / "id|code" / id）を「code name」に変換
 */
if (!function_exists('paper_form_master_display')) {
function paper_form_master_display($raw, $master_id) {
    global $wpdb; $p = $wpdb->prefix;
    $raw = is_array($raw) ? '' : (string)$raw;
    $id = 0; $code = '';
    if ($raw === '') return '';
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $id = (int)($j['id'] ?? 0);
        $code = (string)($j['code'] ?? '');
    } elseif (strpos($raw, '|') !== false) {
        $parts = explode('|', $raw, 3);
        $id = (int)($parts[0] ?? 0);
        $code = (string)($parts[1] ?? '');
    } elseif (ctype_digit($raw)) {
        $id = (int)$raw;
    }
    if ($id <= 0) return $raw;
    static $cache = [];
    $ck = $master_id.':'.$id;
    if (!array_key_exists($ck, $cache)) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT value_code, value_name FROM {$p}paper_master_values WHERE id=%d AND master_id=%d", $id, (int)$master_id), ARRAY_A);
        $cache[$ck] = $row ?: null;
    }
    $row = $cache[$ck];
    if (!$row) return $raw;
    $c = (string)($row['value_code'] ?? $code);
    $n = (string)($row['value_name'] ?? '');
    $disp = trim($c.' '.$n);
    return ($disp !== '') ? $disp : $raw;
}
}

function paper_form_export_csv() {
    if ( ! current_user_can('edit_posts') ) {
        wp_die('権限がありません');
    }
    check_admin_referer('paper_form_export_csv');

    global $wpdb; $p = $wpdb->prefix;

    $template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $user_id   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    $where = " WHERE 1=1 ";
    $params = [];
    if ($template_id > 0) { $where .= " AND r.template_id=%d "; $params[] = $template_id; }
    if ($user_id > 0) { $where .= " AND r.user_id=%d "; $params[] = $user_id; }
    if ($date_from !== '') { $where .= " AND r.created_at >= %s "; $params[] = $date_from . " 00:00:00"; }
    if ($date_to !== '') { $where .= " AND r.created_at <= %s "; $params[] = $date_to . " 23:59:59"; }

    // fields
    $field_where = "";
    $field_params = [];
    if ($template_id > 0) {
        $field_where = " WHERE template_id=%d AND is_active=1 ";
        $field_params[] = $template_id;
    } else {
        $field_where = " WHERE is_active=1 ";
    }
    $sqlf = "SELECT id, template_id, label, type, master_id FROM {$p}paper_fields $field_where ORDER BY template_id ASC, sort_order ASC, id ASC";
    $fields = $wpdb->get_results(empty($field_params) ? $sqlf : $wpdb->prepare($sqlf, $field_params), ARRAY_A);

    // records
    $sql = "SELECT r.id, r.template_id, r.record_no, r.user_id, r.status, r.created_at, t.name AS template_name, u.user_name
            FROM {$p}paper_records r
            LEFT JOIN {$p}paper_templates t ON t.id=r.template_id
            LEFT JOIN {$p}paper_users u ON u.id=r.user_id
            $where
            ORDER BY r.id DESC";
    $rows = $wpdb->get_results(empty($params) ? $sql : $wpdb->prepare($sql, $params), ARRAY_A);
// values
    $record_ids = array_map(fn($r)=>(int)$r['id'], $rows);
    $values_map = [];
    if (!empty($record_ids)) {
        $in = implode(',', array_fill(0, count($record_ids), '%d'));
        $sqlv = "SELECT record_id, field_id, value_long FROM {$p}paper_record_values WHERE record_id IN ($in)";
        $vals = $wpdb->get_results($wpdb->prepare($sqlv, $record_ids), ARRAY_A);
        foreach ($vals as $v) {
            $rid = (int)$v['record_id'];
            $fid = (int)$v['field_id'];
            if (!isset($values_map[$rid])) $values_map[$rid] = [];
            $values_map[$rid][$fid] = (string)$v['value_long'];
        }
    }

// master_select 表示用：保存値(JSON)のid→(code,name)へ変換
$master_value_map = [];
$need_ids = [];
foreach ($fields as $f) {
    if ((($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0)) {
        foreach ($rows as $r) {
            $rid = (int)$r['id'];
            $fid = (int)$f['id'];
            $raw = $values_map[$rid][$fid] ?? '';
            if ($raw === '') continue;
            $tmp = json_decode((string)$raw, true);
            if (is_array($tmp) && isset($tmp['id'])) {
                $need_ids[(int)$tmp['id']] = true;
            } elseif (strpos((string)$raw,'|')!==false) { $parts=explode('|',(string)$raw,3); $need_ids[(int)($parts[0]??0)] = true; }
            elseif (ctype_digit((string)$raw)) { $need_ids[(int)$raw] = true; }
        }
    }
}
if (!empty($need_ids)) {
    $ids = array_keys($need_ids);
    $in = implode(',', array_fill(0, count($ids), '%d'));
    $sqlMv = "SELECT id, value_code, value_name FROM {$p}paper_master_values WHERE id IN ($in)";
    $mvs = $wpdb->get_results($wpdb->prepare($sqlMv, $ids), ARRAY_A);
    foreach ($mvs as $mv) {
        $master_value_map[(int)$mv['id']] = [
            'code'=>(string)($mv['value_code'] ?? ''),
            'name'=>(string)($mv['value_name'] ?? ''),
        ];
    }
}


    // master_item（品番）表示用：ID→名称のマッピング
    $item_map = [];
    $need_item_ids = [];
    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'master_item') {
            foreach ($rows as $r) {
                $rid = (int)$r['id'];
                $fid = (int)$f['id'];
                $v = $values_map[$rid][$fid] ?? '';
                if ($v !== '' && ctype_digit((string)$v)) $need_item_ids[(int)$v] = true;
            }
        }
    }
    if (!empty($need_item_ids)) {
        $ids = array_keys($need_item_ids);
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $sqlIt = "SELECT id, item_code, item_name FROM {$p}paper_items WHERE id IN ($in)";
        $its = $wpdb->get_results($wpdb->prepare($sqlIt, $ids), ARRAY_A);
        foreach ($its as $it) {
            $label = trim((string)($it['item_code'] ?? '') . ' ' . (string)($it['item_name'] ?? ''));
            $item_map[(int)$it['id']] = $label;
        }


    // master_user（作業者）表示用：ID→名称のマッピング
    $user_map = [];
    $need_user_ids = [];
    foreach ($fields as $f) {
        if (($f['type'] ?? '') === 'master_user') {
            foreach ($rows as $r) {
                $rid = (int)$r['id'];
                $fid = (int)$f['id'];
                $v = $values_map[$rid][$fid] ?? '';
                if ($v !== '' && ctype_digit((string)$v)) $need_user_ids[(int)$v] = true;
            }
        }
    }
    if (!empty($need_user_ids)) {
        $ids = array_keys($need_user_ids);
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $sqlU = "SELECT id, user_code, user_name FROM {$p}paper_users WHERE id IN ($in)";
        $us = $wpdb->get_results($wpdb->prepare($sqlU, $ids), ARRAY_A);
        foreach ($us as $u) {
            $label = trim((string)($u['user_code'] ?? '') . ' ' . (string)($u['user_name'] ?? ''));
            $user_map[(int)$u['id']] = $label;
        }
    }
    }


    // output CSV (UTF-8 with BOM for Excel)
    $filename = 'paper_form_records_' . gmdate('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";

    $header = ['ID','テンプレートID','テンプレート名','連番','作業者','ステータス','登録日時'];
    foreach ($fields as $f) {
        $is_master = (((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0));
        if ($is_master) {
            $header[] = $f['label'].'(コード)';
            $header[] = $f['label'].'(名称)';
        } else {
            $header[] = $f['label'];
        }
    }
    echo implode(',', array_map('paper_form_csv_escape', $header)) . "\r\n";
foreach ($rows as $r) {
        $rid = (int)$r['id'];
        $line = [
            $rid,
            (int)$r['template_id'],
            (string)$r['template_name'],
            (int)$r['record_no'],
            (string)$r['user_name'],
            (string)$r['status'],
            (string)$r['created_at'],
        ];
        foreach ($fields as $f) {
            $fid = (int)$f['id'];
            if ($template_id === 0 && (int)$f['template_id'] !== (int)$r['template_id']) {
                $is_master = (((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0));
                if ($is_master) { $line[] = ''; $line[] = ''; } else { $line[] = ''; }
            } else {
                
$val = $values_map[$rid][$fid] ?? '';
$raw = (string)$val;
$is_master = (((int)($f['master_id'] ?? 0) > 0) || (($f['type'] ?? '') === 'master_select') || (strpos((string)($f['type'] ?? ''), 'master:') === 0));
if ($is_master) {
    $idv = 0; $code = ''; $name = '';
    if ($raw !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp) && isset($tmp['id'])) {
            $idv = (int)$tmp['id'];
            $code = (string)($tmp['code'] ?? '');
        } elseif (strpos($raw, '|') !== false) {
            $parts = explode('|', $raw, 3);
            $idv = (int)($parts[0] ?? 0);
            $code = (string)($parts[1] ?? '');
        } elseif (ctype_digit($raw)) {
            $idv = (int)$raw;
        }
    }
    if ($idv > 0 && isset($master_value_map[$idv])) {
        $code = $code !== '' ? $code : (string)($master_value_map[$idv]['code'] ?? '');
        $name = (string)($master_value_map[$idv]['name'] ?? '');
    }
    $line[] = $code;
    $line[] = $name;
} else {
    $line[] = (string)$val;
}
}
        }
        echo implode(',', array_map('paper_form_csv_escape', $line)) . "\r\n";
}

    exit;
}
