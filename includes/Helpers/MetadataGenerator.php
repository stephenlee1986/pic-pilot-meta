<?php

namespace PicPilotMeta\Helpers;

use PicPilotMeta\Admin\Settings;
use WP_Error;

class MetadataGenerator {
    public static function generate($attachment_id, $type = 'alt', $keywords = '') {
        $provider = Settings::get('ai_provider', 'openai');
        
        if ($provider === 'gemini') {
            return self::generateWithGemini($attachment_id, $type, $keywords);
        } else {
            return self::generateWithOpenAI($attachment_id, $type, $keywords);
        }
    }

    private static function generateWithOpenAI($attachment_id, $type = 'alt', $keywords = '') {
        $api_key = Settings::get('openai_api_key');

        // Log the input parameters immediately
        Logger::log("[{$type}] === METADATA GENERATION START ===");
        Logger::log("[{$type}] Attachment ID: $attachment_id");
        Logger::log("[{$type}] Type: $type");
        Logger::log("[{$type}] Keywords: '" . $keywords . "' (length: " . strlen($keywords) . ")");
        Logger::log("[{$type}] Keywords empty check: " . (empty($keywords) ? 'YES' : 'NO'));

        $base_prompt = PromptManager::getBasePrompt($type);
        Logger::log("[{$type}] Base prompt: " . substr($base_prompt, 0, 100) . "...");

        // Enhanced keyword handling with detailed logging
        if (!empty(trim($keywords))) {
            $clean_keywords = trim($keywords);
            Logger::log("[{$type}] Processing keywords: '$clean_keywords'");
            $prompt = PromptManager::enhanceWithKeywords($base_prompt, $clean_keywords, $type);
            Logger::log("[{$type}] Enhanced prompt with keywords created (length: " . strlen($prompt) . ")");
        } else {
            Logger::log("[{$type}] WARNING: No keywords provided for metadata generation - AI will not have professional context");
            $prompt = $base_prompt;
        }

        Logger::log("[{$type}] Final prompt preview: " . substr($prompt, 0, 200) . "...");

        $image_path = get_attached_file($attachment_id);

        if (!$api_key || !$image_path || !file_exists($image_path)) {
            Logger::log("[{$type}] ERROR: Missing API key or image path - API key exists: " . (!empty($api_key) ? 'YES' : 'NO') . ", Image path: $image_path");
            return new WP_Error('missing_data', 'Missing API key or image path.');
        }

        $image_data = file_get_contents($image_path);
        if (!$image_data) {
            Logger::log("[{$type}] ERROR: Failed to read image file");
            return new WP_Error('image_error', 'Failed to load image file.');
        }

        $mime_type = self::getMimeType($image_path);
        $base64 = base64_encode($image_data);
        Logger::log("[{$type}] Image processed - MIME: $mime_type, Base64 length: " . strlen($base64));

        $body = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => PromptManager::getSystemMessage($type)
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64"]],
                    ]
                ]
            ],
            'max_tokens' => 150,
        ];

        Logger::log("[{$type}] Making OpenAI API request...");
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            Logger::log("[{$type}] OpenAI error: " . $response->get_error_message());
            return new WP_Error('api_error', 'OpenAI API error: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        Logger::log("[{$type}] API response received, checking for errors...");

        // Check for API errors
        if (isset($data['error'])) {
            Logger::log("[{$type}] API error: " . json_encode($data['error']));
            return new WP_Error('api_error', 'OpenAI API error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        // Log full response structure for debugging
        Logger::log("[{$type}] Full API response structure: " . json_encode($data, JSON_PRETTY_PRINT));

        $content = trim($data['choices'][0]['message']['content'] ?? '');

        Logger::log("[{$type}] Raw AI response (keywords: '$keywords'): " . $content);

        if (!$content) {
            Logger::log("[{$type}] Empty response from API - full response: " . json_encode($data));
            return new WP_Error('empty_response', 'Empty response from AI API');
        }

        // Check if AI refused to generate content - improved detection
        $content_lower = strtolower($content);
        $ai_refused = false;
        
        if (
            (str_contains($content_lower, "sorry") && (
                str_contains($content_lower, "can't assist") ||
                str_contains($content_lower, "can't identify") ||
                str_contains($content_lower, "can't describe") ||
                str_contains($content_lower, "can't help") ||
                str_contains($content_lower, "cannot identify") ||
                str_contains($content_lower, "cannot describe") ||
                str_contains($content_lower, "cannot help") ||
                str_contains($content_lower, "don't know who this is")
            )) ||
            str_contains($content_lower, "i'm not able to identify") ||
            str_contains($content_lower, "i cannot identify") ||
            str_contains($content_lower, "i'm unable to") ||
            str_contains($content_lower, "i am unable to") ||
            str_contains($content_lower, "don't know who this is") ||
            str_contains($content_lower, "do not know who this is") ||
            (str_contains($content_lower, "unable") && str_contains($content_lower, "identify")) ||
            // Generic refusal patterns
            (str_contains($content_lower, "i can't") && str_contains($content_lower, "help")) ||
            (str_contains($content_lower, "i cannot") && str_contains($content_lower, "help"))
        ) {
            Logger::log("[{$type}] AI refused to generate content");
            return new WP_Error('ai_refused', 'AI refused to generate content for this image');
        }

        // Clean up quotes and code block syntax
        $content = trim($content);
        $content = trim($content, '"');
        $content = preg_replace('/```\w*\n?|\n```$/', '', $content); // Remove code block markers
        $content = trim($content); // Final trim after cleaning

        // Handle multiple suggestions - take only the first one
        $content = self::extractFirstSuggestion($content);

        // For filenames, ensure we don't have .jpg/.png extension in the response
        if ($type === 'filename') {
            $content = preg_replace('/\.(jpg|png|gif|jpeg)$/i', '', $content);
        }

        // Validate the result contains expected professional context if keywords were provided
        if (!empty($keywords) && $type === 'alt') {
            $keyword_parts = explode(',', strtolower($keywords));
            $content_lower = strtolower($content);
            $found_context = false;

            foreach ($keyword_parts as $part) {
                $part = trim($part);
                if (!empty($part) && str_contains($content_lower, $part)) {
                    $found_context = true;
                    break;
                }
            }

            if (!$found_context) {
                Logger::log("[{$type}] WARNING: Generated content may not include provided professional context");
            } else {
                Logger::log("[{$type}] SUCCESS: Generated content includes professional context");
            }
        }

        Logger::log("[{$type}] Final cleaned result: " . $content);
        Logger::log("[{$type}] === METADATA GENERATION END ===");
        return $content;
    }

    private static function generateWithGemini($attachment_id, $type = 'alt', $keywords = '') {
        $api_key = Settings::get('gemini_api_key');

        // Log the input parameters immediately
        Logger::log("[{$type}] === GEMINI METADATA GENERATION START ===");
        Logger::log("[{$type}] Attachment ID: $attachment_id");
        Logger::log("[{$type}] Type: $type");
        Logger::log("[{$type}] Keywords: '" . $keywords . "' (length: " . strlen($keywords) . ")");

        $base_prompt = PromptManager::getBasePrompt($type);
        Logger::log("[{$type}] Base prompt: " . substr($base_prompt, 0, 100) . "...");

        // Enhanced keyword handling with detailed logging
        if (!empty(trim($keywords))) {
            $clean_keywords = trim($keywords);
            Logger::log("[{$type}] Processing keywords: '$clean_keywords'");
            $prompt = PromptManager::enhanceWithKeywords($base_prompt, $clean_keywords, $type);
            Logger::log("[{$type}] Enhanced prompt with keywords created (length: " . strlen($prompt) . ")");
        } else {
            Logger::log("[{$type}] WARNING: No keywords provided for metadata generation - AI will not have professional context");
            $prompt = $base_prompt;
        }

        Logger::log("[{$type}] Final prompt preview: " . substr($prompt, 0, 200) . "...");

        $image_path = get_attached_file($attachment_id);

        if (!$api_key || !$image_path || !file_exists($image_path)) {
            Logger::log("[{$type}] ERROR: Missing API key or image path - API key exists: " . (!empty($api_key) ? 'YES' : 'NO') . ", Image path: $image_path");
            return new WP_Error('missing_data', 'Missing Gemini API key or image path.');
        }

        $image_data = file_get_contents($image_path);
        if (!$image_data) {
            Logger::log("[{$type}] ERROR: Failed to read image file");
            return new WP_Error('image_error', 'Failed to load image file.');
        }

        $mime_type = self::getMimeType($image_path);
        $base64 = base64_encode($image_data);
        Logger::log("[{$type}] Image processed - MIME: $mime_type, Base64 length: " . strlen($base64));

        // Gemini API request structure
        $body = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mime_type,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 150,
                'temperature' => 0.1
            ]
        ];

        Logger::log("[{$type}] Making Gemini API request...");
        $response = wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            Logger::log("[{$type}] Gemini error: " . $response->get_error_message());
            return new WP_Error('api_error', 'Gemini API error: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        Logger::log("[{$type}] Gemini API response received, checking for errors...");

        // Check for API errors
        if (isset($data['error'])) {
            Logger::log("[{$type}] Gemini API error: " . json_encode($data['error']));
            return new WP_Error('api_error', 'Gemini API error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        // Log full response structure for debugging
        Logger::log("[{$type}] Full Gemini API response structure: " . json_encode($data, JSON_PRETTY_PRINT));

        $content = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

        Logger::log("[{$type}] Raw Gemini response (keywords: '$keywords'): " . $content);

        if (!$content) {
            Logger::log("[{$type}] Empty response from Gemini API - full response: " . json_encode($data));
            return new WP_Error('empty_response', 'Empty response from AI API');
        }

        // Check if AI refused to generate content - similar to OpenAI logic
        $content_lower = strtolower($content);
        $ai_refused = false;
        
        if (
            (str_contains($content_lower, "sorry") && (
                str_contains($content_lower, "can't assist") ||
                str_contains($content_lower, "can't identify") ||
                str_contains($content_lower, "can't describe") ||
                str_contains($content_lower, "can't help") ||
                str_contains($content_lower, "cannot identify") ||
                str_contains($content_lower, "cannot describe") ||
                str_contains($content_lower, "cannot help") ||
                str_contains($content_lower, "don't know who this is")
            )) ||
            str_contains($content_lower, "i'm not able to identify") ||
            str_contains($content_lower, "i cannot identify") ||
            str_contains($content_lower, "i'm unable to") ||
            str_contains($content_lower, "i am unable to") ||
            str_contains($content_lower, "don't know who this is") ||
            str_contains($content_lower, "do not know who this is") ||
            (str_contains($content_lower, "unable") && str_contains($content_lower, "identify")) ||
            // Generic refusal patterns
            (str_contains($content_lower, "i can't") && str_contains($content_lower, "help")) ||
            (str_contains($content_lower, "i cannot") && str_contains($content_lower, "help"))
        ) {
            Logger::log("[{$type}] Gemini refused to generate content");
            return new WP_Error('ai_refused', 'AI refused to generate content for this image');
        }

        // Clean up quotes and code block syntax
        $content = trim($content);
        $content = trim($content, '"');
        $content = preg_replace('/```\w*\n?|\n```$/', '', $content); // Remove code block markers
        $content = trim($content); // Final trim after cleaning

        // Handle multiple suggestions - take only the first one
        $content = self::extractFirstSuggestion($content);

        // For filenames, ensure we don't have .jpg/.png extension in the response
        if ($type === 'filename') {
            $content = preg_replace('/\.(jpg|png|gif|jpeg)$/i', '', $content);
        }

        // Validate the result contains expected professional context if keywords were provided
        if (!empty($keywords) && $type === 'alt') {
            $keyword_parts = explode(',', strtolower($keywords));
            $content_lower = strtolower($content);
            $found_context = false;

            foreach ($keyword_parts as $part) {
                $part = trim($part);
                if (!empty($part) && str_contains($content_lower, $part)) {
                    $found_context = true;
                    break;
                }
            }

            if (!$found_context) {
                Logger::log("[{$type}] WARNING: Generated content may not include provided professional context");
            } else {
                Logger::log("[{$type}] SUCCESS: Generated content includes professional context");
            }
        }

        Logger::log("[{$type}] Final cleaned result: " . $content);
        Logger::log("[{$type}] === GEMINI METADATA GENERATION END ===");
        return $content;
    }

    /**
     * Get MIME type with multiple fallback methods
     */
    private static function getMimeType($file_path) {
        // First try WordPress's wp_check_filetype function
        $file_type = wp_check_filetype($file_path);
        if (!empty($file_type['type'])) {
            Logger::log("[MIME] WordPress wp_check_filetype detected: " . $file_type['type']);
            return $file_type['type'];
        }

        // Second try PHP's finfo_file (most reliable if available)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime_type = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                if ($mime_type !== false) {
                    Logger::log("[MIME] finfo_file detected: " . $mime_type);
                    return $mime_type;
                }
            }
        }

        // Third try mime_content_type if available
        if (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file_path);
            if ($mime_type !== false) {
                Logger::log("[MIME] mime_content_type detected: " . $mime_type);
                return $mime_type;
            }
        }

        // Fourth try getimagesize for images
        $image_info = getimagesize($file_path);
        if ($image_info !== false && !empty($image_info['mime'])) {
            Logger::log("[MIME] getimagesize detected: " . $image_info['mime']);
            return $image_info['mime'];
        }

        // Final fallback based on file extension
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $extension_to_mime = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'ico' => 'image/x-icon'
        ];

        if (isset($extension_to_mime[$extension])) {
            Logger::log("[MIME] Extension fallback detected: " . $extension_to_mime[$extension]);
            return $extension_to_mime[$extension];
        }

        // Ultimate fallback
        Logger::log("[MIME] WARNING: Could not detect MIME type, using jpeg fallback");
        return 'image/jpeg';
    }

    /**
     * Extract the first suggestion from AI response that may contain multiple options
     */
    private static function extractFirstSuggestion($content) {
        // Common patterns for multiple suggestions
        $patterns = [
            // Section headers followed by bullet lists (skip section headers, get first actual item)
            '/\*\*(?:Short & Sweet|More Descriptive|Keyword Focused):\*\*\s*\n\s*\*\s*(.*?)(?=\n|$)/is',
            // Introductory text followed by bullet list with markdown bold
            '/Here are.*?(?:title|option|suggestion)s?.*?:\s*\n\s*[\*\-•]\s*\*\*(.*?)\*\*/is',
            // Introductory text followed by simple bullet list  
            '/Here are.*?(?:title|option|suggestion)s?.*?:\s*\n\s*[\*\-•]\s*(.*?)(?=\n|$)/is',
            // Numbered lists: "1. First option\n2. Second option"
            '/^(?:\d+\.?\s*)(.*?)(?=\n\d+\.|\n[A-Z]|\n-|\n\*|$)/s',
            // Bullet lists with markdown bold: "* **First option**\n* **Second option**"
            '/^\s*[\*\-•]\s*\*\*(.*?)\*\*(?=\n|$)/m',
            // Simple bullet lists: "• First option\n• Second option" or "- First option\n- Second option"
            '/^\s*[•\-\*]\s*(.*?)(?=\n[•\-\*]|\n\d+\.|\n[A-Z]|$)/s',
            // Multiple sentences separated by newlines with options
            '/^(.*?)(?=\n(?:Alternative|Another|Option|Or:|Also:|Additionally:))/s',
            // Lines starting with "Option", "Alternative", etc.
            '/^(.*?)(?=\n(?:Option \d+|Alternative \d+|Choice \d+))/s'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($content), $matches)) {
                $first_suggestion = trim($matches[1]);
                // Clean up any remaining markdown
                $first_suggestion = preg_replace('/\*\*(.*?)\*\*/', '$1', $first_suggestion);
                $first_suggestion = trim($first_suggestion);
                
                if (!empty($first_suggestion) && strlen($first_suggestion) > 5) {
                    Logger::log("[EXTRACT] Found multiple suggestions, using first: " . substr($first_suggestion, 0, 100) . "...");
                    return $first_suggestion;
                }
            }
        }

        // If no pattern matches, check for multiple sentences and take the first complete one
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($content));
        if (count($sentences) > 1) {
            $first_sentence = trim($sentences[0]);
            if (strlen($first_sentence) > 5) {
                Logger::log("[EXTRACT] Multiple sentences detected, using first: " . substr($first_sentence, 0, 100) . "...");
                return $first_sentence;
            }
        }

        // Return original content if no multiple suggestions detected
        return $content;
    }

}
