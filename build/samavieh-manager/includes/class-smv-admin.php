<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menus'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('wp_dashboard_setup', array(__CLASS__, 'setup_wp_dashboard'), 99);
        add_action('admin_post_smv_create_pages', array(__CLASS__, 'create_pages'));
        add_action('admin_post_smv_update_registration', array(__CLASS__, 'update_registration'));
        add_action('admin_post_smv_delete_registration', array(__CLASS__, 'delete_registration'));
        add_action('admin_post_smv_view_receipt', array(__CLASS__, 'view_receipt'));
        add_action('admin_post_smv_export_registrations', array(__CLASS__, 'export_csv'));
        add_action('admin_post_smv_export_pdf', array('SMV_PDF', 'export_pdf'));
        add_action('admin_post_smv_export_registration_pdf', array('SMV_PDF', 'export_single_registration_pdf'));
        add_action('admin_post_smv_save_theatre', array(__CLASS__, 'save_theatre'));
        add_action('admin_post_smv_delete_theatre', array(__CLASS__, 'delete_theatre'));
        add_action('admin_post_smv_save_showtime', array(__CLASS__, 'save_showtime'));
        add_action('admin_post_smv_delete_showtime', array(__CLASS__, 'delete_showtime'));
        // AJAX handlers for showtime delete (فقط برای کاربر وارد‌شده و دارای دسترسی)
        add_action('wp_ajax_smv_delete_showtime', array(__CLASS__, 'delete_showtime'));
        // AJAX handler for smart update from ZIP+JSON (فقط مدیر)
        add_action('wp_ajax_smv_smart_update', array(__CLASS__, 'smart_update_from_zip'));
        // Review update/delete
        add_action('admin_post_smv_update_review', array(__CLASS__, 'update_review'));
        add_action('admin_post_smv_delete_review', array(__CLASS__, 'delete_review'));
    }

    public static function update_review() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_POST['review_id']) ? absint($_POST['review_id']) : 0;
        check_admin_referer('smv_update_review_' . $id);
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'pending';
        if (!array_key_exists($status, smv_statuses())) {
            $status = 'pending';
        }
        global $wpdb;
        $table = $wpdb->prefix . 'smv_reviews';
        $wpdb->update($table, array('status' => $status, 'updated_at' => current_time('mysql')), array('id' => $id), array('%s', '%s'), array('%d'));
        wp_safe_redirect(add_query_arg('smv_review_updated', '1', admin_url('admin.php?page=smv-reviews')));
        exit;
    }

    public static function delete_review() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_POST['review_id']) ? absint($_POST['review_id']) : 0;
        check_admin_referer('smv_delete_review_' . $id);
        global $wpdb;
        $table = $wpdb->prefix . 'smv_reviews';
        $wpdb->delete($table, array('id' => $id), array('%d'));
        wp_safe_redirect(add_query_arg('smv_review_deleted', '1', admin_url('admin.php?page=smv-reviews')));
        exit;
    }

    public static function menus() {
        add_menu_page(
            'سماویه',
            'سماویه',
            'manage_smv_registrations',
            'smv-dashboard',
            array(__CLASS__, 'dashboard'),
            self::menu_icon(),
            26
        );

        // آیکن هر آیتم منو با Dashicons داخل عنوان منو قرار می‌گیرد (وردپرس آیکن جدا برای زیرمنو ندارد).
        $icon = function ($name) {
            return "<span class='dashicons dashicons-" . esc_attr($name) . "' aria-hidden='true'></span> ";
        };

        add_submenu_page('smv-dashboard', 'پیشخوان سماویه', $icon('dashboard') . 'پیشخوان', 'manage_smv_registrations', 'smv-dashboard', array(__CLASS__, 'dashboard'));
        add_submenu_page('smv-dashboard', 'ثبت‌نام‌ها و فیش‌ها', $icon('clipboard') . 'ثبت‌نام‌ها', 'manage_smv_registrations', 'smv-registrations', array(__CLASS__, 'registrations'));
        add_submenu_page('smv-dashboard', 'مدیریت تئاترها', $icon('tickets-alt') . 'تئاترها', 'manage_smv_registrations', 'smv-theatres', array(__CLASS__, 'theatres'));
        add_submenu_page('smv-dashboard', 'نظرات تأییدشده', $icon('format-status') . 'نظرات', 'manage_smv_registrations', 'smv-reviews', array(__CLASS__, 'reviews'));
        add_submenu_page('smv-dashboard', 'تنظیمات سماویه', $icon('admin-generic') . 'تنظیمات و اطلاعات تماس', 'manage_smv_registrations', 'smv-settings', array('SMV_Settings', 'render_page'));
        add_submenu_page('smv-dashboard', 'تنظیمات اینماد', $icon('shield') . 'تنظیمات اینماد', 'manage_smv_registrations', 'smv-enamad', array('SMV_Enamad', 'render_page'));
        add_submenu_page('smv-dashboard', 'پشتیبان‌گیری و بروزرسانی', $icon('backup') . 'پشتیبان‌گیری و بروزرسانی', 'manage_smv_registrations', 'smv-backup', array('SMV_Backup', 'render_page'));
    }

    /**
     * پیشخوان اصلی وردپرس را با پیشخوان سماویه یکی می‌کند:
     * همهٔ ویجت‌های پیش‌فرض حذف و پیشخوان اختصاصی افزونه به‌صورت تمام‌عرض نمایش داده می‌شود.
     */
    public static function setup_wp_dashboard() {
        if (!current_user_can('manage_smv_registrations')) {
            return;
        }
        if (apply_filters('smv_replace_wp_dashboard', true) === false) {
            return;
        }

        remove_action('welcome_panel', 'wp_welcome_panel');

        global $wp_meta_boxes;
        if (!isset($wp_meta_boxes['dashboard']) || !is_array($wp_meta_boxes['dashboard'])) {
            $wp_meta_boxes['dashboard'] = array();
        }
        foreach (array('normal', 'side', 'column3', 'column4', 'advanced') as $context) {
            $wp_meta_boxes['dashboard'][$context] = array();
        }

        add_meta_box(
            'smv-dashboard-home',
            'سماویه',
            array(__CLASS__, 'render_wp_dashboard_widget'),
            'dashboard',
            'normal',
            'high'
        );
    }

    /**
     * محتوای ویجت پیشخوان اصلی (همان پیشخوان افزونه).
     */
    public static function render_wp_dashboard_widget() {
        echo '<div class="smv-dashboard-widget">';
        self::dashboard();
        echo '</div>';
    }

    /**
     * آیکن اختصاصی منوی «سماویه» در پیشخوان (بلیت طلایی با ستاره).
     * SVG درون‌خطی به‌صورت data URI تا نیازی به فایل/فونت آیکن نباشد.
     */
    public static function menu_icon() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            . '<path fill="#c9a227" d="M3 6.5A2.5 2.5 0 0 1 5.5 4h13A2.5 2.5 0 0 1 21 6.5v3a2.5 2.5 0 0 0 0 5v3A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-3a2.5 2.5 0 0 0 0-5v-3Z"/>'
            . '<path fill="#123f3a" d="M12 8.2l1.1 2.3 2.5.4-1.8 1.8.4 2.5L12 14.1l-2.2 1.1.4-2.5-1.8-1.8 2.5-.4z"/>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function assets($hook) {
        $screen = get_current_screen();
        $screen_post_type = ($screen && isset($screen->post_type)) ? (string) $screen->post_type : '';
        $screen_id = $screen && isset($screen->id) ? (string) $screen->id : '';
        $allowed = $screen && (
            in_array($screen_post_type, array('smv_theatre', 'smv_project', 'smv_review', 'smv_showtime', 'smv_poll'), true) ||
            'dashboard' === $screen_id ||
            'index.php' === (string) $hook ||
            false !== strpos($screen_id, 'smv-dashboard') ||
            false !== strpos((string) $hook, 'smv-registrations') ||
            false !== strpos((string) $hook, 'smv-settings') ||
            false !== strpos((string) $hook, 'smv-enamad') ||
            false !== strpos((string) $hook, 'smv-backup') ||
            false !== strpos((string) $hook, 'smv-theatres')
        );
        if (!$allowed) {
            return;
        }
        wp_enqueue_style('smv-admin', SMV_URL . 'assets/css/admin.css', array(), SMV_VERSION);
        wp_enqueue_script('smv-admin', SMV_URL . 'assets/js/admin.js', array(), SMV_VERSION, true);
        wp_enqueue_media();
    }

    public static function create_pages() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_create_pages');

        $pages = array(
            'home' => array(
                'title' => 'صفحه اصلی',
                'slug' => 'home',
                'content' => '[samavieh_home]',
            ),
            'theatres' => array(
                'title' => 'تئاترها',
                'slug' => 'theatres',
                'content' => '[samavieh_theatres]',
            ),
            'projects' => array(
                'title' => 'پروژه‌ها',
                'slug' => 'projects',
                'content' => '[samavieh_projects]',
            ),
            'about' => array(
                'title' => 'درباره ما',
                'slug' => 'about',
                'content' => 'درباره گروه فرهنگی هنری سماوه',
            ),
            'contact' => array(
                'title' => 'تماس با ما',
                'slug' => 'contact',
                'content' => '[samavieh_contact]',
            ),
            'tracking' => array(
                'title' => 'پیگیری ثبت‌نام',
                'slug' => 'tracking',
                'content' => '[samavieh_tracking]',
            ),
        );

        foreach ($pages as $key => $page_data) {
            $option_key = 'smv_' . $key . '_page_id';
            $stored = absint(get_option($option_key));
            if ($stored && get_post_status($stored)) {
                continue; // قبلاً ساخته و تنظیم شده است
            }
            $existing = get_page_by_path($page_data['slug']);
            if ($existing && !is_wp_error($existing)) {
                update_option($option_key, (int) $existing->ID);
                continue;
            }
            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $page_data['slug'],
            ));
            if ($page_id && !is_wp_error($page_id)) {
                update_option($option_key, (int) $page_id);
            }
        }

        // اگر صفحهٔ اصلی سایت هنوز روی «آخرین نوشته‌ها» است، صفحهٔ اصلی ساخته‌شده تعیین شود.
        $front_page_id = absint(get_option('smv_home_page_id'));
        if ($front_page_id && 'page' !== get_option('show_on_front') && !get_option('page_on_front')) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $front_page_id);
        }

        wp_safe_redirect(add_query_arg('smv_setup', '1', admin_url('admin.php?page=smv-dashboard')));
        exit;
    }

    private static function count_status($status = '') {
        global $wpdb;
        $table = smv_table_name();
        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public static function dashboard() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        global $wpdb;
        $table = smv_table_name();
        $latest = $wpdb->get_results("SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id ORDER BY r.created_at DESC LIMIT 6");
        $elementor = did_action('elementor/loaded');
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <header class="smv-site-header">
                <div class="smv-site-brand">
                    <img src="<?php echo esc_url(SMV_URL . 'assets/images/samavieh-logo-gold.png'); ?>" alt="لوگو سماویه" />
                    <div class="smv-site-title"><strong>سماویه</strong><span>مدیریت تئاتر، ثبت‌نام و فعالیت‌های فرهنگی هنری</span></div>
                </div>
                <nav class="smv-site-nav">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-dashboard')); ?>">پیشخوان</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres')); ?>">تئاترها</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations')); ?>">ثبت‌نام‌ها</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-settings')); ?>">تنظیمات</a>
                </nav>
            </header>

            <?php if (isset($_GET['smv_setup'])) : ?><div class="notice notice-success is-dismissible"><p>صفحات پایه بررسی و صفحات لازم بدون تکرار ساخته شدند. منوی اصلی هم آماده است.</p></div><?php endif; ?>

            <div class="smv-stat-grid">
                <a class="smv-stat-card is-pending" href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations&status=pending')); ?>"><span>در انتظار بررسی</span><strong><?php echo esc_html(SMV_Persian::digits(self::count_status('pending'))); ?></strong><small>فیش و ثبت‌نام جدید</small></a>
                <a class="smv-stat-card is-approved" href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations&status=approved')); ?>"><span>ثبت‌نام تأییدشده</span><strong><?php echo esc_html(SMV_Persian::digits(self::count_status('approved'))); ?></strong><small>پس از بررسی فیش</small></a>
                <a class="smv-stat-card" href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations')); ?>"><span>همهٔ ثبت‌نام‌ها</span><strong><?php echo esc_html(SMV_Persian::digits(self::count_status())); ?></strong><small>جست‌وجو و مدیریت</small></a>
                <a class="smv-stat-card" href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres')); ?>"><span>تئاترهای منتشرشده</span><strong><?php echo esc_html(SMV_Persian::digits((int) wp_count_posts('smv_theatre')->publish)); ?></strong><small>همان فهرست بخش «تئاترها» — مدیریت اطلاعات اجرا</small></a>
            </div>

            <section class="smv-admin-card">
                <div class="smv-card-title-row"><div><h2><span class="dashicons dashicons-screenoptions"></span>کارهای اصلی</h2><p>چهار میان‌بر برای مدیریت روزانه سایت</p></div></div>
                <div class="smv-quick-grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres&action=add')); ?>"><span class="smv-quick-icon">＋</span><strong>افزودن تئاتر جدید</strong><small>جزئیات، سانس‌ها، بازیگران، پوستر و...</small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations&status=pending')); ?>"><span class="smv-quick-icon">✓</span><strong>بررسی فیش‌ها</strong><small>تأیید یا رد درخواست‌های در انتظار</small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-settings#smv-home-content')); ?>"><span class="smv-quick-icon">✎</span><strong>ویرایش صفحه اصلی</strong><small>تغییر عنوان و متن‌های خودکار بدون المنتور</small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smv-settings')); ?>"><span class="smv-quick-icon">⚙</span><strong>اطلاعات تماس</strong><small>تلفن، نشانی و شبکه‌های اجتماعی</small></a>
                </div>
            </section>

            <div class="smv-admin-two-col">
                <section class="smv-admin-card">
                    <div class="smv-card-title-row"><div><h2><span class="dashicons dashicons-list-view"></span>آخرین درخواست‌ها</h2><p>برای بررسی کامل، روی «مشاهده» بزنید.</p></div><a href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations')); ?>">مشاهده همه</a></div>
                    <?php if ($latest) : ?>
                        <div class="smv-latest-list">
                            <?php foreach ($latest as $row) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations&action=view&id=' . $row->id)); ?>"><span class="smv-avatar"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($row->first_name, 0, 1, 'UTF-8') : substr($row->first_name, 0, 1)); ?></span><span><strong><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></strong><small><?php echo esc_html($row->theatre_title); ?> • <?php echo esc_html(SMV_Persian::date($row->created_at)); ?></small></span><b class="smv-table-status status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(smv_status_label($row->status)); ?></b></a>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?><div class="smv-admin-empty"><span>✦</span><p>هنوز ثبت‌نامی دریافت نشده است.<br>بعد از نخستین ثبت‌نام، اطلاعات اینجا نمایش داده می‌شود.</p></div><?php endif; ?>
                </section>

                <section class="smv-admin-card smv-start-card">
                    <span class="smv-admin-kicker">راه‌اندازی اولیه</span><h2><span class="dashicons dashicons-admin-site-alt3"></span>ساخت خودکار صفحات سایت</h2><p>خانه، تئاترها، پروژه‌ها، درباره ما، تماس و پیگیری ثبت‌نام را با یک کلیک بسازید. صفحات موجود حذف یا تکرار نمی‌شوند.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('smv_create_pages'); ?><input type="hidden" name="action" value="smv_create_pages" /><button type="submit" class="button smv-primary-button">ساخت / تکمیل صفحات پایه</button></form>
                    <hr />
                    <h3>وضعیت ابزارها</h3>
                    <ul class="smv-system-list"><li><span class="<?php echo $elementor ? 'ok' : 'warning'; ?>"><?php echo $elementor ? '✓' : '!'; ?></span>المنتور: <?php echo $elementor ? 'فعال است' : 'فعال نیست'; ?></li><li><span class="<?php echo 'samavieh-elementor-child' === get_stylesheet() ? 'ok' : 'warning'; ?>"><?php echo 'samavieh-elementor-child' === get_stylesheet() ? '✓' : '!'; ?></span>قالب فرزند سماویه: <?php echo 'samavieh-elementor-child' === get_stylesheet() ? 'فعال است' : 'فعال نیست'; ?></li></ul>
                    <a class="smv-help-link" href="<?php echo esc_url(SMV_URL . 'readme.txt'); ?>" target="_blank" rel="noopener">مشاهدهٔ راهنمای کوتاه افزونه</a>
                </section>
            </div>
        </div>
        <?php
    }

    public static function theatres() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        $theatre_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ('add' === $action || 'edit' === $action) {
            self::theatre_editor($action, $theatre_id);
            return;
        }

        if ('save' === $action) {
            self::save_theatre();
            return;
        }

        if ('delete' === $action && $theatre_id) {
            self::delete_theatre_action($theatre_id);
            return;
        }

        // List theatres
        $theatres = get_posts(array(
            'post_type' => 'smv_theatre',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <?php if (isset($_GET['smv_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>تئاتر و سانس‌های آن با موفقیت ذخیره شد.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['smv_error'])) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['smv_error']))); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['smv_deleted'])) : ?>
                <div class="notice notice-success is-dismissible"><p>تئاتر و سانس‌های آن حذف شد.</p></div>
            <?php endif; ?>
            <div class="smv-admin-heading">
                <div>
                    <span class="smv-admin-kicker">مدیریت تئاترها</span>
                    <h1>تئاترها و اجراها</h1>
                    <p>تئاترها را بسازید، سانس‌ها و جزئیات اجرا را تعریف کنید و در سایت منتشر کنید.</p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres&action=add')); ?>" class="button smv-primary-button">افزودن تئاتر جدید</a>
            </div>

            <section class="smv-admin-card">
                <div class="smv-table-wrap smv-theatre-table">
                <table class="smv-registration-table widefat">
                    <thead>
                    <tr><th>پوستر</th><th>عنوان تئاتر</th><th>کارگردان</th><th>بازیگران</th><th>تاریخ شروع</th><th>تاریخ پایان</th><th>محل اجرا</th><th>هزینه</th><th>وضعیت</th><th>عملیات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($theatres as $t) : ?>
                        <tr>
                            <td data-label="پوستر">
                                <?php if (has_post_thumbnail($t->ID)) : ?>
                                    <img src="<?php echo esc_url(get_the_post_thumbnail_url($t->ID, 'thumbnail')); ?>" width="50" height="50" style="border-radius:4px; object-fit:cover;" />
                                <?php else : ?>
                                    <span style="font-size:24px;">🎭</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="عنوان"><?php echo esc_html($t->post_title); ?></td>
                            <td data-label="کارگردان"><?php echo esc_html(get_post_meta($t->ID, '_smv_director', true) ?: '-'); ?></td>
                            <td data-label="بازیگران"><?php echo esc_html(wp_trim_words(get_post_meta($t->ID, '_smv_cast', true) ?: '-', 8)); ?></td>
                            <td data-label="تاریخ شروع"><?php echo esc_html(SMV_Persian::date(get_post_meta($t->ID, '_smv_start_date', true))); ?></td>
                            <td data-label="تاریخ پایان"><?php echo esc_html(SMV_Persian::date(get_post_meta($t->ID, '_smv_end_date', true))); ?></td>
                            <td data-label="محل"><?php echo esc_html(get_post_meta($t->ID, '_smv_venue', true) ?: '-'); ?></td>
                            <td data-label="هزینه"><?php echo esc_html(SMV_Persian::money(get_post_meta($t->ID, '_smv_price', true))); ?></td>
                            <td data-label="وضعیت"><span class="smv-table-status status-<?php echo esc_attr(get_post_status($t->ID)); ?>"><?php echo esc_html(smv_post_status_label(get_post_status($t->ID))); ?></span></td>
                            <td data-label="عملیات">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres&action=edit&id=' . $t->ID)); ?>" class="button button-small">ویرایش</a>
                                <a href="<?php echo esc_url(get_permalink($t->ID)); ?>" class="button button-small" target="_blank">مشاهده</a>
                                <?php if (defined('ELEMENTOR_VERSION')) : ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $t->ID . '&action=elementor')); ?>" class="button button-small">ویرایش با المنتور</a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=smv_delete_theatre&id=' . $t->ID), 'smv_delete_theatre_' . $t->ID)); ?>" class="button button-small smv-danger-button" onclick="return confirm('آیا مطمئنید؟')">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if (empty($theatres)) : ?>
                    <div class="smv-admin-empty"><span>🎭</span><p>هنوز تئاتری ثبت نشده است.</p></div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public static function reviews() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        global $wpdb;
        $table = $wpdb->prefix . 'smv_reviews';
        $theatres = get_posts(array(
            'post_type' => 'smv_theatre',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        $theatre_id = isset($_GET['theatre_id']) ? absint($_GET['theatre_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where = array('1=1');
        $params = array();
        if ($theatre_id) {
            $where[] = 'theatre_id = %d';
            $params[] = $theatre_id;
        }
        if ($status && array_key_exists($status, smv_statuses())) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ($s) {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where[] = '(author LIKE %s OR comment LIKE %s)';
            $params = array_merge($params, array($like, $like));
        }
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <div class="smv-admin-heading">
                <div>
                    <span class="smv-admin-kicker">نظرات تاییدشده</span>
                    <h1>نظرات اجراها</h1>
                    <p>نظرات ثبت‌شده برای اجراها را بررسی و تایید یا رد کنید.</p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smv-reviews')); ?>" class="button smv-primary-button">بازنمایش همه</a>
            </div>

            <section class="smv-admin-card">
                <form method="get" class="smv-filter-bar">
                    <input type="hidden" name="page" value="smv-reviews" />
                    <select name="theatre_id"><option value="0">همهٔ اجراها</option><?php foreach ($theatres as $t) : ?><option value="<?php echo esc_attr($t->ID); ?>" <?php selected($theatre_id, $t->ID); ?>><?php echo esc_html($t->post_title); ?></option><?php endforeach; ?></select>
                    <select name="status"><option value="">همهٔ وضعیت‌ها</option><?php foreach (smv_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                    <input type="search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="نویسنده یا متن نظر…" />
                    <button class="button" type="submit">فیلتر و جست‌وجو</button>
                </form>
                <?php if ($rows) : ?>
                    <div class="smv-table-wrap">
                        <table class="smv-registration-table">
                            <thead>
                            <tr><th>نویسنده</th><th>متن نظر</th><th>امتیاز</th><th>اجرا</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row) : $theatre_title = get_the_title($row->theatre_id) ?: 'اجرا حذف شده'; ?>
                                <tr>
                                    <td data-label="نویسنده"><?php echo esc_html($row->author); ?></td>
                                    <td data-label="متن نظر"><?php echo esc_html(wp_trim_words($row->comment, 15)); ?></td>
                                    <td data-label="امتیاز"><?php echo esc_html(SMV_Persian::digits((int) $row->rating)); ?> ⭐</td>
                                    <td data-label="اجرا"><?php echo esc_html($theatre_title); ?></td>
                                    <td data-label="وضعیت"><span class="smv-table-status status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(smv_status_label($row->status)); ?></span></td>
                                    <td data-label="تاریخ"><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td>
                                    <td data-label="عملیات">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                            <?php wp_nonce_field('smv_update_review_' . $row->id); ?>
                                            <input type="hidden" name="action" value="smv_update_review" />
                                            <input type="hidden" name="review_id" value="<?php echo esc_attr($row->id); ?>" />
                                            <select name="status" onchange="this.form.submit()">
                                                <?php foreach (smv_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row->status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:6px;">
                                            <?php wp_nonce_field('smv_delete_review_' . $row->id); ?>
                                            <input type="hidden" name="action" value="smv_delete_review" />
                                            <input type="hidden" name="review_id" value="<?php echo esc_attr($row->id); ?>" />
                                            <button type="submit" class="button button-small smv-danger-button" onclick="return confirm('حذف نظر؟')">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="smv-admin-empty"><span>💬</span><p>نظری یافت نشد.</p></div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public static function theatre_editor($action, $theatre_id) {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }

        $is_edit = 'edit' === $action && $theatre_id;
        $theatre = null;
        $showtimes = array();
        $meta = array();

        if ($is_edit) {
            $theatre = get_post($theatre_id);
            if (!$theatre || 'smv_theatre' !== $theatre->post_type) {
                wp_die('تئاتر پیدا نشد.');
            }
            $meta = get_post_meta($theatre_id);
            $showtimes = smv_get_all_showtimes_for_theatre($theatre_id);
        }

        $page_title = $is_edit ? 'ویرایش تئاتر' : 'افزودن تئاتر جدید';
        $button_text = $is_edit ? 'بروزرسانی تئاتر' : 'انتشار تئاتر';
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <?php if (isset($_GET['smv_error'])) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['smv_error']))); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['smv_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>ذخیره شد.</strong> اطلاعات تئاتر و سانس‌ها با موفقیت به‌روزرسانی شد.</p></div>
            <?php endif; ?>
            <div class="smv-admin-heading">
                <div>
                    <span class="smv-admin-kicker"><?php echo esc_html($page_title); ?></span>
                    <h1><?php echo esc_html($page_title); ?></h1>
                    <p>تمام اطلاعات اجرا را در این صفحه وارد کنید. سانس‌ها، بازیگران، پوستر و روش‌های پرداخت را می‌توانید تعریف کنید.</p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres')); ?>" class="button button-link">بازگشت به لیست</a>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" id="smv-theatre-form" novalidate>
                <?php wp_nonce_field('smv_save_theatre'); ?>
                <?php wp_nonce_field('smv_delete_showtime', 'smv_delete_showtime_nonce'); ?>
                <input type="hidden" name="action" value="smv_save_theatre" />
                <?php if ($is_edit) : ?><input type="hidden" name="theatre_id" value="<?php echo esc_attr($theatre_id); ?>" /><?php endif; ?>

                <section class="smv-admin-card smv-theatre-editor">
                    <div class="smv-editor-tabs">
                        <button type="button" class="tab-btn active" data-tab="basic">اطلاعات پایه</button>
                        <button type="button" class="tab-btn" data-tab="cast">بازیگران و عوامل</button>
                        <button type="button" class="tab-btn" data-tab="media">مدیا و پوستر</button>
                        <button type="button" class="tab-btn" data-tab="showtimes">سانس‌ها و ظرفیت</button>
                        <button type="button" class="tab-btn" data-tab="payment">روش پرداخت</button>
                    </div>

                    <!-- Tab: Basic -->
                    <div class="tab-panel active" id="tab-basic">
                        <div class="smv-settings-grid">
                            <div class="smv-admin-field full">
                                <label for="smv-theatre-title"><strong>عنوان تئاتر <b>*</b></strong></label>
                                <input type="text" id="smv-theatre-title" name="theatre_title" value="<?php echo esc_attr($is_edit ? $theatre->post_title : ''); ?>" required />
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-theatre-excerpt"><strong>توضیح کوتاه</strong></label>
                                <textarea id="smv-theatre-excerpt" name="theatre_excerpt" rows="3"><?php echo esc_textarea($is_edit ? $theatre->post_excerpt : ''); ?></textarea>
                                <small>توضیح کوتاه که در لیست تئاترها نمایش داده می‌شود.</small>
                            </div>
                            <div class="smv-admin-field">
                                <label><strong>وضعیت اجرا</strong></label>
                                <select name="theatre_status">
                                    <option value="publish" <?php selected($is_edit ? $theatre->post_status : 'publish', 'publish'); ?>>منتشر شده</option>
                                    <option value="draft" <?php selected($is_edit ? $theatre->post_status : '', 'draft'); ?>>پیش‌نویس</option>
                                    <option value="private" <?php selected($is_edit ? $theatre->post_status : '', 'private'); ?>>خفیف</option>
                                </select>
                            </div>
                            <div class="smv-admin-field">
                                <label><strong>ثبت‌نام فعال است؟</strong></label>
                                <select name="registration_status">
                                    <option value="open" <?php selected((isset($meta['_smv_registration_status'][0]) ? $meta['_smv_registration_status'][0] : ''), 'open'); ?>>باز</option>
                                    <option value="closed" <?php selected((isset($meta['_smv_registration_status'][0]) ? $meta['_smv_registration_status'][0] : ''), 'closed'); ?>>بسته</option>
                                </select>
                            </div>
<div class="smv-admin-field">
                                <label for="smv-start-date"><strong>تاریخ شروع اجرا</strong><small>تاریخ شمسی، مثل ۱۴۰۵/۰۲/۱۲</small></label>
                                <input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" id="smv-start-date" name="start_date" value="<?php echo esc_attr(SMV_Persian::date_input_value(isset($meta['_smv_start_date'][0]) ? $meta['_smv_start_date'][0] : '')); ?>" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" />
                            </div>
<div class="smv-admin-field">
                                <label for="smv-end-date"><strong>تاریخ پایان اجرا</strong><small>تاریخ شمسی، مثل ۱۴۰۵/۰۲/۱۲</small></label>
                                <input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" id="smv-end-date" name="end_date" value="<?php echo esc_attr(SMV_Persian::date_input_value(isset($meta['_smv_end_date'][0]) ? $meta['_smv_end_date'][0] : '')); ?>" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" />
                            </div>
                            <div class="smv-admin-field">
                                <label for="smv-venue"><strong>محل اجرا</strong></label>
                                <input type="text" id="smv-venue" name="venue" value="<?php echo esc_attr(isset($meta['_smv_venue'][0]) ? $meta['_smv_venue'][0] : ''); ?>" placeholder="مثلاً سالن اصلی" />
                            </div>
                            <div class="smv-admin-field">
                                <label for="smv-price"><strong>هزینه ثابت اجرا (تومان)</strong></label>
                                <input type="number" id="smv-price" name="price" min="0" value="<?php echo esc_attr(isset($meta['_smv_price'][0]) ? $meta['_smv_price'][0] : ''); ?>" placeholder="اگر 0 باشد، رایگان است" />
                            </div>
<div class="smv-admin-field">
                                <label for="smv-registration-deadline"><strong>مهلت ثبت‌نام</strong><small>تاریخ شمسی، مثل ۱۴۰۵/۰۲/۱۲</small></label>
                                <input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" id="smv-registration-deadline" name="registration_deadline" value="<?php echo esc_attr(SMV_Persian::date_input_value(isset($meta['_smv_registration_deadline'][0]) ? $meta['_smv_registration_deadline'][0] : '')); ?>" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" />
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-theatre-content"><strong>توضیحات کامل اجرا</strong></label>
                                <textarea id="smv-theatre-content" name="theatre_content" rows="6"><?php echo esc_textarea($is_edit ? $theatre->post_content : ''); ?></textarea>
                                <small>توضیحات کامل که در صفحه جزئیات اجرا نمایش داده می‌شود.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Cast & Crew -->
                    <div class="tab-panel" id="tab-cast">
                        <div class="smv-settings-grid">
                            <div class="smv-admin-field full">
                                <label for="smv-director"><strong>کارگردان <b>*</b></strong></label>
                                <input type="text" id="smv-director" name="director" value="<?php echo esc_attr(isset($meta['_smv_director'][0]) ? $meta['_smv_director'][0] : ''); ?>" required placeholder="نام کارگردان" />
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-writer"><strong>نویسنده</strong></label>
                                <input type="text" id="smv-writer" name="writer" value="<?php echo esc_attr(isset($meta['_smv_writer'][0]) ? $meta['_smv_writer'][0] : ''); ?>" placeholder="نام نویسنده" />
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-cast"><strong>بازیگران (مهمانان)</strong></label>
                                <input type="text" id="smv-cast" name="cast" value="<?php echo esc_attr(isset($meta['_smv_cast'][0]) ? $meta['_smv_cast'][0] : ''); ?>" placeholder="نام بازیگران را با کاما جداگذار کنید" />
                                <small>مثال: علیرضا محمدی، ترانه احمدی</small>
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-age-range"><strong>ردهٔ سنی</strong></label>
                                <input type="text" id="smv-age-range" name="age_range" value="<?php echo esc_attr(isset($meta['_smv_age_range'][0]) ? $meta['_smv_age_range'][0] : ''); ?>" placeholder="مثلاً ۱۲ سال و بالاتر" />
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-duration"><strong>مدت اجرا</strong></label>
                                <input type="text" id="smv-duration" name="duration" value="<?php echo esc_attr(isset($meta['_smv_duration'][0]) ? $meta['_smv_duration'][0] : ''); ?>" placeholder="مثلاً ۹۰ دقیقه" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Media -->
                    <div class="tab-panel" id="tab-media">
                        <div class="smv-media-section">
                            <div class="smv-admin-field full">
                                <label><strong>پوستر اصلی تئاتر <b>*</b></strong></label>
                                <div class="smv-image-upload" id="smv-poster-upload">
                                    <?php if ($is_edit && has_post_thumbnail($theatre_id)) : ?>
                                        <img src="<?php echo esc_url(get_the_post_thumbnail_url($theatre_id, 'medium')); ?>" id="smv-poster-preview" style="max-width:200px; border-radius:8px; margin-bottom:10px;" />
                                    <?php endif; ?>
                                    <input type="hidden" name="poster_id" id="smv-poster-id" value="<?php echo esc_attr($is_edit ? get_post_thumbnail_id($theatre_id) : ''); ?>" />
                                    <button type="button" class="button smv-choose-image" id="smv-choose-poster">انتخاب پوستر</button>
                                    <button type="button" class="button smv-remove-image" id="smv-remove-poster" style="display:none;">حذف پوستر</button>
                                </div>
                                <small>تصویر اصلی تئاتر که در هدر و لیست نمایش داده می‌شود.</small>
                            </div>

                            <div class="smv-admin-field full">
                                <label><strong>عکس‌های اجرا (گالری)</strong></label>
                                <div class="smv-image-gallery" id="smv-gallery-upload">
                                    <?php
                                    $gallery_ids = isset($meta['_smv_gallery'][0]) ? maybe_unserialize($meta['_smv_gallery'][0]) : array();
                                    if (!empty($gallery_ids)) :
                                        foreach ($gallery_ids as $gid) : $img = wp_get_attachment_image_src($gid, 'thumbnail'); ?>
                                            <div class="smv-gallery-item">
                                                <img src="<?php echo esc_url($img[0]); ?>" width="80" height="80" style="border-radius:6px; object-fit:cover;" />
                                                <input type="hidden" name="gallery_ids[]" value="<?php echo esc_attr($gid); ?>" />
                                                <button type="button" class="smv-gallery-remove">×</button>
                                            </div>
                                        <?php endforeach;
                                    endif; ?>
                                    <button type="button" class="button smv-choose-image" id="smv-choose-gallery">افزودن عکس به گالری</button>
                                </div>
                                <input type="hidden" name="gallery_ids_json" id="smv-gallery-ids-json" value="<?php echo esc_attr(wp_json_encode($gallery_ids)); ?>" />
                                <small>عکس‌های پشت صحنه، اجرا و پوسترها</small>
                            </div>

                            <div class="smv-admin-field full">
                                <label><strong>موشن گرافیک / ویدیو تریلر</strong></label>
                                <div class="smv-video-upload" id="smv-video-upload">
                                    <?php if (isset($meta['_smv_video_id'][0]) && $meta['_smv_video_id'][0]) : ?>
                                        <?php $video_url = wp_get_attachment_url($meta['_smv_video_id'][0]); ?>
                                        <div class="smv-video-preview">
                                            <a href="<?php echo esc_url($video_url); ?>" target="_blank">مشاهده ویدیو فعلی</a>
                                            <button type="button" class="smv-remove-image" id="smv-remove-video">حذف ویدیو</button>
                                        </div>
                                        <input type="hidden" name="video_id" value="<?php echo esc_attr($meta['_smv_video_id'][0]); ?>" />
                                        <input type="hidden" name="video_url" value="<?php echo esc_attr($video_url); ?>" />
                                    <?php else : ?>
                                        <input type="text" name="video_url" id="smv-video-url" placeholder="یا لینک آپارات/یوتیوب وارد کنید" />
                                        <button type="button" class="button smv-choose-image" id="smv-choose-video">آپلود فایل ویدیو</button>
                                    <?php endif; ?>
                                </div>
                                <small>فایل ویدیو را آپلود کنید یا لینک آپارات/یوتیوب وارد کنید.</small>
                            </div>

                            <div class="smv-admin-field full">
                                <label for="smv-extra-videos"><strong>ویدیوهای بیشتر (اختیاری)</strong></label>
                                <?php $extra_videos = isset($meta['_smv_videos'][0]) ? maybe_unserialize($meta['_smv_videos'][0]) : array(); $extra_videos_text = is_array($extra_videos) ? implode("\n", $extra_videos) : (string) $extra_videos; ?>
                                <textarea id="smv-extra-videos" name="extra_videos" rows="3" dir="ltr" placeholder="https://www.aparat.com/v/...&#10;https://example.com/teaser.mp4"><?php echo esc_textarea($extra_videos_text); ?></textarea>
                                <small>هر لینک ویدیو (آپارات، یوتیوب یا فایل MP4) را در یک خط بنویسید. همهٔ ویدیوها و عکس‌های گالری در بخش «مدیا و ویدیوهای اجرا» در صفحهٔ تئاتر — زیر پوستر — حتماً نمایش داده می‌شوند.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Showtimes -->
                    <div class="tab-panel" id="tab-showtimes">
                        <div class="smv-showtime-toolbar">
                            <div class="smv-showtime-toolbar-text">
                                <h3><span class="dashicons dashicons-calendar-alt"></span> سانس‌ها و ظرفیت</h3>
                                <p>برای هر اجرا می‌توانید چند سانس با تاریخ، ساعت، ظرفیت و قیمت جداگانه تعریف کنید. تغییرات با دکمهٔ «<?php echo esc_html($button_text); ?>» ذخیره می‌شود. <strong>سانس‌ها روزبه‌روز پر می‌شوند:</strong> داخل هر روز، سانس با «اولویت» کمتر (۱) اول پر می‌شود و بعد از تکمیل ظرفیتش، سانس بعدیِ همان روز فعال می‌شود؛ روزهای مختلف مستقل‌اند. اگر اولویت خالی بماند، ترتیب ساعت شروع ملاک است.</p>
                                <div class="smv-admin-field"><label for="smv-fill-mode"><strong>نحوهٔ تکمیل سانس‌ها</strong></label><select id="smv-fill-mode" name="showtime_fill_mode"><option value="ordered" <?php selected((isset($meta['_smv_showtime_fill_mode'][0]) ? $meta['_smv_showtime_fill_mode'][0] : 'ordered'), 'ordered'); ?>>ترتیبی — روزبه‌روز بر اساس اولویت (اول سانس اول هر روز)</option><option value="free" <?php selected((isset($meta['_smv_showtime_fill_mode'][0]) ? $meta['_smv_showtime_fill_mode'][0] : ''), 'free'); ?>>آزاد و همزمان — همهٔ سانس‌های دارای ظرفیت همزمان قابل رزرو (مثلاً دو سانس با هم)</option></select><small>در حالت «آزاد»، اولویت‌بندی کنار می‌رود و مخاطب هر سانسی را که بخواهد انتخاب می‌کند؛ سانس فقط وقتی بسته می‌شود که ظرفیت خودش تکمیل شود.</small></div>
                            </div>
                            <button type="button" class="button smv-primary-button smv-add-showtime" id="smv-add-showtime">
                                <span class="dashicons dashicons-plus-alt2"></span> افزودن سانس جدید
                            </button>
                        </div>

                        <div class="smv-inline-alert" id="smv-showtime-alert" role="alert" hidden></div>

                        <div id="smv-showtimes-list" class="smv-showtime-list">
                            <?php $st_index = 0; ?>
                            <?php foreach ($showtimes as $st) : ?>
                                <?php
                                $st_index++;
                                $st_id = (int) $st->ID;
                                $st_date = get_post_meta($st_id, '_smv_showtime_date', true);
                                $st_time = get_post_meta($st_id, '_smv_showtime_time', true);
                                $st_time_end = get_post_meta($st_id, '_smv_showtime_time_end', true);
                                $st_capacity = absint(get_post_meta($st_id, '_smv_capacity', true));
                                $st_price = absint(get_post_meta($st_id, '_smv_showtime_price', true));
                                $st_venue = get_post_meta($st_id, '_smv_showtime_venue', true);
                                $st_booked = smv_showtime_booked_count($st_id);
                                $st_status = get_post_status($st_id);
                                ?>
                                <div class="smv-showtime-item" data-id="<?php echo esc_attr($st_id); ?>" data-mode="existing">
                                    <div class="smv-showtime-item-head">
                                        <span class="smv-showtime-num"><?php echo esc_html(SMV_Persian::digits($st_index)); ?></span>
                                        <span class="smv-showtime-title">سانس <?php echo esc_html(SMV_Persian::date($st_date)); ?><?php echo ($st_time || $st_time_end) ? ' — ' . esc_html(SMV_Persian::time_range($st_time, $st_time_end)) : ''; ?></span>
                                        <span class="smv-showtime-capacity">رزرو‌شده: <?php echo esc_html(SMV_Persian::digits($st_booked)); ?><?php echo $st_capacity ? ' از ' . esc_html(SMV_Persian::digits($st_capacity)) : ''; ?></span>
                                        <?php if ('publish' !== $st_status) : ?><span class="smv-showtime-status"><?php echo esc_html(smv_post_status_label($st_status)); ?></span><?php endif; ?>
                                        <button type="button" class="smv-remove-showtime" data-id="<?php echo esc_attr($st_id); ?>" data-mode="existing" title="حذف این سانس">
                                            <span class="dashicons dashicons-trash"></span> حذف
                                        </button>
                                    </div>
                                    <div class="smv-showtime-grid">
                                        <label>تاریخ اجرا (شمسی) <input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" name="showtimes[<?php echo esc_attr($st_id); ?>][date]" value="<?php echo esc_attr(SMV_Persian::date_input_value($st_date)); ?>" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" /></label>
                                        <label>ساعت شروع <input type="time" step="60" name="showtimes[<?php echo esc_attr($st_id); ?>][time]" value="<?php echo esc_attr($st_time); ?>" /></label>
                                        <label>ساعت پایان <input type="time" step="60" name="showtimes[<?php echo esc_attr($st_id); ?>][time_end]" value="<?php echo esc_attr($st_time_end); ?>" /></label>
                                        <label>اولویت در روز (۱=اول پر می‌شود) <input type="number" min="1" step="1" name="showtimes[<?php echo esc_attr($st_id); ?>][priority]" value="<?php echo absint(get_post_meta($st_id, '_smv_priority', true)) ? esc_attr(absint(get_post_meta($st_id, '_smv_priority', true))) : ''; ?>" placeholder="خودکار (ساعت)" /></label>
                                        <label>ظرفیت <input type="number" min="0" step="1" name="showtimes[<?php echo esc_attr($st_id); ?>][capacity]" value="<?php echo $st_capacity ? esc_attr($st_capacity) : ''; ?>" placeholder="نامحدود" /></label>
                                        <label>قیمت (تومان) <input type="number" min="0" step="1000" name="showtimes[<?php echo esc_attr($st_id); ?>][price]" value="<?php echo $st_price ? esc_attr($st_price) : ''; ?>" placeholder="رایگان" /></label>
                                        <label class="smv-showtime-venue">محل اجرا <input type="text" name="showtimes[<?php echo esc_attr($st_id); ?>][venue]" value="<?php echo esc_attr($st_venue); ?>" placeholder="مثلاً سالن اصلی" /></label>
                                    </div>
                                    <input type="hidden" name="showtimes[<?php echo esc_attr($st_id); ?>][id]" value="<?php echo esc_attr($st_id); ?>" />
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="smv-showtime-empty" id="smv-showtimes-empty" <?php echo $showtimes ? 'hidden' : ''; ?>>
                            <span class="dashicons dashicons-calendar"></span>
                            <p>هنوز سانسی برای این اجرا ثبت نشده است.<br><small>با دکمهٔ «افزودن سانس جدید» اولین سانس را اضافه کنید. اگر سانسی تعریف نشود، ثبت‌نام در سطح کل اجرا انجام می‌شود.</small></p>
                        </div>

                        <p class="smv-showtime-hint">
                            <span class="dashicons dashicons-info-outline"></span>
                            سانس‌هایی که حذف می‌کنید پس از زدن دکمهٔ «<?php echo esc_html($button_text); ?>» برای همیشه پاک می‌شوند. سانس دارای ثبت‌نام را فقط وقتی حذف کنید که مطمئن هستید.
                        </p>
                    </div>

                    <!-- Tab: Payment -->
                    <div class="tab-panel" id="tab-payment">
                        <div class="smv-settings-grid">
                            <div class="smv-admin-field full">
                                <label><strong>روش‌های پرداخت فعال</strong></label>
                                <div class="smv-payment-options">
                                    <label><input type="checkbox" name="payment_methods[]" value="free" <?php checked((isset($meta['_smv_payment_free'][0]) ? $meta['_smv_payment_free'][0] : ''), '1'); ?>> رایگان</label>
                                    <label><input type="checkbox" name="payment_methods[]" value="financial_aid" <?php checked((isset($meta['_smv_payment_financial_aid'][0]) ? $meta['_smv_payment_financial_aid'][0] : ''), '1'); ?>> کمک مالی (مبلغ به انتخاب شخص)</label>
                                    <label><input type="checkbox" name="payment_methods[]" value="paid" <?php checked((isset($meta['_smv_payment_paid'][0]) ? $meta['_smv_payment_paid'][0] : ''), '1'); ?>> هزینهٔ ثبت‌نام (مبلغ مشخص — از قیمت اجرا/سانس)</label>
                                </div>
                                <small>روش‌هایی که کاربران در فرم ثبت‌نام می‌توانند انتخاب کنند.</small>
                            </div>
                            <div class="smv-admin-field full">
                                <label for="smv-payment-instructions"><strong>راهنمای واریز (کارت به کارت)</strong></label>
                                <textarea id="smv-payment-instructions" name="payment_instructions" rows="4"><?php echo esc_textarea(smv_get_option('payment_instructions')); ?></textarea>
                                <small>شماره کارت، شماره حساب و توضیح لازم برای واریز.</small>
                            </div>
                            <div class="smv-admin-field">
                                <label for="smv-zarinpal-merchant"><strong>کد مرچنت زرین‌پال</strong></label>
                                <input type="text" id="smv-zarinpal-merchant" name="zarinpal_merchant" value="<?php echo esc_attr(smv_get_option('zarinpal_merchant')); ?>" placeholder="مرچنت زرین‌پال" />
                            </div>
                        </div>
                    </div>

                </section>

                <div class="smv-submit-bar">
                    <div class="smv-submit-bar-info">
                        <span class="dashicons dashicons-saved"></span>
                        <span>پس از پایان ویرایش، تغییرات را ذخیره کنید.</span>
                    </div>
                    <div class="smv-submit-bar-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=smv-theatres')); ?>" class="button">بازگشت به لیست</a>
                        <button type="submit" class="button button-primary button-large smv-button-primary" id="smv-save-theatre"><?php echo esc_html($button_text); ?></button>
                    </div>
                </div>
            </form>

            <script type="text/html" id="smv-showtime-template">
                <div class="smv-showtime-item is-new" data-mode="new">
                    <div class="smv-showtime-item-head">
                        <span class="smv-showtime-num">۰</span>
                        <span class="smv-showtime-title">سانس جدید</span>
                        <span class="smv-showtime-badge-new">ذخیره‌نشده</span>
                        <button type="button" class="smv-remove-showtime" data-mode="new" title="حذف این سانس">
                            <span class="dashicons dashicons-trash"></span> حذف
                        </button>
                    </div>
                    <div class="smv-showtime-grid">
                        <label>تاریخ اجرا (شمسی) <input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" name="new_showtime[__INDEX__][date]" value="" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" /></label>
                        <label>ساعت شروع <input type="time" step="60" name="new_showtime[__INDEX__][time]" value="" /></label>
                        <label>ساعت پایان <input type="time" step="60" name="new_showtime[__INDEX__][time_end]" value="" /></label>
                        <label>اولویت در روز (۱=اول پر می‌شود) <input type="number" min="1" step="1" name="new_showtime[__INDEX__][priority]" value="" placeholder="خودکار (ساعت)" /></label>
                        <label>ظرفیت <input type="number" min="0" step="1" name="new_showtime[__INDEX__][capacity]" value="" placeholder="نامحدود" /></label>
                        <label>قیمت (تومان) <input type="number" min="0" step="1000" name="new_showtime[__INDEX__][price]" value="" placeholder="رایگان" /></label>
                        <label class="smv-showtime-venue">محل اجرا <input type="text" name="new_showtime[__INDEX__][venue]" value="" placeholder="مثلاً سالن اصلی" /></label>
                    </div>
                </div>
            </script>

            <script>
            (function () {
                if (!window.jQuery) { return; }
                var $ = window.jQuery;

                function smvNotice($box, message, type) {
                    if (!$box || !$box.length) { window.alert(message); return; }
                    $box.removeAttr('hidden').removeClass('is-error is-success').addClass('is-' + (type || 'error')).text(message);
                    if ($box[0] && $box[0].scrollIntoView) { $box[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }

                $(function () {
                    /* ---------- تب‌ها ---------- */
                    try {
                        $(document).on('click', '.tab-btn', function (e) {
                            e.preventDefault();
                            var tab = $(this).data('tab');
                            $('.tab-btn').removeClass('active');
                            $(this).addClass('active');
                            $('.tab-panel').removeClass('active');
                            $('#tab-' + tab).addClass('active');
                        });
                    } catch (err) { if (window.console) { console.error(err); } }

                    /* ---------- پوستر ---------- */
                    try {
                        var posterFrame;
                        $('#smv-choose-poster').on('click', function (e) {
                            e.preventDefault();
                            if (!window.wp || !wp.media) { window.alert('کتابخانهٔ رسانهٔ وردپرس بارگذاری نشده است. صفحه را دوباره بارگذاری کنید.'); return; }
                            if (posterFrame) { posterFrame.open(); return; }
                            posterFrame = wp.media({ title: 'انتخاب پوستر', button: { text: 'استفاده از این تصویر' }, multiple: false });
                            posterFrame.on('select', function () {
                                var attachment = posterFrame.state().get('selection').first().toJSON();
                                $('#smv-poster-id').val(attachment.id);
                                $('#smv-poster-preview').remove();
                                $('#smv-poster-upload').prepend('<img id="smv-poster-preview" src="' + attachment.url + '" alt="" />');
                                $('#smv-remove-poster').show();
                            });
                            posterFrame.open();
                        });
                        $('#smv-remove-poster').on('click', function () {
                            $('#smv-poster-id').val('');
                            $('#smv-poster-preview').remove();
                            $(this).hide();
                        });
                    } catch (err) { if (window.console) { console.error(err); } }

                    /* ---------- گالری ---------- */
                    try {
                        var galleryFrame;
                        var galleryIds = <?php echo wp_json_encode(array_values(array_map('intval', (array) (isset($gallery_ids) ? $gallery_ids : array())))); ?>;
                        var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

                        function galleryItemHtml(id, url) {
                            return '<div class="smv-gallery-item" data-id="' + id + '">' +
                                '<img src="' + url + '" alt="" />' +
                                '<input type="hidden" name="gallery_ids[]" value="' + id + '" />' +
                                '<button type="button" class="smv-gallery-remove" data-id="' + id + '">×</button>' +
                                '</div>';
                        }
                        function refreshGallery() {
                            $('#smv-gallery-upload .smv-gallery-item').remove();
                            $.each(galleryIds, function (i, gid) {
                                $.post(ajaxUrl, { action: 'smv_get_attachment_url', attachment_id: gid },function (resp) {
                                    if (resp && resp.success) {
                                        $('#smv-gallery-upload').prepend(galleryItemHtml(gid, resp.data.url));
                                    }
                                });
                            });
                            $('#smv-gallery-ids-json').val(JSON.stringify(galleryIds));
                        }
                        $('#smv-choose-gallery').on('click', function (e) {
                            e.preventDefault();
                            if (!window.wp || !wp.media) { window.alert('کتابخانهٔ رسانهٔ وردپرس بارگذاری نشده است.'); return; }
                            if (galleryFrame) { galleryFrame.open(); return; }
                            galleryFrame = wp.media({ title: 'انتخاب عکس‌ها', button: { text: 'افزودن به گالری' }, multiple: 'add' });
                            galleryFrame.on('select', function () {
                                galleryFrame.state().get('selection').each(function (attachment) {
                                    var id = parseInt(attachment.id, 10);
                                    if ($.inArray(id, galleryIds) === -1) { galleryIds.push(id); }
                                });
                                refreshGallery();
                            });
                            galleryFrame.open();
                        });
                        $(document).on('click', '.smv-gallery-remove', function (e) {
                            e.preventDefault();
                            var id = parseInt($(this).data('id'), 10);
                            galleryIds = $.grep(galleryIds, function (val) { return Number(val) !== id; });
                            $(this).closest('.smv-gallery-item').remove();
                            $('#smv-gallery-ids-json').val(JSON.stringify(galleryIds));
                        });
                    } catch (err) { if (window.console) { console.error(err); } }

                    /* ---------- ویدیو ---------- */
                    try {
                        var videoFrame;
                        $(document).on('click', '#smv-choose-video', function (e) {
                            e.preventDefault();
                            if (!window.wp || !wp.media) { window.alert('کتابخانهٔ رسانهٔ وردپرس بارگذاری نشده است.'); return; }
                            if (videoFrame) { videoFrame.open(); return; }
                            videoFrame = wp.media({ title: 'انتخاب ویدیو', button: { text: 'استفاده از این ویدیو' }, multiple: false, library: { type: 'video' } });
                            videoFrame.on('select', function () {
                                var attachment = videoFrame.state().get('selection').first().toJSON();
                                $('#smv-video-upload').html(
                                    '<input type="hidden" id="smv-video-id" name="video_id" value="' + attachment.id + '" />' +
                                    '<input type="hidden" id="smv-video-url" name="video_url" value="' + attachment.url + '" />' +
                                    '<div class="smv-video-preview"><a href="' + attachment.url + '" target="_blank" rel="noopener">مشاهده ویدیو</a> ' +
                                    '<button type="button" class="smv-remove-image" id="smv-remove-video">حذف ویدیو</button></div>'
                                );
                            });
                            videoFrame.open();
                        });
                        $(document).on('click', '#smv-remove-video', function (e) {
                            e.preventDefault();
                            $('#smv-video-upload').html(
                                '<input type="text" id="smv-video-url" name="video_url" value="" placeholder="یا لینک آپارات/یوتیوب وارد کنید" /> ' +
                                '<button type="button" class="button smv-choose-image" id="smv-choose-video">آپلود فایل ویدیو</button>'
                            );
                        });
                    } catch (err) { if (window.console) { console.error(err); } }

                    /* ---------- سانس‌ها ---------- */
                    try {
                        var $list = $('#smv-showtimes-list');
                        var $template = $('#smv-showtime-template');
                        var $alert = $('#smv-showtime-alert');
                        var counter = 0;

                        function updateEmptyState() {
                            var count = $list.children('.smv-showtime-item').length;
                            $('#smv-showtimes-empty').prop('hidden', count > 0);
                            $('.smv-showtime-toolbar .smv-showtime-counter').remove();
                            if (count > 0) {
                                $('.smv-showtime-toolbar-text h3').append(' <span class="smv-showtime-counter">' + count + '</span>');
                            }
                        }

                        function renumber() {
                            $list.children('.smv-showtime-item').each(function (i) {
                                $(this).find('.smv-showtime-num').first().text(Number(i + 1).toLocaleString('fa-IR'));
                            });
                        }

                        $('#smv-add-showtime').on('click', function (e) {
                            e.preventDefault();
                            if (!$template.length) { smvNotice($alert, 'قالب سانس پیدا نشد. صفحه را دوباره بارگذاری کنید.'); return; }
                            counter += 1;
                            var html = $template.html().replace(/__INDEX__/g, 'n' + counter + '_' + Date.now());
                            var $row = $(html);
                            $list.append($row);
                            renumber();
                            updateEmptyState();
                            if ($alert.length) { $alert.attr('hidden', 'hidden'); }
                            var $date = $row.find('input.smv-date-input').first();
                            $date.trigger('focus');
                            if ($row[0] && $row[0].scrollIntoView) { $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                        });

                        $list.on('click', '.smv-remove-showtime', function (e) {
                            e.preventDefault();
                            var $row = $(this).closest('.smv-showtime-item');
                            var mode = $row.data('mode');
                            var id = parseInt($row.attr('data-id') || 0, 10);
                            var question = (mode === 'existing')
                                ? 'این سانس پس از ذخیره برای همیشه حذف می‌شود. مطمئن هستید؟'
                                : 'این سانس ذخیره‌نشده حذف شود؟';
                            if (!window.confirm(question)) { return; }
                            if (mode === 'existing' && id) {
                                // حذف نهایی هنگام ذخیرهٔ فرم انجام می‌شود (بدون وابستگی به آجاکس)
                                $('<input type="hidden" name="delete_showtimes[]" />').val(id).appendTo('#smv-theatre-form');
                            }
                            // حذف فوری از DOM تا اگر کاربر بلافاصله ذخیره کند، دادهٔ حذف‌شده ارسال نشود.
                            $row.find('input, select, textarea').prop('disabled', true);
                            $row.remove();
                            renumber();
                            updateEmptyState();
                        });

                        updateEmptyState();

                        // نرمال‌سازی تاریخ شمسی هنگام خروج از فیلد
                        $(document).on('blur', 'input.smv-date-input', function () {
                            var value = $.trim(String($(this).val() || ''));
                            if (!value) { return; }
                            var latin = value.replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
                                             .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); })
                                             .replace(/[.\-]/g, '/');
                            var m = latin.match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
                            if (m) {
                                $(this).val(m[1] + '/' + ('0' + m[2]).slice(-2) + '/' + ('0' + m[3]).slice(-2));
                            }
                        });

                        /* ---------- اعتبارسنجی پیش از ذخیره ---------- */
                        $('#smv-theatre-form').on('submit', function (e) {
                            var errors = [];
                            var $title = $('#smv-theatre-title');
                            if ($.trim($title.val()) === '') {
                                errors.push('عنوان تئاتر را وارد کنید.');
                                if ($alert.length) { $alert.attr('hidden', 'hidden'); }
                                $('.tab-btn[data-tab="basic"]').trigger('click');
                                $title.trigger('focus');
                                window.alert(errors[0]);
                                e.preventDefault();
                                return false;
                            }

                            function latinDigits(value) {
                                return String(value || '').replace(/[۰-۹]/g, function (d) {
                                    return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
                                }).replace(/[٠-٩]/g, function (d) {
                                    return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d));
                                });
                            }
                            function validDate(value) {
                                var v = $.trim(latinDigits(value)).replace(/[.\-]/g, '/');
                                return /^\d{4}\/\d{1,2}\/\d{1,2}$/.test(v);
                            }

                            $list.children('.smv-showtime-item').each(function (i) {
                                var $row = $(this);
                                var mode = $row.data('mode');
                                var date = $.trim($row.find('input[name$="[date]"]').val() || '');
                                var time = $.trim($row.find('input[name$="[time]"]').val() || '');
                                var timeEnd = $.trim($row.find('input[name$="[time_end]"]').val() || '');
                                var capacity = $.trim($row.find('input[name$="[capacity]"]').val() || '');
                                var price = $.trim($row.find('input[name$="[price]"]').val() || '');
                                var venue = $.trim($row.find('input[name$="[venue]"]').val() || '');
                                var label = 'سانس ' + (i + 1);
                                var filled = !!(time || timeEnd || venue || capacity !== '' || price !== '');

                                // فقط سانس‌های تازه‌اضافه‌شده اجباری به تاریخ دارند؛ سانس‌های قبلی
                                // نباید جلوی ذخیرهٔ کل تئاتر را بگیرند (دادهٔ قدیمی/ناقص).
                                if (mode === 'new' && filled && !date) {
                                    errors.push(label + ': تاریخ اجرا را وارد کنید (یا این ردیف را حذف کنید).');
                                }
                                if (date && !validDate(date)) {
                                    errors.push(label + ': تاریخ باید شمسی و به شکل ۱۴۰۵/۰۲/۱۲ باشد.');
                                }
                                if (mode === 'existing' && !date && (time || timeEnd)) {
                                    errors.push(label + ': برای ساعتی که وارد کرده‌اید، تاریخ اجرا را هم مشخص کنید.');
                                }
                                if (timeEnd && !time) {
                                    errors.push(label + ': ساعت شروع را هم وارد کنید.');
                                }
                                if (time && timeEnd && timeEnd <= time) {
                                    errors.push(label + ': ساعت پایان باید بعد از ساعت شروع باشد.');
                                }
                                if (capacity !== '' && (!/^\d+$/.test(capacity) || parseInt(capacity, 10) < 0)) {
                                    errors.push(label + ': ظرفیت باید عدد صحیح باشد.');
                                }
                                if (price !== '' && !/^\d+$/.test(price)) {
                                    errors.push(label + ': قیمت باید عدد باشد.');
                                }
                            });

                            if (errors.length) {
                                e.preventDefault();
                                $('.tab-btn[data-tab="showtimes"]').trigger('click');
                                smvNotice($alert, errors.join(' — '), 'error');
                                return false;
                            }

                            var $button = $('#smv-save-theatre, .smv-submit-bar button[type="submit"]').first();
                            $button.prop('disabled', true).addClass('is-busy').text('در حال ذخیره…');
                            return true;
                        });
                    } catch (err) {
                        if (window.console) { console.error(err); }
                    }
                });
            }());
            </script>
        </div>
        <?php
    }

    /**
     * ذخیرهٔ تئاتر از فرم اختصاصی پنل.
     * در حالت غیرآجاکس (فرم معمولی) به صفحهٔ لیست هدایت می‌شود و پیام موفقیت نشان داده می‌شود.
     */
    public static function save_theatre() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_save_theatre');

        $is_ajax = function_exists('wp_doing_ajax') && wp_doing_ajax();
        $theatre_id = isset($_POST['theatre_id']) ? absint($_POST['theatre_id']) : 0;

        $finish_error = function ($message) use ($is_ajax, $theatre_id) {
            if ($is_ajax) {
                wp_send_json_error(array('message' => $message));
            }
            $args = array('page' => 'smv-theatres', 'smv_error' => rawurlencode($message));
            if ($theatre_id) {
                $args['action'] = 'edit';
                $args['id'] = $theatre_id;
            } else {
                $args['action'] = 'add';
            }
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        };

        $title = isset($_POST['theatre_title']) ? sanitize_text_field(wp_unslash($_POST['theatre_title'])) : '';
        $excerpt = isset($_POST['theatre_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['theatre_excerpt'])) : '';
        $content = isset($_POST['theatre_content']) ? wp_kses_post(wp_unslash($_POST['theatre_content'])) : '';
        $status = isset($_POST['theatre_status']) ? sanitize_key(wp_unslash($_POST['theatre_status'])) : 'publish';
        if (!in_array($status, array('publish', 'draft', 'private', 'pending'), true)) {
            $status = 'publish';
        }
        $registration_status = (isset($_POST['registration_status']) && 'closed' === $_POST['registration_status']) ? 'closed' : 'open';

        if (!$title) {
            $finish_error('عنوان تئاتر الزامی است.');
        }

        $post_data = array(
            'post_title' => $title,
            'post_excerpt' => $excerpt,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => 'smv_theatre',
        );
        if ($theatre_id) {
            $post_data['ID'] = $theatre_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (!$post_id || is_wp_error($post_id)) {
            $finish_error('ذخیرهٔ تئاتر انجام نشد.');
        }

        // اگر کاربر بدون داشتن مجوز publish ذخیره کرده باشد، وردپرس وضعیت را «در انتظار» می‌کند.
        if ('publish' === $status) {
            self::force_publish($post_id);
        }

        // پوستر
        $poster_id = isset($_POST['poster_id']) ? absint($_POST['poster_id']) : 0;
        if ($poster_id) {
            set_post_thumbnail($post_id, $poster_id);
        } elseif (isset($_POST['poster_id'])) {
            delete_post_thumbnail($post_id);
        }

        // فیلدهای متنی
        $text_fields = array('director', 'writer', 'cast', 'show_time', 'duration', 'age_range', 'venue');
        foreach ($text_fields as $key) {
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, '_smv_' . $key, $value);
        }

        // تاریخ‌ها: ورودی شمسی («۱۴۰۵/۰۲/۱۲») است و به میلادی تبدیل و ذخیره می‌شود.
        foreach (array('start_date', 'end_date', 'registration_deadline') as $key) {
            $value = isset($_POST[$key]) ? SMV_Persian::parse_date_input(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, '_smv_' . $key, $value);
        }

        // اعداد
        foreach (array('price', 'capacity') as $key) {
            $value = isset($_POST[$key]) ? smv_parse_amount(wp_unslash($_POST[$key])) : 0;
            update_post_meta($post_id, '_smv_' . $key, $value);
        }

        update_post_meta($post_id, '_smv_registration_status', $registration_status);
        update_post_meta($post_id, '_smv_showtime_fill_mode', (isset($_POST['showtime_fill_mode']) && 'free' === sanitize_key(wp_unslash($_POST['showtime_fill_mode']))) ? 'free' : 'ordered');

        // گالری
        // گالری: فقط وقتی بازنویسی می‌شود که فرم واقعاً داده‌ای فرستاده باشد
        // (تا ذخیرهٔ بخش‌های دیگر، گالری موجود را پاک نکند).
        if (isset($_POST['gallery_ids']) || isset($_POST['gallery_ids_json'])) {
            $gallery_ids = array();
            if (isset($_POST['gallery_ids']) && is_array($_POST['gallery_ids'])) {
                $gallery_ids = array_values(array_filter(array_map('absint', wp_unslash($_POST['gallery_ids']))));
            } elseif (isset($_POST['gallery_ids_json'])) {
                $decoded = json_decode(wp_unslash($_POST['gallery_ids_json']), true);
                if (is_array($decoded)) {
                    $gallery_ids = array_values(array_filter(array_map('absint', $decoded)));
                }
            }
            update_post_meta($post_id, '_smv_gallery', maybe_serialize($gallery_ids));
        }

        // ویدیو
        $video_url = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
        $video_id = isset($_POST['video_id']) ? absint($_POST['video_id']) : 0;
        update_post_meta($post_id, '_smv_video_url', $video_url);
        update_post_meta($post_id, '_smv_video_id', $video_id);

        // ویدیوهای اضافی (هر خط یک لینک)
        if (isset($_POST['extra_videos'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) wp_unslash($_POST['extra_videos']));
            $extra_videos = array();
            foreach ((array) $lines as $line) {
                $line = esc_url_raw(trim($line));
                if ($line) {
                    $extra_videos[] = $line;
                }
            }
            update_post_meta($post_id, '_smv_videos', maybe_serialize(array_values(array_unique($extra_videos))));
        }

        // روش‌های پرداخت
        $payment_methods = isset($_POST['payment_methods']) ? array_map('sanitize_key', (array) wp_unslash($_POST['payment_methods'])) : array();
        update_post_meta($post_id, '_smv_payment_free', in_array('free', $payment_methods, true) ? '1' : '0');
        update_post_meta($post_id, '_smv_payment_financial_aid', in_array('financial_aid', $payment_methods, true) ? '1' : '0');
        update_post_meta($post_id, '_smv_payment_paid', in_array('paid', $payment_methods, true) ? '1' : '0');

        // راهنمای واریز و کد مرچنت به تنظیمات افزونه می‌روند (قبلاً در گزینهٔ اشتباه ذخیره می‌شدند)
        $settings = get_option('smv_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        if (isset($_POST['payment_instructions'])) {
            $settings['payment_instructions'] = sanitize_textarea_field(wp_unslash($_POST['payment_instructions']));
        }
        if (isset($_POST['zarinpal_merchant'])) {
            $settings['zarinpal_merchant'] = sanitize_text_field(wp_unslash($_POST['zarinpal_merchant']));
        }
        update_option('smv_settings', wp_parse_args($settings, SMV_Settings::defaults()));

        // حذف سانس‌هایی که در فرم علامت «حذف» خورده‌اند
        $delete_showtimes = isset($_POST['delete_showtimes']) && is_array($_POST['delete_showtimes']) ? array_map('absint', wp_unslash($_POST['delete_showtimes'])) : array();
        foreach ($delete_showtimes as $del_id) {
            if (!$del_id || 'smv_showtime' !== get_post_type($del_id)) {
                continue;
            }
            $del_owner = absint(get_post_meta($del_id, '_smv_theatre_id', true));
            if ($del_owner && $del_owner !== $post_id && !current_user_can('delete_post', $del_id)) {
                continue;
            }
            wp_delete_post($del_id, true);
        }

        // به‌روزرسانی سانس‌های موجود (مقادیر فرم حفظ می‌شوند و ظرفیت رزرو صفر نمی‌شود)
        $existing_showtimes = isset($_POST['showtimes']) && is_array($_POST['showtimes']) ? wp_unslash($_POST['showtimes']) : array();
        foreach ($existing_showtimes as $st_id => $st_data) {
            $st_id = absint($st_id);
            if (!$st_id || 'smv_showtime' !== get_post_type($st_id) || in_array($st_id, $delete_showtimes, true)) {
                continue;
            }
            $owner = absint(get_post_meta($st_id, '_smv_theatre_id', true));
            if ($owner && $owner !== $post_id && !current_user_can('edit_post', $st_id)) {
                continue;
            }
            $date = isset($st_data['date']) ? SMV_Persian::parse_date_input($st_data['date']) : '';
            $time = isset($st_data['time']) ? trim((string) SMV_Persian::english_digits($st_data['time'])) : '';
            $time_end = isset($st_data['time_end']) ? trim((string) SMV_Persian::english_digits($st_data['time_end'])) : '';
            update_post_meta($st_id, '_smv_theatre_id', $post_id);
            update_post_meta($st_id, '_smv_showtime_date', $date);
            update_post_meta($st_id, '_smv_showtime_time', $time);
            update_post_meta($st_id, '_smv_showtime_time_end', $time_end);
            update_post_meta($st_id, '_smv_capacity', isset($st_data['capacity']) ? smv_parse_amount($st_data['capacity']) : 0);
            update_post_meta($st_id, '_smv_priority', isset($st_data['priority']) ? absint($st_data['priority']) : 0);
            update_post_meta($st_id, '_smv_showtime_price', isset($st_data['price']) ? smv_parse_amount($st_data['price']) : 0);
            update_post_meta($st_id, '_smv_showtime_venue', isset($st_data['venue']) ? sanitize_text_field($st_data['venue']) : '');
            wp_update_post(array(
                'ID' => $st_id,
                'post_title' => smv_showtime_title($date, $time, $time_end),
            ));
            self::force_publish($st_id);
            smv_showtime_booked_count($st_id);
        }

        // سانس‌های جدید
        $new_showtimes = isset($_POST['new_showtime']) && is_array($_POST['new_showtime']) ? wp_unslash($_POST['new_showtime']) : array();
        foreach ($new_showtimes as $st_data) {
            $date = isset($st_data['date']) ? SMV_Persian::parse_date_input($st_data['date']) : '';
            $time = isset($st_data['time']) ? trim((string) SMV_Persian::english_digits($st_data['time'])) : '';
            $time_end = isset($st_data['time_end']) ? trim((string) SMV_Persian::english_digits($st_data['time_end'])) : '';
            if (!$date && !$time && !$time_end) {
                continue;
            }
            if (!$date) {
                continue; // سانس بدون تاریخ ثبت نمی‌شود
            }
            $st_post_id = wp_insert_post(array(
                'post_title' => smv_showtime_title($date, $time, $time_end),
                'post_status' => 'publish',
                'post_type' => 'smv_showtime',
            ));
            if ($st_post_id && !is_wp_error($st_post_id)) {
                update_post_meta($st_post_id, '_smv_theatre_id', $post_id);
                update_post_meta($st_post_id, '_smv_showtime_date', $date);
                update_post_meta($st_post_id, '_smv_showtime_time', $time);
                update_post_meta($st_post_id, '_smv_showtime_time_end', $time_end);
                update_post_meta($st_post_id, '_smv_capacity', isset($st_data['capacity']) ? smv_parse_amount($st_data['capacity']) : 0);
                update_post_meta($st_post_id, '_smv_priority', isset($st_data['priority']) ? absint($st_data['priority']) : 0);
                update_post_meta($st_post_id, '_smv_booked', 0);
                update_post_meta($st_post_id, '_smv_showtime_price', isset($st_data['price']) ? smv_parse_amount($st_data['price']) : 0);
                update_post_meta($st_post_id, '_smv_showtime_venue', isset($st_data['venue']) ? sanitize_text_field($st_data['venue']) : '');
                self::force_publish($st_post_id);
            }
        }

        $saved_url = $post_id
            ? add_query_arg(array('page' => 'smv-theatres', 'action' => 'edit', 'id' => $post_id, 'smv_saved' => 1), admin_url('admin.php'))
            : add_query_arg(array('page' => 'smv-theatres', 'smv_saved' => 1), admin_url('admin.php'));

        if ($is_ajax) {
            wp_send_json_success(array(
                'message' => 'تئاتر با موفقیت ذخیره شد.',
                'redirect' => $saved_url,
            ));
        }

        wp_safe_redirect($saved_url);
        exit;
    }

    /**
     * اطمینان از منتشر شدن سانس؛ اگر کاربر دسترسی publish نداشته باشد
     * وردپرس وضعیت را به «در انتظار» تغییر می‌دهد و سانس در سایت دیده نمی‌شود.
     */
    private static function force_publish($post_id) {
        global $wpdb;
        $post_id = absint($post_id);
        if (!$post_id) {
            return;
        }
        if ('publish' !== get_post_status($post_id)) {
            $wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $post_id), array('%s'), array('%d'));
            clean_post_cache($post_id);
        }
    }

    public static function delete_theatre_action($theatre_id) {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_delete_theatre_' . $theatre_id);
        self::delete_theatre_with_children($theatre_id);
        wp_safe_redirect(admin_url('admin.php?page=smv-theatres&smv_deleted=1'));
        exit;
    }

    public static function delete_theatre() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_save_theatre');
        $id = isset($_POST['theatre_id']) ? absint($_POST['theatre_id']) : 0;
        if ($id) {
            self::delete_theatre_with_children($id);
        }
        wp_send_json_success(array('message' => 'تئاتر حذف شد.'));
    }

    /**
     * حذف تئاتر همراه با سانس‌های وابسته (تا سانس‌های سرگردان در سایت نمانند).
     */
    private static function delete_theatre_with_children($theatre_id) {
        $theatre_id = absint($theatre_id);
        if (!$theatre_id) {
            return;
        }
        foreach (smv_get_all_showtimes_for_theatre($theatre_id) as $showtime) {
            wp_delete_post($showtime->ID, true);
        }
        wp_delete_post($theatre_id, true);
    }

    public static function save_showtime() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_save_showtime');
        wp_send_json_success(array('message' => 'سانس ذخیره شد.'));
    }

    public static function delete_showtime() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_send_json_error(array('message' => 'دسترسی لازم را ندارید.'));
        }
        check_ajax_referer('smv_delete_showtime');
        $id = isset($_POST['showtime_id']) ? absint($_POST['showtime_id']) : 0;
        if (!$id || 'smv_showtime' !== get_post_type($id)) {
            wp_send_json_error(array('message' => 'سانس پیدا نشد.'));
        }
        if (!current_user_can('delete_post', $id) && !current_user_can('manage_smv_registrations')) {
            wp_send_json_error(array('message' => 'دسترسی لازم را ندارید.'));
        }
        wp_delete_post($id, true);
        wp_send_json_success(array('message' => 'سانس حذف شد.'));
    }

    public static function smart_update_from_zip() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_send_json_error(array('message' => 'دسترسی لازم را ندارید.'));
        }
        check_ajax_referer('smv_smart_update');

        $has_zip = isset($_FILES['smv_update_zip']) && $_FILES['smv_update_zip']['error'] === UPLOAD_ERR_OK;
        $has_json = isset($_FILES['smv_update_json']) && $_FILES['smv_update_json']['error'] === UPLOAD_ERR_OK;

        if (!$has_zip && !$has_json) {
            wp_send_json_error(array('message' => 'فایل ZIP یا JSON یکی از آن‌ها الزامی است.'));
        }

        $new_version = 'نامشخص';
        $temp_dir = null;

        // Process JSON if provided
        if ($has_json) {
            $json_file = $_FILES['smv_update_json'];
            if (!preg_match('/\.json$/i', $json_file['name'])) {
                wp_send_json_error(array('message' => 'فایل JSON نامعتبر است.'));
            }
            $json_content = file_get_contents($json_file['tmp_name']);
            $json_data = json_decode($json_content, true);
            if (!$json_data) {
                wp_send_json_error(array('message' => 'فایل JSON قابل پارس نیست.'));
            }
            $new_version = $json_data['version'] ?? 'نامشخص';
        }

        // Process ZIP if provided
        if ($has_zip) {
            $zip_file = $_FILES['smv_update_zip'];
            if (!preg_match('/\.zip$/i', $zip_file['name'])) {
                wp_send_json_error(array('message' => 'فایل ZIP نامعتبر است.'));
            }

            $temp_dir = sys_get_temp_dir() . '/smv_update_' . uniqid();
            wp_mkdir_p($temp_dir);

            if (!class_exists('ZipArchive')) {
                wp_send_json_error(array('message' => 'افزونهٔ ZIP روی سرور فعال نیست. فایل را از بخش افزونه‌ها نصب کنید.'));
            }

            $zip = new ZipArchive();
            if ($zip->open($zip_file['tmp_name']) !== true) {
                wp_send_json_error(array('message' => 'نمی‌توان فایل ZIP را باز کرد.'));
            }

            // جلوگیری از خروج از مسیر (Zip Slip) پیش از استخراج
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if ('' === $entry) {
                    continue;
                }
                $normalized = str_replace('\\', '/', $entry);
                if (false !== strpos($normalized, '..') || 0 === strpos($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
                    $zip->close();
                    self::remove_dir_recursive($temp_dir);
                    wp_send_json_error(array('message' => 'ساختار فایل ZIP نامعتبر است.'));
                }
            }

            $zip->extractTo($temp_dir);
            $zip->close();

            // Find samavieh-manager folder inside extracted ZIP
            $plugin_source = $temp_dir . '/samavieh-manager';
            if (!is_dir($plugin_source)) {
                foreach ((array) scandir($temp_dir) as $item) {
                    if ($item !== '.' && $item !== '..' && is_dir($temp_dir . '/' . $item)) {
                        $plugin_source = $temp_dir . '/' . $item;
                        break;
                    }
                }
            }

            if (!is_dir($plugin_source) || !is_file($plugin_source . '/samavieh-manager.php')) {
                self::remove_dir_recursive($temp_dir);
                wp_send_json_error(array('message' => 'فایل samavieh-manager.php در ZIP پیدا نشد؛ فایل آپدیت معتبر نیست.'));
            }

            // Replace plugin files
            $plugin_dest = WP_PLUGIN_DIR . '/samavieh-manager';
            self::copy_dir_recursive($plugin_source, $plugin_dest);

            // فقط اگر فایل JSON معتبر باشد نسخهٔ دیتابیس را تغییر می‌دهیم
            if ($has_json) {
                update_option('smv_pending_update_version', $new_version);
            }

            // Cleanup
            self::remove_dir_recursive($temp_dir);
            $temp_dir = null;
        }

        wp_send_json_success(array(
            'message' => 'بروزرسانی با موفقیت انجام شد. نسخه: ' . esc_html($new_version),
            'redirect' => admin_url('admin.php?page=smv-dashboard&smv_updated=1')
        ));
    }

    private static function copy_dir_recursive($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            wp_mkdir_p($dst);
        }
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                self::copy_dir_recursive($src_path, $dst_path);
            } else {
                @copy($src_path, $dst_path);
            }
        }
        closedir($dir);
    }

    private static function remove_dir_recursive($dir) {
        if (!is_dir($dir)) return true;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::remove_dir_recursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
        return true;
    }

    private static function filters() {
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if ($status && !array_key_exists($status, smv_statuses())) {
            $status = '';
        }
        return array(
            'status' => $status,
            'theatre_id' => isset($_GET['theatre_id']) ? absint($_GET['theatre_id']) : 0,
            's' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        );
    }

    public static function get_filters() {
        return self::filters();
    }

    private static function where($filters) {
        global $wpdb;
        $where = array('1=1');
        $params = array();
        if ($filters['status']) {
            $where[] = 'r.status = %s';
            $params[] = $filters['status'];
        }
        if ($filters['theatre_id']) {
            $where[] = 'r.theatre_id = %d';
            $params[] = $filters['theatre_id'];
        }
        if ($filters['s']) {
            $like = '%' . $wpdb->esc_like($filters['s']) . '%';
            $where[] = '(r.first_name LIKE %s OR r.last_name LIKE %s OR r.phone LIKE %s OR r.tracking_code LIKE %s)';
            $params = array_merge($params, array($like, $like, $like, $like));
        }
        return array('sql' => implode(' AND ', $where), 'params' => $params);
    }

    public static function get_where($filters) {
        return self::where($filters);
    }

    public static function registrations() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        if (!empty($_GET['action']) && 'view' === $_GET['action']) {
            self::registration_details(isset($_GET['id']) ? absint($_GET['id']) : 0);
            return;
        }
        global $wpdb;
        $table = smv_table_name();
        $filters = self::filters();
        $where = self::where($filters);
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $params = array_merge($where['params'], array($per_page, ($paged - 1) * $per_page));
        $sql = "SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id WHERE {$where['sql']} ORDER BY r.created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: array();
        $count_sql = "SELECT COUNT(*) FROM {$table} r WHERE {$where['sql']}";
        $total = (int) ($where['params'] ? $wpdb->get_var($wpdb->prepare($count_sql, $where['params'])) : $wpdb->get_var($count_sql));
        $pages = max(1, (int) ceil($total / $per_page));
        $theatres = get_posts(array('post_type' => 'smv_theatre', 'post_status' => array('publish', 'draft', 'pending', 'private'), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $export_url = wp_nonce_url(add_query_arg(array_merge(array('action' => 'smv_export_registrations'), $filters), admin_url('admin-post.php')), 'smv_export_registrations');
        $export_pdf_url = wp_nonce_url(add_query_arg(array_merge(array('action' => 'smv_export_pdf'), $filters), admin_url('admin-post.php')), 'smv_export_registrations');
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <div class="smv-admin-heading"><div><span class="smv-admin-kicker">بررسی درخواست‌ها</span><h1>ثبت‌نام‌ها و فیش‌های واریزی</h1><p>برای دیدن فیش و توضیح بیشتر، روی «مشاهده» بزنید.</p></div>
                <a class="button" href="<?php echo esc_url($export_url); ?>">دریافت خروجی CSV</a>
                <a class="button smv-primary-button" href="<?php echo esc_url($export_pdf_url); ?>">دریافت خروجی PDF</a>
            </div>
            <?php if (isset($_GET['smv_updated'])) : ?><div class="notice notice-success is-dismissible"><p>وضعیت و توضیح ثبت‌نام با موفقیت ذخیره شد.</p></div><?php endif; ?>
            <?php if (isset($_GET['smv_deleted'])) : ?><div class="notice notice-success is-dismissible"><p>ثبت‌نام و فایل فیش مربوط به آن حذف شد.</p></div><?php endif; ?>
            <section class="smv-admin-card">
                <form method="get" class="smv-filter-bar"><input type="hidden" name="page" value="smv-registrations" /><input type="search" name="s" value="<?php echo esc_attr($filters['s']); ?>" placeholder="نام، شماره تماس یا کد پیگیری…" /><select name="status"><option value="">همهٔ وضعیت‌ها</option><?php foreach (smv_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><select name="theatre_id"><option value="0">همهٔ تئاترها</option><?php foreach ($theatres as $theatre) : ?><option value="<?php echo esc_attr($theatre->ID); ?>" <?php selected($filters['theatre_id'], $theatre->ID); ?>><?php echo esc_html($theatre->post_title); ?></option><?php endforeach; ?></select><button class="button" type="submit">فیلتر و جست‌وجو</button><a class="button-link" href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations')); ?>">پاک کردن فیلتر</a></form>
                <?php if ($rows) : ?>
                    <div class="smv-table-wrap"><table class="smv-registration-table"><thead><tr><th>ثبت‌نام‌کننده</th><th>کد پیگیری</th><th>تئاتر / سانس</th><th>روش پرداخت</th><th>مبلغ</th><th>شماره تماس</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
                    <?php foreach ($rows as $row) : $view_url = admin_url('admin.php?page=smv-registrations&action=view&id=' . $row->id); $pdf_url = wp_nonce_url(admin_url('admin-post.php?action=smv_export_registration_pdf&registration_id=' . $row->id), 'smv_view_receipt_' . $row->id); $showtime_title = get_the_title($row->showtime_id ?: 0); $payment_label = smv_payment_type_label($row->payment_type ?? 'free'); ?>
                        <tr><td data-label="ثبت‌نام‌کننده"><strong><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></strong></td><td data-label="کد پیگیری"><code dir="ltr"><?php echo esc_html($row->tracking_code); ?></code></td><td data-label="تئاتر / سانس"><?php echo esc_html($row->theatre_title ? $row->theatre_title : 'تئاتر حذف شده'); ?><br><small><?php echo esc_html($showtime_title ?: 'سانس تعریف نشده'); ?></small></td><td data-label="روش پرداخت"><?php echo esc_html($payment_label); ?></td><td data-label="مبلغ"><?php echo esc_html(SMV_Persian::money(absint($row->amount ?: 0))); ?></td><td data-label="شماره تماس"><a href="tel:<?php echo esc_attr($row->phone); ?>" dir="ltr"><?php echo esc_html($row->phone); ?></a></td><td data-label="تاریخ"><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td><td data-label="وضعیت"><span class="smv-table-status status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(smv_status_label($row->status)); ?></span></td><td data-label="عملیات"><a class="button button-small" href="<?php echo esc_url($view_url); ?>">مشاهده و بررسی</a> <a class="button button-small" href="<?php echo esc_url($pdf_url); ?>" target="_blank">PDF</a></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <div class="smv-pagination"><span>مجموع: <?php echo esc_html(SMV_Persian::digits($total)); ?> ثبت‌نام</span><div><?php if ($paged > 1) : ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">قبلی</a><?php endif; ?><span>صفحهٔ <?php echo esc_html(SMV_Persian::digits($paged) . ' از ' . SMV_Persian::digits($pages)); ?></span><?php if ($paged < $pages) : ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">بعدی</a><?php endif; ?></div></div>
                <?php else : ?><div class="smv-admin-empty"><span>✓</span><h3>موردی برای نمایش نیست</h3><p>با این فیلترها ثبت‌نامی پیدا نشد. فیلترها را تغییر دهید یا بعداً دوباره ببینید.</p></div><?php endif; ?>
            </section>
        </div>
        <?php
    }

    private static function registration_details($id) {
        global $wpdb;
        $table = smv_table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id WHERE r.id = %d", $id));
        if (!$row) {
            echo '<div class="wrap" dir="rtl"><div class="notice notice-error"><p>ثبت‌نام پیدا نشد.</p></div></div>';
            return;
        }
        $receipt_url = wp_nonce_url(admin_url('admin-post.php?action=smv_view_receipt&registration_id=' . $row->id), 'smv_view_receipt_' . $row->id);
        $pdf_url = wp_nonce_url(admin_url('admin-post.php?action=smv_export_registration_pdf&registration_id=' . $row->id), 'smv_view_receipt_' . $row->id);
        $is_image = 0 === strpos($row->receipt_mime, 'image/');
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <a class="smv-back-link" href="<?php echo esc_url(admin_url('admin.php?page=smv-registrations')); ?>">→ بازگشت به همهٔ ثبت‌نام‌ها</a>
            <div class="smv-admin-heading"><div><span class="smv-admin-kicker">کد پیگیری <bdi dir="ltr"><?php echo esc_html($row->tracking_code); ?></bdi></span><h1><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></h1><p>ثبت‌نام تئاتر «<?php echo esc_html($row->theatre_title); ?>»</p></div><span class="smv-table-status status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(smv_status_label($row->status)); ?></span></div>
            <div class="smv-review-grid">
                <section class="smv-admin-card"><div class="smv-card-title-row"><h2>فیش واریزی</h2><a class="button" href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener">باز کردن در اندازه کامل</a></div><div class="smv-receipt-preview"><?php if ($is_image) : ?><a href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($receipt_url); ?>" alt="فیش واریزی ثبت‌نام <?php echo esc_attr($row->tracking_code); ?>" /></a><?php else : ?><div class="smv-pdf-box"><strong>فیش به صورت PDF ارسال شده است</strong><p>برای مشاهده، روی دکمهٔ زیر بزنید.</p><a class="button" href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener">مشاهدهٔ PDF</a></div><?php endif; ?></div></section>
                <div>
                    <section class="smv-admin-card"><h2>اطلاعات ثبت‌نام</h2><dl class="smv-review-details"><div><dt>نام کامل</dt><dd><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></dd></div><div><dt>شماره تماس</dt><dd><a href="tel:<?php echo esc_attr($row->phone); ?>" dir="ltr"><?php echo esc_html($row->phone); ?></a></dd></div><div><dt>تئاتر</dt><dd><?php echo esc_html($row->theatre_title); ?></dd></div><div><dt>سانس</dt><dd><?php echo esc_html(get_the_title($row->showtime_id ?: 0) ?: 'سانس تعریف نشده'); ?></dd></div><div><dt>روش پرداخت</dt><dd><?php echo esc_html(smv_payment_type_label($row->payment_type ?? 'free')); ?></dd></div><div><dt>مبلغ</dt><dd><?php echo esc_html(SMV_Persian::money(absint($row->amount ?: 0))); ?></dd></div><?php if (!empty($row->payment_ref)) : ?><div><dt>کد رهگیری پرداخت</dt><dd><bdi dir="ltr"><?php echo esc_html($row->payment_ref); ?></bdi></dd></div><?php endif; ?><?php if (!empty($row->paid_at)) : ?><div><dt>زمان پرداخت آنلاین</dt><dd><?php echo esc_html(SMV_Persian::date($row->paid_at, true)); ?></dd></div><?php endif; ?><?php if (!empty($row->financial_aid_reason)) : ?><div><dt>دلیل کمک مالی</dt><dd><?php echo esc_html($row->financial_aid_reason); ?></dd></div><?php endif; ?><div><dt>تاریخ ثبت</dt><dd><?php echo esc_html(SMV_Persian::date($row->created_at, true)); ?></dd></div><div><dt>آخرین تغییر</dt><dd><?php echo esc_html(SMV_Persian::date($row->updated_at, true)); ?></dd></div></dl></section>
                    <section class="smv-admin-card"><h2>نتیجهٔ بررسی</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="smv_update_registration" /><input type="hidden" name="registration_id" value="<?php echo esc_attr($row->id); ?>" /><input type="hidden" name="return_to" value="details" /><?php wp_nonce_field('smv_update_registration_' . $row->id); ?><div class="smv-admin-field"><label for="smv-status"><strong>وضعیت ثبت‌نام</strong></label><select id="smv-status" name="status"><?php foreach (smv_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row->status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div><div class="smv-admin-field"><label for="smv-note"><strong>توضیح برای ثبت‌نام‌کننده</strong><small>اختیاری؛ در صفحهٔ پیگیری نمایش داده می‌شود.</small></label><textarea id="smv-note" name="admin_note" rows="4" placeholder="مثلاً: فیش واریزی تأیید شد. منتظر حضور شما هستیم."><?php echo esc_textarea($row->admin_note); ?></textarea></div><button class="button smv-primary-button" type="submit">ذخیرهٔ نتیجهٔ بررسی</button></form></section>
                    <section class="smv-admin-card smv-danger-zone"><h3>حذف ثبت‌نام</h3><p>فقط در صورت ارسال اشتباه یا درخواست کاربر؛ فایل فیش نیز حذف می‌شود و این کار قابل بازگشت نیست.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-smv-confirm="این ثبت‌نام و فیش آن برای همیشه حذف شود؟"><input type="hidden" name="action" value="smv_delete_registration" /><input type="hidden" name="registration_id" value="<?php echo esc_attr($row->id); ?>" /><?php wp_nonce_field('smv_delete_registration_' . $row->id); ?><button class="button smv-danger-button" type="submit">حذف دائم</button></form></section>
                    <section class="smv-admin-card"><h3>خروجی PDF ثبت‌نام</h3><p>فایل PDF شامل کامل‌ترین اطلاعات ثبت‌نام و فیش واریزی را دریافت کنید.</p><a href="<?php echo esc_url($pdf_url); ?>" class="button smv-primary-button" target="_blank">دانلود PDF ثبت‌نام</a></section>
                </div>
            </div>
        </div>
        <?php
    }

    public static function update_registration() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        check_admin_referer('smv_update_registration_' . $id);
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'pending';
        if (!array_key_exists($status, smv_statuses())) {
            $status = 'pending';
        }
        $note = isset($_POST['admin_note']) ? sanitize_textarea_field(wp_unslash($_POST['admin_note'])) : '';
        global $wpdb;
        $table = smv_table_name();
        $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        $wpdb->update($table, array('status' => $status, 'admin_note' => $note, 'updated_at' => current_time('mysql')), array('id' => $id), array('%s', '%s', '%s'), array('%d'));

        // پیامک تغییر وضعیت (فقط اگر وضعیت واقعاً تغییر کرده باشد)
        if ($previous && isset($previous->status) && $previous->status !== $status && class_exists('SMV_SMS')) {
            if (method_exists('SMV_SMS', 'send_status_update')) {
                $updated_row = clone $previous;
                $updated_row->status = $status;
                SMV_SMS::send_status_update($updated_row, smv_status_label($status));
            }
        }
        $url = (!empty($_POST['return_to']) && 'details' === $_POST['return_to']) ? admin_url('admin.php?page=smv-registrations&action=view&id=' . $id) : admin_url('admin.php?page=smv-registrations');
        wp_safe_redirect(add_query_arg('smv_updated', 1, $url));
        exit;
    }

    public static function delete_registration() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        check_admin_referer('smv_delete_registration_' . $id);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT receipt_path FROM ' . smv_table_name() . ' WHERE id = %d', $id));
        if ($row) {
            SMV_Forms::delete_receipt_file($row->receipt_path);
            $wpdb->delete(smv_table_name(), array('id' => $id), array('%d'));
        }
        wp_safe_redirect(admin_url('admin.php?page=smv-registrations&smv_deleted=1'));
        exit;
    }

    public static function view_receipt() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_GET['registration_id']) ? absint($_GET['registration_id']) : 0;
        check_admin_referer('smv_view_receipt_' . $id);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT receipt_path, receipt_mime FROM ' . smv_table_name() . ' WHERE id = %d', $id));
        if (!$row) {
            wp_die('فیش پیدا نشد.');
        }
        $uploads = wp_upload_dir();
        $base = realpath($uploads['basedir'] . '/samavieh-private');
        $file = realpath($uploads['basedir'] . '/' . ltrim($row->receipt_path, '/'));
        $allowed_mimes = array('image/jpeg', 'image/png', 'image/webp', 'application/pdf');
        if (!$base || !$file || 0 !== strpos($file, $base . DIRECTORY_SEPARATOR) || !is_file($file) || !in_array($row->receipt_mime, $allowed_mimes, true)) {
            wp_die('فایل معتبر نیست یا از مسیر امن خارج شده است.');
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: ' . $row->receipt_mime);
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        $disposition = ('application/pdf' === $row->receipt_mime) ? 'attachment' : 'inline';
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $extension = preg_match('/^(jpe?g|png|webp|pdf)$/', $extension) ? '.' . $extension : '';
        header('Content-Disposition: ' . $disposition . '; filename="receipt-' . $id . $extension . '"');
        readfile($file);
        exit;
    }

    public static function export_csv() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_export_registrations');
        global $wpdb;
        $filters = self::filters();
        $where = self::where($filters);
        $table = smv_table_name();
        $sql = "SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id WHERE {$where['sql']} ORDER BY r.created_at DESC LIMIT 5000";
        $rows = $where['params'] ? $wpdb->get_results($wpdb->prepare($sql, $where['params'])) : $wpdb->get_results($sql);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        $csv_date = str_replace('/', '-', SMV_Persian::date_input_value(current_time('Y-m-d')));
        header('Content-Disposition: attachment; filename="samavieh-registrations-' . ($csv_date ? $csv_date : gmdate('Y-m-d')) . '.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array('کد پیگیری', 'نام', 'نام خانوادگی', 'شماره تماس', 'تئاتر', 'سانس', 'روش پرداخت', 'مبلغ', 'وضعیت', 'تاریخ ثبت', 'آخرین تغییر', 'توضیح مدیریت'));
        foreach ($rows as $row) {
            $values = array(
                $row->tracking_code,
                $row->first_name,
                $row->last_name,
                $row->phone,
                $row->theatre_title,
                $row->showtime_id ? get_the_title($row->showtime_id) : '',
                smv_payment_type_label($row->payment_type ?? 'free'),
                absint($row->amount ?: 0),
                smv_status_label($row->status),
                SMV_Persian::date($row->created_at, true),
                SMV_Persian::date($row->updated_at, true),
                $row->admin_note,
            );
            fputcsv($output, array_map('smv_clean_csv_cell', $values));
        }
        fclose($output);
        exit;
    }
}
