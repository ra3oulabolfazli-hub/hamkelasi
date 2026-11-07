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

    private static function icon_html($key) {
        $platforms = self::platforms();
        $mono = isset($platforms[$key]['mono']) ? $platforms[$key]['mono'] : '';
        return '<span class="smv-social-icon smv-social-icon--' . esc_attr($key) . '" aria-hidden="true">' . esc_html($mono) . '</span>';
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
            <div class="smv-social-links">
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
