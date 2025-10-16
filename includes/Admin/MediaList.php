<?php

namespace PicPilotMeta\Admin;

defined('ABSPATH') || exit;

class MediaList {

    public static function init() {
        add_filter('media_row_actions', [__CLASS__, 'add_duplicate_action'], 10, 2);
        // Enqueue styles/scripts for list view
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_filter('media_row_actions', [__CLASS__, 'add_generate_button'], 10, 2);
        
        // Register custom column for media list
        add_filter('manage_media_columns', [__CLASS__, 'add_picpilot_column']);
        add_action('manage_media_custom_column', [__CLASS__, 'render_picpilot_column'], 10, 2);
        
        // Add alt text filtering
        add_action('restrict_manage_posts', [__CLASS__, 'add_alt_text_filter']);
        add_filter('pre_get_posts', [__CLASS__, 'filter_by_alt_text']);
        
        // Add attachment edit page functionality
        add_action('edit_form_after_title', [__CLASS__, 'add_generate_buttons_to_edit_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_edit_page_scripts']);
        
        // Add bulk actions
        add_filter('bulk_actions-upload', [__CLASS__, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-upload', [__CLASS__, 'handle_bulk_actions'], 10, 3);
        add_action('admin_notices', [__CLASS__, 'bulk_action_notices']);
    }


    public static function add_generate_button($actions, $post) {
        if ($post->post_type !== 'attachment' || !wp_attachment_is_image($post->ID)) return $actions;

        // PicPilot tools are now shown in column by default, so don't add to row actions
        return $actions;

        $show_keywords = !empty($settings['show_keywords_field']);

        // Only output keyword input once
        $keywords_input = '';
        if ($show_keywords) {
            $keywords_input = '<input type="text" class="picpilot-keywords" placeholder="' . esc_attr__('Optional keywords/context', 'pic-pilot-meta') . '" data-id="' . esc_attr($post->ID) . '" style="margin-right:6px;max-width:160px;" />';
        }

        // Check if alt text already exists
        $existing_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $alt_button_text = !empty($existing_alt) ? 
            esc_html__('Regenerate Alt Text', 'pic-pilot-meta') : 
            esc_html__('Generate Alt Text', 'pic-pilot-meta');

        // Check if title already exists
        $existing_title = get_the_title($post->ID);
        $title_button_text = !empty($existing_title) ? 
            esc_html__('Regenerate Title', 'pic-pilot-meta') : 
            esc_html__('Generate Title', 'pic-pilot-meta');

        $actions['generate_meta'] = $keywords_input
            . sprintf(
                '<button type="button" class="picpilot-generate-meta" data-id="%d" data-type="alt">%s</button> ',
                esc_attr($post->ID),
                $alt_button_text
            )
            . sprintf(
                '<button type="button" class="picpilot-generate-meta" data-id="%d" data-type="title">%s</button>',
                esc_attr($post->ID),
                $title_button_text
            );

        return $actions;
    }



    public static function add_duplicate_action($actions, $post) {
        if ($post->post_type === 'attachment' && current_user_can('upload_files')) {
            $settings = get_option('picpilot_meta_settings', []);
            $show_in_column = !empty($settings['show_picpilot_in_column']);
            
            // If showing in column, don't add to row actions
            if (!$show_in_column) {
                $actions['duplicate_image_quick'] = '<a href="#" class="pic-pilot-duplicate-image" data-id="' . esc_attr($post->ID) . '">' . esc_html__('Duplicate', 'pic-pilot-meta') . '</a>';
            }
        }

        return $actions;
    }



    public static function enqueue_scripts($hook) {
        if ('upload.php' !== $hook) {
            return;
        }

        // Enqueue CSS styles
        wp_enqueue_style(
            'pic-pilot-meta-styles',
            PIC_PILOT_META_URL . 'assets/css/pic-pilot-meta.css',
            [],
            '1.0.0'
        );

        $settings = get_option('picpilot_meta_settings', []);

        // Enqueue for duplicate logic (still uses jQuery)
        wp_enqueue_script(
            'pic-pilot-meta-duplicate',
            PIC_PILOT_META_URL . 'assets/js/duplicate-image.js',
            ['jquery'],
            PIC_PILOT_META_VERSION,
            true
        );

        wp_localize_script('pic-pilot-meta-duplicate', 'PicPilotStudio', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('picpilot_studio_generate'),
            // Smart generation features are now always enabled
            'enable_filename_generation' => true,
            'enable_title_generation_on_duplicate' => true,
            'enable_alt_generation_on_duplicate' => true,
        ]);


        // Enqueue media list script (vanilla JS)
        wp_enqueue_script(
            'pic-pilot-media-list',
            PIC_PILOT_META_URL . 'assets/js/media-list.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );
        
        // Localize script for media list
        wp_localize_script('pic-pilot-media-list', 'PicPilotStudio', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('picpilot_studio_generate'),
            'settings' => [
                'auto_generate_both_enabled' => !empty($settings['enable_auto_generate_both']),
                'dangerous_rename_enabled' => !empty($settings['enable_dangerous_filename_rename'])
            ]
        ]);

        //Enqueue smart duplication modal
        wp_enqueue_script(
            'picpilot-smart-duplication',
            PIC_PILOT_META_URL . 'assets/js/smart-duplication-modal.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );


        // Enqueue bulk operations script
        wp_enqueue_script(
            'pic-pilot-bulk-operations',
            PIC_PILOT_META_URL . 'assets/js/bulk-operations.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );

        // Enqueue image tags script
        wp_enqueue_script(
            'pic-pilot-image-tags',
            PIC_PILOT_META_URL . 'assets/js/image-tags.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );

        // Attach config BEFORE script load for media-list, smart-duplication modal, and bulk operations
        $bulk_data = [];
        if (isset($_GET['picpilot_bulk_action']) && $_GET['picpilot_bulk_action'] === 'generate') {
            $bulk_data = [
                'key' => sanitize_text_field($_GET['picpilot_bulk_key'] ?? ''),
                'count' => intval($_GET['picpilot_bulk_count'] ?? 0)
            ];
        }

        wp_add_inline_script('pic-pilot-media-list', 'window.picPilotStudio = ' . json_encode([
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('picpilot_studio_generate'),
            'bulk' => $bulk_data,
        ]) . ';', 'before');
    }




    public static function add_row_actions($actions, $post) {
        if ($post->post_type !== 'attachment' || !wp_attachment_is_image($post)) return $actions;

        $actions['picpilot_generate'] = sprintf(
            '<a href="#" class="pic-pilot-generate-metadata" data-id="%d">%s</a>',
            esc_attr($post->ID),
            esc_html__('Generate Metadata', 'pic-pilot-meta')
        );

        return $actions;
    }

    /**
     * Add comprehensive image filter dropdown to media library
     */
    public static function add_alt_text_filter() {
        global $pagenow;
        
        if ($pagenow !== 'upload.php') {
            return;
        }

        // Get current filter values
        $current_alt_filter = isset($_GET['picpilot_alt_filter']) ? sanitize_text_field($_GET['picpilot_alt_filter']) : '';
        $current_tag_filter = isset($_GET['picpilot_tag_filter']) ? sanitize_text_field($_GET['picpilot_tag_filter']) : '';
        $current_filter = $current_alt_filter ?: ($current_tag_filter ? 'tag:' . $current_tag_filter : '');

        // Get available tags with counts
        $tags = get_terms([
            'taxonomy' => 'picpilot_image_tags',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        // Calculate accurate tag counts
        $tag_options = [];
        if (!empty($tags) && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $attachments = get_objects_in_term($tag->term_id, 'picpilot_image_tags');
                $image_count = 0;
                
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment_id) {
                        if (wp_attachment_is_image($attachment_id)) {
                            $image_count++;
                        }
                    }
                }
                
                if ($image_count > 0) {
                    $tag_options[] = [
                        'value' => 'tag:' . $tag->slug,
                        'label' => sprintf('Tagged: %s (%d)', $tag->name, $image_count),
                        'selected' => $current_filter === ('tag:' . $tag->slug)
                    ];
                }
            }
        }
        
        echo '<select name="picpilot_filter" id="picpilot-comprehensive-filter">';
        echo '<option value="">' . esc_html__('All Images', 'pic-pilot-meta') . '</option>';
        
        // Alt text options
        echo '<optgroup label="' . esc_attr__('By Missing Attributes', 'pic-pilot-meta') . '">';
        echo '<option value="missing_alt"' . selected($current_filter, 'missing_alt', false) . '>' . esc_html__('Missing Alt tag', 'pic-pilot-meta') . '</option>';
        echo '<option value="missing_title"' . selected($current_filter, 'missing_title', false) . '>' . esc_html__('Missing title', 'pic-pilot-meta') . '</option>';
        echo '<option value="missing_both"' . selected($current_filter, 'missing_both', false) . '>' . esc_html__('Missing alt tag and title', 'pic-pilot-meta') . '</option>';
        echo '</optgroup>';
        
        // Tag options
        if (!empty($tag_options)) {
            echo '<optgroup label="' . esc_attr__('By Tags', 'pic-pilot-meta') . '">';
            foreach ($tag_options as $tag_option) {
                echo sprintf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($tag_option['value']),
                    selected($tag_option['selected'], true, false),
                    esc_html($tag_option['label'])
                );
            }
            echo '</optgroup>';
        }
        
        echo '</select>';
        
        // Add JavaScript to handle the unified filter
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const filter = document.getElementById('picpilot-comprehensive-filter');
            if (filter) {
                filter.addEventListener('change', function() {
                    const form = this.closest('form') || document.getElementById('posts-filter');
                    if (form) {
                        // Clear existing filter inputs
                        const existingInputs = form.querySelectorAll('input[name="picpilot_alt_filter"], input[name="picpilot_tag_filter"]');
                        existingInputs.forEach(input => input.remove());
                        
                        // Add appropriate hidden input based on selection
                        const value = this.value;
                        if (value.startsWith('tag:')) {
                            const tagSlug = value.replace('tag:', '');
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'picpilot_tag_filter';
                            hiddenInput.value = tagSlug;
                            form.appendChild(hiddenInput);
                        } else if (value) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'picpilot_alt_filter';
                            hiddenInput.value = value;
                            form.appendChild(hiddenInput);
                        }
                        
                        form.submit();
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Filter attachments by alt text status
     */
    public static function filter_by_alt_text($query) {
        global $pagenow;
        
        if (!is_admin() || $pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }

        if (!isset($_GET['picpilot_alt_filter']) || empty($_GET['picpilot_alt_filter'])) {
            return;
        }

        $filter = sanitize_text_field($_GET['picpilot_alt_filter']);

        if ($filter === 'missing_alt') {
            // Images without alt text
            $query->set('meta_query', [
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
            ]);
        } elseif ($filter === 'missing_title') {
            // Images with default/empty titles - handled in posts_where filter
            add_filter('posts_where', [__CLASS__, 'filter_missing_title_where'], 10, 2);
        } elseif ($filter === 'missing_both') {
            // Images missing both alt text and meaningful titles
            $query->set('meta_query', [
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
            ]);
            // Also filter by title
            add_filter('posts_where', [__CLASS__, 'filter_missing_title_where'], 10, 2);
        }

        // Ensure we only get image attachments
        $query->set('post_type', 'attachment');
        $query->set('post_status', 'inherit');
        $query->set('post_mime_type', 'image');
    }

    /**
     * Filter posts WHERE clause for missing titles
     */
    public static function filter_missing_title_where($where, $query) {
        global $wpdb;

        // Only apply to our specific query
        if (!is_admin() || !isset($_GET['picpilot_alt_filter'])) {
            return $where;
        }

        $filter = sanitize_text_field($_GET['picpilot_alt_filter']);
        if ($filter !== 'missing_title' && $filter !== 'missing_both') {
            return $where;
        }

        // Add condition for empty titles or titles that look like filenames
        $where .= $wpdb->prepare(" AND ({$wpdb->posts}.post_title = '' OR {$wpdb->posts}.post_title IS NULL OR {$wpdb->posts}.post_title REGEXP %s)", '^(IMG_|DSC_|P[0-9]{8}|[0-9]{8}_|[a-zA-Z0-9_-]+\\.(jpg|jpeg|png|gif|webp))$');

        // Remove this filter after use to prevent affecting other queries
        remove_filter('posts_where', [__CLASS__, 'filter_missing_title_where'], 10);

        return $where;
    }

    /**
     * Add generate buttons to attachment edit page
     */
    public static function add_generate_buttons_to_edit_page($post) {
        if (!$post || $post->post_type !== 'attachment' || !wp_attachment_is_image($post->ID)) {
            return;
        }

        $existing_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $alt_button_text = !empty($existing_alt) ? 
            esc_html__('Regenerate Alt Text', 'pic-pilot-meta') : 
            esc_html__('Generate Alt Text', 'pic-pilot-meta');

        // Check if title already exists
        $existing_title = get_the_title($post->ID);
        $title_button_text = !empty($existing_title) ? 
            esc_html__('Regenerate Title', 'pic-pilot-meta') : 
            esc_html__('Generate Title', 'pic-pilot-meta');

        ?>
        <div id="picpilot-edit-page-controls" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
            <h3 style="margin-top: 0; font-size: 14px; color: #333;">
                🤖 <?php esc_html_e('AI Metadata Generation', 'pic-pilot-meta'); ?>
            </h3>
            
            <div style="margin-bottom: 15px;">
                <label for="picpilot-edit-keywords" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php esc_html_e('Keywords (optional):', 'pic-pilot-meta'); ?>
                </label>
                <input type="text" id="picpilot-edit-keywords" class="widefat" 
                       placeholder="<?php esc_attr_e('Add context for better AI results (e.g., Business manager, construction site)', 'pic-pilot-meta'); ?>" />
                <p class="description">
                    <?php esc_html_e('Provide context about the person, profession, or setting to help AI generate more accurate descriptions.', 'pic-pilot-meta'); ?>
                </p>
            </div>

            <div class="picpilot-edit-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="picpilot-generate-title-edit" class="button button-secondary" data-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-format-chat" style="margin-right: 5px;"></span>
                    <?php echo esc_html($title_button_text); ?>
                </button>

                <button type="button" id="picpilot-generate-alt-edit" class="button button-secondary" data-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-universal-access-alt" style="margin-right: 5px;"></span>
                    <?php echo esc_html($alt_button_text); ?>
                </button>
            </div>

            <div id="picpilot-edit-status" style="margin-top: 10px; padding: 8px; border-radius: 3px; display: none;">
                <!-- Status messages will appear here -->
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue scripts for attachment edit page
     */
    public static function enqueue_edit_page_scripts($hook) {
        global $post;
        
        if ($hook !== 'post.php' || !$post || $post->post_type !== 'attachment' || !wp_attachment_is_image($post->ID)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'pic-pilot-meta-edit-styles',
            PIC_PILOT_META_URL . 'assets/css/pic-pilot-meta.css',
            [],
            '1.0.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'pic-pilot-meta-edit',
            PIC_PILOT_META_URL . 'assets/js/attachment-edit.js',
            [],
            PIC_PILOT_META_VERSION,
            true
        );

        // Localize script
        wp_localize_script('pic-pilot-meta-edit', 'PicPilotStudioEdit', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('picpilot_studio_generate'),
            'attachment_id' => $post->ID,
        ]);
    }

    /**
     * Add bulk actions to media library
     */
    public static function add_bulk_actions($actions) {
        $actions['picpilot_bulk_generate'] = __('Generate AI Metadata', 'pic-pilot-meta');
        return $actions;
    }

    /**
     * Handle bulk actions - redirect to JavaScript modal instead of processing here
     */
    public static function handle_bulk_actions($redirect_url, $action, $post_ids) {
        if ($action !== 'picpilot_bulk_generate') {
            return $redirect_url;
        }

        // Filter to only include image attachments
        $image_ids = [];
        foreach ($post_ids as $id) {
            if (wp_attachment_is_image($id)) {
                $image_ids[] = $id;
            }
        }

        if (empty($image_ids)) {
            $redirect_url = add_query_arg('picpilot_bulk_error', 'no_images', $redirect_url);
            return $redirect_url;
        }

        // Store selected IDs in transient for JavaScript to access
        $transient_key = 'picpilot_bulk_' . get_current_user_id() . '_' . time();
        set_transient($transient_key, $image_ids, 300); // 5 minutes

        // Add query args to trigger JavaScript modal
        $redirect_url = add_query_arg([
            'picpilot_bulk_action' => 'generate',
            'picpilot_bulk_key' => $transient_key,
            'picpilot_bulk_count' => count($image_ids)
        ], $redirect_url);

        return $redirect_url;
    }

    /**
     * Display bulk action notices
     */
    public static function bulk_action_notices() {
        if (isset($_GET['picpilot_bulk_error']) && $_GET['picpilot_bulk_error'] === 'no_images') {
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 esc_html__('No image attachments were selected for AI metadata generation.', 'pic-pilot-meta') . 
                 '</p></div>';
        }

        if (isset($_GET['picpilot_bulk_success'])) {
            $count = intval($_GET['picpilot_bulk_success']);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 /* translators: %d: number of images that had AI metadata generated */
                 sprintf(esc_html__('Successfully generated AI metadata for %d images.', 'pic-pilot-meta'), absint($count)) .
                 '</p></div>';
        }
    }

    /**
     * Add PicPilot column to media list
     */
    public static function add_picpilot_column($columns) {
        // Always show PicPilot tools column
        $columns['picpilot_tools'] = __('PicPilot Tools', 'pic-pilot-meta');
        return $columns;
    }

    /**
     * Render PicPilot column content
     */
    public static function render_picpilot_column($column_name, $post_id) {
        if ($column_name !== 'picpilot_tools' || !wp_attachment_is_image($post_id)) {
            return;
        }

        $settings = get_option('picpilot_meta_settings', []);
        $show_keywords = !empty($settings['show_keywords_field']);
        $show_hover_info = !empty($settings['show_hover_info']);
        $auto_generate_both_enabled = !empty($settings['enable_auto_generate_both']);
        $dangerous_rename_enabled = !empty($settings['enable_dangerous_filename_rename']);
        
        // Get current metadata for individual button tooltips and status
        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        $title = get_the_title($post_id);
        $filename = basename(get_attached_file($post_id));
        
        // Check what's missing for "Generate Both" button
        $is_missing_alt = empty($alt_text);
        $is_missing_title = empty($title) || strpos(strtolower($title), strtolower(pathinfo($filename, PATHINFO_FILENAME))) !== false;
        $is_missing_both = $is_missing_alt && $is_missing_title;
        
        echo '<div class="picpilot-column-wrapper">';
        
        // Keywords input
        if ($show_keywords) {
            echo '<input type="text" class="picpilot-keywords" placeholder="' . esc_attr__('Keywords', 'pic-pilot-meta') . '" data-id="' . esc_attr($post_id) . '" style="width:100%;margin-bottom:5px;font-size:11px;" />';
        }
        
        // Check if alt text already exists
        $existing_alt = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        $alt_button_text = !empty($existing_alt) ? 
            esc_html__('Regen Alt', 'pic-pilot-meta') : 
            esc_html__('Gen Alt', 'pic-pilot-meta');
        
        // Prepare tooltips for each button if hover info is enabled
        $alt_tooltip = '';
        $title_tooltip = '';
        $duplicate_tooltip = '';
        $both_tooltip = '';
        $rename_tooltip = '';
        
        if ($show_hover_info) {
            $alt_tooltip = $alt_text ? 'Alt: ' . esc_attr($alt_text) : 'No alt text';
            $title_tooltip = $title ? 'Title: ' . esc_attr($title) : 'No title';
            $duplicate_tooltip = $filename ? 'File: ' . esc_attr($filename) : 'Unknown file';
            $both_tooltip = 'Generate both alt text and title with AI';
            $rename_tooltip = 'Rename filename (dangerous operation)';
        }
        
        // Show "Generate Both" button prominently if both are missing and feature is enabled
        if ($is_missing_both && $auto_generate_both_enabled) {
            echo '<div class="picpilot-generate-both-section" style="margin-bottom:5px;">';
            echo sprintf(
                '<button type="button" class="button button-primary button-small picpilot-generate-both" data-id="%d"%s style="width:100%%;font-weight:600;">🪄 %s</button>',
                esc_attr($post_id),
                $both_tooltip ? ' title="' . esc_attr($both_tooltip) . '"' : '',
                esc_html__('Generate Both', 'pic-pilot-meta')
            );
            echo '</div>';
        }
        
        // Compact button layout for individual actions
        echo '<div class="picpilot-button-group" style="display:flex;gap:2px;flex-wrap:wrap;">';
        
        // Alt text button
        echo sprintf(
            '<button type="button" class="button button-small picpilot-generate-meta" data-id="%d" data-type="alt"%s>%s</button>',
            esc_attr($post_id),
            $alt_tooltip ? ' title="' . esc_attr($alt_tooltip) . '"' : '',
            esc_html($alt_button_text)
        );

        // Title button
        echo sprintf(
            '<button type="button" class="button button-small picpilot-generate-meta" data-id="%d" data-type="title"%s>%s</button>',
            esc_attr($post_id),
            $title_tooltip ? ' title="' . esc_attr($title_tooltip) . '"' : '',
            esc_html__('Gen Title', 'pic-pilot-meta')
        );

        // Duplicate button
        echo sprintf(
            '<button type="button" class="button button-small pic-pilot-duplicate-image" data-id="%d"%s>%s</button>',
            esc_attr($post_id),
            $duplicate_tooltip ? ' title="' . esc_attr($duplicate_tooltip) . '"' : '',
            esc_html__('Duplicate', 'pic-pilot-meta')
        );
        
        echo '</div>';
        
        // Dangerous rename section (only if enabled)
        if ($dangerous_rename_enabled) {
            echo '<div class="picpilot-rename-section" style="margin-top:5px;padding-top:5px;border-top:1px solid #ddd;">';
            echo sprintf(
                '<button type="button" class="button button-small picpilot-rename-filename" data-id="%d"%s style="font-size:10px;color:#d63638;">⚠️ %s</button>',
                esc_attr($post_id),
                $rename_tooltip ? ' title="' . esc_attr($rename_tooltip) . '"' : '',
                esc_html__('Rename', 'pic-pilot-meta')
            );
            echo '</div>';
        }
        
        echo '</div>';
    }
}
