<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Settings {
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register'));
        add_filter('option_page_capability_smv_settings', array(__CLASS__, 'capability'));
    }

    public static function capability() {
        return 'manage_smv_registrations';
    }

    public static function defaults() {
        return array(
            'organization_name' => 'گروه فرهنگی هنری سماویه',
            'tagline' => 'هنر، اندیشه و معنویت',
            'phone' => '',
            'email' => '',
            'address' => '',
            'instagram' => '',
            'telegram' => '',
            'whatsapp' => '',
            'notification_email' => get_option('admin_email'),
            'zarinpal_merchant' => '',
            'home_hero_title' => 'روایتِ نور،',
            'home_hero_highlight' => 'بر صحنهٔ دل‌ها',
            'home_hero_text' => 'در سماویه، قصه‌ها را با نگاهی اصیل و زبانی هنری روایت می‌کنیم؛ برای لحظه‌هایی که در ذهن و جان می‌مانند.',
            'home_about_title' => "هنر برای دل‌ها،\nبا ریشه‌هایی از معنا",
            'home_about_text' => 'سماویه خانهٔ قصه‌هایی است که از فرهنگ و باورهای ما الهام می‌گیرند. تلاش می‌کنیم با تئاتر و فعالیت‌های فرهنگی، فضایی امن برای تجربهٔ زیبایی، گفت‌وگو و رشد هنری بسازیم.',
            'footer_about' => 'روایت قصه‌های اصیل بر صحنهٔ هنر؛ برای ساختن تجربه‌ای زیبا، ماندگار و نزدیک به فرهنگ و باورهایمان.',
            'tracking_page_id' => 0,
            'theatres_page_id' => 0,
            'projects_page_id' => 0,
            'receipt_max_size' => 5,
            'success_message' => 'درخواست ثبت‌نام شما ثبت شد. تأیید نهایی پس از بررسی فیش واریزی انجام می‌شود.',
            'payment_instructions' => '',
            'rules' => 'اطلاعات واردشده را صحیح اعلام می‌کنم و با بررسی فیش واریزی توسط مدیریت گروه موافقم.',
        );
    }

    public static function register() {
        register_setting('smv_settings', 'smv_settings', array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default' => self::defaults(),
        ));
    }

    public static function sanitize($input) {
        $defaults = self::defaults();
        $input = is_array($input) ? $input : array();
        $output = $defaults;
        $text = array('organization_name', 'tagline', 'phone', 'home_hero_title', 'home_hero_highlight', 'zarinpal_merchant');
        foreach ($text as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        }
        $areas = array('address', 'home_hero_text', 'home_about_title', 'home_about_text', 'footer_about', 'success_message', 'payment_instructions', 'rules');
        foreach ($areas as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_textarea_field($input[$key]) : '';
        }
        foreach (array('email', 'notification_email') as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_email($input[$key]) : '';
        }
        foreach (array('instagram', 'telegram', 'whatsapp') as $key) {
            $output[$key] = isset($input[$key]) ? esc_url_raw($input[$key]) : '';
        }
        $output['tracking_page_id'] = isset($input['tracking_page_id']) ? absint($input['tracking_page_id']) : 0;
        $output['theatres_page_id'] = isset($input['theatres_page_id']) ? absint($input['theatres_page_id']) : absint(smv_get_option('theatres_page_id'));
        $output['projects_page_id'] = isset($input['projects_page_id']) ? absint($input['projects_page_id']) : absint(smv_get_option('projects_page_id'));
        $output['receipt_max_size'] = isset($input['receipt_max_size']) ? min(10, max(1, absint($input['receipt_max_size']))) : 5;
        return $output;
    }

    public static function render_page() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $options = wp_parse_args(get_option('smv_settings', array()), self::defaults());
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <div class="smv-admin-heading">
                <div><span class="smv-admin-kicker">اطلاعات پایه سایت</span><h1>تنظیمات سماویه</h1><p>اطلاعات تماس و پیام‌های ثبت‌نام را از اینجا تغییر دهید؛ نیازی به کدنویسی نیست.</p></div>
            </div>
            <form method="post" action="options.php" class="smv-settings-form">
                <?php settings_fields('smv_settings'); ?>
                <section class="smv-admin-card">
                    <h2>مشخصات گروه</h2>
                    <div class="smv-settings-grid">
                        <?php self::input('organization_name', 'نام مجموعه', $options['organization_name']); ?>
                        <?php self::input('tagline', 'شعار کوتاه', $options['tagline']); ?>
                        <?php self::input('phone', 'شماره تماس', $options['phone'], 'text', 'مثال: ۰۲۱۱۲۳۴۵۶۷۸'); ?>
                        <?php self::input('email', 'ایمیل نمایشی', $options['email'], 'email'); ?>
                        <?php self::input('notification_email', 'ایمیل اطلاع‌رسانی ثبت‌نام جدید', $options['notification_email'], 'email', 'خلاصه ثبت‌نام‌های تازه به این ایمیل ارسال می‌شود.'); ?>
                        <?php self::input('zarinpal_merchant', 'کد مرچنت زرین‌پال (درگاه پرداخت)', $options['zarinpal_merchant'], 'text', 'برای فعال‌سازی پرداخت آنلاین بلیت'); ?>
                        <?php self::input('instagram', 'لینک اینستاگرام', $options['instagram'], 'url', 'https://instagram.com/...'); ?>
                        <?php self::input('telegram', 'لینک تلگرام', $options['telegram'], 'url', 'https://t.me/...'); ?>
                        <?php self::input('whatsapp', 'لینک واتس‌اپ', $options['whatsapp'], 'url', 'https://wa.me/...'); ?>
                    </div>
                    <?php self::textarea('address', 'نشانی', $options['address'], 2); ?>
                </section>
                <section class="smv-admin-card" id="smv-home-content">
                    <h2>محتوای خودکار صفحهٔ اصلی و پانوشت</h2>
                    <p class="smv-settings-help">متن‌های این بخش بلافاصله پس از ذخیره در جای مشخص‌شدهٔ سایت قرار می‌گیرند؛ بدون نیاز به المنتور یا ویرایش کد.</p>
                    <div class="smv-settings-grid">
                        <?php self::input('home_hero_title', 'عنوان اول صفحهٔ اصلی', $options['home_hero_title']); ?>
                        <?php self::input('home_hero_highlight', 'عنوان طلایی صفحهٔ اصلی', $options['home_hero_highlight']); ?>
                    </div>
                    <?php self::textarea('home_hero_text', 'متن معرفی زیر عنوان اصلی', $options['home_hero_text'], 3); ?>
                    <?php self::textarea('home_about_title', 'عنوان بخش دربارهٔ سماویه', $options['home_about_title'], 2); ?>
                    <?php self::textarea('home_about_text', 'متن بخش دربارهٔ سماویه در صفحهٔ اصلی', $options['home_about_text'], 4); ?>
                    <?php self::textarea('footer_about', 'متن معرفی کوتاه در پانوشت', $options['footer_about'], 3); ?>
                </section>
                <section class="smv-admin-card">
                    <h2>ثبت‌نام و پیگیری</h2>
                    <div class="smv-settings-grid">
                        <div class="smv-admin-field">
                            <label for="smv-tracking-page"><strong>صفحهٔ پیگیری ثبت‌نام</strong></label>
                            <?php wp_dropdown_pages(array('name' => 'smv_settings[tracking_page_id]', 'id' => 'smv-tracking-page', 'selected' => $options['tracking_page_id'], 'show_option_none' => 'انتخاب صفحه', 'option_none_value' => 0)); ?>
                            <small>صفحه‌ای که شورت‌کد [samavieh_tracking] در آن قرار دارد.</small>
                        </div>
                        <?php self::input('receipt_max_size', 'حداکثر حجم فیش (مگابایت)', $options['receipt_max_size'], 'number', 'عددی بین ۱ تا ۱۰'); ?>
                    </div>
                    <?php self::textarea('payment_instructions', 'راهنمای واریز (بالای فرم ثبت‌نام)', $options['payment_instructions'], 3); ?>
                    <p class="smv-settings-help">می‌توانید شماره کارت/حساب، نام صاحب حساب و توضیح لازم برای واریز را اینجا بنویسید. اگر خالی باشد، این بخش در سایت نمایش داده نمی‌شود.</p>
                    <?php self::textarea('success_message', 'پیام بعد از ثبت درخواست', $options['success_message'], 3); ?>
                    <?php self::textarea('rules', 'متن رضایت‌نامهٔ کنار فرم', $options['rules'], 3); ?>
                </section>
                <?php submit_button('ذخیره تنظیمات', 'smv-primary-button', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private static function input($key, $label, $value, $type = 'text', $help = '') {
        echo '<div class="smv-admin-field"><label for="smv-setting-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        $extra = ('number' === $type) ? ' min="1" max="10"' : '';
        echo '<input id="smv-setting-' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="smv_settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '"' . $extra . ' />';
        if ($help) {
            echo '<small>' . esc_html($help) . '</small>';
        }
        echo '</div>';
    }

    private static function textarea($key, $label, $value, $rows = 3) {
        echo '<div class="smv-admin-field"><label for="smv-setting-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea id="smv-setting-' . esc_attr($key) . '" rows="' . absint($rows) . '" name="smv_settings[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea></div>';
    }
}
