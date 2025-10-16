<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\Logger;

defined('ABSPATH') || exit;

class ScanController {
    
    public static function init() {
        add_action('wp_ajax_pic_pilot_start_scan', [__CLASS__, 'handle_start_scan']);
        add_action('wp_ajax_pic_pilot_scan_batch', [__CLASS__, 'handle_scan_batch']);
        add_action('wp_ajax_pic_pilot_get_scan_status', [__CLASS__, 'handle_get_scan_status']);
        add_action('wp_ajax_pic_pilot_cancel_scan', [__CLASS__, 'handle_cancel_scan']);
        add_action('wp_ajax_pic_pilot_get_issues', [__CLASS__, 'handle_get_issues']);
    }
    
    public static function handle_start_scan() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }
        
        $scan_type = sanitize_text_field($_POST['scan_type'] ?? 'partial');
        $filters = [];
        
        // Parse filters from request
        if (!empty($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        // Get pages to scan based on type and filters
        $pages = self::get_pages_to_scan($scan_type, $filters);
        
        if (empty($pages)) {
            wp_send_json_error([
                'message' => __('No pages found to scan.', 'pic-pilot-meta')
            ]);
        }
        
        // Create scan session
        $scan_id = DatabaseManager::create_scan_session($scan_type, $filters);
        
        if (!$scan_id) {
            wp_send_json_error([
                'message' => __('Failed to create scan session.', 'pic-pilot-meta')
            ]);
        }
        
        // Update scan with total pages count
        DatabaseManager::update_scan_progress($scan_id, [
            'pages_total' => count($pages),
            'status' => 'running'
        ]);
        
        Logger::log("[SCAN] Started scan {$scan_id} with " . count($pages) . " pages");
        
        wp_send_json_success([
            'scan_id' => $scan_id,
            'total_pages' => count($pages),
            'message' => __('Scan started successfully', 'pic-pilot-meta')
        ]);
    }
    
    public static function handle_scan_batch() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }
        
        $scan_id = sanitize_text_field($_POST['scan_id']);
        $batch_start = (int)($_POST['batch_start'] ?? 0);
        $batch_size = (int)($_POST['batch_size'] ?? 15);
        
        // Get scan info
        global $wpdb;
        $scan_table = $wpdb->prefix . 'picpilot_scan_history';
        $scan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $scan_table WHERE scan_id = %s", $scan_id), ARRAY_A);
        
        if (!$scan || $scan['status'] !== 'running') {
            wp_send_json_error([
                'message' => __('Scan not found or not running.', 'pic-pilot-meta')
            ]);
        }
        
        // Get pages for this batch
        $filters = json_decode($scan['scan_filters'], true) ?: [];
        $all_pages = self::get_pages_to_scan($scan['scan_type'], $filters);
        $batch_pages = array_slice($all_pages, $batch_start, $batch_size);
        
        $issues_found = 0;
        $images_scanned = 0;
        
        foreach ($batch_pages as $page) {
            $page_issues = self::scan_page_images($scan_id, $page);
            $issues_found += $page_issues['issues_count'];
            $images_scanned += $page_issues['images_count'];
        }
        
        // Update scan progress
        $pages_scanned = $batch_start + count($batch_pages);
        $total_issues = $scan['issues_found'] + $issues_found;
        
        DatabaseManager::update_scan_progress($scan_id, [
            'pages_scanned' => $pages_scanned,
            'images_found' => $scan['images_found'] + $images_scanned,
            'issues_found' => $total_issues
        ]);
        
        // Check if scan is complete
        $is_complete = $pages_scanned >= $scan['pages_total'];
        
        if ($is_complete) {
            // Update final scan statistics
            $stats = DatabaseManager::get_scan_stats($scan_id);
            DatabaseManager::update_scan_progress($scan_id, [
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'issues_critical' => $stats['critical_issues'],
                'issues_high' => $stats['high_issues'],
                'issues_medium' => $stats['medium_issues']
            ]);
            
            Logger::log("[SCAN] Completed scan {$scan_id} - {$pages_scanned} pages, {$total_issues} issues found");
        }
        
        wp_send_json_success([
            'scan_id' => $scan_id,
            'pages_scanned' => $pages_scanned,
            'total_pages' => $scan['pages_total'],
            'issues_found' => $issues_found,
            'total_issues' => $total_issues,
            'is_complete' => $is_complete,
            'progress_percentage' => round(($pages_scanned / $scan['pages_total']) * 100, 1)
        ]);
    }
    
    public static function handle_get_scan_status() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        $scan_id = sanitize_text_field($_POST['scan_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'picpilot_scan_history';
        $scan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE scan_id = %s", $scan_id), ARRAY_A);
        
        if (!$scan) {
            wp_send_json_error(['message' => __('Scan not found.', 'pic-pilot-meta')]);
        }
        
        wp_send_json_success($scan);
    }
    
    public static function handle_cancel_scan() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        $scan_id = sanitize_text_field($_POST['scan_id']);
        
        DatabaseManager::update_scan_progress($scan_id, [
            'status' => 'cancelled',
            'completed_at' => current_time('mysql')
        ]);
        
        Logger::log("[SCAN] Cancelled scan {$scan_id}");
        
        wp_send_json_success(['message' => __('Scan cancelled.', 'pic-pilot-meta')]);
    }
    
    public static function handle_get_issues() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        $scan_id = sanitize_text_field($_POST['scan_id'] ?? '');
        $page = (int)($_POST['page'] ?? 1);
        $per_page = (int)($_POST['per_page'] ?? 25);
        $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true);
        
        if (empty($scan_id)) {
            $latest_scan = DatabaseManager::get_latest_scan();
            $scan_id = $latest_scan['scan_id'] ?? '';
        }
        
        if (empty($scan_id)) {
            wp_send_json_error(['message' => __('No scan data available.', 'pic-pilot-meta')]);
        }
        
        $results = DatabaseManager::get_scan_results($scan_id, $filters, $page, $per_page);
        
        // Format results for display
        foreach ($results['results'] as &$result) {
            $result['formatted_date'] = mysql2date('M j, Y', $result['created_at']);
            $result['image_size_formatted'] = size_format($result['image_filesize']);
            $result['priority_label'] = self::get_priority_label($result['priority_score']);
            $result['status_labels'] = self::get_status_labels($result['alt_text_status'], $result['title_attr_status']);
        }
        
        wp_send_json_success($results);
    }
    
    private static function get_pages_to_scan($scan_type, $filters = []) {
        $args = [
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        switch ($scan_type) {
            case 'posts':
                $args['post_type'] = 'post';
                break;
            case 'pages':
                $args['post_type'] = 'page';
                break;
            case 'full':
                $args['post_type'] = 'any';
                break;
            default: // partial - scan all public post types (no date restriction by default)
                $args['post_type'] = self::get_scannable_post_types();
                // Removed default date restriction to scan all content
        }
        
        // Apply additional filters
        if (!empty($filters['post_type'])) {
            $args['post_type'] = $filters['post_type'];
        }
        
        if (!empty($filters['date_range'])) {
            switch ($filters['date_range']) {
                case '7days':
                    $args['date_query'] = [['after' => '7 days ago']];
                    break;
                case '30days':
                    $args['date_query'] = [['after' => '30 days ago']];
                    break;
                case '90days':
                    $args['date_query'] = [['after' => '90 days ago']];
                    break;
            }
        }
        
        Logger::log("[SCAN] Query args: " . json_encode($args));
        
        $query = new \WP_Query($args);
        $posts = $query->posts;
        
        Logger::log("[SCAN] Found " . count($posts) . " posts/pages to scan");
        
        return $posts;
    }
    
    private static function scan_page_images($scan_id, $page_id) {
        $post = get_post($page_id);
        if (!$post) {
            Logger::log("[SCAN] Page ID $page_id not found");
            return ['images_count' => 0, 'issues_count' => 0];
        }
        
        $images_found = [];
        $issues_count = 0;
        
        Logger::log("[SCAN] Scanning page: {$post->post_title} (ID: $page_id)");
        
        // Get featured image
        $featured_image_id = get_post_thumbnail_id($page_id);
        if ($featured_image_id) {
            $images_found[] = [
                'id' => $featured_image_id,
                'type' => 'featured',
                'position' => 1,
                'is_virtual' => false
            ];
            Logger::log("[SCAN] Found featured image: $featured_image_id");
        }
        
        // Parse content for images
        $content = $post->post_content;
        Logger::log("[SCAN] Content length: " . strlen($content) . " characters");
        
        preg_match_all('/<img[^>]+>/i', $content, $img_matches);
        Logger::log("[SCAN] Found " . count($img_matches[0]) . " img tags in content");
        
        $position = $featured_image_id ? 2 : 1;
        
        foreach ($img_matches[0] as $img_tag) {
            Logger::log("[SCAN] Processing img tag: " . substr($img_tag, 0, 100) . "...");
            
            $image_id = null;
            
            // Method 1: Extract image ID from WordPress wp-image-ID class
            if (preg_match('/wp-image-(\d+)/i', $img_tag, $id_match)) {
                $image_id = (int)$id_match[1];
                Logger::log("[SCAN] Found wp-image-ID: $image_id");
                
                if (!wp_attachment_is_image($image_id)) {
                    Logger::log("[SCAN] wp-image-ID $image_id is not a valid attachment, trying URL method");
                    $image_id = null;
                }
            }
            
            // Method 2: If no wp-image-ID or invalid, try to find by URL
            if (!$image_id) {
                if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                    $image_url = $src_match[1];
                    Logger::log("[SCAN] Extracted image URL: $image_url");
                    
                    // Try to find attachment by URL
                    $image_id = self::get_attachment_id_by_url($image_url);
                    if ($image_id) {
                        Logger::log("[SCAN] Found attachment ID by URL: $image_id");
                    } else {
                        Logger::log("[SCAN] Could not find attachment ID for URL: $image_url");
                    }
                }
            }
            
            // Method 3: If still no ID, create a virtual image record for analysis
            if (!$image_id) {
                if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                    $image_url = $src_match[1];
                    
                    // Skip obviously broken URLs
                    if (empty($image_url) || $image_url === '#' || strpos($image_url, 'placeholder') !== false) {
                        Logger::log("[SCAN] Skipping broken/placeholder image: $image_url");
                        continue;
                    }
                    
                    // Only process images from this WordPress site
                    $site_url = home_url();
                    if (strpos($image_url, $site_url) === 0 || strpos($image_url, '/wp-content/') !== false) {
                        $images_found[] = [
                            'id' => 'url_' . md5($image_url), // Virtual ID for URL-based images
                            'url' => $image_url,
                            'type' => 'inline',
                            'position' => $position++,
                            'img_tag' => $img_tag,
                            'is_virtual' => true
                        ];
                        Logger::log("[SCAN] Added virtual image for URL: $image_url");
                    } else {
                        Logger::log("[SCAN] Skipping external image: $image_url");
                    }
                }
            } else {
                // Valid WordPress attachment found
                $images_found[] = [
                    'id' => $image_id,
                    'type' => 'inline',
                    'position' => $position++,
                    'img_tag' => $img_tag,
                    'is_virtual' => false
                ];
                Logger::log("[SCAN] Added WordPress attachment image: $image_id");
            }
        }
        
        Logger::log("[SCAN] Total images found on page: " . count($images_found));
        
        // Analyze each image
        foreach ($images_found as $image) {
            $issue_data = self::analyze_image_accessibility($page_id, $image, $post);
            if ($issue_data) {
                DatabaseManager::save_scan_result($scan_id, $issue_data);
                Logger::log("[SCAN] Saved scan result for image {$image['id']}: alt={$issue_data['alt_text_status']}, title={$issue_data['title_attr_status']}");
                
                if ($issue_data['alt_text_status'] !== 'present' || $issue_data['title_attr_status'] !== 'present') {
                    $issues_count++;
                    Logger::log("[SCAN] Found accessibility issue with image {$image['id']}");
                }
            } else {
                Logger::log("[SCAN] No issue data returned for image {$image['id']}");
            }
        }
        
        Logger::log("[SCAN] Page scan complete: " . count($images_found) . " images, $issues_count issues");
        
        return [
            'images_count' => count($images_found),
            'issues_count' => $issues_count
        ];
    }
    
    private static function analyze_image_accessibility($page_id, $image_data, $post) {
        $image_id = $image_data['id'];
        $is_virtual = $image_data['is_virtual'] ?? false;
        
        Logger::log("[SCAN] Analyzing image accessibility for image ID: $image_id (virtual: " . ($is_virtual ? 'yes' : 'no') . ")");
        
        if ($is_virtual) {
            // Handle virtual images (identified by URL only)
            $image_url = $image_data['url'];
            $attachment = null;
        } else {
            // Handle regular WordPress attachments
            $attachment = get_post($image_id);
            if (!$attachment) {
                Logger::log("[SCAN] Attachment not found for image ID: $image_id");
                return null;
            }
        }
        
        // Get image metadata and alt text
        if ($is_virtual) {
            // For virtual images, extract info from the img tag
            $metadata = null;
            $image_url = $image_data['url'];
            $alt_text = '';
            
            // Extract alt text from img tag
            if (preg_match('/alt=["\']([^"\']*)["\']/', $image_data['img_tag'], $alt_match)) {
                $alt_text = $alt_match[1];
            }
            
            $alt_status = empty($alt_text) ? 'missing' : (trim($alt_text) === '' ? 'empty' : 'present');
            
        } else {
            // For WordPress attachments
            $metadata = wp_get_attachment_metadata($image_id);
            $image_url = wp_get_attachment_url($image_id);
            $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            $alt_status = empty($alt_text) ? 'missing' : (trim($alt_text) === '' ? 'empty' : 'present');
        }
        
        Logger::log("[SCAN] Image $image_id alt text: '$alt_text' (status: $alt_status)");
        
        // Check title attribute (from the img tag if available)
        $title_attr = '';
        $title_status = 'missing';
        
        if (!empty($image_data['img_tag'])) {
            Logger::log("[SCAN] Checking img tag for title attribute: " . substr($image_data['img_tag'], 0, 200));
            if (preg_match('/title=["\']([^"\']*)["\']/', $image_data['img_tag'], $title_match)) {
                $title_attr = $title_match[1];
                $title_status = empty($title_attr) ? 'empty' : 'present';
                Logger::log("[SCAN] Found title attribute: '$title_attr' (status: $title_status)");
            } else {
                Logger::log("[SCAN] No title attribute found in img tag");
            }
        } else {
            Logger::log("[SCAN] No img tag available for title check (featured image)");
        }
        
        // Extract context information
        $content = $post->post_content;
        $context = self::extract_image_context($content, $image_data);
        
        // Calculate priority score
        $priority_score = self::calculate_priority_score($image_data, $alt_status, $title_status, $post);
        
        // Get file size and caption
        if ($is_virtual) {
            $image_filesize = null; // Can't get file size for virtual images easily
            $image_caption = '';
        } else {
            $image_filesize = filesize(get_attached_file($image_id)) ?: null;
            $image_caption = wp_get_attachment_caption($image_id) ?: '';
        }
        
        return [
            'page_id' => $page_id,
            'page_url' => get_permalink($page_id),
            'page_title' => $post->post_title,
            'page_type' => $post->post_type,
            'page_modified' => $post->post_modified,
            'image_id' => $image_id,
            'image_url' => $image_url,
            'image_filename' => basename($image_url),
            'image_width' => $metadata['width'] ?? null,
            'image_height' => $metadata['height'] ?? null,
            'image_filesize' => $image_filesize,
            'alt_text_status' => $alt_status,
            'title_attr_status' => $title_status,
            'alt_text_current' => $alt_text,
            'title_attr_current' => $title_attr,
            'image_position' => $image_data['position'],
            'image_type' => $image_data['type'],
            'context_before' => $context['before'] ?? '',
            'context_after' => $context['after'] ?? '',
            'section_heading' => $context['heading'] ?? '',
            'image_caption' => $image_caption,
            'priority_score' => $priority_score
        ];
    }
    
    private static function extract_image_context($content, $image_data) {
        $context = ['before' => '', 'after' => '', 'heading' => ''];
        
        if (empty($image_data['img_tag'])) {
            return $context;
        }
        
        $img_tag = $image_data['img_tag'];
        $img_pos = strpos($content, $img_tag);
        
        if ($img_pos === false) {
            return $context;
        }
        
        // Get surrounding text (50 chars before and after)
        $before_start = max(0, $img_pos - 50);
        $context['before'] = substr($content, $before_start, $img_pos - $before_start);
        $context['before'] = wp_strip_all_tags($context['before']);

        $after_start = $img_pos + strlen($img_tag);
        $context['after'] = substr($content, $after_start, 50);
        $context['after'] = wp_strip_all_tags($context['after']);
        
        // Find nearest heading
        $before_content = substr($content, 0, $img_pos);
        if (preg_match_all('/<h[1-6][^>]*>([^<]+)<\/h[1-6]>/i', $before_content, $heading_matches)) {
            $context['heading'] = end($heading_matches[1]);
        }
        
        return $context;
    }
    
    private static function calculate_priority_score($image_data, $alt_status, $title_status, $post) {
        $score = 5; // Base score
        
        // Image type priority
        switch ($image_data['type']) {
            case 'featured':
                $score += 3;
                break;
            case 'inline':
                if ($image_data['position'] <= 2) {
                    $score += 2; // First/second image is more important
                }
                break;
        }
        
        // Missing both attributes is critical
        if ($alt_status !== 'present' && $title_status !== 'present') {
            $score += 3;
        }
        
        // Alt text is more important than title
        if ($alt_status !== 'present') {
            $score += 2;
        }
        
        // Page type importance
        if ($post->post_type === 'page') {
            $score += 1; // Pages are generally more important than posts
        }
        
        return min(10, $score); // Cap at 10
    }
    
    private static function get_priority_label($score) {
        if ($score >= 8) return 'Critical';
        if ($score >= 6) return 'High';
        if ($score >= 4) return 'Medium';
        return 'Low';
    }
    
    private static function get_status_labels($alt_status, $title_status) {
        $labels = [];
        
        if ($alt_status !== 'present') {
            $labels[] = 'Missing Alt Text';
        }
        
        if ($title_status !== 'present') {
            $labels[] = 'Missing Title';
        }
        
        if (empty($labels)) {
            $labels[] = 'Complete';
        }
        
        return $labels;
    }
    
    private static function get_attachment_id_by_url($image_url) {
        global $wpdb;
        
        // Clean the URL - remove size variations (-150x150, -1024x768, etc.)
        $clean_url = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $image_url);
        
        // Try exact match first
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
            $image_url
        ));
        
        if ($attachment_id) {
            return (int)$attachment_id;
        }
        
        // Try with cleaned URL
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
            $clean_url
        ));
        
        if ($attachment_id) {
            return (int)$attachment_id;
        }
        
        // Try searching by filename in meta
        $filename = basename($clean_url);
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $filename
        ));
        
        return $attachment_id ? (int)$attachment_id : null;
    }
    
    private static function get_scannable_post_types() {
        // Get all public post types that likely have content with images
        $post_types = get_post_types([
            'public' => true,
            'exclude_from_search' => false
        ], 'names');
        
        // Remove attachment post type (we don't scan attachment pages themselves)
        unset($post_types['attachment']);
        
        // Common post types that should be included
        $default_types = ['post', 'page'];
        
        // Add WooCommerce product if available
        if (post_type_exists('product')) {
            $default_types[] = 'product';
        }
        
        // Merge with discovered public post types
        $scannable_types = array_unique(array_merge($default_types, array_values($post_types)));
        
        Logger::log("[SCAN] Scannable post types: " . implode(', ', $scannable_types));
        
        return $scannable_types;
    }
    
    /**
     * Get available post types with their labels for filtering
     */
    public static function get_available_post_types() {
        $post_types = get_post_types([
            'public' => true,
            'exclude_from_search' => false
        ], 'objects');
        
        // Remove attachment post type
        unset($post_types['attachment']);
        
        $available_types = [];
        
        foreach ($post_types as $post_type) {
            $available_types[$post_type->name] = $post_type->label;
        }
        
        // Ensure common types are always included even if not "public"
        $essential_types = [
            'post' => 'Posts',
            'page' => 'Pages'
        ];
        
        foreach ($essential_types as $type => $label) {
            if (post_type_exists($type) && !isset($available_types[$type])) {
                $available_types[$type] = $label;
            }
        }
        
        // Add WooCommerce product if available
        if (post_type_exists('product') && !isset($available_types['product'])) {
            $available_types['product'] = 'Products';
        }
        
        // Sort by label for better UX
        asort($available_types);
        
        return $available_types;
    }
    
    /**
     * Get post types that actually have scanned content
     */
    public static function get_scanned_post_types($scan_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        
        if ($scan_id) {
            $sql = "SELECT DISTINCT page_type FROM $table WHERE scan_id = %s ORDER BY page_type";
            $results = $wpdb->get_col($wpdb->prepare($sql, $scan_id));
        } else {
            // Get from latest scan
            $latest_scan = self::get_latest_scan();
            if (!$latest_scan) {
                return [];
            }
            
            $sql = "SELECT DISTINCT page_type FROM $table WHERE scan_id = %s ORDER BY page_type";
            $results = $wpdb->get_col($wpdb->prepare($sql, $latest_scan['scan_id']));
        }
        
        $post_types_with_labels = [];
        foreach ($results as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            $label = $post_type_obj ? $post_type_obj->label : ucfirst($post_type);
            $post_types_with_labels[$post_type] = $label;
        }
        
        return $post_types_with_labels;
    }
    
    /**
     * Get latest scan data
     */
    private static function get_latest_scan() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_history';
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE status = 'completed' 
            ORDER BY completed_at DESC 
            LIMIT 1
        "), ARRAY_A);
    }
}