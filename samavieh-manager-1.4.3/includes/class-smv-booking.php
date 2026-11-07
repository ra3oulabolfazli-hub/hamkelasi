<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * نمایش سانس‌ها و ظرفیت باقی‌مانده
 * (ثبت نوع نوشتهٔ «سانس» در class-smv-cpt.php انجام می‌شود و اینجا تکرار نمی‌شود)
 */
class SMV_Booking {

    public static function init() {
        add_shortcode('samavieh_showtimes', array(__CLASS__, 'showtimes_shortcode'));
        add_shortcode('samavieh_seat_map', array(__CLASS__, 'seat_map_shortcode'));
    }

    public static function showtimes_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'theatre_id' => 0,
            'count' => -1,
            'columns' => 3,
        ), $atts, 'samavieh_showtimes');

        $theatre_id = absint($atts['theatre_id']);
        $args = array(
            'post_type' => 'smv_showtime',
            'post_status' => 'publish',
            'posts_per_page' => max(-1, (int) $atts['count']),
            'orderby' => 'meta_value',
            'meta_key' => '_smv_showtime_date',
            'order' => 'ASC',
            'no_found_rows' => true,
        );
        if ($theatre_id) {
            $args['meta_query'] = array(array(
                'key' => '_smv_theatre_id',
                'value' => $theatre_id,
                'compare' => '=',
            ));
        }

        $query = new WP_Query($args);
        $columns = min(4, max(1, absint($atts['columns'])));
        $registration_target = $theatre_id ? get_permalink($theatre_id) : '';
        ob_start();
        if ($query->have_posts()) :
            echo '<div class="smv-showtime-grid smv-columns-' . esc_attr($columns) . '">';
            while ($query->have_posts()) : $query->the_post();
                $id = get_the_ID();
                $date = get_post_meta($id, '_smv_showtime_date', true);
                $time = get_post_meta($id, '_smv_showtime_time', true);
                $time_end = get_post_meta($id, '_smv_showtime_time_end', true);
                $venue = get_post_meta($id, '_smv_showtime_venue', true);
                $capacity = absint(get_post_meta($id, '_smv_capacity', true));
                $price = get_post_meta($id, '_smv_showtime_price', true);
                $remaining = smv_showtime_seats_remaining($id);
                $booked = smv_showtime_booked_count($id);
                $percent = ($capacity > 0) ? min(100, round(($booked / $capacity) * 100)) : 0;
                $is_full = (null !== $remaining && $remaining <= 0);
                ?>
                <article class="smv-showtime-card<?php echo $is_full ? ' is-full' : ''; ?>">
                    <div class="smv-showtime-header">
                        <span class="smv-showtime-date"><?php echo esc_html(SMV_Persian::date($date)); ?></span>
                        <span class="smv-showtime-time"><?php echo esc_html(SMV_Persian::time_range($time, $time_end)); ?></span>
                        <?php if ($is_full) : ?><span class="smv-showtime-full">تکمیل</span><?php endif; ?>
                    </div>
                    <div class="smv-showtime-body">
                        <h4><?php the_title(); ?></h4>
                        <?php if ($venue) : ?><p class="smv-showtime-venue"><?php echo esc_html($venue); ?></p><?php endif; ?>
                        <div class="smv-showtime-capacity">
                            <?php if (null === $remaining) : ?>
                                <span>ظرفیت: بدون محدودیت</span>
                            <?php else : ?>
                                <span>ظرفیت باقی‌مانده: <?php echo esc_html(SMV_Persian::digits($remaining)); ?> از <?php echo esc_html(SMV_Persian::digits($capacity)); ?></span>
                                <div class="smv-progress-bar"><div class="smv-progress-fill" style="width:<?php echo esc_attr($percent); ?>%"></div></div>
                                <span class="smv-showtime-percent"><?php echo esc_html(SMV_Persian::digits($percent)); ?>٪ پر شده</span>
                            <?php endif; ?>
                        </div>
                        <?php if ('' !== $price && null !== $price) : ?><p class="smv-showtime-price"><?php echo esc_html(SMV_Persian::money($price)); ?></p><?php endif; ?>
                    </div>
                    <div class="smv-showtime-footer">
                        <?php if ($is_full) : ?>
                            <button type="button" class="smv-button smv-button-ghost" disabled>ظرفیت تکمیل است</button>
                        <?php elseif ($registration_target) : ?>
                            <a href="<?php echo esc_url($registration_target . '#smv-registration'); ?>" class="smv-button smv-button-primary">ثبت‌نام در این سانس</a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php
            endwhile;
            echo '</div>';
        else :
            echo '<div class="smv-empty">' . smv_icon('calendar') . '<h3>سانسی برای این اجرا برنامه‌ریزی نشده است</h3><p>به‌زودی سانس‌های جدید اضافه می‌شود.</p></div>';
        endif;
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * نقشهٔ نمایشی صندلی‌ها (صندلی‌های رزروشده بر اساس متای قدیمی).
     * رزرو واقعی از طریق فرم ثبت‌نام انجام می‌شود.
     */
    public static function seat_map_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('showtime_id' => 0), $atts, 'samavieh_seat_map');
        $id = absint($atts['showtime_id']);
        if (!$id) {
            return '<div class="smv-notice">سانس مشخص نشده است.</div>';
        }
        $capacity = absint(get_post_meta($id, '_smv_capacity', true));
        if (!$capacity) {
            return '<div class="smv-notice">برای این سانس ظرفیتی تعریف نشده است.</div>';
        }
        $booked = maybe_unserialize(get_post_meta($id, '_smv_booked_seats', true));
        if (!is_array($booked)) {
            $booked = array();
        }
        ob_start();
        ?>
        <div class="smv-seat-map" id="smv-seat-map-<?php echo esc_attr($id); ?>">
            <div class="smv-seat-legend">
                <span class="smv-seat-status free"></span> خالی
                <span class="smv-seat-status booked"></span> رزرو شده
            </div>
            <div class="smv-seat-grid">
                <?php for ($i = 1; $i <= $capacity; $i++) : $is_booked = in_array($i, array_map('absint', $booked), true); ?>
                    <span class="smv-seat<?php echo $is_booked ? ' booked' : ''; ?>" aria-label="صندلی <?php echo esc_attr($i); ?>"><?php echo esc_html(SMV_Persian::digits($i)); ?></span>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
