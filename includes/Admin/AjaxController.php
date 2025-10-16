<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Services\ImageDuplicator;
use PicPilotMeta\Helpers\Logger;
use PicPilotMeta\Helpers\FilenameGenerator;
use PicPilotMeta\Helpers\MetadataGenerator;

// Import WordPress functions
use function add_action;
use function check_ajax_referer;
use function wp_send_json_error;
use function wp_send_json_success;
use function absint;
use function wp_attachment_is_image;
use function sanitize_text_field;
use function is_wp_error;
use function get_option;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function json_encode;
use function json_decode;
use function get_attached_file;
use function update_post_meta;
use function wp_update_post;
use function current_user_can;
use function error_log;
use function __;

// If this file is called directly, abort
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AjaxController
 * 
 * Handles all AJAX requests for the plugin.
 * This includes image duplication and metadata generation.
 * 
 * @package PicPilotMeta\Admin
 */

// WordPress functions are in global namespace
if (!defined('\ABSPATH')) {
    exit;
}

class AjaxController {

    public static function init(): void {
        add_action('wp_ajax_pic_pilot_duplicate_image', [__CLASS__, 'duplicate_image']);
        add_action('wp_ajax_picpilot_generate_metadata', [__CLASS__, 'generate_metadata']);
        add_action('wp_ajax_picpilot_generate_filename', [__CLASS__, 'wp_ajax_picpilot_generate_filename']);
        add_action('wp_ajax_picpilot_bulk_process', [__CLASS__, 'bulk_process']);
        add_action('wp_ajax_picpilot_get_images_without_alt', [__CLASS__, 'get_images_without_alt']);
        add_action('wp_ajax_picpilot_get_images_without_titles', [__CLASS__, 'get_images_without_titles']);
        add_action('wp_ajax_picpilot_generate_both', [__CLASS__, 'generate_both_metadata']);
        add_action('wp_ajax_picpilot_check_image_usage', [__CLASS__, 'check_image_usage']);
        add_action('wp_ajax_picpilot_rename_filename', [__CLASS__, 'rename_filename']);
        add_action('wp_ajax_picpilot_generate_ai_filename', [__CLASS__, 'generate_ai_filename']);
        add_action('wp_ajax_picpilot_update_attachment_metadata', [__CLASS__, 'update_attachment_metadata']);
    }

    public static function duplicate_image() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $id = \absint($_POST['attachment_id'] ?? 0);
        if (!$id || !\wp_attachment_is_image($id)) {
            \wp_send_json_error(['message' => 'Invalid image ID.']);
        }

        $new_title = \sanitize_text_field($_POST['new_title'] ?? '');
        $new_alt = \sanitize_text_field($_POST['new_alt'] ?? '');
        $new_filename = \sanitize_text_field($_POST['new_filename'] ?? '');
        $keywords = \sanitize_text_field($_POST['keywords'] ?? '');

        try {
            $_POST['update_original'] = 'false'; // Prevent updating original image

            // Smart generation features are now always enabled
            $title_enabled = true;
            $alt_enabled = true;
            $filename_enabled = true;

            if ($new_title === 'generate' && $title_enabled) {
                $title_result = MetadataGenerator::generate($id, 'title', $keywords);
                if (\is_wp_error($title_result)) {
                    Logger::log('[DUPLICATE] Title generation failed: ' . $title_result->get_error_message());
                    throw new \Exception('Title generation failed: ' . $title_result->get_error_message());
                }

                // MetadataGenerator now returns string directly
                $new_title = $title_result;

                if (empty($new_title)) {
                    Logger::log('[DUPLICATE] Title generation returned empty result');
                    throw new \Exception('AI returned an empty title.');
                }
            } elseif ($new_title === 'generate' && !$title_enabled) {
                Logger::log('[DUPLICATE] Title generation requested but feature is disabled in settings');
                throw new \Exception('Title generation is disabled in plugin settings.');
            }

            if ($new_alt === 'generate' && $alt_enabled) {
                $alt_result = MetadataGenerator::generate($id, 'alt', $keywords);
                if (\is_wp_error($alt_result)) {
                    Logger::log('[DUPLICATE] Alt generation failed: ' . $alt_result->get_error_message());
                    throw new \Exception('Alt text generation failed: ' . $alt_result->get_error_message());
                }

                // MetadataGenerator now returns string directly
                $new_alt = $alt_result;

                if (empty($new_alt)) {
                    Logger::log('[DUPLICATE] Alt generation returned empty result');
                    throw new \Exception('AI returned an empty alt text.');
                }
            } elseif ($new_alt === 'generate' && !$alt_enabled) {
                Logger::log('[DUPLICATE] Alt generation requested but feature is disabled in settings');
                throw new \Exception('Alt text generation is disabled in plugin settings.');
            }

            if ($new_filename === 'generate' && $filename_enabled) {
                $new_filename = FilenameGenerator::generate($id, $keywords);
                if (\is_wp_error($new_filename)) {
                    Logger::log('[DUPLICATE] Filename generation failed: ' . $new_filename->get_error_message());
                    throw new \Exception('Filename generation failed: ' . $new_filename->get_error_message());
                }
                if (empty($new_filename)) {
                    Logger::log('[DUPLICATE] Filename generation returned empty result');
                    throw new \Exception('AI returned an empty filename.');
                }
            } elseif ($new_filename === 'generate' && !$filename_enabled) {
                Logger::log('[DUPLICATE] Filename generation requested but feature is disabled in settings');
                throw new \Exception('Filename generation is disabled in plugin settings.');
            }

            // Create duplicate with generated or provided metadata
            $new_id = ImageDuplicator::duplicate($id, $new_title ?: null, $new_filename ?: null, $new_alt ?: null);

            if (!$new_id) {
                throw new \Exception('Failed to duplicate image.');
            }

            Logger::log("[DUPLICATE] Created #$new_id from #$id | Title: $new_title | Alt: $new_alt | Filename: $new_filename | Keywords: $keywords");
            \wp_send_json_success(['id' => $new_id]);
        } catch (\Throwable $e) {
            Logger::log('[DUPLICATE] Error: ' . $e->getMessage());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function wp_ajax_picpilot_generate_filename() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $id = \absint($_POST['attachment_id'] ?? 0);
        $keywords = \sanitize_text_field($_POST['keywords'] ?? '');

        if (!$id || !\wp_attachment_is_image($id)) {
            \wp_send_json_error(['message' => \__('Invalid image ID.', 'pic-pilot-meta')]);
        }

        $result = FilenameGenerator::generate($id, $keywords);
        if (\is_wp_error($result)) {
            Logger::log('[FILENAME] Error: ' . $result->get_error_message());
            \wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (empty($result)) {
            Logger::log('[FILENAME] Empty result');
            \wp_send_json_error(['message' => 'Generated filename is empty']);
        }

        Logger::log("[FILENAME] ID $id - Keywords: '$keywords' - Generated: $result");
        \wp_send_json_success(['filename' => $result]);
    }

    public static function generate_metadata() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $id = \absint($_POST['attachment_id'] ?? 0);
        $type = \sanitize_text_field($_POST['type'] ?? 'alt');
        $keywords = \sanitize_text_field($_POST['keywords'] ?? '');

        // Debug logging
        $js_version = \sanitize_text_field($_POST['js_version'] ?? 'unknown');
        Logger::log("[AJAX] generate_metadata called - ID: $id, Type: $type, Keywords: '$keywords', JS Version: $js_version");
        Logger::log("[AJAX] Raw POST data: " . json_encode($_POST));

        // Server-side request deduplication
        $request_key = "picpilot_gen_{$id}_{$type}_" . md5($keywords);
        $active_requests = \get_transient('picpilot_active_requests') ?: [];
        
        if (isset($active_requests[$request_key]) && (time() - $active_requests[$request_key]) < 30) {
            Logger::log("[AJAX] Duplicate request blocked - Key: $request_key, JS Version: $js_version");
            // Return success with a message instead of error to avoid showing error to user
            \wp_send_json_success([
                'type' => $type,
                'result' => 'Generation already in progress',
                'shouldUpdate' => false,
                'duplicate_blocked' => true
            ]);
            return;
        }
        
        // Mark request as active
        $active_requests[$request_key] = time();
        \set_transient('picpilot_active_requests', $active_requests, 60);
        Logger::log("[AJAX] Request marked as active - Key: $request_key, JS Version: $js_version");

        if (!$id || !\wp_attachment_is_image($id)) {
            // Clean up on error
            unset($active_requests[$request_key]);
            \set_transient('picpilot_active_requests', $active_requests, 60);
            return self::log_and_fail($id, 'Invalid image ID');
        }

        $settings = \get_option('picpilot_meta_settings', []);
        $provider = $settings['ai_provider'] ?? 'openai';
        
        if ($provider === 'gemini') {
            $api_key = $settings['gemini_api_key'] ?? '';
            if (empty($api_key)) {
                return self::log_and_fail($id, 'Missing Gemini API key.');
            }
        } else {
            $api_key = $settings['openai_api_key'] ?? '';
            if (empty($api_key)) {
                return self::log_and_fail($id, 'Missing OpenAI API key.');
            }
        }

        // Use the unified MetadataGenerator instead of duplicate logic
        Logger::log("[AJAX] Calling MetadataGenerator::generate with keywords: '$keywords'");
        $result = MetadataGenerator::generate($id, $type, $keywords);

        if (\is_wp_error($result)) {
            Logger::log("[{$type}] Generation failed: " . $result->get_error_message());
            return self::log_and_fail($id, $result->get_error_message());
        }

        // MetadataGenerator now returns string directly
        $content = $result;

        if (empty($content)) {
            Logger::log("[{$type}] Empty content from generator");
            return self::log_and_fail($id, 'Empty result from AI generation');
        }

        // Update the actual attachment with the generated metadata
        if ($type === 'alt') {
            \update_post_meta($id, '_wp_attachment_image_alt', $content);
            Logger::log("[{$type}] Updated alt text for image ID: $id");
        } elseif ($type === 'title') {
            \wp_update_post(['ID' => $id, 'post_title' => $content]);
            Logger::log("[{$type}] Updated title for image ID: $id");
        }

        // Prepare success response
        $response_data = [
            'type' => $type,
            'result' => $content,
            'shouldUpdate' => true,
        ];

        Logger::log("[SUCCESS] [$type] Image ID: $id, Keywords: '$keywords', Result: " . substr($content, 0, 100));
        Logger::log("[SUCCESS] Response data being sent: " . json_encode($response_data));

        // Clean up active request
        $active_requests = \get_transient('picpilot_active_requests') ?: [];
        unset($active_requests[$request_key]);
        \set_transient('picpilot_active_requests', $active_requests, 60);
        Logger::log("[AJAX] Request completed and cleaned up - Key: $request_key, JS Version: $js_version");

        \wp_send_json_success($response_data);
    }

    public static function log_and_fail($id, $message) {
        // Clean up active request on error if we have the context
        if (isset($_POST['type']) && isset($_POST['keywords'])) {
            $type = \sanitize_text_field($_POST['type']);
            $keywords = \sanitize_text_field($_POST['keywords']);
            $request_key = "picpilot_gen_{$id}_{$type}_" . md5($keywords);
            $active_requests = \get_transient('picpilot_active_requests') ?: [];
            unset($active_requests[$request_key]);
            \set_transient('picpilot_active_requests', $active_requests, 60);
            Logger::log("[AJAX] Request failed and cleaned up - Key: $request_key");
        }
        
        Logger::log("[ERROR] Image ID: $id, Reason: $message");
        \wp_send_json_error($message);
        exit;
    }

    /**
     * Handle bulk processing request
     */
    public static function bulk_process() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $bulk_key = \sanitize_text_field($_POST['bulk_key'] ?? '');
        $generate_title = $_POST['generate_title'] === '1';
        $generate_alt = $_POST['generate_alt'] === '1';

        if (empty($bulk_key)) {
            \wp_send_json_error(['message' => 'Invalid bulk key']);
        }

        // Get image IDs from transient
        $image_ids = \get_transient($bulk_key);
        if (!$image_ids || !is_array($image_ids)) {
            \wp_send_json_error(['message' => 'Bulk operation expired or invalid']);
        }

        // Validate that user can edit these attachments
        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Filter to ensure all IDs are valid images
        $valid_image_ids = [];
        foreach ($image_ids as $id) {
            if (\wp_attachment_is_image($id)) {
                $valid_image_ids[] = $id;
            }
        }

        if (empty($valid_image_ids)) {
            \wp_send_json_error(['message' => 'No valid image attachments found']);
        }

        Logger::log("[BULK] Starting bulk processing for " . count($valid_image_ids) . " images. Title: " . ($generate_title ? 'yes' : 'no') . ", Alt: " . ($generate_alt ? 'yes' : 'no'));

        // Delete the transient as it's no longer needed
        \delete_transient($bulk_key);

        \wp_send_json_success([
            'image_ids' => $valid_image_ids,
            'message' => 'Bulk processing initiated'
        ]);
    }

    /**
     * Get images without alt text
     */
    public static function get_images_without_alt() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Query for image attachments without alt text
        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);

        $image_ids = $query->posts;

        Logger::log("[BULK] Found " . count($image_ids) . " images without alt text");

        \wp_send_json_success([
            'image_ids' => $image_ids,
            'count' => count($image_ids)
        ]);
    }

    /**
     * Get images without titles (or with default titles)
     */
    public static function get_images_without_titles() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Query for image attachments with missing or default titles
        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        $image_ids = [];

        // Filter images with missing or default titles
        foreach ($query->posts as $attachment_id) {
            $title = \get_the_title($attachment_id);
            $filename = \get_post_meta($attachment_id, '_wp_attached_file', true);
            $basename = basename($filename, '.' . pathinfo($filename, PATHINFO_EXTENSION));

            // Check if title is empty, matches filename, or is a generic pattern
            if (
                empty($title) ||
                $title === $basename ||
                $title === $filename ||
                preg_match('/^(img|image|photo|picture)[-_]?\d*$/i', $title) ||
                preg_match('/^untitled[-_]?\d*$/i', $title)
            ) {
                $image_ids[] = $attachment_id;
            }
        }

        Logger::log("[BULK] Found " . count($image_ids) . " images without proper titles");

        \wp_send_json_success([
            'image_ids' => $image_ids,
            'count' => count($image_ids)
        ]);
    }

    /**
     * Generate both alt text and title for an image
     */
    public static function generate_both_metadata() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $id = \absint($_POST['attachment_id'] ?? 0);
        $keywords = \sanitize_text_field($_POST['keywords'] ?? '');

        if (!$id || !\wp_attachment_is_image($id)) {
            \wp_send_json_error(['message' => \__('Invalid image ID.', 'pic-pilot-meta')]);
        }

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Check if both generation features are enabled
        $settings = \get_option('picpilot_meta_settings', []);
        $both_enabled = $settings['enable_auto_generate_both'] ?? false;
        // Smart generation features are now always enabled
        $alt_enabled = true;
        $title_enabled = true;

        if (!$both_enabled) {
            \wp_send_json_error(['message' => 'Auto-generate both feature is disabled in settings']);
        }

        if (!$alt_enabled || !$title_enabled) {
            \wp_send_json_error(['message' => 'Both alt text and title generation must be enabled in settings']);
        }

        try {
            // Generate alt text
            $alt_result = MetadataGenerator::generate($id, 'alt', $keywords);
            if (\is_wp_error($alt_result)) {
                throw new \Exception('Alt text generation failed: ' . $alt_result->get_error_message());
            }

            // Generate title
            $title_result = MetadataGenerator::generate($id, 'title', $keywords);
            if (\is_wp_error($title_result)) {
                throw new \Exception('Title generation failed: ' . $title_result->get_error_message());
            }

            // Update the attachment
            \update_post_meta($id, '_wp_attachment_image_alt', $alt_result);
            \wp_update_post(['ID' => $id, 'post_title' => $title_result]);

            Logger::log("[GENERATE_BOTH] Updated image $id - Alt: '$alt_result', Title: '$title_result'");

            \wp_send_json_success([
                'alt_result' => $alt_result,
                'title_result' => $title_result,
                'message' => 'Both alt text and title generated successfully'
            ]);
        } catch (\Throwable $e) {
            Logger::log('[GENERATE_BOTH] Error: ' . $e->getMessage());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Check where an image is being used
     */
    public static function check_image_usage() {
        Logger::log('[USAGE_CHECK] Starting usage check. POST data: ' . json_encode($_POST));
        
        try {
            \check_ajax_referer('picpilot_studio_generate', 'nonce');
        } catch (\Exception $e) {
            Logger::log('[USAGE_CHECK] Nonce verification failed: ' . $e->getMessage());
            \wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        $id = \absint($_POST['attachment_id'] ?? 0);
        Logger::log("[USAGE_CHECK] Checking usage for attachment ID: $id");

        if (!$id || !\wp_attachment_is_image($id)) {
            Logger::log("[USAGE_CHECK] Invalid image ID: $id");
            \wp_send_json_error(['message' => \__('Invalid image ID.', 'pic-pilot-meta')]);
        }

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        global $wpdb;
        
        $usage = [];
        $image_url = \wp_get_attachment_url($id);
        $attachment_data = \get_post($id);
        
        if (!$attachment_data) {
            \wp_send_json_error(['message' => 'Image not found']);
        }

        // Check if it's a featured image
        $featured_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d AND p.post_status = 'publish'",
            $id
        ));

        foreach ($featured_posts as $post) {
            $usage[] = [
                'type' => 'Featured Image',
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_id' => $post->ID,
                'edit_url' => \get_edit_post_link($post->ID)
            ];
        }

        // Check content references by URL
        $filename = basename($image_url);
        $posts_with_content = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts} 
             WHERE post_content LIKE %s AND post_status = 'publish'",
            '%' . $wpdb->esc_like($filename) . '%'
        ));

        foreach ($posts_with_content as $post) {
            $usage[] = [
                'type' => 'Content Reference',
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_id' => $post->ID,
                'edit_url' => \get_edit_post_link($post->ID)
            ];
        }

        // Check shortcodes and gallery references
        $gallery_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts} 
             WHERE post_content LIKE %s AND post_status = 'publish'",
            '%ids=' . $id . '%'
        ));

        foreach ($gallery_posts as $post) {
            $usage[] = [
                'type' => 'Gallery/Shortcode',
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_id' => $post->ID,
                'edit_url' => \get_edit_post_link($post->ID)
            ];
        }

        $is_safe_to_rename = empty($usage);
        
        Logger::log("[USAGE_CHECK] Completed for ID $id. Usage count: " . count($usage) . ", Safe to rename: " . ($is_safe_to_rename ? 'yes' : 'no'));
        
        \wp_send_json_success([
            'usage' => $usage,
            'is_safe_to_rename' => $is_safe_to_rename,
            'usage_count' => count($usage),
            'current_filename' => basename(\get_attached_file($id))
        ]);
    }

    /**
     * Rename image filename (dangerous operation)
     */
    public static function rename_filename() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        $id = \absint($_POST['attachment_id'] ?? 0);
        $new_filename = \sanitize_file_name($_POST['new_filename'] ?? '');
        $force_rename = $_POST['force_rename'] === 'true';

        if (!$id || !\wp_attachment_is_image($id)) {
            \wp_send_json_error(['message' => \__('Invalid image ID.', 'pic-pilot-meta')]);
        }

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Check if dangerous renaming is enabled
        $settings = \get_option('picpilot_meta_settings', []);
        $rename_enabled = $settings['enable_dangerous_filename_rename'] ?? false;

        if (!$rename_enabled) {
            \wp_send_json_error(['message' => 'Dangerous filename renaming is disabled in settings']);
        }

        if (empty($new_filename)) {
            \wp_send_json_error(['message' => 'New filename cannot be empty']);
        }

        try {
            $current_file = \get_attached_file($id);
            $current_filename = basename($current_file);
            $file_extension = pathinfo($current_file, PATHINFO_EXTENSION);
            
            // Ensure new filename has correct extension
            $new_filename_with_ext = pathinfo($new_filename, PATHINFO_EXTENSION) ? 
                $new_filename : $new_filename . '.' . $file_extension;

            if ($current_filename === $new_filename_with_ext) {
                \wp_send_json_error(['message' => 'New filename is the same as current filename']);
            }

            $upload_dir = \wp_upload_dir();
            $old_path = $current_file;
            $new_path = dirname($current_file) . '/' . $new_filename_with_ext;

            // Check if new filename already exists
            if (file_exists($new_path)) {
                \wp_send_json_error(['message' => 'A file with this name already exists']);
            }

            // Check usage unless forced
            if (!$force_rename) {
                // This is a quick usage check - the frontend should have already done the full check
                global $wpdb;
                $usage_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
                    $id
                ));
                
                if ($usage_count > 0) {
                    \wp_send_json_error([
                        'message' => 'Image is in use. Use force_rename=true to proceed anyway.',
                        'requires_force' => true
                    ]);
                }
            }

            // Rename the physical file
            if (!rename($old_path, $new_path)) {
                throw new \Exception('Failed to rename physical file');
            }

            // Update database records
            \update_post_meta($id, '_wp_attached_file', str_replace($upload_dir['basedir'] . '/', '', $new_path));
            
            // Update any size variations
            $metadata = \wp_get_attachment_metadata($id);
            if ($metadata && isset($metadata['sizes'])) {
                $old_dir = dirname($old_path);
                $old_basename = pathinfo($current_filename, PATHINFO_FILENAME);
                $new_basename = pathinfo($new_filename_with_ext, PATHINFO_FILENAME);
                
                foreach ($metadata['sizes'] as $size => $size_data) {
                    $old_size_file = $old_dir . '/' . $old_basename . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $file_extension;
                    $new_size_file = $old_dir . '/' . $new_basename . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $file_extension;
                    
                    if (file_exists($old_size_file)) {
                        rename($old_size_file, $new_size_file);
                        $metadata['sizes'][$size]['file'] = basename($new_size_file);
                    }
                }
                
                $metadata['file'] = str_replace($upload_dir['basedir'] . '/', '', $new_path);
                \wp_update_attachment_metadata($id, $metadata);
            }

            Logger::log("[RENAME] Renamed image $id from '$current_filename' to '$new_filename_with_ext'");

            \wp_send_json_success([
                'message' => 'Filename renamed successfully',
                'old_filename' => $current_filename,
                'new_filename' => $new_filename_with_ext,
                'new_url' => \wp_get_attachment_url($id)
            ]);
        } catch (\Throwable $e) {
            Logger::log('[RENAME] Error: ' . $e->getMessage());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Generate AI filename for an attachment
     */
    public static function generate_ai_filename() {
        \check_ajax_referer('picpilot_studio_generate', 'nonce');

        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Permission denied']);
        }

        $id = \absint($_POST['attachment_id'] ?? 0);
        if (!$id || !\wp_attachment_is_image($id)) {
            \wp_send_json_error(['message' => 'Invalid image ID']);
        }

        $keywords = \sanitize_text_field($_POST['keywords'] ?? '');

        // Check if dangerous filename rename is enabled
        $settings = \get_option('picpilot_meta_settings', []);
        $rename_enabled = $settings['enable_dangerous_filename_rename'] ?? false;

        if (!$rename_enabled) {
            \wp_send_json_error(['message' => 'Dangerous filename renaming is disabled in settings']);
        }

        try {
            $filename = FilenameGenerator::generate($id, $keywords);
            
            if (\is_wp_error($filename)) {
                Logger::log('[AI_FILENAME] Generation failed: ' . $filename->get_error_message());
                \wp_send_json_error(['message' => $filename->get_error_message()]);
            }

            if (empty($filename)) {
                Logger::log('[AI_FILENAME] Generation returned empty result');
                \wp_send_json_error(['message' => 'AI returned an empty filename']);
            }

            Logger::log("[AI_FILENAME] Generated filename for image $id: '$filename' (keywords: '$keywords')");

            \wp_send_json_success([
                'filename' => $filename,
                'message' => 'Filename generated successfully'
            ]);
        } catch (\Throwable $e) {
            Logger::log('[AI_FILENAME] Error: ' . $e->getMessage());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Update attachment metadata (for selective upload manual edits)
     */
    public static function update_attachment_metadata() {
        \check_ajax_referer('picpilot_selective_upload', 'nonce');
        
        if (!\current_user_can('upload_files')) {
            \wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $attachment_id = \absint($_POST['attachment_id'] ?? 0);
        $type = \sanitize_text_field($_POST['type'] ?? '');
        $value = \sanitize_text_field($_POST['value'] ?? '');
        
        if (!$attachment_id || !\wp_attachment_is_image($attachment_id)) {
            \wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        if (!in_array($type, ['alt', 'title'])) {
            \wp_send_json_error(['message' => 'Invalid metadata type']);
        }
        
        try {
            if ($type === 'alt') {
                \update_post_meta($attachment_id, '_wp_attachment_image_alt', $value);
            } elseif ($type === 'title') {
                \wp_update_post(['ID' => $attachment_id, 'post_title' => $value]);
            }
            
            Logger::log("[SELECTIVE_UPDATE] Updated {$type} for attachment #{$attachment_id}: '{$value}'");
            \wp_send_json_success(['message' => 'Metadata updated successfully']);
            
        } catch (\Throwable $e) {
            Logger::log('[SELECTIVE_UPDATE] Error: ' . $e->getMessage());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
