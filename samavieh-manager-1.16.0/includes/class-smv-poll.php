<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * سیستم نظرسنجی (Poll / Survey)
 */
class SMV_Poll {

    public static function init() {
        add_shortcode('samavieh_poll', array(__CLASS__, 'poll_shortcode'));
        add_action('wp_ajax_smv_vote_poll', array(__CLASS__, 'vote_ajax'));
        add_action('wp_ajax_nopriv_smv_vote_poll', array(__CLASS__, 'vote_ajax'));
    }

    public static function poll_shortcode($atts) {
        SMV_Plugin::enqueue_frontend();
        $atts = shortcode_atts(array('id' => 0), $atts, 'samavieh_poll');
        $id = absint($atts['id']);
        if (!$id) {
            $id = smv_get_active_poll_id();
        }
        if (!$id || 'smv_poll' !== get_post_type($id) || 'publish' !== get_post_status($id)) {
            return '';
        }

        $title = get_post_meta($id, '_smv_poll_title', true);
        if (!$title) {
            $title = get_the_title($id);
        }
        $description = get_post_meta($id, '_smv_poll_description', true);
        $end_date = get_post_meta($id, '_smv_poll_end_date', true);
        $status = get_post_meta($id, '_smv_poll_status', true);
        if (!$status) {
            $status = 'active';
        }
        $answers = maybe_unserialize(get_post_meta($id, '_smv_poll_answers', true));
        if (!is_array($answers) || !$answers) {
            return '';
        }

        if ($end_date && $end_date < current_time('Y-m-d')) {
            $status = 'closed';
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $has_voted = false;
        if ($ip) {
            $votes = get_option('smv_poll_votes_' . $id, array());
            $has_voted = is_array($votes) && in_array($ip, $votes, true);
        }

        $total_votes = 0;
        foreach ($answers as $answer) {
            $total_votes += absint(isset($answer['votes']) ? $answer['votes'] : 0);
        }

        ob_start();
        ?>
        <div class="smv-poll-panel" id="smv-poll-<?php echo esc_attr($id); ?>">
            <div class="smv-poll-header">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if ($description) : ?><p><?php echo esc_html($description); ?></p><?php endif; ?>
                <?php if ('active' !== $status) : ?><span class="smv-poll-closed">نظرسنجی بسته شده است</span><?php endif; ?>
            </div>

            <?php if ($has_voted || 'active' !== $status || !$answers) : ?>
                <div class="smv-poll-results">
                    <?php foreach ($answers as $answer) : $votes_count = absint(isset($answer['votes']) ? $answer['votes'] : 0); $percent = $total_votes ? round(($votes_count / $total_votes) * 100) : 0; ?>
                        <div class="smv-poll-result-item">
                            <span class="smv-poll-answer-text"><?php echo esc_html(isset($answer['text']) ? $answer['text'] : ''); ?></span>
                            <div class="smv-poll-progress"><div class="smv-poll-progress-fill" style="width:<?php echo esc_attr($percent); ?>%"></div></div>
                            <span class="smv-poll-vote-count"><?php echo esc_html(SMV_Persian::digits($votes_count)); ?> رای (<?php echo esc_html(SMV_Persian::digits($percent)); ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="smv-poll-total">کل رای‌ها: <?php echo esc_html(SMV_Persian::digits($total_votes)); ?></div>
                    <?php if ($has_voted && 'active' === $status) : ?><div class="smv-poll-thanks"><?php echo smv_icon('check'); ?> رای شما ثبت شده است.</div><?php endif; ?>
                </div>
            <?php else : ?>
                <form class="smv-poll-form" id="smv-poll-form-<?php echo esc_attr($id); ?>">
                    <?php foreach ($answers as $idx => $answer) : ?>
                        <label class="smv-poll-option">
                            <input type="radio" name="smv_poll_answer" value="<?php echo esc_attr($idx); ?>" required />
                            <span><?php echo esc_html(isset($answer['text']) ? $answer['text'] : ''); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="smv-button smv-button-primary smv-poll-submit">ارسال رای</button>
                </form>
                <div class="smv-poll-message" id="smv-poll-message-<?php echo esc_attr($id); ?>" style="display:none;"></div>
                <script>
                (function(){
                    var form = document.getElementById('smv-poll-form-<?php echo esc_js((string) $id); ?>');
                    if (!form || form.dataset.smvBound) { return; }
                    form.dataset.smvBound = '1';
                    form.addEventListener('submit', function(event){
                        event.preventDefault();
                        var answer = form.querySelector('input[name="smv_poll_answer"]:checked');
                        if (!answer) { return; }
                        var message = document.getElementById('smv-poll-message-<?php echo esc_js((string) $id); ?>');
                        var body = new FormData();
                        body.append('action', 'smv_vote_poll');
                        body.append('poll_id', '<?php echo esc_js((string) $id); ?>');
                        body.append('answer_index', answer.value);
                        body.append('nonce', '<?php echo esc_js(wp_create_nonce('smv_poll_nonce')); ?>');
                        fetch((window.smvFrontend && smvFrontend.ajaxUrl) || '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function(response){ return response.json(); })
                            .then(function(result){
                                if (message) {
                                    message.style.display = 'block';
                                    message.textContent = (result.data && result.data.message) ? result.data.message : 'خطا در ثبت رای';
                                }
                                if (result.success) { window.location.reload(); }
                            })
                            .catch(function(){
                                if (message) {
                                    message.style.display = 'block';
                                    message.textContent = 'ارتباط با سرور برقرار نشد.';
                                }
                            });
                    });
                }());
                </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function vote_ajax() {
        check_ajax_referer('smv_poll_nonce', 'nonce');
        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $answer_index = isset($_POST['answer_index']) ? absint($_POST['answer_index']) : -1;

        if (!$poll_id || 'smv_poll' !== get_post_type($poll_id)) {
            wp_send_json_error(array('message' => 'نظرسنجی معتبر نیست.'));
        }

        $status = get_post_meta($poll_id, '_smv_poll_status', true) ?: 'active';
        $end_date = get_post_meta($poll_id, '_smv_poll_end_date', true);
        if ('active' !== $status || ($end_date && $end_date < current_time('Y-m-d'))) {
            wp_send_json_error(array('message' => 'این نظرسنجی بسته شده است.'));
        }

        $answers = maybe_unserialize(get_post_meta($poll_id, '_smv_poll_answers', true));
        if (!is_array($answers) || !isset($answers[$answer_index])) {
            wp_send_json_error(array('message' => 'پاسخ نامعتبر است.'));
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $votes = get_option('smv_poll_votes_' . $poll_id, array());
        if (!is_array($votes)) {
            $votes = array();
        }
        if ($ip && in_array($ip, $votes, true)) {
            wp_send_json_error(array('message' => 'شما قبلاً در این نظرسنجی رای داده‌اید.'));
        }

        $answers[$answer_index]['votes'] = absint(isset($answers[$answer_index]['votes']) ? $answers[$answer_index]['votes'] : 0) + 1;
        update_post_meta($poll_id, '_smv_poll_answers', maybe_serialize($answers));
        if ($ip) {
            $votes[] = $ip;
            update_option('smv_poll_votes_' . $poll_id, $votes, false);
        }

        wp_send_json_success(array('message' => 'رای شما ثبت شد.'));
    }
}
