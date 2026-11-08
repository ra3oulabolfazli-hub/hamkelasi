<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_CPT {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
        add_action('init', array(__CLASS__, 'register_taxonomies'), 11);
    }

    public static function register_post_types() {
        // منوهای این نوع نوشته‌ها در پنل اختصاصی «سماویه» نمایش داده می‌شوند
        // تا در پیشخوان وردپرس منوهای تکراری ایجاد نشود.
        register_post_type('smv_theatre', array(
            'labels' => array(
                'name' => 'تئاترها',
                'singular_name' => 'تئاتر',
                'menu_name' => 'تئاترها',
                'add_new' => 'افزودن تئاتر',
                'add_new_item' => 'افزودن تئاتر جدید',
                'edit_item' => 'ویرایش تئاتر',
                'new_item' => 'تئاتر جدید',
                'view_item' => 'مشاهده تئاتر',
                'search_items' => 'جست‌وجوی تئاترها',
                'not_found' => 'هنوز تئاتری اضافه نشده است.',
                'not_found_in_trash' => 'تئاتری در زباله‌دان نیست.',
                'featured_image' => 'پوستر اصلی تئاتر',
                'set_featured_image' => 'انتخاب پوستر تئاتر',
                'remove_featured_image' => 'حذف پوستر تئاتر',
                'use_featured_image' => 'استفاده به‌عنوان پوستر تئاتر',
            ),
            'public' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-tickets-alt',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'theatre', 'with_front' => false),
            'capability_type' => array('smv_theatre', 'smv_theatres'),
            'map_meta_cap' => true,
        ));

        register_post_type('smv_project', array(
            'labels' => array(
                'name' => 'پروژه‌ها',
                'singular_name' => 'پروژه',
                'menu_name' => 'پروژه‌ها',
                'add_new' => 'افزودن پروژه',
                'add_new_item' => 'افزودن پروژه جدید',
                'edit_item' => 'ویرایش پروژه',
                'new_item' => 'پروژه جدید',
                'view_item' => 'مشاهده پروژه',
                'search_items' => 'جست‌وجوی پروژه‌ها',
                'not_found' => 'هنوز پروژه‌ای اضافه نشده است.',
                'not_found_in_trash' => 'پروژه‌ای در زباله‌دان نیست.',
            ),
            'public' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-portfolio',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'project', 'with_front' => false),
            'capability_type' => array('smv_project', 'smv_projects'),
            'map_meta_cap' => true,
        ));

        register_post_type('smv_showtime', array(
            'labels' => array(
                'name' => 'سانس‌ها',
                'singular_name' => 'سانس',
                'menu_name' => 'سانس‌ها',
                'add_new' => 'افزودن سانس',
                'add_new_item' => 'افزودن سانس جدید',
                'edit_item' => 'ویرایش سانس',
                'new_item' => 'سانس جدید',
                'view_item' => 'مشاهده سانس',
                'search_items' => 'جست‌وجوی سانس‌ها',
                'not_found' => 'هنوز سانسی اضافه نشده است.',
                'not_found_in_trash' => 'سانسی در زباله‌دان نیست.',
            ),
            'public' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-calendar',
            'supports' => array('title', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'showtime', 'with_front' => false),
            'capability_type' => array('smv_showtime', 'smv_showtimes'),
            'map_meta_cap' => true,
        ));

        register_post_type('smv_poll', array(
            'labels' => array(
                'name' => 'نظرسنجی‌ها',
                'singular_name' => 'نظرسنجی',
                'menu_name' => 'نظرسنجی‌ها',
                'add_new' => 'افزودن نظرسنجی',
                'add_new_item' => 'افزودن نظرسنجی جدید',
                'edit_item' => 'ویرایش نظرسنجی',
                'new_item' => 'نظرسنجی جدید',
                'view_item' => 'مشاهده نظرسنجی',
                'search_items' => 'جست‌وجوی نظرسنجی‌ها',
                'not_found' => 'هنوز نظرسنجی‌ای اضافه نشده است.',
                'not_found_in_trash' => 'نظرسنجی در زباله‌دان نیست.',
            ),
            'public' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-chart-pie',
            'supports' => array('title', 'editor', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'poll', 'with_front' => false),
            'capability_type' => array('smv_poll', 'smv_polls'),
            'map_meta_cap' => true,
        ));

        register_post_type('smv_article', array(
            'labels' => array(
                'name' => 'مقالات',
                'singular_name' => 'مقاله',
                'menu_name' => 'مقالات',
                'add_new' => 'افزودن مقاله',
                'add_new_item' => 'افزودن مقالهٔ جدید',
                'edit_item' => 'ویرایش مقاله',
                'new_item' => 'مقالهٔ جدید',
                'view_item' => 'مشاهدهٔ مقاله',
                'search_items' => 'جست‌وجوی مقالات',
                'not_found' => 'هنوز مقاله‌ای اضافه نشده است.',
                'not_found_in_trash' => 'مقاله‌ای در زباله‌دان نیست.',
                'featured_image' => 'تصویر شاخص مقاله',
                'set_featured_image' => 'انتخاب تصویر شاخص',
                'remove_featured_image' => 'حذف تصویر شاخص',
            ),
            'public' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-media-document',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments', 'revisions', 'custom-fields'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'maghale', 'with_front' => false),
            'taxonomies' => array('smv_article_cat', 'smv_article_tag'),
            'capability_type' => array('smv_article', 'smv_articles'),
            'map_meta_cap' => true,
        ));

        // نوع نوشتهٔ قدیمی نظرات برای سازگاری باقی می‌ماند؛
        // نظرات جدید در جدول اختصاصی افزونه ذخیره می‌شوند.
        register_post_type('smv_review', array(
            'labels' => array(
                'name' => 'نظرات (قدیمی)',
                'singular_name' => 'نظر (قدیمی)',
                'menu_name' => 'نظرات (قدیمی)',
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'supports' => array('title', 'editor'),
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => array('smv_review', 'smv_reviews'),
            'map_meta_cap' => true,
        ));
    }

    public static function register_taxonomies() {
        register_taxonomy('smv_article_cat', array('smv_article'), array(
            'labels' => array('name' => 'دسته‌های مقالات', 'singular_name' => 'دستهٔ مقاله', 'add_new_item' => 'افزودن دسته', 'edit_item' => 'ویرایش دسته', 'search_items' => 'جست‌وجوی دسته‌ها'),
            'public' => true, 'hierarchical' => true, 'show_in_rest' => true, 'show_ui' => true, 'show_in_menu' => false,
            'rewrite' => array('slug' => 'maghale-cat', 'with_front' => false),
        ));
        register_taxonomy('smv_article_tag', array('smv_article'), array(
            'labels' => array('name' => 'برچسب‌های مقالات', 'singular_name' => 'برچسب مقاله', 'add_new_item' => 'افزودن برچسب', 'edit_item' => 'ویرایش برچسب', 'search_items' => 'جست‌وجوی برچسب‌ها'),
            'public' => true, 'hierarchical' => false, 'show_in_rest' => true, 'show_ui' => true, 'show_in_menu' => false,
            'rewrite' => array('slug' => 'maghale-tag', 'with_front' => false),
        ));
    }
}
