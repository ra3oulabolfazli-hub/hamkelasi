<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * نمایش لینک شبکه‌های اجتماعی
 * (قبلاً این بخش فقط متن «بارگذاری...» را نشان می‌داد و هیچ محتوایی بارگذاری نمی‌شد)
 */
class SMV_Social {

    public static function init() {
        add_shortcode('samavieh_social', array(__CLASS__, 'social_shortcode'));
        add_shortcode('samavieh_tweets', array(__CLASS__, 'tweets_shortcode'));
    }

    /**
     * شبکه‌های پشتیبانی‌شده. «mono» حرف نشان هر شبکه است و رنگ کارت با کلاس CSS همان کلید تعیین می‌شود.
     * شبکه‌های ایرانی (روبیکا، بله، ایتا، آپارات) در اولویت نمایش هستند.
     */
    public static function platforms() {
        return array(
            'rubika' => array('label' => 'روبیکا', 'mono' => 'R', 'desc' => 'کانال رسمی ما در روبیکا'),
            'eitaa' => array('label' => 'ایتا', 'mono' => 'E', 'desc' => 'گزارش تصویری اجراها در ایتا'),
            'bale' => array('label' => 'بله', 'mono' => 'B', 'desc' => 'اطلاع‌رسانی اجراها در پیام‌رسان بله'),
            'telegram' => array('label' => 'تلگرام', 'mono' => 'T', 'desc' => 'تازه‌ترین خبرهای گروه در تلگرام'),
            'instagram' => array('label' => 'اینستاگرام', 'mono' => 'I', 'desc' => 'تصاویر و ویدیوهای پشت صحنه'),
            'aparat' => array('label' => 'آپارات', 'mono' => 'A', 'desc' => 'تیزر و فیلم کامل اجراها در آپارات'),
            'youtube' => array('label' => 'یوتیوب', 'mono' => 'Y', 'desc' => 'آرشیو ویدیوها در یوتیوب'),
            'whatsapp' => array('label' => 'واتس‌اپ', 'mono' => 'W', 'desc' => 'ارتباط مستقیم با ما در واتس‌اپ'),
            'twitter' => array('label' => 'ایکس (توییتر)', 'mono' => 'X', 'desc' => 'پیام‌های کوتاه ما در ایکس'),
        );
    }

    /** نام کلیدهای شبکه‌های اجتماعی برای تنظیمات */
    public static function keys() {
        return array_keys(self::platforms());
    }

    /** آیکن واقعی (SVG) هر شبکه — هم‌رنگ و هم‌شکل نشان همان شبکه، بدون نیاز به فایل خارجی */
    public static function brand_svg($key) {
        $inner = array(
            // روبیکا: الماس چندوجهی رنگی
            'rubika' => '<rect width="48" height="48" rx="12" fill="#5b2ee5"/><polygon points="24,7 39,19 24,41 9,19" fill="#ff5a8a"/><polygon points="24,7 39,19 24,22 9,19" fill="#ffb020"/><polygon points="9,19 24,22 24,41" fill="#7c3aed"/><polygon points="39,19 24,22 24,41" fill="#06b6d4"/><polygon points="24,7 31,19 24,22 17,19" fill="#fff" opacity=".85"/>',
            // ایتا: حباب گفت‌وگوی نارنجی
            'eitaa' => '<rect width="48" height="48" rx="12" fill="#f2682a"/><path d="M13 11h22a5 5 0 0 1 5 5v13a5 5 0 0 1-5 5H22l-7 6v-6h-2a5 5 0 0 1-5-5V16a5 5 0 0 1 5-5z" fill="#fff"/><circle cx="17" cy="22" r="2.4" fill="#f2682a"/><circle cx="24" cy="22" r="2.4" fill="#f2682a"/><circle cx="31" cy="22" r="2.4" fill="#f2682a"/>',
            // بله: حباب پیام با تیک
            'bale' => '<rect width="48" height="48" rx="12" fill="#0aa8c7"/><path d="M13 10h22a6 6 0 0 1 6 6v12a6 6 0 0 1-6 6H23l-8 7v-7h-2a6 6 0 0 1-6-6V16a6 6 0 0 1 6-6z" fill="#fff"/><path d="m17 24 5 5 9-10" fill="none" stroke="#0aa8c7" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>',
            // تلگرام: هواپیمای کاغذی
            'telegram' => '<rect width="48" height="48" rx="12" fill="#229ed9"/><path d="M39 10 9.5 21.5l8.6 3.2 2.2 7.6 5.1-4.9 6.3 4.6 3.5-16.5L39 10zM20.6 24.4l13.7-8.5-11 10.1-.5 4.6-2.2-6.2z" fill="#fff"/>',
            // اینستاگرام: دوربین
            'instagram' => '<defs><linearGradient id="smvIg" x1="0" y1="48" x2="48" y2="0"><stop offset="0" stop-color="#fdc468"/><stop offset=".5" stop-color="#df4996"/><stop offset="1" stop-color="#4c68d7"/></linearGradient></defs><rect width="48" height="48" rx="12" fill="url(#smvIg)"/><rect x="12" y="12" width="24" height="24" rx="7" fill="none" stroke="#fff" stroke-width="3"/><circle cx="24" cy="24" r="5.2" fill="none" stroke="#fff" stroke-width="3"/><circle cx="31.6" cy="16.4" r="1.9" fill="#fff"/>',
            // آپارات: دکمهٔ پخش
            'aparat' => '<rect width="48" height="48" rx="12" fill="#df2856"/><circle cx="24" cy="24" r="12" fill="#fff"/><path d="M21 17.5v13l10-6.5-10-6.5z" fill="#df2856"/>',
            // یوتیوب
            'youtube' => '<rect width="48" height="48" rx="12" fill="#ff0033"/><rect x="9" y="14" width="30" height="20" rx="6" fill="#fff"/><path d="M21 19.5v9l8-4.5-8-4.5z" fill="#ff0033"/>',
            // واتس‌اپ: حباب + تلفن
            'whatsapp' => '<rect width="48" height="48" rx="12" fill="#25d366"/><path d="M24 8a15 15 0 0 0-12.8 22.6L9 39l8.6-2.1A15 15 0 1 0 24 8z" fill="#fff"/><path d="M19.5 16.5c.5 3.2 5.5 8.4 8.9 9.3l2-2.4 4 1.8c-.6 2.2-2.3 3.1-4.5 2.7-5.8-1.1-12-6.9-13-12.8-.3-2.1.6-3.8 2.7-4.4l1.7 3.9-1.8 2.4z" fill="#25d366"/>',
            // ایکس
            'twitter' => '<rect width="48" height="48" rx="12" fill="#000"/><path d="M15 13l7.2 9.3L15.6 35h3.4l5-8.1L29.4 35H34l-7.5-9.7L33.4 13h-3.4l-4.4 7.2L20.5 13H15z" fill="#fff"/>',
        );
        $svg_inner = isset($inner[$key]) ? $inner[$key] : '<rect width="48" height="48" rx="12" fill="#123f3a"/><text x="24" y="30" text-anchor="middle" fill="#fff" font-size="18" font-weight="700">' . esc_html(strtoupper(substr($key, 0, 1))) . '</text>';
        return '<svg class="smv-brand-svg" viewBox="0 0 48 48" width="48" height="48" aria-hidden="true" focusable="false">' . $svg_inner . '</svg>';
    }

    private static function icon_html($key) {
        return '<span class="smv-social-icon smv-social-icon--' . esc_attr($key) . '" aria-hidden="true">' . self::brand_svg($key) . '</span>';
    }

    public static function social_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'platform' => 'rubika',
            'count' => 6,
        ), $atts, 'samavieh_social');

        $platforms = self::platforms();
        $platform = isset($platforms[$atts['platform']]) ? $atts['platform'] : 'rubika';
        $url = smv_get_option($platform);
        if (!$url) {
            // اگر شبکهٔ درخواستی تنظیم نشده باشد، اولین شبکهٔ دارای لینک نمایش داده می‌شود.
            foreach ($platforms as $key => $info) {
                if (smv_get_option($key)) {
                    $platform = $key;
                    $url = smv_get_option($key);
                    break;
                }
            }
        }
        if (!$url) {
            return '';
        }
        $label = $platforms[$platform]['label'];
        $desc = $platforms[$platform]['desc'];

        ob_start();
        ?>
        <div class="smv-social-profile">
            <a class="smv-social-card smv-social-card--<?php echo esc_attr($platform); ?>" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo self::icon_html($platform); ?>
                <span class="smv-social-body">
                    <strong>ما را در <?php echo esc_html($label); ?> دنبال کنید</strong>
                    <small><?php echo esc_html($desc); ?></small>
                </span>
                <span class="smv-social-cta">مشاهده صفحه <?php echo smv_icon('arrow'); ?></span>
            </a>
            <div class="smv-social-links smv-social-links--large">
                <?php foreach ($platforms as $key => $info) : $link = smv_get_option($key); ?>
                    <?php if ($link && $key !== $platform) : ?>
                        <a class="smv-social-link smv-social-link--<?php echo esc_attr($key); ?>" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($info['label']); ?>">
                            <?php echo self::icon_html($key); ?>
                            <span><?php echo esc_html($info['label']); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function tweets_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'username' => '',
            'count' => 5,
        ), $atts, 'samavieh_tweets');

        $username = sanitize_text_field($atts['username']);
        if (!$username) {
            return '';
        }
        $username = ltrim($username, '@');
        ob_start();
        ?>
        <div class="smv-tweets-carousel">
            <div class="smv-tweets-header">
                <span class="smv-twitter-icon">🐦</span>
                <h3>توییت‌های اخیر <bdi dir="ltr">@<?php echo esc_html($username); ?></bdi></h3>
            </div>
            <p class="smv-tweets-note">برای دیدن جدیدترین توییت‌ها روی دکمهٔ زیر بزنید.</p>
            <a href="https://twitter.com/<?php echo esc_attr($username); ?>" target="_blank" rel="noopener noreferrer" class="smv-button smv-button-primary smv-tweets-more">مشاهده همه توییت‌ها</a>
        </div>
        <?php
        return ob_get_clean();
    }
}
