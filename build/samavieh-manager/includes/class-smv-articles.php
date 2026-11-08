<?php
if (!defined('ABSPATH')) { exit; }

class SMV_Articles {
    public static function init() {
        add_shortcode('samavieh_articles', array(__CLASS__, 'shortcode'));
        add_shortcode('samavieh_latest_articles', array(__CLASS__, 'shortcode'));
        add_filter('the_content', array(__CLASS__, 'single_content'), 25);
        add_action('admin_init', array(__CLASS__, 'maybe_seed'), 30);
        // ریدایرکت منوی «مقالات» باید در admin_init (قبل از چاپ هدر مدیریت) انجام شود؛
        // ریدایرکت داخل callback صفحه بعد از ارسال هدرها هشدار «headers already sent» می‌دهد.
        add_action('admin_init', array(__CLASS__, 'maybe_redirect_menu'), 1);
        add_action('admin_post_smv_seed_articles', array(__CLASS__, 'handle_seed'));
        add_action('admin_post_smv_seed_articles_force', array(__CLASS__, 'handle_seed_force'));
    }

    public static function admin_redirect() {
        wp_safe_redirect(admin_url('edit.php?post_type=smv_article'));
        exit;
    }

    /** اگر کاربر روی زیرمنوی «مقالات» کلیک کرده باشد، زودهنگام (بدون خروجی) به لیست مقالات می‌رویم. */
    public static function maybe_redirect_menu() {
        if (!isset($_GET['page']) || 'smv-articles' !== sanitize_key(wp_unslash($_GET['page']))) {
            return;
        }
        if (!current_user_can('manage_smv_registrations')) {
            return;
        }
        self::admin_redirect();
    }

    /** fallback صفحهٔ منو (به‌طور معمول اجرا نمی‌شود چون در admin_init ریدایرکت شده‌ایم). */
    public static function admin_fallback() {
        $url = admin_url('edit.php?post_type=smv_article');
        echo '<div class="wrap" dir="rtl"><h1>مقالات</h1><p>در حال انتقال به فهرست مقالات…</p><p><a class="button button-primary" href="' . esc_url($url) . '">مشاهدهٔ فهرست مقالات</a></p></div>';
        echo '<script>window.location.href=' . wp_json_encode($url) . ';</script>';
    }

    public static function seed_data() {
        $data = include SMV_PATH . 'includes/data-articles.php';
        return is_array($data) ? $data : array();
    }

    public static function build_content($a) {
        $theatres = function_exists('smv_theatres_url') ? smv_theatres_url() : home_url('/theatres/');
        $h = '';
        $h .= '<p>' . esc_html($a['intro']) . '</p>';
        foreach ((array) $a['sections'] as $sec) {
            $h .= '<h2>' . esc_html($sec[0]) . '</h2><p>' . esc_html($sec[1]) . '</p>';
        }
        $h .= '<h2>نکته‌های کاربردی</h2><ul>';
        foreach ((array) $a['tips'] as $t) { $h .= '<li>' . esc_html($t) . '</li>'; }
        $h .= '</ul><h2>پرسش‌های پرتکرار</h2>';
        foreach ((array) $a['faq'] as $f) {
            $h .= '<h3>' . esc_html($f[0]) . '</h3><p>' . esc_html($f[1]) . '</p>';
        }
        $h .= '<h2>سخن پایانی</h2><p>' . esc_html($a['conclusion']) . '</p>';
        $h .= '<p>برای دیدن اجراهای در حال اجرا و رزرو سانس، به <a href="' . esc_url($theatres) . '">صفحهٔ تئاترهای سماویه</a> سر بزنید و دیگر <a href="' . esc_url(get_post_type_archive_link('smv_article')) . '">مقالات سماویه</a> را هم بخوانید.</p>';
        return $h;
    }

    public static function seed($force = false) {
        $created = 0; $skipped = 0;
        foreach (self::seed_data() as $a) {
          try {
            $existing = get_page_by_path($a['slug'], OBJECT, 'smv_article');
            if ($existing && !$force) { $skipped++; continue; }
            $cat_id = 0;
            if (!empty($a['category'])) {
                $term = term_exists($a['category'], 'smv_article_cat');
                if (!$term) { $term = wp_insert_term($a['category'], 'smv_article_cat'); }
                if (!is_wp_error($term) && $term) { $cat_id = is_array($term) ? (int) $term['term_id'] : (int) $term; }
            }
            $postarr = array(
                'post_type' => 'smv_article', 'post_status' => 'publish',
                'post_title' => $a['title'], 'post_name' => $a['slug'],
                'post_content' => self::build_content($a), 'post_excerpt' => $a['excerpt'],
                'comment_status' => 'open',
            );
            if ($existing && $force) { $postarr['ID'] = $existing->ID; $id = wp_update_post($postarr); }
            else { $id = wp_insert_post($postarr); }
            if ($id && !is_wp_error($id)) {
                if ($cat_id) { wp_set_post_terms($id, array($cat_id), 'smv_article_cat', false); }
                if (!empty($a['tags'])) { wp_set_post_terms($id, (array) $a['tags'], 'smv_article_tag', false); }
                update_post_meta($id, '_smv_focus_keyword', $a['keyword']);
                update_post_meta($id, '_smv_seeded_article', '1');
                if (class_exists('SMV_SEO')) { SMV_SEO::fill_for_post($id, true); }
                $created++;
            }
          } catch (Throwable $e) {
            update_option('smv_articles_seed_error', $e->getMessage());
          }
        }
        update_option('smv_articles_seeded_v1', array('count' => $created, 'skipped' => $skipped, 'time' => time()));
        return array('created' => $created, 'skipped' => $skipped);
    }

    public static function maybe_seed() {
        // ساخت مقالات فقط یک بار، در محیط مدیریت، وقتی کاربرِ مجاز وارد شده باشد
        // و پرچم «در انتظار ساخت» هنگام فعال‌سازی افزونه ست شده باشد؛ هیچ کار سنگینی
        // هنگام فعال‌سازی یا بارگذاری صفحات سایت انجام نمی‌شود تا فعال‌سازی روی هیچ هاستی خطا ندهد.
        if (get_option('smv_articles_seeded_v1')) { delete_option('smv_seed_articles_pending'); return; }
        if (!post_type_exists('smv_article')) { return; }
        if (!current_user_can('manage_smv_registrations')) { return; }
        // ساخت وقتی انجام می‌شود که: پرچم فعال‌سازی ست شده باشد، یا هنوز هیچ مقاله‌ای وجود نداشته باشد
        // (در به‌روزرسانی افزونه هوک فعال‌سازی اجرا نمی‌شود و پرچم ست نشده است).
        if (!get_option('smv_seed_articles_pending')) {
            $counts = wp_count_posts('smv_article');
            $existing = 0;
            if ($counts) {
                foreach (array('publish', 'draft', 'pending', 'private') as $st) {
                    if (isset($counts->$st)) { $existing += (int) $counts->$st; }
                }
            }
            if ($existing > 0) { return; }
        }
        try {
            self::seed(false);
            delete_option('smv_seed_articles_pending');
        } catch (Throwable $e) {
            update_option('smv_articles_seed_error', $e->getMessage());
            delete_option('smv_seed_articles_pending');
        }
    }

    public static function handle_seed() {
        if (!current_user_can('manage_smv_registrations')) { wp_die('دسترسی لازم را ندارید.'); }
        check_admin_referer('smv_seed_articles');
        $r = self::seed(false);
        wp_safe_redirect(add_query_arg(array('smv_articles_seeded' => $r['created'], 'smv_articles_skipped' => $r['skipped']), admin_url('admin.php?page=smv-seo')));
        exit;
    }
    public static function handle_seed_force() {
        if (!current_user_can('manage_smv_registrations')) { wp_die('دسترسی لازم را ندارید.'); }
        check_admin_referer('smv_seed_articles_force');
        $r = self::seed(true);
        wp_safe_redirect(add_query_arg(array('smv_articles_seeded' => $r['created'], 'smv_articles_skipped' => $r['skipped']), admin_url('admin.php?page=smv-seo')));
        exit;
    }

    public static function reading_time($content) {
        $words = str_word_count(wp_strip_all_tags($content));
        // str_word_count undercounts Persian; fallback by chars
        $chars = preg_match_all('/./us', wp_strip_all_tags($content), $mm); $words = max($words, (int) (($chars ? $chars : strlen(wp_strip_all_tags($content))) / 6));
        return max(1, (int) ceil($words / 180));
    }

    public static function card($post) {
        $cats = get_the_terms($post->ID, 'smv_article_cat');
        $cat = ($cats && !is_wp_error($cats)) ? $cats[0]->name : '';
        $img = get_the_post_thumbnail_url($post->ID, 'medium_large');
        $date = class_exists('SMV_Persian') ? SMV_Persian::date(get_the_date('Y-m-d', $post)) : get_the_date('', $post);
        ob_start(); ?>
        <article class="smv-article-card">
            <a class="smv-article-media" href="<?php echo esc_url(get_permalink($post)); ?>">
                <?php if ($img) { ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($post)); ?>" loading="lazy"><?php } else { ?><span class="smv-article-media-fallback">📖</span><?php } ?>
                <?php if ($cat) { ?><span class="smv-article-cat"><?php echo esc_html($cat); ?></span><?php } ?>
            </a>
            <div class="smv-article-body">
                <h3><a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></h3>
                <p><?php echo esc_html(wp_trim_words(get_the_excerpt($post) ?: wp_strip_all_tags($post->post_content), 24)); ?></p>
                <div class="smv-article-meta"><span><?php echo esc_html($date); ?></span><span><?php echo (int) self::reading_time($post->post_content); ?> دقیقه مطالعه</span></div>
                <a class="smv-article-more" href="<?php echo esc_url(get_permalink($post)); ?>">خواندن مقاله ←</a>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    public static function shortcode($atts) {
        if (class_exists('SMV_Plugin')) { SMV_Plugin::enqueue_frontend(); }
        $atts = shortcode_atts(array('count' => 9, 'category' => '', 'columns' => 3), $atts, 'samavieh_articles');
        $args = array('post_type' => 'smv_article', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['count'], 'orderby' => 'date', 'order' => 'DESC');
        if ($atts['category']) { $args['tax_query'] = array(array('taxonomy' => 'smv_article_cat', 'field' => 'slug', 'terms' => sanitize_title($atts['category']))); }
        $q = new WP_Query($args);
        if (!$q->have_posts()) { return '<div class="smv-articles-empty">هنوز مقاله‌ای منتشر نشده است.</div>'; }
        $h = '<div class="smv-articles-grid cols-' . (int) $atts['columns'] . '">';
        while ($q->have_posts()) { $q->the_post(); $h .= self::card(get_post()); }
        wp_reset_postdata();
        return $h . '</div>';
    }

    public static function single_content($content) {
        if (is_admin() || !is_singular('smv_article') || !in_the_loop() || !is_main_query()) { return $content; }
        $id = get_the_ID();
        $cats = get_the_terms($id, 'smv_article_cat');
        $args = array('post_type' => 'smv_article', 'post_status' => 'publish', 'posts_per_page' => 3, 'post__not_in' => array($id), 'orderby' => 'date', 'order' => 'DESC');
        if ($cats && !is_wp_error($cats)) {
            $args['tax_query'] = array(array('taxonomy' => 'smv_article_cat', 'field' => 'term_id', 'terms' => array($cats[0]->term_id)));
        }
        $q = new WP_Query($args);
        $extra = '';
        if ($q->have_posts()) {
            $extra .= '<section class="smv-related-articles"><h2>مقالات مرتبط</h2><div class="smv-articles-grid cols-3">';
            while ($q->have_posts()) { $q->the_post(); $extra .= self::card(get_post()); }
            wp_reset_postdata();
            $extra .= '</div></section>';
        }
        $theatres = function_exists('smv_theatres_url') ? smv_theatres_url() : home_url('/theatres/');
        $extra .= '<div class="smv-article-cta"><strong>دوست دارید تئاتر را از نزدیک تجربه کنید؟</strong><p>اجراهای در حال اجرای سماویه را ببینید و سانس مناسب‌تان را رزرو کنید.</p><a class="smv-button smv-button-gold" href="' . esc_url($theatres) . '">مشاهده و ثبت‌نام تئاترها</a></div>';
        return $content . $extra;
    }
}
