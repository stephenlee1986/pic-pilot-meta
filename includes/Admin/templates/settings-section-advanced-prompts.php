<?php

use PicPilotMeta\Admin\Settings;

// System Messages Section
echo '<tr><td colspan="2"><h2 style="margin: 30px 0 15px 0; padding: 0; color: #333;">🤖 System Messages</h2></td></tr>';

$system_settings = [
    [
        'key' => 'system_message_alt',
        'label' => __('Alt Text System Message', 'pic-pilot-meta'),
        'type' => 'textarea',
        'default' => 'You are an accessibility expert. Create concise, descriptive alt text under 125 characters. Focus on what\'s meaningful about the image and its purpose. Be objective and specific.',
        'description' => __('Instructions given to AI before generating alt text. Defines the role and format expectations.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'system_message_title',
        'label' => __('Title System Message', 'pic-pilot-meta'),
        'type' => 'textarea',
        'default' => 'You are a content writer. Create ONE SEO-friendly, descriptive title that captures the main subject and context of the image. Do not provide multiple options or explanations.',
        'description' => __('Instructions given to AI before generating titles. Defines the role and format expectations.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'system_message_filename',
        'label' => __('Filename System Message', 'pic-pilot-meta'),
        'type' => 'textarea',
        'default' => 'You are a file naming expert. Generate concise, descriptive filenames without extensions. Use only alphanumeric characters and hyphens.',
        'description' => __('Instructions given to AI before generating filenames. Defines the role and format expectations.', 'pic-pilot-meta'),
    ],
];

foreach ($system_settings as $setting) {
    Settings::render_setting_with_toggle($setting);
}

// Context Enhancement Section
echo '<tr><td colspan="2"><h2 style="margin: 30px 0 15px 0; padding: 0; color: #333;">🎯 Context Enhancement</h2></td></tr>';

$context_settings = [
    [
        'key' => 'context_template_alt_prefix',
        'label' => __('Alt Text Context Prefix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => 'Context: This image shows ',
        'description' => __('Text added before keywords when enhancing alt text prompts.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'context_template_alt_suffix',
        'label' => __('Alt Text Context Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => '. Incorporate this context naturally into your description. ',
        'description' => __('Text added after keywords when enhancing alt text prompts.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'context_template_title_prefix',
        'label' => __('Title Context Prefix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => 'Context: This image shows ',
        'description' => __('Text added before keywords when enhancing title prompts.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'context_template_title_suffix',
        'label' => __('Title Context Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => '. Use this context to create a more specific and relevant title. ',
        'description' => __('Text added after keywords when enhancing title prompts.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'context_template_filename_prefix',
        'label' => __('Filename Context Prefix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => 'Context: ',
        'description' => __('Text added before keywords when enhancing filename prompts.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'context_template_filename_suffix',
        'label' => __('Filename Context Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => '. Use this context for the filename but keep it concise.',
        'description' => __('Text added after keywords when enhancing filename prompts.', 'pic-pilot-meta'),
    ],
];

foreach ($context_settings as $setting) {
    Settings::render_setting_with_toggle($setting);
}

// Note: Fallback patterns are no longer used as the system now shows error messages instead of generating fallback text

// Copy Suffixes Section
echo '<tr><td colspan="2"><h2 style="margin: 30px 0 15px 0; padding: 0; color: #333;">📋 Copy Suffixes</h2></td></tr>';

$copy_settings = [
    [
        'key' => 'copy_suffix_title',
        'label' => __('Title Copy Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => ' (Copy)',
        'description' => __('Suffix added to duplicated image titles when not using AI generation.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'copy_suffix_alt',
        'label' => __('Alt Text Copy Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => ' (Copy)',
        'description' => __('Suffix added to duplicated image alt text when not using AI generation.', 'pic-pilot-meta'),
    ],
    [
        'key' => 'copy_suffix_filename',
        'label' => __('Filename Copy Suffix', 'pic-pilot-meta'),
        'type' => 'text',
        'default' => '-copy',
        'description' => __('Suffix added to duplicated filenames when not using AI generation.', 'pic-pilot-meta'),
    ],
];

foreach ($copy_settings as $setting) {
    Settings::render_setting_with_toggle($setting);
}

echo '<tr><td colspan="2"><p style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; color: #666;"><strong>💡 Pro Tip:</strong> Use placeholders like <code>{keywords}</code>, <code>{date}</code>, <code>{title}</code>, <code>{basename}</code>, and <code>{datetime}</code> to create dynamic patterns. Leave fields empty to use built-in defaults.</p></td></tr>';