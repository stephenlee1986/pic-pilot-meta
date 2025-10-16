<?php

namespace PicPilotMeta\Helpers;

defined('ABSPATH') || exit;

class OptimizationCompatibility {

    /**
     * Temporarily disable known optimization plugins during file operations
     */
    public static function disable_optimization_plugins() {
        // Store original states
        $original_states = [];

        // Common WebP/optimization plugins that might interfere
        $plugins_to_disable = [
            'wp_optimize_webp_conversion',
            'imagify_auto_optimize',
            'smush_auto_smush',
            'shortpixel_auto_media_library',
            'ewww_image_optimizer_auto',
            'optimole_auto_optimization'
        ];

        foreach ($plugins_to_disable as $filter) {
            if (has_filter($filter)) {
                $original_states[$filter] = true;
                remove_all_filters($filter);
            }
        }

        // Temporarily disable WebP generation during upload
        add_filter('wp_generate_attachment_metadata', [self::class, 'defer_webp_generation'], 1, 2);

        return $original_states;
    }

    /**
     * Re-enable optimization plugins after file operations
     */
    public static function restore_optimization_plugins($original_states) {
        // Remove our temporary filter
        remove_filter('wp_generate_attachment_metadata', [self::class, 'defer_webp_generation'], 1);

        // Note: We don't restore the filters as they'll be re-added by the plugins themselves
        Logger::log('[OPTIMIZATION] Restored optimization plugin states');
    }

    /**
     * Defer WebP generation to avoid conflicts during rename
     */
    public static function defer_webp_generation($metadata, $attachment_id) {
        // Mark this attachment for later WebP processing
        update_post_meta($attachment_id, '_picpilot_defer_webp', true);

        Logger::log("[OPTIMIZATION] Deferred WebP generation for attachment #{$attachment_id}");

        return $metadata;
    }

    /**
     * Process deferred WebP generation after rename is complete
     */
    public static function process_deferred_webp($attachment_id) {
        if (get_post_meta($attachment_id, '_picpilot_defer_webp', true)) {
            delete_post_meta($attachment_id, '_picpilot_defer_webp');

            // Trigger WebP generation now that rename is complete
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                // Re-generate metadata to trigger optimization plugins
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $metadata);

                Logger::log("[OPTIMIZATION] Processed deferred WebP generation for attachment #{$attachment_id}");
            }
        }
    }

    /**
     * Check if file is currently locked by optimization plugins
     */
    public static function is_file_locked($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        // Try to open file for writing to check if it's locked
        $handle = @fopen($file_path, 'r+');
        if ($handle === false) {
            return true;
        }

        $locked = !@flock($handle, LOCK_EX | LOCK_NB);
        if (!$locked) {
            @flock($handle, LOCK_UN);
        }
        @fclose($handle);

        return $locked;
    }

    /**
     * Wait for file lock to be released with timeout
     */
    public static function wait_for_file_unlock($file_path, $timeout_seconds = 5) {
        $start_time = time();
        $check_interval = 0.1; // Check every 100ms

        while (time() - $start_time < $timeout_seconds) {
            if (!self::is_file_locked($file_path)) {
                return true;
            }
            usleep($check_interval * 1000000);
        }

        Logger::log("[OPTIMIZATION] File lock timeout reached for: {$file_path}");
        return false;
    }
}