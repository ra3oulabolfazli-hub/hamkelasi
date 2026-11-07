<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Persian {
    public static function digits($value) {
        return strtr((string) $value, array(
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ));
    }

    public static function english_digits($value) {
        $value = strtr((string) $value, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ));
        return $value;
    }

    public static function gregorian_to_jalali($gy, $gm, $gd) {
        $gy = (int) $gy;
        $gm = (int) $gm;
        $gd = (int) $gd;
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return array($jy, $jm, $jd);
    }


    /* ------------------------------------------------------------------
     * تبدیل دوطرفهٔ تاریخ شمسی/میلادی (الگوریتم تقویم جلالی، بدون وابستگی)
     * ------------------------------------------------------------------ */

    private static function int_div($a, $b) {
        // تقسیم با کوتاه‌سازی به سمت صفر (مطابق الگوریتم مرجع جلالی)؛ floor برای اعداد منفی اشتباه است.
        return (int) ($a / $b);
    }

    private static function int_mod($a, $b) {
        return $a - self::int_div($a, $b) * $b;
    }

    /**
     * اطلاعات تقویم جلالی برای یک سال: { leap, gy, march }
     */
    private static function jalali_calendar($jy) {
        $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178);
        $bl = count($breaks);
        $jy = (int) $jy;
        if ($jy < $breaks[0] || $jy >= $breaks[$bl - 1]) {
            return array('leap' => 0, 'gy' => $jy + 621, 'march' => 21);
        }
        $gy = $jy + 621;
        $leap_j = -14;
        $jp = $breaks[0];
        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leap_j = $leap_j + self::int_div($jump, 33) * 8 + self::int_div(self::int_mod($jump, 33), 4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leap_j = $leap_j + self::int_div($n, 33) * 8 + self::int_div(self::int_mod($n, 33) + 3, 4);
        if (self::int_mod($jump, 33) === 4 && ($jump - $n) === 4) {
            $leap_j++;
        }
        $leap_g = self::int_div($gy, 4) - self::int_div((self::int_div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leap_j - $leap_g;
        $leap = self::int_mod(self::int_mod($n, 33) + 3, 4);
        if (2 === $leap) {
            $leap = 1;
        } else {
            $leap = 0;
        }
        return array('leap' => $leap, 'gy' => $gy, 'march' => $march);
    }

    /** شمارهٔ روز جولیَن از تاریخ میلادی */
    public static function gregorian_to_jdn($gy, $gm, $gd) {
        $d = self::int_div((($gy + self::int_div($gm - 8, 6) + 100100) * 1461), 4)
            + self::int_div(153 * self::int_mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;
        return $d - self::int_div(self::int_div($gy + 100100 + self::int_div($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /** تاریخ میلادی از شمارهٔ روز جولیَن */
    public static function jdn_to_gregorian($jdn) {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::int_div(self::int_div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::int_div(self::int_mod($j, 1461), 4) * 5 + 308;
        $gd = self::int_div(self::int_mod($i, 153), 5) + 1;
        $gm = self::int_mod(self::int_div($i, 153), 12) + 1;
        $gy = self::int_div($j, 1461) - 100100 + self::int_div(8 - $gm, 6);
        return array($gy, $gm, $gd);
    }

    /** شمسی → میلادی */
    public static function jalali_to_gregorian($jy, $jm, $jd) {
        $jy = (int) $jy;
        $jm = (int) $jm;
        $jd = (int) $jd;
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return array(0, 0, 0);
        }
        $cal = self::jalali_calendar($jy);
        $jdn = self::gregorian_to_jdn($cal['gy'], 3, $cal['march'])
            + ($jm - 1) * 31 - self::int_div($jm, 7) * ($jm - 7) + $jd - 1;
        list($gy, $gm, $gd) = self::jdn_to_gregorian($jdn);
        if (!checkdate($gm, $gd, $gy)) {
            return array(0, 0, 0);
        }
        return array($gy, $gm, $gd);
    }

    /** «۱۴۰۳/۰۱/۰۱» برای نمایش در ورودی‌های فرم */
    public static function date_input_value($date) {
        $date = trim((string) $date);
        if ('' === $date || '0000-00-00' === $date) {
            return '';
        }
        if (!preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', self::english_digits($date), $m)) {
            return '';
        }
        list($jy, $jm, $jd) = self::gregorian_to_jalali($m[1], $m[2], $m[3]);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /**
     * ورودی تاریخ کاربر → تاریخ میلادی Y-m-d (برای ذخیره در دیتابیس).
     * هر دو قالب شمسی (۱۴۰۵/۰۲/۱۲) و میلادی (2026-05-02) پذیرفته می‌شود.
     */
    public static function parse_date_input($value) {
        $value = trim((string) self::english_digits((string) $value));
        if ('' === $value) {
            return '';
        }
        $value = str_replace(array('/', '.', '،', ' '), '-', $value);
        if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            return '';
        }
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if ($y >= 1700) {
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : '';
        }
        if ($y >= 1200 && $y <= 1599) {
            list($gy, $gm, $gd) = self::jalali_to_gregorian($y, $mo, $d);
            return $gd ? sprintf('%04d-%02d-%02d', $gy, $gm, $gd) : '';
        }
        return '';
    }

    /**
     * نرمال‌سازی ساعت سانس به قالب ۲۴ ساعتهٔ «HH:MM» (مثلاً «18:30»).
     * ارقام فارسی/عربی، «۱۸:۳۰» و «8:5» همه پذیرفته و به «08:05» تبدیل می‌شوند.
     * قالب ۱۲ ساعته (AM/PM) عمداً پشتیبانی نمی‌شود تا ساعت همیشه ۲۴ ساعته بماند.
     * ورودی نامعتبر → رشتهٔ خالی.
     */
    public static function normalize_time($value) {
        $value = trim((string) self::english_digits((string) $value));
        if ('' === $value) {
            return '';
        }
        if (!preg_match('/^(\d{1,2})\s*[:.]\s*(\d{1,2})(?:\s*[:.]\s*\d{1,2})?$/', $value, $m)) {
            return '';
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            return '';
        }
        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** ساعت شروع و پایان سانس به‌صورت ۲۴ ساعته: «۱۸:۳۰ تا ۲۰:۰۰» */
    public static function time_range($start, $end = '') {
        $start = self::normalize_time($start);
        $end = self::normalize_time($end);
        if ('' === $start) {
            return '' === $end ? '' : self::digits($end);
        }
        if ('' === $end || $end === $start) {
            return self::digits($start);
        }
        return self::digits($start . ' تا ' . $end);
    }

    public static function date($date, $with_time = false) {
        if (empty($date) || '0000-00-00' === $date) {
            return '';
        }
        if (is_numeric($date)) {
            $timestamp = (int) $date;
        } else {
            try {
                $local_date = new DateTimeImmutable((string) $date, wp_timezone());
                $timestamp = $local_date->getTimestamp();
            } catch (Exception $exception) {
                $timestamp = false;
            }
        }
        if (!$timestamp) {
            return '';
        }
        $parts = explode('-', wp_date('Y-n-j', $timestamp));
        list($jy, $jm, $jd) = self::gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        $months = array(
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        );
        $output = $jd . ' ' . $months[$jm] . ' ' . $jy;
        if ($with_time) {
            $output .= '، ساعت ' . wp_date('H:i', $timestamp);
        }
        return self::digits($output);
    }

    public static function money($amount) {
        if ('' === $amount || null === $amount) {
            return 'تماس بگیرید';
        }
        if (0 === (int) $amount) {
            return 'رایگان';
        }
        return self::digits(number_format_i18n((float) $amount, 0)) . ' تومان';
    }

    public static function normalize_phone($phone) {
        $phone = self::english_digits($phone);
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        if (0 === strpos($phone, '+98')) {
            $phone = '0' . substr($phone, 3);
        } elseif (0 === strpos($phone, '0098')) {
            $phone = '0' . substr($phone, 4);
        } elseif (0 === strpos($phone, '98') && 12 === strlen($phone)) {
            $phone = '0' . substr($phone, 2);
        }
        return $phone;
    }
}
