<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Activator {
    public static function activate() {
        self::create_tables();
        self::create_roles();
        if (class_exists('SMV_CPT')) {
            SMV_CPT::register_post_types();
            if (method_exists('SMV_CPT', 'register_taxonomies')) {
                SMV_CPT::register_taxonomies();
            }
        }
        // ساخت ۲۰ مقالهٔ آماده به اولین ورود مدیر به پیشخوان موکول می‌شود (نه لحظهٔ فعال‌سازی)
        if (!get_option('smv_articles_seeded_v1')) {
            update_option('smv_seed_articles_pending', 1);
        }
        flush_rewrite_rules();
        update_option('smv_db_version', SMV_DB_VERSION);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('smv_scheduled_backup');
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        // جدول ثبت‌نام‌ها (شامل ستون‌های سانس، روش پرداخت، مبلغ و کمک مالی)
        $table = $wpdb->prefix . 'smv_registrations';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            theatre_id bigint(20) unsigned NOT NULL,
            showtime_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            phone varchar(30) NOT NULL,
            tracking_code varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_note text NULL,
            receipt_path varchar(255) NOT NULL DEFAULT '',
            receipt_mime varchar(100) NOT NULL DEFAULT '',
            payment_type varchar(20) NOT NULL DEFAULT 'free',
            companions tinyint(1) NOT NULL DEFAULT 0,
            amount bigint(20) NOT NULL DEFAULT 0,
            financial_aid_amount bigint(20) NOT NULL DEFAULT 0,
            financial_aid_reason text NULL,
            payment_ref varchar(100) NOT NULL DEFAULT '',
            paid_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tracking_code (tracking_code),
            KEY theatre_id (theatre_id),
            KEY showtime_id (showtime_id),
            KEY status (status),
            KEY phone (phone),
            KEY payment_type (payment_type),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta($sql);

        // آپدیت جداول موجود (اضافه کردن ستون‌های جدید اگر وجود ندارند)
        self::ensure_columns($table, array(
            'showtime_id' => 'bigint(20) unsigned NOT NULL DEFAULT 0',
            'payment_type' => "varchar(20) NOT NULL DEFAULT 'free'",
            'companions' => 'tinyint(1) NOT NULL DEFAULT 0',
            'amount' => 'bigint(20) NOT NULL DEFAULT 0',
            'financial_aid_amount' => 'bigint(20) NOT NULL DEFAULT 0',
            'financial_aid_reason' => 'text NULL',
            'payment_ref' => "varchar(100) NOT NULL DEFAULT ''",
            'paid_at' => 'datetime NULL DEFAULT NULL',
        ));

        // جدول نظرات (حداکثر ۲ نظر تأییدشده برای هر اجرا)
        $review_table = $wpdb->prefix . 'smv_reviews';
        $review_sql = "CREATE TABLE IF NOT EXISTS {$review_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            theatre_id bigint(20) NOT NULL,
            author varchar(200) NOT NULL,
            comment text NOT NULL,
            rating tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY theatre_id (theatre_id),
            KEY status (status)
        ) {$charset};";
        dbDelta($review_sql);
        self::ensure_columns($review_table, array(
            'updated_at' => 'datetime NULL DEFAULT NULL',
        ));

        update_option('smv_db_version', SMV_DB_VERSION);
    }

    private static function ensure_columns($table, $columns) {
        global $wpdb;
        $existing = $wpdb->get_results("DESCRIBE {$table}");
        if (!is_array($existing) || !$existing) {
            return;
        }
        $existing_cols = array();
        foreach ($existing as $col) {
            $existing_cols[] = $col->Field;
        }
        foreach ($columns as $col_name => $col_def) {
            if (!in_array($col_name, $existing_cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col_name} {$col_def}");
            }
        }
    }

    public static function capabilities() {
        $caps = array('manage_smv_registrations');
        foreach (array('smv_theatre', 'smv_project', 'smv_showtime', 'smv_poll', 'smv_review', 'smv_article') as $type) {
            $plural = $type . 's';
            $caps = array_merge($caps, array(
                'edit_' . $type,
                'read_' . $type,
                'delete_' . $type,
                'edit_' . $plural,
                'edit_others_' . $plural,
                'publish_' . $plural,
                'read_private_' . $plural,
                'delete_' . $plural,
                'delete_private_' . $plural,
                'delete_published_' . $plural,
                'delete_others_' . $plural,
                'edit_private_' . $plural,
                'edit_published_' . $plural,
            ));
        }
        return array_values(array_unique($caps));
    }

    public static function create_roles() {
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::capabilities() as $cap) {
                $admin->add_cap($cap);
            }
        }

        $manager = add_role('smv_manager', 'مدیر ثبت‌نام سماویه', array(
            'read' => true,
            'upload_files' => true,
        ));

        if (!$manager) {
            $manager = get_role('smv_manager');
        }
        if ($manager) {
            $manager->add_cap('read');
            $manager->add_cap('upload_files');
            foreach (self::capabilities() as $cap) {
                $manager->add_cap($cap);
            }
        }
    }
}
