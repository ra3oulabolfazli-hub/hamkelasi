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
        add_action('admin_init', array(__CLASS__, 'maybe_auto_fill'), 20);
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        $yoast = false;
        try { $yoast = self::yoast_active(); } catch (Throwable $e) { $yoast = false; }
        if (!$yoast) {
            add_action('wp_head', array(__CLASS__, 'fallback_head'), 5);
            add_filter('pre_get_document_title', array(__CLASS__, 'document_title'), 20);
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

    /** توضیح ساخته‌شده از شورت‌کدِ خام (مثل «[samavieh_home]») سئوی دستی نیست و باید جایگزین شود. */
    private static function is_junk_desc($desc) {
        $desc = trim((string) $desc);
        if ($desc === '') { return true; }
        if (strpos($desc, '[') !== false && strpos($desc, ']') !== false) { return true; }
        return false;
    }

    private static function org_name() {
        if (function_exists('smv_get_option')) {
            $org = smv_get_option('organization_name', '');
            if ($org) { return $org; }
        }
        return get_bloginfo('name');
    }
    private static function org_tagline() {
        if (function_exists('smv_get_option')) {
            $tag = smv_get_option('tagline', '');
            if ($tag) { return $tag; }
        }
        return get_bloginfo('description');
    }

    /** پروفایل سئوی اختصاصی برگه‌ها بر اساس نقش صفحه (شورت‌کد/نامک/صفحهٔ اصلی). */
    public static function page_profile($post) {
        if (!$post || $post->post_type !== 'page') { return null; }
        $site = get_bloginfo('name');
        $org = self::org_name();
        $content = (string) ($post->post_content ?? '');
        $slug = (string) ($post->post_name ?? '');
        $is_front = (function_exists('get_option') && (int) get_option('page_on_front') === (int) $post->ID)
            || false !== strpos($content, '[samavieh_home');
        if ($is_front) {
            $hero = function_exists('smv_get_option') ? self::clean_desc(smv_get_option('home_hero_text', ''), 90) : '';
            return array(
                'keyword' => 'ثبت‌نام تئاتر',
                'title' => 'ثبت‌نام تئاتر و برنامه‌های فرهنگی | ' . $site,
                'desc' => 'ثبت‌نام تئاتر و برنامه‌های فرهنگی ' . $org . '؛ مشاهدهٔ تئاترهای در حال اجرا، سانس‌ها و ثبت‌نام آنلاین.' . ($hero ? ' ' . $hero : ''),
            );
        }
        $map = array(
            'theatres' => array('kw' => 'تئاترهای در حال اجرا', 'desc' => 'تئاترهای در حال اجرا؛ معرفی نمایش‌های روی صحنه، اطلاعات اجرا، سانس‌ها و ثبت‌نام آنلاین در سماویه.'),
            'projects' => array('kw' => 'پروژه‌های فرهنگی هنری', 'desc' => 'پروژه‌های فرهنگی هنری سماویه؛ آشنایی با طرح‌ها و فعالیت‌های فرهنگی، هنری و تئاتری گروه.'),
            'articles' => array('kw' => 'مقالات تئاتر', 'desc' => 'مقالات تئاتر و آموزش‌های فرهنگی هنری؛ راهنمای بازیگری، کارگردانی و تماشای بهتر نمایش در وبلاگ سماویه.'),
            'contact' => array('kw' => 'تماس با سماویه', 'desc' => 'تماس با سماویه؛ نشانی، تلفن و راه‌های ارتباطی برای پرسش دربارهٔ تئاترها، سانس‌ها و ثبت‌نام.'),
            'tracking' => array('kw' => 'پیگیری ثبت‌نام تئاتر', 'desc' => 'پیگیری ثبت‌نام تئاتر با کد رهگیری؛ مشاهدهٔ وضعیت ثبت‌نام، سانس انتخابی و جزئیات درخواست در سماویه.'),
            'registration' => array('kw' => 'فرم ثبت‌نام تئاتر', 'desc' => 'فرم ثبت‌نام تئاتر؛ انتخاب سانس، ثبت اطلاعات، همراهان و دریافت کد رهگیری ثبت‌نام در سماویه.'),
            'about' => array('kw' => 'درباره سماویه', 'desc' => 'درباره سماویه؛ آشنایی با ' . $org . '، اهداف، رویکرد و فعالیت‌های تئاتری و فرهنگی گروه.'),
        );
        $key = null;
        if (false !== strpos($content, '[samavieh_theatres')) { $key = 'theatres'; }
        elseif (false !== strpos($content, '[samavieh_projects')) { $key = 'projects'; }
        elseif (false !== strpos($content, '[samavieh_articles') || false !== strpos($content, '[samavieh_latest_articles')) { $key = 'articles'; }
        elseif (false !== strpos($content, '[samavieh_contact')) { $key = 'contact'; }
        elseif (false !== strpos($content, '[samavieh_tracking')) { $key = 'tracking'; }
        elseif (false !== strpos($content, '[samavieh_registration')) { $key = 'registration'; }
        elseif (isset($map[$slug])) { $key = $slug; }
        if (!$key) { return null; }
        $p = $map[$key];
        return array('keyword' => $p['kw'], 'title' => $p['kw'] . ' | ' . $site, 'desc' => $p['desc']);
    }

    /** روی آپدیت افزونه هم یک بار سئوی برگه‌ها خودکار تکمیل می‌شود (فقط فیلدهای خالی/خراب). */
    public static function maybe_auto_fill() {
        $ver = defined('SMV_VERSION') ? SMV_VERSION : '1';
        if (get_option('smv_seo_pages_v') === $ver) { return; }
        if (!current_user_can('edit_posts')) { return; }
        self::fill_all();
        update_option('smv_seo_pages_v', $ver);
    }

    public static function focus_keyword_for($post) {
        $kw = get_post_meta($post->ID, '_smv_focus_keyword', true);
        if ($kw) { return $kw; }
        if ($post->post_type === 'page') {
            $profile = self::page_profile($post);
            if ($profile) { return $profile['keyword']; }
        }
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
        $profile = ($post->post_type === 'page') ? self::page_profile($post) : null;
        $title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        if (!$title) {
            $title = $profile ? $profile['title'] : (get_the_title($post) . ' | ' . $site);
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
        }
        $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (self::is_junk_desc($desc)) {
            if ($profile) {
                $desc = self::clean_desc($profile['desc']);
            } else {
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
                if ($post->post_type === 'smv_project') {
                    $src = ($post->post_excerpt ?: '') . ' آشنایی با پروژهٔ «' . get_the_title($post) . '» از ' . self::org_name() . ' در سماویه.';
                }
                $clean_src = function_exists('strip_shortcodes') ? strip_shortcodes((string) $src) : $src;
                $desc = self::clean_desc($clean_src);
                if (self::ulen($desc) < 40) {
                    // محتوای کوتاه/شورت‌کدی: توضیح معنادار بر اساس عنوان و مشخصات سایت
                    $desc = self::clean_desc(get_the_title($post) . '؛ ' . $site . ' — ' . self::org_tagline());
                }
            }
            if ($desc) { update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc); }
        }
        $kw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!$kw) {
            $kw = self::focus_keyword_for($post);
            if ($kw) { update_post_meta($post_id, '_yoast_wpseo_focuskw', $kw); }
        }
        // فیلدهای شبکه‌های اجتماعی یواست (فقط اگر خالی باشند و تصویر شاخص باشد)
        $final_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $final_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if ($final_title && !get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true)) { update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $final_title); }
        if ($final_desc && !get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true)) { update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $final_desc); }
        if ($final_title && !get_post_meta($post_id, '_yoast_wpseo_twitter-title', true)) { update_post_meta($post_id, '_yoast_wpseo_twitter-title', $final_title); }
        if ($final_desc && !get_post_meta($post_id, '_yoast_wpseo_twitter-description', true)) { update_post_meta($post_id, '_yoast_wpseo_twitter-description', $final_desc); }
        if (function_exists('get_post_thumbnail_id') && function_exists('wp_get_attachment_image_url')) {
            $thumb_id = (int) get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $img_url = wp_get_attachment_image_url($thumb_id, 'large');
                if ($img_url) {
                    if (!get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true)) { update_post_meta($post_id, '_yoast_wpseo_opengraph-image', $img_url); }
                    if (!get_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', true)) { update_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', $thumb_id); }
                    if (!get_post_meta($post_id, '_yoast_wpseo_twitter-image', true)) { update_post_meta($post_id, '_yoast_wpseo_twitter-image', $img_url); }
                }
            }
        }
        // آینهٔ داخلی (وقتی یواست نصب نیست از همین‌ها در خروجی استفاده می‌شود)؛ آینهٔ خراب هم اصلاح می‌شود
        if (!get_post_meta($post_id, '_smv_seo_title', true)) { update_post_meta($post_id, '_smv_seo_title', get_post_meta($post_id, '_yoast_wpseo_title', true)); }
        if (self::is_junk_desc(get_post_meta($post_id, '_smv_seo_description', true))) { update_post_meta($post_id, '_smv_seo_description', get_post_meta($post_id, '_yoast_wpseo_metadesc', true)); }
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
        $total = 0; $with = 0; $per_type = array();
        foreach (self::seo_types() as $type) {
            $ids = get_posts(array('post_type' => $type, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'suppress_filters' => true));
            $t_total = 0; $t_with = 0;
            foreach ((array) $ids as $id) {
                $total++; $t_total++;
                $d1 = get_post_meta($id, '_yoast_wpseo_metadesc', true); $d2 = get_post_meta($id, '_smv_seo_description', true);
                if (($d1 && !self::is_junk_desc($d1)) || ($d2 && !self::is_junk_desc($d2))) { $with++; $t_with++; }
            }
            $per_type[$type] = array('total' => $t_total, 'with_seo' => $t_with);
        }
        $articles = get_posts(array('post_type' => 'smv_article', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids'));
        $acount = wp_count_posts('smv_article');
        return array('total' => $total, 'with_seo' => $with, 'per_type' => $per_type, 'articles' => isset($acount->publish) ? (int) $acount->publish : 0);
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

    private static function print_meta($title, $desc, $url, $img, $type = 'website') {
        if ($desc) { echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n"; }
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        echo '<meta property="og:locale" content="fa_IR">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ($desc) { echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n"; }
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($img) { echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n"; }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        if ($desc) { echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n"; }
        if ($img) { echo '<meta name="twitter:image" content="' . esc_url($img) . '">' . "\n"; }
    }

    /** وقتی یواست نیست، عنوان سند از عنوان سئوی ذخیره‌شده ساخته می‌شود. */
    public static function document_title($title) {
        if (function_exists('is_front_page') && is_front_page()) {
            $front_id = (int) get_option('page_on_front');
            if ($front_id) {
                $t = get_post_meta($front_id, '_smv_seo_title', true);
                if ($t) { return $t; }
            }
            return get_bloginfo('name') . ' | ' . self::org_tagline();
        }
        if (is_singular()) {
            $post = get_queried_object();
            if ($post && isset($post->ID)) {
                $t = get_post_meta($post->ID, '_smv_seo_title', true);
                if ($t) { return $t; }
            }
        }
        return $title;
    }

    public static function fallback_head() {
        $site = get_bloginfo('name');
        // صفحهٔ اصلی
        if (function_exists('is_front_page') && is_front_page()) {
            $front_id = (int) get_option('page_on_front');
            $post = $front_id ? get_post($front_id) : null;
            $title = ($post ? get_post_meta($front_id, '_smv_seo_title', true) : '') ?: ($site . ' | ' . self::org_tagline());
            $desc = $post ? get_post_meta($front_id, '_smv_seo_description', true) : '';
            if (!$desc && $post) { $p = self::page_profile($post); $desc = $p ? $p['desc'] : ''; }
            if (!$desc) { $desc = self::clean_desc(get_bloginfo('description')); }
            $url = home_url('/');
            $img = ($post && function_exists('get_the_post_thumbnail_url')) ? get_the_post_thumbnail_url($front_id, 'large') : '';
            self::print_meta($title, $desc, $url, $img, 'website');
            $org = array('@context' => 'https://schema.org', '@type' => 'Organization', 'name' => self::org_name(), 'url' => $url);
            $sameas = array();
            foreach (array('instagram', 'telegram', 'aparat', 'youtube', 'rubika', 'eitaa', 'bale') as $net) {
                if (function_exists('smv_get_option')) { $v = smv_get_option($net, ''); if ($v) { $sameas[] = $v; } }
            }
            if ($sameas) { $org['sameAs'] = $sameas; }
            echo '<script type="application/ld+json">' . wp_json_encode($org) . '</script>' . "\n";
            echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => $url)) . '</script>' . "\n";
            return;
        }
        // آرشیوها
        if (function_exists('is_post_type_archive') && is_post_type_archive()) {
            $type = function_exists('get_query_var') ? get_query_var('post_type') : '';
            $labels = array(
                'smv_article' => array('مقالات تئاتر | ' . $site, 'مقالات تئاتر و آموزش‌های فرهنگی هنری؛ راهنمای بازیگری، کارگردانی و تماشای بهتر نمایش در وبلاگ سماویه.'),
                'smv_theatre' => array('تئاترهای در حال اجرا | ' . $site, 'تئاترهای در حال اجرا؛ معرفی نمایش‌های روی صحنه، اطلاعات اجرا، سانس‌ها و ثبت‌نام آنلاین در سماویه.'),
                'smv_project' => array('پروژه‌های فرهنگی هنری | ' . $site, 'پروژه‌های فرهنگی هنری سماویه؛ آشنایی با طرح‌ها و فعالیت‌های فرهنگی، هنری و تئاتری گروه.'),
            );
            if (isset($labels[$type])) {
                $url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link($type) : home_url('/');
                self::print_meta($labels[$type][0], $labels[$type][1], $url, '', 'website');
                return;
            }
        }
        if (!is_singular()) { return; }
        $post = get_queried_object();
        if (!$post || !isset($post->ID)) { return; }
        $title = get_post_meta($post->ID, '_smv_seo_title', true) ?: (get_the_title($post) . ' | ' . get_bloginfo('name'));
        $desc = get_post_meta($post->ID, '_smv_seo_description', true);
        if (!$desc || self::is_junk_desc($desc)) {
            $p = ($post->post_type === 'page') ? self::page_profile($post) : null;
            $desc = $p ? $p['desc'] : self::clean_desc(function_exists('strip_shortcodes') ? strip_shortcodes((string) ($post->post_excerpt ?: $post->post_content)) : ($post->post_excerpt ?: $post->post_content));
        }
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
                <?php
                $type_labels = array('post' => 'نوشته‌ها', 'page' => 'برگه‌ها', 'smv_theatre' => 'تئاترها', 'smv_project' => 'پروژه‌ها', 'smv_article' => 'مقالات');
                if (!empty($st['per_type'])) {
                    echo '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr><th style="text-align:right;border-bottom:1px solid #ddd;padding:4px">نوع</th><th style="border-bottom:1px solid #ddd;padding:4px">دارای سئو</th><th style="border-bottom:1px solid #ddd;padding:4px">کل</th></tr>';
                    foreach ($st['per_type'] as $tp => $row) {
                        echo '<tr><td style="padding:4px">' . esc_html($type_labels[$tp] ?? $tp) . '</td><td style="text-align:center;padding:4px">' . (int) $row['with_seo'] . '</td><td style="text-align:center;padding:4px">' . (int) $row['total'] . '</td></tr>';
                    }
                    echo '</table>';
                }
                ?>
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
                    <li>برگه‌های اصلی سایت (صفحهٔ اصلی، تئاترها، پروژه‌ها، مقالات، تماس، پیگیری و فرم ثبت‌نام) پروفایل سئوی اختصاصی می‌گیرند و روی هر آپدیت افزونه، تکمیل سئو یک بار خودکار اجرا می‌شود؛ توضیح خراب ساخته‌شده از شورت‌کد هم خودکار اصلاح می‌شود.</li>
                    <li>فیلدهای Open Graph و توییتر یواست (عنوان، توضیح و تصویر) از روی تصویر شاخص و سئوی صفحه پر می‌شوند.</li>
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
