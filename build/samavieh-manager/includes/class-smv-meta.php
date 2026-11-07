<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Meta {
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_boxes'));
        add_action('save_post_smv_theatre', array(__CLASS__, 'save_theatre'));
        add_action('save_post_smv_project', array(__CLASS__, 'save_project'));
        add_action('save_post_smv_poll', array(__CLASS__, 'save_poll'));
    }

    public static function add_boxes() {
        add_meta_box('smv-theatre-details', 'اطلاعات اجرا و ثبت‌نام', array(__CLASS__, 'theatre_box'), 'smv_theatre', 'normal', 'high');
        add_meta_box('smv-project-details', 'اطلاعات تکمیلی پروژه', array(__CLASS__, 'project_box'), 'smv_project', 'normal', 'high');
        add_meta_box('smv-poll-details', 'پرسش و گزینه‌های نظرسنجی', array(__CLASS__, 'poll_box'), 'smv_poll', 'normal', 'high');
    }

    private static function field($post_id, $key, $label, $type = 'text', $description = '', $options = array()) {
        $meta_key = '_smv_' . $key;
        $value = get_post_meta($post_id, $meta_key, true);
        $id = 'smv-' . str_replace('_', '-', $key);
        echo '<div class="smv-admin-field">';
        echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label>';
        if ('select' === $type) {
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($meta_key) . '">';
            foreach ($options as $option_value => $option_label) {
                echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
            }
            echo '</select>';
        } elseif ('textarea' === $type) {
            echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($meta_key) . '" rows="3">' . esc_textarea($value) . '</textarea>';
        } elseif ('date' === $type) {
            // تاریخ شمسی: مقدار ذخیره‌شده میلادی است و در فرم شمسی نمایش/دریافت می‌شود.
            echo '<input type="text" class="smv-date-input" inputmode="numeric" dir="ltr" id="' . esc_attr($id) . '" name="' . esc_attr($meta_key) . '" value="' . esc_attr(SMV_Persian::date_input_value($value)) . '" placeholder="۱۴۰۵/۰۲/۱۲" autocomplete="off" />';
        } else {
            $extra = ('number' === $type) ? ' min="0" step="1"' : '';
            echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($meta_key) . '" value="' . esc_attr($value) . '"' . $extra . ' />';
        }
        if ($description) {
            echo '<small>' . esc_html($description) . '</small>';
        }
        echo '</div>';
    }

    public static function theatre_box($post) {
        wp_nonce_field('smv_save_theatre_meta', 'smv_theatre_meta_nonce');
        echo '<p class="smv-admin-help">فقط همین مشخصات را وارد کنید. برای توضیحات کامل از ویرایشگر بالای صفحه یا دکمهٔ «ویرایش با المنتور» استفاده کنید و تصویر اصلی را از بخش «تصویر شاخص» انتخاب کنید.</p>';
        echo '<div class="smv-admin-grid">';
        self::field($post->ID, 'director', 'کارگردان');
        self::field($post->ID, 'writer', 'نویسنده');
        self::field($post->ID, 'cast', 'بازیگران', 'text', 'نام بازیگران را با ویرگول جدا کنید.');
        self::field($post->ID, 'start_date', 'تاریخ شروع اجرا', 'date');
        self::field($post->ID, 'end_date', 'تاریخ پایان اجرا', 'date', 'اگر اجرا یک‌روزه است، خالی بماند.');
        self::field($post->ID, 'show_time', 'ساعت اجرا', 'text', 'مثال: ۱۸:۰۰ و ۲۰:۰۰');
        self::field($post->ID, 'duration', 'مدت اجرا', 'text', 'مثال: ۷۰ دقیقه');
        self::field($post->ID, 'age_range', 'ردهٔ سنی', 'text', 'مثال: مناسب ۱۲ سال به بالا');
        self::field($post->ID, 'venue', 'محل اجرا');
        self::field($post->ID, 'price', 'مبلغ ثبت‌نام (تومان)', 'number', 'برای ثبت‌نام رایگان عدد صفر را وارد کنید.');
        self::field($post->ID, 'capacity', 'ظرفیت', 'number', 'صفر یعنی بدون محدودیت ظرفیت.');
        self::field($post->ID, 'registration_deadline', 'آخرین مهلت ثبت‌نام', 'date');
        self::field($post->ID, 'registration_status', 'وضعیت ثبت‌نام', 'select', '', array('open' => 'باز است', 'closed' => 'بسته است'));
        echo '</div>';

        $methods = smv_theatre_payment_methods($post->ID);
        echo '<div class="smv-admin-field"><strong>روش‌های پرداخت فعال</strong><div class="smv-payment-options">';
        foreach (array('free' => 'رایگان', 'financial_aid' => 'کمک مالی', 'paid' => 'پرداخت آنلاین (زرین‌پال)') as $key => $label) {
            echo '<label><input type="checkbox" name="smv_payment_methods[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $methods, true), true, false) . ' /> ' . esc_html($label) . '</label>';
        }
        echo '</div><small>این روش‌ها در فرم ثبت‌نام همین اجرا نمایش داده می‌شوند.</small></div>';
    }

    public static function project_box($post) {
        wp_nonce_field('smv_save_project_meta', 'smv_project_meta_nonce');
        echo '<div class="smv-admin-grid">';
        self::field($post->ID, 'project_year', 'سال اجرا', 'text', 'مثال: ۱۴۰۵');
        self::field($post->ID, 'project_status', 'وضعیت پروژه', 'select', '', array('completed' => 'انجام شده', 'ongoing' => 'در حال اجرا'));
        echo '</div>';
    }

    public static function poll_box($post) {
        wp_nonce_field('smv_save_poll_meta', 'smv_poll_meta_nonce');
        $answers = maybe_unserialize(get_post_meta($post->ID, '_smv_poll_answers', true));
        $lines = array();
        if (is_array($answers)) {
            foreach ($answers as $answer) {
                if (isset($answer['text'])) {
                    $lines[] = $answer['text'];
                }
            }
        }
        echo '<div class="smv-admin-grid">';
        self::field($post->ID, 'poll_title', 'عنوان نظرسنجی', 'text', 'اگر خالی باشد، عنوان همین نوشته استفاده می‌شود.');
        self::field($post->ID, 'poll_description', 'توضیح کوتاه', 'textarea');
        self::field($post->ID, 'poll_status', 'وضعیت', 'select', '', array('active' => 'فعال', 'closed' => 'بسته'));
        self::field($post->ID, 'poll_end_date', 'تاریخ پایان', 'date', 'بعد از این تاریخ رای‌گیری بسته می‌شود.');
        echo '<div class="smv-admin-field smv-admin-field-full"><label for="smv-poll-answers"><strong>گزینه‌های نظرسنجی</strong></label>';
        echo '<textarea id="smv-poll-answers" name="_smv_poll_answers_text" rows="6" placeholder="هر گزینه در یک خط">' . esc_textarea(implode("\n", $lines)) . '</textarea>';
        echo '<small>هر گزینه را در یک خط بنویسید. با ویرایش متن یک گزینه، تعداد رای‌های آن حفظ می‌شود.</small></div>';
        echo '<div class="smv-admin-field"><label><strong>آمار رای‌ها</strong></label><p>';
        if (is_array($answers) && $answers) {
            foreach ($answers as $answer) {
                echo esc_html(isset($answer['text']) ? $answer['text'] : '') . ' : ' . esc_html(SMV_Persian::digits(isset($answer['votes']) ? $answer['votes'] : 0)) . '<br />';
            }
        } else {
            echo 'هنوز رایی ثبت نشده است.';
        }
        echo '</p></div>';
        echo '</div>';
    }

    private static function can_save($post_id, $nonce_name, $action) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (!isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $action)) {
            return false;
        }
        return current_user_can('edit_post', $post_id);
    }

    public static function save_theatre($post_id) {
        if (!self::can_save($post_id, 'smv_theatre_meta_nonce', 'smv_save_theatre_meta')) {
            return;
        }
        $text_fields = array('director', 'writer', 'cast', 'show_time', 'duration', 'age_range', 'venue');
        $date_fields = array('start_date', 'end_date', 'registration_deadline');
        $number_fields = array('price', 'capacity');
        foreach ($text_fields as $key) {
            $value = isset($_POST['_smv_' . $key]) ? sanitize_text_field(wp_unslash($_POST['_smv_' . $key])) : '';
            update_post_meta($post_id, '_smv_' . $key, $value);
        }
        foreach ($date_fields as $key) {
            $value = isset($_POST['_smv_' . $key]) ? SMV_Persian::parse_date_input(wp_unslash($_POST['_smv_' . $key])) : '';
            update_post_meta($post_id, '_smv_' . $key, $value);
        }
        foreach ($number_fields as $key) {
            $value = isset($_POST['_smv_' . $key]) ? smv_parse_amount(wp_unslash($_POST['_smv_' . $key])) : 0;
            update_post_meta($post_id, '_smv_' . $key, $value);
        }
        $status = isset($_POST['_smv_registration_status']) && 'open' === $_POST['_smv_registration_status'] ? 'open' : 'closed';
        update_post_meta($post_id, '_smv_registration_status', $status);

        if (isset($_POST['smv_payment_methods'])) {
            $methods = array_map('sanitize_key', (array) wp_unslash($_POST['smv_payment_methods']));
            update_post_meta($post_id, '_smv_payment_free', in_array('free', $methods, true) ? '1' : '0');
            update_post_meta($post_id, '_smv_payment_financial_aid', in_array('financial_aid', $methods, true) ? '1' : '0');
            update_post_meta($post_id, '_smv_payment_paid', in_array('paid', $methods, true) ? '1' : '0');
        }
    }

    public static function save_project($post_id) {
        if (!self::can_save($post_id, 'smv_project_meta_nonce', 'smv_save_project_meta')) {
            return;
        }
        $year = isset($_POST['_smv_project_year']) ? sanitize_text_field(wp_unslash($_POST['_smv_project_year'])) : '';
        $status = isset($_POST['_smv_project_status']) && 'ongoing' === $_POST['_smv_project_status'] ? 'ongoing' : 'completed';
        update_post_meta($post_id, '_smv_project_year', $year);
        update_post_meta($post_id, '_smv_project_status', $status);
    }

    public static function save_poll($post_id) {
        if (!self::can_save($post_id, 'smv_poll_meta_nonce', 'smv_save_poll_meta')) {
            return;
        }
        update_post_meta($post_id, '_smv_poll_title', isset($_POST['_smv_poll_title']) ? sanitize_text_field(wp_unslash($_POST['_smv_poll_title'])) : '');
        update_post_meta($post_id, '_smv_poll_description', isset($_POST['_smv_poll_description']) ? sanitize_textarea_field(wp_unslash($_POST['_smv_poll_description'])) : '');
        update_post_meta($post_id, '_smv_poll_status', (isset($_POST['_smv_poll_status']) && 'closed' === $_POST['_smv_poll_status']) ? 'closed' : 'active');
        $end_date = isset($_POST['_smv_poll_end_date']) ? sanitize_text_field(wp_unslash($_POST['_smv_poll_end_date'])) : '';
        update_post_meta($post_id, '_smv_poll_end_date', preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) ? $end_date : '');

        // گزینه‌ها: متن‌ها از فرم خوانده می‌شوند و رای‌های قبلی بر اساس متن حفظ می‌شوند.
        $existing = maybe_unserialize(get_post_meta($post_id, '_smv_poll_answers', true));
        $votes_by_text = array();
        $votes_by_index = array();
        if (is_array($existing)) {
            foreach ($existing as $index => $answer) {
                if (!isset($answer['text'])) {
                    continue;
                }
                $votes_by_text[trim((string) $answer['text'])] = absint(isset($answer['votes']) ? $answer['votes'] : 0);
                $votes_by_index[$index] = absint(isset($answer['votes']) ? $answer['votes'] : 0);
            }
        }

        $raw = isset($_POST['_smv_poll_answers_text']) ? sanitize_textarea_field(wp_unslash($_POST['_smv_poll_answers_text'])) : '';
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $answers = array();
        $index = 0;
        foreach ($lines as $line) {
            $text = trim($line);
            if ('' === $text) {
                continue;
            }
            $votes = isset($votes_by_text[$text]) ? $votes_by_text[$text] : (isset($votes_by_index[$index]) ? $votes_by_index[$index] : 0);
            $answers[] = array('text' => $text, 'votes' => $votes);
            $index++;
        }
        update_post_meta($post_id, '_smv_poll_answers', maybe_serialize($answers));
    }
}
