<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنظیمات نشان اینماد (Enamad)
 */
class SMV_Enamad {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 25);
    }

    public static function admin_menu() {
        add_submenu_page(
            'smv-dashboard',
            'تنظیمات اینماد',
            'تنظیمات اینماد',
            'manage_smv_registrations',
            'smv-enamad',
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }

        $settings = wp_parse_args(get_option('smv_enamad_settings', array()), self::defaults());

        if (isset($_POST['smv_enamad_save'])) {
            check_admin_referer('smv_enamad_settings');
            $new_settings = array(
                'enabled' => isset($_POST['smv_enamad_enabled']) ? 1 : 0,
                'seal_url' => esc_url_raw($_POST['smv_enamad_seal_url'] ?? ''),
                'verify_url' => esc_url_raw($_POST['smv_enamad_verify_url'] ?? ''),
                'footer_text' => sanitize_textarea_field($_POST['smv_enamad_footer_text'] ?? ''),
            );
            update_option('smv_enamad_settings', $new_settings);
            echo '<div class="notice notice-success is-dismissible"><p>تنظیمات اینماد ذخیره شد.</p></div>';
            $settings = $new_settings;
        }

        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <div class="smv-admin-heading">
                <div>
                    <span class="smv-admin-kicker">نشان تجارت الکترونیک</span>
                    <h1>تنظیمات اینماد</h1>
                    <p>نشان اینماد را در پایین سایت و صفحه پرداخت نمایش دهید تا مشتریان اعتماد بیشتری به سایت داشته باشند.</p>
                </div>
            </div>

            <?php if (!is_ssl()) : ?>
            <div class="notice notice-warning is-dismissible">
                <p>توجه: برای دریافت گواهینامه اینماد، سایت باید روی HTTPS (SSL) باشد. اکنون سایت با HTTP باز می‌شود.</p>
            </div>
            <?php endif; ?>

            <form method="post" class="smv-settings-form">
                <?php wp_nonce_field('smv_enamad_settings'); ?>
                <section class="smv-admin-card">
                    <h2>تنظیمات نشان اینماد</h2>
                    <div class="smv-settings-grid">
                        <div class="smv-admin-field">
                            <label for="smv_enamad_enabled"><strong>فعال‌سازی نشان اینماد</strong></label>
                            <input type="checkbox" id="smv_enamad_enabled" name="smv_enamad_enabled" value="1" <?php checked($settings['enabled'], 1); ?> />
                            <small>اگر فعال باشد، نشان اینماد در پانوشت و صفحه پرداخت نمایش داده می‌شود.</small>
                        </div>
                        <?php self::input('smv_enamad_seal_url', 'آدرس تصویر نشان اینماد', $settings['seal_url'], 'url', 'https://enamad.ir/seal/...'); ?>
                        <?php self::input('smv_enamad_verify_url', 'آدرس صفحه تایید اینماد', $settings['verify_url'], 'url', 'https://enamad.ir/verify/...'); ?>
                        <?php self::textarea('smv_enamad_footer_text', 'متن زیر نشان در پانوشت', $settings['footer_text'], 2); ?>
                    </div>
                </section>

                <section class="smv-admin-card">
                    <h2>نصب گواهینامه SSL</h2>
                    <p>برای دریافت اینماد، ابتدا باید گواهینامه SSL (HTTPS) را روی دامنه سایت نصب کنید. بعد از نصب SSL، می‌توانید درخواست اینماد را از سایت اینماد ثبت کنید و تصویر نشان و لینک تایید را در بالا وارد کنید.</p>
                    <ul>
                        <li>مرحله ۱: گواهینامه SSL از شرکت هاستینگ یا Let's Encrypt دریافت و نصب شود.</li>
                        <li>مرحله ۲: درخواست اینماد از طریق <a href="https://enamad.ir" target="_blank" rel="noopener">سایت اینماد</a> ثبت شود.</li>
                        <li>مرحله ۳: تصویر نشان و لینک تایید اینماد را در این صفحه وارد کرده و ذخیره کنید.</li>
                    </ul>
                </section>

                <?php submit_button('ذخیره تنظیمات اینماد', 'smv-primary-button', 'smv_enamad_save', false); ?>
            </form>
        </div>
        <?php
    }

    private static function input($key, $label, $value, $type = 'text', $help = '') {
        echo '<div class="smv-admin-field"><label for="smv_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input id="smv_' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if ($help) {
            echo '<small>' . esc_html($help) . '</small>';
        }
        echo '</div>';
    }

    private static function textarea($key, $label, $value, $rows = 3) {
        echo '<div class="smv-admin-field"><label for="smv_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea id="smv_' . esc_attr($key) . '" rows="' . absint($rows) . '" name="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
        echo '</div>';
    }

    public static function defaults() {
        return array(
            'enabled' => 0,
            'seal_url' => '',
            'verify_url' => '',
            'footer_text' => 'نشان اعتماد تجارت الکترونیک',
        );
    }

    public static function get_settings() {
        return wp_parse_args(get_option('smv_enamad_settings', array()), self::defaults());
    }

    public static function render_seal() {
        $settings = self::get_settings();
        if (!$settings['enabled'] || empty($settings['seal_url'])) {
            return '';
        }
        ob_start();
        ?>
        <div class="smv-enamad-seal" style="text-align:center; margin:15px 0;">
            <a href="<?php echo esc_url($settings['verify_url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($settings['footer_text']); ?>">
                <img src="<?php echo esc_url($settings['seal_url']); ?>" alt="<?php echo esc_attr($settings['footer_text']); ?>" width="100" height="100" />
            </a>
            <p style="font-size:11px; color:#666; margin-top:5px;"><?php echo esc_html($settings['footer_text']); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
