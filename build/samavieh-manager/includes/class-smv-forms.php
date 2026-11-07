<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Forms {
    public static function init() {
        add_action('admin_post_nopriv_smv_register', array(__CLASS__, 'handle_registration'));
        add_action('admin_post_smv_register', array(__CLASS__, 'handle_registration'));
        add_action('admin_post_nopriv_smv_track', array(__CLASS__, 'handle_tracking'));
        add_action('admin_post_smv_track', array(__CLASS__, 'handle_tracking'));
    }

    public static function state() {
        return self::consume_state('register');
    }

    public static function tracking_state() {
        return self::consume_state('track');
    }

    private static function consume_state($expected) {
        if (empty($_GET['smv_state'])) {
            return null;
        }
        $token = sanitize_text_field(wp_unslash($_GET['smv_state']));
        if (!preg_match('/^[A-Za-z0-9]{20,40}$/', $token)) {
            return null;
        }
        $state = get_transient('smv_state_' . $token);
        if (!is_array($state) || empty($state['type']) || $expected !== $state['type']) {
            // وضعیت مربوط به فرم دیگری است؛ آن را حذف نمی‌کنیم تا فرم مربوطه پیام را از دست ندهد.
            return null;
        }
        delete_transient('smv_state_' . $token);
        return $state;
    }

    /**
     * یک «پیام یک‌بارمصرف» می‌سازد و آدرس بازگشت را برمی‌گرداند.
     * برای استفاده در بازگشت از درگاه پرداخت هم عمومی است.
     */
    public static function make_state_url($type, $data, $anchor = 'smv-registration', $return_url = '') {
        $data['type'] = $type;
        $token = wp_generate_password(32, false, false);
        set_transient('smv_state_' . $token, $data, 5 * MINUTE_IN_SECONDS);

        if (!$return_url) {
            $return_url = isset($_POST['smv_return_url']) ? esc_url_raw(wp_unslash($_POST['smv_return_url'])) : home_url('/');
        }
        $return_url = wp_validate_redirect($return_url, home_url('/'));
        $return_url = preg_replace('/#.*$/', '', $return_url);
        $return_url = remove_query_arg('smv_state', $return_url);
        return add_query_arg('smv_state', $token, $return_url) . ($anchor ? '#' . sanitize_title($anchor) : '');
    }

    /**
     * ساخت داده‌های نمایش نتیجهٔ پیگیری از یک ردیف ثبت‌نام.
     */
    public static function tracking_result_from_row($row) {
        if (!$row) {
            return array();
        }
        return array(
            'tracking_code' => $row->tracking_code,
            'status' => $row->status,
            'status_label' => smv_status_label($row->status),
            'admin_note' => isset($row->admin_note) ? $row->admin_note : '',
            'theatre' => get_the_title($row->theatre_id),
            'showtime' => !empty($row->showtime_id) ? get_the_title($row->showtime_id) : '',
            'payment_type_label' => smv_payment_type_label(isset($row->payment_type) ? $row->payment_type : 'free'),
            'companions' => isset($row->companions) ? absint($row->companions) : 0,
            'people_count' => function_exists('smv_registration_people_count') ? smv_registration_people_count(isset($row->companions) ? $row->companions : 0) : 1,
            'amount' => absint(isset($row->amount) ? $row->amount : 0),
            'name' => $row->first_name . ' ' . $row->last_name,
            'created_at' => SMV_Persian::date($row->created_at, true),
            'updated_at' => SMV_Persian::date($row->updated_at, true),
        );
    }

    private static function redirect($type, $data, $anchor = 'smv-registration') {
        wp_safe_redirect(self::make_state_url($type, $data, $anchor));
        exit;
    }

    private static function fail($message, $old = array(), $type = 'register', $anchor = 'smv-registration') {
        self::redirect($type, array(
            'success' => false,
            'message' => $message,
            'old' => $old,
        ), $anchor);
    }

    private static function validate_name($value) {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        return $length >= 2 && $length <= 100 && preg_match('/^[\p{L}\p{M}\s\-\x{200C}]+$/u', $value);
    }

    /**
     * بررسی اینکه سانس انتخاب‌شده واقعاً به همین تئاتر تعلق دارد.
     */
    private static function showtime_belongs_to_theatre($showtime_id, $theatre_id) {
        if (!$showtime_id) {
            return false;
        }
        if ('smv_showtime' !== get_post_type($showtime_id)) {
            return false;
        }
        return absint(get_post_meta($showtime_id, '_smv_theatre_id', true)) === absint($theatre_id);
    }

    public static function handle_registration() {
        $theatre_id = isset($_POST['theatre_id']) ? absint($_POST['theatre_id']) : 0;
        $showtime_id = isset($_POST['showtime_id']) ? absint($_POST['showtime_id']) : 0;
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field(wp_unslash($_POST['payment_type'])) : '';
        $financial_aid_amount = isset($_POST['financial_aid_amount']) ? smv_parse_amount(wp_unslash($_POST['financial_aid_amount'])) : 0;
        $financial_aid_reason = isset($_POST['financial_aid_reason']) ? sanitize_textarea_field(wp_unslash($_POST['financial_aid_reason'])) : '';
        // تعداد همراه: ۰ (همراه ندارم)، ۱ یا ۲ نفر — هزینه و مصرف ظرفیت بر اساس مجموع افراد است.
        $companions = isset($_POST['companions']) ? max(0, min(2, absint($_POST['companions']))) : 0;
        $people_count = function_exists('smv_registration_people_count') ? smv_registration_people_count($companions) : (1 + $companions);

        $old = array(
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'showtime_id' => $showtime_id,
            'payment_type' => $payment_type,
            'financial_aid_amount' => $financial_aid_amount,
            'financial_aid_reason' => $financial_aid_reason,
            'companions' => $companions,
        );

        $nonce = isset($_POST['smv_register_nonce']) ? sanitize_text_field(wp_unslash($_POST['smv_register_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'smv_register_' . $theatre_id)) {
            self::fail('اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', $old);
        }
        if (!empty($_POST['smv_website'])) {
            self::fail('ارسال فرم انجام نشد. لطفاً دوباره تلاش کنید.', $old);
        }
        if (!$theatre_id || !smv_theatre_registration_open($theatre_id)) {
            self::fail('ثبت‌نام این تئاتر در حال حاضر بسته یا ظرفیت آن تکمیل شده است.', $old);
        }

        $has_showtimes = (bool) smv_get_showtimes_for_theatre($theatre_id);
        if ($has_showtimes) {
            if (!$showtime_id) {
                self::fail('لطفاً یک سانس را انتخاب کنید.', $old);
            }
            if (!self::showtime_belongs_to_theatre($showtime_id, $theatre_id)) {
                self::fail('سانس انتخاب‌شده معتبر نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.', $old);
            }
            $remaining = smv_showtime_seats_remaining($showtime_id);
            if (null !== $remaining && $remaining <= 0) {
                self::fail('ظرفیت این سانس تکمیل شده است (تکمیل ظرفیت). سانس بعدی به‌صورت خودکار فعال شده است؛ صفحه را تازه‌سازی کنید.', $old);
            }
            if (null !== $remaining && $remaining < $people_count) {
                self::fail('ظرفیت باقی‌ماندهٔ این سانس برای ' . SMV_Persian::digits($people_count) . ' نفر کافی نیست (فقط ' . SMV_Persian::digits($remaining) . ' صندلی خالی مانده است). تعداد همراه را کمتر کنید یا سانس دیگری انتخاب کنید.', $old);
            }
            // حالت ترتیبی: در هر روز فقط سانس فعالِ همان روز؛ حالت آزاد: هر سانس دارای ظرفیت.
            $is_active_today = function_exists('smv_showtime_is_active') ? smv_showtime_is_active($showtime_id, $theatre_id) : true;
            if (!$is_active_today) {
                self::fail('سانس‌های هر روز به ترتیب اولویت پر می‌شوند؛ این سانس هنوز فعال نشده است. ابتدا سانسِ قبلیِ همان روز باید تکمیل شود.', $old);
            }
        } else {
            $showtime_id = 0;
        }

        if (!self::validate_name($old['first_name']) || !self::validate_name($old['last_name'])) {
            self::fail('نام و نام خانوادگی را کامل و فقط با حروف وارد کنید.', $old);
        }

        $phone = SMV_Persian::normalize_phone($old['phone']);
        if (!preg_match('/^09\d{9}$/', $phone)) {
            self::fail('شماره تماس را به صورت شماره موبایل ۱۱ رقمی وارد کنید؛ مثلاً 09121234567.', $old);
        }

        // فقط روش‌های پرداخت فعالِ همین اجرا پذیرفته می‌شوند.
        $enabled_methods = smv_theatre_payment_methods($theatre_id);
        if (!$payment_type || !in_array($payment_type, $enabled_methods, true)) {
            self::fail('روش پرداخت انتخاب‌شده برای این اجرا فعال نیست.', $old);
        }

        $amount = 0;
        if ('financial_aid' === $payment_type) {
            // کمک مالی: مبلغ را خود شخص مشخص می‌کند.
            if ($financial_aid_amount <= 0) {
                self::fail('لطفاً مبلغ کمک مالی را خودتان وارد کنید.', $old);
            }
            $amount = $financial_aid_amount;
        } elseif ('paid' === $payment_type) {
            // هزینهٔ ثبت‌نام: مبلغ از قبل مشخص است (قیمت سانس، و اگر نبود قیمت اجرا)
            $fee_per_person = function_exists('smv_registration_fixed_fee') ? smv_registration_fixed_fee($theatre_id, $showtime_id) : 0;
            if ($fee_per_person <= 0) {
                self::fail('هزینهٔ ثبت‌نام این اجرا مشخص نشده است. لطفاً روش دیگری (رایگان یا کمک مالی) انتخاب کنید.', $old);
            }
            // هزینه به‌ازای هر نفر است و با تعداد افراد (خود شخص + همراهان) ضرب می‌شود.
            $amount = $fee_per_person * $people_count;
        }

        if (empty($_POST['smv_terms_accepted']) && empty($_POST['smv_consent'])) {
            self::fail('برای ارسال ثبت‌نام، تیک «قوانین را خواندم و قبول می‌کنم» الزامی است.', $old);
        }

        $rate_key = 'smv_reg_rate_' . md5(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown');
        if (get_transient($rate_key)) {
            self::fail('درخواست شما کمی پیش ثبت شده است. برای جلوگیری از ارسال تکراری، یک دقیقه بعد دوباره تلاش کنید.', $old);
        }

        // دریافت فیش واریزی
        $receipt_path = '';
        $receipt_mime = '';
        $receipt_required = ('free' !== $payment_type);

        if (!empty($_FILES['receipt']) && isset($_FILES['receipt']['error']) && UPLOAD_ERR_OK === (int) $_FILES['receipt']['error']) {
            $receipt_result = self::store_receipt($_FILES['receipt']);
            if (is_wp_error($receipt_result)) {
                self::fail($receipt_result->get_error_message(), $old);
            }
            $receipt_path = $receipt_result['path'];
            $receipt_mime = $receipt_result['type'];
        } elseif ($receipt_required) {
            self::fail('آپلود فیش واریزی برای این روش پرداخت الزامی است.', $old);
        }

        $tracking_code = self::generate_tracking_code();
        $now = current_time('mysql');

        global $wpdb;
        $inserted = $wpdb->insert(smv_table_name(), array(
            'theatre_id' => $theatre_id,
            'showtime_id' => $showtime_id,
            'first_name' => $old['first_name'],
            'last_name' => $old['last_name'],
            'phone' => $phone,
            'tracking_code' => $tracking_code,
            'status' => 'pending',
            'admin_note' => '',
            'receipt_path' => $receipt_path,
            'receipt_mime' => $receipt_mime,
            'payment_type' => $payment_type,
            'companions' => $companions,
            'amount' => $amount,
            'financial_aid_amount' => $financial_aid_amount,
            'financial_aid_reason' => $financial_aid_reason,
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'));

        if (!$inserted) {
            if ($receipt_path) {
                self::delete_receipt_file($receipt_path);
            }
            self::fail('ذخیرهٔ اطلاعات انجام نشد. لطفاً دوباره تلاش کنید یا با مدیریت تماس بگیرید.', $old);
        }

        // ظرفیت بر اساس محتوای جدول محاسبه می‌شود؛ فقط متای قدیمی را همگام می‌کنیم.
        if ($showtime_id) {
            smv_showtime_booked_count($showtime_id);
        }

        set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        self::notify_admin($tracking_code, $theatre_id, $showtime_id, $old, $phone, $payment_type, $amount);

        // پیامک ثبت‌نام (کد پیگیری ۴ رقمی) برای ثبت‌نام‌کننده
        if (class_exists('SMV_SMS')) {
            SMV_SMS::send_registration(array(
                'phone' => $phone,
                'first_name' => $old['first_name'],
                'last_name' => $old['last_name'],
                'code' => $tracking_code,
                'theatre' => get_the_title($theatre_id),
                'showtime' => $showtime_id ? get_the_title($showtime_id) : '',
                'amount' => $amount ? SMV_Persian::money($amount) : 'رایگان',
                'status' => smv_status_label('pending'),
            ));
        }

        self::redirect('register', array(
            'success' => true,
            'message' => smv_get_option('success_message'),
            'tracking_code' => $tracking_code,
            'theatre' => get_the_title($theatre_id),
            'showtime' => $showtime_id ? get_the_title($showtime_id) : '',
            'name' => trim($old['first_name'] . ' ' . $old['last_name']),
            'phone' => $phone,
            'payment_type' => smv_payment_type_label($payment_type),
            'companions' => $companions,
            'people_count' => $people_count,
            'amount' => $amount,
        ));
    }

    /**
     * کد پیگیری ۴ رقمی یکتا (مثلاً ۴۲۱۷).
     * اگر همهٔ کدهای چهاررقمی استفاده شده باشند (بیش از ۹۰۰۰ ثبت‌نام)، باز هم کد چهاررقمی برمی‌گردد.
     */
    private static function generate_tracking_code() {
        global $wpdb;
        $table = smv_table_name();
        for ($attempt = 0; $attempt < 80; $attempt++) {
            $code = (string) random_int(1000, 9999);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE tracking_code = %s", $code));
            if (!$exists) {
                return $code;
            }
        }
        return (string) random_int(1000, 9999);
    }

    private static function store_receipt($file) {
        if (empty($file) || !isset($file['error'])) {
            return new WP_Error('missing_file', 'انتخاب فیش واریزی الزامی است.');
        }
        if (UPLOAD_ERR_OK !== (int) $file['error']) {
            return new WP_Error('upload_error', 'آپلود فیش کامل انجام نشد. فایل را دوباره انتخاب کنید.');
        }
        $max_size = (int) smv_get_option('receipt_max_size', 5) * MB_IN_BYTES;
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $max_size) {
            return new WP_Error('file_size', 'حجم فیش بیشتر از حد مجاز است. حداکثر ' . SMV_Persian::digits(smv_get_option('receipt_max_size', 5)) . ' مگابایت انتخاب کنید.');
        }

        $allowed = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        );
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
        if (empty($checked['ext']) || empty($checked['type']) || !in_array($checked['type'], $allowed, true)) {
            return new WP_Error('file_type', 'فرمت فیش مجاز نیست. فقط JPG، PNG، WebP یا PDF ارسال کنید.');
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('upload_dir', 'مسیر ذخیره‌سازی در دسترس نیست. با مدیریت سایت تماس بگیرید.');
        }
        $subdir = '/samavieh-private/' . wp_date('Y/m');
        $target_dir = $uploads['basedir'] . $subdir;
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('upload_dir', 'ایجاد مسیر امن ذخیره‌سازی ممکن نشد.');
        }
        self::protect_private_directory($uploads['basedir'] . '/samavieh-private');

        $extension = $checked['ext'];
        $unique = function () use ($extension) {
            return 'smv-' . wp_generate_password(40, false, false) . '.' . $extension;
        };
        $upload_filter = function ($dirs) use ($subdir) {
            $dirs['path'] = $dirs['basedir'] . $subdir;
            $dirs['url'] = $dirs['baseurl'] . $subdir;
            $dirs['subdir'] = $subdir;
            return $dirs;
        };
        add_filter('upload_dir', $upload_filter);
        $uploaded = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes' => $allowed,
            'unique_filename_callback' => $unique,
        ));
        remove_filter('upload_dir', $upload_filter);

        if (!empty($uploaded['error'])) {
            return new WP_Error('upload_failed', 'ذخیرهٔ فیش انجام نشد: ' . $uploaded['error']);
        }
        $relative = ltrim(substr(wp_normalize_path($uploaded['file']), strlen(wp_normalize_path($uploads['basedir']))), '/');
        return array('path' => $relative, 'type' => $checked['type']);
    }

    private static function protect_private_directory($directory) {
        if (!is_dir($directory)) {
            return;
        }
        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $rules, LOCK_EX);
        }
        $index = $directory . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
        }
    }

    public static function delete_receipt_file($relative_path) {
        if (!$relative_path) {
            return;
        }
        $uploads = wp_upload_dir();
        $base = realpath($uploads['basedir'] . '/samavieh-private');
        $file = realpath($uploads['basedir'] . '/' . ltrim($relative_path, '/'));
        if ($base && $file && 0 === strpos($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) {
            @unlink($file);
        }
    }

    private static function notify_admin($tracking_code, $theatre_id, $showtime_id, $old, $phone, $payment_type, $amount) {
        $email = smv_get_option('notification_email', get_option('admin_email'));
        if (!$email || !is_email($email)) {
            return;
        }
        $subject = 'ثبت‌نام جدید تئاتر «' . get_the_title($theatre_id) . '»';
        $url = admin_url('admin.php?page=smv-registrations');
        $message = "یک درخواست ثبت‌نام جدید دریافت شد.\n\n";
        $message .= 'نام: ' . $old['first_name'] . ' ' . $old['last_name'] . "\n";
        $message .= 'شماره تماس: ' . $phone . "\n";
        $message .= 'کد پیگیری: ' . $tracking_code . "\n";
        $message .= 'تئاتر: ' . get_the_title($theatre_id) . "\n";
        $message .= 'سانس: ' . ($showtime_id ? get_the_title($showtime_id) : 'ثبت‌نام در سطح اجرا') . "\n";
        $message .= 'روش پرداخت: ' . smv_payment_type_label($payment_type) . "\n";
        $message .= 'مبلغ: ' . SMV_Persian::money($amount) . "\n\n";
        $message .= 'برای مشاهده و بررسی فیش وارد پیشخوان شوید: ' . $url;
        wp_mail($email, $subject, $message);
    }

    public static function handle_tracking() {
        $nonce = isset($_POST['smv_track_nonce']) ? sanitize_text_field(wp_unslash($_POST['smv_track_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'smv_track')) {
            self::fail('اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.', array(), 'track', 'smv-tracking');
        }
        $code = isset($_POST['tracking_code']) ? sanitize_text_field(wp_unslash($_POST['tracking_code'])) : '';
        $code = SMV_Persian::english_digits($code);
        $code = strtoupper(preg_replace('/\s+/', '', $code));
        $raw_phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $phone = SMV_Persian::normalize_phone($raw_phone);
        $old = array('tracking_code' => $code, 'phone' => $raw_phone);

        // کد پیگیری جدید ۴ رقمی است؛ کدهای قدیمی‌تر (SMV-XXXXXXXX) هم برای سازگاری پذیرفته می‌شوند.
        $valid_code = (bool) preg_match('/^\d{4}$/', $code) || (bool) preg_match('/^SMV-[A-Z2-9]{8}$/', $code);
        if (!$valid_code || !preg_match('/^09\d{9}$/', $phone)) {
            self::fail('کد پیگیری و شماره تماس را کامل و صحیح وارد کنید.', $old, 'track', 'smv-tracking');
        }

        $rate_key = 'smv_track_rate_' . md5(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown');
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 12) {
            self::fail('تعداد تلاش‌ها بیشتر از حد مجاز است. لطفاً ۱۵ دقیقه بعد دوباره تلاش کنید.', $old, 'track', 'smv-tracking');
        }

        global $wpdb;
        $table = smv_table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE tracking_code = %s LIMIT 1", $code));
        if (!$row || !hash_equals((string) $row->phone, (string) $phone)) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            self::fail('اطلاعاتی با این کد پیگیری و شماره تماس پیدا نشد.', $old, 'track', 'smv-tracking');
        }

        delete_transient($rate_key);

        self::redirect('track', array(
            'success' => true,
            'result' => self::tracking_result_from_row($row),
        ), 'smv-tracking');
    }
}
