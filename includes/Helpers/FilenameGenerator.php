<?php

namespace PicPilotMeta\Helpers;

use PicPilotMeta\Admin\Settings;
use WP_Error;

class FilenameGenerator {
    public static function generate($attachment_id, $keywords = '') {
        $provider = Settings::get('ai_provider', 'openai');
        
        if ($provider === 'gemini') {
            return self::generateWithGemini($attachment_id, $keywords);
        } else {
            return self::generateWithOpenAI($attachment_id, $keywords);
        }
    }

    private static function generateWithOpenAI($attachment_id, $keywords = '') {
        $api_key = Settings::get('openai_api_key');
        $base_prompt = PromptManager::getBasePrompt('filename');
        
        // Add keywords to prompt if provided, but avoid personal identification
        if (!empty($keywords)) {
            $prompt = PromptManager::enhanceWithKeywords($base_prompt, $keywords, 'filename');
        } else {
            $prompt = $base_prompt;
        }

        $image_path = get_attached_file($attachment_id);

        if (!$api_key || !$image_path || !file_exists($image_path)) {
            return new WP_Error('missing_data', 'Missing API key or local file.');
        }

        $image_data = file_get_contents($image_path);
        if (!$image_data) {
            return new WP_Error('image_error', 'Failed to read image file.');
        }

        // Get MIME type with fallbacks
        $mime_type = wp_check_filetype($image_path)['type'];
        if (!$mime_type && function_exists('mime_content_type')) {
            $mime_type = mime_content_type($image_path);
        }
        if (!$mime_type) {
            $mime_type = 'application/octet-stream'; // fallback
        }
        $base64 = base64_encode($image_data);
        $image_url = "data:$mime_type;base64,$base64";

        $body = [
            'model' => 'gpt-4o',
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $image_url]],
                ]
            ]],
            'max_tokens' => 50,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::log('[FILENAME] OpenAI error: ' . $response->get_error_message());
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for API errors
        if (isset($response_body['error'])) {
            Logger::log('[FILENAME] API error: ' . json_encode($response_body['error']));
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        Logger::log('[FILENAME] Full AI response: ' . json_encode($response_body));

        $content = trim($response_body['choices'][0]['message']['content'] ?? '');

        Logger::log('[FILENAME] Raw AI response: ' . $content);

        if (!$content) {
            Logger::log('[FILENAME] Empty response, using fallback');
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        // Check if AI refused or gave explanation instead of filename
        if (
            str_contains(strtolower($content), "sorry") ||
            str_contains(strtolower($content), "can't assist") ||
            str_contains(strtolower($content), "identify") ||
            strlen($content) > 100
        ) {
            Logger::log('[FILENAME] AI refused or gave long response, extracting filename or using fallback');

            // Try to extract a suggested filename from the response
            if (preg_match('/"([^"]+\.(jpg|jpeg|png|gif|webp))"/', $content, $matches)) {
                $content = $matches[1];
                Logger::log('[FILENAME] Extracted filename from response: ' . $content);
            } else {
                return PromptManager::generateFallbackFilename($attachment_id, $keywords);
            }
        }

        // Remove quotes if present
        if (str_starts_with($content, '"') && str_ends_with($content, '"')) {
            $content = trim($content, '"');
        } elseif (str_starts_with($content, '"') && str_ends_with($content, '"')) {
            $content = trim($content, '""');
        }

        // Remove file extension if AI included it
        $content = preg_replace('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', '', $content);

        $sanitized = sanitize_title($content);

        // Check if sanitized result is too long or empty
        if (strlen($sanitized) > 80 || empty($sanitized)) {
            Logger::log('[FILENAME] Sanitized result too long or empty, using fallback');
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        Logger::log('[FILENAME] Sanitized: ' . $sanitized);

        return $sanitized;
    }

    private static function generateWithGemini($attachment_id, $keywords = '') {
        $api_key = Settings::get('gemini_api_key');
        $base_prompt = PromptManager::getBasePrompt('filename');
        
        // Add keywords to prompt if provided, but avoid personal identification
        if (!empty($keywords)) {
            $prompt = PromptManager::enhanceWithKeywords($base_prompt, $keywords, 'filename');
        } else {
            $prompt = $base_prompt;
        }

        $image_path = get_attached_file($attachment_id);

        if (!$api_key || !$image_path || !file_exists($image_path)) {
            return new WP_Error('missing_data', 'Missing Gemini API key or local file.');
        }

        $image_data = file_get_contents($image_path);
        if (!$image_data) {
            return new WP_Error('image_error', 'Failed to read image file.');
        }

        // Get MIME type with fallbacks
        $mime_type = wp_check_filetype($image_path)['type'];
        if (!$mime_type && function_exists('mime_content_type')) {
            $mime_type = mime_content_type($image_path);
        }
        if (!$mime_type) {
            $mime_type = 'application/octet-stream'; // fallback
        }
        $base64 = base64_encode($image_data);

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
                'maxOutputTokens' => 50,
                'temperature' => 0.1
            ]
        ];

        $response = wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::log('[FILENAME] Gemini error: ' . $response->get_error_message());
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for API errors
        if (isset($response_body['error'])) {
            Logger::log('[FILENAME] Gemini API error: ' . json_encode($response_body['error']));
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        Logger::log('[FILENAME] Full Gemini response: ' . json_encode($response_body));

        $content = trim($response_body['candidates'][0]['content']['parts'][0]['text'] ?? '');

        Logger::log('[FILENAME] Raw Gemini response: ' . $content);

        if (!$content) {
            Logger::log('[FILENAME] Empty response, using fallback');
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        // Check if AI refused or gave explanation instead of filename
        if (
            str_contains(strtolower($content), "sorry") ||
            str_contains(strtolower($content), "can't assist") ||
            str_contains(strtolower($content), "identify") ||
            strlen($content) > 100
        ) {
            Logger::log('[FILENAME] Gemini refused or gave long response, extracting filename or using fallback');

            // Try to extract a suggested filename from the response
            if (preg_match('/"([^"]+\.(jpg|jpeg|png|gif|webp))"/', $content, $matches)) {
                $content = $matches[1];
                Logger::log('[FILENAME] Extracted filename from response: ' . $content);
            } else {
                return PromptManager::generateFallbackFilename($attachment_id, $keywords);
            }
        }

        // Remove quotes if present
        if (str_starts_with($content, '"') && str_ends_with($content, '"')) {
            $content = trim($content, '"');
        } elseif (str_starts_with($content, '"') && str_ends_with($content, '"')) {
            $content = trim($content, '""');
        }

        // Remove file extension if AI included it
        $content = preg_replace('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', '', $content);

        $sanitized = sanitize_title($content);

        // Check if sanitized result is too long or empty
        if (strlen($sanitized) > 80 || empty($sanitized)) {
            Logger::log('[FILENAME] Sanitized result too long or empty, using fallback');
            return PromptManager::generateFallbackFilename($attachment_id, $keywords);
        }

        Logger::log('[FILENAME] Sanitized: ' . $sanitized);

        return $sanitized;
    }

}
