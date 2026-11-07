<?php
if (!defined('ABSPATH')) {
    exit;
}

function smv_get_option($key, $default = '') {
    $options = get_option('smv_settings', array());
    if (!is_array($options)) {
        $options = array();
    }
    if (class_exists('SMV_Settings')) {
        $options = wp_parse_args($options, SMV_Settings::defaults());
    }
    return array_key_exists($key, $options) ? $options[$key] : $default;
}

function smv_statuses() {
    return array(
        'pending' => 'در انتظار بررسی',
        'approved' => 'تأیید شد',
        'rejected' => 'رد شد / نیاز به اصلاح',
    );
}

function smv_status_label($status) {
    $statuses = smv_statuses();
    return isset($statuses[$status]) ? $statuses[$status] : 'در انتظار بررسی';
}

function smv_post_status_label($status) {
    $labels = array(
        'publish' => 'منتشر شده',
        'draft' => 'پیش‌نویس',
        'pending' => 'در انتظار بررسی',
        'private' => 'خصوصی',
        'future' => 'زمان‌بندی شده',
        'trash' => 'زباله‌دان',
    );
    return isset($labels[$status]) ? $labels[$status] : $status;
}

function smv_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'smv_registrations';
}

function smv_reviews_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'smv_reviews';
}

function smv_payment_type_label($type) {
    $labels = array(
        'free' => 'رایگان',
        'financial_aid' => 'کمک مالی (مبلغ به انتخاب شخص)',
        'paid' => 'هزینهٔ ثبت‌نام (مبلغ مشخص)',
    );
    return isset($labels[$type]) ? $labels[$type] : 'نامشخص';
}

/**
 * مبلغ مشخصِ «هزینهٔ ثبت‌نام» برای یک اجرا/سانس (تومان).
 * اولویت با قیمت سانس است؛ اما قیمت سانس وقتی خالی/صفر ذخیره شده یعنی «قیمت جدا
 * ندارد»، پس باید به قیمت خود اجرا برگشت — وگرنه با اینکه برای اجرا قیمت تعیین
 * شده، پرداخت «مبلغ مشخص» خطای «هزینه مشخص نشده است» می‌دهد.
 */
function smv_registration_fixed_fee($theatre_id, $showtime_id = 0) {
    $price = $showtime_id ? absint(get_post_meta($showtime_id, '_smv_showtime_price', true)) : 0;
    if ($price <= 0) {
        $price = absint(get_post_meta($theatre_id, '_smv_price', true));
    }
    return $price;
}

/**
 * تعداد کل افراد یک ثبت‌نام از روی تعداد همراه (۰ تا ۲ همراه مجاز است):
 * خود شخص + همراهان. هزینهٔ «مبلغ مشخص» و مصرف ظرفیت بر اساس همین عدد است.
 */
function smv_registration_people_count($companions) {
    $companions = max(0, min(2, absint($companions)));
    return 1 + $companions;
}

/** برچسب فارسی تعداد همراه: «همراه ندارد»، «۱ همراه»، «۲ همراه» */
function smv_companions_label($companions) {
    $companions = max(0, min(2, absint($companions)));
    if (0 === $companions) {
        return 'همراه ندارد';
    }
    return SMV_Persian::digits($companions) . ' همراه';
}

/**
 * تبدیل ورودی کاربر به عدد صحیح: ارقام فارسی/عربی، جداکنندهٔ هزارگان، فاصله و واحد پول را پاک می‌کند.
 * (مثلاً «۱۵۰,۰۰۰ تومان» → 150000)
 */
/**
 * عنوان فارسی سانس برای پنل و لیست‌های کشویی: «سانس ۱۲ اردیبهشت ۱۴۰۵ — ۱۸:۳۰ تا ۲۰:۰۰»
 */
/**
 * تصویر عمودی (پرتره) تئاتر برای کارت صفحهٔ اصلی.
 * اول تصویر عمودیِ جداگانه‌ای که مدیر برای کارت اصلی انتخاب کرده، وگرنه پوستر/تصویر شاخص.
 */
function smv_theatre_portrait_url($theatre_id, $size = 'medium_large') {
    $theatre_id = absint($theatre_id);
    if (!$theatre_id) {
        return '';
    }
    $portrait_id = absint(get_post_meta($theatre_id, '_smv_portrait_image_id', true));
    if ($portrait_id) {
        $url = wp_get_attachment_image_url($portrait_id, $size);
        if ($url) {
            return $url;
        }
    }
    $url = get_the_post_thumbnail_url($theatre_id, $size);
    return $url ? $url : '';
}

function smv_showtime_title($date, $time = '', $time_end = '') {
    $title = 'سانس';
    if ($date) {
        $title .= ' ' . SMV_Persian::date($date);
    }
    $range = SMV_Persian::time_range($time, $time_end);
    if ($range) {
        $title .= ' — ' . $range;
    }
    return $title;
}

/**
 * برچسب ساعت سانس (شروع تا پایان) بر اساس شناسهٔ سانس.
 */
function smv_showtime_time_label($showtime_id) {
    return SMV_Persian::time_range(
        get_post_meta($showtime_id, '_smv_showtime_time', true),
        get_post_meta($showtime_id, '_smv_showtime_time_end', true)
    );
}

function smv_parse_amount($value) {
    if (is_array($value) || is_object($value)) {
        return 0;
    }
    $value = class_exists('SMV_Persian') ? SMV_Persian::english_digits((string) $value) : (string) $value;
    $value = str_replace(array('٬', ',', '،', ' ', "\xC2\xA0", 'تومان', 'ریال'), '', $value);
    $value = preg_replace('/[^0-9]/', '', $value);
    return $value === '' ? 0 : absint($value);
}

/**
 * روش‌های پرداخت فعال یک تئاتر.
 * اگر هیچ روشی برای اجرا ذخیره نشده باشد (اجراهای قدیمی)، هر سه روش فعال در نظر گرفته می‌شود
 * تا فرم ثبت‌نام بدون تنظیم اضافی کار کند.
 */
function smv_theatre_payment_methods($theatre_id) {
    $theatre_id = absint($theatre_id);
    $map = array(
        'free' => '_smv_payment_free',
        'financial_aid' => '_smv_payment_financial_aid',
        'paid' => '_smv_payment_paid',
    );
    $methods = array();
    $any_meta = false;
    foreach ($map as $type => $meta_key) {
        $value = get_post_meta($theatre_id, $meta_key, true);
        if ('' !== $value) {
            $any_meta = true;
        }
        if ('1' === (string) $value) {
            $methods[] = $type;
        }
    }
    if (!$any_meta) {
        return array('free', 'financial_aid', 'paid');
    }
    if (!$methods) {
        // هیچ روشی انتخاب نشده بود؛ رایگان به عنوان حالت پیش‌فرض تا فرم ثبت‌نام قفل نشود.
        return array('free');
    }
    return $methods;
}

function smv_theatre_registration_open($theatre_id) {
    $theatre_id = absint($theatre_id);
    if (!$theatre_id || 'publish' !== get_post_status($theatre_id)) {
        return false;
    }
    if ('open' !== get_post_meta($theatre_id, '_smv_registration_status', true)) {
        return false;
    }
    $deadline = get_post_meta($theatre_id, '_smv_registration_deadline', true);
    if ($deadline && $deadline < current_time('Y-m-d')) {
        return false;
    }
    $showtimes = smv_get_showtimes_for_theatre($theatre_id);
    if (!$showtimes) {
        // اجرای بدون سانس: ثبت‌نام در سطح خود اجرا انجام می‌شود.
        return true;
    }
    foreach ($showtimes as $showtime) {
        $capacity = absint(get_post_meta($showtime->ID, '_smv_capacity', true));
        $booked = smv_showtime_booked_count($showtime->ID);
        if ($capacity <= 0 || ($capacity - $booked) > 0) {
            return true;
        }
    }
    return false;
}

/**
 * تعداد ثبت‌نام‌های آنلاین (از خود سایت) یک سانس (همهٔ وضعیت‌ها به‌جز رد‌شده‌ها).
 * از خود جدول ثبت‌نام‌ها محاسبه می‌شود تا با حذف یا رد شدن یک ثبت‌نام، ظرفیت آزاد شود.
 */
function smv_showtime_online_booked_count($showtime_id) {
    global $wpdb;
    $showtime_id = absint($showtime_id);
    if (!$showtime_id) {
        return 0;
    }
    $table = smv_table_name();
    // هر ثبت‌نام به‌اندازهٔ «خود شخص + همراهانش» صندلی مصرف می‌کند.
    $count = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(1 + companions), 0) FROM {$table} WHERE showtime_id = %d AND status <> 'rejected'", $showtime_id));
    return (int) $count;
}

/**
 * تعداد ثبت‌نام دستی یک سانس؛ عددی که مدیر برای ثبت‌نام‌های خارج از سایت
 * (حضوری، تلفنی، گیشه و...) دستی وارد می‌کند تا ظرفیت واقعی درست محاسبه شود.
 */
function smv_showtime_manual_booked_count($showtime_id) {
    $showtime_id = absint($showtime_id);
    if (!$showtime_id) {
        return 0;
    }
    return absint(get_post_meta($showtime_id, '_smv_manual_booked', true));
}

/**
 * مجموع رزروشدهٔ یک سانس = ثبت‌نام‌های سایت + ثبت‌نام‌های دستیِ مدیر.
 * همهٔ محاسبه‌های ظرفیت باقی‌مانده و «تکمیل ظرفیت» بر اساس همین مجموع است،
 * پس با وارد کردن عدد دستی می‌شود ظرفیت ثبت‌نامی هر سانس را دستی دستکاری کرد.
 */
function smv_showtime_booked_count($showtime_id) {
    $showtime_id = absint($showtime_id);
    if (!$showtime_id) {
        return 0;
    }
    $total = smv_showtime_online_booked_count($showtime_id) + smv_showtime_manual_booked_count($showtime_id);
    // همگام‌سازی متای قدیمی برای سازگاری با نسخه‌های قبلی.
    update_post_meta($showtime_id, '_smv_booked', (int) $total);
    return (int) $total;
}

function smv_theatre_seats_remaining($theatre_id) {
    $total_capacity = 0;
    $total_booked = 0;
    foreach (smv_get_showtimes_for_theatre($theatre_id) as $showtime) {
        $cap = absint(get_post_meta($showtime->ID, '_smv_capacity', true));
        if ($cap <= 0) {
            continue;
        }
        $total_capacity += $cap;
        $total_booked += smv_showtime_booked_count($showtime->ID);
    }
    if (!$total_capacity) {
        return null;
    }
    return max(0, $total_capacity - $total_booked);
}

function smv_showtime_seats_remaining($showtime_id) {
    $capacity = absint(get_post_meta($showtime_id, '_smv_capacity', true));
    if (!$capacity) {
        return null;
    }
    return max(0, $capacity - smv_showtime_booked_count($showtime_id));
}

function smv_get_return_url() {
    $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $request = remove_query_arg('smv_state', $request);
    return home_url($request);
}

function smv_icon($name, $class = '') {
    $paths = array(
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'ticket' => '<path d="M3 9V5h18v4a3 3 0 0 0 0 6v4H3v-4a3 3 0 0 0 0-6Z"/><path d="M13 5v2m0 3v4m0 3v2"/>',
        'phone' => '<path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2c-3.5-1.5-6.5-4.5-8-8l2-2-1-4Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7l9 6 9-6"/>',
        'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'upload' => '<path d="M12 16V4m-5 5 5-5 5 5"/><path d="M4 15v5h16v-5"/>',
        'download' => '<path d="M12 4v12m-5-5 5 5 5-5"/><path d="M4 20h16"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert' => '<path d="M12 3l-9.5 17h19L12 3Z"/><path d="M12 9v4m0 3h.01"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'folder' => '<path d="M3 6h7l2 2h9v11H3V6Z"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4H4v12h4"/>',
        'theatre' => '<path d="M4 3h16v18H4z"/><path d="M4 3c4 4 4 8 0 12m16-12c-4 4-4 8 0 12M8 21v-6h8v6"/>',
        'video' => '<polygon points="14 2 6 7.8 6 16.2 14 2"/><path d="M19 20v-8L7 12l12 8z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'people' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'play' => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'mic' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    );
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="smv-icon ' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function smv_theatres_url() {
    $page_id = absint(smv_get_option('theatres_page_id'));
    if ($page_id && get_post_status($page_id)) {
        return get_permalink($page_id);
    }
    $page = get_page_by_path('theatres');
    return $page ? get_permalink($page) : home_url('/theatres/');
}

function smv_projects_url() {
    $page_id = absint(smv_get_option('projects_page_id'));
    if ($page_id && get_post_status($page_id)) {
        return get_permalink($page_id);
    }
    $page = get_page_by_path('projects');
    return $page ? get_permalink($page) : home_url('/projects/');
}

function smv_tracking_url() {
    $page_id = absint(smv_get_option('tracking_page_id'));
    if ($page_id && get_post_status($page_id)) {
        return get_permalink($page_id);
    }
    $page = get_page_by_path('tracking');
    return $page ? get_permalink($page) : home_url('/tracking/');
}

function smv_clean_csv_cell($value) {
    $value = (string) $value;
    if (preg_match('/^[=+\-@]/', $value)) {
        $value = "'" . $value;
    }
    return $value;
}

function smv_get_theatre_meta($theatre_id, $key = '') {
    $theatre_id = absint($theatre_id);
    $meta = get_post_meta($theatre_id);
    if ($key === '') {
        return $meta;
    }
    return isset($meta[$key]) ? maybe_unserialize($meta[$key][0]) : '';
}

function smv_get_showtimes_for_theatre($theatre_id) {
    $showtimes = get_posts(array(
        'post_type' => 'smv_showtime',
        'post_status' => array('publish'),
        'posts_per_page' => -1,
        'meta_query' => array(array(
            'key' => '_smv_theatre_id',
            'value' => absint($theatre_id),
            'compare' => '=',
        )),
        'orderby' => 'meta_value',
        'meta_key' => '_smv_showtime_date',
        'order' => 'ASC',
    ));
    return smv_sort_showtimes(is_array($showtimes) ? $showtimes : array());
}

/**
 * مرتب‌سازی سانس‌ها بر اساس اولویت رزرو: اول تاریخ، بعد ساعت شروع، بعد شناسه
 * (سانسی که زودتر برگزار می‌شود، زودتر پر می‌شود).
 */
function smv_sort_showtimes($showtimes) {
    $showtimes = array_values(is_array($showtimes) ? $showtimes : array());
    usort($showtimes, function ($a, $b) {
        $a_id = isset($a->ID) ? (int) $a->ID : 0;
        $b_id = isset($b->ID) ? (int) $b->ID : 0;
        // اول: روز برگزاری (به ترتیب روزها نمایش و تکمیل می‌شوند)
        $a_date = (string) get_post_meta($a_id, '_smv_showtime_date', true);
        $b_date = (string) get_post_meta($b_id, '_smv_showtime_date', true);
        if ($a_date !== $b_date) {
            return strcmp($a_date, $b_date);
        }
        // داخل هر روز: اول «اولویت» ثبت‌شده توسط مدیر (۱ زودتر پر می‌شود)، بعد ساعت شروع
        $a_priority = absint(get_post_meta($a_id, '_smv_priority', true));
        $b_priority = absint(get_post_meta($b_id, '_smv_priority', true));
        $a_priority_key = $a_priority > 0 ? $a_priority : 9999;
        $b_priority_key = $b_priority > 0 ? $b_priority : 9999;
        if ($a_priority_key !== $b_priority_key) {
            return $a_priority_key <=> $b_priority_key;
        }
        $a_time = (string) get_post_meta($a_id, '_smv_showtime_time', true);
        $b_time = (string) get_post_meta($b_id, '_smv_showtime_time', true);
        if ($a_time !== $b_time) {
            return strcmp($a_time, $b_time);
        }
        return $a_id <=> $b_id;
    });
    return $showtimes;
}

/** اولویت ثبت‌شدهٔ یک سانس توسط مدیر (۰ یعنی تعیین نشده) */
function smv_showtime_priority_value($showtime_id) {
    return absint(get_post_meta($showtime_id, '_smv_priority', true));
}

/** گروه‌بندی سانس‌ها بر اساس روز: ['2026-05-05' => [سانس‌ها به ترتیب اولویت], ...] روزها صعودی */
function smv_group_showtimes_by_day($theatre_id) {
    $groups = array();
    foreach (smv_ordered_showtimes_for_theatre($theatre_id) as $showtime) {
        $date = (string) get_post_meta($showtime->ID, '_smv_showtime_date', true);
        if (!isset($groups[$date])) {
            $groups[$date] = array();
        }
        $groups[$date][] = $showtime;
    }
    ksort($groups);
    return $groups;
}

/** شمارهٔ روز (۱، ۲، …) برای یک تاریخ در میان روزهای اجرای این تئاتر */
function smv_showtime_day_number($theatre_id, $date) {
    $days = array_keys(smv_group_showtimes_by_day($theatre_id));
    $index = array_search((string) $date, $days, true);
    return false === $index ? 0 : $index + 1;
}

/** سانس‌های مرتب‌شدهٔ یک اجرا به ترتیب اولویت پر شدن */
function smv_ordered_showtimes_for_theatre($theatre_id) {
    return smv_get_showtimes_for_theatre($theatre_id);
}

/** شمارهٔ اولویت سانس «داخل روز خودش» (۱ = اولین سانسی که در آن روز پر می‌شود) */
function smv_showtime_priority($showtime_id, $theatre_id = 0) {
    if (!$theatre_id) {
        $theatre_id = absint(get_post_meta($showtime_id, '_smv_theatre_id', true));
    }
    $date = (string) get_post_meta($showtime_id, '_smv_showtime_date', true);
    $groups = smv_group_showtimes_by_day($theatre_id);
    if (!isset($groups[$date])) {
        return 0;
    }
    foreach ($groups[$date] as $index => $showtime) {
        if ((int) $showtime->ID === (int) $showtime_id) {
            return $index + 1;
        }
    }
    return 0;
}

/**
 * سانس‌های «فعال» برای رزرو، به تفکیک روز: هر روز مستقل پر می‌شود،
 * پس برای هر روز اولین سانسِ دارای ظرفیت (بر اساس اولویت همان روز) فعال است.
 * برمی‌گرداند: ['2026-05-05' => showtime_id, ...]
 */
function smv_active_showtime_ids($theatre_id) {
    $actives = array();
    foreach (smv_group_showtimes_by_day($theatre_id) as $date => $showtimes) {
        foreach ($showtimes as $showtime) {
            $remaining = smv_showtime_seats_remaining($showtime->ID);
            if (null === $remaining || $remaining > 0) {
                $actives[$date] = (int) $showtime->ID;
                break;
            }
        }
    }
    return $actives;
}

/**
 * شناسهٔ اولین سانس فعال (زودترین روز) — برای سازگاری با فراخوانی‌های قبلی.
 * برای بررسی «آیا این سانس فعال است» از smv_showtime_state استفاده کنید (روزبه‌روز).
 */
function smv_active_showtime_id($theatre_id) {
    $actives = smv_active_showtime_ids($theatre_id);
    return $actives ? (int) reset($actives) : 0;
}

/**
 * نحوهٔ تکمیل سانس‌های یک اجرا:
 * ordered = ترتیبیِ روزبه‌روز بر اساس اولویت (پیش‌فرض)
 * free    = آزاد/همزمان: همهٔ سانس‌های دارای ظرفیت همزمان فعال و قابل انتخاب‌اند.
 */
function smv_theatre_fill_mode($theatre_id) {
    $mode = (string) get_post_meta($theatre_id, '_smv_showtime_fill_mode', true);
    return 'free' === $mode ? 'free' : 'ordered';
}

/** آیا این سانس در حال حاضر قابل رزرو است؟ (در حالت آزاد همهٔ دارای ظرفیت، در حالت ترتیبی سانس فعالِ روز خودش) */
function smv_showtime_is_active($showtime_id, $theatre_id = 0) {
    if (!$theatre_id) {
        $theatre_id = absint(get_post_meta($showtime_id, '_smv_theatre_id', true));
    }
    if ('free' === smv_theatre_fill_mode($theatre_id)) {
        $remaining = smv_showtime_seats_remaining($showtime_id);
        return null === $remaining || $remaining > 0;
    }
    $date = (string) get_post_meta($showtime_id, '_smv_showtime_date', true);
    $actives = smv_active_showtime_ids($theatre_id);
    return isset($actives[$date]) && (int) $actives[$date] === (int) $showtime_id;
}

/**
 * وضعیت ترتیبی یک سانس داخل روز خودش: full | active | waiting
 * full = تکمیل ظرفیت، active = سانس قابل رزروِ این روز، waiting = پس از تکمیل سانس قبلیِ همان روز فعال می‌شود.
 * (روزهای مختلف مستقل‌اند: فعال بودن سانس یک روز منتظر پر شدن روزهای دیگر نمی‌ماند.)
 */
function smv_showtime_state($showtime_id, $theatre_id = 0) {
    $showtime_id = absint($showtime_id);
    if (!$theatre_id) {
        $theatre_id = absint(get_post_meta($showtime_id, '_smv_theatre_id', true));
    }
    $remaining = smv_showtime_seats_remaining($showtime_id);
    if (null !== $remaining && $remaining <= 0) {
        return 'full';
    }
    return smv_showtime_is_active($showtime_id, $theatre_id) ? 'active' : 'waiting';
}

/** متن قوانین ثبت‌نام (قابل ویرایش از تنظیمات؛ پیش‌فرض، متن سوگواره) */
function smv_registration_terms_text() {
    $text = trim((string) smv_get_option('terms_text', ''));
    if ('' !== $text) {
        return $text;
    }
    return "با توجه به هزینه‌ی برگزاری سوگواره و محدودیت ظرفیت، خواهشمندیم به عنوان یک دِین شرعی به ازای هر رزرو، حتماً در مراسم حضور داشته باشید.\nعدم حضور شما موجب محرومیت دیگران از حضور در سوگواره می‌شود.";
}

/**
 * فهرست ویدیوهای یک اجرا برای نمایش حتمی در صفحهٔ اجرا:
 * ویدیوی اصلی (فایل آپلودشده یا لینک) + لینک‌های اضافی (هر خط یک لینک).
 * هر آیتم: ['url' => ..., 'type' => 'file'|'embed']
 */
function smv_theatre_videos($theatre_id) {
    $theatre_id = absint($theatre_id);
    $urls = array();
    $primary = trim((string) get_post_meta($theatre_id, '_smv_video_url', true));
    if ('' === $primary) {
        $video_id = absint(get_post_meta($theatre_id, '_smv_video_id', true));
        $primary = $video_id ? (string) wp_get_attachment_url($video_id) : '';
    }
    if ($primary) {
        $urls[] = $primary;
    }
    $extra = get_post_meta($theatre_id, '_smv_videos', true);
    $extra_items = maybe_unserialize($extra);
    if (is_string($extra_items)) {
        $extra_items = preg_split('/\r\n|\r|\n/', $extra_items);
    }
    if (is_array($extra_items)) {
        foreach ($extra_items as $item) {
            $item = trim((string) $item);
            if ($item) {
                $urls[] = $item;
            }
        }
    }
    $videos = array();
    foreach (array_unique($urls) as $url) {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $is_file = (bool) preg_match('/\.(mp4|webm|mov|m4v)$/i', $path);
        $videos[] = array('url' => $url, 'type' => $is_file ? 'file' : 'embed');
    }
    return $videos;
}

/** خروجی HTML پخش ویدیو (فایل مستقیم با تگ video؛ لینک خارجی با embed/اوامبد) */
function smv_video_embed_html($video) {
    $url = isset($video['url']) ? (string) $video['url'] : '';
    if (!$url) {
        return '';
    }
    if (isset($video['type']) && 'file' === $video['type']) {
        return '<video class="smv-video-player" controls preload="metadata" playsinline src="' . esc_url($url) . '"></video>';
    }
    $oembed = function_exists('wp_oembed_get') ? wp_oembed_get($url) : false;
    if ($oembed) {
        return '<div class="smv-video-frame">' . $oembed . '</div>';
    }
    $shortcode = do_shortcode('[embed width="800" height="450"]' . esc_url($url) . '[/embed]');
    if ($shortcode && false === strpos($shortcode, '[embed')) {
        return '<div class="smv-video-frame">' . $shortcode . '</div>';
    }
    return '<a class="smv-video-link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"><span class="smv-video-play">▶</span> تماشای ویدیو</a>';
}

/**
 * همهٔ سانس‌های یک اجرا، بدون توجه به وضعیت انتشار (برای پنل مدیریت).
 */
function smv_get_all_showtimes_for_theatre($theatre_id) {
    return get_posts(array(
        'post_type' => 'smv_showtime',
        'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
        'posts_per_page' => -1,
        'meta_query' => array(array(
            'key' => '_smv_theatre_id',
            'value' => absint($theatre_id),
            'compare' => '=',
        )),
        'orderby' => 'meta_value',
        'meta_key' => '_smv_showtime_date',
        'order' => 'ASC',
    ));
}

/**
 * نظرات تأییدشدهٔ یک اجرا از جدول اختصاصی افزونه.
 */
function smv_get_reviews_for_theatre($theatre_id, $approved_only = true, $limit = 2) {
    global $wpdb;
    $table = smv_reviews_table_name();
    $limit = absint($limit);
    if (!$limit) {
        $limit = 2;
    }
    $sql = "SELECT * FROM {$table} WHERE theatre_id = %d";
    $params = array(absint($theatre_id));
    if ($approved_only) {
        $sql .= " AND status = %s";
        $params[] = 'approved';
    }
    $sql .= ' ORDER BY created_at DESC LIMIT %d';
    $params[] = $limit;
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
    return is_array($rows) ? $rows : array();
}

/**
 * آخرین نظرسنجی فعال (برای نمایش خودکار در صفحهٔ اجرا).
 */
function smv_get_active_poll_id() {
    $polls = get_posts(array(
        'post_type' => 'smv_poll',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ));
    return $polls ? (int) $polls[0] : 0;
}

/**
 * نمایش شمارهٔ فارسی با جداکننده هزارگان.
 */
function smv_fa_number($number) {
    return SMV_Persian::digits(number_format_i18n((float) $number, 0));
}
