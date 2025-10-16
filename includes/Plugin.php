<?php

namespace PicPilotMeta;

use PicPilotMeta\Admin\AjaxController;
use PicPilotMeta\Admin\MediaList;
use PicPilotMeta\Admin\Settings;
use PicPilotMeta\Admin\AttachmentFields;
use PicPilotMeta\Admin\ImageTags;
use PicPilotMeta\Admin\DashboardController;
use PicPilotMeta\Admin\ScanController;
use PicPilotMeta\Admin\ExportController;
use PicPilotMeta\Admin\DatabaseManager;
use PicPilotMeta\Admin\SelectiveUpload;
use PicPilotMeta\Helpers\Logger;
use PicPilotMeta\Helpers\MetadataGenerator;
use PicPilotMeta\Helpers\PromptManager;

defined('ABSPATH') || exit;

class Plugin {
    public static function init() {
        // Note: load_plugin_textdomain() is no longer needed for WordPress.org hosted plugins (WP 4.6+)
        // WordPress automatically loads translations from wordpress.org

        // Register admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);

        // Initialize plugin modules
        if (is_admin()) {
            AjaxController::init();
            MediaList::init();
            Settings::init(); // This will handle admin_init and settings logic
            AttachmentFields::init(); // Add AI tools to image edit screens
            ImageTags::init(); // Initialize image tagging system
            DashboardController::init(); // Initialize dashboard functionality
            ScanController::init(); // Initialize scanning functionality
            ExportController::init(); // Initialize export functionality
            SelectiveUpload::init(); // Initialize selective upload functionality
            
            // Initialize default advanced prompt settings
            self::init_default_prompt_settings();
        }
        
        // Initialize upload handlers
        self::init_upload_handlers();
    }

    public static function register_admin_page() {
        add_menu_page(
            'Pic Pilot Meta',
            'Pic Pilot Meta',
            'manage_options',
            'pic-pilot-meta',
            [Settings::class, 'render_settings_page'],
            self::get_menu_icon()
        );

        // Use JavaScript to override WordPress's !important styles after page load
        add_action('admin_footer', [__CLASS__, 'fix_menu_icon_js']);
    }

    /**
     * Use JavaScript to remove WordPress's !important override
     */
    public static function fix_menu_icon_js() {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const iconElement = document.querySelector("#adminmenu .toplevel_page_pic-pilot-meta .wp-menu-image");
                if (iconElement) {
                    // Remove the style attribute that WordPress adds with !important
                    iconElement.removeAttribute("style");
                    // Set our custom background image
                    iconElement.style.backgroundImage = "url(\'' . esc_js(self::get_menu_icon()) . '\')";
                    iconElement.style.backgroundSize = "20px 20px";
                    iconElement.style.backgroundRepeat = "no-repeat";
                    iconElement.style.backgroundPosition = "center";
                }
            });
        </script>';
    }

    /**
     * Get custom menu icon as base64 encoded SVG
     */
    private static function get_menu_icon() {
        // Try using your actual PNG icon instead of SVG to avoid WordPress manipulation
        $icon_path = PIC_PILOT_META_PATH . '.wordpress-org/icon-128x128.png';

        if (file_exists($icon_path)) {
            $image_data = file_get_contents($icon_path);
            return 'data:image/png;base64,' . base64_encode($image_data);
        }

        // Fallback: Try a Dashicon that looks similar
        return 'dashicons-businessman';
    }

    public static function init_upload_handlers() {
        add_action('add_attachment', [__CLASS__, 'handle_new_attachment']);
        add_action('picpilot_generate_upload_metadata', [__CLASS__, 'generate_upload_metadata'], 10, 3);
    }

    public static function handle_new_attachment($attachment_id) {
        // Add basic logging to debug
        Logger::log("[UPLOAD] handle_new_attachment called for ID: $attachment_id");
        
        // Only process images
        if (!wp_attachment_is_image($attachment_id)) {
            Logger::log("[UPLOAD] Skipping non-image attachment ID: $attachment_id");
            return;
        }

        $settings = get_option('picpilot_meta_settings', []);
        $auto_generate_alt = $settings['auto_generate_metadata_on_upload'] ?? false;
        $auto_generate_title = $settings['auto_generate_title_on_upload'] ?? false;

        Logger::log("[UPLOAD] Settings - Alt: " . ($auto_generate_alt ? 'enabled' : 'disabled') . ", Title: " . ($auto_generate_title ? 'enabled' : 'disabled'));

        if (!$auto_generate_alt && !$auto_generate_title) {
            Logger::log("[UPLOAD] No auto-generation features enabled, skipping");
            return;
        }

        // Process immediately instead of scheduling
        Logger::log("[UPLOAD] Processing metadata generation for ID: $attachment_id");
        self::generate_upload_metadata($attachment_id, $auto_generate_alt, $auto_generate_title);
    }

    public static function generate_upload_metadata($attachment_id, $generate_alt, $generate_title) {
        $updated = false;

        if ($generate_alt) {
            // Check if alt text is missing or empty
            $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (empty($current_alt)) {
                $alt_result = MetadataGenerator::generate($attachment_id, 'alt', '');
                if (!is_wp_error($alt_result)) {
                    $alt_content = is_array($alt_result) ? $alt_result['content'] : $alt_result;
                    if (!empty($alt_content)) {
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_content);
                        $updated = true;
                        Logger::log("[UPLOAD] Auto-generated alt text for image ID: $attachment_id");
                    }
                }
            }
        }

        if ($generate_title) {
            // Generate a better title
            $title_result = MetadataGenerator::generate($attachment_id, 'title', '');
            if (!is_wp_error($title_result)) {
                $title_content = is_array($title_result) ? $title_result['content'] : $title_result;
                if (!empty($title_content)) {
                    wp_update_post([
                        'ID' => $attachment_id,
                        'post_title' => $title_content
                    ]);
                    $updated = true;
                    Logger::log("[UPLOAD] Auto-generated title for image ID: $attachment_id");
                }
            }
        }

        if ($updated) {
            Logger::log("[UPLOAD] Auto-generation completed for image ID: $attachment_id");
        }
    }
    
    /**
     * Initialize default advanced prompt settings if they don't exist
     */
    private static function init_default_prompt_settings() {
        $existing_settings = get_option('picpilot_meta_settings', []);
        $default_settings = PromptManager::getDefaultSettings();
        $needs_update = false;
        
        foreach ($default_settings as $key => $default_value) {
            if (!isset($existing_settings[$key])) {
                $existing_settings[$key] = $default_value;
                $needs_update = true;
            }
        }
        
        if ($needs_update) {
            update_option('picpilot_meta_settings', $existing_settings);
            Logger::log("[PLUGIN] Initialized default advanced prompt settings");
        }
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate_plugin() {
        // Create database tables
        DatabaseManager::create_tables();
        
        // Set database version
        update_option(DatabaseManager::DB_VERSION_OPTION, DatabaseManager::DB_VERSION);
        
        Logger::log("[PLUGIN] Plugin activated - database tables created");
    }

}
