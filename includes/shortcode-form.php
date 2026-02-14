<?php
if (!defined('ABSPATH')) { exit; }

// includes/shortcode-form.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure save function is loaded
if (!function_exists('kami_form_save_record')) {
    require_once KAMI_FORM_DIR . 'includes/save-record.php';
}

require_once plugin_dir_path(__FILE__) . 'save-record.php';

function kami_form_shortcode($atts) {
    global $wpdb;
    $p = $wpdb->prefix;

    $atts = shortcode_atts([
        'template' => '0',
    ], $atts);

    $template_id = (int)$atts['template'];
    if ($template_id <= 0) return '<p>template指定が不正です。</p>';

    $tpl = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, bg_attachment_id, is_active
         FROM {$p}kami_templates
         WHERE id=%d AND is_active=1",
        $template_id
    ), ARRAY_A);
    if (!$tpl) return '<p>テンプレートが見つかりません。</p>';

    $bg_url = wp_get_attachment_image_url((int)$tpl['bg_attachment_id'], 'full');
    if (!$bg_url) return '<p>背景画像が見つかりません。</p>';

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT id, type, required, rules_json, x_pct, y_pct, w_pct, h_pct, master_id
         FROM {$p}kami_fields
         WHERE template_id=%d AND is_active=1
         ORDER BY sort_order ASC, id ASC",
        $template_id
    ), ARRAY_A);
    if (!$fields) return '<p>入力項目がありません。</p>';

    $msg = '';
    $saved = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kami_form_action']) && $_POST['kami_form_action'] === 'save') {
        if (!isset($_POST['kami_form_nonce']) || !wp_verify_nonce($_POST['kami_form_nonce'], 'kami_form_save_'.$template_id)) {
            $msg = '<div class="kami-form-error">送信の整合性確認に失敗しました（nonce）。</div>';
        } else {
            $hp = isset($_POST['pf_hp']) ? trim((string)$_POST['pf_hp']) : '';
            if ($hp !== '') {
                $msg = '<div class="kami-form-error">送信が不正と判定されました。</div>';
            } else {
                $record_date = null;
                $user_id = 0;
$vals = [];
                foreach ($fields as $f) {
                    $fid = (int)$f['id'];
                    $key = 'f_' . $fid;
                    $vals[$fid] = $_POST[$key] ?? '';
                }

                $res = kami_form_save_record($template_id, $record_date, $user_id, $vals);
                if ($res['ok']) {
                    $saved = $res;
                    $msg = '<div class="kami-form-ok">登録しました。番号：' . esc_html((string)$res['record_no']) . '</div>';
                } else {
                    $msg = '<div class="kami-form-error">登録に失敗しました：' . esc_html((string)$res['error']) . '</div>';
                }
            }
        }
    }

    $out  = '<style>
.kami-form-wrap{max-width:1100px;margin:0 auto;}
.kami-stage{position:relative;width:100%;border:1px solid #ddd;}
.kami-stage img{width:100%;height:auto;display:block;}
.kami-field{position:absolute;box-sizing:border-box;}
.kami-field input,.kami-field select,.kami-field textarea{
  width:100%;height:100%;box-sizing:border-box;
  font-size:16px;padding:4px 6px;border:1px solid #666;border-radius:4px;background:rgba(255,255,255,0.9);
}
.kami-topbar{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;}
.kami-topbar .ctl{display:flex;gap:6px;align-items:center;}
.kami-form-ok{padding:8px 10px;background:#e7ffe7;border:1px solid #6ac46a;margin:8px 0;}
.kami-form-error{padding:8px 10px;background:#ffe7e7;border:1px solid #d26a6a;margin:8px 0;}
.kami-submit{margin:12px 0;}
.kami-submit button{font-size:18px;padding:10px 16px;}
</style>';

    $out .= '<div class="kami-form-wrap">';
    $out .= $msg;

    $out .= '<form method="post">';
    $out .= '<input type="hidden" name="kami_form_action" value="save">';
    $out .= wp_nonce_field('kami_form_save_'.$template_id, 'kami_form_nonce', true, false);

    $out .= '<div style="display:none;"><label>leave blank</label><input type="text" name="pf_hp" value=""></div>';

    $out .= '<div class="kami-stage">';
    $out .= '<img src="' . esc_url($bg_url) . '" alt="">';

    foreach ($fields as $f) {
        $fid = (int)$f['id'];
        $type = (string)$f['type'];
        
        if ($type === 'label') {
            // 表示のみラベル
            $style = 'position:absolute;left:'.((int)$f['x']).'px;top:'.((int)$f['y']).'px;'.
                     'width:'.((int)$f['w']).'px;height:'.((int)$f['h']).'px;'.
                     'overflow:hidden;white-space:pre-wrap;';
            $text = (string)($f['label'] ?? '');
            echo '<div class=\"pf-label\" style=\"'.esc_attr($style).'\">'.esc_html($text).'</div>';
            continue;
        }
$required = ((int)$f['required'] === 1);

        $style = sprintf(
            'left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
            esc_attr((string)$f['x_pct']),
            esc_attr((string)$f['y_pct']),
            esc_attr((string)$f['w_pct']),
            esc_attr((string)$f['h_pct'])
        );

        $rules = [];
        if (!empty($f['rules_json'])) {
            $tmp = json_decode((string)$f['rules_json'], true);
            if (is_array($tmp)) $rules = $tmp;
        }

        $name = 'f_' . $fid;
        $val  = $_POST[$name] ?? '';

        $out .= '<div class="kami-field" style="' . $style . '">';

        if ($type === 'master_item') {
            // 品番マスターのプルダウン
            $items = $wpdb->get_results("SELECT id, item_code, item_name FROM {$p}kami_items WHERE is_active=1 ORDER BY item_code ASC, item_name ASC", ARRAY_A);
            $out .= '<select name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
            $out .= '<option value=""></option>';
            foreach ($items as $it) {
                $idv = (string)($it['id'] ?? '');
                $sel = ($idv !== '' && (string)$val === $idv) ? ' selected' : '';
                $label = trim((string)($it['item_code'] ?? '') . ' ' . (string)($it['item_name'] ?? ''));
                $out .= '<option value="' . esc_attr($idv) . '"' . $sel . '>' . esc_html($label) . '</option>';
            }
            $out .= '</select>';
            $out .= '</div>';
            continue;
        }


        
        if ($type === 'master_user') {
            // 作業者マスターのプルダウン
            $out .= '<select name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
            $out .= '<option value=""></option>';
            foreach ($users as $u) {
                $idv = (string)($u['id'] ?? '');
                $sel = ($idv !== '' && (string)$val === $idv) ? ' selected' : '';
                $label = trim((string)($u['user_code'] ?? '') . ' ' . (string)($u['user_name'] ?? ''));
                $out .= '<option value="' . esc_attr($idv) . '"' . $sel . '>' . esc_html($label) . '</option>';
            }
            $out .= '</select>';
            $out .= '</div>';
            continue;
        }


if ($type === 'master_select') {
    $master_id = (int)($f['master_id'] ?? 0);
    $opts = [];
    if ($master_id > 0) {
        $opts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, value_code, value_name FROM {$p}kami_master_values WHERE master_id=%d AND is_active=1 ORDER BY sort_order ASC, value_code ASC, value_name ASC",
            $master_id
        ), ARRAY_A);
    }
    $out .= '<select name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
    $out .= '<option value=""></option>';

    $sel_id = '';
    $sel_code = '';
    if ($val !== '') {
        $tmp = json_decode((string)$val, true);
        if (is_array($tmp) && isset($tmp['id'])) {
            $sel_id = (string)(int)$tmp['id'];
            $sel_code = (string)($tmp['code'] ?? '');
        } elseif (ctype_digit((string)$val)) {
            $sel_id = (string)$val;
        }
    }

    foreach ($opts as $o) {
        $idv = (string)($o['id'] ?? '');
        $code = (string)($o['value_code'] ?? '');
        $label = trim($code . ' ' . (string)($o['value_name'] ?? ''));
        $value = $idv . '|' . $code;
        $sel = ($sel_id !== '' && $idv === $sel_id) ? ' selected' : '';
        $out .= '<option value="' . esc_attr($value) . '"' . $sel . '>' . esc_html($label) . '</option>';
    }

    $out .= '</select>';
    $out .= '</div>';
    continue;
}

if ($type === 'textarea') {
            $out .= '<textarea name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>'
                 . esc_textarea((string)$val) . '</textarea>';
        } else {
            $htmlType = 'text';
            $extra = '';

            if ($type === 'number') {
                $htmlType = 'number';
                $extra .= ' inputmode="decimal"';
                if (isset($rules['min']))  $extra .= ' min="' . esc_attr((string)$rules['min']) . '"';
                if (isset($rules['max']))  $extra .= ' max="' . esc_attr((string)$rules['max']) . '"';
                if (isset($rules['step'])) $extra .= ' step="' . esc_attr((string)$rules['step']) . '"';
            } elseif ($type === 'date') {
                $htmlType = 'date';
            } elseif ($type === 'time') {
                $htmlType = 'time';
            } elseif ($type === 'email') {
                $htmlType = 'email';
                $extra .= ' inputmode="email"';
            } else {
                $htmlType = 'text';
                if (isset($rules['maxlen'])) $extra .= ' maxlength="' . esc_attr((string)$rules['maxlen']) . '"';
                if (isset($rules['pattern'])) $extra .= ' pattern="' . esc_attr((string)$rules['pattern']) . '"';
            }

            $out .= '<input type="' . esc_attr($htmlType) . '" name="' . esc_attr($name) . '"'
                 . ($required ? ' required' : '')
                 . $extra
                 . ' value="' . esc_attr((string)$val) . '">';
        }

        $out .= '</div>';
    }

    $out .= '</div>';

    $out .= '<div class="kami-submit"><button type="submit">登録</button></div>';
    $out .= '</form>';

    if ($saved && isset($saved['record_no'])) {
        $out .= '<div>登録番号：' . esc_html((string)$saved['record_no']) . '</div>';
    }

    $out .= '</div>';
    return $out;
}
add_shortcode('kami_form', 'kami_form_shortcode');
