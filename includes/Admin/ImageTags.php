<?php

namespace PicPilotMeta\Admin;

defined('ABSPATH') || exit;

/**
 * Class ImageTags
 * 
 * Handles image tagging/categorizing functionality for media library
 * 
 * @package PicPilotStudio\Admin
 */
class ImageTags {

    public static function init() {
        // Register custom taxonomy for image tags
        add_action('init', [__CLASS__, 'register_image_tags_taxonomy']);
        
        // Add AJAX handlers for tag management
        add_action('wp_ajax_picpilot_add_image_tag', [__CLASS__, 'ajax_add_image_tag']);
        add_action('wp_ajax_picpilot_remove_image_tag', [__CLASS__, 'ajax_remove_image_tag']);
        add_action('wp_ajax_picpilot_get_all_tags', [__CLASS__, 'ajax_get_all_tags']);
        
        // Integrate with existing MediaList filter
        add_filter('pre_get_posts', [__CLASS__, 'filter_media_by_tags']);
        
        // Add tag column to media list
        add_filter('manage_media_columns', [__CLASS__, 'add_tags_column']);
        add_action('manage_media_custom_column', [__CLASS__, 'display_tags_column'], 10, 2);
    }

    /**
     * Register custom taxonomy for image tags
     */
    public static function register_image_tags_taxonomy() {
        register_taxonomy('picpilot_image_tags', 'attachment', [
            'labels' => [
                'name' => __('Image Tags', 'pic-pilot-meta'),
                'singular_name' => __('Image Tag', 'pic-pilot-meta'),
                'search_items' => __('Search Tags', 'pic-pilot-meta'),
                'all_items' => __('All Tags', 'pic-pilot-meta'),
                'edit_item' => __('Edit Tag', 'pic-pilot-meta'),
                'update_item' => __('Update Tag', 'pic-pilot-meta'),
                'add_new_item' => __('Add New Tag', 'pic-pilot-meta'),
                'new_item_name' => __('New Tag Name', 'pic-pilot-meta'),
                'menu_name' => __('Image Tags', 'pic-pilot-meta'),
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => false,
            'show_admin_column' => false,
            'query_var' => true,
            'rewrite' => false,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Add tag to image via AJAX
     */
    public static function ajax_add_image_tag() {
        check_ajax_referer('picpilot_studio_generate', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $tag_name = sanitize_text_field($_POST['tag_name'] ?? '');

        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Invalid image ID']);
        }

        if (empty($tag_name)) {
            wp_send_json_error(['message' => 'Tag name cannot be empty']);
        }

        // Get or create the tag term
        $term = term_exists($tag_name, 'picpilot_image_tags');
        if (!$term) {
            $term = wp_insert_term($tag_name, 'picpilot_image_tags');
            if (is_wp_error($term)) {
                wp_send_json_error(['message' => 'Failed to create tag: ' . $term->get_error_message()]);
            }
        }

        // Add tag to attachment
        $result = wp_set_object_terms($attachment_id, $tag_name, 'picpilot_image_tags', true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'Failed to add tag: ' . $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Tag added successfully',
            'tag_name' => $tag_name,
            'attachment_id' => $attachment_id
        ]);
    }

    /**
     * Remove tag from image via AJAX
     */
    public static function ajax_remove_image_tag() {
        check_ajax_referer('picpilot_studio_generate', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $tag_name = sanitize_text_field($_POST['tag_name'] ?? '');

        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Invalid image ID']);
        }

        if (empty($tag_name)) {
            wp_send_json_error(['message' => 'Tag name cannot be empty']);
        }

        // Remove tag from attachment
        $result = wp_remove_object_terms($attachment_id, $tag_name, 'picpilot_image_tags');
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'Failed to remove tag: ' . $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Tag removed successfully',
            'tag_name' => $tag_name,
            'attachment_id' => $attachment_id
        ]);
    }

    /**
     * Get all available tags via AJAX
     */
    public static function ajax_get_all_tags() {
        check_ajax_referer('picpilot_studio_generate', 'nonce');

        $tags = get_terms([
            'taxonomy' => 'picpilot_image_tags',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($tags)) {
            wp_send_json_error(['message' => 'Failed to retrieve tags']);
        }

        $tag_data = [];
        foreach ($tags as $tag) {
            // Get accurate count of image attachments with this tag
            $attachments = get_objects_in_term($tag->term_id, 'picpilot_image_tags');
            $image_count = 0;
            
            if (is_array($attachments)) {
                foreach ($attachments as $attachment_id) {
                    if (wp_attachment_is_image($attachment_id)) {
                        $image_count++;
                    }
                }
            }
            
            $tag_data[] = [
                'id' => $tag->term_id,
                'name' => $tag->name,
                'count' => $image_count
            ];
        }

        wp_send_json_success(['tags' => $tag_data]);
    }


    /**
     * Filter media by selected tag (integrated with MediaList filter)
     */
    public static function filter_media_by_tags($query) {
        global $pagenow;
        
        if (!is_admin() || $pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }

        // Only proceed if we're on the media library and have a tag filter
        if (!isset($_GET['picpilot_tag_filter']) || empty($_GET['picpilot_tag_filter'])) {
            return;
        }

        // Ensure we're dealing with attachments
        if ($query->get('post_type') !== 'attachment') {
            return;
        }

        $tag_slug = sanitize_text_field($_GET['picpilot_tag_filter']);
        
        // Get existing tax_query to avoid conflicts with other filters
        $tax_query = $query->get('tax_query') ?: [];
        
        $tax_query[] = [
            'taxonomy' => 'picpilot_image_tags',
            'field' => 'slug',
            'terms' => $tag_slug
        ];

        $query->set('tax_query', $tax_query);
        
        // Ensure we only get image attachments when filtering by tags
        $query->set('post_mime_type', 'image');
    }

    /**
     * Add tags column to media list
     */
    public static function add_tags_column($columns) {
        $columns['picpilot_tags'] = __('Tags', 'pic-pilot-meta');
        return $columns;
    }

    /**
     * Display tags in the tags column
     */
    public static function display_tags_column($column_name, $attachment_id) {
        if ($column_name !== 'picpilot_tags') {
            return;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            echo '—';
            return;
        }

        $tags = wp_get_object_terms($attachment_id, 'picpilot_image_tags');
        
        if (empty($tags) || is_wp_error($tags)) {
            echo '<span class="picpilot-no-tags" style="color: #999;">' . esc_html__('No tags', 'pic-pilot-meta') . '</span>';
            echo '<br><button type="button" class="picpilot-add-tag-btn button-link" data-id="' . esc_attr($attachment_id) . '" style="font-size: 12px; color: #0073aa;">' . esc_html__('+ Add tag', 'pic-pilot-meta') . '</button>';
            return;
        }

        $tag_names = [];
        foreach ($tags as $tag) {
            $tag_names[] = sprintf(
                '<span class="picpilot-tag" data-tag="%s" data-id="%d" style="display: inline-block; background: #0073aa; color: white; padding: 2px 6px; margin: 1px; border-radius: 3px; font-size: 11px; cursor: pointer;" title="%s">%s <span class="picpilot-remove-tag" style="margin-left: 4px; cursor: pointer;">&times;</span></span>',
                esc_attr($tag->name),
                esc_attr($attachment_id),
                esc_attr__('Click × to remove', 'pic-pilot-meta'),
                esc_html($tag->name)
            );
        }

        echo wp_kses_post(implode(' ', $tag_names));
        echo '<br><button type="button" class="picpilot-add-tag-btn button-link" data-id="' . esc_attr($attachment_id) . '" style="font-size: 12px; color: #0073aa;">' . esc_html__('+ Add tag', 'pic-pilot-meta') . '</button>';
    }

    /**
     * Get tags for a specific attachment
     */
    public static function get_attachment_tags($attachment_id) {
        $tags = wp_get_object_terms($attachment_id, 'picpilot_image_tags');
        
        if (is_wp_error($tags)) {
            return [];
        }

        return $tags;
    }

    /**
     * Get all available tags
     */
    public static function get_all_tags() {
        $tags = get_terms([
            'taxonomy' => 'picpilot_image_tags',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($tags)) {
            return [];
        }

        return $tags;
    }
}