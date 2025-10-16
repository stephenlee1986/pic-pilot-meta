<?php

/**
 * Plugin Name: Pic Pilot Meta
 * Plugin URI: https://wordpress.org/plugins/pic-pilot-meta/
 * Description: AI-powered image metadata generation for WordPress. Automatically create SEO-optimized titles, alt text, and duplicate images with intelligent AI assistance using OpenAI GPT-4o or Google Gemini.
 * Version: 2.2.3
 * Author: Stephen Lee Hernandez
 * Author URI: https://profiles.wordpress.org/stephenhernandez/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pic-pilot-meta
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// Manual autoloader for plugin classes since we removed Composer
spl_autoload_register(function ($class) {
    // Only autoload our plugin classes
    if (strpos($class, 'PicPilotMeta\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $file = __DIR__ . '/includes/' . str_replace(['PicPilotMeta\\', '\\'], ['', '/'], $class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Ensure the Plugin class exists before using it
if (class_exists('PicPilotMeta\Plugin')) {
    \PicPilotMeta\Plugin::init();

    // Register activation hook
    register_activation_hook(__FILE__, ['PicPilotMeta\Plugin', 'activate_plugin']);
} else {
    error_log('PicPilotMeta\Plugin class not found. Please check autoloading and class definition.');
}

define('PIC_PILOT_META_URL', plugin_dir_url(__FILE__));
define('PIC_PILOT_META_PATH', plugin_dir_path(__FILE__));
define('PIC_PILOT_META_VERSION', '2.2.0');

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style(
        'pic-pilot-meta-global',
        plugin_dir_url(__FILE__) . 'assets/css/pic-pilot-meta.css',
        [],
        PIC_PILOT_META_VERSION
    );
});

// Add pic-pilot-meta class to WordPress admin body for CSS scoping
add_action('admin_body_class', function ($classes) {
    return $classes . ' pic-pilot-meta';
});



add_filter('big_image_size_threshold', '__return_false');
