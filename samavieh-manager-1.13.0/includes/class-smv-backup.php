<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * پشتیبان‌گیری و بروزرسانی
 * - بک‌اپ دیتابیس و فایل‌های افزونه/قالب/آپلودها در پوشهٔ محافظت‌شده
 * - بازسازی از نسخه پشتیبان
 * - بررسی بروزرسانی‌های وردپرس، افزونه‌ها و قالب‌ها
 */
class SMV_Backup {

    public static function init() {
        add_action('admin_post_smv_backup_now', array(__CLASS__, 'backup_now'));
        add_action('admin_post_smv_restore_backup', array(__CLASS__, 'restore_backup'));
        add_action('admin_post_smv_delete_backup', array(__CLASS__, 'delete_backup'));
        add_action('admin_init', array(__CLASS__, 'schedule_backup'));
        add_action('smv_scheduled_backup', array(__CLASS__, 'run_scheduled_backup'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
    }

    private static function backup_dir() {
        return WP_CONTENT_DIR . '/samavieh-backups';
    }

    /**
     * پوشهٔ پشتیبان شامل فایل SQL است؛ باید از دسترسی عمومی محافظت شود.
     */
    private static function protect_dir() {
        $dir = self::backup_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", LOCK_EX);
        }
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
        }
    }

    private static function safe_backup_path($file) {
        $dir = realpath(self::backup_dir());
        $path = realpath($file);
        if (!$dir || !$path) {
            return '';
        }
        if (0 !== strpos($path, $dir . DIRECTORY_SEPARATOR)) {
            return '';
        }
        if (!preg_match('/\.zip$/i', $path)) {
            return '';
        }
        return $path;
    }

    public static function admin_notices() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || (false === strpos((string) $screen->id, 'smv-backup') && 'toplevel_page_smv-dashboard' !== $screen->id)) {
            return;
        }
        if (isset($_GET['smv_backup_done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>نسخه پشتیبان با موفقیت ایجاد شد.</p></div>';
        }
        if (isset($_GET['smv_restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>بازسازی نسخه پشتیبان انجام شد.</p></div>';
        }
        if (isset($_GET['smv_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>نسخه پشتیبان حذف شد.</p></div>';
        }
        if (isset($_GET['smv_backup_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>خطا: ' . esc_html(sanitize_text_field(wp_unslash($_GET['smv_backup_error']))) . '</p></div>';
        }
    }

    public static function render_page() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }

        $backup_dir = self::backup_dir();
        $backups = array();
        if (is_dir($backup_dir)) {
            foreach ((array) scandir($backup_dir) as $file) {
                if ('.' === $file || '..' === $file) {
                    continue;
                }
                $path = $backup_dir . '/' . $file;
                if (is_file($path) && preg_match('/\.zip$/i', $file)) {
                    $backups[] = array(
                        'name' => $file,
                        'size' => size_format((int) filesize($path)),
                        'date' => wp_date('Y/m/d H:i', (int) filemtime($path)),
                        'path' => $path,
                    );
                }
            }
            usort($backups, function ($a, $b) {
                return filemtime($b['path']) <=> filemtime($a['path']);
            });
        }

        $update_checks = self::check_updates();
        ?>
        <div class="wrap smv-admin-wrap" dir="rtl">
            <div class="smv-admin-heading">
                <div>
                    <span class="smv-admin-kicker">نسخه پشتیبان و بازسازی</span>
                    <h1>پشتیبان‌گیری و بروزرسانی</h1>
                    <p>قبل از هر تغییر مهم، یک نسخه پشتیبان بگیرید. در صورت نیاز می‌توانید به نسخهٔ قبلی بازگردید.</p>
                </div>
            </div>

            <section class="smv-admin-card">
                <div class="smv-card-title-row"><div><h2>پشتیبان‌گیری فوری</h2><p>نسخهٔ پشتیبان از دیتابیس و فایل‌های مهم ذخیره می‌شود.</p></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smv_backup_action'); ?>
                    <input type="hidden" name="action" value="smv_backup_now" />
                    <button type="submit" class="button smv-primary-button">ایجاد نسخه پشتیبان فوری</button>
                </form>
                <p class="smv-settings-help">بک‌اپ شامل: دیتابیس وردپرس، فایل‌های افزونهٔ سماویه، قالب فعال و پوشهٔ آپلودها.</p>
            </section>

            <section class="smv-admin-card">
                <div class="smv-card-title-row"><div><h2>نسخه‌های پشتیبان موجود</h2><p>نسخه‌های ذخیره‌شده برای بازسازی یا حذف.</p></div></div>
                <?php if ($backups) : ?>
                    <table class="smv-backup-table">
                        <thead><tr><th>نام فایل</th><th>حجم</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($backups as $backup) : ?>
                            <tr>
                                <td><?php echo esc_html($backup['name']); ?></td>
                                <td><?php echo esc_html($backup['size']); ?></td>
                                <td><?php echo esc_html($backup['date']); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('smv_backup_action'); ?>
                                        <input type="hidden" name="action" value="smv_restore_backup" />
                                        <input type="hidden" name="backup_file" value="<?php echo esc_attr($backup['path']); ?>" />
                                        <button type="submit" class="button button-small" onclick="return confirm('از بازسازی این نسخه مطمئن هستید؟ تغییرات فعلی بازنویسی می‌شوند.')">بازسازی</button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-right:6px;">
                                        <?php wp_nonce_field('smv_backup_action'); ?>
                                        <input type="hidden" name="action" value="smv_delete_backup" />
                                        <input type="hidden" name="backup_file" value="<?php echo esc_attr($backup['path']); ?>" />
                                        <button type="submit" class="button button-small smv-danger-button" onclick="return confirm('این نسخه پشتیبان برای همیشه حذف شود؟')">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="smv-admin-empty"><span>📦</span><p>هنوز نسخه پشتیبانی ایجاد نشده است.</p></div>
                <?php endif; ?>
            </section>

            <section class="smv-admin-card">
                <div class="smv-card-title-row"><div><h2>بروزرسانی از فایل ZIP یا JSON</h2><p>فایل آپدیت (.zip) یا فایل اطلاعات نسخه (.json) را انتخاب کنید.</p></div></div>
                <form id="smv-smart-update-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('smv_smart_update'); ?>
                    <div class="smv-settings-grid">
                        <div class="smv-admin-field full">
                            <label for="smv_update_zip"><strong>فایل آپدیت (.zip)</strong></label>
                            <input type="file" id="smv_update_zip" name="smv_update_zip" accept=".zip" />
                            <small>فایل ZIP حاوی پوشهٔ samavieh-manager نسخه جدید.</small>
                        </div>
                        <div class="smv-admin-field full">
                            <label for="smv_update_json"><strong>فایل اطلاعات نسخه (.json)</strong></label>
                            <input type="file" id="smv_update_json" name="smv_update_json" accept=".json" />
                            <small>اختیاری؛ نمونه: {"version":"1.4.0"}</small>
                        </div>
                    </div>
                    <button type="submit" class="button smv-primary-button" id="smv-smart-update-btn">اعمال بروزرسانی</button>
                </form>
                <div id="smv-smart-update-result" style="margin-top:15px; padding:12px; border-radius:8px; display:none;"></div>
                <script>
                jQuery(document).ready(function($){
                    var smv_ajax_url = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                    $('#smv-smart-update-form').on('submit', function(e){
                        e.preventDefault();
                        var btn = $('#smv-smart-update-btn');
                        var result = $('#smv-smart-update-result');
                        btn.prop('disabled', true).text('در حال پردازش…');
                        result.hide().removeClass().empty();
                        var formData = new FormData(this);
                        formData.append('action', 'smv_smart_update');
                        formData.append('_wpnonce', $('input[name="_wpnonce"]').val());
                        $.ajax({
                            url: smv_ajax_url,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(resp){
                                if (resp.success) {
                                    result.css({'background':'#e4f4eb','border':'1px solid #388367','color':'#176245'});
                                    result.html('<strong>✅ موفق:</strong> ' + resp.data.message).show();
                                    if (resp.data.redirect) {
                                        setTimeout(function(){ window.location.href = resp.data.redirect; }, 1500);
                                    }
                                } else {
                                    result.css({'background':'#fde9e7','border':'1px solid #dfbcbc','color':'#944545'});
                                    result.html('<strong>❌ خطا:</strong> ' + resp.data.message).show();
                                    btn.prop('disabled', false).text('اعمال بروزرسانی');
                                }
                            },
                            error: function(){
                                result.css({'background':'#fde9e7','border':'1px solid #dfbcbc','color':'#944545'});
                                result.html('<strong>❌ خطا:</strong> ارتباط با سرور برقرار نشد.').show();
                                btn.prop('disabled', false).text('اعمال بروزرسانی');
                            }
                        });
                    });
                });
                </script>
                <p class="smv-settings-help">توجه: قبل از هر بروزرسانی، حتماً یک نسخه پشتیبان بگیرید.</p>
            </section>

            <section class="smv-admin-card">
                <div class="smv-card-title-row"><div><h2>وضعیت بروزرسانی‌ها</h2><p>بررسی نسخه‌های جدید وردپرس، قالب‌ها و افزونه‌ها.</p></div></div>
                <div class="smv-update-grid">
                    <?php foreach ($update_checks as $check) : ?>
                        <div class="smv-update-card <?php echo esc_attr($check['class']); ?>">
                            <div class="smv-update-icon"><?php echo esc_html($check['icon']); ?></div>
                            <div class="smv-update-info">
                                <div class="smv-update-title"><?php echo esc_html($check['title']); ?></div>
                                <div class="smv-update-status"><?php echo esc_html($check['status']); ?></div>
                            </div>
                            <?php if (!empty($check['url'])) : ?>
                                <a href="<?php echo esc_url($check['url']); ?>" class="button smv-update-btn">بروزرسانی</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function check_updates() {
        $checks = array();

        $core_update = get_site_option('update_core');
        $has_core_update = false;
        if (is_object($core_update) && !empty($core_update->updates)) {
            foreach ((array) $core_update->updates as $update) {
                if (isset($update->response) && 'upgrade' === $update->response) {
                    $has_core_update = true;
                    $checks[] = array(
                        'title' => 'هستهٔ وردپرس',
                        'status' => 'نسخهٔ جدید موجود است: ' . (isset($update->current) ? $update->current : ''),
                        'class' => 'needs-update',
                        'icon' => '🔧',
                        'url' => network_admin_url('update-core.php'),
                    );
                }
            }
        }
        if (!$has_core_update) {
            $checks[] = array(
                'title' => 'هستهٔ وردپرس',
                'status' => 'به‌روز است',
                'class' => 'up-to-date',
                'icon' => '🔧',
                'url' => '',
            );
        }

        $plugin_updates = (array) get_site_option('update_plugins');
        $has_plugin_update = false;
        if (!empty($plugin_updates['response']) && is_array($plugin_updates['response'])) {
            foreach ($plugin_updates['response'] as $plugin_file => $data) {
                if (is_object($data) && !empty($data->new_version)) {
                    $has_plugin_update = true;
                    $checks[] = array(
                        'title' => 'افزونه: ' . $plugin_file,
                        'status' => 'نسخه جدید: ' . $data->new_version,
                        'class' => 'needs-update',
                        'icon' => '🧩',
                        'url' => admin_url('update.php?action=upgrade-plugin&plugin=' . rawurlencode($plugin_file) . '&_wpnonce=' . wp_create_nonce('upgrade-plugin_' . $plugin_file)),
                    );
                }
            }
        }
        if (!$has_plugin_update) {
            $checks[] = array(
                'title' => 'افزونه‌های نصب‌شده',
                'status' => 'همه به‌روز هستند',
                'class' => 'up-to-date',
                'icon' => '🧩',
                'url' => '',
            );
        }

        $theme_updates = (array) get_site_option('update_themes');
        $has_theme_update = false;
        if (!empty($theme_updates['response']) && is_array($theme_updates['response'])) {
            foreach ($theme_updates['response'] as $theme => $data) {
                if (is_object($data) && !empty($data->new_version)) {
                    $has_theme_update = true;
                    $checks[] = array(
                        'title' => 'قالب: ' . $theme,
                        'status' => 'نسخه جدید: ' . $data->new_version,
                        'class' => 'needs-update',
                        'icon' => '🎨',
                        'url' => admin_url('themes.php'),
                    );
                }
            }
        }
        if (!$has_theme_update) {
            $checks[] = array(
                'title' => 'قالب‌های نصب‌شده',
                'status' => 'همه به‌روز هستند',
                'class' => 'up-to-date',
                'icon' => '🎨',
                'url' => '',
            );
        }

        return $checks;
    }

    public static function schedule_backup() {
        if (!wp_next_scheduled('smv_scheduled_backup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', 'smv_scheduled_backup');
        }
    }

    public static function run_scheduled_backup() {
        self::create_backup();
    }

    public static function backup_now() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_backup_action');

        $result = self::create_backup();
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('smv_backup_error', rawurlencode($result->get_error_message()), admin_url('admin.php?page=smv-backup')));
            exit;
        }

        wp_safe_redirect(add_query_arg('smv_backup_done', '1', admin_url('admin.php?page=smv-backup')));
        exit;
    }

    private static function create_backup() {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', 'افزونهٔ ZIP روی سرور فعال نیست (extension=zip).');
        }

        self::protect_dir();
        $backup_dir = self::backup_dir();
        $filename = 'samavieh-backup-' . gmdate('Y-m-d-H-i-s') . '.zip';
        $zip_path = $backup_dir . '/' . $filename;

        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return new WP_Error('zip_open', 'نمی‌توان فایل ZIP را ایجاد کرد.');
        }

        $db_sql = self::export_database();
        if ($db_sql) {
            $zip->addFromString('database/' . gmdate('Y-m-d-H-i-s') . '-samavieh-database.sql', $db_sql);
        }

        $plugin_dir = WP_PLUGIN_DIR . '/samavieh-manager';
        if (is_dir($plugin_dir)) {
            self::add_dir_to_zip($plugin_dir, $zip, 'plugin/samavieh-manager/');
        }

        $theme_dir = get_stylesheet_directory();
        if (is_dir($theme_dir)) {
            self::add_dir_to_zip($theme_dir, $zip, 'theme/' . basename($theme_dir) . '/');
        }

        $uploads_dir = WP_CONTENT_DIR . '/uploads';
        if (is_dir($uploads_dir)) {
            self::add_dir_to_zip($uploads_dir, $zip, 'uploads/');
        }

        $zip->close();

        if (!is_file($zip_path) || filesize($zip_path) < 1024) {
            return new WP_Error('zip_empty', 'فایل پشتیبان ایجاد نشد (حجم فایل صفر است).');
        }

        return $zip_path;
    }

    public static function restore_backup() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_backup_action');

        $file = self::safe_backup_path(isset($_POST['backup_file']) ? sanitize_text_field(wp_unslash($_POST['backup_file'])) : '');
        if (!$file) {
            wp_safe_redirect(add_query_arg('smv_backup_error', rawurlencode('فایل پشتیبان معتبر نیست.'), admin_url('admin.php?page=smv-backup')));
            exit;
        }

        if (!class_exists('ZipArchive')) {
            wp_safe_redirect(add_query_arg('smv_backup_error', rawurlencode('افزونهٔ ZIP روی سرور فعال نیست.'), admin_url('admin.php?page=smv-backup')));
            exit;
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($file)) {
            wp_safe_redirect(add_query_arg('smv_backup_error', rawurlencode('نمی‌توان فایل ZIP را باز کرد.'), admin_url('admin.php?page=smv-backup')));
            exit;
        }

        $extract_dir = self::backup_dir() . '/_restore_' . gmdate('Y-m-d-H-i-s');
        wp_mkdir_p($extract_dir);
        $zip->extractTo($extract_dir);
        $zip->close();

        $db_files = glob($extract_dir . '/database/*.sql');
        if (!empty($db_files)) {
            self::import_database($db_files[0]);
        }

        $plugin_restore = $extract_dir . '/plugin/samavieh-manager';
        if (is_dir($plugin_restore)) {
            $dest = WP_PLUGIN_DIR . '/samavieh-manager';
            if (is_dir($dest)) {
                self::remove_dir($dest);
            }
            self::copy_dir($plugin_restore, $dest);
        }

        self::remove_dir($extract_dir);

        wp_safe_redirect(add_query_arg('smv_restored', '1', admin_url('admin.php?page=smv-backup')));
        exit;
    }

    public static function delete_backup() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_backup_action');
        $file = self::safe_backup_path(isset($_POST['backup_file']) ? sanitize_text_field(wp_unslash($_POST['backup_file'])) : '');
        if ($file && is_file($file)) {
            @unlink($file);
        }
        wp_safe_redirect(admin_url('admin.php?page=smv-backup&smv_deleted=1'));
        exit;
    }

    /*──────────────────────────────────────────────
     * دیتابیس: export / import
     *────────────────────────────────────────────*/

    private static function export_database() {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        if (empty($tables)) {
            return false;
        }

        $sql = '-- Samavieh Database Backup -- ' . gmdate('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $sql .= "-- Table: {$table}\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_A);
            if ($create && isset($create['Create Table'])) {
                $sql .= $create['Create Table'] . ";\n\n";
            }

            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $col_list = '`' . implode('`, `', $columns) . '`';
                foreach (array_chunk($rows, 100) as $batch) {
                    $values = array();
                    foreach ($batch as $row) {
                        $escaped = array();
                        foreach ($columns as $col) {
                            $val = $row[$col];
                            if (null === $val) {
                                $escaped[] = 'NULL';
                            } else {
                                $escaped[] = "'" . esc_sql((string) $val) . "'";
                            }
                        }
                        $values[] = '(' . implode(',', $escaped) . ')';
                    }
                    $sql .= "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $values) . ";\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private static function import_database($sql_file) {
        global $wpdb;
        if (!is_file($sql_file)) {
            return false;
        }
        $sql = file_get_contents($sql_file);
        if (!$sql) {
            return false;
        }

        // هر دستور در فایل خروجی با «;» و خط جدید پایان می‌یابد.
        $statements = preg_split("/;\s*\n/", $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ('' === $statement || 0 === strpos($statement, '--') || 0 === strpos($statement, 'SET ')) {
                continue;
            }
            $wpdb->query($statement . ';');
        }
        return true;
    }

    /*──────────────────────────────────────────────
     * ZIP helpers
     *────────────────────────────────────────────*/

    private static function add_dir_to_zip($dir, $zip, $relative = '') {
        $entries = scandir($dir);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            $path = $dir . '/' . $file;
            $zip_path = $relative . $file;
            if (is_dir($path)) {
                if (in_array($file, array('node_modules', '.git', 'cache', 'upgrade', 'backups-dup-lite'), true)) {
                    continue;
                }
                $zip->addEmptyDir($zip_path);
                self::add_dir_to_zip($path, $zip, $zip_path . '/');
            } else {
                if (preg_match('/\.(log|tmp)$/i', $file) || !is_readable($path)) {
                    continue;
                }
                if (filesize($path) > 200 * MB_IN_BYTES) {
                    continue;
                }
                $zip->addFile($path, $zip_path);
            }
        }
    }

    private static function copy_dir($src, $dst) {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            wp_mkdir_p($dst);
        }
        foreach ((array) scandir($src) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                self::copy_dir($src_path, $dst_path);
            } else {
                @copy($src_path, $dst_path);
            }
        }
    }

    private static function remove_dir($dir) {
        if (!is_dir($dir)) {
            return true;
        }
        foreach ((array) scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::remove_dir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
        return true;
    }
}
