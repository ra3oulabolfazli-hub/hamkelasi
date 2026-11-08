<?php
if (!defined('ABSPATH')) { exit; }

class SMV_SEO {
    const YOAST_FILE = 'wordpress-seo/wp-seo.php';
    const YOAST_ZIP = 'https://downloads.wordpress.org/plugin/wordpress-seo.latest-stable.zip';

    public static function init() {
        add_action('admin_post_smv_install_yoast', array(__CLASS__, 'handle_install_yoast'));
        add_action('admin_post_smv_activate_yoast', array(__CLASS__, 'handle_activate_yoast'));
        add_action('admin_post_smv_run_seo', array(__CLASS__, 'handle_run_seo'));
        add_action('save_post', array(__CLASS__, 'on_save'), 30, 2);
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        $yoast = false;
        try { $yoast = self::yoast_active(); } catch (Throwable $e) { $yoast = false; }
        if (!$yoast) {
            add_action('wp_head', array(__CLASS__, 'fallback_head'), 5);
        } else {
            add_filter('wpseo_schema_graph', array(__CLASS__, 'schema_graph'), 20, 2);
        }
    }

    public static function yoast_active() {
        if (defined('WPSEO_VERSION')) { return true; }
        // تشخیص بدون بارگذاری فایل‌های مدیریت در فرانت‌اند (جلوگیری از هر خطای احتمالی هنگام بارگذاری افزونه)
        $active = get_option('active_plugins', array());
        if (is_array($active) && in_array(self::YOAST_FILE, $active, true)) { return true; }
        if (function_exists('is_plugin_active')) { return (bool) is_plugin_active(self::YOAST_FILE); }
        return false;
    }
    public static function yoast_installed() {
        return file_exists(WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php');
    }
    public static function seo_types() {
        return array('post', 'page', 'smv_theatre', 'smv_project', 'smv_article');
    }

    private static function ulen($text) {
        $n = preg_match_all('/./us', (string) $text, $m);
        return $n === false ? strlen((string) $text) : $n;
    }
    private static function usub($text, $start, $length) {
        if (preg_match_all('/./us', (string) $text, $m)) { return implode('', array_slice($m[0], $start, $length)); }
        return substr((string) $text, $start, $length);
    }
    private static function clean_desc($text, $len = 155) {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $text)));
        if (self::ulen($text) > $len) { $text = self::usub($text, 0, $len - 1) . '…'; }
        return $text;
    }

    public static function focus_keyword_for($post) {
        $kw = get_post_meta($post->ID, '_smv_focus_keyword', true);
        if ($kw) { return $kw; }
        $title = get_the_title($post);
        if ($post->post_type === 'smv_theatre') { return 'تئاتر ' . $title; }
        if ($post->post_type === 'smv_article') { return $title; }
        return $title;
    }

    /** فقط فیلدهای خالی یواست/سئو را پر می‌کند؛ مقادیر دستی بازنویسی نمی‌شوند. */
    public static function fill_for_post($post_id, $force_from_article = false) {
        $post = get_post($post_id);
        if (!$post || !isset($post->post_type) || !in_array($post->post_type, self::seo_types(), true)) { return false; }
        if (!isset($post->post_status) || $post->post_status === 'auto-draft') { return false; }
        $site = get_bloginfo('name');
        $title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        if (!$title) {
            $title = get_the_title($post) . ' | ' . $site;
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
        }
        $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!$desc) {
            $src = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
            if ($post->post_type === 'smv_article') {
                // برای امتیاز بهتر یواست، توضیحات با کلمهٔ کلیدی اصلی شروع می‌شود
                $kw = self::focus_keyword_for($post);
                $base = self::clean_desc($src, 140);
                $src = ($kw && false === strpos($base, $kw)) ? $kw . '؛ ' . $base : $base;
            }
            if ($post->post_type === 'smv_theatre') {
                $src = ($post->post_excerpt ?: '') . ' مشاهده اطلاعات اجرا، سانس‌ها و ثبت‌نام آنلاین تئاتر «' . get_the_title($post) . '» در سماویه.';
            }
            $desc = self::clean_desc($src);
            if ($desc) { update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc); }
        }
        $kw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!$kw) {
            $kw = self::focus_keyword_for($post);
            if ($kw) { update_post_meta($post_id, '_yoast_wpseo_focuskw', $kw); }
        }
        // آینهٔ داخلی (وقتی یواست نصب نیست از همین‌ها در خروجی استفاده می‌شود)
        if (!get_post_meta($post_id, '_smv_seo_title', true)) { update_post_meta($post_id, '_smv_seo_title', get_post_meta($post_id, '_yoast_wpseo_title', true)); }
        if (!get_post_meta($post_id, '_smv_seo_description', true)) { update_post_meta($post_id, '_smv_seo_description', get_post_meta($post_id, '_yoast_wpseo_metadesc', true)); }
        if (!get_post_meta($post_id, '_smv_seo_keyword', true)) { update_post_meta($post_id, '_smv_seo_keyword', get_post_meta($post_id, '_yoast_wpseo_focuskw', true)); }
        return true;
    }

    public static function fill_all() {
        $total = 0; $filled = 0;
        foreach (self::seo_types() as $type) {
            $ids = get_posts(array('post_type' => $type, 'post_status' => array('publish', 'draft', 'private'), 'numberposts' => 500, 'fields' => 'ids', 'suppress_filters' => true));
            foreach ((array) $ids as $id) { $total++; if (self::fill_for_post($id)) { $filled++; } }
        }
        update_option('smv_seo_last_run', array('total' => $total, 'filled' => $filled, 'time' => time()));
        return array('total' => $total, 'filled' => $filled);
    }

    public static function on_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!$post || !in_array($post->post_type, self::seo_types(), true)) { return; }
        self::fill_for_post($post_id);
    }

    public static function stats() {
        $total = 0; $with = 0;
        foreach (self::seo_types() as $type) {
            $ids = get_posts(array('post_type' => $type, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'suppress_filters' => true));
            foreach ((array) $ids as $id) {
                $total++;
                if (get_post_meta($id, '_yoast_wpseo_metadesc', true) || get_post_meta($id, '_smv_seo_description', true)) { $with++; }
            }
        }
        $articles = get_posts(array('post_type' => 'smv_article', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids'));
        $acount = wp_count_posts('smv_article');
        return array('total' => $total, 'with_seo' => $with, 'articles' => isset($acount->publish) ? (int) $acount->publish : 0);
    }

    public static function handle_install_yoast() {
        if (!current_user_can('install_plugins')) { wp_die('برای نصب افزونه دسترسی مدیر کل لازم است.'); }
        check_admin_referer('smv_install_yoast');
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install(self::YOAST_ZIP);
        $msg = 'installed';
        if (is_wp_error($result) || !$result) { $msg = 'install_failed'; }
        else {
            $act = activate_plugin(self::YOAST_FILE);
            $msg = is_wp_error($act) ? 'installed_not_active' : 'installed_active';
            if ($msg === 'installed_active') { self::fill_all(); }
        }
        wp_safe_redirect(add_query_arg('smv_yoast', $msg, admin_url('admin.php?page=smv-seo')));
        exit;
    }
    public static function handle_activate_yoast() {
        if (!current_user_can('activate_plugins')) { wp_die('دسترسی لازم را ندارید.'); }
        check_admin_referer('smv_activate_yoast');
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $act = activate_plugin(self::YOAST_FILE);
        if (!is_wp_error($act)) { self::fill_all(); }
        wp_safe_redirect(add_query_arg('smv_yoast', is_wp_error($act) ? 'activate_failed' : 'activated', admin_url('admin.php?page=smv-seo')));
        exit;
    }
    public static function handle_run_seo() {
        if (!current_user_can('manage_smv_registrations')) { wp_die('دسترسی لازم را ندارید.'); }
        check_admin_referer('smv_run_seo');
        $r = self::fill_all();
        wp_safe_redirect(add_query_arg(array('smv_seo_done' => $r['filled'], 'smv_seo_total' => $r['total']), admin_url('admin.php?page=smv-seo')));
        exit;
    }

    public static function admin_notice() {
        if (!current_user_can('manage_smv_registrations') || self::yoast_active()) { return; }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->id) && strpos($screen->id, 'smv') === false && $screen->id !== 'dashboard') { return; }
        echo '<div class="notice notice-warning"><p><strong>سماویه:</strong> افزونهٔ Yoast SEO (رایگان) فعال نیست. برای تکمیل خودکار سئوی صفحات، مقالات و تئاترها از منوی «سماویه ← سئو و یواست» آن را با یک کلیک نصب کنید.</p></div>';
    }

    public static function fallback_head() {
        if (!is_singular()) { return; }
        $post = get_queried_object();
        if (!$post || !isset($post->ID)) { return; }
        $title = get_post_meta($post->ID, '_smv_seo_title', true) ?: (get_the_title($post) . ' | ' . get_bloginfo('name'));
        $desc = get_post_meta($post->ID, '_smv_seo_description', true);
        if (!$desc) { $desc = self::clean_desc($post->post_excerpt ?: $post->post_content); }
        $url = get_permalink($post);
        $img = get_the_post_thumbnail_url($post->ID, 'large');
        if ($desc) { echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n"; }
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:type" content="' . ($post->post_type === 'smv_article' ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ($desc) { echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n"; }
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($img) { echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n"; }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        if ($post->post_type === 'smv_article') {
            $schema = array('@context' => 'https://schema.org', '@type' => 'Article', 'headline' => get_the_title($post), 'description' => $desc, 'datePublished' => get_the_date('c', $post), 'mainEntityOfPage' => $url);
            if ($img) { $schema['image'] = $img; }
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
        }
    }

    public static function schema_graph($graph, $context) {
        if (is_singular('smv_article')) {
            $post = get_queried_object();
            if ($post && isset($post->ID)) {
                $graph[] = array('@type' => 'Article', '@id' => get_permalink($post) . '#article', 'headline' => get_the_title($post), 'description' => self::clean_desc($post->post_excerpt ?: $post->post_content), 'datePublished' => get_the_date('c', $post));
            }
        }
        return $graph;
    }

    public static function render_page() {
        if (!current_user_can('manage_smv_registrations')) { wp_die('دسترسی لازم را ندارید.'); }
        $active = self::yoast_active(); $installed = self::yoast_installed();
        $st = self::stats(); $last = get_option('smv_seo_last_run');
        ?>
        <div class="wrap smv-seo-page" dir="rtl">
            <h1>سئو و یواست — سماویه</h1>
            <?php if (isset($_GET['smv_yoast'])) { echo '<div class="notice notice-success"><p>عملیات یواست انجام شد: ' . esc_html(sanitize_key(wp_unslash($_GET['smv_yoast']))) . '</p></div>'; } ?>
            <?php if (isset($_GET['smv_seo_done'])) { echo '<div class="notice notice-success"><p>تکمیل خودکار سئو انجام شد: ' . (int) $_GET['smv_seo_done'] . ' مورد از ' . (int) ($_GET['smv_seo_total'] ?? 0) . ' مورد بررسی شد (فقط فیلدهای خالی پر شدند).</p></div>'; } ?>
            <?php if (isset($_GET['smv_articles_seeded'])) { echo '<div class="notice notice-success"><p>' . (int) $_GET['smv_articles_seeded'] . ' مقاله ساخته/به‌روز شد و ' . (int) ($_GET['smv_articles_skipped'] ?? 0) . ' مقاله از قبل وجود داشت.</p></div>'; } ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:1000px;margin:16px 0">
                <div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px"><strong>وضعیت یواست</strong><p><?php echo $active ? '✅ نصب و فعال است' : ($installed ? '⚠️ نصب است ولی فعال نیست' : '❌ نصب نیست'); ?></p>
                <?php if (!$installed) { ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('smv_install_yoast'); ?><input type="hidden" name="action" value="smv_install_yoast"><button class="button button-primary">نصب و فعال‌سازی Yoast SEO رایگان</button></form>
                    <p style="color:#666">نسخهٔ رسمی رایگان از مخزن وردپرس (downloads.wordpress.org) دانلود می‌شود؛ نیازمند دسترسی نصب افزونه و اینترنت روی هاست است.</p>
                <?php } elseif (!$active) { ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('smv_activate_yoast'); ?><input type="hidden" name="action" value="smv_activate_yoast"><button class="button button-primary">فعال‌سازی یواست و تکمیل سئو</button></form>
                <?php } else { echo '<p style="color:#2e7d32">یواست فعال است و فیلدهای سئو از داده‌های زیر پر می‌شوند.</p>'; } ?>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px"><strong>پوشش سئو</strong><p><?php echo (int) $st['with_seo']; ?> از <?php echo (int) $st['total']; ?> محتوای منتشرشده توضیحات سئو دارد.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('smv_run_seo'); ?><input type="hidden" name="action" value="smv_run_seo"><button class="button">تکمیل خودکار سئوی همه (فقط خالی‌ها)</button></form>
                    <?php if ($last) { echo '<p style="color:#666">آخرین اجرا: ' . (int) $last['filled'] . ' از ' . (int) $last['total'] . '</p>'; } ?>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px"><strong>مقالات</strong><p><?php echo (int) $st['articles']; ?> مقالهٔ منتشرشده</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"><?php wp_nonce_field('smv_seed_articles'); ?><input type="hidden" name="action" value="smv_seed_articles"><button class="button">ساخت ۲۰ مقالهٔ آماده (اگر نیست)</button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('مقالات آماده بازنویسی شوند؟')"><?php wp_nonce_field('smv_seed_articles_force'); ?><input type="hidden" name="action" value="smv_seed_articles_force"><button class="button">بازنویسی مقالات آماده</button></form>
                    <p style="color:#666">نمایش در سایت: شورت‌کد <code>[samavieh_articles]</code> یا آرشیو <code>/maghale/</code></p>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px;max-width:1000px">
                <h2>چه چیزهایی خودکار تکمیل می‌شود؟</h2>
                <ul style="list-style:disc;margin-right:20px">
                    <li>برای همهٔ برگه‌ها، نوشته‌ها، تئاترها، پروژه‌ها و مقالات: عنوان سئو، توضیحات متا و کلمهٔ کلیدی اصلی — فقط اگر خالی باشند.</li>
                    <li>هر محتوای تازه (از جمله تئاتر و مقالهٔ جدید) هنگام ذخیره به‌صورت خودکار سئوی اولیه می‌گیرد.</li>
                    <li>مقالات آماده از ابتدا با کلمهٔ کلیدی، تیترهای H2/H3، چکیده، پرسش پرتکرار و لینک داخلی ساخته شده‌اند تا در یواست امتیاز خوانایی و سئوی خوبی بگیرند.</li>
                    <li>اگر یواست نصب نباشد، افزونه موقتاً خودش متای توضیحات، Open Graph و اسکیمای مقاله را چاپ می‌کند تا صفحه‌ای بدون سئو نماند؛ با نصب یواست این بخش خودکار خاموش می‌شود.</li>
                    <li>سئوی دستی شما هرگز بازنویسی نمی‌شود.</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
