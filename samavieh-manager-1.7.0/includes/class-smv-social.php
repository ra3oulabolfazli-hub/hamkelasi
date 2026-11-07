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
            'rubika' => array('label' => 'روبیکا', 'brand' => '#7c3aed', 'desc' => 'کانال رسمی ما در روبیکا'),
            'eitaa' => array('label' => 'ایتا', 'brand' => '#f47b20', 'desc' => 'گزارش تصویری اجراها در ایتا'),
            'bale' => array('label' => 'بله', 'brand' => '#00c2a8', 'desc' => 'اطلاع‌رسانی اجراها در پیام‌رسان بله'),
            'telegram' => array('label' => 'تلگرام', 'brand' => '#229ed9', 'desc' => 'تازه‌ترین خبرهای گروه در تلگرام'),
            'instagram' => array('label' => 'اینستاگرام', 'brand' => '#df4996', 'desc' => 'تصاویر و ویدیوهای پشت صحنه'),
            'aparat' => array('label' => 'آپارات', 'brand' => '#ed145b', 'desc' => 'تیزر و فیلم کامل اجراها در آپارات'),
            'youtube' => array('label' => 'یوتیوب', 'brand' => '#ff0033', 'desc' => 'آرشیو ویدیوها در یوتیوب'),
            'whatsapp' => array('label' => 'واتس‌اپ', 'brand' => '#25d366', 'desc' => 'ارتباط مستقیم با ما در واتس‌اپ'),
            'twitter' => array('label' => 'ایکس (توییتر)', 'brand' => '#000000', 'desc' => 'پیام‌های کوتاه ما در ایکس'),
        );
    }

    /** نام کلیدهای شبکه‌های اجتماعی برای تنظیمات */
    public static function keys() {
        return array_keys(self::platforms());
    }

    /**
     * لوگوی واقعی (SVG) هر شبکه، ترسیم‌شده بر اساس نشان رسمی همان شبکه:
     * روبیکا = مکعب شش‌ضلعی رنگی، ایتا = نشان نارنجی ایتا، بله = حباب سبزآبی با تیک سفید،
     * آپارات = حلقه‌فیلم صورتی، تلگرام/اینستاگرام/یوتیوب/واتس‌اپ/ایکس = نشان‌های استاندارد جهانی.
     */
    public static function brand_svg($key) {
        $inner = array(
            // روبیکا: شش‌ضلعی رنگی با مکعب سفید وسط (نشان رسمی روبیکا)
            'rubika' => '<g><polygon points="24,3 42,13.5 42,34.5 24,45 6,34.5 6,13.5" fill="#fff"/><polygon points="24,3 33,8.2 24,13.5 15,8.2" fill="#7c4dff"/><polygon points="15,8.2 24,13.5 24,24 15,18.8" fill="#00b0ff"/><polygon points="33,8.2 42,13.5 33,18.8 24,13.5" fill="#00c853"/><polygon points="6,13.5 15,8.2 15,18.8 6,24" fill="#ff4081" transform="translate(0,10.5)"/><polygon points="42,13.5 33,18.8 33,29.3 42,24" fill="#ffc107" transform="translate(0,10.5)"/><polygon points="15,29.3 24,24 24,34.5 15,39.7" fill="#ff6d00" transform="translate(0,-10.5)"/><polygon points="24,24 33,29.3 24,45 24,34.5" fill="#304ffe" opacity="0"/><polygon points="24,13.5 33,18.8 24,24 15,18.8" fill="#e8f5e9"/><polygon points="15,18.8 24,24 24,33 15,27.8" fill="#cfd8dc"/><polygon points="33,18.8 24,24 24,33 33,27.8" fill="#90a4ae"/><polygon points="24,3 42,13.5 42,34.5 24,45 6,34.5 6,13.5 24,24" fill="none" stroke="#eceff1" stroke-width="1"/></g>',
            // ایتا: حلقهٔ نارنجی (@) با موج سبز داخل (نشان رسمی ایتا)
            'eitaa' => '<g><path d="M24 4a20 20 0 1 0 20 20c0 6-3.4 9-7.6 9-3.4 0-5.8-2-6.4-5C28.6 31 25.6 33 21.5 33 14.6 33 10 27.6 10 21.4 10 13.9 16.4 8 24.4 8c7.4 0 12.6 4.8 12.6 11.4 0 5.6-3.6 10.6-8.4 10.6-2 0-3.6-1-4.2-2.6-.4 1.8-2 3.1-4.2 3.1-3.4 0-5.6-2.8-5.6-7 0-5.2 3.8-9.5 8.8-9.5 1.8 0 3.2.6 4.2 1.6l.6-2.4h5.4l-2.2 10.4c-.4 2 .4 3 1.8 3 2.2 0 4.2-3 4.2-6.6 0-4.6-3.6-7.8-8.8-7.8-6.2 0-11 4.6-11 10.2 0 4.8 3.4 8.2 8 8.2 2.6 0 4.6-1 6.2-2.6l3 3.8C31.6 35.6 27.4 37.5 22.4 37.5 13.4 37.5 6.5 30.6 6.5 21.7 6.5 11.7 14.2 4 24 4z" fill="#f47b20"/><path d="M30.5 18.5c-1.8 5-6 8.4-10.8 9.6 1-4.8 4.6-8.4 10.8-9.6zM18.6 29.6c4.6-.6 8.6-3.4 10.8-7.6-4 .4-8.2 3-10.8 7.6z" fill="#7ac142"/></g>',
            // بله: حباب گرد سبزآبی با گوشهٔ بالا-چپ و تیک سفید (نشان رسمی بله)
            'bale' => '<defs><linearGradient id="smvBale" x1="8" y1="40" x2="40" y2="8"><stop offset="0" stop-color="#28348a"/><stop offset=".55" stop-color="#00a99d"/><stop offset="1" stop-color="#3ddc97"/></linearGradient></defs><path d="M13.5 5.5 8 13A17.5 17.5 0 1 0 13.5 5.5z" fill="url(#smvBale)"/><path d="m15.5 24.2 5.8 5.8L33.5 17.8" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>',
            // تلگرام: هواپیمای کاغذی سفید روی آبی
            'telegram' => '<circle cx="24" cy="24" r="21" fill="#229ed9"/><path d="M35.8 13.2 12.6 22.1c-.9.4-.9 1.5.1 1.8l5.7 1.8 2.2 6.8c.3.8 1.3 1 1.9.4l2.8-2.7 5.4 4c.7.5 1.7.1 1.9-.8l3.1-18.6c.2-1-.8-1.9-1.9-1.6zM21 25.2l9.7-6.1c.2-.1.4.2.2.3l-8.2 7.4-.3 3.2L21 25.2z" fill="#fff"/>',
            // اینستاگرام: دوربین سفید روی گرادیان رسمی
            'instagram' => '<defs><radialGradient id="smvIg2" cx=".3" cy="1.1" r="1.2"><stop offset="0" stop-color="#fdc468"/><stop offset=".45" stop-color="#df4996"/><stop offset=".75" stop-color="#8a3ab9"/><stop offset="1" stop-color="#4c68d7"/></radialGradient></defs><rect width="48" height="48" rx="12" fill="url(#smvIg2)"/><rect x="12" y="12" width="24" height="24" rx="7" fill="none" stroke="#fff" stroke-width="3"/><circle cx="24" cy="24" r="5.2" fill="none" stroke="#fff" stroke-width="3"/><circle cx="31.6" cy="16.4" r="1.9" fill="#fff"/>',
            // آپارات: حلقه‌فیلم صورتی با سه سوراخ و قاب مشکی (نشان رسمی آپارات)
            'aparat' => '<g><path d="M10 8a14 14 0 0 0-2 6M38 8a14 14 0 0 1 2 6M10 40a14 14 0 0 1-2-6M38 40a14 14 0 0 0 2-6" fill="none" stroke="#111" stroke-width="3.4" stroke-linecap="round"/><circle cx="24" cy="24" r="16" fill="#ed145b"/><circle cx="17.5" cy="18.5" r="3.6" fill="#fff"/><circle cx="30.5" cy="18.5" r="3.6" fill="#fff"/><circle cx="24" cy="29.5" r="3.6" fill="#fff"/><circle cx="24" cy="24" r="1.8" fill="#fff"/></g>',
            // یوتیوب: مستطیل قرمز با دکمهٔ پخش
            'youtube' => '<rect x="4" y="10" width="40" height="28" rx="8" fill="#ff0033"/><path d="M20 17.5v13l11-6.5-11-6.5z" fill="#fff"/>',
            // واتس‌اپ: حباب سبز با تلفن سفید
            'whatsapp' => '<path d="M24 4a19.6 19.6 0 0 0-16.7 29.6L5.5 44l10.6-1.7A19.6 19.6 0 1 0 24 4z" fill="#25d366"/><path d="M19.7 14.6c.4-.1 1 0 1.3.6l1.3 3.1c.1.4 0 .9-.3 1.2l-1 1.1a13.6 13.6 0 0 0 6 6l1.1-1c.3-.3.8-.4 1.2-.3l3.1 1.3c.6.3.7.9.6 1.3-.7 2-2.4 3-4.6 2.7-5.9-.8-12.3-7.2-13.1-13.1-.3-2.2.7-3.9 2.4-4.5z" fill="#fff"/>',
            // ایکس: نشان X سفید روی مشکی
            'twitter' => '<rect width="48" height="48" rx="10" fill="#000"/><path d="M14.5 11h5.2l6 8.2L32.8 11h2.7l-8.4 11.3L36 37h-5.2l-6.5-8.9L16.6 37h-2.7l9-12.1L14.5 11zm3.4 2.4 14 21.2h-2.3l-14-21.2h2.3z" fill="#fff"/>',
        );
        $svg_inner = isset($inner[$key]) ? $inner[$key] : '<rect width="48" height="48" rx="12" fill="#123f3a"/><text x="24" y="30" text-anchor="middle" fill="#fff" font-size="18" font-weight="700">' . esc_html(strtoupper(substr($key, 0, 1))) . '</text>';
        return '<svg class="smv-brand-svg" viewBox="0 0 48 48" width="48" height="48" role="img" aria-label="' . esc_attr($key) . '" focusable="false">' . $svg_inner . '</svg>';
    }

    private static function icon_html($key) {
        return '<span class="smv-social-icon smv-social-icon--' . esc_attr($key) . '" aria-hidden="true">' . self::brand_svg($key) . '</span>';
    }

    public static function social_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'platform' => '',
            'count' => 9,
        ), $atts, 'samavieh_social');

        $platforms = self::platforms();
        // هر شبکه‌ای که لینکش در تنظیمات هست، کارت جدا خودش را می‌گیرد.
        $configured = array();
        foreach ($platforms as $key => $info) {
            $link = smv_get_option($key);
            if ($link) {
                $configured[$key] = array('info' => $info, 'url' => $link);
            }
        }
        // اگر شبکهٔ خاصی خواسته شده باشد، کارت همان شبکه اول می‌آید (بقیهٔ کارت‌ها هم می‌مانند تا شورت‌کدهای قبلی نشکنند)
        if (!empty($atts['platform']) && isset($configured[$atts['platform']])) {
            $first_key = $atts['platform'];
            $reordered = array($first_key => $configured[$first_key]);
            foreach ($configured as $key => $item) {
                if ($key !== $first_key) {
                    $reordered[$key] = $item;
                }
            }
            $configured = $reordered;
        }
        if (!$configured) {
            return '';
        }
        $limit = max(1, absint($atts['count']));
        $configured = array_slice($configured, 0, $limit, true);

        ob_start();
        ?>
        <div class="smv-social-grid" role="list">
            <?php foreach ($configured as $key => $item) : $info = $item['info']; ?>
                <a class="smv-social-tile smv-social-tile--<?php echo esc_attr($key); ?>" style="--smv-brand:<?php echo esc_attr($info['brand']); ?>" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer" role="listitem" aria-label="<?php echo esc_attr($info['label']); ?>">
                    <span class="smv-social-tile-icon"><?php echo self::icon_html($key); ?></span>
                    <span class="smv-social-tile-body"><strong><?php echo esc_html($info['label']); ?></strong><small><?php echo esc_html($info['desc']); ?></small></span>
                    <span class="smv-social-tile-cta">مشاهده صفحه <?php echo smv_icon('arrow'); ?></span>
                </a>
            <?php endforeach; ?>
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
