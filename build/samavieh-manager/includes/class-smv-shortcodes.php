<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Shortcodes {
    public static function init() {
        add_shortcode('samavieh_home', array(__CLASS__, 'home'));
        add_shortcode('samavieh_theatres', array(__CLASS__, 'theatres'));
        add_shortcode('samavieh_projects', array(__CLASS__, 'projects'));
        add_shortcode('samavieh_theatre_details', array(__CLASS__, 'theatre_details'));
        add_shortcode('samavieh_registration', array(__CLASS__, 'registration'));
        add_shortcode('samavieh_tracking', array(__CLASS__, 'tracking'));
        add_shortcode('samavieh_contact', array(__CLASS__, 'contact'));
        add_shortcode('samavieh_showtimes', array('SMV_Booking', 'showtimes_shortcode'));
        add_shortcode('samavieh_seat_map', array('SMV_Booking', 'seat_map_shortcode'));
        add_shortcode('samavieh_poll', array('SMV_Poll', 'poll_shortcode'));
        add_shortcode('samavieh_reviews', array('SMV_Review', 'reviews_shortcode'));
        add_shortcode('samavieh_social', array('SMV_Social', 'social_shortcode'));
        add_shortcode('samavieh_tweets', array('SMV_Social', 'tweets_shortcode'));
        add_shortcode('samavieh_payment', array('SMV_Payment', 'payment_shortcode'));
        add_shortcode('samavieh_enamad', array('SMV_Enamad', 'render_seal'));

        // AJAX for gallery attachment URL (فقط برای مدیر وارد‌شده)
        add_action('wp_ajax_smv_get_attachment_url', array(__CLASS__, 'ajax_get_attachment_url'));
    }

    public static function ajax_get_attachment_url() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_send_json_error(array('message' => 'دسترسی لازم را ندارید.'));
        }
        $id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : '';
        wp_send_json_success(array('url' => $url ? $url : ''));
    }

    private static function page_url($slug, $fallback = '') {
        $page = get_page_by_path($slug);
        if ($page) {
            return get_permalink($page);
        }
        return $fallback ? $fallback : home_url('/' . $slug . '/');
    }

    private static function tracking_url() {
        $page_id = absint(smv_get_option('tracking_page_id'));
        return $page_id ? get_permalink($page_id) : self::page_url('tracking');
    }

    public static function home() {
        SMV_Plugin::enqueue_frontend();
        $org = smv_get_option('organization_name', 'گروه فرهنگی هنری سماویه');
        $tagline = smv_get_option('tagline', 'هنر، اندیشه و معنویت');
        $hero_title = smv_get_option('home_hero_title', 'روایتِ نور،');
        $hero_highlight = smv_get_option('home_hero_highlight', 'بر صحنهٔ دل‌ها');
        $hero_text = smv_get_option('home_hero_text');
        $about_title = smv_get_option('home_about_title');
        $about_text = smv_get_option('home_about_text');
        $custom_logo_id = absint(get_theme_mod('custom_logo'));
        $brand_logo = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
        if (!$brand_logo) {
            $brand_logo = SMV_URL . 'assets/images/samavieh-logo-gold.png';
        }
        ob_start();
        ?>
        <div class="smv-home">
            <section class="smv-hero">
                <div class="smv-hero-pattern" aria-hidden="true"></div>
                <div class="smv-container smv-hero-grid">
                    <div class="smv-hero-content">
                        <span class="smv-eyebrow"><i></i><?php echo esc_html($org); ?></span>
                        <h1><?php echo esc_html($hero_title); ?><br><em><?php echo esc_html($hero_highlight); ?></em></h1>
                        <p><?php echo esc_html($hero_text); ?></p>
                        <div class="smv-button-row">
                            <a class="smv-button smv-button-gold" href="<?php echo esc_url(smv_theatres_url()); ?>">مشاهده و ثبت‌نام تئاترها <?php echo smv_icon('arrow'); ?></a>
                            <a class="smv-button smv-button-ghost" href="<?php echo esc_url(self::page_url('about')); ?>">آشنایی با سماویه</a>
                        </div>
                        <div class="smv-hero-note"><?php echo smv_icon('check'); ?><span>ثبت‌نام آسان، پیگیری شفاف</span></div>
                    </div>
                    <div class="smv-hero-art" aria-label="نشان تزئینی سماویه">
                        <div class="smv-art-star smv-art-star-one" aria-hidden="true">✦</div>
                        <div class="smv-art-star smv-art-star-two" aria-hidden="true">✧</div>
                        <div class="smv-arch">
                            <div class="smv-arch-inner">
                                <span class="smv-art-bismillah">هنر • معنویت • زیبایی</span>
                                <img class="smv-hero-official-logo" src="<?php echo esc_url($brand_logo); ?>" alt="">
                                <span><?php echo esc_html($tagline); ?></span>
                                <div class="smv-art-geometry" aria-hidden="true">✦</div>
                            </div>
                        </div>
                        <div class="smv-art-caption"><span></span> قصه‌ای برای ماندن <span></span></div>
                    </div>
                </div>
                <div class="smv-hero-wave" aria-hidden="true"></div>
            </section>

            <section class="smv-features smv-container" aria-label="مزایای ثبت‌نام">
                <article><span class="smv-feature-icon"><?php echo smv_icon('ticket'); ?></span><div><h2>انتخاب اجرا</h2><p>مشخصات کامل هر تئاتر را ببینید.</p></div></article>
                <article><span class="smv-feature-icon"><?php echo smv_icon('upload'); ?></span><div><h2>ارسال فیش</h2><p>ثبت‌نام و آپلود فیش در چند دقیقه.</p></div></article>
                <article><span class="smv-feature-icon"><?php echo smv_icon('search'); ?></span><div><h2>پیگیری وضعیت</h2><p>نتیجهٔ بررسی را با کد پیگیری ببینید.</p></div></article>
            </section>

            <section class="smv-section smv-container">
                <div class="smv-section-heading">
                    <div><span class="smv-kicker">روی صحنهٔ سماویه</span><h2>تئاترهای پیش رو</h2><p>اجرای موردنظر را انتخاب کنید و ثبت‌نام را آنلاین انجام دهید.</p></div>
                    <a class="smv-text-link" href="<?php echo esc_url(smv_theatres_url()); ?>">مشاهده همه <?php echo smv_icon('arrow'); ?></a>
                </div>
                <?php echo do_shortcode('[samavieh_theatres count="3" columns="3" show_price="0"]'); ?>
            </section>

            <section class="smv-section smv-container smv-home-social">
                <div class="smv-centered-heading"><span class="smv-kicker">همراه سماویه باشید</span><h2>شبکه‌های اجتماعی سماویه</h2><p>ما را در روبیکا، ایتا، بله و دیگر شبکه‌ها دنبال کنید.</p></div>
                <?php echo do_shortcode('[samavieh_social]'); ?>
            </section>

            <section class="smv-about-band">
                <div class="smv-container smv-about-grid">
                    <div class="smv-about-emblem" aria-hidden="true"><div><span>✦</span><img class="smv-about-official-logo" src="<?php echo esc_url($brand_logo); ?>" alt=""><small>قصه‌ای از جنس معنا</small></div></div>
                    <div class="smv-about-copy">
                        <span class="smv-kicker smv-kicker-light">دربارهٔ سماویه</span>
                        <h2><?php echo nl2br(esc_html($about_title)); ?></h2>
                        <p><?php echo esc_html($about_text); ?></p>
                        <ul class="smv-check-list"><li>روایت‌هایی اصیل و خانواده‌محور</li><li>فضایی حرفه‌ای برای تجربهٔ هنر</li><li>ارتباط مستقیم و محترمانه با مخاطبان</li></ul>
                        <a class="smv-button smv-button-outline-light" href="<?php echo esc_url(self::page_url('about')); ?>">بیشتر دربارهٔ ما <?php echo smv_icon('arrow'); ?></a>
                    </div>
                </div>
            </section>

            <section class="smv-section smv-container">
                <div class="smv-section-heading"><div><span class="smv-kicker">مسیر فعالیت‌ها</span><h2>پروژه‌های سماویه</h2><p>گزیده‌ای از رویدادها و فعالیت‌های فرهنگی هنری.</p></div><a class="smv-text-link" href="<?php echo esc_url(self::page_url('projects')); ?>">همهٔ پروژه‌ها <?php echo smv_icon('arrow'); ?></a></div>
                <?php echo do_shortcode('[samavieh_projects count="3" columns="3"]'); ?>
            </section>

            <section class="smv-steps-band">
                <div class="smv-container">
                    <div class="smv-centered-heading"><span class="smv-kicker">فقط در سه مرحله</span><h2>از انتخاب اجرا تا تأیید ثبت‌نام</h2></div>
                    <div class="smv-steps">
                        <article><span>۱</span><div><h3>تئاتر و سانس را انتخاب کنید</h3><p>زمان، محل اجرا و ظرفیت را بررسی کنید.</p></div></article>
                        <article><span>۲</span><div><h3>روش هزینه را انتخاب کنید</h3><p>رایگان، هزینهٔ ثبت‌نام با مبلغ مشخص، یا کمک مالی با مبلغی که خودتان تعیین می‌کنید.</p></div></article>
                        <article><span>۳</span><div><h3>کد پیگیری را نگه دارید</h3><p>نتیجهٔ بررسی را هر زمان با کد و شماره تماس ببینید.</p></div></article>
                    </div>
                </div>
            </section>

            <section class="smv-container smv-cta">
                <div><span class="smv-kicker smv-kicker-light">منتظرتان هستیم</span><h2>صندلیِ شما در سماویه آماده است</h2><p>اجرای بعدی را از همین حالا انتخاب کنید.</p></div>
                <div class="smv-button-row"><a class="smv-button smv-button-gold" href="<?php echo esc_url(smv_theatres_url()); ?>">مشاهده تئاترها</a><a class="smv-button smv-button-ghost" href="<?php echo esc_url(self::tracking_url()); ?>">پیگیری ثبت‌نام</a></div>
            </section>

            <?php echo do_shortcode('[samavieh_enamad]'); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function theatres($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('count' => -1, 'columns' => 3, 'ids' => '', 'show_price' => 1), $atts, 'samavieh_theatres');
        $show_price = !empty($atts['show_price']) && '0' !== (string) $atts['show_price'];
        $args = array(
            'post_type' => 'smv_theatre',
            'post_status' => 'publish',
            'posts_per_page' => max(-1, (int) $atts['count']),
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        );
        if ($atts['ids']) {
            $args['post__in'] = array_map('absint', explode(',', $atts['ids']));
            $args['orderby'] = 'post__in';
        }
        $query = new WP_Query($args);
        $columns = min(4, max(1, absint($atts['columns'])));
        ob_start();
        if ($query->have_posts()) {
            echo '<div class="smv-card-grid smv-columns-' . esc_attr($columns) . '">';
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $open = smv_theatre_registration_open($id);
                $date = get_post_meta($id, '_smv_start_date', true);
                $venue = get_post_meta($id, '_smv_venue', true);
                $price = get_post_meta($id, '_smv_price', true);
                $director = get_post_meta($id, '_smv_director', true);
                ?>
                <article <?php post_class('smv-theatre-card'); ?>>
                    <a class="smv-card-image smv-card-image--portrait" href="<?php the_permalink(); ?>" aria-label="مشاهده <?php the_title_attribute(); ?>">
                        <?php $card_image = smv_theatre_portrait_url($id); ?>
                        <?php if ($card_image) : ?>
                            <img src="<?php echo esc_url($card_image); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                        <?php else : ?>
                            <img src="<?php echo esc_url(SMV_URL . 'assets/images/theatre-placeholder.svg'); ?>" alt="تصویر پیش‌فرض تئاتر" loading="lazy" />
                        <?php endif; ?>
                        <span class="smv-status-pill <?php echo $open ? 'is-open' : 'is-closed'; ?>"><i></i><?php echo $open ? 'ثبت‌نام باز' : 'ثبت‌نام بسته'; ?></span>
                    </a>
                    <div class="smv-card-body">
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if ($director) : ?><div class="smv-card-meta-item"><?php echo smv_icon('user'); ?><?php echo esc_html('کارگردان: ' . $director); ?></div><?php endif; ?>
                        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 18, '…')); ?></p>
                        <div class="smv-card-meta">
                            <?php if ($date) : ?><span><?php echo smv_icon('calendar'); ?><?php echo esc_html(SMV_Persian::date($date)); ?></span><?php endif; ?>
                            <?php if ($venue) : ?><span><?php echo smv_icon('pin'); ?><?php echo esc_html($venue); ?></span><?php endif; ?>
                        </div>
                        <div class="smv-card-footer"><?php if ($show_price) : ?><strong><?php echo esc_html(SMV_Persian::money($price)); ?></strong><?php endif; ?><a href="<?php the_permalink(); ?>"><?php echo $open ? 'جزئیات و ثبت‌نام' : 'مشاهده جزئیات'; ?> <?php echo smv_icon('arrow'); ?></a></div>
                    </div>
                </article>
                <?php
            }
            echo '</div>';
        } else {
            echo '<div class="smv-empty">' . smv_icon('theatre') . '<h3>تئاتر فعالی ثبت نشده است</h3><p>به‌زودی اطلاعات اجراهای جدید در این بخش قرار می‌گیرد.</p></div>';
        }
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function projects($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('count' => -1, 'columns' => 3), $atts, 'samavieh_projects');
        $query = new WP_Query(array(
            'post_type' => 'smv_project',
            'post_status' => 'publish',
            'posts_per_page' => max(-1, (int) $atts['count']),
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));
        $columns = min(4, max(1, absint($atts['columns'])));
        ob_start();
        if ($query->have_posts()) {
            echo '<div class="smv-card-grid smv-project-grid smv-columns-' . esc_attr($columns) . '">';
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $year = get_post_meta($id, '_smv_project_year', true);
                $status = get_post_meta($id, '_smv_project_status', true);
                ?>
                <article <?php post_class('smv-project-card'); ?>>
                    <a class="smv-project-image" href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) : the_post_thumbnail('medium_large', array('loading' => 'lazy')); else : ?>
                            <img src="<?php echo esc_url(SMV_URL . 'assets/images/project-placeholder.svg'); ?>" alt="تصویر پیش‌فرض پروژه" loading="lazy" />
                        <?php endif; ?>
                        <span class="smv-project-overlay"></span>
                        <div class="smv-project-title"><small><?php echo 'ongoing' === $status ? 'در حال اجرا' : 'پروژهٔ فرهنگی هنری'; ?><?php echo $year ? ' • ' . esc_html($year) : ''; ?></small><h3><?php the_title(); ?></h3><span>مشاهده پروژه <?php echo smv_icon('arrow'); ?></span></div>
                    </a>
                </article>
                <?php
            }
            echo '</div>';
        } else {
            echo '<div class="smv-empty">' . smv_icon('folder') . '<h3>پروژه‌ای ثبت نشده است</h3><p>پروژه‌های فرهنگی و هنری سماویه در این بخش معرفی می‌شوند.</p></div>';
        }
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function theatre_details($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('id' => 0), $atts, 'samavieh_theatre_details');
        $id = $atts['id'] ? absint($atts['id']) : get_the_ID();
        if (!$id || 'smv_theatre' !== get_post_type($id)) {
            return '';
        }

        $meta = get_post_meta($id);
        $director = isset($meta['_smv_director'][0]) ? $meta['_smv_director'][0] : '';
        $writer = isset($meta['_smv_writer'][0]) ? $meta['_smv_writer'][0] : '';
        $cast = isset($meta['_smv_cast'][0]) ? $meta['_smv_cast'][0] : '';
        $age_range = isset($meta['_smv_age_range'][0]) ? $meta['_smv_age_range'][0] : '';
        $duration = isset($meta['_smv_duration'][0]) ? $meta['_smv_duration'][0] : '';
        $start_date = isset($meta['_smv_start_date'][0]) ? $meta['_smv_start_date'][0] : '';
        $end_date = isset($meta['_smv_end_date'][0]) ? $meta['_smv_end_date'][0] : '';
        $venue = isset($meta['_smv_venue'][0]) ? $meta['_smv_venue'][0] : '';
        $price = isset($meta['_smv_price'][0]) ? $meta['_smv_price'][0] : '';
        $registration_deadline = isset($meta['_smv_registration_deadline'][0]) ? $meta['_smv_registration_deadline'][0] : '';
        $registration_status = isset($meta['_smv_registration_status'][0]) ? $meta['_smv_registration_status'][0] : 'open';
        $gallery_ids = isset($meta['_smv_gallery'][0]) ? maybe_unserialize($meta['_smv_gallery'][0]) : array();
        if (!is_array($gallery_ids)) {
            $gallery_ids = array();
        }
        $video_url = isset($meta['_smv_video_url'][0]) ? $meta['_smv_video_url'][0] : '';
        $videos = function_exists('smv_theatre_videos') ? smv_theatre_videos($id) : array();

        $open = smv_theatre_registration_open($id);
        $showtimes = smv_get_showtimes_for_theatre($id);
        $payment_methods = smv_theatre_payment_methods($id);

        ob_start();
        ?>
        <section class="smv-theatre-details smv-panel">
            <div class="smv-panel-heading"><div><span class="smv-kicker">پیش از ثبت‌نام</span><h2>اطلاعات اجرا</h2></div><span class="smv-status-pill <?php echo $open ? 'is-open' : 'is-closed'; ?>"><i></i><?php echo $open ? 'ثبت‌نام باز' : 'ثبت‌نام بسته'; ?></span></div>

            <!-- Poster (وسط‌چین) -->
            <div class="smv-theatre-media smv-poster-center">
                <?php if (has_post_thumbnail($id)) : ?>
                    <figure class="smv-poster-figure"><img src="<?php echo esc_url(get_the_post_thumbnail_url($id, 'large')); ?>" alt="<?php echo esc_attr(get_the_title($id)); ?>" class="smv-theatre-poster" /><figcaption><?php echo esc_html(get_the_title($id)); ?></figcaption></figure>
                <?php else : ?>
                    <figure class="smv-poster-figure"><img src="<?php echo esc_url(SMV_URL . 'assets/images/theatre-placeholder.svg'); ?>" alt="<?php echo esc_attr(get_the_title($id)); ?>" class="smv-theatre-poster" /><figcaption><?php echo esc_html(get_the_title($id)); ?></figcaption></figure>
                <?php endif; ?>
            </div>

            <!-- مدیا و ویدیوها: همیشه نمایش داده می‌شود -->
            <section class="smv-media-section" id="smv-media">
                <h3>🎬 مدیا و ویدیوهای اجرا</h3>
                <?php if (!empty($videos)) : ?>
                    <div class="smv-videos-list">
                        <?php foreach ($videos as $video) : ?>
                            <div class="smv-video-item"><?php echo smv_video_embed_html($video); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($gallery_ids)) : ?>
                    <div class="smv-theatre-gallery">
                        <?php
                        foreach ($gallery_ids as $gid) :
                            $img = wp_get_attachment_image_src(absint($gid), 'medium');
                            $full = wp_get_attachment_image_src(absint($gid), 'large');
                            if (!$img || empty($img[0])) {
                                continue;
                            }
                            ?>
                            <a class="smv-gallery-link" href="<?php echo esc_url($full && !empty($full[0]) ? $full[0] : $img[0]); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($img[0]); ?>" alt="<?php echo esc_attr(get_the_title($id)); ?>" class="smv-gallery-img" loading="lazy" /></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($videos) && empty($gallery_ids)) : ?>
                    <div class="smv-media-empty">به‌زودی تریلر، تیزر و تصاویر این اجرا در این بخش قرار می‌گیرد.</div>
                <?php endif; ?>
            </section>

            <div class="smv-details-grid">
                <?php foreach (array(
                    'director' => array('کارگردان', 'user', $director),
                    'writer' => array('نویسنده', 'user', $writer),
                    'cast' => array('بازیگران', 'people', $cast),
                    'start_date' => array('تاریخ شروع', 'calendar', $start_date ? SMV_Persian::date($start_date) : ''),
                    'end_date' => array('تاریخ پایان', 'calendar', $end_date ? SMV_Persian::date($end_date) : ''),
                    'show_time' => array('ساعت اجرا', 'clock', 'به ازای هر سانس'),
                    'duration' => array('مدت اجرا', 'clock', $duration),
                    'age_range' => array('ردهٔ سنی', 'user', $age_range),
                    'venue' => array('محل اجرا', 'pin', $venue),
                    'price' => array('هزینهٔ ثبت‌نام', 'ticket', $price ? SMV_Persian::money($price) : 'رایگان'),
                    'registration_deadline' => array('مهلت ثبت‌نام', 'calendar', $registration_deadline ? SMV_Persian::date($registration_deadline) : ''),
                ) as $key => $item) :
                    if (empty($item[2])) { continue; }
                    ?>
                    <div class="smv-detail-item"><span><?php echo smv_icon($item[1]); ?></span><div><small><?php echo esc_html($item[0]); ?></small><strong><?php echo esc_html($item[2]); ?></strong></div></div>
                <?php endforeach; ?>
                <?php $remaining = smv_theatre_seats_remaining($id); if (null !== $remaining) : ?>
                    <div class="smv-detail-item"><span><?php echo smv_icon('ticket'); ?></span><div><small>ظرفیت باقی‌مانده</small><strong><?php echo esc_html(SMV_Persian::digits($remaining)); ?> نفر</strong></div></div>
                <?php endif; ?>
            </div>

            <div class="smv-theatre-actions">
                <div class="smv-payment-methods">
                    <h3>روش‌های پرداخت</h3>
                    <div class="smv-payment-options">
                        <?php if (in_array('free', $payment_methods, true)) : ?><span class="smv-payment-badge is-free"><?php echo smv_icon('ticket'); ?> رایگان</span><?php endif; ?>
                        <?php if (in_array('paid', $payment_methods, true)) : ?><span class="smv-payment-badge is-paid"><?php echo smv_icon('ticket'); ?> هزینهٔ ثبت‌نام (مبلغ مشخص<?php echo $price ? ' — ' . esc_html(SMV_Persian::money($price)) : ''; ?>)</span><?php endif; ?>
                        <?php if (in_array('financial_aid', $payment_methods, true)) : ?><span class="smv-payment-badge is-aid"><?php echo smv_icon('user'); ?> کمک مالی (مبلغ به انتخاب شما)</span><?php endif; ?>
                    </div>
                </div>

                <div class="smv-showtimes-section">
                    <h3>سانس‌ها و رز صندلی</h3>
                    <?php echo do_shortcode('[samavieh_showtimes theatre_id="' . esc_attr($id) . '" columns="3"]'); ?>
                </div>

                <?php if ($open) : ?>
                    <div class="smv-registration-section">
                        <h3>ثبت‌نام در اجرا</h3>
                        <?php echo do_shortcode('[samavieh_registration theatre="' . esc_attr($id) . '"]'); ?>
                    </div>
                <?php else : ?>
                    <div class="smv-closed-notice"><?php echo smv_icon('ticket'); ?> ثبت‌نام برای این اجرا در حال حاضر غیرفعال است.</div>
                <?php endif; ?>

                <div class="smv-reviews-section">
                    <h3>نظرات مخاطبان</h3>
                    <?php echo do_shortcode('[samavieh_reviews theatre_id="' . esc_attr($id) . '" count="2"]'); ?>
                </div>

                <div class="smv-poll-section">
                    <h3>نظرسنجی</h3>
                    <?php echo do_shortcode('[samavieh_poll]'); ?>
                </div>

                <div class="smv-social-section">
                    <h3>شبکه‌های اجتماعی</h3>
                    <?php echo do_shortcode('[samavieh_social count="6"]'); ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function registration($atts) {
        // فرم ثبت‌نام در هر صفحه فقط یک بار رندر می‌شود تا شناسه‌های تکراری و ارسال اشتباه رخ ندهد.
        static $rendered = false;
        if ($rendered) {
            return '';
        }

        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('theatre' => 0), $atts, 'samavieh_registration');
        $theatre_id = $atts['theatre'] ? absint($atts['theatre']) : get_the_ID();
        if (!$theatre_id || 'smv_theatre' !== get_post_type($theatre_id)) {
            return '<div class="smv-notice smv-notice-info" id="smv-registration">' . smv_icon('ticket') . '<div><strong>ابتدا تئاتر را انتخاب کنید</strong><p>برای تکمیل فرم، وارد صفحهٔ اختصاصی تئاتر موردنظر شوید.</p><a class="smv-text-link" href="' . esc_url(smv_theatres_url()) . '">مشاهده تئاترها</a></div></div>';
        }

        $rendered = true;

        // سانس‌ها روزبه‌روز و داخل هر روز به ترتیب اولویت پر می‌شوند:
        // در هر روز فقط اولین سانس دارای ظرفیت «فعال» و قابل انتخاب است؛
        // قبلی‌های همان روز «تکمیل ظرفیت» و بعدی‌های همان روز «در انتظار» هستند. روزهای مختلف مستقل‌اند.
        $fill_mode = function_exists('smv_theatre_fill_mode') ? smv_theatre_fill_mode($theatre_id) : 'ordered';
        $active_ids_by_day = function_exists('smv_active_showtime_ids') ? smv_active_showtime_ids($theatre_id) : array();
        $showtimes_by_day = array();
        foreach (smv_group_showtimes_by_day($theatre_id) as $day_date => $day_showtimes) {
            $priority = 0;
            foreach ($day_showtimes as $showtime) {
                $sid = $showtime->ID;
                $priority++;
                $remaining = smv_showtime_seats_remaining($sid);
                $state = function_exists('smv_showtime_state') ? smv_showtime_state($sid, $theatre_id) : ((null !== $remaining && $remaining <= 0) ? 'full' : 'active');
                $showtimes_by_day[$day_date][] = array(
                    'id' => $sid,
                    'priority' => $priority,
                    'state' => $state,
                    'date' => get_post_meta($sid, '_smv_showtime_date', true),
                    'time' => get_post_meta($sid, '_smv_showtime_time', true),
                    'time_end' => get_post_meta($sid, '_smv_showtime_time_end', true),
                    'venue' => get_post_meta($sid, '_smv_showtime_venue', true),
                    'price' => function_exists('smv_registration_fixed_fee') ? smv_registration_fixed_fee($theatre_id, $sid) : absint(get_post_meta($sid, '_smv_showtime_price', true)),
                    'remaining' => $remaining,
                    'capacity' => absint(get_post_meta($sid, '_smv_capacity', true)),
                );
            }
        }
        $fixed_fee = function_exists('smv_registration_fixed_fee') ? smv_registration_fixed_fee($theatre_id) : 0;

        $payment_methods = smv_theatre_payment_methods($theatre_id);
        $state = SMV_Forms::state();
        $old = $state && !empty($state['old']) ? $state['old'] : array();
        ob_start();
        ?>
        <section class="smv-registration smv-panel" id="smv-registration">
            <?php if ($state && !empty($state['success'])) : ?>
                <div class="smv-success-box">
                    <div class="smv-success-icon"><?php echo smv_icon('check'); ?></div>
                    <span class="smv-kicker">درخواست شما دریافت شد</span>
                    <h2>ثبت اولیه با موفقیت انجام شد</h2>
                    <p><?php echo esc_html($state['message']); ?></p>
                    <?php if (!empty($state['theatre'])) : ?><div class="smv-success-theatre">تئاتر: <strong><?php echo esc_html($state['theatre']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($state['showtime'])) : ?><div class="smv-success-showtime">سانس: <strong><?php echo esc_html($state['showtime']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($state['payment_type'])) : ?><div class="smv-success-payment">روش پرداخت: <strong><?php echo esc_html($state['payment_type']); ?></strong></div><?php endif; ?>
                    <?php if (isset($state['people_count'])) : ?><div class="smv-success-people">تعداد افراد: <strong><?php echo esc_html(SMV_Persian::digits($state['people_count'])); ?> نفر<?php echo !empty($state['companions']) ? ' (' . esc_html(smv_companions_label($state['companions'])) . ')' : ' (بدون همراه)'; ?></strong></div><?php endif; ?>
                    <?php if (!empty($state['amount'])) : ?><div class="smv-success-amount">مبلغ: <strong><?php echo esc_html(SMV_Persian::money($state['amount'])); ?></strong></div><?php endif; ?>
                    <div class="smv-tracking-code-box"><small>کد پیگیری شما</small><code dir="ltr"><?php echo esc_html($state['tracking_code']); ?></code><button type="button" class="smv-copy-button" data-smv-copy="<?php echo esc_attr($state['tracking_code']); ?>"><?php echo smv_icon('copy'); ?> کپی کد</button></div>
                    <div class="smv-important-note"><?php echo smv_icon('alert'); ?><span>این کد و شماره تماس ثبت‌شده را نزد خود نگه دارید. برای مشاهدهٔ نتیجهٔ بررسی به هر دو نیاز دارید.</span></div>
                    <div class="smv-button-row smv-justify-center"><a class="smv-button smv-button-primary" href="<?php echo esc_url(self::tracking_url()); ?>">پیگیری وضعیت ثبت‌نام <?php echo smv_icon('arrow'); ?></a><button type="button" class="smv-button smv-button-light" onclick="window.print()">🖨 چاپ برگهٔ ثبت‌نام</button></div>

                    <!-- برگهٔ کادربندی‌شدهٔ مناسب چاپ (فقط هنگام چاپ دیده می‌شود) -->
                    <div class="smv-print-ticket" aria-hidden="true">
                        <div class="smv-print-frame">
                            <div class="smv-print-head">
                                <img src="<?php echo esc_url(SMV_URL . 'assets/images/samavieh-logo.png'); ?>" alt="" />
                                <div><strong>گروه فرهنگی هنری سماویه</strong><span>برگهٔ ثبت‌نام تئاتر</span></div>
                            </div>
                            <h3 class="smv-print-theatre"><?php echo esc_html(!empty($state['theatre']) ? $state['theatre'] : ''); ?></h3>
                            <table class="smv-print-table">
                                <?php if (!empty($state['name'])) : ?><tr><th>نام ثبت‌نام‌کننده</th><td><?php echo esc_html($state['name']); ?></td></tr><?php endif; ?>
                                <?php if (!empty($state['showtime'])) : ?><tr><th>سانس</th><td><?php echo esc_html($state['showtime']); ?></td></tr><?php endif; ?>
                                <?php if (!empty($state['payment_type'])) : ?><tr><th>روش پرداخت</th><td><?php echo esc_html($state['payment_type']); ?></td></tr><?php endif; ?>
                                <?php if (isset($state['people_count'])) : ?><tr><th>تعداد افراد</th><td><?php echo esc_html(SMV_Persian::digits($state['people_count'])); ?> نفر<?php echo !empty($state['companions']) ? ' (' . esc_html(smv_companions_label($state['companions'])) . ')' : ' (بدون همراه)'; ?></td></tr><?php endif; ?>
                                <?php if (!empty($state['amount'])) : ?><tr><th>مبلغ</th><td><?php echo esc_html(SMV_Persian::money($state['amount'])); ?></td></tr><?php endif; ?>
                                <?php if (!empty($state['phone'])) : ?><tr><th>شماره تماس</th><td dir="ltr"><?php echo esc_html($state['phone']); ?></td></tr><?php endif; ?>
                            </table>
                            <div class="smv-print-code"><small>کد پیگیری</small><code dir="ltr"><?php echo esc_html($state['tracking_code']); ?></code></div>
                            <p class="smv-print-note">این برگه را چاپ کنید و هنگام مراجعه همراه داشته باشید. برای پیگیری وضعیت ثبت‌نام، کد پیگیری و شماره تماس لازم است.</p>
                            <div class="smv-print-foot">سماویه — هنر، اندیشه و معنویت</div>
                        </div>
                    </div>
                </div>
            <?php elseif (!smv_theatre_registration_open($theatre_id)) : ?>
                <div class="smv-closed-box"><?php echo smv_icon('ticket'); ?><div><h2>ثبت‌نام این اجرا فعال نیست</h2><p>ثبت‌نام بسته شده یا ظرفیت تکمیل شده است. برای اطلاعات بیشتر می‌توانید با مدیریت سماویه تماس بگیرید.</p></div></div>
            <?php else : ?>
                <div class="smv-panel-heading"><div><span class="smv-kicker">رزرو حضور در اجرا</span><h2>فرم ثبت‌نام تئاتر</h2><p>اطلاعات را با دقت وارد کنید. موارد ستاره‌دار الزامی هستند.</p></div><span class="smv-form-step">۱ از ۱</span></div>
                <?php if ($state && empty($state['success'])) : ?><div class="smv-form-message is-error" role="alert"><?php echo smv_icon('alert'); ?><span><?php echo esc_html($state['message']); ?></span></div><?php endif; ?>

                <form class="smv-form smv-registration-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-smv-form>
                    <input type="hidden" name="action" value="smv_register" />
                    <input type="hidden" name="theatre_id" value="<?php echo esc_attr($theatre_id); ?>" />
                    <input type="hidden" name="smv_return_url" value="<?php echo esc_url(smv_get_return_url()); ?>" />
                    <?php wp_nonce_field('smv_register_' . $theatre_id, 'smv_register_nonce'); ?>

                    <?php $payment_instructions = smv_get_option('payment_instructions'); if ($payment_instructions) : ?>
                        <div class="smv-payment-guide"><strong>راهنمای واریز</strong><div><?php echo nl2br(esc_html($payment_instructions)); ?></div></div>
                    <?php endif; ?>

                    <div class="smv-honeypot" aria-hidden="true"><label>وب‌سایت<input type="text" name="smv_website" tabindex="-1" autocomplete="off" /></label></div>

                    <div class="smv-form-grid">
                        <div class="smv-field"><label for="smv-first-name-<?php echo esc_attr($theatre_id); ?>">نام <b>*</b></label><input id="smv-first-name-<?php echo esc_attr($theatre_id); ?>" type="text" name="first_name" value="<?php echo esc_attr(isset($old['first_name']) ? $old['first_name'] : ''); ?>" required maxlength="100" autocomplete="given-name" placeholder="نام خود را وارد کنید" /></div>
                        <div class="smv-field"><label for="smv-last-name-<?php echo esc_attr($theatre_id); ?>">نام خانوادگی <b>*</b></label><input id="smv-last-name-<?php echo esc_attr($theatre_id); ?>" type="text" name="last_name" value="<?php echo esc_attr(isset($old['last_name']) ? $old['last_name'] : ''); ?>" required maxlength="100" autocomplete="family-name" placeholder="نام خانوادگی خود را وارد کنید" /></div>
                        <div class="smv-field smv-field-full"><label for="smv-phone-<?php echo esc_attr($theatre_id); ?>">شماره تماس <b>*</b></label><input id="smv-phone-<?php echo esc_attr($theatre_id); ?>" type="tel" name="phone" value="<?php echo esc_attr(isset($old['phone']) ? $old['phone'] : ''); ?>" required inputmode="tel" dir="ltr" maxlength="16" autocomplete="tel" placeholder="09121234567" /><small>شمارهٔ موبایلی وارد کنید که برای پیگیری در دسترس شما باشد.</small></div>

                        <!-- Showtime selection (گروه‌بندی بر اساس روز، اولویت داخل هر روز) -->
                        <?php if (!empty($showtimes_by_day)) : ?>
                            <div class="smv-field smv-field-full">
                                <label for="smv-showtime-<?php echo esc_attr($theatre_id); ?>">انتخاب سانس <b>*</b></label>
                                <select name="showtime_id" id="smv-showtime-<?php echo esc_attr($theatre_id); ?>" required>
                                    <?php if (empty($active_ids_by_day)) : ?><option value="">— همهٔ سانس‌ها تکمیل ظرفیت هستند —</option><?php endif; ?>
                                    <?php $day_number = 0; $smv_default_selected_done = false; $smv_preferred_showtime = !empty($old['showtime_id']) ? absint($old['showtime_id']) : (isset($_GET['smv_reg_showtime']) ? absint(wp_unslash($_GET['smv_reg_showtime'])) : (isset($_GET['smv_showtime']) ? absint(wp_unslash($_GET['smv_showtime'])) : 0)); foreach ($showtimes_by_day as $day_date => $day_items) : $day_number++; ?>
                                        <optgroup label="روز <?php echo esc_attr(SMV_Persian::digits($day_number)); ?> — <?php echo esc_attr(SMV_Persian::date_with_weekday($day_date)); ?>">
                                        <?php foreach ($day_items as $st) : ?>
                                            <?php $is_default = false; if (!$smv_default_selected_done && 'active' === $st['state']) { $is_default = $smv_preferred_showtime ? ((int) $st['id'] === $smv_preferred_showtime) : true; } if ($is_default) { $smv_default_selected_done = true; } ?>
                                            <option value="<?php echo esc_attr($st['id']); ?>" data-price="<?php echo esc_attr($st['price']); ?>" data-state="<?php echo esc_attr($st['state']); ?>" data-remaining="<?php echo esc_attr(null === $st['remaining'] ? -1 : $st['remaining']); ?>" <?php echo 'active' === $st['state'] ? '' : 'disabled'; ?> <?php echo $is_default ? 'selected' : ''; ?>>
                                                اولویت <?php echo esc_html(SMV_Persian::digits($st['priority'])); ?> — <?php echo esc_html(SMV_Persian::date_with_weekday($st['date'])); ?><?php echo ($st['time'] || $st['time_end']) ? ' — ' . esc_html(SMV_Persian::time_range($st['time'], $st['time_end'])) : ''; ?>
                                                <?php if ('full' === $st['state']) : ?>
                                                    ( تکمیل ظرفیت )
                                                <?php elseif ('waiting' === $st['state']) : ?>
                                                    ( در انتظار تکمیل سانس قبلیِ همین روز )
                                                <?php elseif (null === $st['remaining']) : ?>
                                                    ( ظرفیت آزاد )
                                                <?php else : ?>
                                                    ( <?php echo esc_html(SMV_Persian::digits($st['remaining'])); ?> از <?php echo esc_html(SMV_Persian::digits($st['capacity'])); ?> صندلی خالی )
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <small><?php echo 'mixed' === $fill_mode ? 'حالت تکمیل هر روز جداست: بعضی روزها ترتیبی (فقط سانس فعالِ همان روز) و بعضی روزها آزاد و همزمان‌اند؛ سانس موردنظر را از فهرست انتخاب کنید.' : ('free' === $fill_mode ? 'تکمیل سانس‌ها آزاد و همزمان است؛ هر سانسی که ظرفیت دارد را می‌توانید انتخاب کنید.' : 'سانس‌ها روزبه‌روز و داخل هر روز به ترتیب اولویت پر می‌شوند؛ در هر روز فقط سانس فعال قابل انتخاب است و روزهای مختلف مستقل از هم هستند.'); ?></small>
                            </div>
                        <?php endif; ?>

                        <!-- Companions: همراه ندارم / یک نفر / دو نفر -->
                        <div class="smv-field smv-field-full">
                            <label for="smv-companions-<?php echo esc_attr($theatre_id); ?>">تعداد همراه <b>*</b></label>
                            <?php $old_companions = isset($old['companions']) ? max(0, min(2, absint($old['companions']))) : 0; ?>
                            <select name="companions" id="smv-companions-<?php echo esc_attr($theatre_id); ?>" required>
                                <option value="0" <?php selected($old_companions, 0); ?>>همراه ندارم — فقط خودم (۱ نفر)</option>
                                <option value="1" <?php selected($old_companions, 1); ?>>یک نفر همراه — مجموع ۲ نفر</option>
                                <option value="2" <?php selected($old_companions, 2); ?>>دو نفر همراه — مجموع ۳ نفر</option>
                            </select>
                            <small>هزینهٔ ثبت‌نام به‌ازای هر نفر است و با تعداد افراد بیشتر می‌شود؛ همراهان هم صندلی اشغال می‌کنند.</small>
                        </div>

                        <!-- Payment type selection: رایگان / هزینهٔ ثبت‌نام (مبلغ مشخص) / کمک مالی (مبلغ به انتخاب شخص) -->
                        <div class="smv-field smv-field-full smv-payment-type-field">
                            <label for="smv-payment-type">هزینه و روش پرداخت <b>*</b></label>
                            <select name="payment_type" id="smv-payment-type" required>
                                <option value="">-- روش را انتخاب کنید --</option>
                                <?php if (in_array('free', $payment_methods, true)) : ?>
                                    <option value="free">رایگان — بدون هزینه</option>
                                <?php endif; ?>
                                <?php if (in_array('paid', $payment_methods, true)) : ?>
                                    <option value="paid">هزینهٔ ثبت‌نام — مبلغ مشخص<?php echo $fixed_fee ? ' (' . esc_html(SMV_Persian::money($fixed_fee)) . ')' : ''; ?></option>
                                <?php endif; ?>
                                <?php if (in_array('financial_aid', $payment_methods, true)) : ?>
                                    <option value="financial_aid">کمک مالی — مبلغ را خودتان مشخص می‌کنید</option>
                                <?php endif; ?>
                            </select>
                            <small>سه روش: «رایگان» بدون هزینه است؛ «هزینهٔ ثبت‌نام» مبلغش از قبل مشخص شده؛ در «کمک مالی» مبلغ را خودتان تعیین می‌کنید.</small>
                        </div>

                        <!-- شمارهٔ کارت واریزی: درشت، پررنگ و جدا (از تنظیمات) -->
                        <?php $card_display = function_exists('smv_card_number_display') ? smv_card_number_display() : ''; if ($card_display) : $card_raw = smv_card_number_raw(); $card_holder = trim((string) smv_get_option('card_holder', '')); ?>
                        <div class="smv-field smv-field-full smv-card-number-field">
                            <div class="smv-card-number-box">
                                <span class="smv-card-number-label">💳 شمارهٔ کارت واریزی</span>
                                <strong class="smv-card-number-value" dir="ltr"><?php echo esc_html($card_display); ?></strong>
                                <?php if ($card_holder) : ?><span class="smv-card-number-holder">به نام: <b><?php echo esc_html($card_holder); ?></b></span><?php endif; ?>
                                <button type="button" class="smv-copy-button smv-card-copy" data-smv-copy="<?php echo esc_attr($card_raw); ?>"><?php echo smv_icon('copy'); ?> کپی شماره کارت</button>
                                <small>مبلغ را به این کارت واریز کنید و فیش آن را در بخش «فیش واریزی» بارگذاری کنید.</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Fixed fee notice (هزینهٔ ثبت‌نام با مبلغ مشخص) -->
                        <div class="smv-field smv-field-full smv-fixed-fee-box" style="display:none;">
                            <div class="smv-fixed-fee-note">هزینهٔ ثبت‌نام برای هر نفر: <strong data-smv-fixed-fee><?php echo $fixed_fee ? esc_html(SMV_Persian::money($fixed_fee)) : ''; ?></strong><br>مبلغ قابل پرداخت شما: <strong data-smv-fixed-fee-total><?php echo $fixed_fee ? esc_html(SMV_Persian::money($fixed_fee)) : ''; ?></strong> <span data-smv-people-note></span><small>مبلغ نهایی = هزینهٔ هر نفر × تعداد افراد (خودتان + همراهان) و بر اساس سانس انتخابی است؛ فیش واریز آن را در بخش «فیش واریزی» بارگذاری کنید.</small></div>
                        </div>

                        <!-- Amount for financial aid: مبلغ توسط خود شخص -->
                        <div class="smv-field smv-field-full smv-financial-aid-amount" style="display:none;">
                            <label>مبلغ کمک مالی (تومان) — به انتخاب شما <b>*</b></label>
                            <input type="number" name="financial_aid_amount" id="smv-financial-aid-amount" min="1000" step="1000" placeholder="مثلاً ۵۰۰۰۰" />
                            <small>هر مبلغی که مایلید به‌عنوان کمک مالی (برای همهٔ افراد: خودتان + همراهان) پرداخت کنید را خودتان وارد کنید و فیش آن را بارگذاری کنید.</small>
                        </div>

                        <!-- Optional note for financial aid -->
                        <div class="smv-field smv-field-full smv-financial-aid-reason" style="display:none;">
                            <label>توضیح (اختیاری)</label>
                            <textarea name="financial_aid_reason" id="smv-financial-aid-reason" rows="2" placeholder="اگر توضیحی دارید بنویسید (اختیاری)"></textarea>
                        </div>

                        <!-- Receipt upload -->
                        <div class="smv-field smv-field-full smv-receipt-field">
                            <label>فیش واریزی <span id="smv-receipt-required">*</span></label>
                            <label class="smv-file-input" for="smv-receipt-<?php echo esc_attr($theatre_id); ?>">
                                <span class="smv-file-icon"><?php echo smv_icon('upload'); ?></span>
                                <span><strong data-smv-file-label>تصویر یا PDF فیش را انتخاب کنید</strong><small>JPG، PNG، WebP یا PDF — حداکثر <?php echo esc_html(SMV_Persian::digits(smv_get_option('receipt_max_size', 5))); ?> مگابایت</small></span>
                                <b>انتخاب فایل</b>
                            </label>
                            <input class="smv-native-file" id="smv-receipt-<?php echo esc_attr($theatre_id); ?>" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" data-smv-file />
                            <small id="smv-receipt-note">برای روش «رایگان» آپلود فیش الزامی نیست.</small>
                        </div>
                    </div>

                    <div class="smv-terms-box">
                        <h3 class="smv-terms-title">📜 قوانین ثبت‌نام</h3>
                        <div class="smv-terms-text"><?php echo nl2br(esc_html(smv_registration_terms_text())); ?></div>
                        <label class="smv-consent smv-terms-consent"><input type="checkbox" name="smv_terms_accepted" value="1" required /><span>قوانین را خواندم و قبول می‌کنم <b>*</b></span></label>
                    </div>

                    <div class="smv-submit-row"><button type="submit" class="smv-button smv-button-primary" data-smv-submit>ارسال ثبت‌نام <?php echo smv_icon('arrow'); ?></button><span><?php echo smv_icon('check'); ?>اطلاعات شما فقط برای بررسی ثبت‌نام استفاده می‌شود.</span></div>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function(){
                    var paymentSelect = document.getElementById('smv-payment-type');
                    var amountField = document.querySelector('.smv-financial-aid-amount');
                    var reasonField = document.querySelector('.smv-financial-aid-reason');
                    var receiptField = document.querySelector('.smv-receipt-field');
                    var receiptInput = receiptField ? receiptField.querySelector('input[type="file"]') : null;
                    var receiptNote = document.getElementById('smv-receipt-note');
                    var receiptRequired = document.getElementById('smv-receipt-required');

                    function toggleFields() {
                        var val = paymentSelect ? paymentSelect.value : '';
                        var feeBox = document.querySelector('.smv-fixed-fee-box');
                        if (amountField) amountField.style.display = (val === 'financial_aid') ? 'block' : 'none';
                        if (reasonField) reasonField.style.display = (val === 'financial_aid') ? 'block' : 'none';
                        if (feeBox) feeBox.style.display = (val === 'paid') ? 'block' : 'none';
                        var cardBox = document.querySelector('.smv-card-number-field');
                        if (cardBox) cardBox.style.display = (val === 'free') ? 'none' : 'block';
                        if (val === 'free') {
                            if (receiptField) receiptField.style.display = 'none';
                            if (receiptInput) receiptInput.removeAttribute('required');
                            if (receiptNote) receiptNote.textContent = 'رایگان است — فیش لازم نیست.';
                            if (receiptRequired) receiptRequired.textContent = '';
                        } else if (val === 'financial_aid') {
                            if (receiptField) receiptField.style.display = 'block';
                            if (receiptInput) receiptInput.setAttribute('required', 'required');
                            if (receiptNote) receiptNote.textContent = 'لطفاً فیش یا سند کمک مالی را آپلود کنید.';
                            if (receiptRequired) receiptRequired.textContent = '*';
                        } else if (val === 'paid') {
                            if (receiptField) receiptField.style.display = 'block';
                            if (receiptInput) receiptInput.setAttribute('required', 'required');
                            if (receiptNote) receiptNote.textContent = 'پس از پرداخت آنلاین، فیش پرداخت را آپلود کنید.';
                            if (receiptRequired) receiptRequired.textContent = '*';
                        }
                    }

                    if (paymentSelect) {
                        paymentSelect.addEventListener('change', toggleFields);
                    }
                    // اگر از دکمهٔ «ثبت‌نام در این سانس» آمده باشد، همان سانس خودکار انتخاب می‌شود.
                    try {
                        var requestedShowtime = new URLSearchParams(window.location.search).get('smv_reg_showtime') || new URLSearchParams(window.location.search).get('smv_showtime');
                        var stSelect = document.getElementById('smv-showtime-<?php echo esc_js($theatre_id); ?>');
                        if (requestedShowtime && stSelect) {
                            for (var oi = 0; oi < stSelect.options.length; oi++) {
                                if (stSelect.options[oi].value === requestedShowtime && !stSelect.options[oi].disabled) {
                                    stSelect.value = requestedShowtime;
                                    stSelect.dispatchEvent(new Event('change'));
                                    break;
                                }
                            }
                        }
                    } catch (e) {}
                    var showtimeSelect = document.getElementById('smv-showtime-<?php echo esc_js($theatre_id); ?>');
                    var companionsSelect = document.getElementById('smv-companions-<?php echo esc_js($theatre_id); ?>');
                    function syncFee() {
                        var feeEl = document.querySelector('[data-smv-fixed-fee]');
                        var totalEl = document.querySelector('[data-smv-fixed-fee-total]');
                        var peopleNote = document.querySelector('[data-smv-people-note]');
                        var price = <?php echo esc_js($fixed_fee ? (int) $fixed_fee : 0); ?>;
                        if (showtimeSelect) {
                            var opt = showtimeSelect.options[showtimeSelect.selectedIndex];
                            var optPrice = opt ? parseInt(opt.getAttribute('data-price') || '0', 10) : 0;
                            if (optPrice > 0) { price = optPrice; }
                        }
                        var people = companionsSelect ? (parseInt(companionsSelect.value || '0', 10) + 1) : 1;
                        if (feeEl && price > 0) { feeEl.textContent = price.toLocaleString('fa-IR') + ' تومان'; }
                        if (totalEl) { totalEl.textContent = (price * people).toLocaleString('fa-IR') + ' تومان'; }
                        if (peopleNote) { peopleNote.textContent = 'برای ' + people.toLocaleString('fa-IR') + ' نفر'; }
                    }
                    if (showtimeSelect) { showtimeSelect.addEventListener('change', syncFee); }
                    if (companionsSelect) { companionsSelect.addEventListener('change', syncFee); }
                    syncFee();
                    toggleFields();
                });
                </script>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function tracking() {
        SMV_Plugin::enqueue_frontend();
        $state = SMV_Forms::tracking_state();
        $old = $state && !empty($state['old']) ? $state['old'] : array();
        ob_start();
        ?>
        <section class="smv-tracking smv-panel" id="smv-tracking">
            <div class="smv-centered-heading"><span class="smv-tracking-symbol"><?php echo smv_icon('search'); ?></span><span class="smv-kicker">وضعیت درخواست</span><h2>پیگیری ثبت‌نام</h2><p>کد پیگیری و همان شماره تماسی که هنگام ثبت‌نام وارد کرده‌اید را بنویسید.</p></div>
            <?php if ($state && empty($state['success'])) : ?><div class="smv-form-message is-error" role="alert"><?php echo smv_icon('alert'); ?><span><?php echo esc_html($state['message']); ?></span></div><?php endif; ?>
            <?php if ($state && !empty($state['success']) && !empty($state['message'])) : ?><div class="smv-form-message is-success" role="status"><?php echo smv_icon('check'); ?><span><?php echo esc_html($state['message']); ?></span></div><?php endif; ?>
            <?php if ($state && !empty($state['success']) && !empty($state['result'])) : $result = $state['result']; ?>
                <div class="smv-tracking-result">
                    <div class="smv-result-top"><div><small>وضعیت فعلی ثبت‌نام</small><h3><?php echo esc_html($result['theatre']); ?></h3></div><span class="smv-result-status status-<?php echo esc_attr($result['status']); ?>"><?php echo esc_html($result['status_label']); ?></span></div>
                    <?php if ('approved' === $result['status']) : ?><div class="smv-result-message is-approved"><?php echo smv_icon('check'); ?><div><strong>ثبت‌نام شما تأیید شد</strong><p>فیش واریزی بررسی و ثبت‌نام شما با موفقیت نهایی شده است.</p></div></div>
                    <?php elseif ('rejected' === $result['status']) : ?><div class="smv-result-message is-rejected"><?php echo smv_icon('alert'); ?><div><strong>ثبت‌نام نیاز به بررسی مجدد دارد</strong><p>توضیح مدیریت را بخوانید و در صورت نیاز با سماویه تماس بگیرید.</p></div></div>
                    <?php else : ?><div class="smv-result-message is-pending"><?php echo smv_icon('clock'); ?><div><strong>درخواست شما در نوبت بررسی است</strong><p>به‌محض بررسی فیش، وضعیت در همین صفحه به‌روزرسانی می‌شود.</p></div></div><?php endif; ?>
                    <dl class="smv-result-details"><div><dt>نام ثبت‌نام‌کننده</dt><dd><?php echo esc_html($result['name']); ?></dd></div><div><dt>کد پیگیری</dt><dd dir="ltr"><?php echo esc_html($result['tracking_code']); ?></dd></div><?php if (!empty($result['showtime'])) : ?><div><dt>سانس</dt><dd><?php echo esc_html($result['showtime']); ?></dd></div><?php endif; ?><div><dt>روش پرداخت</dt><dd><?php echo esc_html($result['payment_type_label'] ?: 'نامشخص'); ?></dd></div><div><dt>مبلغ</dt><dd><?php echo esc_html($result['amount'] ? SMV_Persian::money($result['amount']) : 'رایگان'); ?></dd></div><?php if (!empty($result['people_count'])) : ?><div><dt>تعداد افراد</dt><dd><?php echo esc_html(SMV_Persian::digits($result['people_count'])); ?> نفر<?php echo !empty($result['companions']) ? ' (' . esc_html(smv_companions_label($result['companions'])) . ')' : ''; ?></dd></div><?php endif; ?><div><dt>تاریخ ثبت درخواست</dt><dd><?php echo esc_html($result['created_at']); ?></dd></div><div><dt>آخرین به‌روزرسانی</dt><dd><?php echo esc_html($result['updated_at']); ?></dd></div></dl>
                    <?php if ($result['admin_note']) : ?><div class="smv-admin-note"><strong>توضیح مدیریت:</strong><p><?php echo nl2br(esc_html($result['admin_note'])); ?></p></div><?php endif; ?>
                </div>
            <?php endif; ?>
            <form class="smv-form smv-tracking-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-smv-form>
                <input type="hidden" name="action" value="smv_track" /><input type="hidden" name="smv_return_url" value="<?php echo esc_url(smv_get_return_url()); ?>" />
                <?php wp_nonce_field('smv_track', 'smv_track_nonce'); ?>
                <div class="smv-form-grid"><div class="smv-field"><label for="smv-tracking-code">کد پیگیری <b>*</b></label><input id="smv-tracking-code" type="text" name="tracking_code" value="<?php echo esc_attr(isset($old['tracking_code']) ? $old['tracking_code'] : ''); ?>" required dir="ltr" maxlength="20" autocomplete="off" inputmode="numeric" placeholder="مثلاً ۴۲۱۷" data-smv-uppercase /><small>کد پیگیری ۴ رقمی که پس از ثبت‌نام دریافت کرده‌اید.</small></div><div class="smv-field"><label for="smv-tracking-phone">شماره تماس <b>*</b></label><input id="smv-tracking-phone" type="tel" name="phone" value="<?php echo esc_attr(isset($old['phone']) ? $old['phone'] : ''); ?>" required dir="ltr" inputmode="tel" maxlength="16" autocomplete="tel" placeholder="09121234567" /></div></div>
                <button type="submit" class="smv-button smv-button-primary smv-button-block" data-smv-submit>نمایش وضعیت <?php echo smv_icon('search'); ?></button>
            </form>
            <p class="smv-privacy-note"><?php echo smv_icon('check'); ?>برای حفظ حریم خصوصی، نتیجه فقط با تطابق کد پیگیری و شماره تماس نمایش داده می‌شود.</p>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function contact() {
        SMV_Plugin::enqueue_frontend();
        $phone = smv_get_option('phone');
        $email = smv_get_option('email');
        $address = smv_get_option('address');
        ob_start();
        ?>
        <div class="smv-contact-wrap">
            <?php if ($phone || $email || $address) : ?>
                <div class="smv-contact-grid">
                    <?php if ($phone) : ?><a class="smv-contact-card" href="tel:<?php echo esc_attr(SMV_Persian::normalize_phone($phone)); ?>"><span><?php echo smv_icon('phone'); ?></span><div><small>تلفن تماس</small><strong dir="ltr"><?php echo esc_html($phone); ?></strong><p>برای هماهنگی و پاسخ به پرسش‌ها</p></div></a><?php endif; ?>
                    <?php if ($email) : ?><a class="smv-contact-card" href="mailto:<?php echo esc_attr($email); ?>"><span><?php echo smv_icon('mail'); ?></span><div><small>ایمیل</small><strong dir="ltr"><?php echo esc_html($email); ?></strong><p>ارسال درخواست و پیشنهاد همکاری</p></div></a><?php endif; ?>
                    <?php if ($address) : ?><div class="smv-contact-card"><span><?php echo smv_icon('pin'); ?></span><div><small>نشانی</small><strong><?php echo esc_html($address); ?></strong><p>لطفاً پیش از مراجعه هماهنگ کنید.</p></div></div><?php endif; ?>
                </div>
            <?php else : ?>
                <div class="smv-empty"><?php echo smv_icon('phone'); ?><h3>راه‌های ارتباطی به‌زودی تکمیل می‌شود</h3><p>اطلاعات تماس گروه فرهنگی هنری سماویه در این بخش قرار می‌گیرد.</p><?php if (current_user_can('manage_smv_registrations')) : ?><a class="smv-button smv-button-primary" href="<?php echo esc_url(admin_url('admin.php?page=smv-settings')); ?>">وارد کردن اطلاعات تماس</a><?php endif; ?></div>
            <?php endif; ?>
            <div class="smv-social-row">
                <?php foreach (array('instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'whatsapp' => 'واتس‌اپ') as $key => $label) : $url = smv_get_option($key); if ($url) : ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?> <?php echo smv_icon('arrow'); ?></a><?php endif; endforeach; ?>
            </div>
            <div class="smv-contact-help"><div><span class="smv-kicker">ثبت‌نام کرده‌اید؟</span><h3>برای وضعیت ثبت‌نام، تماس لازم نیست</h3><p>با کد پیگیری و شماره تماس، آخرین وضعیت را آنلاین ببینید.</p></div><a class="smv-button smv-button-primary" href="<?php echo esc_url(self::tracking_url()); ?>">پیگیری ثبت‌نام</a></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
