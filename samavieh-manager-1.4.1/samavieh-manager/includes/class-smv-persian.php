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
