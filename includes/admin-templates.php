<?php
if (!defined('ABSPATH')) { exit; }

// includes/admin-templates.php
if ( ! defined( 'ABSPATH' ) ) exit;

function paper_form_admin_templates_page() {
    if ( ! current_user_can('edit_posts') ) return;

    global $wpdb; $p = $wpdb->prefix;

    // Handle create/update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pf_action']) && $_POST['pf_action'] === 'save_template') {
        check_admin_referer('pf_save_template');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = sanitize_text_field($_POST['name'] ?? '');
        $bg_attachment_id = (int)($_POST['bg_attachment_id'] ?? 0);
        $base_width = (int)($_POST['base_width'] ?? 0);
        $base_height = (int)($_POST['base_height'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $bg_attachment_id <= 0) {
            echo '<div class="notice notice-error"><p>用紙名と背景画像は必須です。</p></div>';
        } else {
            if ($id > 0) {
                $wpdb->update(
                    "{$p}paper_templates",
                    [
                        'name' => $name,
                        'bg_attachment_id' => $bg_attachment_id,
                        'base_width' => $base_width,
                        'base_height' => $base_height,
                        'is_active' => $is_active,
                    ],
                    ['id' => $id],
                    ['%s','%d','%d','%d','%d'],
                    ['%d']
                );
                echo '<div class="notice notice-success"><p>更新しました。</p></div>';
            } else {
                $wpdb->insert(
                    "{$p}paper_templates",
                    [
                        'name' => $name,
                        'bg_attachment_id' => $bg_attachment_id,
                        'base_width' => $base_width,
                        'base_height' => $base_height,
                        'is_active' => $is_active,
                    ],
                    ['%s','%d','%d','%d','%d']
                );
                echo '<div class="notice notice-success"><p>作成しました。</p></div>';
            }
        }
    }

    // Handle delete
    if (isset($_GET['pf_delete']) && isset($_GET['_wpnonce'])) {
        $del_id = (int)$_GET['pf_delete'];
        if ($del_id > 0 && wp_verify_nonce($_GET['_wpnonce'], 'pf_delete_template_'.$del_id)) {
            $wpdb->delete("{$p}paper_templates", ['id'=>$del_id], ['%d']);
            echo '<div class="notice notice-success"><p>削除しました。</p></div>';
        }
    }

    $edit_id = isset($_GET['pf_edit']) ? (int)$_GET['pf_edit'] : 0;
    $editing = null;
    if ($edit_id > 0) {
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_templates WHERE id=%d", $edit_id), ARRAY_A);
    }

    $templates = $wpdb->get_results("SELECT * FROM {$p}paper_templates ORDER BY id DESC", ARRAY_A);

    echo '<div class="wrap"><h1>用紙テンプレート</h1>';

    // Form
    echo '<div class="pf-box"><h2>' . ($editing ? 'テンプレート編集' : 'テンプレート新規作成') . '</h2>';
    echo '<form method="post">';
    wp_nonce_field('pf_save_template');
    echo '<input type="hidden" name="pf_action" value="save_template">';
    echo '<input type="hidden" name="id" value="' . esc_attr($editing['id'] ?? 0) . '">';

    $bg_id = (int)($editing['bg_attachment_id'] ?? 0);
    $bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';

    echo '<div class="pf-row"><label>用紙名</label><input type="text" name="name" required value="' . esc_attr($editing['name'] ?? '') . '"></div>';

    echo '<div class="pf-row"><label>背景画像</label>
        <input type="hidden" id="pf_bg_id" name="bg_attachment_id" value="' . esc_attr($bg_id) . '">
        <input type="text" id="pf_bg_url" value="' . esc_attr($bg_url) . '" readonly style="min-width:420px;">
        <button class="button pf-pick-media" data-target-id="pf_bg_id" data-target-url="pf_bg_url">メディアから選択</button>
    </div>';
    echo '<div class="pf-row"><label></label><img class="pf-bg-preview" src="' . esc_url($bg_url) . '" ' . ($bg_url ? '' : 'style="display:none;"') . '></div>';

    echo '<div class="pf-row"><label>基準サイズ</label>
        <input type="number" name="base_width" placeholder="幅(px)" value="' . esc_attr($editing['base_width'] ?? 0) . '">
        <input type="number" name="base_height" placeholder="高さ(px)" value="' . esc_attr($editing['base_height'] ?? 0) . '">
        <span class="pf-help">未入力でも可（%配置のため必須ではありません）</span>
    </div>';

    $checked = ((int)($editing['is_active'] ?? 1) === 1) ? 'checked' : '';
    echo '<div class="pf-row"><label>有効</label><label><input type="checkbox" name="is_active" value="1" ' . $checked . '> 有効</label></div>';

    echo '<div class="pf-row"><label></label><button class="button button-primary">保存</button></div>';
    echo '</form></div>';

    // List
    echo '<h2>テンプレート一覧</h2>';
    echo '<table class="widefat striped pf-table"><thead><tr>
            <th>ID</th><th>用紙名</th><th>有効</th><th>背景</th><th>操作</th>
          </tr></thead><tbody>';

    foreach ($templates as $t) {
        $id = (int)$t['id'];
        $bg = (int)$t['bg_attachment_id'];
        $bg_thumb = $bg ? wp_get_attachment_image($bg, [80,80], true) : '';
        $del_url = wp_nonce_url(admin_url('admin.php?page=paper-forms&pf_delete='.$id), 'pf_delete_template_'.$id);
        $edit_url = admin_url('admin.php?page=paper-forms&pf_edit='.$id);

        echo '<tr>
            <td>'.esc_html($id).'</td>
            <td>'.esc_html($t['name']).'</td>
            <td>'.(((int)$t['is_active']===1)?'○':'').'</td>
            <td>'.$bg_thumb.'</td>
            <td>
              <a class="button" href="'.esc_url($edit_url).'">編集</a>
              <a class="button button-link-delete" href="'.esc_url($del_url).'" onclick="return confirm(&quot;削除しますか？&quot;)">削除</a>
              <a class="button" href="'.esc_url(admin_url('admin.php?page=paper-form-fields&template_id='.$id)).'">項目設定</a>
            </td>
        </tr>';
    }
    echo '</tbody></table>';

    echo '<p class="pf-help">現場入力ページにはショートコード <code>[paper_form template="ID"]</code> を貼り付けてください。</p>';
    echo '</div>';
}

function paper_form_admin_fields_page() {
    if ( ! current_user_can('edit_posts') ) return;
    global $wpdb; $p = $wpdb->prefix;

    $template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
    if ($template_id <= 0) {
        echo '<div class="wrap"><h1>項目設定</h1><p>テンプレートを選択してください（テンプレート一覧から「項目設定」を押します）。</p></div>';
        return;
    }

    $tpl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_templates WHERE id=%d", $template_id), ARRAY_A);
    if (!$tpl) { echo '<div class="wrap"><p>テンプレートが見つかりません。</p></div>'; return; }

    // Add field
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['pf_action'] ?? '') === 'add_field') {
        check_admin_referer('pf_add_field_'.$template_id);

        $label = sanitize_text_field($_POST['label'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'text');
        
        // type が master:<id> の場合は master_select として扱う
        if (strpos($type, 'master:') === 0) {
            $master_id = (int)substr($type, strlen('master:'));
            $type = 'master_select';
        }
$master_id = (int)($_POST['master_id'] ?? 0);
        if ($master_id > 0) { $type = 'master_select'; }
        $required = isset($_POST['required']) ? 1 : 0;
        $x = (float)($_POST['x_pct'] ?? 0);
        $y = (float)($_POST['y_pct'] ?? 0);
        $w = (float)($_POST['w_pct'] ?? 10);
        $h = (float)($_POST['h_pct'] ?? 3);

        if ($label === '') {
            echo '<div class="notice notice-error"><p>表示名（管理/CSV用）は必須です。</p></div>';
        } else {
            // generate field_key t{tid}_fNNN
            $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}paper_fields WHERE template_id=%d", $template_id));
            $field_key = sprintf('t%d_f%03d', $template_id, $count + 1);

            $wpdb->insert("{$p}paper_fields", [
                'template_id'=>$template_id,
                'field_key'=>$field_key,
                'label'=>$label,
                'type'=>$type,
            'master_id'=>($type==='master_select' ? $master_id : 0),
                'required'=>$required,
                'rules_json'=>null,
                'x_pct'=>$x,'y_pct'=>$y,'w_pct'=>$w,'h_pct'=>$h,
                'sort_order'=>$count+1,
                'is_active'=>1,
            ], ['%d','%s','%s','%s','%d','%s','%f','%f','%f','%f','%d','%d']);
            echo '<div class="notice notice-success"><p>項目を追加しました。</p></div>';
        }
    }

    // Update field
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['pf_action'] ?? '') === 'update_field') {
        $field_id = (int)($_POST['field_id'] ?? 0);
        check_admin_referer('pf_update_field_'.$field_id);

        $label = sanitize_text_field($_POST['label'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'text');
        
        // type が master:<id> の場合は master_select として扱う
        if (strpos($type, 'master:') === 0) {
            $master_id = (int)substr($type, strlen('master:'));
            $type = 'master_select';
        }
$master_id = (int)($_POST['master_id'] ?? 0);
        if ($master_id > 0) { $type = 'master_select'; }
        $required = isset($_POST['required']) ? 1 : 0;
        $x = (float)($_POST['x_pct'] ?? 0);
        $y = (float)($_POST['y_pct'] ?? 0);
        $w = (float)($_POST['w_pct'] ?? 10);
        $h = (float)($_POST['h_pct'] ?? 3);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // rules_json as free JSON
        $rules_raw = trim((string)($_POST['rules_json'] ?? ''));
        $rules_json = null;
        if ($rules_raw !== '') {
            $tmp = json_decode($rules_raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rules_json = wp_json_encode($tmp, JSON_UNESCAPED_UNICODE);
            } else {
                echo '<div class="notice notice-error"><p>rules_json はJSONとして不正です。</p></div>';
            }
        }

        $wpdb->update("{$p}paper_fields", [
            'label'=>$label,
            'type'=>$type,
            'master_id'=>($type==='master_select' ? $master_id : 0),
            'required'=>$required,
            'rules_json'=>$rules_json,
            'x_pct'=>$x,'y_pct'=>$y,'w_pct'=>$w,'h_pct'=>$h,
            'is_active'=>$is_active
        ], ['id'=>$field_id, 'template_id'=>$template_id],
        ['%s','%s','%d','%s','%f','%f','%f','%f','%d'],
        ['%d','%d']);
        echo '<div class="notice notice-success"><p>更新しました。</p></div>';
    }

    // Delete field
    if (isset($_GET['pf_del_field']) && isset($_GET['_wpnonce'])) {
        $fid = (int)$_GET['pf_del_field'];
        if ($fid>0 && wp_verify_nonce($_GET['_wpnonce'], 'pf_del_field_'.$fid)) {
            $wpdb->delete("{$p}paper_fields", ['id'=>$fid, 'template_id'=>$template_id], ['%d','%d']);
            echo '<div class="notice notice-success"><p>削除しました。</p></div>';
        }
    }

    
    // Copy field
    if (isset($_GET['pf_copy_field']) && isset($_GET['_wpnonce'])) {
        $fid = (int) $_GET['pf_copy_field'];
        if ($fid > 0 && wp_verify_nonce($_GET['_wpnonce'], 'pf_copy_field_'.$fid)) {
            $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}paper_fields WHERE id=%d AND template_id=%d", $fid, $template_id), ARRAY_A);
            if ($src) {
                $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}paper_fields WHERE template_id=%d", $template_id));
                $field_key = sprintf('t%d_f%03d', $template_id, $count + 1);

                $new = $src;
                unset($new['id']);
                $new['field_key'] = $field_key;
                $new['label'] = (string)$src['label'] . '（コピー）';

                // コピー直後に重ならないように Y を +5% ずらす（はみ出す場合は -10%）
                if (isset($src['y_pct'])) {
                    $old_y = (float) $src['y_pct'];
                    $new_y = $old_y + 5.0;
                    if ($new_y > 100.0) { $new_y = max($old_y - 5.0, 0.0); }
                    $new['y_pct'] = $new_y;
                }

                // 位置などはそのまま。並び順は末尾へ
                if (isset($new['sort_order'])) { $new['sort_order'] = (int)$count + 1; }

                $ok = $wpdb->insert("{$p}paper_fields", $new);
                if ($ok) {
                    echo '<div class="notice notice-success"><p>コピーしました。</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>コピーに失敗しました。</p></div>';
                }
            }
        }
    }

$masters_all = $wpdb->get_results("SELECT id, master_name FROM {$p}paper_masters WHERE is_active=1 ORDER BY sort_order ASC, id ASC", ARRAY_A);

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}paper_fields WHERE template_id=%d AND type<>'label' ORDER BY sort_order ASC, id ASC",
        $template_id
    ), ARRAY_A);

    echo '<div class="wrap"><h1>項目設定</h1>';
    echo '<p><strong>テンプレート:</strong> ' . esc_html($tpl['name']) . '（ID:'.esc_html($template_id).'）</p>';

    // Visual designer (背景画像上に枠を表示)
    $bg_id = (int)$tpl['bg_attachment_id'];
    $bg_url = $bg_id ? wp_get_attachment_image_url($bg_id, 'full') : '';
    $nonce = wp_create_nonce('pf_save_positions_'.$template_id);

    if ($bg_url) {
        echo '<div class="pf-box"><h2>配置エディタ（ドラッグ＆リサイズ）</h2>';
        echo '<div class="pf-designer-wrap">';
        echo '<div id="pf-canvas-wrap">';
        echo '<div id="pf-canvas" data-template-id="'.esc_attr($template_id).'" data-nonce="'.esc_attr($nonce).'" data-ajax-url="'.esc_url(admin_url('admin-ajax.php')).'">';
        echo '<img src="'.esc_url($bg_url).'" alt="">';

        foreach ($fields as $f) {
            $fid = (int)$f['id'];
            // % -> inline style
            $style = sprintf(
                'left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
                esc_attr((string)$f['x_pct']),
                esc_attr((string)$f['y_pct']),
                esc_attr((string)$f['w_pct']),
                esc_attr((string)$f['h_pct'])
            );
            echo '<div class="pf-field-box" data-field-id="'.esc_attr($fid).'"
                    data-x-pct="'.esc_attr($f['x_pct']).'"
                    data-y-pct="'.esc_attr($f['y_pct']).'"
                    data-w-pct="'.esc_attr($f['w_pct']).'"
                    data-h-pct="'.esc_attr($f['h_pct']).'"
                    style="'.$style.'">';
            echo '<div class="pf-mini">'.esc_html($f['label']).'</div>';
            echo '</div>';
        }

        echo '</div>'; // canvas
        echo '<div style="margin-top:10px;">
                <button class="button button-primary" id="pf-save-positions">位置/サイズを保存</button>
                <span id="pf-save-status" class="pf-help" style="margin-left:8px;"></span>
              </div>';
        echo '</div>'; // canvas-wrap

        echo '<div id="pf-side" class="pf-box">';
        echo '<h3>使い方</h3>';
        echo '<ol>
                <li>枠をドラッグして位置調整</li>
                <li>角や辺をドラッグしてサイズ変更</li>
                <li>最後に「位置/サイズを保存」</li>
              </ol>';
        echo '<div id="pf-selected" class="pf-help">選択中: なし</div>';
        echo '<p class="pf-help">※表示名（管理/CSV用）は下の一覧で編集できます（現場画面では表示されません）。</p>';
        echo '</div>'; // side
        echo '</div>'; // designer-wrap
        echo '</div>'; // pf-box
    } else {
        echo '<div class="notice notice-warning"><p>背景画像が未設定です。テンプレートで背景画像を設定してください。</p></div>';
    }


    echo '<div class="pf-box"><h2>項目追加</h2>';
    echo '<form method="post">';
    wp_nonce_field('pf_add_field_'.$template_id);
    echo '<input type="hidden" name="pf_action" value="add_field">';
    echo '<div class="pf-row"><label>表示名（管理/CSV）</label><input type="text" name="label" required></div>';
    echo '<div class="pf-row"><label>タイプ</label><select name="type">'.paper_form_admin_type_options('text', 0).'</select>
          <label><input type="checkbox" name="required" value="1"> 必須</label>
          </div>';
    
echo '<div class="pf-row"><label>対象マスター</label>';
$masters = $wpdb->get_results("SELECT id, master_name FROM {$p}paper_masters WHERE is_active=1 ORDER BY sort_order ASC, id ASC", ARRAY_A);
echo '<select name="master_id"><option value="0"></option>';
foreach ($masters as $mm) {
    echo '<option value="'.esc_attr($mm['id']).'">'.esc_html($mm['master_name']).'</option>';
}
echo '</select><span class="pf-help" style="margin-left:8px;">対象マスターを選ぶと、この項目は自動的にプルダウンになります</span></div>';

    echo '<div class="pf-row"><label>位置/サイズ(%)</label>
            X <input type="number" step="0.01" name="x_pct" value="0">
            Y <input type="number" step="0.01" name="y_pct" value="0">
            W <input type="number" step="0.01" name="w_pct" value="10">
            H <input type="number" step="0.01" name="h_pct" value="3">
          </div>';
    echo '<div class="pf-row"><label></label><button class="button button-primary">追加</button></div>';
    echo '</form></div>';

    echo '<h2>項目一覧</h2>';
    echo '<table class="widefat striped pf-table"><thead><tr>
            <th>ID</th><th>field_key</th><th>表示名</th><th>type</th><th>必須</th><th>有効</th><th>位置(%)</th><th>rules_json</th><th>操作</th>
          </tr></thead><tbody>';

    foreach ($fields as $f) {
        $fid = (int)$f['id'];
        $del = wp_nonce_url(admin_url('admin.php?page=paper-form-fields&template_id='.$template_id.'&pf_del_field='.$fid), 'pf_del_field_'.$fid);
        $copy = wp_nonce_url(admin_url('admin.php?page=paper-form-fields&template_id='.$template_id.'&pf_copy_field='.$fid), 'pf_copy_field_'.$fid);
        echo '<tr><td>'.esc_html($fid).'</td>
            <td>'.esc_html($f['field_key']).'</td>
            <td>
              <form method="post" style="margin:0;">
              <input type="hidden" name="pf_action" value="update_field">
              <input type="hidden" name="field_id" value="'.esc_attr($fid).'">';
        wp_nonce_field('pf_update_field_'.$fid);
        echo '<input type="text" name="label" value="'.esc_attr($f['label']).'">
            </td>
            <td><select name="type">'.paper_form_admin_type_options($f['type'], (int)($f['master_id'] ?? 0)).'</select></td>
            <td>'.paper_form_admin_master_select((int)($f['master_id'] ?? 0), $masters_all).'</td>
            <td style="text-align:center;"><input type="checkbox" name="required" value="1" '.(((int)$f['required']===1)?'checked':'').'></td>
            <td style="text-align:center;"><input type="checkbox" name="is_active" value="1" '.(((int)$f['is_active']===1)?'checked':'').'></td>
            <td>
              X <input type="number" step="0.01" name="x_pct" value="'.esc_attr($f['x_pct']).'" style="width:90px;"><br>
              Y <input type="number" step="0.01" name="y_pct" value="'.esc_attr($f['y_pct']).'" style="width:90px;"><br>
              W <input type="number" step="0.01" name="w_pct" value="'.esc_attr($f['w_pct']).'" style="width:90px;"><br>
              H <input type="number" step="0.01" name="h_pct" value="'.esc_attr($f['h_pct']).'" style="width:90px;">
            </td>
            <td><textarea name="rules_json" rows="3" style="width:240px;">'.esc_textarea((string)$f['rules_json']).'</textarea>
                <div class="pf-help">例: {"min":0,"max":999,"maxlen":20}</div>
            </td>
            <td>
              <button class="button button-primary">更新</button>
              <a class="button" href="'.esc_url($copy).'">コピー</a>
              <a class="button button-link-delete" href="'.esc_url($del).'" onclick="return confirm(&quot;削除しますか？&quot;)">削除</a>
              </form>
            </td>
        </tr>';
    }
    echo '</tbody></table>';
    echo '<p class="pf-help">現場画面にはラベルは表示されません（値入力のみ）。</p>';
    echo '</div>';
}


function paper_form_admin_master_select($current_master_id, $masters) {
    $out = '<select name="master_id"><option value="0"></option>';
    foreach ($masters as $mm) {
        $sel = ((int)$current_master_id === (int)$mm['id']) ? ' selected' : '';
        $out .= '<option value="'.esc_attr($mm['id']).'"'.$sel.'>'.esc_html($mm['master_name']).'</option>';
    }
    $out .= '</select>';
    return $out;
}


function paper_form_admin_type_options($current, $master_id_unused = 0) {
    // 基本タイプ + ラベル（表示のみ）
    $types = ['text','number','date','time','email','textarea','label'];
    $out = '';
    foreach ($types as $t) {
        $sel = ($current===$t) ? ' selected' : '';
        $label = $t;
        if ($t === 'label') $label = 'ラベル（表示のみ）';
        $out .= '<option value="'.esc_attr($t).'"'.$sel.'>'.esc_html($label).'</option>';
    }

    // 既存仕様：マスターを type 側で選べる場合に備え、master:<id> も追加（既存互換）
    global $wpdb;
    $p = $wpdb->prefix;
    $masters = $wpdb->get_results("SELECT id, master_key, master_label FROM {$p}paper_masters WHERE is_active=1 ORDER BY id ASC", ARRAY_A);
    if (is_array($masters)) {
        foreach ($masters as $m) {
            $mid = (int)$m['id'];
            $key = (string)($m['master_key'] ?? '');
            $mlabel = (string)($m['master_label'] ?? '');
            $val = 'master:'.$mid;
            $sel = ($current===$val) ? ' selected' : '';
            $disp = trim($mlabel) !== '' ? $mlabel : ('master_'.$key);
            $out .= '<option value="'.esc_attr($val).'"'.$sel.'>'.esc_html($disp).'</option>';
        }
    }

}