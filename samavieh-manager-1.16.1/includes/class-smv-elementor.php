<?php
if (!defined('ABSPATH')) {
    exit;
}

class SMV_Elementor {
    public static function init() {
        // فقط اگر المنتور فعال باشد، ویجت‌ها را ثبت می‌کنیم
        if (!defined('ELEMENTOR_VERSION') || !class_exists('\\Elementor\\Plugin')) {
            return;
        }
        add_action('elementor/elements/categories_registered', array(__CLASS__, 'register_category'));
        add_action('elementor/widgets/register', array(__CLASS__, 'register_widgets'));
    }

    public static function register_category($elements_manager) {
        $elements_manager->add_category('smv', array(
            'title' => 'سماویه',
            'icon' => 'fa fa-star',
        ));
    }

    public static function register_widgets($widgets_manager) {
        $widgets_file = SMV_PATH . 'elementor/class-smv-elementor-widgets.php';
        if (!file_exists($widgets_file)) {
            return;
        }
        require_once $widgets_file;
        $widgets = array(
            'SMV_Theatres_Widget',
            'SMV_Projects_Widget',
            'SMV_Theatre_Details_Widget',
            'SMV_Registration_Widget',
            'SMV_Tracking_Widget',
            'SMV_Contact_Widget',
        );
        foreach ($widgets as $widget) {
            if (class_exists($widget)) {
                $widgets_manager->register(new $widget());
            }
        }
    }
}
