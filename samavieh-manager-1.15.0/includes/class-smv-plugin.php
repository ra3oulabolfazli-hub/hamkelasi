<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SMV_Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        SMV_CPT::init();
        SMV_Meta::init();
        SMV_Settings::init();
        SMV_Forms::init();
        SMV_Shortcodes::init();
        SMV_Admin::init();
        SMV_Elementor::init();

        // ماژول‌هایی که قبلاً هیچ‌گاه مقداردهی نمی‌شدند (آجاکس و اکشن‌های آن‌ها کار نمی‌کرد)
        if (class_exists('SMV_Booking')) {
            SMV_Booking::init();
        }
        if (class_exists('SMV_Payment')) {
            SMV_Payment::init();
        }
        if (class_exists('SMV_Poll')) {
            SMV_Poll::init();
        }
        if (class_exists('SMV_Review')) {
            SMV_Review::init();
        }
        if (class_exists('SMV_Social')) {
            SMV_Social::init();
        }
        if (class_exists('SMV_SMS')) {
            SMV_SMS::init();
        }
        if (class_exists('SMV_Backup')) {
            SMV_Backup::init();
        }
        if (class_exists('SMV_Articles')) {
            SMV_Articles::init();
        }
        if (class_exists('SMV_SEO')) {
            SMV_SEO::init();
        }

        add_action('init', array($this, 'maybe_upgrade_db'), 5);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_filter('body_class', array($this, 'body_class'));
        add_filter('the_content', array($this, 'single_theatre_content'), 20);
    }

    public function maybe_upgrade_db() {
        $installed = get_option('smv_db_version');
        if ($installed === SMV_DB_VERSION) {
            return;
        }
        SMV_Activator::create_tables();
        SMV_Activator::create_roles();

        // ترمیم داده‌های قبلی: سانس‌هایی که به دلیل نبود مجوز، «در انتظار» ذخیره شده‌اند
        // در سایت دیده نمی‌شدند و باعث بسته شدن ثبت‌نام می‌شدند.
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->posts} SET post_status = 'publish' WHERE post_type = 'smv_showtime' AND post_status = 'pending'");

        // یک‌بار: عنوان سانس‌ها به تاریخ شمسی + بازهٔ ساعت تبدیل می‌شود.
        if (!get_option('smv_showtime_titles_jalali')) {
            $showtime_ids = get_posts(array(
                'post_type' => 'smv_showtime',
                'post_status' => 'any',
                'numberposts' => 500,
                'fields' => 'ids',
                'suppress_filters' => true,
            ));
            foreach ((array) $showtime_ids as $showtime_id) {
                $date = get_post_meta($showtime_id, '_smv_showtime_date', true);
                $time = get_post_meta($showtime_id, '_smv_showtime_time', true);
                $time_end = get_post_meta($showtime_id, '_smv_showtime_time_end', true);
                if ($date) {
                    $title = smv_showtime_title($date, $time, $time_end);
                    if ($title && get_the_title($showtime_id) !== $title) {
                        wp_update_post(array('ID' => $showtime_id, 'post_title' => $title));
                    }
                }
            }
            update_option('smv_showtime_titles_jalali', 1);
        }

        flush_rewrite_rules(false);
        update_option('smv_db_version', SMV_DB_VERSION);
        delete_option('smv_needs_cap_refresh');
    }

    public function register_assets() {
        wp_register_style(
            'smv-frontend',
            SMV_URL . 'assets/css/frontend.css',
            array(),
            SMV_VERSION
        );
        wp_register_script(
            'smv-frontend',
            SMV_URL . 'assets/js/frontend.js',
            array(),
            SMV_VERSION,
            true
        );
        wp_localize_script('smv-frontend', 'smvFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'copied' => 'کد پیگیری کپی شد.',
            'copy' => 'کپی کد',
            'sending' => 'در حال ارسال…',
            'maxSize' => (int) smv_get_option('receipt_max_size', 5) * MB_IN_BYTES,
            'sizeError' => 'حجم فایل بیشتر از حد مجاز است.',
        ));
    }

    public static function enqueue_frontend() {
        if (!wp_style_is('smv-frontend', 'registered')) {
            self::instance()->register_assets();
        }
        wp_enqueue_style('smv-frontend');
        wp_enqueue_script('smv-frontend');
    }

    public function body_class($classes) {
        $classes[] = 'smv-site';
        return $classes;
    }

    public function single_theatre_content($content) {
        if (is_admin() || !is_singular('smv_theatre') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (defined('ELEMENTOR_VERSION') && class_exists('\Elementor\Plugin')) {
            $editor = \Elementor\Plugin::instance()->editor;
            if ($editor && $editor->is_edit_mode()) {
                return $content;
            }
        }

        $elementor_data = get_post_meta(get_the_ID(), '_elementor_data', true);
        $elementor_data = is_string($elementor_data) ? $elementor_data : '';
        $has_details_widget = (false !== strpos($elementor_data, 'smv-theatre-details') || false !== strpos($elementor_data, 'samavieh_theatre_details'));

        if (has_shortcode($content, 'samavieh_theatre_details') || $has_details_widget) {
            return $content;
        }

        // شورت‌کد جزئیات، خودش فرم ثبت‌نام را هم نمایش می‌دهد؛
        // بنابراین فرم ثبت‌نام دیگر به‌صورت جداگانه (و تکراری) اضافه نمی‌شود.
        return do_shortcode('[samavieh_theatre_details]') . $content;
    }
}
