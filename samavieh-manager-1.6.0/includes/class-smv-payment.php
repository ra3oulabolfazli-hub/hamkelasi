<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * اتصال به درگاه پرداخت زرین‌پال (درخواست، بازگشت از درگاه و تأیید تراکنش)
 */
class SMV_Payment {

    const REQUEST_URL = 'https://api.zarinpal.com/pg/v4/payment/request.json';
    const VERIFY_URL = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
    const START_PAY_URL = 'https://payment.zarinpal.com/pg/StartPay/';

    public static function init() {
        add_shortcode('samavieh_payment', array(__CLASS__, 'payment_shortcode'));
        add_action('wp_ajax_smv_request_payment', array(__CLASS__, 'request_payment_ajax'));
        add_action('wp_ajax_nopriv_smv_request_payment', array(__CLASS__, 'request_payment_ajax'));
        add_action('admin_post_smv_payment_callback', array(__CLASS__, 'payment_callback'));
        add_action('admin_post_nopriv_smv_payment_callback', array(__CLASS__, 'payment_callback'));
    }

    private static function get_registration($registration_id) {
        global $wpdb;
        $registration_id = absint($registration_id);
        if (!$registration_id) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . smv_table_name() . ' WHERE id = %d', $registration_id));
    }

    private static function callback_url($row) {
        return admin_url('admin-post.php?action=smv_payment_callback&reg=' . absint($row->id) . '&key=' . rawurlencode(self::signature($row)));
    }

    private static function signature($row) {
        return wp_hash('smv-payment|' . absint($row->id) . '|' . $row->tracking_code);
    }

    public static function payment_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'registration_id' => 0,
            'amount' => 0,
        ), $atts, 'samavieh_payment');

        $row = self::get_registration($atts['registration_id']);
        if (!$row) {
            return '<div class="smv-notice smv-notice-error">ثبت‌نام پیدا نشد.</div>';
        }

        $amount = absint($row->amount);
        if ($amount <= 0) {
            return '<div class="smv-notice smv-notice-error">مبلغ قابل پرداخت برای این ثبت‌نام تعریف نشده است.</div>';
        }
        if (!empty($row->payment_ref)) {
            return '<div class="smv-notice smv-notice-success">پرداخت این ثبت‌نام قبلاً با موفقیت انجام شده است. کد رهگیری: <bdi dir="ltr">' . esc_html($row->payment_ref) . '</bdi></div>';
        }
        if (!smv_get_option('zarinpal_merchant', '')) {
            return '<div class="smv-notice smv-notice-info">درگاه پرداخت آنلاین فعال نیست. لطفاً از طریق فیش واریزی ثبت‌نام کنید.</div>';
        }

        $panel_id = 'smv-payment-panel-' . absint($row->id);
        ob_start();
        ?>
        <div class="smv-payment-panel" id="<?php echo esc_attr($panel_id); ?>">
            <div class="smv-payment-header">
                <span class="smv-kicker">پرداخت بلیت</span>
                <h2>مبلغ قابل پرداخت</h2>
                <p class="smv-payment-amount"><?php echo esc_html(SMV_Persian::money($amount)); ?></p>
            </div>
            <div class="smv-payment-methods">
                <button type="button" class="smv-payment-btn smv-payment-zarinpal" data-smv-pay="zarinpal" data-reg-id="<?php echo esc_attr($row->id); ?>">
                    <span>🏦</span> پرداخت با زرین‌پال
                </button>
            </div>
            <div class="smv-payment-note">
                <p>اگر قبلاً فیش واریزی آپلود کرده‌اید، نیازی به پرداخت آنلاین نیست؛ منتظر بررسی مدیریت بمانید.</p>
            </div>
            <div class="smv-form-message" style="display:none;" role="status"></div>
        </div>
        <script>
        (function(){
            var panel = document.getElementById('<?php echo esc_js($panel_id); ?>');
            if (!panel) { return; }
            var button = panel.querySelector('[data-smv-pay="zarinpal"]');
            var message = panel.querySelector('.smv-form-message');
            if (!button) { return; }
            button.addEventListener('click', function(){
                button.disabled = true;
                var body = new FormData();
                body.append('action', 'smv_request_payment');
                body.append('registration_id', button.getAttribute('data-reg-id'));
                body.append('nonce', '<?php echo esc_js(wp_create_nonce('smv_payment_nonce')); ?>');
                fetch((window.smvFrontend && smvFrontend.ajaxUrl) || '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(response){ return response.json(); })
                    .then(function(result){
                        if (result.success && result.data.url) {
                            window.location.href = result.data.url;
                            return;
                        }
                        button.disabled = false;
                        if (message) {
                            message.style.display = 'block';
                            message.className = 'smv-form-message is-error';
                            message.textContent = (result.data && result.data.message) ? result.data.message : 'خطا در اتصال به درگاه پرداخت.';
                        }
                    })
                    .catch(function(){
                        button.disabled = false;
                        if (message) {
                            message.style.display = 'block';
                            message.className = 'smv-form-message is-error';
                            message.textContent = 'ارتباط با سرور برقرار نشد.';
                        }
                    });
            });
        }());
        </script>
        <?php
        return ob_get_clean();
    }

    public static function request_payment_ajax() {
        check_ajax_referer('smv_payment_nonce', 'nonce');

        $row = self::get_registration(isset($_POST['registration_id']) ? $_POST['registration_id'] : 0);
        if (!$row) {
            wp_send_json_error(array('message' => 'ثبت‌نام پیدا نشد.'));
        }
        $merchant = smv_get_option('zarinpal_merchant', '');
        if (!$merchant) {
            wp_send_json_error(array('message' => 'کد مرچنت زرین‌پال تنظیم نشده است.'));
        }
        $amount = absint($row->amount);
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'مبلغ این ثبت‌نام صفر است و قابل پرداخت نیست.'));
        }
        if (!empty($row->payment_ref)) {
            wp_send_json_error(array('message' => 'این ثبت‌نام قبلاً پرداخت شده است.'));
        }

        $data = array(
            'merchant_id' => $merchant,
            'amount' => $amount * 10, // مبالغ سایت به تومان است؛ درگاه ریال می‌گیرد.
            'callback_url' => self::callback_url($row),
            'description' => 'پرداخت ثبت‌نام سماویه - کد ' . $row->tracking_code,
            'metadata' => array('mobile' => $row->phone),
        );

        $response = wp_remote_post(self::REQUEST_URL, array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
            'body' => wp_json_encode($data),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'خطا در اتصال به درگاه: ' . $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $authority = isset($body['data']['authority']) ? $body['data']['authority'] : '';
        if (!$authority) {
            $error_message = isset($body['errors']['message']) ? $body['errors']['message'] : 'پاسخ نامعتبر از درگاه';
            wp_send_json_error(array('message' => 'خطا در ایجاد درخواست پرداخت: ' . $error_message));
        }

        update_option('smv_payment_authority_' . $row->id, $authority, false);
        wp_send_json_success(array('url' => self::START_PAY_URL . $authority));
    }

    public static function payment_callback() {
        $registration_id = isset($_GET['reg']) ? absint($_GET['reg']) : 0;
        $row = self::get_registration($registration_id);
        $tracking_url = smv_tracking_url();

        if (!$row) {
            wp_safe_redirect(add_query_arg('smv_pay', 'invalid', $tracking_url));
            exit;
        }

        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if (!$key || !hash_equals(self::signature($row), $key)) {
            wp_safe_redirect(add_query_arg('smv_pay', 'invalid', $tracking_url));
            exit;
        }

        $status = isset($_GET['Status']) ? sanitize_text_field(wp_unslash($_GET['Status'])) : '';
        $authority = isset($_GET['Authority']) ? sanitize_text_field(wp_unslash($_GET['Authority'])) : '';
        $stored_authority = get_option('smv_payment_authority_' . $row->id, '');

        if ('OK' !== $status || !$authority || ($stored_authority && !hash_equals((string) $stored_authority, $authority))) {
            $url = SMV_Forms::make_state_url('track', array(
                'success' => false,
                'message' => 'پرداخت لغو شد یا ناموفق بود. در صورت نیاز دوباره تلاش کنید یا فیش واریزی ارسال کنید.',
                'old' => array('tracking_code' => $row->tracking_code, 'phone' => $row->phone),
            ), 'smv-tracking', $tracking_url);
            wp_safe_redirect($url);
            exit;
        }

        $response = wp_remote_post(self::VERIFY_URL, array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
            'body' => wp_json_encode(array(
                'merchant_id' => smv_get_option('zarinpal_merchant', ''),
                'amount' => absint($row->amount) * 10,
                'authority' => $authority,
            )),
        ));

        $verified = false;
        $reference = '';
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $code = isset($body['data']['code']) ? (int) $body['data']['code'] : 0;
            if (100 === $code || 101 === $code) {
                $verified = true;
                $reference = isset($body['data']['ref_id']) ? (string) $body['data']['ref_id'] : '';
            }
        }

        global $wpdb;
        if ($verified) {
            $note = trim((string) $row->admin_note);
            $note = ($note ? $note . "\n" : '') . 'پرداخت آنلاین تأیید شد. کد رهگیری درگاه: ' . $reference;
            $wpdb->update(smv_table_name(), array(
                'payment_type' => 'paid',
                'payment_ref' => $reference,
                'paid_at' => current_time('mysql'),
                'admin_note' => $note,
                'updated_at' => current_time('mysql'),
            ), array('id' => $row->id), array('%s', '%s', '%s', '%s', '%s'), array('%d'));
            delete_option('smv_payment_authority_' . $row->id);
            $row = self::get_registration($row->id);
            $url = SMV_Forms::make_state_url('track', array(
                'success' => true,
                'message' => 'پرداخت شما با موفقیت انجام و ثبت شد.',
                'result' => SMV_Forms::tracking_result_from_row($row),
            ), 'smv-tracking', $tracking_url);
        } else {
            $url = SMV_Forms::make_state_url('track', array(
                'success' => false,
                'message' => 'پرداخت تأیید نشد. اگر مبلغ از حساب شما کسر شده باشد، تا ۷۲ ساعت به‌صورت خودکار بازگردانده می‌شود.',
                'old' => array('tracking_code' => $row->tracking_code, 'phone' => $row->phone),
            ), 'smv-tracking', $tracking_url);
        }

        wp_safe_redirect($url);
        exit;
    }
}
