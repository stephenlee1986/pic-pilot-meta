<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\Logger;

defined('ABSPATH') || exit;

class ExportController {
    
    public static function init() {
        add_action('wp_ajax_pic_pilot_export_csv', [__CLASS__, 'handle_export_csv']);
        add_action('wp_ajax_pic_pilot_export_pdf', [__CLASS__, 'handle_export_pdf']);
    }
    
    public static function handle_export_csv() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }
        
        $scan_id = sanitize_text_field($_POST['scan_id'] ?? '');
        $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true);
        
        if (empty($scan_id)) {
            $latest_scan = DatabaseManager::get_latest_scan();
            $scan_id = $latest_scan['scan_id'] ?? '';
        }
        
        if (empty($scan_id)) {
            wp_send_json_error(['message' => __('No scan data available for export.', 'pic-pilot-meta')]);
        }
        
        // Get all results (no pagination for export)
        $results = DatabaseManager::get_scan_results($scan_id, $filters, 1, 999999);
        
        if (empty($results['results'])) {
            wp_send_json_error(['message' => __('No data found to export.', 'pic-pilot-meta')]);
        }
        
        $csv_data = self::generate_csv_data($results['results']);
        $filename = self::generate_filename('csv', $scan_id);
        
        // Return download URL instead of direct file output for AJAX
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        if (file_put_contents($file_path, $csv_data)) {
            wp_send_json_success([
                'download_url' => $file_url,
                'filename' => $filename,
                'message' => __('CSV export generated successfully.', 'pic-pilot-meta')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate CSV export.', 'pic-pilot-meta')]);
        }
    }
    
    public static function handle_export_pdf() {
        check_ajax_referer('pic_pilot_dashboard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'pic-pilot-meta'));
        }
        
        // PDF export is a pro feature placeholder
        wp_send_json_error([
            'message' => __('PDF export is available in Pic Pilot Meta Pro.', 'pic-pilot-meta'),
            'pro_feature' => true
        ]);
    }
    
    private static function generate_csv_data($results) {
        $csv_data = [];
        
        // CSV Headers
        $headers = [
            'Page Title',
            'Page URL',
            'Page Type',
            'Image Filename',
            'Image URL',
            'Image Size',
            'Alt Text Status',
            'Title Attribute Status',
            'Current Alt Text',
            'Current Title Attribute',
            'Priority Level',
            'Priority Score',
            'Image Position',
            'Image Type',
            'Section Heading',
            'Context Before',
            'Context After',
            'Image Caption',
            'Last Modified',
            'Issues'
        ];
        
        $csv_data[] = $headers;
        
        // Data rows
        foreach ($results as $result) {
            $issues = [];
            if ($result['alt_text_status'] !== 'present') {
                $issues[] = 'Missing Alt Text';
            }
            if ($result['title_attr_status'] !== 'present') {
                $issues[] = 'Missing Title Attribute';
            }
            if (empty($issues)) {
                $issues[] = 'Complete';
            }
            
            $priority_label = self::get_priority_label($result['priority_score']);
            $image_size = '';
            if ($result['image_width'] && $result['image_height']) {
                $image_size = $result['image_width'] . 'x' . $result['image_height'];
                if ($result['image_filesize']) {
                    $image_size .= ' (' . size_format($result['image_filesize']) . ')';
                }
            }
            
            $row = [
                $result['page_title'],
                $result['page_url'],
                ucfirst($result['page_type']),
                $result['image_filename'],
                $result['image_url'],
                $image_size,
                ucfirst($result['alt_text_status']),
                ucfirst($result['title_attr_status']),
                $result['alt_text_current'],
                $result['title_attr_current'],
                $priority_label,
                $result['priority_score'],
                $result['image_position'],
                ucfirst($result['image_type']),
                $result['section_heading'],
                $result['context_before'],
                $result['context_after'],
                $result['image_caption'],
                $result['page_modified'],
                implode(', ', $issues)
            ];
            
            $csv_data[] = $row;
        }
        
        // Convert to CSV string with UTF-8 BOM
        $output = "\xEF\xBB\xBF"; // UTF-8 BOM
        foreach ($csv_data as $row) {
            $output .= '"' . implode('","', array_map(function($field) {
                // Ensure UTF-8 encoding and escape quotes
                return str_replace('"', '""', mb_convert_encoding($field, 'UTF-8', 'UTF-8'));
            }, $row)) . '"' . "\n";
        }
        
        return $output;
    }
    
    private static function generate_filename($format, $scan_id) {
        $site_name = sanitize_title(get_bloginfo('name'));
        $date = date('Y-m-d-H-i-s');
        $scan_short = substr($scan_id, -8);
        
        return "pic-pilot-accessibility-report-{$site_name}-{$scan_short}-{$date}.{$format}";
    }
    
    private static function get_priority_label($score) {
        if ($score >= 8) return 'Critical';
        if ($score >= 6) return 'High';
        if ($score >= 4) return 'Medium';
        return 'Low';
    }
    
    public static function generate_summary_stats($scan_id) {
        $stats = DatabaseManager::get_scan_stats($scan_id);
        $scan_info = DatabaseManager::get_latest_scan();
        
        return [
            'scan_date' => $scan_info['completed_at'] ?? $scan_info['started_at'],
            'total_images' => (int)$stats['total_images'],
            'total_issues' => (int)$stats['missing_alt'] + (int)$stats['missing_title'] - (int)$stats['missing_both'],
            'missing_alt' => (int)$stats['missing_alt'],
            'missing_title' => (int)$stats['missing_title'],
            'missing_both' => (int)$stats['missing_both'],
            'critical_issues' => (int)$stats['critical_issues'],
            'high_issues' => (int)$stats['high_issues'],
            'medium_issues' => (int)$stats['medium_issues'],
            'pages_affected' => (int)$stats['pages_with_issues'],
            'completion_percentage' => $stats['total_images'] > 0 ? 
                round((($stats['total_images'] - ($stats['missing_alt'] + $stats['missing_title'] - $stats['missing_both'])) / $stats['total_images']) * 100, 1) : 100
        ];
    }
}