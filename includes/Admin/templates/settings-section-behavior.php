<?php

use PicPilotMeta\Admin\Settings;

$settings = [
    [
        'key' => 'auto_generate_metadata_on_upload',
        'label' => __('🪄 Auto-Generate Alt Text on Upload', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Automatically generate descriptive alt text using AI when a new image is uploaded. ⚠️ This feature consumes OpenAI API credits with each upload.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'auto_generate_title_on_upload',
        'label' => __('🪄 Auto-Generate Title on Upload', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Automatically generate SEO-friendly titles using AI when a new image is uploaded. ⚠️ This feature consumes OpenAI API credits with each upload.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'show_keywords_field',
        'label' => __('🧩 Show Keywords Field in Media Library', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Adds a field to pass keywords for more accurate AI results (used in grid view).', 'pic-pilot-meta'),
    ],
    [
        'key' => 'log_enabled',
        'label' => __('📜 Enable AI Logging', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Logs all metadata generation attempts, results, and errors for debugging.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'show_hover_info',
        'label' => __('ℹ️ Show Metadata on Hover', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Display alt text, title, and filename on hover when using the PicPilot column.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'enable_media_modal_tools',
        'label' => __('🔧 Enable AI Tools in Media Modal', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Show AI tools in media library modal/popup. ⚠️ Disable this if you experience conflicts with page builders or performance issues.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'enable_auto_generate_both',
        'label' => __('🪄 Enable Auto-Generate Both Button', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Shows an additional "Auto-Generate Both" button when an image is missing both alt text and title. This generates both attributes at once with AI. ⚠️ This feature consumes more API credits.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'enable_dangerous_filename_rename',
        'label' => __('⚠️ Enable Dangerous Filename Renaming', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('DANGEROUS: Allows renaming image filenames which may break existing references. The system will detect image usage and warn you, but proceed with extreme caution. Only enable if you understand the risks.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'enable_selective_upload_area',
        'label' => __('📤 Enable Selective AI Upload Area', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Adds a dedicated upload area where you can upload images and selectively apply AI generation for alt text, title, and filename. This allows you to use AI tokens only when needed, instead of automatic generation on all uploads.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'enable_optimization_compatibility',
        'label' => __('🔧 Enable Optimization Plugin Compatibility', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('Improves compatibility with WebP optimization plugins (Smush, Imagify, EWWW, etc.) by temporarily disabling them during file renaming operations. Enable this if you experience file renaming failures with optimization plugins active.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'remove_settings_on_uninstall',
        'label' => __('🗑️ Remove Settings on Uninstall', 'pic-pilot-meta'),
        'type' => 'checkbox',
        'description' => __('When enabled, all plugin settings and data will be completely removed when the plugin is uninstalled. This includes all settings, scan results, and database tables. Leave unchecked to preserve settings for future reinstallation.', 'pic-pilot-meta'),
    ],
];

foreach ($settings as $setting) {
    Settings::render_setting_row($setting);
}
