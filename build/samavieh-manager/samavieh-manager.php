<?php
/**
 * Plugin Name:       Samavieh Manager | مدیریت سماویه
 * Plugin URI:        https://example.com/samavieh
 * Description:       مدیریت تئاتر، پروژه، سانس‌بندی، ثبت‌نام، فیش واریزی، پیگیری وضعیت، نظرات، نظرسنجی، پرداخت زرین‌پال، خروجی PDF، پشتیبان‌گیری و پنل مدیریت؛ اختصاصی گروه فرهنگی هنری سماویه.
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Samavieh
 * Text Domain:       samavieh
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SMV_VERSION', '1.7.0');
define('SMV_DB_VERSION', '1.2.0');
define('SMV_FILE', __FILE__);
define('SMV_PATH', plugin_dir_path(__FILE__));
define('SMV_URL', plugin_dir_url(__FILE__));

require_once SMV_PATH . 'includes/class-smv-activator.php';
require_once SMV_PATH . 'includes/class-smv-persian.php';
require_once SMV_PATH . 'includes/helpers.php';
require_once SMV_PATH . 'includes/class-smv-cpt.php';
require_once SMV_PATH . 'includes/class-smv-meta.php';
require_once SMV_PATH . 'includes/class-smv-settings.php';
require_once SMV_PATH . 'includes/class-smv-forms.php';
require_once SMV_PATH . 'includes/class-smv-shortcodes.php';
require_once SMV_PATH . 'includes/class-smv-elementor.php';
require_once SMV_PATH . 'includes/class-smv-admin.php';
require_once SMV_PATH . 'includes/class-smv-plugin.php';
require_once SMV_PATH . 'includes/class-smv-pdf.php';
require_once SMV_PATH . 'includes/class-smv-enamad.php';
require_once SMV_PATH . 'includes/class-smv-booking.php';
require_once SMV_PATH . 'includes/class-smv-payment.php';
require_once SMV_PATH . 'includes/class-smv-poll.php';
require_once SMV_PATH . 'includes/class-smv-review.php';
require_once SMV_PATH . 'includes/class-smv-social.php';
require_once SMV_PATH . 'includes/class-smv-sms.php';
require_once SMV_PATH . 'includes/class-smv-backup.php';

register_activation_hook(__FILE__, array('SMV_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('SMV_Activator', 'deactivate'));

function smv_run_plugin() {
    return SMV_Plugin::instance();
}
add_action('plugins_loaded', 'smv_run_plugin', 20);
