<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Image Alt Text Generator — generates and saves alt text for media library images.
 */
class TABAIX_SEO_Alt_Text
{

    private static $instance = null;
    const META_KEY = '_tabaix_seo_ai_alt_generated';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Hook into media library list view to show alt text status
        add_filter('manage_media_columns', [$this, 'add_media_column']);
        add_action('manage_media_custom_column', [$this, 'render_media_column'], 10, 2);

        // Auto-generate alt text when image is uploaded (if enabled)
        add_action('add_attachment', [$this, 'maybe_auto_generate']);
    }

    // ── Media Library Column ──────────────────────────────────────────────

    public function add_media_column($columns)
    {
        $columns['tabaix_seo_alt_text'] = '✦ AI Alt Text';
        return $columns;
    }

    public function render_media_column($column_name, $attachment_id)
    {
        if ($column_name !== 'tabaix_seo_alt_text')
            return;

        if (!wp_attachment_is_image($attachment_id)) {
            echo '<span style="color:#94a3b8;font-size:11px">Not an image</span>';
            return;
        }

        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $ai_generated = get_post_meta($attachment_id, self::META_KEY, true);

        echo '<div style="min-width:160px">';
        if ($current_alt) {
            echo '<span style="color:#10b981;font-size:11px;font-weight:600">' . esc_html(substr($current_alt, 0, 40)) . (strlen($current_alt) > 40 ? '…' : '') . '</span>';
            if ($ai_generated) {
                echo '<br><span style="font-size:10px;color:#6366f1">✦ AI Generated</span>';
            }
        } else {
            echo '<span style="color:#f59e0b;font-size:11px">No alt text</span>';
        }
        echo '<br><a href="#" class="tabaix-seo-gen-alt-link" data-id="' . esc_attr($attachment_id) . '" style="font-size:11px;color:#6366f1;text-decoration:none">✦ Generate</a>';
        echo '</div>';
    }

    // ── Auto-generate on upload ───────────────────────────────────────────

    public function maybe_auto_generate($attachment_id)
    {
        // Only if image and auto-generate is enabled in settings
        if (!TABAIX_SEO_Settings::get('alt_text_auto', 0))
            return;
        if (!wp_attachment_is_image($attachment_id))
            return;

        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing))
            return; // Don't overwrite existing

        self::generate_and_save($attachment_id);
    }

    // ── Core Generation ───────────────────────────────────────────────────

    /**
     * Generate alt text for an attachment using its filename, title, and context.
     */
    public static function generate_alt_text($attachment_id)
    {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return new WP_Error('not_found', 'Attachment not found.');
        }

        $filename = basename(get_attached_file($attachment_id));
        $title = $attachment->post_title;
        $caption = $attachment->post_excerpt;
        $description = $attachment->post_content;
        $image_url = wp_get_attachment_url($attachment_id);

        // Get file metadata
        $meta = wp_get_attachment_metadata($attachment_id);
        $dims = isset($meta['width'], $meta['height']) ? "{$meta['width']}x{$meta['height']}" : 'unknown';

        $context_parts = array_filter([$title, $caption, $description]);
        $context = implode(' | ', $context_parts);

        $prompt = "Generate a highly descriptive, SEO-optimized alt text for an image.

Image filename: {$filename}
Image title: {$title}
Image dimensions: {$dims}" . ($context ? "\nContext: {$context}" : '') . "

Requirements:
- Describe what is visually in the image based on available context
- Be specific and descriptive (not generic like 'image of...')
- Include relevant keywords naturally
- Keep it under 125 characters
- Do NOT start with 'Image of', 'Photo of', 'Picture of'
- Do NOT use quotes
- Write in plain text, no markdown

Return ONLY the alt text, nothing else.";

        return TABAIX_SEO_API::generate($prompt);
    }

    /**
     * Generate and immediately save alt text for an attachment.
     */
    public static function generate_and_save($attachment_id)
    {
        $alt = self::generate_alt_text($attachment_id);
        if (is_wp_error($alt))
            return $alt;

        $alt = trim($alt);
        // Remove leading/trailing quotes if AI added them
        $alt = trim($alt, '"\'`');

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        update_post_meta($attachment_id, self::META_KEY, 1);

        return $alt;
    }

    /**
     * Bulk generate alt text for all images missing alt text.
     */
    public static function bulk_generate($limit = 10)
    {
        $images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        // Also query images with empty alt text
        $images_empty = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ]);

        $all = array_unique(array_merge($images, $images_empty), SORT_REGULAR);
        $all = array_slice($all, 0, $limit);

        $results = [];
        foreach ($all as $img) {
            $result = self::generate_and_save($img->ID);
            $results[] = [
                'id' => $img->ID,
                'filename' => basename(get_attached_file($img->ID)),
                'alt_text' => is_wp_error($result) ? null : $result,
                'error' => is_wp_error($result) ? $result->get_error_message() : null,
            ];
        }

        return $results;
    }

    /**
     * Get count of images missing alt text.
     */
    public static function count_missing_alt()
    {
        global $wpdb;
        return (int) $wpdb->get_var("
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%'
              AND p.post_status = 'inherit'
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
    }
}
