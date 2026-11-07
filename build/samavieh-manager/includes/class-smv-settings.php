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
            'rubika' => '',
            'bale' => '',
            'eitaa' => '',
            'aparat' => '',
            'youtube' => '',
            'twitter' => '',
            // پیامک
            'sms_enabled' => 0,
            'sms_provider' => 'kavenegar',
            'sms_api_key' => '',
            'sms_sender' => '',
            'sms_custom_url' => '',
            'sms_template' => '', // خالی = متن پیش‌فرض SMV_SMS::default_template()
            'sms_admin_phone' => '',
            'sms_on_status' => 1,
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
        foreach (array('instagram', 'telegram', 'whatsapp', 'rubika', 'bale', 'eitaa', 'aparat', 'youtube', 'twitter') as $key) {
            $output[$key] = isset($input[$key]) ? esc_url_raw(trim((string) $input[$key])) : '';
        }
        // پیامک
        $output['sms_enabled'] = empty($input['sms_enabled']) ? 0 : 1;
        $output['sms_on_status'] = empty($input['sms_on_status']) ? 0 : 1;
        $providers = class_exists('SMV_SMS') ? SMV_SMS::provider_labels() : array('kavenegar' => 'کاوه‌نگار');
        $provider = isset($input['sms_provider']) ? sanitize_key($input['sms_provider']) : 'kavenegar';
        $output['sms_provider'] = isset($providers[$provider]) ? $provider : 'kavenegar';
        $output['sms_api_key'] = isset($input['sms_api_key']) ? sanitize_text_field($input['sms_api_key']) : '';
        $output['sms_sender'] = isset($input['sms_sender']) ? sanitize_text_field($input['sms_sender']) : '';
        $output['sms_custom_url'] = isset($input['sms_custom_url']) ? esc_url_raw($input['sms_custom_url']) : '';
        $output['sms_template'] = isset($input['sms_template']) ? sanitize_textarea_field($input['sms_template']) : '';
        $output['sms_admin_phone'] = isset($input['sms_admin_phone']) ? preg_replace('/[^0-9]/', '', SMV_Persian::english_digits($input['sms_admin_phone'])) : '';
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

                    </div>
                    <?php self::textarea('address', 'نشانی', $options['address'], 2); ?>
                </section>

                <section class="smv-admin-card" id="smv-social-settings">
                    <h2><span class="dashicons dashicons-share"></span> شبکه‌های اجتماعی و پیام‌رسان‌ها</h2>
                    <p class="smv-settings-help">لینک هر شبکه را وارد کنید؛ هر کدام که خالی بماند در سایت نمایش داده نمی‌شود. روبیکا، ایتا و بله در اولویت نمایش هستند و با نشان رنگی خودشان نمایش داده می‌شوند.</p>
                    <div class="smv-settings-grid">
                        <?php
                        $social_hints = array(
                            'rubika' => 'https://rubika.ir/...',
                            'eitaa' => 'https://eitaa.com/...',
                            'bale' => 'https://ble.ir/...',
                            'telegram' => 'https://t.me/...',
                            'instagram' => 'https://instagram.com/...',
                            'aparat' => 'https://aparat.com/...',
                            'youtube' => 'https://youtube.com/@...',
                            'whatsapp' => 'https://wa.me/98...',
                            'twitter' => 'https://x.com/...',
                        );
                        $social_labels = array(
                            'rubika' => 'لینک کانال روبیکا',
                            'eitaa' => 'لینک کانال ایتا',
                            'bale' => 'لینک کانال بله',
                            'telegram' => 'لینک کانال تلگرام',
                            'instagram' => 'لینک اینستاگرام',
                            'aparat' => 'لینک آپارات',
                            'youtube' => 'لینک یوتیوب',
                            'whatsapp' => 'لینک واتس‌اپ',
                            'twitter' => 'لینک ایکس (توییتر)',
                        );
                        foreach ($social_labels as $key => $label) {
                            self::input($key, $label, $options[$key], 'url', $social_hints[$key]);
                        }
                        ?>
                    </div>
                </section>

                <section class="smv-admin-card" id="smv-sms-settings">
                    <h2><span class="dashicons dashicons-email-alt"></span> پیامک ثبت‌نام (SMS)</h2>
                    <p class="smv-settings-help">با فعال‌کردن این بخش، پس از هر ثبت‌نام موفق یک پیامک شامل کد پیگیری برای ثبت‌نام‌کننده ارسال می‌شود.</p>
                    <div class="smv-settings-grid">
                        <?php
                        self::checkbox('sms_enabled', 'ارسال پیامک فعال باشد', $options['sms_enabled'], 'اگر خاموش باشد هیچ پیامکی ارسال نمی‌شود.');
                        self::checkbox('sms_on_status', 'پیامک تغییر وضعیت (تأیید/رد)', $options['sms_on_status'], 'هنگام تغییر وضعیت ثبت‌نام، پیامک اطلاع‌رسانی ارسال می‌شود.');
                        self::select('sms_provider', 'درگاه پیامک', $options['sms_provider'], SMV_SMS::provider_labels());
                        self::input('sms_api_key', 'کلید API درگاه', $options['sms_api_key'], 'text', 'از پنل درگاه پیامکی خود کپی کنید.');
                        self::input('sms_sender', 'شمارهٔ خط / فرستنده', $options['sms_sender'], 'text', 'مثلاً 10004346');
                        self::input('sms_admin_phone', 'شمارهٔ مدیر برای اطلاع‌رسانی', $options['sms_admin_phone'], 'text', 'اختیاری؛ با هر ثبت‌نام جدید یک پیامک به این شماره می‌رود.');
                        self::input('sms_custom_url', 'آدرس وب‌هوک (فقط درگاه دلخواه)', $options['sms_custom_url'], 'url', 'https://example.com/sms/send');
                        ?>
                    </div>
                    <div class="smv-admin-field">
                        <label for="smv-setting-sms-template"><strong>متن پیامک</strong><small>متغیرها: {name} {first_name} {code} {theatre} {showtime} {amount} {status} {site}</small></label>
                        <textarea id="smv-setting-sms-template" rows="4" name="smv_settings[sms_template]" placeholder="<?php echo esc_attr(SMV_SMS::default_template()); ?>"><?php echo esc_textarea($options['sms_template']); ?></textarea>
                        <small>اگر خالی بماند، متن پیش‌فرض بالا استفاده می‌شود.</small>
                    </div>
                    <?php
                    $provider_info = SMV_SMS::providers();
                    $current_provider = isset($provider_info[$options['sms_provider']]) ? $provider_info[$options['sms_provider']] : null;
                    if ($current_provider) :
                        ?>
                        <p class="smv-settings-help">راهنمای درگاه انتخاب‌شده: <?php echo esc_html($current_provider['hint']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($_GET['sms_test'])) : ?>
                        <?php if ('ok' === $_GET['sms_test']) : ?>
                            <div class="notice notice-success inline"><p>پیامک آزمایشی ارسال شد. وضعیت را در جدول «آخرین ارسال‌ها» ببینید.</p></div>
                        <?php else : ?>
                            <div class="notice notice-error inline"><p>ارسال پیامک آزمایشی ناموفق بود: <?php echo esc_html(isset($_GET['sms_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['sms_msg']))) : 'خطای نامشخص'); ?></p></div>
                        <?php endif; ?>
                    <?php endif; ?>
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

                <section class="smv-admin-card">
                    <h2><span class="dashicons dashicons-smartphone"></span> آزمون درگاه و آخرین ارسال‌ها</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="smv-sms-test-form">
                        <?php wp_nonce_field('smv_sms_test'); ?>
                        <input type="hidden" name="action" value="smv_sms_test" />
                        <div class="smv-sms-test-row">
                            <input type="tel" name="sms_test_phone" dir="ltr" placeholder="09121234567" value="<?php echo esc_attr(SMV_Persian::normalize_phone(smv_get_option('sms_admin_phone', ''))); ?>" required />
                            <button type="submit" class="button smv-primary-button">ارسال پیامک آزمایشی</button>
                        </div>
                        <small>قبل از ذخیرهٔ تنظیمات جدید، ابتدا آن‌ها را ذخیره کنید و سپس آزمایش بگیرید.</small>
                    </form>
                    <div class="smv-table-wrap smv-sms-log-wrap">
                        <table class="smv-registration-table">
                            <thead><tr><th>زمان</th><th>شماره</th><th>درگاه</th><th>نوع</th><th>نتیجه</th><th>جزئیات</th></tr></thead>
                            <tbody>
                            <?php $logs = SMV_SMS::logs(); ?>
                            <?php if ($logs) : ?>
                                <?php foreach ($logs as $entry) : ?>
                                    <tr>
                                        <td data-label="زمان"><?php echo esc_html(SMV_Persian::date(isset($entry['time']) ? $entry['time'] : '', true)); ?></td>
                                        <td data-label="شماره"><bdi dir="ltr"><?php echo esc_html(isset($entry['phone']) ? $entry['phone'] : ''); ?></bdi></td>
                                        <td data-label="درگاه"><?php echo esc_html(isset($provider_info[$entry['provider']]) ? $provider_info[$entry['provider']]['label'] : $entry['provider']); ?></td>
                                        <td data-label="نوع"><?php echo esc_html(self::context_label(isset($entry['context']) ? $entry['context'] : '')); ?></td>
                                        <td data-label="نتیجه"><span class="smv-table-status <?php echo !empty($entry['ok']) ? 'status-approved' : 'status-rejected'; ?>"><?php echo !empty($entry['ok']) ? 'موفق' : 'ناموفق'; ?></span></td>
                                        <td data-label="جزئیات"><small dir="ltr"><?php echo esc_html(isset($entry['detail']) ? $entry['detail'] : ''); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="6" data-label="نتیجه">هنوز پیامکی ارسال نشده است.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-smv-confirm="گزارش ارسال‌ها پاک شود؟">
                        <?php wp_nonce_field('smv_sms_clear_log'); ?>
                        <input type="hidden" name="action" value="smv_sms_clear_log" />
                        <button type="submit" class="button">پاک‌کردن گزارش</button>
                    </form>
                </section>
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

    private static function checkbox($key, $label, $value, $help = '') {
        echo '<div class="smv-admin-field smv-admin-field-checkbox">';
        echo '<label class="smv-checkbox-label"><input type="hidden" name="smv_settings[' . esc_attr($key) . ']" value="0" /><input type="checkbox" id="smv-setting-' . esc_attr($key) . '" name="smv_settings[' . esc_attr($key) . ']" value="1" ' . checked(1, (int) $value, false) . ' /> <strong>' . esc_html($label) . '</strong></label>';
        if ($help) {
            echo '<small>' . esc_html($help) . '</small>';
        }
        echo '</div>';
    }

    private static function select($key, $label, $value, $options, $help = '') {
        echo '<div class="smv-admin-field"><label for="smv-setting-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<select id="smv-setting-' . esc_attr($key) . '" name="smv_settings[' . esc_attr($key) . ']">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if ($help) {
            echo '<small>' . esc_html($help) . '</small>';
        }
        echo '</div>';
    }

    /** برچسب فارسی نوع پیامک در گزارش ارسال‌ها */
    private static function context_label($context) {
        $labels = array(
            'registration' => 'ثبت‌نام',
            'status_change' => 'تغییر وضعیت',
            'admin_alert' => 'اطلاع به مدیر',
            'test' => 'آزمایشی',
            'manual' => 'دستی',
        );
        return isset($labels[$context]) ? $labels[$context] : $context;
    }

    private static function textarea($key, $label, $value, $rows = 3) {
        echo '<div class="smv-admin-field"><label for="smv-setting-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea id="smv-setting-' . esc_attr($key) . '" rows="' . absint($rows) . '" name="smv_settings[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea></div>';
    }
}
