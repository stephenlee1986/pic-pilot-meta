<?php

namespace PicPilotMeta\Admin;

use PicPilotMeta\Helpers\Logger;

defined('ABSPATH') || exit;

class DatabaseManager {
    
    public const DB_VERSION = '1.1';
    public const DB_VERSION_OPTION = 'picpilot_dashboard_db_version';
    
    public static function init() {
        add_action('admin_init', [__CLASS__, 'check_database_version']);
    }
    
    public static function check_database_version() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            // Force recreate for this version due to schema conflicts in v1.0
            $force_recreate = $installed_version === '1.0' || $installed_version === '0';
            
            self::create_tables($force_recreate);
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
            Logger::log("[DATABASE] Updated database schema to version " . self::DB_VERSION . ($force_recreate ? ' (forced recreation)' : ''));
        } else {
            // Also check if tables actually exist (in case they were dropped)
            global $wpdb;
            $table_name = $wpdb->prefix . 'picpilot_scan_history';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            
            if (!$table_exists) {
                Logger::log("[DATABASE] Tables missing, recreating...");
                self::create_tables();
            }
        }
    }
    
    public static function create_tables($force_recreate = false) {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // If force recreate, drop existing tables first
        if ($force_recreate) {
            $scan_results_table = $wpdb->prefix . 'picpilot_scan_results';
            $scan_history_table = $wpdb->prefix . 'picpilot_scan_history';
            
            $wpdb->query("DROP TABLE IF EXISTS $scan_results_table");
            $wpdb->query("DROP TABLE IF EXISTS $scan_history_table");
            
            Logger::log("[DATABASE] Dropped existing tables for recreation");
        }
        
        // Table for scan results
        $scan_results_table = $wpdb->prefix . 'picpilot_scan_results';
        $scan_results_sql = "CREATE TABLE $scan_results_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id varchar(50) NOT NULL,
            page_id bigint(20) unsigned NOT NULL,
            page_url text NOT NULL,
            page_title text NOT NULL,
            page_type varchar(50) NOT NULL,
            page_modified datetime NOT NULL,
            image_id bigint(20) unsigned NOT NULL,
            image_url text NOT NULL,
            image_filename varchar(255) NOT NULL,
            image_width int unsigned,
            image_height int unsigned,
            image_filesize int unsigned,
            alt_text_status enum('present','empty','missing') NOT NULL,
            title_attr_status enum('present','empty','missing') NOT NULL,
            alt_text_current text,
            title_attr_current text,
            image_position int unsigned NOT NULL DEFAULT 1,
            image_type enum('featured','gallery','inline','other') NOT NULL DEFAULT 'inline',
            context_before text,
            context_after text,
            section_heading text,
            image_caption text,
            priority_score int unsigned NOT NULL DEFAULT 5,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY page_id (page_id),
            KEY image_id (image_id),
            KEY alt_text_status (alt_text_status),
            KEY title_attr_status (title_attr_status),
            KEY priority_score (priority_score),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for scan history
        $scan_history_table = $wpdb->prefix . 'picpilot_scan_history';
        $scan_history_sql = "CREATE TABLE $scan_history_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id varchar(50) NOT NULL,
            scan_type enum('full','partial','pages','posts') NOT NULL DEFAULT 'partial',
            status enum('running','completed','failed','cancelled') NOT NULL DEFAULT 'running',
            pages_scanned int unsigned NOT NULL DEFAULT 0,
            pages_total int unsigned NOT NULL DEFAULT 0,
            images_found int unsigned NOT NULL DEFAULT 0,
            issues_found int unsigned NOT NULL DEFAULT 0,
            issues_critical int unsigned NOT NULL DEFAULT 0,
            issues_high int unsigned NOT NULL DEFAULT 0,
            issues_medium int unsigned NOT NULL DEFAULT 0,
            scan_filters text,
            started_by bigint(20) unsigned NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            error_message text,
            PRIMARY KEY (id),
            UNIQUE KEY scan_id_unique (scan_id),
            KEY status (status),
            KEY started_by (started_by),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($scan_results_sql);
        dbDelta($scan_history_sql);
        
        Logger::log("[DATABASE] Created tables: {$scan_results_table}, {$scan_history_table}");
    }
    
    public static function get_scan_results($scan_id, $filters = [], $page = 1, $per_page = 25) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        $where_clauses = ['scan_id = %s'];
        $where_values = [$scan_id];
        
        // Apply filters
        if (!empty($filters['alt_status'])) {
            $where_clauses[] = 'alt_text_status = %s';
            $where_values[] = $filters['alt_status'];
        }
        
        if (!empty($filters['title_status'])) {
            $where_clauses[] = 'title_attr_status = %s';
            $where_values[] = $filters['title_status'];
        }
        
        if (!empty($filters['priority'])) {
            $where_clauses[] = 'priority_score >= %d';
            $where_values[] = $filters['priority'];
        }
        
        if (!empty($filters['page_type'])) {
            $where_clauses[] = 'page_type = %s';
            $where_values[] = $filters['page_type'];
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table $where_sql";
        $total_items = $wpdb->get_var($wpdb->prepare($count_sql, $where_values));
        
        // Get paginated results
        $offset = ($page - 1) * $per_page;
        $results_sql = "SELECT * FROM $table $where_sql ORDER BY priority_score DESC, created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($results_sql, $where_values), ARRAY_A);
        
        return [
            'results' => $results,
            'total_items' => (int)$total_items,
            'total_pages' => ceil($total_items / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        ];
    }
    
    public static function get_scan_stats($scan_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_images,
                SUM(CASE WHEN alt_text_status = 'missing' OR alt_text_status = 'empty' THEN 1 ELSE 0 END) as missing_alt,
                SUM(CASE WHEN title_attr_status = 'missing' OR title_attr_status = 'empty' THEN 1 ELSE 0 END) as missing_title,
                SUM(CASE WHEN (alt_text_status = 'missing' OR alt_text_status = 'empty') AND (title_attr_status = 'missing' OR title_attr_status = 'empty') THEN 1 ELSE 0 END) as missing_both,
                SUM(CASE WHEN priority_score >= 8 THEN 1 ELSE 0 END) as critical_issues,
                SUM(CASE WHEN priority_score >= 6 AND priority_score < 8 THEN 1 ELSE 0 END) as high_issues,
                SUM(CASE WHEN priority_score >= 4 AND priority_score < 6 THEN 1 ELSE 0 END) as medium_issues,
                COUNT(DISTINCT page_id) as pages_with_issues
            FROM $table 
            WHERE scan_id = %s
        ", $scan_id), ARRAY_A);
        
        return $stats;
    }
    
    public static function save_scan_result($scan_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_results';
        
        return $wpdb->insert($table, array_merge($data, [
            'scan_id' => $scan_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]));
    }
    
    public static function create_scan_session($scan_type = 'partial', $filters = []) {
        global $wpdb;
        
        $scan_id = 'scan_' . time() . '_' . wp_generate_password(8, false);
        $table = $wpdb->prefix . 'picpilot_scan_history';
        
        $result = $wpdb->insert($table, [
            'scan_id' => $scan_id,
            'scan_type' => $scan_type,
            'status' => 'running',
            'scan_filters' => json_encode($filters),
            'started_by' => get_current_user_id(),
            'started_at' => current_time('mysql')
        ]);
        
        if ($result) {
            Logger::log("[DATABASE] Created scan session: $scan_id");
            return $scan_id;
        }
        
        return false;
    }
    
    public static function update_scan_progress($scan_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_history';
        
        return $wpdb->update($table, $data, ['scan_id' => $scan_id]);
    }
    
    public static function get_latest_scan() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'picpilot_scan_history';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE status = %s 
            ORDER BY completed_at DESC 
            LIMIT 1
        ", 'completed'), ARRAY_A);
    }
}