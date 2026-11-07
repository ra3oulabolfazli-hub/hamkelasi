<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * خروجی PDF برای ثبت‌نام‌ها و فیش‌ها
 * خروجی PDF: در صورت نبود کتابخانهٔ PDF، صفحهٔ چاپ اختصاصی در مرورگر باز می‌شود
 */
class SMV_PDF {

    public static function init() {
        add_action('admin_post_smv_export_pdf', array(__CLASS__, 'export_pdf'));
        add_action('admin_post_smv_export_registration_pdf', array(__CLASS__, 'export_single_registration_pdf'));
    }

    /**
     * خروجی PDF با TCPDF یا mPDF در صورت وجود، در غیر این صورت صفحهٔ آمادهٔ چاپ
     */
    public static function export_pdf() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        check_admin_referer('smv_export_registrations');

        global $wpdb;
        $filters = SMV_Admin::get_filters();
        $where = SMV_Admin::get_where($filters);
        $table = smv_table_name();

        $sql = "SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id WHERE {$where['sql']} ORDER BY r.created_at DESC LIMIT 1000";
        $rows = $where['params'] ? $wpdb->get_results($wpdb->prepare($sql, $where['params'])) : $wpdb->get_results($sql);

        self::render_pdf('ثبت‌نام‌های سماویه', $rows);
    }

    public static function export_single_registration_pdf() {
        if (!current_user_can('manage_smv_registrations')) {
            wp_die('دسترسی لازم را ندارید.');
        }
        $id = isset($_GET['registration_id']) ? absint($_GET['registration_id']) : 0;
        check_admin_referer('smv_view_receipt_' . $id);

        global $wpdb;
        $table = smv_table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT r.*, p.post_title AS theatre_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.theatre_id WHERE r.id = %d", $id));

        if (!$row) {
            wp_die('ثبت‌نام پیدا نشد.');
        }

        $rows = array($row);
        self::render_pdf('ثبت‌نام شماره ' . $row->tracking_code, $rows, true);
    }

    private static function render_pdf($title, $rows, $single = false) {
        // سعی در استفاده از TCPDF اگر نصب باشد
        $tcpdf_path = SMV_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            self::tcpdf_export($title, $rows, $single);
            return;
        }

        // در صورت وجود mPDF
        $mpdf_path = SMV_PATH . 'vendor/mpdf/mpdf/autoload.php';
        if (file_exists($mpdf_path)) {
            self::mpdf_export($title, $rows, $single);
            return;
        }

        // حالت نهایی: صفحهٔ HTML آمادهٔ چاپ که مرورگر می‌تواند «ذخیره به PDF» کند
        self::html_export($title, $rows, $single);
    }

    private static function tcpdf_export($title, $rows, $single) {
        require_once SMV_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Samavieh Manager');
        $pdf->SetAuthor('گروه فرهنگی هنری سماویه');
        $pdf->SetTitle($title);
        $pdf->setHeaderFont(array('dejavusans', '', 10));
        $pdf->setFooterFont(array('dejavusans', '', 8));
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $html = '<h2 style="font-family:dejavusans; color:#1a5f7a;">' . esc_html($title) . '</h2>';
        $html .= '<p style="font-family:dejavusans; font-size:9pt;">تاریخ تهیه: ' . esc_html(SMV_Persian::date(current_time('mysql'))) . '</p>';

        if ($single && !empty($rows[0])) {
            $row = $rows[0];
            $html .= '<table border="1" cellpadding="4" style="font-family:dejavusans; font-size:9pt; border-collapse:collapse; width:100%;">';
            $html .= '<tr><td><strong>کد پیگیری</strong></td><td>' . esc_html($row->tracking_code) . '</td></tr>';
            $html .= '<tr><td><strong>نام</strong></td><td>' . esc_html($row->first_name . ' ' . $row->last_name) . '</td></tr>';
            $html .= '<tr><td><strong>تلفن</strong></td><td>' . esc_html($row->phone) . '</td></tr>';
            $html .= '<tr><td><strong>تئاتر</strong></td><td>' . esc_html($row->theatre_title ?: 'تئاتر حذف شده') . '</td></tr>';
            $html .= '<tr><td><strong>وضعیت</strong></td><td>' . esc_html(smv_status_label($row->status)) . '</td></tr>';
            $html .= '<tr><td><strong>تاریخ ثبت</strong></td><td>' . esc_html(SMV_Persian::date($row->created_at)) . '</td></tr>';
            $html .= '<tr><td><strong>آخرین تغییر</strong></td><td>' . esc_html(SMV_Persian::date($row->updated_at)) . '</td></tr>';
            if ($row->admin_note) {
                $html .= '<tr><td><strong>توضیح مدیریت</strong></td><td>' . esc_html($row->admin_note) . '</td></tr>';
            }
            $html .= '</table>';
        } else {
            $html .= '<table border="1" cellpadding="4" style="font-family:dejavusans; font-size:8pt; border-collapse:collapse; width:100%;">';
            $html .= '<tr style="background:#eef5f8;"><th>کد پیگیری</th><th>نام</th><th>تلفن</th><th>تئاتر</th><th>وضعیت</th><th>تاریخ ثبت</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($row->tracking_code) . '</td>';
                $html .= '<td>' . esc_html($row->first_name . ' ' . $row->last_name) . '</td>';
                $html .= '<td>' . esc_html($row->phone) . '</td>';
                $html .= '<td>' . esc_html($row->theatre_title ?: 'تئاتر حذف شده') . '</td>';
                $html .= '<td>' . esc_html(smv_status_label($row->status)) . '</td>';
                $html .= '<td>' . esc_html(SMV_Persian::date($row->created_at)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('samavieh-' . sanitize_title($title) . '.pdf', 'D');
        exit;
    }

    private static function mpdf_export($title, $rows, $single) {
        require_once SMV_PATH . 'vendor/mpdf/mpdf/autoload.php';

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->SetHeader('{|{' . esc_html($title) . '}|}');
        $mpdf->SetFooter('{|{صفحه {PAGENO}}|}');

        ob_start();
        include SMV_PATH . 'includes/templates/pdf-template.php';
        $html = ob_get_clean();

        $mpdf->WriteHTML($html);
        $mpdf->Output('samavieh-' . sanitize_title($title) . '.pdf', 'D');
        exit;
    }

    private static function html_export($title, $rows, $single) {
        // صفحهٔ HTML آمادهٔ چاپ؛ کاربر با پنجرهٔ چاپ مرورگر می‌تواند آن را به PDF تبدیل کند.
        // (قبلاً یک فایل .html دانلود می‌شد که «PDF» نامیده می‌شد)
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($title); ?></title>
            <style>
                @page { size: A4; margin: 15mm; }
                body { font-family: 'Tahoma', 'Arial', sans-serif; direction: rtl; font-size: 12pt; color: #333; }
                h1 { color: #1a5f7a; text-align: center; border-bottom: 2px solid #1a5f7a; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: right; font-size: 11pt; }
                th { background: #eef5f8; font-weight: bold; }
                .info { margin-bottom: 20px; text-align: center; color: #666; }
                .print-btn { text-align: center; margin-top: 30px; }
                .print-btn button { background: #1a5f7a; color: #fff; border: none; padding: 10px 25px; font-size: 12pt; cursor: pointer; border-radius: 4px; }
                .print-btn button:hover { background: #154a62; }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html($title); ?></h1>
            <div class="info">تاریخ تهیه: <?php echo esc_html(SMV_Persian::date(current_time('mysql'))); ?> | گروه فرهنگی هنری سماویه</div>

            <?php if ($single && !empty($rows[0])) : $row = $rows[0]; ?>
                <table>
                    <tr><th style="width:30%;">کد پیگیری</th><td><?php echo esc_html($row->tracking_code); ?></td></tr>
                    <tr><th>نام ثبت‌نام‌کننده</th><td><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></td></tr>
                    <tr><th>شماره تماس</th><td><?php echo esc_html($row->phone); ?></td></tr>
                    <tr><th>تئاتر</th><td><?php echo esc_html($row->theatre_title ?: 'تئاتر حذف شده'); ?></td></tr>
                    <tr><th>وضعیت</th><td><?php echo esc_html(smv_status_label($row->status)); ?></td></tr>
                    <tr><th>تاریخ ثبت</th><td><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td></tr>
                    <tr><th>آخرین تغییر</th><td><?php echo esc_html(SMV_Persian::date($row->updated_at)); ?></td></tr>
                    <?php if ($row->admin_note) : ?><tr><th>توضیح مدیریت</th><td><?php echo esc_html($row->admin_note); ?></td></tr><?php endif; ?>
                </table>
            <?php else : ?>
                <table>
                    <tr><th>کد پیگیری</th><th>نام</th><th>تلفن</th><th>تئاتر</th><th>وضعیت</th><th>تاریخ ثبت</th></tr>
                    <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->tracking_code); ?></td>
                        <td><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></td>
                        <td><?php echo esc_html($row->phone); ?></td>
                        <td><?php echo esc_html($row->theatre_title ?: 'تئاتر حذف شده'); ?></td>
                        <td><?php echo esc_html(smv_status_label($row->status)); ?></td>
                        <td><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <div class="print-btn"><button onclick="window.print()">چاپ / ذخیره به عنوان PDF</button></div>
            <script>window.print();</script>
        </body>
        </html>
        <?php
        ob_end_flush();
        exit;
    }
}
