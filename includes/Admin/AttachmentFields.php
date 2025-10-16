<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\Logger;

defined('ABSPATH') || exit;

class AttachmentFields {

    public static function init() {
        // Add AI tools to attachment edit form (native WordPress)
        add_filter('attachment_fields_to_edit', [__CLASS__, 'add_ai_tools_fields'], 10, 2);
        
        // Enqueue scripts for both admin and frontend contexts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_attachment_scripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_attachment_scripts']); // For frontend editors
        
        // Enhanced page builder integration
        add_action('elementor/editor/before_enqueue_scripts', [__CLASS__, 'enqueue_attachment_scripts']);
        
        // Additional page builder hooks
        add_action('vc_enqueue_ui_js', [__CLASS__, 'enqueue_attachment_scripts']); // Visual Composer
        add_action('et_fb_enqueue_assets', [__CLASS__, 'enqueue_attachment_scripts']); // Divi
    }

    /**
     * Add AI tools fields to attachment edit form
     */
    public static function add_ai_tools_fields($form_fields, $post) {
        // Only show for images
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        // Check if media modal tools are enabled
        if (!Settings::get('enable_media_modal_tools', false)) {
            return $form_fields;
        }

        // Get current values
        $current_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $current_title = $post->post_title;

        // Create AI Tools section
        $ai_tools_html = self::render_ai_tools_section($post->ID, $current_title, $current_alt);

        // Insert AI tools after standard fields but before alt text
        // Find the position to insert (after image_url if it exists, otherwise after post_excerpt)
        $insert_position = 0;
        $field_keys = array_keys($form_fields);
        
        // Debug: Log available fields to understand the structure
        Logger::log('[MODAL] Available form fields: ' . implode(', ', $field_keys));
        
        // Look for common fields that appear before alt text
        $target_fields = ['image_url', 'post_excerpt', 'post_content', 'url'];
        foreach ($target_fields as $target_field) {
            $pos = array_search($target_field, $field_keys);
            if ($pos !== false) {
                $insert_position = $pos + 1;
                Logger::log("[MODAL] Found $target_field at position $pos, inserting AI tools at position $insert_position");
                break;
            }
        }
        
        // If no target fields found, insert before image_alt
        if ($insert_position === 0) {
            $alt_pos = array_search('image_alt', $field_keys);
            $insert_position = $alt_pos !== false ? $alt_pos : count($form_fields);
            Logger::log("[MODAL] No target fields found, inserting at position $insert_position (before alt or at end)");
        }
        
        // Split the array and insert our field
        $before = array_slice($form_fields, 0, $insert_position, true);
        $after = array_slice($form_fields, $insert_position, null, true);
        
        $form_fields = array_merge($before, [
            'pic_pilot_ai_tools' => [
                'label' => '',
                'input' => 'html',
                'html' => $ai_tools_html,
                'show_in_edit' => true,
                'show_in_modal' => true,
            ]
        ], $after);

        return $form_fields;
    }

    /**
     * Render the AI tools section HTML
     */
    private static function render_ai_tools_section($attachment_id, $current_title, $current_alt) {
        ob_start();
        
        // Get settings to check which features are enabled
        $settings = get_option('picpilot_meta_settings', []);
        $auto_generate_both_enabled = !empty($settings['enable_auto_generate_both']);
        $dangerous_rename_enabled = !empty($settings['enable_dangerous_filename_rename']);
        $show_keywords = !empty($settings['show_keywords_field']);
        
        // Check what's missing for "Generate Both" button
        $filename = basename(get_attached_file($attachment_id));
        $is_missing_alt = empty($current_alt);
        $is_missing_title = empty($current_title) || strpos(strtolower($current_title), strtolower(pathinfo($filename, PATHINFO_FILENAME))) !== false;
        $is_missing_both = $is_missing_alt && $is_missing_title;
        
        // Load script inline for page builder compatibility
        static $script_loaded = false;
        if (!$script_loaded) {
            $script_loaded = true;
            $ajax_url = admin_url('admin-ajax.php');
            $nonce = wp_create_nonce('picpilot_studio_generate');
            
            // Load universal modal JavaScript inline
            $universal_modal_path = plugin_dir_path(__DIR__) . '../assets/js/universal-modal.js';
            $universal_modal_content = '';
            if (file_exists($universal_modal_path)) {
                $universal_modal_content = file_get_contents($universal_modal_path);
            }
            ?>
            <script>
            // Ensure window.picPilotAttachment exists for all contexts
            if (typeof window.picPilotAttachment === 'undefined') {
                window.picPilotAttachment = { 
                    ajax_url: '<?php echo esc_js($ajax_url); ?>', 
                    nonce: '<?php echo esc_js($nonce); ?>',
                    settings: {
                        auto_generate_both_enabled: <?php echo $auto_generate_both_enabled ? 'true' : 'false'; ?>,
                        dangerous_rename_enabled: <?php echo $dangerous_rename_enabled ? 'true' : 'false'; ?>,
                        show_keywords: <?php echo $show_keywords ? 'true' : 'false'; ?>
                    }
                };
            }
            
            
            // Load Universal Modal JavaScript inline for page builder compatibility
            <?php
            if (!empty($universal_modal_content)) {
                // Output JavaScript content directly (already from trusted plugin file)
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $universal_modal_content;
            }
            ?>
            
            // Note: Click handling is now done by universal-modal.js to prevent duplicate handlers
            
            
            // Simple modal function
            function openPicPilotModal(attachmentId) {
                // Remove existing modal
                const existingModal = document.getElementById('pic-pilot-ai-modal');
                if (existingModal) {
                    existingModal.remove();
                }
                
                // Get current image data
                const currentTitle = getImageTitle(attachmentId);
                const currentAlt = getImageAlt(attachmentId);
                const imageUrl = getImageUrl(attachmentId);
                
                // Create modal HTML
                const modalHtml = `
                    <div id="pic-pilot-ai-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px;">
                        <div style="background: #fff; border-radius: 8px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: #2271b1; color: #fff; border-radius: 8px 8px 0 0;">
                                <h2 style="margin: 0; font-size: 18px;">🤖 AI Tools & Metadata</h2>
                                <button type="button" onclick="document.getElementById('pic-pilot-ai-modal').remove()" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">×</button>
                            </div>
                            
                            <div style="padding: 20px;">
                                <div style="text-align: center; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                                    <img src="${imageUrl || ''}" alt="Preview" style="max-width: 100%; max-height: 150px; border-radius: 4px;" />
                                    <div style="margin-top: 10px; font-size: 13px; color: #666;">
                                        <strong>Current Title:</strong> ${currentTitle || 'No title'}<br>
                                        <strong>Current Alt Text:</strong> ${currentAlt || 'No alt text'}
                                    </div>
                                </div>

                                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">🎯 Keywords (optional):</label>
                                    <input type="text" id="pic-pilot-modal-keywords" placeholder="e.g., business person, outdoor scene, product photo" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Provide context for better AI results</p>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                    <div style="border: 1px solid #ddd; border-radius: 6px; padding: 15px; text-align: center;">
                                        <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">📝 Generate Title</h3>
                                        <p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">Create an SEO-friendly title</p>
                                        <button type="button" class="button button-primary" onclick="generateMetadata('title', ${attachmentId})" style="background: #2271b1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Generate Title</button>
                                    </div>

                                    <div style="border: 1px solid #ddd; border-radius: 6px; padding: 15px; text-align: center;">
                                        <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">🏷️ Generate Alt Text</h3>
                                        <p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">Create accessible descriptions</p>
                                        <button type="button" class="button button-primary" onclick="generateMetadata('alt', ${attachmentId})" style="background: #2271b1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Generate Alt Text</button>
                                    </div>
                                </div>
                                
                                <div style="text-align: center; padding: 15px; background: #f0f8f0; border: 1px solid #00a32a; border-radius: 6px;">
                                    <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">🔄 Duplicate Image</h3>
                                    <p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">Create a copy with AI metadata</p>
                                    <button type="button" class="button button-secondary" onclick="duplicateImage(${attachmentId})" style="background: #00a32a; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">🔄 Duplicate with AI</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHtml);
                
                // Focus keywords input
                document.getElementById('pic-pilot-modal-keywords').focus();
                
                // Close on overlay click
                document.getElementById('pic-pilot-ai-modal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.remove();
                    }
                });
            }
            
            // Helper functions
            function getImageTitle(attachmentId) {
                const titleSelectors = [
                    `#attachment_${attachmentId}_title`,
                    'input[name*="[post_title]"]',
                    '#attachment-details-title',
                    'input[data-setting="title"]',
                    '.setting[data-setting="title"] input',
                    'input.attachment-title',
                    '#title'
                ];

                for (const selector of titleSelectors) {
                    const field = document.querySelector(selector);
                    if (field) {
                        return field.value || '';
                    }
                }
                return '';
            }

            function getImageAlt(attachmentId) {
                const altSelectors = [
                    `#attachment_${attachmentId}_alt`,
                    'input[name*="[image_alt]"]',
                    '#attachment-details-alt-text',
                    'input[data-setting="alt"]',
                    '.setting[data-setting="alt"] input',
                    'input.attachment-alt'
                ];

                for (const selector of altSelectors) {
                    const field = document.querySelector(selector);
                    if (field) {
                        return field.value || '';
                    }
                }
                return '';
            }

            function getImageUrl(attachmentId) {
                const img = document.querySelector(`.attachment-preview img, .details-image img, .media-modal img[data-attachment-id="${attachmentId}"]`);
                if (img) {
                    return img.src || img.dataset.fullSrc || '';
                }

                const urlField = document.querySelector('input[name*="[url]"], #attachment-details-copy-link');
                if (urlField && urlField.value && urlField.value.includes('/uploads/')) {
                    return urlField.value;
                }
                return '';
            }
            
            // Placeholder functions for modal actions
            function generateMetadata(type, attachmentId) {
                alert('Generate ' + type + ' functionality will be implemented here');
            }
            
            function duplicateImage(attachmentId) {
                alert('Duplicate image functionality will be implemented here');
            }
            </script>
            <?php
        }
        ?>
        <div class="pic-pilot-attachment-ai-tools" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
            <div class="pic-pilot-ai-launcher" style="padding: 10px; text-align: center;">
                <button type="button" 
                        class="button button-primary pic-pilot-launch-modal-btn" 
                        data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                        style="width: 100%; background: #2271b1; border-color: #2271b1; font-size: 12px; font-weight: 500; padding: 8px 12px;">
                    Pic Pilot
                </button>
                
                <p style="margin: 6px 0 0 0; font-size: 11px; color: #666; text-align: center;">
                    Generate alt text, titles, duplicate images & more
                </p>
            </div>
        </div>

        <style>
            .pic-pilot-attachment-ai-tools {
                margin: 15px 0;
                padding: 12px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 6px;
                text-align: center;
            }
            
            .pic-pilot-ai-launcher {
                padding: 0 !important;
            }
            
            .pic-pilot-launch-modal-btn {
                width: 100% !important;
                margin: 0 !important;
                padding: 10px 16px !important;
                font-size: 14px !important;
                font-weight: 600 !important;
            }
            
            .pic-pilot-launch-modal-btn:hover {
                background: #1e5c8c !important;
                border-color: #1e5c8c !important;
            }
            
            /* Better positioning in media sidebar */
            .media-sidebar .pic-pilot-attachment-ai-tools {
                margin: 12px 0;
                background: #fff;
                border: 2px solid #2271b1;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            /* Ensure full visibility */
            .attachment-details .pic-pilot-attachment-ai-tools {
                clear: both;
                display: block !important;
                visibility: visible !important;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts for attachment edit screen
     */
    public static function enqueue_attachment_scripts($hook = '') {
        // Check if media modal tools are enabled
        $modal_tools_enabled = Settings::get('enable_media_modal_tools', false);
        
        if (!$modal_tools_enabled) {
            return;
        }

        // Enhanced detection for page builder contexts
        $should_enqueue = is_admin() || 
                         isset($_GET['elementor-preview']) || 
                         isset($_GET['vc_editable']) ||
                         isset($_GET['et_fb']) ||
                         wp_doing_ajax() ||
                         self::is_page_builder_context();

        if (!$should_enqueue) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'pic-pilot-attachment-fields',
            PIC_PILOT_META_URL . 'assets/css/pic-pilot-meta.css',
            [],
            PIC_PILOT_META_VERSION
        );

        // Enqueue universal modal script for page builders
        wp_enqueue_script(
            'pic-pilot-universal-modal',
            PIC_PILOT_META_URL . 'assets/js/universal-modal.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('pic-pilot-universal-modal', 'picPilotUniversal', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('picpilot_studio_generate'),
        ]);
    }

    /**
     * Detect if we're in a page builder context
     */
    private static function is_page_builder_context() {
        // Check for Elementor
        if (defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin')) {
            return true;
        }
        
        // Check for Visual Composer
        if (defined('WPB_VC_VERSION') || function_exists('vc_is_inline')) {
            return true;
        }
        
        // Check for Divi
        if (function_exists('et_divi_builder_init') || defined('ET_BUILDER_VERSION')) {
            return true;
        }
        
        // Check page builder specific $_POST or $_GET parameters
        $page_builder_params = [
            'elementor-preview', 'elementor_library',
            'vc_editable', 'vc_action',
            'et_fb', 'et_bfb'
        ];
        
        foreach ($page_builder_params as $param) {
            if (isset($_GET[$param]) || isset($_POST[$param])) {
                return true;
            }
        }
        
        return false;
    }



}