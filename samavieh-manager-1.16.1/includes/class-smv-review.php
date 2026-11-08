<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * سیستم نظرات مخاطبان (حداکثر ۲ نظر تأییدشده برای هر اجرا)
 *
 * نظرات در جدول اختصاصی افزونه ذخیره می‌شوند و همان‌جا (نه به‌صورت نوشتهٔ وردپرسی)
 * خوانده می‌شوند تا نظرات ثبت‌شده از فرم سایت، در پنل «نظرات» و صفحهٔ اجرا یکسان دیده شوند.
 */
class SMV_Review {

    public static function init() {
        add_shortcode('samavieh_reviews', array(__CLASS__, 'reviews_shortcode'));
        add_shortcode('samavieh_review_form', array(__CLASS__, 'review_form_shortcode'));
        add_action('wp_ajax_smv_submit_review', array(__CLASS__, 'submit_ajax'));
        add_action('wp_ajax_nopriv_smv_submit_review', array(__CLASS__, 'submit_ajax'));
    }

    public static function reviews_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array(
            'theatre_id' => 0,
            'count' => 2,
            'form' => 1,
            'approved_only' => 1,
        ), $atts, 'samavieh_reviews');

        $theatre_id = $atts['theatre_id'] ? absint($atts['theatre_id']) : get_the_ID();
        if (!$theatre_id || 'smv_theatre' !== get_post_type($theatre_id)) {
            return '';
        }

        $reviews = smv_get_reviews_for_theatre($theatre_id, !empty($atts['approved_only']), absint($atts['count']));
        $all_approved = smv_get_reviews_for_theatre($theatre_id, true, 99);
        $approved_count = count($all_approved);
        $average = 0;
        if ($approved_count) {
            $sum = 0;
            foreach ($all_approved as $item) { $sum += absint($item->rating); }
            $average = $sum / $approved_count;
        }

        ob_start();
        ?>
        <div class="smv-reviews">
            <div class="smv-reviews-summary">
                <div class="smv-reviews-score"><strong><?php echo esc_html($approved_count ? SMV_Persian::digits(number_format($average, 1)) : '—'); ?></strong><span class="smv-review-rating smv-stars-animated"><?php echo self::stars((int) round($average)); ?></span><small><?php echo $approved_count ? esc_html(SMV_Persian::digits($approved_count)) . ' نظر تأییدشده' : 'هنوز نظری ثبت نشده است'; ?></small></div>
                <p>تجربهٔ تماشای این اجرا را با دیگران به اشتراک بگذارید؛ نظر شما پس از تأیید مدیریت همین‌جا نمایش داده می‌شود.</p>
            </div>
            <?php if ($reviews) : ?>
                <div class="smv-reviews-grid">
                    <?php foreach ($reviews as $review) : $initial = function_exists('mb_substr') ? mb_substr($review->author, 0, 1, 'UTF-8') : substr($review->author, 0, 1); ?>
                        <article class="smv-review-card">
                            <div class="smv-review-header">
                                <span class="smv-review-avatar" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                                <span class="smv-review-meta"><span class="smv-review-author"><?php echo esc_html($review->author); ?></span><span class="smv-review-date"><?php echo esc_html(SMV_Persian::date($review->created_at)); ?></span></span>
                                <span class="smv-review-rating smv-stars-animated"><?php echo self::stars(absint($review->rating)); ?></span>
                            </div>
                            <div class="smv-review-body"><?php echo nl2br(esc_html($review->comment)); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="smv-reviews-empty"><span class="smv-reviews-empty-icon">💬</span><h4>اولین نفر باشید که نظر می‌دهد</h4><p>هنوز نظری برای این اجرا ثبت نشده است؛ تجربهٔ خودتان را بنویسید.</p></div>
            <?php endif; ?>

            <?php if (!empty($atts['form'])) : ?>
                <?php if ($approved_count >= 2) : ?>
                    <p class="smv-review-limit"><?php echo smv_icon('check'); ?> دو نظر تأییدشده برای این اجرا ثبت شده است. با تشکر از همراهی شما.</p>
                <?php else : ?>
                    <?php echo self::review_form($theatre_id); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function review_form_shortcode($atts) {
        $atts = shortcode_atts(array('theatre_id' => 0), $atts, 'samavieh_review_form');
        $theatre_id = $atts['theatre_id'] ? absint($atts['theatre_id']) : get_the_ID();
        if (!$theatre_id || 'smv_theatre' !== get_post_type($theatre_id)) {
            return '';
        }
        return self::review_form($theatre_id);
    }

    private static function review_form($theatre_id) {
        $form_id = 'smv-review-form-' . absint($theatre_id);
        ob_start();
        ?>
        <form class="smv-form smv-review-form" id="<?php echo esc_attr($form_id); ?>" data-smv-review-form data-theatre="<?php echo esc_attr($theatre_id); ?>">
            <h3 class="smv-review-form-title">نظر خود را بنویسید</h3>
            <div class="smv-form-grid">
                <div class="smv-field">
                    <label for="<?php echo esc_attr($form_id); ?>-author">نام شما <b>*</b></label>
                    <input type="text" id="<?php echo esc_attr($form_id); ?>-author" name="author" required maxlength="100" autocomplete="name" />
                </div>
                <div class="smv-field">
                    <label id="<?php echo esc_attr($form_id); ?>-rating-label">امتیاز شما <b>*</b></label>
                    <div class="smv-star-rating" data-smv-star-rating role="radiogroup" aria-labelledby="<?php echo esc_attr($form_id); ?>-rating-label">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <button type="button" class="smv-star-btn" data-value="<?php echo esc_attr($i); ?>" role="radio" aria-checked="false" aria-label="<?php echo esc_attr(SMV_Persian::digits($i)); ?> ستاره">★</button>
                        <?php endfor; ?>
                        <input type="hidden" id="<?php echo esc_attr($form_id); ?>-rating" name="rating" value="" data-smv-rating-input />
                    </div>
                    <small class="smv-star-rating-text" data-smv-rating-text>برای امتیاز دادن، روی ستاره‌ها بزنید.</small>
                </div>
                <div class="smv-field smv-field-full">
                    <label for="<?php echo esc_attr($form_id); ?>-comment">متن نظر <b>*</b></label>
                    <textarea id="<?php echo esc_attr($form_id); ?>-comment" name="comment" rows="3" required maxlength="1000"></textarea>
                </div>
            </div>
            <div class="smv-honeypot" aria-hidden="true"><label>وب‌سایت<input type="text" name="smv_review_website" tabindex="-1" autocomplete="off" /></label></div>
            <div class="smv-submit-row">
                <button type="submit" class="smv-button smv-button-primary" data-smv-submit>ثبت نظر <?php echo smv_icon('arrow'); ?></button>
                <span><?php echo smv_icon('check'); ?>نظر شما پس از تأیید مدیریت نمایش داده می‌شود.</span>
            </div>
            <div class="smv-form-message" style="display:none;" role="status"></div>
        </form>
        <script>
        (function(){
            var form = document.getElementById('<?php echo esc_js($form_id); ?>');
            if (!form || form.dataset.smvBound) { return; }
            form.dataset.smvBound = '1';
            form.addEventListener('submit', function(event){
                event.preventDefault();
                var message = form.querySelector('.smv-form-message');
                var button = form.querySelector('[data-smv-submit]');
                var data = new FormData(form);
                data.append('action', 'smv_submit_review');
                data.append('theatre_id', form.getAttribute('data-theatre'));
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('smv_review_nonce')); ?>');
                if (button) { button.disabled = true; }
                fetch((window.smvFrontend && smvFrontend.ajaxUrl) || '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', credentials: 'same-origin', body: data })
                    .then(function(response){ return response.json(); })
                    .then(function(result){
                        if (!message) { return; }
                        message.style.display = 'block';
                        message.className = 'smv-form-message ' + (result.success ? 'is-success' : 'is-error');
                        message.textContent = (result.data && result.data.message) ? result.data.message : 'خطا در ارسال نظر.';
                        if (result.success) { form.reset(); form.querySelectorAll('.smv-star-btn').forEach(function(b){ b.classList.remove('is-filled','is-hover'); b.setAttribute('aria-checked','false'); }); var rt = form.querySelector('[data-smv-rating-text]'); if (rt) { rt.textContent = 'برای امتیاز دادن، روی ستاره‌ها بزنید.'; } }
                        if (button) { button.disabled = false; }
                    })
                    .catch(function(){
                        if (message) {
                            message.style.display = 'block';
                            message.className = 'smv-form-message is-error';
                            message.textContent = 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.';
                        }
                        if (button) { button.disabled = false; }
                    });
            });
        }());
        </script>
        <?php
        return ob_get_clean();
    }

    private static function stars($rating) {
        $rating = max(0, min(5, absint($rating)));
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<span class="smv-star' . ($i <= $rating ? ' filled' : '') . '" style="--smv-star-delay:' . esc_attr(($i - 1) * 90) . 'ms">★</span>';
        }
        return $html;
    }

    public static function submit_ajax() {
        check_ajax_referer('smv_review_nonce', 'nonce');

        $theatre_id = isset($_POST['theatre_id']) ? absint($_POST['theatre_id']) : 0;
        $author = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;

        if (!empty($_POST['smv_review_website'])) {
            wp_send_json_error(array('message' => 'ارسال نظر انجام نشد.'));
        }
        if (!$theatre_id || 'smv_theatre' !== get_post_type($theatre_id)) {
            wp_send_json_error(array('message' => 'اجرای انتخاب‌شده معتبر نیست.'));
        }
        if (!$author || !$comment || $rating < 1 || $rating > 5) {
            wp_send_json_error(array('message' => 'تمام موارد را به درستی پر کنید.'));
        }

        $rate_key = 'smv_review_rate_' . md5(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown');
        if (get_transient($rate_key)) {
            wp_send_json_error(array('message' => 'کمی پیش نظری ثبت کرده‌اید. لطفاً یک دقیقه بعد دوباره تلاش کنید.'));
        }

        global $wpdb;
        $table = smv_reviews_table_name();
        $approved_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE theatre_id = %d AND status = 'approved'", $theatre_id));
        if ($approved_count >= 2) {
            wp_send_json_error(array('message' => 'حداکثر دو نظر برای این اجرا ثبت شده است.'));
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert($table, array(
            'theatre_id' => $theatre_id,
            'author' => $author,
            'comment' => $comment,
            'rating' => max(1, min(5, $rating)),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%d', '%s', '%s', '%d', '%s', '%s', '%s'));

        if (!$inserted) {
            wp_send_json_error(array('message' => 'ذخیرهٔ نظر انجام نشد. لطفاً دوباره تلاش کنید.'));
        }

        set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        wp_send_json_success(array('message' => 'نظر شما ثبت شد و پس از تأیید مدیریت نمایش داده می‌شود.'));
    }
}
