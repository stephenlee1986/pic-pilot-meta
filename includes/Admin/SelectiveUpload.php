<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\MetadataGenerator;
use PicPilotMeta\Helpers\FilenameGenerator;
use PicPilotMeta\Helpers\Logger;
use PicPilotMeta\Helpers\OptimizationCompatibility;

defined('ABSPATH') || exit;

class SelectiveUpload {
    
    public static function init() {
        // Only initialize if the setting is enabled
        if (!Settings::get('enable_selective_upload_area')) {
            return;
        }
        
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_picpilot_selective_upload', [__CLASS__, 'handle_selective_upload']);
        add_action('wp_ajax_picpilot_automatic_upload', [__CLASS__, 'handle_automatic_upload']);
        add_action('wp_ajax_picpilot_process_selective_ai', [__CLASS__, 'handle_selective_ai_processing']);
    }
    
    public static function add_admin_menu() {
        add_submenu_page(
            'upload.php',
            __('AI Upload Area', 'pic-pilot-meta'),
            __('🤖 AI Upload', 'pic-pilot-meta'),
            'upload_files',
            'picpilot-selective-upload',
            [__CLASS__, 'render_upload_page']
        );
    }
    
    public static function enqueue_scripts($hook) {
        if ('media_page_picpilot-selective-upload' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('wp-plupload');
        
        wp_enqueue_script(
            'picpilot-selective-upload',
            PIC_PILOT_META_URL . 'assets/js/selective-upload.js',
            ['jquery', 'wp-plupload', 'jquery-ui-droppable'],
            PIC_PILOT_META_VERSION,
            true
        );
        
        wp_enqueue_style(
            'picpilot-selective-upload-style',
            PIC_PILOT_META_URL . 'assets/css/selective-upload.css',
            [],
            PIC_PILOT_META_VERSION
        );
        
        wp_localize_script('picpilot-selective-upload', 'picpilotSelectiveUpload', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('picpilot_selective_upload'),
            'strings' => [
                'uploadFiles' => __('Drop files here or click to upload', 'pic-pilot-meta'),
                'processing' => __('Processing...', 'pic-pilot-meta'),
                'generateAlt' => __('Generate Alt Text', 'pic-pilot-meta'),
                'generateTitle' => __('Generate Title', 'pic-pilot-meta'),
                'generateFilename' => __('Generate Filename', 'pic-pilot-meta'),
                'generating' => __('Generating...', 'pic-pilot-meta'),
                'success' => __('Generated successfully!', 'pic-pilot-meta'),
                'error' => __('Error generating content', 'pic-pilot-meta'),
                'uploadError' => __('Upload failed', 'pic-pilot-meta'),
                'keywords' => __('Keywords (optional)', 'pic-pilot-meta'),
            ]
        ]);
    }
    
    public static function render_upload_page() {
        ?>
        <div class="wrap picpilot-selective-upload">
            <h1><?php esc_html_e('AI-Powered Upload Areas', 'pic-pilot-meta'); ?></h1>
            <p class="description">
                <?php esc_html_e('Choose between automatic AI processing or selective control. Both upload areas support the same file types and quality.', 'pic-pilot-meta'); ?>
            </p>
            
            <div class="upload-areas-container">
                <!-- Automatic AI Upload Area -->
                <div class="upload-area-section">
                    <h2 class="section-title">
                        <span class="icon">🪄</span>
                        <?php esc_html_e('Automatic AI Processing', 'pic-pilot-meta'); ?>
                    </h2>
                    <p class="section-description">
                        <?php esc_html_e('Upload and forget! AI will automatically generate alt text, titles, and intelligent filenames. Perfect for quick bulk uploads with complete optimization.', 'pic-pilot-meta'); ?>
                    </p>
                    
                    <div class="picpilot-upload-area" id="automatic-upload-area">
                        <div class="upload-zone automatic-zone" id="automatic-upload-zone">
                            <div class="upload-icon">🪄</div>
                            <h3><?php esc_html_e('Automatic AI Upload', 'pic-pilot-meta'); ?></h3>
                            <p><?php esc_html_e('Drop files here for instant AI processing', 'pic-pilot-meta'); ?></p>
                            <button type="button" class="button button-primary" id="automatic-select-files-btn">
                                <?php esc_html_e('Select Files for Auto AI', 'pic-pilot-meta'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Manual/Selective Upload Area -->
                <div class="upload-area-section">
                    <h2 class="section-title">
                        <span class="icon">🎯</span>
                        <?php esc_html_e('Selective AI Control', 'pic-pilot-meta'); ?>
                    </h2>
                    <p class="section-description">
                        <?php esc_html_e('Upload images and choose exactly what to generate with AI. Add keywords for better results and control your token usage.', 'pic-pilot-meta'); ?>
                    </p>
                    
                    <div class="picpilot-upload-area" id="selective-upload-area">
                        <div class="upload-zone selective-zone" id="selective-upload-zone">
                            <div class="upload-icon">🎯</div>
                            <h3><?php esc_html_e('Selective AI Upload', 'pic-pilot-meta'); ?></h3>
                            <p><?php esc_html_e('Drop files here for manual AI control', 'pic-pilot-meta'); ?></p>
                            <button type="button" class="button button-secondary" id="selective-select-files-btn">
                                <?php esc_html_e('Select Files for Manual Control', 'pic-pilot-meta'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Automatic Processing Results -->
            <div class="uploaded-files automatic-files" id="automatic-uploaded-files" style="display: none;">
                <h2><?php esc_html_e('✅ Automatically Processed Images', 'pic-pilot-meta'); ?></h2>
                <p class="description"><?php esc_html_e('These images have been automatically processed with AI-generated content.', 'pic-pilot-meta'); ?></p>
                <div class="files-grid automatic-grid" id="automatic-files-grid"></div>
            </div>
            
            <!-- Selective Processing Results -->
            <div class="uploaded-files selective-files" id="selective-uploaded-files" style="display: none;">
                <h2><?php esc_html_e('🎯 Images Ready for Selective Processing', 'pic-pilot-meta'); ?></h2>
                <p class="description"><?php esc_html_e('Use the controls below to selectively generate AI content for these images.', 'pic-pilot-meta'); ?></p>
                <div class="files-grid selective-grid" id="selective-files-grid"></div>
            </div>
        </div>
        <?php
    }
    
    public static function handle_selective_upload() {
        check_ajax_referer('picpilot_selective_upload', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['file'];
        $upload_overrides = ['test_form' => false];
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = [
                'post_mime_type' => $movefile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                $attachment_url = wp_get_attachment_url($attachment_id);
                $attachment_title = get_the_title($attachment_id);
                $attachment_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                
                Logger::log("[SELECTIVE_UPLOAD] Uploaded attachment #{$attachment_id}: {$movefile['file']}");
                
                wp_send_json_success([
                    'id' => $attachment_id,
                    'url' => $attachment_url,
                    'title' => $attachment_title,
                    'alt' => $attachment_alt,
                    'filename' => basename($movefile['file']),
                    'mode' => 'selective'
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to create attachment']);
            }
        } else {
            wp_send_json_error(['message' => $movefile['error'] ?? 'Upload failed']);
        }
    }
    
    public static function handle_selective_ai_processing() {
        check_ajax_referer('picpilot_selective_upload', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        if (!in_array($type, ['alt', 'title', 'filename'])) {
            wp_send_json_error(['message' => 'Invalid generation type']);
        }
        
        try {
            Logger::log("[SELECTIVE_AI] Processing {$type} for attachment #{$attachment_id}, keywords: '{$keywords}'");
            
            if ($type === 'filename') {
                $result = FilenameGenerator::generate($attachment_id, $keywords);
                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }
                
                // For filename, we return the suggestion but don't apply it yet
                wp_send_json_success(['generated_content' => $result, 'type' => 'filename']);
                
            } else {
                $result = MetadataGenerator::generate($attachment_id, $type, $keywords);
                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }
                
                // Apply the generated content
                if ($type === 'alt') {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $result);
                } elseif ($type === 'title') {
                    wp_update_post(['ID' => $attachment_id, 'post_title' => $result]);
                }
                
                Logger::log("[SELECTIVE_AI] Successfully generated and applied {$type}: '{$result}'");
                wp_send_json_success(['generated_content' => $result, 'type' => $type]);
            }
            
        } catch (\Exception $e) {
            Logger::log("[SELECTIVE_AI] Error generating {$type}: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public static function handle_automatic_upload() {
        check_ajax_referer('picpilot_selective_upload', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['file'];
        $upload_overrides = ['test_form' => false];
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = [
                'post_mime_type' => $movefile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);

            if (!is_wp_error($attachment_id)) {
                Logger::log("[AUTOMATIC_UPLOAD] Uploaded attachment #{$attachment_id}: {$movefile['file']}");

                // Temporarily disable optimization plugins to prevent file conflicts (if enabled)
                // This solves the issue where WebP optimization plugins interfere with file renaming
                $optimization_states = [];
                if (Settings::get('enable_optimization_compatibility')) {
                    Logger::log("[AUTOMATIC_UPLOAD] Optimization compatibility mode enabled - temporarily disabling optimization plugins");
                    $optimization_states = OptimizationCompatibility::disable_optimization_plugins();
                }

                // Automatically generate AI content BEFORE wp_generate_attachment_metadata()
                // This ensures the file is renamed before optimization plugins process it
                $ai_results = [];
                $original_filename = basename($movefile['file']);
                $current_file_path = $movefile['file'];

                try {
                    // Generate alt text
                    $alt_result = MetadataGenerator::generate($attachment_id, 'alt', '');
                    if (!is_wp_error($alt_result) && !empty($alt_result)) {
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_result);
                        $ai_results['alt'] = $alt_result;
                        Logger::log("[AUTOMATIC_UPLOAD] Generated alt text for #{$attachment_id}: '{$alt_result}'");
                    }

                    // Generate title
                    $title_result = MetadataGenerator::generate($attachment_id, 'title', '');
                    if (!is_wp_error($title_result) && !empty($title_result)) {
                        wp_update_post(['ID' => $attachment_id, 'post_title' => $title_result]);
                        $ai_results['title'] = $title_result;
                        Logger::log("[AUTOMATIC_UPLOAD] Generated title for #{$attachment_id}: '{$title_result}'");
                    }

                    // Generate intelligent filename BEFORE wp_generate_attachment_metadata()
                    Logger::log("[AUTOMATIC_UPLOAD] Starting filename generation for #{$attachment_id} (automatic mode - pre-optimization)");
                    $filename_result = FilenameGenerator::generate($attachment_id, '');
                    Logger::log("[AUTOMATIC_UPLOAD] Filename generation result: " . (is_wp_error($filename_result) ? $filename_result->get_error_message() : $filename_result));

                    if (!is_wp_error($filename_result) && !empty($filename_result)) {
                        // Get file extension from original
                        $path_info = pathinfo($current_file_path);
                        $extension = $path_info['extension'];
                        $new_filename = $filename_result . '.' . $extension;

                        // Check if filename already exists
                        $upload_dir = wp_upload_dir();
                        $new_file_path = $upload_dir['path'] . '/' . $new_filename;
                        $counter = 1;
                        $base_filename = $filename_result;

                        while (file_exists($new_file_path)) {
                            $filename_result = $base_filename . '-' . $counter;
                            $new_filename = $filename_result . '.' . $extension;
                            $new_file_path = $upload_dir['path'] . '/' . $new_filename;
                            $counter++;
                        }

                        // Rename the physical file BEFORE optimization plugins run (with advanced conflict handling)
                        $rename_success = false;

                        // First, wait for any existing file locks to clear (if optimization compatibility is enabled)
                        $file_unlocked = true;
                        if (Settings::get('enable_optimization_compatibility')) {
                            $file_unlocked = OptimizationCompatibility::wait_for_file_unlock($current_file_path, 5);
                        }

                        if ($file_unlocked) {
                            Logger::log("[AUTOMATIC_UPLOAD] File unlocked, proceeding with rename for #{$attachment_id}");

                            $max_retries = 3;
                            $retry_delay = 100000; // 100ms in microseconds

                            for ($i = 0; $i < $max_retries; $i++) {
                                if (rename($current_file_path, $new_file_path)) {
                                    $rename_success = true;
                                    Logger::log("[AUTOMATIC_UPLOAD] Rename successful on attempt " . ($i + 1));
                                    break;
                                }

                                Logger::log("[AUTOMATIC_UPLOAD] Rename attempt " . ($i + 1) . " failed for #{$attachment_id}");

                                // Wait before retry to allow any file locks to clear
                                if ($i < $max_retries - 1) {
                                    usleep($retry_delay);
                                    $retry_delay *= 2; // Exponential backoff
                                }
                            }
                        } else {
                            Logger::log("[AUTOMATIC_UPLOAD] File remains locked, skipping rename for #{$attachment_id}");
                        }

                        if ($rename_success) {
                            // Update the current file path for subsequent operations
                            $current_file_path = $new_file_path;

                            // Update the attached file path in database
                            update_attached_file($attachment_id, $new_file_path);

                            $ai_results['filename'] = [
                                'original' => $original_filename,
                                'new' => $new_filename
                            ];

                            Logger::log("[AUTOMATIC_UPLOAD] Pre-renamed file for #{$attachment_id}: '{$original_filename}' -> '{$new_filename}'");
                        } else {
                            Logger::log("[AUTOMATIC_UPLOAD] Failed to pre-rename file for #{$attachment_id} after {$max_retries} attempts");
                        }
                    }
                    
                } catch (\Exception $e) {
                    Logger::log("[AUTOMATIC_UPLOAD] AI generation error for #{$attachment_id}: " . $e->getMessage());
                    // Continue even if AI fails - the upload was successful
                }

                // Now generate attachment metadata with the final file path
                // This will run optimization plugins on the correctly named file
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $current_file_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                Logger::log("[AUTOMATIC_UPLOAD] Generated attachment metadata for #{$attachment_id} with file: {$current_file_path}");

                // Restore optimization plugins and process any deferred operations (if enabled)
                if (Settings::get('enable_optimization_compatibility')) {
                    Logger::log("[AUTOMATIC_UPLOAD] Restoring optimization plugins and processing deferred operations");
                    OptimizationCompatibility::restore_optimization_plugins($optimization_states);
                    OptimizationCompatibility::process_deferred_webp($attachment_id);
                }

                $attachment_url = wp_get_attachment_url($attachment_id);
                $attachment_title = get_the_title($attachment_id);
                $attachment_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                $current_filename = basename(get_attached_file($attachment_id));
                
                wp_send_json_success([
                    'id' => $attachment_id,
                    'url' => $attachment_url,
                    'title' => $attachment_title,
                    'alt' => $attachment_alt,
                    'filename' => $current_filename,
                    'original_filename' => $original_filename,
                    'ai_results' => $ai_results,
                    'mode' => 'automatic'
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to create attachment']);
            }
        } else {
            wp_send_json_error(['message' => $movefile['error'] ?? 'Upload failed']);
        }
    }
}