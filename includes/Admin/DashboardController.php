<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\Logger;

defined('ABSPATH') || exit;

class DashboardController {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_dashboard_submenu'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_pic_pilot_remove_scan', [__CLASS__, 'handle_remove_scan']);

        // Broken images scan AJAX handlers
        add_action('wp_ajax_pic_pilot_start_broken_scan', [__CLASS__, 'handle_start_broken_scan']);
        add_action('wp_ajax_pic_pilot_broken_scan_batch', [__CLASS__, 'handle_broken_scan_batch']);
        add_action('wp_ajax_pic_pilot_get_broken_results', [__CLASS__, 'handle_get_broken_results']);

        // Broken images action AJAX handlers
        add_action('wp_ajax_pic_pilot_export_broken_report', [__CLASS__, 'handle_export_broken_report']);

        // Initialize database manager
        DatabaseManager::init();
    }
    
    public static function add_dashboard_submenu() {
        add_submenu_page(
            'pic-pilot-meta',
            __('Dashboard', 'pic-pilot-meta'),
            __('Dashboard', 'pic-pilot-meta'),
            'manage_options',
            'pic-pilot-dashboard',
            [__CLASS__, 'render_dashboard_page']
        );

        add_submenu_page(
            'pic-pilot-meta',
            __('Broken Images', 'pic-pilot-meta'),
            __('Broken Images', 'pic-pilot-meta'),
            'manage_options',
            'pic-pilot-broken-images',
            [__CLASS__, 'render_broken_images_page']
        );
    }
    
    public static function enqueue_dashboard_assets($hook) {
        if ($hook !== 'pic-pilot-meta_page_pic-pilot-dashboard' && $hook !== 'pic-pilot-meta_page_pic-pilot-broken-images') {
            return;
        }
        
        wp_enqueue_script(
            'pic-pilot-dashboard',
            PIC_PILOT_META_URL . 'assets/js/dashboard.js',
            ['jquery'],
            PIC_PILOT_META_VERSION,
            true
        );
        
        wp_enqueue_style(
            'pic-pilot-dashboard',
            PIC_PILOT_META_URL . 'assets/css/dashboard.css',
            [],
            PIC_PILOT_META_VERSION
        );
        
        $settings = get_option('picpilot_meta_settings', []);
        
        wp_localize_script('pic-pilot-dashboard', 'picPilotDashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pic_pilot_dashboard'),
            'strings' => [
                'scan_starting' => __('Starting scan...', 'pic-pilot-meta'),
                'scan_progress' => __('Scanning page {current} of {total}...', 'pic-pilot-meta'),
                'scan_completed' => __('Scan completed successfully!', 'pic-pilot-meta'),
                'scan_failed' => __('Scan failed. Please try again.', 'pic-pilot-meta'),
                'confirm_new_scan' => __('This will start a new scan. Continue?', 'pic-pilot-meta'),
                'no_issues_found' => __('Great! No accessibility issues found.', 'pic-pilot-meta'),
                'loading' => __('Loading...', 'pic-pilot-meta')
            ]
        ]);
        
        // Pass settings to JavaScript for feature detection
        wp_localize_script('pic-pilot-dashboard', 'picPilotSettings', [
            'ai_features_enabled' => !empty($settings['openai_api_key']) || !empty($settings['gemini_api_key']),
            'auto_generate_both_enabled' => !empty($settings['enable_auto_generate_both']),
            'dangerous_rename_enabled' => !empty($settings['enable_dangerous_filename_rename']),
            // Smart generation features are now always enabled
            'alt_generation_enabled' => true,
            'title_generation_enabled' => true,
            'filename_generation_enabled' => true,
            'generate_nonce' => wp_create_nonce('picpilot_studio_generate')
        ]);
    }
    
    public static function render_dashboard_page() {
        $latest_scan = DatabaseManager::get_latest_scan();
        $stats = $latest_scan ? DatabaseManager::get_scan_stats($latest_scan['scan_id']) : null;

        include __DIR__ . '/templates/dashboard.php';
    }

    public static function render_broken_images_page() {
        include __DIR__ . '/templates/broken-images.php';
    }
    
    public static function get_dashboard_stats() {
        $latest_scan = DatabaseManager::get_latest_scan();
        
        if (!$latest_scan) {
            return [
                'has_scan' => false,
                'message' => __('No scans found. Click "Scan Now" to get started.', 'pic-pilot-meta')
            ];
        }
        
        $stats = DatabaseManager::get_scan_stats($latest_scan['scan_id']);
        $total_issues = $stats['missing_alt'] + $stats['missing_title'] - $stats['missing_both'];
        
        return [
            'has_scan' => true,
            'scan_date' => $latest_scan['completed_at'],
            'total_images' => (int)$stats['total_images'],
            'total_issues' => $total_issues,
            'missing_alt' => (int)$stats['missing_alt'],
            'missing_title' => (int)$stats['missing_title'],
            'missing_both' => (int)$stats['missing_both'],
            'critical_issues' => (int)$stats['critical_issues'],
            'high_issues' => (int)$stats['high_issues'],
            'medium_issues' => (int)$stats['medium_issues'],
            'pages_with_issues' => (int)$stats['pages_with_issues'],
            'completion_percentage' => $stats['total_images'] > 0 ? 
                round((($stats['total_images'] - $total_issues) / $stats['total_images']) * 100, 1) : 100
        ];
    }
    
    public static function get_recent_scans($limit = 5) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_history';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                scan_id,
                scan_type,
                status,
                pages_scanned,
                issues_found,
                started_at,
                completed_at,
                error_message
            FROM $table 
            ORDER BY started_at DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
    }
    
    public static function get_priority_breakdown($scan_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                CASE 
                    WHEN priority_score >= 8 THEN 'critical'
                    WHEN priority_score >= 6 THEN 'high'
                    WHEN priority_score >= 4 THEN 'medium'
                    ELSE 'low'
                END as priority_level,
                COUNT(*) as count,
                GROUP_CONCAT(DISTINCT page_type) as page_types
            FROM $table 
            WHERE scan_id = %s
            GROUP BY 
                CASE 
                    WHEN priority_score >= 8 THEN 'critical'
                    WHEN priority_score >= 6 THEN 'high'
                    WHEN priority_score >= 4 THEN 'medium'
                    ELSE 'low'
                END
            ORDER BY priority_score DESC
        ", $scan_id), ARRAY_A);
    }
    
    public static function get_page_types_summary($scan_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                page_type,
                COUNT(DISTINCT page_id) as pages_count,
                COUNT(*) as images_count,
                SUM(CASE WHEN alt_text_status IN ('missing', 'empty') THEN 1 ELSE 0 END) as missing_alt_count,
                SUM(CASE WHEN title_attr_status IN ('missing', 'empty') THEN 1 ELSE 0 END) as missing_title_count
            FROM $table 
            WHERE scan_id = %s
            GROUP BY page_type
            ORDER BY images_count DESC
        ", $scan_id), ARRAY_A);
    }

    public static function handle_remove_scan() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }

        $scan_id = sanitize_text_field($_POST['scan_id']);

        if (empty($scan_id)) {
            wp_send_json_error([
                'message' => __('Invalid scan ID.', 'pic-pilot-meta')
            ]);
        }

        global $wpdb;

        // Remove scan results
        $results_table = $wpdb->prefix . 'picpilot_scan_results';
        $wpdb->delete($results_table, ['scan_id' => $scan_id]);

        // Remove scan history
        $history_table = $wpdb->prefix . 'picpilot_scan_history';
        $deleted = $wpdb->delete($history_table, ['scan_id' => $scan_id]);

        if ($deleted === false) {
            wp_send_json_error([
                'message' => __('Failed to remove scan.', 'pic-pilot-meta')
            ]);
        }

        Logger::log("[SCAN] Removed scan {$scan_id}");

        wp_send_json_success([
            'message' => __('Scan removed successfully.', 'pic-pilot-meta')
        ]);
    }

    // Broken Images Scan Methods

    public static function handle_start_broken_scan() {
        try {
            check_ajax_referer('pic_pilot_dashboard', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
            }

            $options = [
                'check_media_library' => !empty($_POST['check_media_library']),
                'check_content_images' => !empty($_POST['check_content_images']),
                'check_featured_images' => !empty($_POST['check_featured_images']),
                'check_external_images' => !empty($_POST['check_external_images'])
            ];

            Logger::log("[BROKEN] Starting scan with options: " . json_encode($options));

            // Validate at least one option is selected
            if (!array_filter($options)) {
                wp_send_json_error([
                    'message' => __('Please select at least one scan option.', 'pic-pilot-meta')
                ]);
            }

            // Create a temporary scan session
            $scan_id = 'broken_' . time();
            set_transient("pic_pilot_broken_scan_{$scan_id}", [
                'status' => 'running',
                'options' => $options,
                'started_at' => current_time('mysql'),
                'total_items' => 0,
                'processed_items' => 0,
                'broken_found' => 0
            ], HOUR_IN_SECONDS);

            // Get total items to scan
            $total_items = self::get_broken_scan_total($options);

            update_option("pic_pilot_broken_scan_{$scan_id}_total", $total_items);

            Logger::log("[BROKEN] Started broken images scan {$scan_id} - {$total_items} items to check");

            wp_send_json_success([
                'scan_id' => $scan_id,
                'total_items' => $total_items,
                'message' => __('Broken images scan started', 'pic-pilot-meta')
            ]);

        } catch (Exception $e) {
            Logger::log("[BROKEN] Error starting scan: " . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to start scan: ', 'pic-pilot-meta') . $e->getMessage()
            ]);
        }
    }

    public static function handle_broken_scan_batch() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }

        $scan_id = sanitize_text_field($_POST['scan_id']);
        $batch_start = (int)($_POST['batch_start'] ?? 0);
        $batch_size = (int)($_POST['batch_size'] ?? 10);

        $scan_data = get_transient("pic_pilot_broken_scan_{$scan_id}");

        if (!$scan_data || $scan_data['status'] !== 'running') {
            wp_send_json_error(['message' => __('Scan not found or not running.', 'pic-pilot-meta')]);
        }

        $results = self::process_broken_scan_batch($scan_id, $scan_data['options'], $batch_start, $batch_size);

        $total_items = get_option("pic_pilot_broken_scan_{$scan_id}_total", 0);
        $processed_items = $batch_start + $batch_size;
        $is_complete = $processed_items >= $total_items;

        // Update scan progress
        $scan_data['processed_items'] = min($processed_items, $total_items);
        $scan_data['broken_found'] = ($scan_data['broken_found'] ?? 0) + count($results);

        if ($is_complete) {
            $scan_data['status'] = 'completed';
            $scan_data['completed_at'] = current_time('mysql');
        }

        set_transient("pic_pilot_broken_scan_{$scan_id}", $scan_data, HOUR_IN_SECONDS);

        wp_send_json_success([
            'scan_id' => $scan_id,
            'processed_items' => $scan_data['processed_items'],
            'total_items' => $total_items,
            'broken_found' => count($results),
            'total_broken' => $scan_data['broken_found'],
            'is_complete' => $is_complete,
            'results' => $results,
            'progress_percentage' => $total_items > 0 ? round(($scan_data['processed_items'] / $total_items) * 100, 1) : 100
        ]);
    }

    public static function handle_get_broken_results() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');

        $scan_id = sanitize_text_field($_POST['scan_id'] ?? '');
        $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true);

        $results = get_option("pic_pilot_broken_results_{$scan_id}", []);

        // Apply filters if provided
        if (!empty($filters['issue_type'])) {
            $results = array_filter($results, function($item) use ($filters) {
                return $item['issue_type'] === $filters['issue_type'];
            });
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $results = array_filter($results, function($item) use ($search) {
                return strpos(strtolower($item['image_url'] ?? ''), $search) !== false ||
                       strpos(strtolower($item['location'] ?? ''), $search) !== false;
            });
        }

        wp_send_json_success([
            'results' => array_values($results),
            'total_count' => count($results)
        ]);
    }

    private static function get_broken_scan_total($options) {
        $total = 0;

        try {
            if ($options['check_media_library']) {
                $attachments_count = wp_count_attachments('image');
                $total += $attachments_count->inherit ?? 0;
            }

            if ($options['check_content_images'] || $options['check_featured_images']) {
                $posts = get_posts([
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields' => 'ids'
                ]);
                $total += count($posts);
            }
        } catch (Exception $e) {
            Logger::log("[BROKEN] Error calculating scan total: " . $e->getMessage());
            $total = 100; // Fallback value
        }

        return max(1, $total); // Ensure at least 1 to avoid division by zero
    }

    private static function process_broken_scan_batch($scan_id, $options, $batch_start, $batch_size) {
        $broken_items = [];
        $processed = 0;

        // Check Media Library attachments
        if ($options['check_media_library'] && $processed < $batch_size) {
            $attachments = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'any',
                'numberposts' => $batch_size - $processed,
                'offset' => $batch_start,
                'fields' => 'ids'
            ]);

            foreach ($attachments as $attachment_id) {
                $file_path = get_attached_file($attachment_id);
                $image_url = wp_get_attachment_url($attachment_id);

                if (!$file_path || !file_exists($file_path)) {
                    $broken_items[] = [
                        'type' => 'attachment',
                        'id' => $attachment_id,
                        'image_url' => $image_url ?: '',
                        'location' => 'Media Library',
                        'issue_type' => 'missing-file',
                        /* translators: %s: filename of the missing image file */
                        'details' => sprintf(__('File missing: %s', 'pic-pilot-meta'), basename($file_path ?: '')),
                        'post_title' => get_the_title($attachment_id)
                    ];
                }

                $processed++;
                if ($processed >= $batch_size) break;
            }
        }

        // Check posts for content images and featured images
        if (($options['check_content_images'] || $options['check_featured_images']) && $processed < $batch_size) {
            $remaining = $batch_size - $processed;

            // Calculate offset for posts
            $attachments_count = 0;
            if ($options['check_media_library']) {
                try {
                    $attachments_count_obj = wp_count_attachments('image');
                    $attachments_count = $attachments_count_obj->inherit ?? 0;
                } catch (Exception $e) {
                    $attachments_count = 0;
                }
            }
            $offset = max(0, $batch_start - $attachments_count);

            $posts = get_posts([
                'post_type' => 'any',
                'post_status' => 'publish',
                'numberposts' => $remaining,
                'offset' => $offset
            ]);

            foreach ($posts as $post) {
                // Check featured image
                if ($options['check_featured_images']) {
                    $featured_id = get_post_thumbnail_id($post->ID);
                    if ($featured_id) {
                        $file_path = get_attached_file($featured_id);
                        if (!$file_path || !file_exists($file_path)) {
                            $broken_items[] = [
                                'type' => 'featured_image',
                                'id' => $featured_id,
                                'post_id' => $post->ID,
                                'image_url' => wp_get_attachment_url($featured_id) ?: '',
                                /* translators: %s: post title where the featured image is missing */
                                'location' => sprintf(__('Featured image in "%s"', 'pic-pilot-meta'), $post->post_title),
                                'issue_type' => 'missing-file',
                                'details' => __('Featured image file is missing', 'pic-pilot-meta'),
                                'post_title' => $post->post_title
                            ];
                        }
                    }
                }

                // Check content images
                if ($options['check_content_images']) {
                    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);

                    foreach ($matches[1] as $img_url) {
                        // Skip data URIs and relative URLs
                        if (strpos($img_url, 'data:') === 0 || strpos($img_url, '//') === false) {
                            continue;
                        }

                        $site_url = home_url();
                        $is_internal = strpos($img_url, $site_url) === 0;

                        if ($is_internal) {
                            // Check if internal image exists
                            $file_path = str_replace(content_url(), WP_CONTENT_DIR, $img_url);
                            if (!file_exists($file_path)) {
                                $broken_items[] = [
                                    'type' => 'content_image',
                                    'post_id' => $post->ID,
                                    'image_url' => $img_url,
                                    /* translators: %s: post title where the content image is broken */
                                    'location' => sprintf(__('Content in "%s"', 'pic-pilot-meta'), $post->post_title),
                                    'issue_type' => 'broken-link',
                                    'details' => __('Image file does not exist', 'pic-pilot-meta'),
                                    'post_title' => $post->post_title
                                ];
                            }
                        } else if ($options['check_external_images']) {
                            // Check external image (simple head request)
                            $response = wp_remote_head($img_url, ['timeout' => 10]);
                            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
                                $broken_items[] = [
                                    'type' => 'external_image',
                                    'post_id' => $post->ID,
                                    'image_url' => $img_url,
                                    /* translators: %s: post title where the external image is broken */
                                    'location' => sprintf(__('Content in "%s"', 'pic-pilot-meta'), $post->post_title),
                                    'issue_type' => 'external-link',
                                    'details' => __('External image is not accessible', 'pic-pilot-meta'),
                                    'post_title' => $post->post_title
                                ];
                            }
                        }
                    }
                }

                $processed++;
                if ($processed >= $batch_size) break;
            }
        }

        // Store results for this batch
        $existing_results = get_option("pic_pilot_broken_results_{$scan_id}", []);
        $all_results = array_merge($existing_results, $broken_items);
        update_option("pic_pilot_broken_results_{$scan_id}", $all_results);

        return $broken_items;
    }

    // Broken Images Action Handlers

    public static function handle_export_broken_report() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }

        $scan_id = sanitize_text_field($_POST['scan_id'] ?? '');

        if (empty($scan_id)) {
            wp_send_json_error(['message' => __('Invalid scan ID.', 'pic-pilot-meta')]);
        }

        try {
            $results = get_option("pic_pilot_broken_results_{$scan_id}", []);

            if (empty($results)) {
                wp_send_json_error(['message' => __('No broken images data to export.', 'pic-pilot-meta')]);
            }

            // Generate CSV content
            $csv_data = [];
            $csv_data[] = ['Type', 'Image URL', 'Location', 'Issue Type', 'Details', 'Post Title', 'Post ID', 'Attachment ID'];

            foreach ($results as $item) {
                $csv_data[] = [
                    $item['type'] ?? '',
                    $item['image_url'] ?? '',
                    $item['location'] ?? '',
                    $item['issue_type'] ?? '',
                    $item['details'] ?? '',
                    $item['post_title'] ?? '',
                    $item['post_id'] ?? '',
                    $item['id'] ?? ''
                ];
            }

            // Create temporary file
            $upload_dir = wp_upload_dir();
            $filename = 'broken-images-report-' . $scan_id . '-' . date('Y-m-d-H-i-s') . '.csv';
            $file_path = $upload_dir['path'] . '/' . $filename;

            $handle = fopen($file_path, 'w');
            if ($handle === false) {
                wp_send_json_error(['message' => __('Could not create export file.', 'pic-pilot-meta')]);
            }

            foreach ($csv_data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);

            $download_url = $upload_dir['url'] . '/' . $filename;

            Logger::log("[BROKEN] Generated export report: {$filename}");

            wp_send_json_success([
                'download_url' => $download_url,
                'filename' => $filename,
                'message' => __('Export completed successfully.', 'pic-pilot-meta')
            ]);

        } catch (Exception $e) {
            Logger::log("[BROKEN] Error generating export: " . $e->getMessage());
            wp_send_json_error(['message' => __('Error generating export: ', 'pic-pilot-meta') . $e->getMessage()]);
        }
    }
}