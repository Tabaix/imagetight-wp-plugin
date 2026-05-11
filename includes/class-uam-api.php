<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Unified API handler for Gemini and OpenAI providers.
 *
 * Supported Gemini models (as of 2026-02):
 *  - gemini-3.1-pro-preview      (newest, preview)
 *  - gemini-3-flash-preview      (fastest, preview)
 *  - gemini-2.5-pro              (stable, proven)
 *
 * Supported OpenAI models (as of 2026-02):
 *  - gpt-5.2                     (latest, best quality)
 *  - gpt-5.1-mini                (fast, cost-effective)
 *  - gpt-4o                      (legacy, multimodal)
 */
class TABAIX_SEO_API
{
    // Base endpoints
    const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    const OPENAI_BASE = 'https://api.openai.com/v1/';
    const IMAGEN_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict';
    const GEMINI_IMAGE_MODEL = 'gemini-2.5-flash-image';

    // Default fallback models
    const DEFAULT_GEMINI_MODEL = 'gemini-2.5-pro';
    const DEFAULT_OPENAI_MODEL = 'gpt-5.2';

    // Retry settings
    const MAX_RETRIES = 3;
    const RETRY_BASE_DELAY = 2; // seconds

    // Timestamp of last API call (rate throttle)
    private static $last_call_time = 0;

    /**
     * Throttle API calls — enforce minimum gap between requests.
     */
    private static function throttle($min_gap = 1.0)
    {
        $now = microtime(true);
        $elapsed = $now - self::$last_call_time;
        if (self::$last_call_time > 0 && $elapsed < $min_gap) {
            $wait = (int) ceil(($min_gap - $elapsed) * 1000000);
            usleep($wait);
        }
        self::$last_call_time = microtime(true);
    }

    // ─── Gemini Text Generation ───────────────────────────────────────────────

    public static function gemini_generate($prompt, $model = null, $options = [])
    {
        $custom_gemini_key = TABAIX_SEO_Settings::get('gemini_api_key');

        if (empty($custom_gemini_key)) {
            return new WP_Error('no_key', __('Please go to Settings and enter your free Google AI Studio key to use Gemini AI features.', 'tabaix-seo-optimizer-pro'));
        }

        $model = $model ?? TABAIX_SEO_Settings::get('gemini_model', self::DEFAULT_GEMINI_MODEL);
        $url = self::GEMINI_BASE . "{$model}:generateContent?key={$custom_gemini_key}";

        $temperature = $options['temperature'] ?? 0.7;
        $max_tokens = $options['max_tokens'] ?? 2048;
        $top_p = $options['top_p'] ?? 0.95;

        // Build parts — support optional image (base64 or url)
        $parts = [['text' => $prompt]];
        if (!empty($options['image_base64']) && !empty($options['image_mime'])) {
            $parts = [
                [
                    'inline_data' => [
                        'mime_type' => $options['image_mime'],
                        'data' => $options['image_base64'],
                    ]
                ],
                ['text' => $prompt],
            ];
        }

        $body = wp_json_encode([
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => (float) $temperature,
                'maxOutputTokens' => (int) $max_tokens,
                'topP' => (float) $top_p,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ]);

        // Retry loop with exponential backoff for 429 errors
        $last_error = '';
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                $delay = self::RETRY_BASE_DELAY * pow(2, $attempt - 1);
                sleep($delay);
            }

            self::throttle();

            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
                'timeout' => 90,
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('network_error', 'Network error: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($code === 200) {
                break; // Success!
            }

            // On 429, retry (unless last attempt)
            if ($code === 429 && $attempt < self::MAX_RETRIES) {
                $last_error = 'Rate limited (429). Retrying...';
                continue;
            }

            // Non-retryable error or final retry exhausted
            $msg = $data['error']['message'] ?? "Gemini API error (HTTP {$code})";
            if ($code === 401 || $code === 403) {
                $msg = 'Invalid Gemini API key. Please check your key in Settings.';
            } elseif ($code === 429) {
                $msg = 'Gemini rate limit exceeded after ' . self::MAX_RETRIES . ' retries. Please wait a minute and try again, or switch to a faster model like gemini-2.0-flash.';
            } elseif ($code === 404) {
                $msg = "Model '{$model}' not found. Please select a different model in Settings.";
            }
            return new WP_Error('api_error', $msg);
        }

        // Handle blocked content
        $finish_reason = $data['candidates'][0]['finishReason'] ?? '';
        if ($finish_reason === 'SAFETY') {
            return new WP_Error('safety_block', 'Content was blocked by Gemini safety filters. Try rephrasing your prompt.');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ─── Gemini with Image (Multimodal) ──────────────────────────────────────

    public static function gemini_generate_with_image($prompt, $image_path, $model = null)
    {
        if (!file_exists($image_path)) {
            return new WP_Error('no_file', 'Image file not found: ' . $image_path);
        }

        $mime = mime_content_type($image_path);
        // Only supported image types for Gemini Vision
        $supported = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $supported, true)) {
            // Try to describe from URL instead
            return self::gemini_generate($prompt, $model);
        }

        $image_data = file_get_contents($image_path);
        if (false === $image_data) {
            return new WP_Error('read_error', 'Failed to read image file.');
        }
        $b64 = base64_encode($image_data);
        return self::gemini_generate($prompt, $model, [
            'image_base64' => $b64,
            'image_mime' => $mime,
        ]);
    }

    // ─── Google Imagen 3 (Text-to-Image) ─────────────────────────────────────

    /**
     * Generate images using Gemini 2.5 Flash Image (primary) or Imagen 4 (fallback).
     */
    public static function gemini_generate_image($prompt, $aspect_ratio = '1:1', $count = 1)
    {
        $api_key = TABAIX_SEO_Settings::get('gemini_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_key', 'Gemini API key is required for image generation.');
        }

        // === Primary: Gemini 2.5 Flash Image (generateContent API) ===
        $result = self::gemini_flash_image($prompt, $aspect_ratio, $api_key);
        if (!is_wp_error($result)) {
            return $result;
        }

        // === Fallback: Imagen 4 (:predict API) ===
        return self::imagen4_generate($prompt, $aspect_ratio, $count, $api_key);
    }

    /**
     * Generate image using Gemini 2.5 Flash Image model.
     */
    private static function gemini_flash_image($prompt, $aspect_ratio, $api_key)
    {
        $url = self::GEMINI_BASE . self::GEMINI_IMAGE_MODEL . ":generateContent?key={$api_key}";

        $body_data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'responseModalities' => ['Image'],
            ],
        ];

        // Add aspect ratio config if not default
        if ($aspect_ratio && $aspect_ratio !== '1:1') {
            $body_data['generationConfig']['imageConfig'] = [
                'aspectRatio' => $aspect_ratio,
            ];
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body_data),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? "Gemini Flash Image error (HTTP {$code})";
            return new WP_Error('gemini_image_error', $msg);
        }

        // Parse generateContent response — images are in candidates[].content.parts[].inlineData
        $images = [];
        foreach ($data['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (!empty($part['inlineData']['data'])) {
                    $mime = $part['inlineData']['mimeType'] ?? 'image/png';
                    $images[] = "data:{$mime};base64," . $part['inlineData']['data'];
                }
            }
        }

        if (!empty($images)) {
            return $images;
        }

        return new WP_Error('no_image', 'No images returned from Gemini Flash Image.');
    }

    /**
     * Generate images using Imagen 4 model (:predict endpoint).
     */
    private static function imagen4_generate($prompt, $aspect_ratio, $count, $api_key)
    {
        $url = self::IMAGEN_BASE . "?key={$api_key}";
        $body = wp_json_encode([
            'instances' => [['prompt' => $prompt]],
            'parameters' => [
                'sampleCount' => min((int) $count, 4),
                'aspectRatio' => $aspect_ratio,
                'safetyFilterLevel' => 'block_only_high',
                'personGeneration' => 'allow_adult',
            ],
        ]);

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? "Imagen 4 API error (HTTP {$code})";
            if ($code === 403 || $code === 404) {
                return new WP_Error('imagen_unavailable', 'Neither Gemini Flash Image nor Imagen 4 could generate images. Error: ' . $msg);
            }
            return new WP_Error('api_error', $msg);
        }

        // Parse :predict response — images in predictions[].bytesBase64Encoded
        $images = [];
        foreach ($data['predictions'] ?? [] as $pred) {
            if (!empty($pred['bytesBase64Encoded'])) {
                $images[] = 'data:image/png;base64,' . $pred['bytesBase64Encoded'];
            }
        }

        return !empty($images) ? $images : new WP_Error('no_image', 'No images returned from Imagen 4 API.');
    }

    // ─── OpenAI Text Generation ───────────────────────────────────────────────

    public static function openai_generate($prompt, $system = 'You are a helpful AI assistant for WordPress.', $model = null, $options = [])
    {
        $api_key = TABAIX_SEO_Settings::get('openai_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_key', __('OpenAI API key is not configured. Please go to Settings → API Configuration and add your key.', 'tabaix-seo-optimizer-pro'));
        }

        $model = $model ?? TABAIX_SEO_Settings::get('openai_model', self::DEFAULT_OPENAI_MODEL);
        $temperature = $options['temperature'] ?? 0.7;
        $max_tokens = $options['max_tokens'] ?? 2048;

        $url = self::OPENAI_BASE . 'chat/completions';
        $body = wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => (int) $max_tokens,
            'temperature' => (float) $temperature,
        ]);

        // Retry loop with exponential backoff for 429 errors
        $last_error = '';
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                $delay = self::RETRY_BASE_DELAY * pow(2, $attempt - 1);
                sleep($delay);
            }

            self::throttle();

            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => $body,
                'timeout' => 90,
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('network_error', 'Network error: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($code === 200) {
                break;
            }

            if ($code === 429 && $attempt < self::MAX_RETRIES) {
                $last_error = 'Rate limited (429). Retrying...';
                continue;
            }

            $msg = $data['error']['message'] ?? "OpenAI API error (HTTP {$code})";
            if ($code === 401) {
                $msg = 'Invalid OpenAI API key. Please check your key in Settings.';
            } elseif ($code === 429) {
                $msg = 'OpenAI rate limit exceeded after ' . self::MAX_RETRIES . ' retries. Please wait a minute and try again.';
            } elseif ($code === 404) {
                $msg = "OpenAI model '{$model}' not found. Please select a different model in Settings.";
            }
            return new WP_Error('api_error', $msg);
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }

    // ─── OpenAI with Image (Vision) ───────────────────────────────────────────

    public static function openai_generate_with_image($prompt, $image_path_or_url, $model = null)
    {
        $api_key = TABAIX_SEO_Settings::get('openai_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_key', 'OpenAI API key is required for image analysis.');
        }

        $model = $model ?? 'gpt-4o'; // Vision requires gpt-4o family

        // Build image content
        if (filter_var($image_path_or_url, FILTER_VALIDATE_URL)) {
            $image_content = ['type' => 'image_url', 'image_url' => ['url' => $image_path_or_url, 'detail' => 'low']];
        } elseif (file_exists($image_path_or_url)) {
            $mime = mime_content_type($image_path_or_url);
            $image_data = file_get_contents($image_path_or_url);
            if (false === $image_data) {
                return new WP_Error('read_error', 'Failed to read image file.');
            }
            $b64 = base64_encode($image_data);
            $image_content = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}", 'detail' => 'low']];
        } else {
            return new WP_Error('no_image', 'Image not found: ' . $image_path_or_url);
        }

        $url = self::OPENAI_BASE . 'chat/completions';
        $body = wp_json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        $image_content,
                    ],
                ]
            ],
            'max_tokens' => 400,
            'temperature' => 0.5,
        ]);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response))
            return $response;
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            return new WP_Error('api_error', $data['error']['message'] ?? "Vision API error (HTTP {$code})");
        }
        return $data['choices'][0]['message']['content'] ?? '';
    }

    // ─── DALL·E 3 Image Generation ────────────────────────────────────────────

    public static function openai_image($prompt, $size = '1024x1024', $quality = 'standard')
    {
        $api_key = TABAIX_SEO_Settings::get('openai_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_key', __('OpenAI API key is not configured.', 'tabaix-seo-optimizer-pro'));
        }

        $url = self::OPENAI_BASE . 'images/generations';
        $body = wp_json_encode([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality, // 'standard' or 'hd'
        ]);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => $body,
            'timeout' => 120,
        ]);

        if (is_wp_error($response))
            return $response;
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            return new WP_Error('api_error', $data['error']['message'] ?? 'Image API error');
        }
        return $data['data'][0]['url'] ?? '';
    }

    // ─── Smart Dispatcher ─────────────────────────────────────────────────────

    /**
     * Generate text using the currently active provider.
     */
    public static function generate($prompt, $system = '', $options = [])
    {
        $provider = TABAIX_SEO_Settings::get('provider', 'gemini');

        if ($provider === 'openai') {
            $sys = $system ?: 'You are a helpful AI assistant for WordPress. Respond in clear, professional language.';
            $result = self::openai_generate($prompt, $sys, null, $options);
            // Auto-fallback to Gemini if OpenAI key not set
            if (is_wp_error($result) && $result->get_error_code() === 'no_key') {
                $gemini_key = TABAIX_SEO_Settings::get('gemini_api_key');
                if (!empty($gemini_key)) {
                    $full = $system ? "{$system}\n\n{$prompt}" : $prompt;
                    return self::gemini_generate($full, null, $options);
                }
            }
            return $result;
        }

        // Gemini (default)
        $full = $system ? "{$system}\n\n{$prompt}" : $prompt;
        $result = self::gemini_generate($full, null, $options);
        // Auto-fallback to OpenAI if Gemini key not set
        if (is_wp_error($result) && $result->get_error_code() === 'no_key') {
            $openai_key = TABAIX_SEO_Settings::get('openai_api_key');
            if (!empty($openai_key)) {
                $sys = $system ?: 'You are a helpful AI assistant for WordPress.';
                return self::openai_generate($prompt, $sys, null, $options);
            }
        }
        return $result;
    }

    /**
     * Generate text with an image.
     */
    public static function generate_with_image($prompt, $image_path, $options = [])
    {
        $provider = TABAIX_SEO_Settings::get('provider', 'gemini');
        if ($provider === 'openai') {
            return self::openai_generate_with_image($prompt, $image_path);
        }
        return self::gemini_generate_with_image($prompt, $image_path);
    }

    // ─── Connection Test ──────────────────────────────────────────────────────

    /**
     * Test an API key without consuming credits.
     */
    public static function test_connection($provider = null)
    {
        $provider = $provider ?? TABAIX_SEO_Settings::get('provider', 'gemini');

        if ($provider === 'gemini') {
            $api_key = TABAIX_SEO_Settings::get('gemini_api_key');
            if (empty($api_key)) {
                return new WP_Error('no_key', 'Gemini API key is not set.');
            }
            // Use models list endpoint — lightweight, no token cost
            $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$api_key}&pageSize=1";
            $response = wp_remote_get($url, ['timeout' => 15]);
            if (is_wp_error($response))
                return $response;
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                return ['success' => true, 'models_available' => count($data['models'] ?? [])];
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $msg = $data['error']['message'] ?? "API error (HTTP {$code})";
            if ($code === 400 && str_contains($msg, 'API key not valid')) {
                $msg = 'Invalid API key. Please check you copied the full key from AI Studio.';
            }
            return new WP_Error('test_failed', $msg);
        }

        if ($provider === 'openai') {
            $api_key = TABAIX_SEO_Settings::get('openai_api_key');
            if (empty($api_key)) {
                return new WP_Error('no_key', 'OpenAI API key is not set.');
            }
            // Use models list endpoint — lightweight
            $url = self::OPENAI_BASE . 'models?limit=1';
            $response = wp_remote_get($url, [
                'headers' => ['Authorization' => 'Bearer ' . $api_key],
                'timeout' => 15,
            ]);
            if (is_wp_error($response))
                return $response;
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                return ['success' => true];
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $msg = $data['error']['message'] ?? "API error (HTTP {$code})";
            if ($code === 401) {
                $msg = 'Invalid OpenAI API key. Make sure it starts with "sk-".';
            }
            return new WP_Error('test_failed', $msg);
        }

        return new WP_Error('unknown_provider', "Unknown provider: {$provider}");
    }

    // ─── Available Models ─────────────────────────────────────────────────────

    public static function gemini_models()
    {
        return [
            'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (Newest)',
            'gemini-3-pro-preview' => 'Gemini 3 Pro (Large Context)',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro (Stable High Quality)',
            'gemini-3-flash-preview' => 'Gemini 3 Flash (Fastest)',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Balanced)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash (Fast & Stable)',
            'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (Cheapest)',
        ];
    }

    public static function openai_models()
    {
        return [
            'gpt-5.2' => 'GPT-5.2 (Latest)',
            'gpt-5.1-mini' => 'GPT-5.1 Mini',
            'gpt-4o' => 'GPT-4o (Legacy)',
        ];
    }
}
