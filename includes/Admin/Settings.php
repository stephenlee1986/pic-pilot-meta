<?php

namespace PicPilotMeta\Admin;
use PicPilotMeta\Helpers\Logger;

class Settings {

    public static function init() {
        register_setting('picpilot_meta_settings_group', 'picpilot_meta_settings', [
            'sanitize_callback' => [self::class, 'sanitize_settings']
        ]);
        add_action('admin_menu', function () {
            add_options_page(
                __('Pic Pilot Meta Settings', 'pic-pilot-meta'),
                __('Pic Pilot Meta', 'pic-pilot-meta'),
                'manage_options',
                'picpilot-meta-settings',
                [self::class, 'render_settings_page']
            );
        });
        add_action('admin_post_picpilot_clear_log', [self::class, 'handle_clear_log']);
    }


    public static function render_setting_row($setting) {
        $value = get_option('picpilot_meta_settings')[$setting['key']] ?? '';
        echo '<tr valign="top">';
        echo '<th scope="row">' . esc_html($setting['label']) . '</th>';
        echo '<td>';

        switch ($setting['type']) {
            case 'checkbox':
                echo '<label><input type="checkbox" name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" value="1" ' . checked($value, 1, false) . '> ' . esc_html($setting['description']) . '</label>';
                break;

            case 'text':
                echo '<input type="text" name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
                if (!empty($setting['description'])) {
                    echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                }
                break;
            case 'password-toggle':
                echo '<div style="position:relative;">';
                echo '<input type="password" data-toggleable name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '</div>';
                if (!empty($setting['description'])) {
                    echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                }
                break;
            case 'select':
                echo '<select name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" class="regular-text">';
                if (isset($setting['options']) && is_array($setting['options'])) {
                    foreach ($setting['options'] as $option_value => $option_label) {
                        echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                    }
                }
                echo '</select>';
                if (!empty($setting['description'])) {
                    echo '<p class="description">' . esc_html($setting['description']) . '</p>';
                }
                break;
        }

        echo '</td></tr>';
    }

    public static function render_setting_with_toggle($setting) {
        $settings = get_option('picpilot_meta_settings', []);
        $mode_key = $setting['key'] . '_mode';
        $current_mode = $settings[$mode_key] ?? 'default';
        $current_value = $settings[$setting['key']] ?? '';
        $default_value = $setting['default'] ?? '';
        
        echo '<tr valign="top">';
        echo '<th scope="row">' . esc_html($setting['label']) . '</th>';
        echo '<td>';
        
        // Radio buttons
        echo '<div style="margin-bottom: 10px;">';
        echo '<label style="margin-right: 20px;"><input type="radio" name="picpilot_meta_settings[' . esc_attr($mode_key) . ']" value="default" ' . checked($current_mode, 'default', false) . ' onchange="toggleCustomField(\'' . esc_attr($setting['key']) . '\')"> Use Optimized Default</label>';
        echo '<label><input type="radio" name="picpilot_meta_settings[' . esc_attr($mode_key) . ']" value="custom" ' . checked($current_mode, 'custom', false) . ' onchange="toggleCustomField(\'' . esc_attr($setting['key']) . '\')"> Custom</label>';
        echo '</div>';
        
        // Default value display (read-only)
        echo '<div id="default_' . esc_attr($setting['key']) . '" style="' . ($current_mode === 'custom' ? 'display: none;' : '') . '">';
        echo '<div style="padding: 8px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; color: #646970; font-style: italic; max-width: 600px;">';
        echo esc_html($default_value);
        echo '</div>';
        echo '</div>';
        
        // Custom input field
        echo '<div id="custom_' . esc_attr($setting['key']) . '" style="' . ($current_mode === 'default' ? 'display: none;' : '') . '">';
        if ($setting['type'] === 'textarea') {
            echo '<textarea name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" rows="3" class="large-text">' . esc_textarea($current_value) . '</textarea>';
        } else {
            echo '<input type="text" name="picpilot_meta_settings[' . esc_attr($setting['key']) . ']" value="' . esc_attr($current_value) . '" class="large-text" />';
        }
        echo '</div>';
        
        if (!empty($setting['description'])) {
            echo '<p class="description">' . esc_html($setting['description']) . '</p>';
        }
        
        echo '</td></tr>';
        
        // Add JavaScript for toggling
        static $js_added = false;
        if (!$js_added) {
            echo '<script>
            function toggleCustomField(key) {
                const defaultDiv = document.getElementById("default_" + key);
                const customDiv = document.getElementById("custom_" + key);
                const radios = document.querySelectorAll(`input[name="picpilot_meta_settings[${key}_mode]"]`);
                
                let selectedValue = "";
                radios.forEach(radio => {
                    if (radio.checked) selectedValue = radio.value;
                });
                
                if (selectedValue === "default") {
                    defaultDiv.style.display = "block";
                    customDiv.style.display = "none";
                } else {
                    defaultDiv.style.display = "none";
                    customDiv.style.display = "block";
                }
            }
            </script>';
            $js_added = true;
        }
    }

    public static function render_settings_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        
        echo '<div class="wrap pic-pilot-meta">';
        echo '<h1>' . esc_html__('Pic Pilot Meta', 'pic-pilot-meta') . '</h1>';
        
        // Tab navigation
        echo '<nav class="nav-tab-wrapper">';
        echo '<a href="?page=picpilot-meta-settings&tab=settings" class="nav-tab ' . ($current_tab === 'settings' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('Settings', 'pic-pilot-meta');
        echo '</a>';
        echo '<a href="?page=picpilot-meta-settings&tab=advanced-prompts" class="nav-tab ' . ($current_tab === 'advanced-prompts' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('Advanced Prompts', 'pic-pilot-meta');
        echo '</a>';
        echo '<a href="?page=picpilot-meta-settings&tab=information" class="nav-tab ' . ($current_tab === 'information' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('Information & Guide', 'pic-pilot-meta');
        echo '</a>';
        echo '<a href="?page=picpilot-meta-settings&tab=logs" class="nav-tab ' . ($current_tab === 'logs' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('Logs', 'pic-pilot-meta');
        echo '</a>';
        echo '</nav>';
        
        echo '<div class="tab-content">';
        
        if ($current_tab === 'settings') {
            self::render_settings_tab();
        } elseif ($current_tab === 'advanced-prompts') {
            self::render_advanced_prompts_tab();
        } elseif ($current_tab === 'information') {
            self::render_information_tab();
        } elseif ($current_tab === 'logs') {
            self::render_logs_tab();
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private static function render_settings_tab() {
        echo '<form method="post" action="options.php">';
        settings_fields('picpilot_meta_settings_group');
        echo '<table class="form-table">';

        // Load modular sections
        include __DIR__ . '/templates/settings-section-ai.php';
        include __DIR__ . '/templates/settings-section-behavior.php';

        echo '</table>';
        submit_button();
        echo '</form>';
    }
    
    private static function render_advanced_prompts_tab() {
        echo '<form method="post" action="options.php">';
        settings_fields('picpilot_meta_settings_group');
        echo '<table class="form-table">';

        include __DIR__ . '/templates/settings-section-advanced-prompts.php';

        echo '</table>';
        submit_button();
        echo '</form>';
    }
    
    private static function render_information_tab() {
        include __DIR__ . '/templates/settings-section-information.php';
    }


    private static function render_logs_tab() {
        $log_file = Logger::LOG_FILE;
        $log_data = file_exists($log_file) ? file_get_contents($log_file) : '';

        if (isset($_GET['cleared'])) {
            echo '<div class="updated notice"><p>' . esc_html__('Log cleared.', 'pic-pilot-meta') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<textarea id="picpilot-log-content" readonly rows="20" style="width:100%;font-family:monospace;">' . esc_textarea($log_data) . '</textarea>';
        echo '<div style="margin-top: 10px;">';
        echo '<button type="button" id="picpilot-copy-log" class="button" style="margin-right: 10px;">📋 ' . esc_html__('Copy to Clipboard', 'pic-pilot-meta') . '</button>';
        wp_nonce_field('picpilot_clear_log');
        echo '<input type="hidden" name="action" value="picpilot_clear_log">';
        submit_button(esc_html__('Clear Log', 'pic-pilot-meta'), 'delete', 'submit', false);
        echo '</div>';
        echo '</form>';
        
        // Add JavaScript for copy functionality
        echo '<script>
        document.getElementById("picpilot-copy-log").addEventListener("click", function() {
            const logContent = document.getElementById("picpilot-log-content");
            logContent.select();
            logContent.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand("copy");
                const button = this;
                const originalText = button.innerHTML;
                button.innerHTML = "✅ ' . esc_js(__('Copied!', 'pic-pilot-meta')) . '";
                button.style.backgroundColor = "#46b450";
                button.style.color = "white";
                
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.style.backgroundColor = "";
                    button.style.color = "";
                }, 2000);
            } catch (err) {
                alert("' . esc_js(__('Failed to copy. Please select the text manually and copy.', 'pic-pilot-meta')) . '");
            }
        });
        </script>';
    }

    public static function handle_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'pic-pilot-meta'));
        }

        check_admin_referer('picpilot_clear_log');

        $log_file = Logger::LOG_FILE;
        if (file_exists($log_file)) {
            unlink($log_file);
        }

        wp_redirect(admin_url('admin.php?page=picpilot-meta-settings&tab=logs&cleared=1'));
        exit;
    }

    public static function sanitize_settings($input) {
        // Get existing settings to preserve values from other tabs
        $existing_settings = get_option('picpilot_meta_settings', []);
        
        // Handle checkbox fields - if not present in input, they should be 0
        $checkbox_fields = [
            'auto_generate_metadata_on_upload',
            'auto_generate_title_on_upload',
            'show_keywords_field',
            'log_enabled',
            'show_hover_info',
            'enable_media_modal_tools',
            'enable_auto_generate_both',
            'enable_dangerous_filename_rename',
            'enable_selective_upload_area',
            'enable_optimization_compatibility',
            'remove_settings_on_uninstall'
        ];
        
        // Set checkbox fields to 0 if not present in input (unchecked)
        foreach ($checkbox_fields as $field) {
            if (!isset($input[$field])) {
                $input[$field] = 0;
            }
        }
        
        // Merge new input with existing settings
        $merged_settings = array_merge($existing_settings, $input);
        
        // Sanitize each value based on type
        foreach ($merged_settings as $key => $value) {
            if (is_string($value)) {
                $merged_settings[$key] = sanitize_text_field($value);
            }
        }
        
        return $merged_settings;
    }

    public static function get($key, $default = null) {
        $options = get_option('picpilot_meta_settings', []);
        return $options[$key] ?? $default;
    }
}
