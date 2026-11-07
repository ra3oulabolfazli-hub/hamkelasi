<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ارسال پیامک ثبت‌نام از طریق درگاه‌های پیامکی ایرانی.
 *
 * درگاه‌های پشتیبانی‌شده: کاوه‌نگار، ملی‌پیامک، SMS.ir، قاصدک، فراز اس‌ام‌اس (IPPanel)
 * و درگاه دلخواه (Webhook با POST JSON).
 */
class SMV_SMS {

    const LOG_OPTION = 'smv_sms_log';
    const MAX_LOG_ENTRIES = 20;

    public static function init() {
        add_action('admin_post_smv_sms_test', array(__CLASS__, 'handle_test'));
        add_action('admin_post_smv_sms_clear_log', array(__CLASS__, 'handle_clear_log'));
    }

    /** فهرست درگاه‌ها: نام نمایشی + توضیح فیلدهای لازم */
    public static function providers() {
        return array(
            'kavenegar'   => array('label' => 'کاوه‌نگار (Kavenegar)', 'hint' => 'کلید API را از پنل کاوه‌نگار بگیرید. شمارهٔ فرستنده لازم است.'),
            'melipayamak' => array('label' => 'ملی‌پیامک (MeliPayamak)', 'hint' => 'کلید API سرویس «ارسال ساده» را از پنل ملی‌پیامک بردارید.'),
            'smsir'       => array('label' => 'SMS.ir', 'hint' => 'کلید API نسخهٔ ۱ (x-api-key) و شمارهٔ خط را وارد کنید.'),
            'ghasedak'    => array('label' => 'قاصدک (Ghasedak)', 'hint' => 'کلید API و شمارهٔ خط (linenumber).'),
            'farazsms'    => array('label' => 'فراز اس‌ام‌اس / IPPanel', 'hint' => 'کلید API سرویس Webservice و شمارهٔ فرستنده.'),
            'custom'      => array('label' => 'درگاه دلخواه (Webhook)', 'hint' => 'آدرس وب‌هوک درگاه را وارد کنید؛ JSON با کلیدهای to، message، sender و api_key ارسال می‌شود.'),
        );
    }

    public static function provider_labels() {
        $labels = array();
        foreach (self::providers() as $key => $info) {
            $labels[$key] = $info['label'];
        }
        return $labels;
    }

    public static function is_enabled() {
        return (bool) absint(smv_get_option('sms_enabled', 0));
    }

    public static function default_template() {
        return "سلام {name} عزیز،\nثبت‌نام شما در «{theatre}{showtime}» ثبت شد.\nکد پیگیری: {code}\nوضعیت: {status}\n{site}";
    }

    /**
     * جای‌گذاری متغیرها در متن پیامک.
     */
    public static function render_message($template, $data = array()) {
        $data = wp_parse_args($data, array(
            'first_name' => '',
            'last_name'  => '',
            'name'       => '',
            'phone'      => '',
            'code'       => '',
            'theatre'    => '',
            'showtime'   => '',
            'amount'     => '',
            'status'     => '',
            'site'       => get_bloginfo('name'),
        ));
        if ('' === $data['name']) {
            $data['name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        }
        if ('' === $data['name']) {
            $data['name'] = 'همراه گرامی';
        }
        if ('' !== $data['showtime'] && 0 !== strpos(ltrim($data['showtime']), '—') && 0 !== strpos(ltrim($data['showtime']), '-')) {
            $data['showtime'] = ' — ' . $data['showtime'];
        }
        if ('' === $template) {
            $template = self::default_template();
        }
        $replace = array(
            '{name}'       => $data['name'],
            '{first_name}' => $data['first_name'],
            '{last_name}'  => $data['last_name'],
            '{phone}'      => $data['phone'],
            '{code}'       => $data['code'],
            '{theatre}'    => $data['theatre'],
            '{showtime}'   => $data['showtime'],
            '{amount}'     => $data['amount'],
            '{status}'     => $data['status'],
            '{site}'       => $data['site'],
        );
        return trim(strtr($template, $replace));
    }

    /**
     * ارسال یک پیامک. خروجی: true یا WP_Error
     */
    public static function send($phone, $message, $context = 'manual') {
        $phone = SMV_Persian::normalize_phone((string) $phone);
        $message = trim((string) $message);

        if (!$phone || !preg_match('/^09\d{9}$/', $phone)) {
            return new WP_Error('smv_sms_phone', 'شمارهٔ موبایل معتبر نیست.');
        }
        if ('' === $message) {
            return new WP_Error('smv_sms_empty', 'متن پیامک خالی است.');
        }
        if (!self::is_enabled()) {
            return new WP_Error('smv_sms_disabled', 'ارسال پیامک در تنظیمات غیرفعال است.');
        }

        $provider = (string) smv_get_option('sms_provider', 'kavenegar');
        $api_key = trim((string) smv_get_option('sms_api_key'));
        $sender = trim((string) smv_get_option('sms_sender'));

        if ('custom' === $provider) {
            if (!smv_get_option('sms_custom_url')) {
                return new WP_Error('smv_sms_config', 'آدرس وب‌هوک درگاه پیامک تنظیم نشده است.');
            }
        } elseif ('' === $api_key) {
            return new WP_Error('smv_sms_config', 'کلید API درگاه پیامک تنظیم نشده است.');
        }

        $request = self::build_request($provider, $phone, $message, $api_key, $sender);
        if (is_wp_error($request)) {
            self::log_entry($phone, $provider, $context, false, $request->get_error_message());
            return $request;
        }

        $response = wp_remote_post($request['url'], $request['args']);
        $body = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
        $http_code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        if (is_wp_error($response)) {
            self::log_entry($phone, $provider, $context, false, $response->get_error_message());
            return $response;
        }

        $error = self::detect_error($provider, $body, $http_code);
        if ($error) {
            self::log_entry($phone, $provider, $context, false, $error . ' | ' . self::snippet($body));
            return new WP_Error('smv_sms_failed', $error);
        }

        self::log_entry($phone, $provider, $context, true, self::snippet($body));
        do_action('smv_sms_sent', $phone, $message, $provider, $context);
        return true;
    }

    /**
     * ساخت درخواست HTTP هر درگاه.
     */
    private static function build_request($provider, $phone, $message, $api_key, $sender) {
        $json_headers = array('Content-Type' => 'application/json; charset=utf-8', 'Accept' => 'application/json');

        switch ($provider) {
            case 'kavenegar':
                $url = 'https://api.kavenegar.com/v1/' . rawurlencode($api_key) . '/sms/send.json';
                $body = array('receptor' => $phone, 'message' => $message);
                if ($sender) {
                    $body['sender'] = $sender;
                }
                return array('url' => $url, 'args' => array('timeout' => 20, 'body' => $body));

            case 'melipayamak':
                $url = 'https://console.melipayamak.com/api/send/simple/' . rawurlencode($api_key);
                $body = array('from' => $sender, 'to' => $phone, 'text' => $message);
                return array('url' => $url, 'args' => array('timeout' => 20, 'headers' => $json_headers, 'body' => wp_json_encode($body)));

            case 'smsir':
                $url = 'https://api.sms.ir/v1/send/bulk';
                $headers = $json_headers;
                $headers['x-api-key'] = $api_key;
                $body = array(
                    'lineNumber' => $sender,
                    'messageText' => $message,
                    'mobiles' => array($phone),
                );
                return array('url' => $url, 'args' => array('timeout' => 20, 'headers' => $headers, 'body' => wp_json_encode($body)));

            case 'ghasedak':
                $url = 'https://api.ghasedak.me/v2/sms/send/simple';
                $headers = array('apikey' => $api_key, 'Content-Type' => 'application/x-www-form-urlencoded');
                $body = array('receptor' => $phone, 'message' => $message, 'linenumber' => $sender);
                return array('url' => $url, 'args' => array('timeout' => 20, 'headers' => $headers, 'body' => $body));

            case 'farazsms':
                $url = 'https://api2.ippanel.com/api/v1/sms/send/webservice/single';
                $headers = $json_headers;
                $headers['Authorization'] = $api_key;
                $body = array('recipient' => array($phone), 'sender' => $sender, 'message' => $message);
                return array('url' => $url, 'args' => array('timeout' => 20, 'headers' => $headers, 'body' => wp_json_encode($body)));

            case 'custom':
                $url = (string) smv_get_option('sms_custom_url');
                if (!preg_match('#^https?://#i', $url)) {
                    return new WP_Error('smv_sms_config', 'آدرس وب‌هوک باید با http یا https شروع شود.');
                }
                $headers = $json_headers;
                if ($api_key) {
                    $headers['Authorization'] = $api_key;
                }
                $body = array('to' => $phone, 'message' => $message, 'sender' => $sender, 'api_key' => $api_key);
                return array('url' => $url, 'args' => array('timeout' => 20, 'headers' => $headers, 'body' => wp_json_encode($body)));

            default:
                return new WP_Error('smv_sms_provider', 'درگاه پیامک انتخاب‌شده پشتیبانی نمی‌شود.');
        }
    }

    /**
     * تشخیص خطای درگاه از روی پاسخ (بعضی درگاه‌ها خطا را با HTTP 200 برمی‌گردانند).
     */
    private static function detect_error($provider, $body, $http_code) {
        $json = json_decode($body, true);

        if ('kavenegar' === $provider && is_array($json) && isset($json['return']['status'])) {
            if (200 !== (int) $json['return']['status']) {
                return isset($json['return']['message']) ? (string) $json['return']['message'] : 'کاوه‌نگار درخواست را نپذیرفت.';
            }
        }
        if ('smsir' === $provider && is_array($json) && isset($json['status'])) {
            if (1 !== (int) $json['status']) {
                return isset($json['message']) ? (string) $json['message'] : 'SMS.ir درخواست را نپذیرفت.';
            }
        }
        if ('ghasedak' === $provider && is_array($json) && isset($json['result']['code'])) {
            if (200 !== (int) $json['result']['code']) {
                return isset($json['result']['message']) ? (string) $json['result']['message'] : 'قاصدک درخواست را نپذیرفت.';
            }
        }
        if ($http_code >= 400) {
            return 'درگاه پیامک با کد ' . $http_code . ' پاسخ داد.';
        }
        return '';
    }

    private static function snippet($body) {
        $body = is_string($body) ? $body : '';
        $body = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($body)));
        if (function_exists('mb_substr')) {
            return mb_substr($body, 0, 180, 'UTF-8');
        }
        return substr($body, 0, 180);
    }

    public static function mask_phone($phone) {
        $phone = (string) $phone;
        if (strlen($phone) < 7) {
            return $phone;
        }
        return substr($phone, 0, 4) . '***' . substr($phone, -3);
    }

    private static function log_entry($phone, $provider, $context, $ok, $detail) {
        $log = get_option(self::LOG_OPTION, array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, array(
            'time'     => current_time('mysql'),
            'phone'    => self::mask_phone($phone),
            'provider' => $provider,
            'context'  => $context,
            'ok'       => (bool) $ok,
            'detail'   => (string) $detail,
        ));
        $log = array_slice($log, 0, self::MAX_LOG_ENTRIES);
        update_option(self::LOG_OPTION, $log, false);
    }

    public static function logs() {
        $log = get_option(self::LOG_OPTION, array());
        return is_array($log) ? $log : array();
    }

    public static function last_log() {
        $log = self::logs();
        return $log ? $log[0] : null;
    }

    /**
     * پیامک ثبت‌نام (به ثبت‌نام‌کننده + اطلاع به مدیر در صورت تنظیم شماره)
     */
    public static function send_registration($data) {
        if (!self::is_enabled()) {
            return false;
        }
        $data = wp_parse_args($data, array(
            'phone'      => '',
            'first_name' => '',
            'last_name'  => '',
            'code'       => '',
            'theatre'    => '',
            'showtime'   => '',
            'amount'     => '',
            'status'     => 'در انتظار بررسی',
        ));

        $template = (string) smv_get_option('sms_template');
        $message = self::render_message($template, $data);
        $result = self::send($data['phone'], $message, 'registration');

        $admin_phone = SMV_Persian::normalize_phone((string) smv_get_option('sms_admin_phone'));
        if ($admin_phone && preg_match('/^09\d{9}$/', $admin_phone)) {
            $admin_message = 'ثبت‌نام جدید: ' . $data['first_name'] . ' ' . $data['last_name']
                . "\nاجرا: " . $data['theatre']
                . "\nکد پیگیری: " . $data['code'];
            self::send($admin_phone, $admin_message, 'admin_alert');
        }

        return $result;
    }

    /**
     * پیامک تغییر وضعیت ثبت‌نام (تأیید/رد/در انتظار)
     */
    public static function send_status_update($row, $status_label) {
        if (!self::is_enabled() || !absint(smv_get_option('sms_on_status', 0))) {
            return false;
        }
        if (!is_object($row) || empty($row->phone)) {
            return false;
        }
        $theatre = $row->theatre_id ? get_the_title($row->theatre_id) : '';
        $message = "وضعیت ثبت‌نام شما در «{$theatre}» به «{$status_label}» تغییر کرد.\nکد پیگیری: {$row->tracking_code}";
        return self::send($row->phone, $message, 'status_change');
    }

    /**
     * ارسال پیامک آزمایشی از صفحهٔ تنظیمات.
     */
    public static function handle_test() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_sms_test');

        $phone = isset($_POST['sms_test_phone']) ? sanitize_text_field(wp_unslash($_POST['sms_test_phone'])) : '';
        $message = 'پیامک آزمایشی سماویه؛ اگر این متن را می‌بینید، درگاه پیامک درست تنظیم شده است.';
        $result = self::send($phone, $message, 'test');

        $args = array('page' => 'smv-settings');
        if (is_wp_error($result)) {
            $args['sms_test'] = 'fail';
            $args['sms_msg'] = rawurlencode($result->get_error_message());
        } else {
            $args['sms_test'] = 'ok';
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_clear_log() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_sms_clear_log');
        update_option(self::LOG_OPTION, array(), false);
        wp_safe_redirect(add_query_arg(array('page' => 'smv-settings', 'sms_log' => 'cleared'), admin_url('admin.php')));
        exit;
    }
}
