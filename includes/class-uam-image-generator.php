<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Image_Generator
{

    /**
     * Generate a featured image concept (prompt) or image URL.
     * Returns image URL string or WP_Error.
     */
    public static function generate_featured_image($post_title, $post_excerpt = '', $style = 'photorealistic', $aspect_ratio = '16:9')
    {
        // Step 1: Ask AI to craft the best image prompt
        $style_guides = [
            'photorealistic' => 'ultra-realistic photography, DSLR quality, professional lighting',
            'illustration' => 'flat vector illustration, modern design, vibrant colors',
            'digital-art' => 'digital artwork, cinematic, detailed, artstation quality',
            'minimalist' => 'clean minimalist design, simple shapes, elegant typography',
            'watercolor' => 'beautiful watercolor painting, soft textures, artistic',
        ];
        $style_guide = $style_guides[$style] ?? $style_guides['photorealistic'];

        $meta_prompt = "Create a detailed image generation prompt for a blog post featured image.

Post title: \"{$post_title}\"
Excerpt: \"{$post_excerpt}\"
Style: {$style_guide}

Return ONLY the image prompt (no explanation, no quotes). The prompt should be 1-2 sentences, descriptive, and suitable for DALL-E or Stable Diffusion. Do NOT include any text or words in the image.";

        $image_prompt = TABAIX_SEO_API::generate($meta_prompt);
        if (is_wp_error($image_prompt))
            return $image_prompt;

        // Step 2: Generate the image
        $provider = TABAIX_SEO_Settings::get('provider', 'gemini');

        // Use DALL-E 3 for OpenAI, otherwise use Gemini/Imagen
        if ($provider === 'openai') {
            return TABAIX_SEO_API::openai_image($image_prompt);
        } else {
            // Gemini image generation (auto-routes through Flash Image → Imagen 4)
            $result = TABAIX_SEO_API::gemini_generate_image($image_prompt, $aspect_ratio);
            if (is_wp_error($result)) {
                return $result;
            }
            // Return the first image as a data URI
            return is_array($result) ? $result[0] : $result;
        }
    }

    /**
     * Save a remote image URL / base64 to WordPress media library.
     */
    public static function save_image_to_library($image_source, $post_id = 0, $filename = 'ai-generated.png')
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        if (strpos($image_source, 'data:image') === 0) {
            // base64 image
            $parts = explode(',', $image_source, 2);
            $b64data = $parts[1] ?? '';
            $img_data = base64_decode($b64data);

            $upload = wp_upload_bits($filename, null, $img_data);
            if ($upload['error']) {
                return new WP_Error('upload_error', $upload['error']);
            }

            $file_path = $upload['file'];
            $file_url = $upload['url'];
            $mime_type = 'image/png';
        } else {
            // Remote URL
            $tmp = download_url($image_source);
            if (is_wp_error($tmp))
                return $tmp;

            $file_array = [
                'name' => $filename,
                'tmp_name' => $tmp,
            ];

            $attach_id = media_handle_sideload($file_array, $post_id);
            @wp_delete_file($tmp);

            if (is_wp_error($attach_id))
                return $attach_id;
            return $attach_id;
        }

        // Attach to media library
        $attachment = [
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_mime_type' => $mime_type,
            'guid' => $file_url,
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Generate product image variants description.
     */
    public static function generate_product_image_prompt($product_name, $variant = 'white background', $style = 'commercial photography')
    {
        $prompt = "Create a professional product photography prompt for: \"{$product_name}\".
Background: {$variant}
Style: {$style}

Return ONLY the image prompt (1-2 sentences). Perfect for e-commerce, clean, professional.";
        return TABAIX_SEO_API::generate($prompt);
    }

    /**
     * Get image optimization recommendations.
     */
    public static function get_optimization_tips($filename, $file_size_kb, $dimensions)
    {
        $prompt = "Provide image optimization recommendations for a WordPress website.
Filename: {$filename}
File size: {$file_size_kb}KB
Dimensions: {$dimensions}

Return ONLY valid JSON:
{
  \"recommended_size_kb\": <number>,
  \"recommended_dimensions\": \"<width>x<height>\",
  \"format_suggestion\": \"webp|jpeg|png\",
  \"optimization_tips\": [\"tip1\",\"tip2\",\"tip3\"],
  \"estimated_savings_percent\": <number>
}";
        return TABAIX_SEO_API::generate($prompt);
    }
}
