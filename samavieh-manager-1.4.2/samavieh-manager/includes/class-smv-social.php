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

    private static function platforms() {
        return array(
            'instagram' => array('label' => 'اینستاگرام', 'icon' => '📷'),
            'telegram' => array('label' => 'تلگرام', 'icon' => '✈️'),
            'whatsapp' => array('label' => 'واتس‌اپ', 'icon' => '💬'),
            'twitter' => array('label' => 'توییتر / ایکس', 'icon' => '🐦'),
            'youtube' => array('label' => 'یوتیوب', 'icon' => '▶️'),
            'aparat' => array('label' => 'آپارات', 'icon' => '🎬'),
        );
    }

    public static function social_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'platform' => 'instagram',
            'count' => 6,
        ), $atts, 'samavieh_social');

        $platforms = self::platforms();
        $platform = isset($platforms[$atts['platform']]) ? $atts['platform'] : 'instagram';
        $url = smv_get_option($platform);
        if (!$url) {
            return '';
        }
        $label = $platforms[$platform]['label'];

        ob_start();
        ?>
        <div class="smv-social-profile">
            <a class="smv-social-card" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                <span class="smv-social-icon"><?php echo esc_html($platforms[$platform]['icon']); ?></span>
                <span class="smv-social-body">
                    <strong>ما را در <?php echo esc_html($label); ?> دنبال کنید</strong>
                    <small>جدیدترین تصاویر و ویدیوهای پشت صحنه را ببینید.</small>
                </span>
                <span class="smv-social-cta">مشاهده صفحه <?php echo smv_icon('arrow'); ?></span>
            </a>
            <div class="smv-social-links">
                <?php foreach ($platforms as $key => $info) : $link = smv_get_option($key); ?>
                    <?php if ($link && $key !== $platform) : ?>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($info['icon'] . ' ' . $info['label']); ?></a>
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
