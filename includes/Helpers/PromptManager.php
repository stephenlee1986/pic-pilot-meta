<?php

namespace PicPilotMeta\Helpers;

use PicPilotMeta\Admin\Settings;

class PromptManager {

    /**
     * Get system message for AI interactions
     */
    public static function getSystemMessage($type) {
        $key = "system_message_{$type}";
        $mode_key = $key . '_mode';
        
        $defaults = [
            'alt' => 'You are an accessibility expert. Create concise, descriptive alt text under 125 characters. Focus on what\'s meaningful about the image and its purpose. Be objective and specific.',
            'title' => 'You are a content writer. Create ONE SEO-friendly, descriptive title that captures the main subject and context of the image. Do not provide multiple options or explanations.',
            'filename' => 'You are a file naming expert. Generate concise, descriptive filenames without extensions. Use only alphanumeric characters and hyphens.'
        ];

        // Check if user wants default or custom
        $mode = Settings::get($mode_key, 'default');
        if ($mode === 'default') {
            return $defaults[$type] ?? '';
        }
        
        return Settings::get($key, $defaults[$type] ?? '');
    }

    /**
     * Get base prompt for the specified type
     */
    public static function getBasePrompt($type) {
        $key = "default_prompt_{$type}";
        $mode_key = $key . '_mode';
        
        $defaults = [
            'alt' => 'Describe this image for alt text in one short sentence.',
            'title' => 'Suggest a short SEO-friendly title for this image.',
            'filename' => 'Generate a short, SEO-friendly filename based on this image.'
        ];

        // Check if user wants default or custom
        $mode = Settings::get($mode_key, 'default');
        if ($mode === 'default') {
            return $defaults[$type] ?? '';
        }

        return Settings::get($key, $defaults[$type] ?? '');
    }

    /**
     * Enhance prompt with keywords and context
     */
    public static function enhanceWithKeywords($prompt, $keywords, $type) {
        if (empty(trim($keywords))) {
            return $prompt;
        }

        $clean_keywords = trim($keywords);
        
        // Get customizable context templates
        $context_templates = self::getContextTemplates($type);
        
        return $context_templates['prefix'] . $clean_keywords . $context_templates['suffix'] . $prompt;
    }

    /**
     * Get context enhancement templates
     */
    private static function getContextTemplates($type) {
        $defaults = [
            'alt' => [
                'prefix' => 'Context: This image shows ',
                'suffix' => '. Incorporate this context naturally into your description. '
            ],
            'title' => [
                'prefix' => 'Context: This image shows ',
                'suffix' => '. Use this context to create a more specific and relevant title. '
            ],
            'filename' => [
                'prefix' => 'Context: ',
                'suffix' => '. Use this context for the filename but keep it concise.'
            ]
        ];

        $templates = [];
        foreach (['prefix', 'suffix'] as $part) {
            $key = "context_template_{$type}_{$part}";
            $mode_key = $key . '_mode';
            
            // Check if user wants default or custom
            $mode = Settings::get($mode_key, 'default');
            if ($mode === 'default') {
                $templates[$part] = $defaults[$type][$part] ?? '';
            } else {
                $templates[$part] = Settings::get($key, $defaults[$type][$part] ?? '');
            }
        }

        return $templates;
    }

    /**
     * Get fallback text when AI generation fails
     */
    public static function getFallbackText($type, $keywords = '', $original_title = '') {
        Logger::log("[{$type}] === FALLBACK GENERATION START ===");
        
        if ($type === 'title') {
            if (!empty($keywords)) {
                $fallback_template = Settings::get('fallback_title_keywords', '{keywords}');
                $fallback = str_replace('{keywords}', ucwords(trim(preg_replace('/[^\w\s]/', ' ', $keywords))), $fallback_template);
                if (strlen($fallback) > 5) {
                    Logger::log("[title] Fallback from keywords: " . $fallback);
                    return $fallback;
                }
            }

            $fallback_default = Settings::get('fallback_title_default', 'Image {date}');
            $fallback = str_replace('{date}', date('Y-m-d'), $fallback_default);
            if (!empty($original_title)) {
                $copy_suffix = Settings::get('copy_suffix_title', ' (Copy)');
                $fallback = $original_title . $copy_suffix;
            }
            Logger::log("[title] Default fallback: " . $fallback);
            return $fallback;
        }

        if ($type === 'alt') {
            if (!empty($keywords)) {
                $fallback_template = Settings::get('fallback_alt_keywords', '{keywords} image');
                $fallback = str_replace('{keywords}', trim($keywords), $fallback_template);
                Logger::log("[alt] Fallback from keywords: " . $fallback);
                return $fallback;
            }

            $fallback_default = Settings::get('fallback_alt_default', 'Descriptive image');
            if (!empty($original_title)) {
                $fallback_template = Settings::get('fallback_alt_with_title', '{title}');
                $fallback_default = str_replace('{title}', $original_title, $fallback_template);
            }
            Logger::log("[alt] Default fallback: " . $fallback_default);
            return $fallback_default;
        }

        Logger::log("[{$type}] === FALLBACK GENERATION END ===");
        return '';
    }

    /**
     * Get copy suffix for duplicated items
     */
    public static function getCopySuffix($type) {
        $defaults = [
            'title' => ' (Copy)',
            'alt' => ' (Copy)',
            'filename' => '-copy'
        ];

        $key = "copy_suffix_{$type}";
        $mode_key = $key . '_mode';
        
        // Check if user wants default or custom
        $mode = Settings::get($mode_key, 'default');
        if ($mode === 'default') {
            return $defaults[$type] ?? '';
        }

        return Settings::get($key, $defaults[$type] ?? '');
    }

    /**
     * Generate fallback filename with customizable patterns
     */
    public static function generateFallbackFilename($attachment_id, $keywords = '') {
        $original_file = get_attached_file($attachment_id);
        $pathinfo = pathinfo($original_file);
        $base_name = $pathinfo['filename'];

        if (!empty($keywords)) {
            $keyword_words = preg_split('/[^\w]+/', strtolower($keywords));
            $keyword_words = array_filter($keyword_words, function ($word) {
                return strlen($word) > 2 && !in_array($word, ['this', 'that', 'the', 'and', 'but', 'for']);
            });

            if (!empty($keyword_words)) {
                $template = Settings::get('fallback_filename_keywords', '{keywords}-{date}');
                $fallback = str_replace([
                    '{keywords}' => implode('-', array_slice($keyword_words, 0, 3)),
                    '{date}' => date('Ymd')
                ], $template);
                Logger::log('[FILENAME] Fallback from keywords: ' . $fallback);
                return sanitize_title($fallback);
            }
        }

        $template = Settings::get('fallback_filename_default', '{basename}-copy-{datetime}');
        $fallback = str_replace([
            '{basename}' => $base_name,
            '{date}' => date('Ymd'),
            '{datetime}' => date('Ymd-His')
        ], $template);
        
        Logger::log('[FILENAME] Default fallback: ' . $fallback);
        return sanitize_title($fallback);
    }

    /**
     * Get all default settings for initialization
     */
    public static function getDefaultSettings() {
        return [
            // Default base prompts
            'default_prompt_alt' => 'Describe this image for alt text in one short sentence.',
            'default_prompt_title' => 'Suggest a short SEO-friendly title for this image.',
            'default_prompt_filename' => 'Generate a short, SEO-friendly filename based on this image.',
            
            // System messages
            'system_message_alt' => 'You are an accessibility expert. Create concise, descriptive alt text under 125 characters. Focus on what\'s meaningful about the image and its purpose. Be objective and specific.',
            'system_message_title' => 'You are a content writer. Create ONE SEO-friendly, descriptive title that captures the main subject and context of the image. Do not provide multiple options or explanations.',
            'system_message_filename' => 'You are a file naming expert. Generate concise, descriptive filenames without extensions. Use only alphanumeric characters and hyphens.',
            
            // Context templates
            'context_template_alt_prefix' => 'Context: This image shows ',
            'context_template_alt_suffix' => '. Incorporate this context naturally into your description. ',
            'context_template_title_prefix' => 'Context: This image shows ',
            'context_template_title_suffix' => '. Use this context to create a more specific and relevant title. ',
            'context_template_filename_prefix' => 'Context: ',
            'context_template_filename_suffix' => '. Use this context for the filename but keep it concise.',
            
            // Fallback patterns
            'fallback_title_keywords' => '{keywords}',
            'fallback_title_default' => 'Image {date}',
            'fallback_alt_keywords' => '{keywords} image',
            'fallback_alt_default' => 'Descriptive image',
            'fallback_alt_with_title' => '{title}',
            'fallback_filename_keywords' => '{keywords}-{date}',
            'fallback_filename_default' => '{basename}-copy-{datetime}',
            
            // Copy suffixes
            'copy_suffix_title' => ' (Copy)',
            'copy_suffix_alt' => ' (Copy)',
            'copy_suffix_filename' => '-copy'
        ];
    }
}