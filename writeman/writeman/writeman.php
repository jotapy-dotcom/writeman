<?php
/**
 * Plugin Name: WriteMan
 * Plugin URI:  https://20xxnoticias.com
 * Description: Generación autónoma de noticias con IA (Groq/OpenRouter), curaduría RSS, publicación automática, A/B testing, aprendizaje y redes sociales.
 * Version:     1.0.3
 * Author:      Jota
 * Author URI:  https://20xxnoticias.com
 * Text Domain: writeman
 * Domain Path: /languages
 * License:     GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

define('WRITEMAN_VERSION', '1.0.3');
define('WRITEMAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WRITEMAN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WRITEMAN_PLUGIN_BASENAME', plugin_basename(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'WriteMan_';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = WRITEMAN_PLUGIN_DIR . 'includes/class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';
    if (file_exists($file)) require $file;
});

if (is_admin()) {
    require_once WRITEMAN_PLUGIN_DIR . 'admin/class-admin.php';
}

class WriteMan_Core {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        register_activation_hook(WRITEMAN_PLUGIN_BASENAME, [$this, 'activate']);
        register_deactivation_hook(WRITEMAN_PLUGIN_BASENAME, [$this, 'deactivate']);
        add_action('init', [$this, 'init']);
        add_action('wp_loaded', [$this, 'schedule_cron']);
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('writeman_sync_feeds_cron', [$this, 'cron_sync_feeds']);
        add_action('writeman_process_queue', [$this, 'cron_process_queue']);
    }
    public function activate() {
        WriteMan_DB::create_tables();
        $defaults = [
            'writing_style' => 'profesional, informativo, atractivo',
            'custom_prompt' => '',
            'quality_threshold' => 30,
            'viral_threshold' => 30,
            'max_posts_per_run' => 3,
            'feeds' => [],
            'post_status' => 'publish',
            'post_format' => 'standard',
            'allow_comments' => 1,
            'allow_pings' => 1,
            'cron_schedule' => ['type'=>'interval','minutes'=>5,'hours'=>[0],'days_of_week'=>[1],'days_of_month'=>[1]],
            'ai_provider' => 'groq',
            'ai_api_key' => '',
            'ai_model' => 'llama-3.3-70b-versatile',
            'ai_fallback_model' => 'gemma2-9b-it',
            'default_featured_image' => ''
        ];
        foreach ($defaults as $key => $value) {
            $opt_name = 'writeman_' . $key;
            if (get_option($opt_name) === false) update_option($opt_name, $value);
        }
        $this->schedule_cron();
    }
    public function deactivate() {
        wp_clear_scheduled_hook('writeman_sync_feeds_cron');
        wp_clear_scheduled_hook('writeman_process_queue');
    }
    public function add_cron_schedules($schedules) {
        $schedules['writeman_sync_interval'] = ['interval' => 600, 'display' => 'Cada 10 minutos (sincronizar fuentes)'];
        $schedules['writeman_custom'] = ['interval' => 300, 'display' => 'WriteMan personalizado'];
        $schedules['weekly'] = ['interval' => 604800, 'display' => 'Semanal'];
        $schedules['monthly'] = ['interval' => 2592000, 'display' => 'Mensual'];
        return $schedules;
    }
    public function schedule_cron() {
        $ts_sync = wp_next_scheduled('writeman_sync_feeds_cron');
        if ($ts_sync) wp_unschedule_event($ts_sync, 'writeman_sync_feeds_cron');
        $ts_process = wp_next_scheduled('writeman_process_queue');
        if ($ts_process) wp_unschedule_event($ts_process, 'writeman_process_queue');
        wp_schedule_event(time(), 'writeman_sync_interval', 'writeman_sync_feeds_cron');
        $schedule = get_option('writeman_cron_schedule', ['type'=>'interval','minutes'=>5]);
        $type = $schedule['type'];
        if ($type === 'interval') {
            wp_schedule_event(time(), 'writeman_custom', 'writeman_process_queue');
        } elseif ($type === 'daily') {
            $hours = $schedule['hours'] ?? [0];
            foreach ($hours as $hour) {
                $time = strtotime("today $hour:00");
                if (time() > $time) $time = strtotime("tomorrow $hour:00");
                wp_schedule_event($time, 'daily', 'writeman_process_queue');
            }
        } elseif ($type === 'weekly') {
            $days = $schedule['days_of_week'] ?? [1];
            $hours = $schedule['hours'] ?? [0];
            foreach ($days as $day) {
                foreach ($hours as $hour) {
                    $time = strtotime("next sunday +$day days $hour:00");
                    wp_schedule_event($time, 'weekly', 'writeman_process_queue');
                }
            }
        } elseif ($type === 'monthly') {
            $days = $schedule['days_of_month'] ?? [1];
            $hours = $schedule['hours'] ?? [0];
            foreach ($days as $day) {
                foreach ($hours as $hour) {
                    $time = strtotime(date("Y-m-$day $hour:00"));
                    if (time() > $time) $time = strtotime("first day of next month $hour:00");
                    wp_schedule_event($time, 'monthly', 'writeman_process_queue');
                }
            }
        }
    }
    public function cron_sync_feeds() { 
        error_log('WriteMan: Ejecución automática de sincronización de fuentes');
        if (class_exists('WriteMan_Worker')) WriteMan_Worker::populate_queue(); 
    }
    public function cron_process_queue() { 
        error_log('WriteMan: Ejecución automática de procesamiento de cola');
        if (class_exists('WriteMan_Worker')) WriteMan_Worker::process_queue(); 
    }
    public function init() {
        load_plugin_textdomain('writeman', false, dirname(WRITEMAN_PLUGIN_BASENAME) . '/languages');
        if (is_admin()) new WriteMan_Admin();
        add_action('wp_head', function() {
            if (is_single()) {
                echo '<script>document.addEventListener("DOMContentLoaded",()=>{fetch("'.admin_url('admin-ajax.php').'?action=writeman_track_view&post_id='.get_the_ID().'")});</script>';
            }
        });
        add_action('init', function() {
            if (isset($_GET['wm_track'], $_GET['post_id'], $_GET['variant'])) {
                $pid = intval($_GET['post_id']);
                $var = intval($_GET['variant']);
                if (class_exists('WriteMan_Analytics')) WriteMan_Analytics::record_click($pid, $var);
                wp_redirect(remove_query_arg(['wm_track','post_id','variant']));
                exit;
            }
        });
    }
}
WriteMan_Core::get_instance();