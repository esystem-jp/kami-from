<?php
if (!defined('ABSPATH')) { exit; }

// includes/install.php
if ( ! defined( 'ABSPATH' ) ) exit;

function kami_form_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // テンプレート
    $sql_templates = "CREATE TABLE {$p}kami_templates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        bg_attachment_id BIGINT UNSIGNED NOT NULL,
        base_width INT NOT NULL DEFAULT 0,
        base_height INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    // 項目定義
    $sql_fields = "CREATE TABLE {$p}kami_fields (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        template_id BIGINT UNSIGNED NOT NULL,
        field_key VARCHAR(64) NOT NULL,
        label VARCHAR(255) NOT NULL,
        type VARCHAR(32) NOT NULL DEFAULT 'text',
        required TINYINT(1) NOT NULL DEFAULT 0,
        rules_json LONGTEXT NULL,
        master_id BIGINT UNSIGNED NULL,
        x_pct DECIMAL(10,4) NOT NULL DEFAULT 0,
        y_pct DECIMAL(10,4) NOT NULL DEFAULT 0,
        w_pct DECIMAL(10,4) NOT NULL DEFAULT 10,
        h_pct DECIMAL(10,4) NOT NULL DEFAULT 3,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY template_id (template_id),
        KEY field_key (field_key),
        KEY master_id (master_id)
    ) {$charset_collate};";

    // 作業者マスター
    $sql_users = "CREATE TABLE {$p}kami_users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_code VARCHAR(64) NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_code (user_code)
    ) {$charset_collate};";

    // 品番マスター
    $sql_items = "CREATE TABLE {$p}kami_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        item_code VARCHAR(64) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        spec_json LONGTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY item_code (item_code)
    ) {$charset_collate};";

    
    // 汎用マスター定義
    $sql_masters = "CREATE TABLE {$p}kami_masters (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        master_key VARCHAR(64) NOT NULL,
        master_name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY master_key (master_key)
    ) {$charset_collate};";

    // 汎用マスター値
    $sql_master_values = "CREATE TABLE {$p}kami_master_values (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        master_id BIGINT UNSIGNED NOT NULL,
        value_code VARCHAR(64) NOT NULL,
        value_name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY master_id (master_id),
        UNIQUE KEY master_code (master_id, value_code)
    ) {$charset_collate};";

// 代表日付廃止：連番はテンプレート単位
    $sql_sequences = "CREATE TABLE {$p}kami_sequences (
        template_id BIGINT UNSIGNED NOT NULL,
        record_date DATE NULL,
        last_no BIGINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (template_id)
    ) {$charset_collate};";

    // レコード
    $sql_records = "CREATE TABLE {$p}kami_records (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        template_id BIGINT UNSIGNED NOT NULL,
        record_date DATE NULL,
        record_no BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY template_id (template_id),
        KEY record_no (record_no),
        KEY created_at (created_at)
    ) {$charset_collate};";

    // 縦持ちの値
    $sql_values = "CREATE TABLE {$p}kami_record_values (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        record_id BIGINT UNSIGNED NOT NULL,
        field_id BIGINT UNSIGNED NOT NULL,
        value_long LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY record_id (record_id),
        KEY field_id (field_id)
    ) {$charset_collate};";

    dbDelta($sql_templates);
    dbDelta($sql_fields);
    dbDelta($sql_users);
    dbDelta($sql_items);
    dbDelta($sql_masters);
    dbDelta($sql_master_values);
    dbDelta($sql_sequences);
    dbDelta($sql_records);
    dbDelta($sql_values);

    // 既存環境の差異吸収
    $wpdb->query("ALTER TABLE {$p}kami_sequences MODIFY record_date DATE NULL");
    $wpdb->query("ALTER TABLE {$p}kami_records MODIFY record_date DATE NULL");
    $wpdb->query("ALTER TABLE {$p}kami_records MODIFY user_id BIGINT UNSIGNED NULL");


    // ---- 汎用マスターへ移行（B: 一本化） ----
    $master_user_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}kami_masters WHERE master_key=%s", 'user'));
    if ($master_user_id <= 0) {
        $wpdb->insert("{$p}kami_masters", ['master_key'=>'user','master_name'=>'作業者','is_active'=>1,'sort_order'=>10], ['%s','%s','%d','%d']);
        $master_user_id = (int)$wpdb->insert_id;
    }
    $master_item_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}kami_masters WHERE master_key=%s", 'item'));
    if ($master_item_id <= 0) {
        $wpdb->insert("{$p}kami_masters", ['master_key'=>'item','master_name'=>'品番','is_active'=>1,'sort_order'=>20], ['%s','%s','%d','%d']);
        $master_item_id = (int)$wpdb->insert_id;
    }

    // 旧マスター → 汎用マスター値へ
    $rows = $wpdb->get_results("SELECT user_code AS code, user_name AS name, is_active FROM {$p}kami_users", ARRAY_A);
    foreach ($rows as $r) {
        $code = (string)($r['code'] ?? '');
        $name = (string)($r['name'] ?? '');
        if ($code === '' && $name === '') continue;
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}kami_master_values WHERE master_id=%d AND value_code=%s",
            $master_user_id, $code
        ));
        if ($exists <= 0) {
            $wpdb->insert("{$p}kami_master_values", [
                'master_id'=>$master_user_id,
                'value_code'=>$code,
                'value_name'=>$name,
                'is_active'=>(int)($r['is_active'] ?? 1),
                'sort_order'=>0
            ], ['%d','%s','%s','%d','%d']);
        }
    }

    $rows = $wpdb->get_results("SELECT item_code AS code, item_name AS name, is_active FROM {$p}kami_items", ARRAY_A);
    foreach ($rows as $r) {
        $code = (string)($r['code'] ?? '');
        $name = (string)($r['name'] ?? '');
        if ($code === '' && $name === '') continue;
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}kami_master_values WHERE master_id=%d AND value_code=%s",
            $master_item_id, $code
        ));
        if ($exists <= 0) {
            $wpdb->insert("{$p}kami_master_values", [
                'master_id'=>$master_item_id,
                'value_code'=>$code,
                'value_name'=>$name,
                'is_active'=>(int)($r['is_active'] ?? 1),
                'sort_order'=>0
            ], ['%d','%s','%s','%d','%d']);
        }
    }

    // フィールドタイプ変換（既存の master_user/master_item を master_select に統一）
    $wpdb->query($wpdb->prepare("UPDATE {$p}kami_fields SET type='master_select', master_id=%d WHERE type='master_user'", $master_user_id));
    $wpdb->query($wpdb->prepare("UPDATE {$p}kami_fields SET type='master_select', master_id=%d WHERE type='master_item'", $master_item_id));

}
