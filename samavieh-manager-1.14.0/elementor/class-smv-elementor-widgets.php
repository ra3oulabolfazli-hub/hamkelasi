<?php
if (!defined('ABSPATH') || !class_exists('\Elementor\Widget_Base')) {
    exit;
}

abstract class SMV_Base_Widget extends \Elementor\Widget_Base {
    public function get_categories() {
        return array('smv');
    }

    public function get_keywords() {
        return array('سماویه', 'تئاتر', 'ثبت نام', 'samavieh');
    }

    public function get_style_depends() {
        return array('smv-frontend');
    }

    public function get_script_depends() {
        return array('smv-frontend');
    }
}

class SMV_Theatres_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-theatres'; }
    public function get_title() { return 'فهرست تئاترهای سماویه'; }
    public function get_icon() { return 'eicon-posts-grid'; }

    protected function register_controls() {
        $this->start_controls_section('content', array('label' => 'تنظیمات فهرست', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
        $this->add_control('count', array('label' => 'تعداد تئاترها', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 6, 'min' => 1, 'max' => 48));
        $this->add_control('columns', array('label' => 'تعداد ستون در دسکتاپ', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'options' => array('1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴')));
        $this->add_control('ids', array('label' => 'شناسهٔ تئاترهای مشخص (اختیاری)', 'type' => \Elementor\Controls_Manager::TEXT, 'description' => 'شناسه‌ها را با ویرگول انگلیسی جدا کنید؛ مثال: 12,25,40. خالی یعنی همه.'));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $ids = isset($settings['ids']) ? preg_replace('/[^0-9,]/', '', $settings['ids']) : '';
        echo do_shortcode('[samavieh_theatres count="' . absint($settings['count']) . '" columns="' . absint($settings['columns']) . '" ids="' . esc_attr($ids) . '"]');
    }
}

class SMV_Projects_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-projects'; }
    public function get_title() { return 'پروژه‌های سماویه'; }
    public function get_icon() { return 'eicon-gallery-grid'; }

    protected function register_controls() {
        $this->start_controls_section('content', array('label' => 'تنظیمات فهرست', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
        $this->add_control('count', array('label' => 'تعداد پروژه‌ها', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 6, 'min' => 1, 'max' => 48));
        $this->add_control('columns', array('label' => 'تعداد ستون در دسکتاپ', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'options' => array('1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴')));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[samavieh_projects count="' . absint($settings['count']) . '" columns="' . absint($settings['columns']) . '"]');
    }
}

class SMV_Theatre_Details_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-theatre-details'; }
    public function get_title() { return 'اطلاعات اجرای تئاتر'; }
    public function get_icon() { return 'eicon-info-box'; }

    protected function register_controls() {
        $this->start_controls_section('content', array('label' => 'انتخاب تئاتر', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
        $this->add_control('theatre', array('label' => 'تئاتر', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '0', 'options' => SMV_Elementor_Helper::theatre_options(), 'description' => 'در صفحهٔ خود تئاتر، «تئاتر صفحهٔ فعلی» را انتخاب کنید.'));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[samavieh_theatre_details id="' . absint($settings['theatre']) . '"]');
    }
}

class SMV_Registration_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-registration'; }
    public function get_title() { return 'فرم ثبت‌نام و فیش'; }
    public function get_icon() { return 'eicon-form-horizontal'; }

    protected function register_controls() {
        $this->start_controls_section('content', array('label' => 'انتخاب تئاتر', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT));
        $this->add_control('theatre', array('label' => 'تئاتر', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '0', 'options' => SMV_Elementor_Helper::theatre_options(), 'description' => 'در صفحهٔ خود تئاتر، «تئاتر صفحهٔ فعلی» را انتخاب کنید.'));
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[samavieh_registration theatre="' . absint($settings['theatre']) . '"]');
    }
}

class SMV_Tracking_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-tracking'; }
    public function get_title() { return 'فرم پیگیری ثبت‌نام'; }
    public function get_icon() { return 'eicon-search-results'; }
    protected function render() { echo do_shortcode('[samavieh_tracking]'); }
}

class SMV_Contact_Widget extends SMV_Base_Widget {
    public function get_name() { return 'smv-contact'; }
    public function get_title() { return 'اطلاعات تماس سماویه'; }
    public function get_icon() { return 'eicon-info-circle'; }
    protected function render() { echo do_shortcode('[samavieh_contact]'); }
}

class SMV_Elementor_Helper {
    public static function theatre_options() {
        $options = array('0' => 'تئاتر صفحهٔ فعلی');
        $posts = get_posts(array('post_type' => 'smv_theatre', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        foreach ($posts as $post) {
            $options[$post->ID] = $post->post_title;
        }
        return $options;
    }
}
