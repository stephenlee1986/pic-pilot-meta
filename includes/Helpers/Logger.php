<?php

namespace PicPilotMeta\Helpers;

class Logger {
    const LOG_FILE = \WP_CONTENT_DIR . '/uploads/pic-pilot-meta.log';
    const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public static function log($message) {
        $settings = get_option('picpilot_meta_settings', []);
        
        // For debugging purposes, temporarily force logging on debug messages
        $force_debug = strpos($message, '[DEBUG]') !== false || 
                      strpos($message, '[AJAX]') !== false || 
                      strpos($message, '[USAGE_CHECK]') !== false ||
                      strpos($message, '[RENAME]') !== false;
        
        if (empty($settings['log_enabled']) && !$force_debug) return;

        $timestamp = current_time('mysql');
        $line = "[$timestamp] $message\n";

        // Rotate log if too big
        if (file_exists(self::LOG_FILE) && filesize(self::LOG_FILE) >= self::MAX_SIZE) {
            unlink(self::LOG_FILE); // start fresh
        }

        file_put_contents(self::LOG_FILE, $line, FILE_APPEND);
    }
}
