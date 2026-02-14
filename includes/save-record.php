<?php
// includes/save-record.php
if ( ! defined('ABSPATH') ) { exit; }

function paper_form_parse_number($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = str_replace([',',' '], '', $s);
    return is_numeric($s) ? $s : null;
}
function paper_form_parse_date($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}
function paper_form_parse_time($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('/^\d{1,2}:\d{2}$/', $s)) return $s;
    $ts = strtotime($s);
    return $ts ? date('H:i', $ts) : null;
}

if ( ! function_exists('paper_form_save_record') ) {
function paper_form_save_record($template_id, $user_id = 0, $arg3 = null, $arg4 = null) {
    global $wpdb;
    $p = $wpdb->prefix;

// 互換：旧呼び出し（template_id, user_id, record_date, values）と
// 新呼び出し（template_id, user_id, values）の両方に対応
$values = [];
if (is_array($arg3) && $arg4 === null) {
    $values = $arg3;
} elseif (is_array($arg4)) {
    $values = $arg4;
} else {
    $values = [];
}

    $template_id = (int)$template_id;
    $user_id = (int)$user_id;
    if ($template_id <= 0) return ['ok'=>false,'error'=>'invalid_template'];
    if ($user_id < 0) $user_id = 0;

    $tpl = $wpdb->get_row($wpdb->prepare(
        "SELECT id, is_active FROM {$p}paper_templates WHERE id=%d",
        $template_id
    ), ARRAY_A);
    if (!$tpl) return ['ok'=>false,'error'=>'template_not_found'];
    if ((int)$tpl['is_active'] !== 1) return ['ok'=>false,'error'=>'template_inactive'];

    // user は任意（0なら未指定扱い）
    if ($user_id > 0) {
        $u = $wpdb->get_row($wpdb->prepare(
            "SELECT mv.id, mv.is_active
             FROM {$p}paper_masters m
             JOIN {$p}paper_master_values mv ON mv.master_id=m.id
             WHERE m.master_key='user' AND mv.id=%d",
            $user_id
        ), ARRAY_A);
        if (!$u) return ['ok'=>false,'error'=>'user_not_found'];
        if ((int)$u['is_active'] !== 1) return ['ok'=>false,'error'=>'user_inactive'];
    } else {
        $user_id = 0;
    }

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT id, type, required, rules_json, master_id
         FROM {$p}paper_fields
         WHERE template_id=%d AND is_active=1 AND type<>'label'
         ORDER BY sort_order ASC, id ASC",
        $template_id
    ), ARRAY_A);
    if (!$fields) return ['ok'=>false,'error'=>'no_fields'];

    $field_map = [];
    foreach ($fields as $f) $field_map[(int)$f['id']] = $f;

    // normalize
    $normalized = [];
    foreach ($field_map as $fid => $f) {
        $raw = array_key_exists($fid, $values) ? $values[$fid] : '';
        $raw = is_array($raw) ? '' : (string)$raw;
        $type = (string)($f['type'] ?? 'text');
        $required = ((int)($f['required'] ?? 0) === 1);

        $rules = [];
        if (!empty($f['rules_json'])) {
            $tmp = json_decode((string)$f['rules_json'], true);
            if (is_array($tmp)) $rules = $tmp;
        }

        $v_long = '';
        if ($type === 'number') {
            $num = paper_form_parse_number($raw);
            if ($required && $num === null) return ['ok'=>false,'error'=>"required_field_{$fid}"];
            if ($num !== null) {
                if (isset($rules['min']) && is_numeric($rules['min']) && (float)$num < (float)$rules['min']) return ['ok'=>false,'error'=>"min_violation_{$fid}"];
                if (isset($rules['max']) && is_numeric($rules['max']) && (float)$num > (float)$rules['max']) return ['ok'=>false,'error'=>"max_violation_{$fid}"];
                $v_long = (string)$num;
            }
        } elseif ($type === 'date') {
            $d = paper_form_parse_date($raw);
            if ($required && $d === null) return ['ok'=>false,'error'=>"required_field_{$fid}"];
            $v_long = $d ? (string)$d : '';
        } elseif ($type === 'time') {
            $t = paper_form_parse_time($raw);
            if ($required && $t === null) return ['ok'=>false,'error'=>"required_field_{$fid}"];
            $v_long = $t ? (string)$t : '';
        } elseif ($type === 'email') {
            $raw = trim($raw);
            if ($required && $raw === '') return ['ok'=>false,'error'=>"required_field_{$fid}"];
            if ($raw !== '' && !is_email($raw)) return ['ok'=>false,'error'=>"invalid_email_{$fid}"];
            $v_long = ($raw === '') ? '' : sanitize_email($raw);
        } elseif ($type === 'master_select') {
            // value: JSON or "id|code" or id
            $raw = trim($raw);
            $idv = '';
            $code = '';
            if ($raw !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp) && isset($tmp['id'])) {
                    $idv = (string)(int)$tmp['id'];
                    $code = (string)($tmp['code'] ?? '');
                } elseif (strpos($raw, '|') !== false) {
                    list($a,$b) = array_pad(explode('|',$raw,2),2,'');
                    $idv = ctype_digit($a) ? $a : '';
                    $code = (string)$b;
                } elseif (ctype_digit($raw)) {
                    $idv = $raw;
                }
            }
            if ($required && $idv === '') return ['ok'=>false,'error'=>"required_field_{$fid}"];
            $v_long = ($idv === '') ? '' : json_encode(['id'=>(int)$idv,'code'=>$code], JSON_UNESCAPED_UNICODE);
        } else {
            $raw = (string)$raw;
            if ($required && trim($raw) === '') return ['ok'=>false,'error'=>"required_field_{$fid}"];
            $v_long = sanitize_text_field($raw);
        }

        $normalized[$fid] = ['long' => (string)$v_long];
    }

    // Transaction for sequence + insert
    try {
        $wpdb->query('START TRANSACTION');

        // Ensure sequence row exists
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$p}paper_sequences (template_id, record_date, last_no)
             VALUES (%d, NULL, 0)",
            $template_id
        ));

        $seq = $wpdb->get_row($wpdb->prepare(
            "SELECT last_no FROM {$p}paper_sequences WHERE template_id=%d AND record_date IS NULL FOR UPDATE",
            $template_id
        ), ARRAY_A);
        if (!$seq) throw new Exception('seq_select_failed');
        $last_no = (int)($seq['last_no'] ?? 0);
        $record_no = $last_no + 1;

        $upd = $wpdb->query($wpdb->prepare(
            "UPDATE {$p}paper_sequences SET last_no=%d WHERE template_id=%d AND record_date IS NULL",
            $record_no, $template_id
        ));
        if ($upd === false) throw new Exception('seq_update_failed');

        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$p}paper_records (template_id, record_date, record_no, user_id, status)
             VALUES (%d, NULL, %d, %d, %s)",
            $template_id, $record_no, $user_id, 'active'
        ));
        if ($ok === false) throw new Exception('record_insert_failed');
        $record_id = (int)$wpdb->insert_id;

        foreach ($normalized as $fid => $v) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$p}paper_record_values WHERE record_id=%d AND field_id=%d",
                $record_id, (int)$fid
            ));
            if ($wpdb->last_error) throw new Exception('value_delete_failed');

            $okv = $wpdb->insert(
                "{$p}paper_record_values",
                [
                    'record_id' => $record_id,
                    'field_id' => (int)$fid,
                    'value_long' => (string)($v['long'] ?? ''),
                ],
                ['%d','%d','%s']
            );
            if ($okv === false || $wpdb->last_error) throw new Exception('value_insert_failed');
        }

        $wpdb->query('COMMIT');
        return ['ok'=>true,'record_id'=>$record_id,'record_no'=>$record_no];
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}}
