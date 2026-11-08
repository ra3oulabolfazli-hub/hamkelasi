<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?php echo isset($title) ? esc_html($title) : 'ثبت‌نام‌های سماویه'; ?></title>
<style>
@page { size: A4; margin: 15mm; }
body { font-family: 'Tahoma', 'Arial', sans-serif; direction: rtl; font-size: 11pt; color: #333; line-height: 1.6; }
h1 { color: #1a5f7a; text-align: center; border-bottom: 2px solid #1a5f7a; padding-bottom: 10px; margin-bottom: 20px; }
.info { text-align: center; color: #666; margin-bottom: 20px; font-size: 10pt; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10pt; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
th { background: #eef5f8; font-weight: bold; }
.single { max-width: 700px; margin: 0 auto; }
.single td { vertical-align: top; }
.print-note { margin-top: 30px; text-align: center; font-size: 9pt; color: #888; }
</style>
</head>
<body>
<h1><?php echo isset($title) ? esc_html($title) : 'ثبت‌نام‌های سماویه'; ?></h1>
<div class="info">تاریخ تهیه: <?php echo isset($date) ? esc_html($date) : SMV_Persian::date(current_time('mysql')); ?> | گروه فرهنگی هنری سماویه</div>

<?php if (!empty($single_row) || (!empty($single) && !empty($rows[0]))) : $row = !empty($single_row) ? $single_row : $rows[0]; ?>
<div class="single">
<table>
<tr><th style="width:35%;">کد پیگیری</th><td><?php echo esc_html($row->tracking_code); ?></td></tr>
<tr><th>نام ثبت‌نام‌کننده</th><td><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></td></tr>
<tr><th>شماره تماس</th><td><?php echo esc_html($row->phone); ?></td></tr>
<tr><th>تعداد نفرات</th><td><?php echo esc_html(smv_registration_people_count(isset($row->companions) ? $row->companions : 0)); ?> نفر<?php if (!empty($row->companions)) : ?> (<?php echo esc_html(smv_companions_label($row->companions)); ?>)<?php endif; ?></td></tr>
<tr><th>تئاتر</th><td><?php echo esc_html($row->theatre_title ?: 'تئاتر حذف شده'); ?></td></tr>
<tr><th>وضعیت</th><td><?php echo esc_html(smv_status_label($row->status)); ?></td></tr>
<tr><th>تاریخ ثبت</th><td><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td></tr>
<tr><th>آخرین تغییر</th><td><?php echo esc_html(SMV_Persian::date($row->updated_at)); ?></td></tr>
<?php if ($row->admin_note) : ?><tr><th>توضیح مدیریت</th><td><?php echo esc_html($row->admin_note); ?></td></tr><?php endif; ?>
</table>
</div>
<?php else : ?>
<table>
<tr><th>کد پیگیری</th><th>نام</th><th>تلفن</th><th>تئاتر</th><th>تعداد نفرات</th><th>وضعیت</th><th>تاریخ ثبت</th></tr>
<?php foreach ($rows as $row) : ?>
<tr>
<td><?php echo esc_html($row->tracking_code); ?></td>
<td><?php echo esc_html($row->first_name . ' ' . $row->last_name); ?></td>
<td><?php echo esc_html($row->phone); ?></td>
<td><?php echo esc_html($row->theatre_title ?: 'تئاتر حذف شده'); ?></td>
<td><?php echo esc_html(smv_registration_people_count(isset($row->companions) ? $row->companions : 0)); ?> نفر</td>
<td><?php echo esc_html(smv_status_label($row->status)); ?></td>
<td><?php echo esc_html(SMV_Persian::date($row->created_at)); ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<div class="print-note">این فایل توسط پلاگین مدیریت سماویه تولید شده است.</div>
</body>
</html>
