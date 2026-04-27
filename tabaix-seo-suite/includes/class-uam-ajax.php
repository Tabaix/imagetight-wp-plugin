<?php
if (!defined('ABSPATH'))
    exit;

class UAM_Ajax
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $actions = [
            'uam_generate_outline',
            'uam_generate_intro',
            'uam_generate_conclusion',
            'uam_generate_full_post',
            'uam_generate_product_desc',
            'uam_generate_meta',
            'uam_generate_social',
            'uam_generate_email',
            'uam_analyze_readability',
            'uam_analyze_keywords',
            'uam_check_originality',
            'uam_fix_grammar',
            'uam_grammar_report',
            'uam_predict_performance',
            'uam_analyze_sentiment',
            'uam_generate_image',
            'uam_generate_product_image_prompt',
            'uam_image_optimization_tips',
            'uam_set_featured_image',
            'uam_analyze_vision',

            'uam_moderate_comment',
            'uam_bulk_moderate',
            'uam_analytics_report',
            'uam_quick_switch_provider',
            'uam_generate_alt_text',
            'uam_bulk_generate_alt_text',
            'uam_save_alt_text',
            'uam_save_seo_meta',
            'uam_test_connection',
            'uam_scan_seo_audit',
            'uam_get_post_audit',
            'uam_optimize_post_seo',

            'uam_scan_links',
            'uam_ai_suggest_links',
            'uam_insert_link',
            'uam_save_autolink_rules',
            'uam_extract_keywords',
            'uam_check_broken_links',
            'uam_fix_link',
            'uam_save_manual_link',
            'uam_delete_manual_link',
            'uam_admin_chatbot',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [$this, 'dispatch']);
            add_action("wp_ajax_nopriv_{$action}", [$this, 'dispatch']);
        }
    }

    public function dispatch()
    {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';

        // Public actions (no auth required)
        $public_actions = [];

        if (!in_array($action, $public_actions, true)) {
            check_ajax_referer('uam_admin_nonce', 'nonce');
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }
        }


        $method = str_replace('uam_', 'handle_', $action);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            wp_send_json_error(['message' => 'Unknown action']);
        }
    }

    // ─── Content Generation ──────────────────────────────────────────────────

    private function handle_generate_outline()
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        if (empty($topic))
            wp_send_json_error(['message' => 'Topic is required']);
        $result = UAM_Content_Generator::generate_outline($topic, $keywords);
        $this->send($result);
    }

    private function handle_generate_intro()
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $result = UAM_Content_Generator::generate_intro($topic, $keywords);
        $this->send($result);
    }

    private function handle_generate_conclusion()
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $points = sanitize_text_field($_POST['main_points'] ?? '');
        $result = UAM_Content_Generator::generate_conclusion($topic, $points);
        $this->send($result);
    }

    private function handle_generate_full_post()
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $word_count = (int) ($_POST['word_count'] ?? 800);
        $result = UAM_Content_Generator::generate_full_post($topic, $keywords, $word_count);
        $this->send($result);
    }

    private function handle_generate_product_desc()
    {
        $name = sanitize_text_field($_POST['product_name'] ?? '');
        $features = sanitize_textarea_field($_POST['features'] ?? '');
        $audience = sanitize_text_field($_POST['audience'] ?? '');
        $result = UAM_Content_Generator::generate_product_description($name, $features, $audience);
        $this->send($result);
    }

    private function handle_generate_meta()
    {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $result = UAM_Content_Generator::generate_meta($title, $content, $keyword);
        $this->send_json_result($result);
    }

    private function handle_generate_social()
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $result = UAM_Content_Generator::generate_social_posts($topic, $url);
        $this->send_json_result($result);
    }

    private function handle_generate_email()
    {
        $type = sanitize_key($_POST['email_type'] ?? 'newsletter');
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $brand = sanitize_text_field($_POST['brand'] ?? '');
        $result = UAM_Content_Generator::generate_email($type, $topic, $brand);
        $this->send_json_result($result);
    }

    // ─── SEO / Optimization ──────────────────────────────────────────────────

    private function handle_analyze_readability()
    {
        $content = wp_kses_post($_POST['content'] ?? '');
        if (empty($content))
            wp_send_json_error(['message' => 'Content required']);
        $result = UAM_SEO_Optimizer::analyze_readability($content);
        $this->send_json_result($result);
    }

    private function handle_analyze_keywords()
    {
        $content = wp_kses_post($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['focus_keyword'] ?? '');
        $result = UAM_SEO_Optimizer::analyze_keywords($content, $keyword);
        $this->send_json_result($result);
    }

    private function handle_check_originality()
    {
        $content = wp_kses_post($_POST['content'] ?? '');
        $result = UAM_SEO_Optimizer::check_originality($content);
        $this->send_json_result($result);
    }

    private function handle_fix_grammar()
    {
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $result = UAM_SEO_Optimizer::fix_grammar($content);
        $this->send($result);
    }

    private function handle_grammar_report()
    {
        $content = wp_kses_post($_POST['content'] ?? '');
        $result = UAM_SEO_Optimizer::grammar_report($content);
        $this->send_json_result($result);
    }

    private function handle_predict_performance()
    {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $niche = sanitize_text_field($_POST['niche'] ?? '');
        $result = UAM_SEO_Optimizer::predict_performance($title, $content, $niche);
        $this->send_json_result($result);
    }

    private function handle_analyze_sentiment()
    {
        $content = wp_kses_post($_POST['content'] ?? '');
        $result = UAM_SEO_Optimizer::analyze_sentiment($content);
        $this->send_json_result($result);
    }

    // ─── Image ───────────────────────────────────────────────────────────────

    private function handle_analyze_vision()
    {
        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        $image_url = esc_url_raw($_POST['image_url'] ?? '');

        if (!$attachment_id && !$image_url) {
            wp_send_json_error(['message' => 'Missing image']);
        }

        $image_path = '';
        if ($attachment_id) {
            $image_path = get_attached_file($attachment_id);
        } else {
            // Download temporary image if it's a URL
            // For simplicity, we'll try to use the URL directly if the API supports it, 
            // but usually we need a path. 
            // UAM_API::generate_with_image expects a path.
        }

        if (!$image_path && $attachment_id) {
            wp_send_json_error(['message' => 'Image file not found on server']);
        }

        // If no path but we have URL, we might need to download it.
        // For now, let's assume we have an attachment ID mostly.

        $prompt = "Analyze this image and provide: 1) A concise SEO-friendly title (max 60 chars). 2) A descriptive alt text. 3) A brief caption. Return as JSON with keys: title, alt, caption.";

        $result = UAM_API::generate_with_image($prompt, $image_path);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Try to parse JSON from result
        $json = json_decode($result, true);
        if (!$json) {
            // fallback if AI didn't return perfect JSON
            $json = [
                'title' => 'Analyzed Image',
                'alt' => $result,
                'caption' => ''
            ];
        }

        wp_send_json_success($json);
    }

    private function handle_generate_image()
    {
        $title = sanitize_text_field($_POST['post_title'] ?? '');
        $excerpt = sanitize_textarea_field($_POST['post_excerpt'] ?? '');
        $style = sanitize_key($_POST['style'] ?? 'photorealistic');
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $aspect_ratio = sanitize_text_field($_POST['aspect_ratio'] ?? '16:9');

        $result = UAM_Image_Generator::generate_featured_image($title, $excerpt, $style, $aspect_ratio);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Optionally save to media library
        if ($_POST['save_to_library'] ?? false) {
            $attach_id = UAM_Image_Generator::save_image_to_library($result, $post_id, "ai-generated-{$post_id}.png");
            if (!is_wp_error($attach_id)) {
                wp_send_json_success([
                    'image_url' => wp_get_attachment_url($attach_id),
                    'attach_id' => $attach_id,
                ]);
            }
        }

        wp_send_json_success(['image_url' => $result]);
    }

    private function handle_set_featured_image()
    {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $attach_id = (int) ($_POST['attach_id'] ?? 0);

        if (!$post_id || !$attach_id) {
            wp_send_json_error(['message' => 'Missing post or attachment ID']);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $result = set_post_thumbnail($post_id, $attach_id);

        if ($result) {
            wp_send_json_success(['message' => 'Featured image set successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to set featured image.']);
        }
    }

    private function handle_generate_product_image_prompt()
    {
        $product = sanitize_text_field($_POST['product_name'] ?? '');
        $variant = sanitize_text_field($_POST['variant'] ?? 'white background');
        $style = sanitize_text_field($_POST['style'] ?? 'commercial photography');
        $result = UAM_Image_Generator::generate_product_image_prompt($product, $variant, $style);
        $this->send($result);
    }

    private function handle_image_optimization_tips()
    {
        $filename = sanitize_file_name($_POST['filename'] ?? '');
        $size_kb = (int) ($_POST['file_size_kb'] ?? 0);
        $dimensions = sanitize_text_field($_POST['dimensions'] ?? '');
        $result = UAM_Image_Generator::get_optimization_tips($filename, $size_kb, $dimensions);
        $this->send_json_result($result);
    }


    // ─── Internal Links ──────────────────────────────────────────────────────

    private function handle_scan_links()
    {
        $result = UAM_Internal_Links::scan_all_posts();
        wp_send_json_success($result);
    }

    private function handle_ai_suggest_links()
    {
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $link_type = sanitize_key($_POST['link_type'] ?? 'all');

        $result = UAM_Internal_Links::ai_suggest_links($post_id, $link_type);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['suggestions' => $result]);
    }

    private function handle_insert_link()
    {
        $post_id = intval($_POST['post_id'] ?? 0);
        $anchor = sanitize_text_field($_POST['anchor'] ?? '');
        $target_url = esc_url_raw($_POST['target_url'] ?? '');

        if (!$post_id || empty($anchor) || empty($target_url))
            wp_send_json_error(['message' => 'Missing required fields.']);

        $options = [
            'nofollow' => !empty($_POST['nofollow']),
            'new_tab' => !empty($_POST['new_tab']),
            'title' => sanitize_text_field($_POST['link_title'] ?? ''),
        ];

        $result = UAM_Internal_Links::insert_link($post_id, $anchor, $target_url, $options);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link inserted successfully!']);
    }

    private function handle_save_autolink_rules()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $rules = isset($_POST['rules']) ? $_POST['rules'] : [];
        $enabled = intval($_POST['autolink_enabled'] ?? 0);

        // Sanitize rules
        $clean_rules = [];
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                $keyword = sanitize_text_field($rule['keyword'] ?? '');
                $url = esc_url_raw($rule['url'] ?? '');
                $max = intval($rule['max_links'] ?? 1);
                if (!empty($keyword) && !empty($url)) {
                    $clean_rules[] = [
                        'keyword' => $keyword,
                        'url' => $url,
                        'max_links' => max(1, $max),
                        'type' => in_array($rule['type'] ?? '', ['internal', 'external']) ? $rule['type'] : 'internal',
                        'nofollow' => !empty($rule['nofollow']),
                        'new_tab' => !empty($rule['new_tab']),
                    ];
                }
            }
        }

        UAM_Internal_Links::save_autolink_rules($clean_rules);
        UAM_Settings::update('autolink_enabled', $enabled);

        wp_send_json_success(['message' => 'Auto-link rules saved!', 'count' => count($clean_rules)]);
    }

    // ─── New Link Management Handlers ────────────────────────────────────────

    private function handle_extract_keywords()
    {
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $result = UAM_Internal_Links::extract_keywords($post_id);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['keywords' => $result]);
    }

    private function handle_check_broken_links()
    {
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $result = UAM_Internal_Links::check_broken_links($post_id);
        wp_send_json_success($result);
    }

    private function handle_fix_link()
    {
        if (!current_user_can('edit_posts'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $post_id = intval($_POST['post_id'] ?? 0);
        $old_url = esc_url_raw($_POST['old_url'] ?? '');
        $new_url = esc_url_raw($_POST['new_url'] ?? '');
        $action = sanitize_key($_POST['fix_action'] ?? 'replace');

        if (!$post_id || empty($old_url))
            wp_send_json_error(['message' => 'Post ID and URL are required.']);

        $result = UAM_Internal_Links::fix_link($post_id, $old_url, $new_url, $action);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link fixed successfully!']);
    }

    private function handle_save_manual_link()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $data = [
            'keyword' => sanitize_text_field($_POST['keyword'] ?? ''),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'type' => sanitize_key($_POST['type'] ?? 'internal'),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'nofollow' => !empty($_POST['nofollow']),
            'new_tab' => !empty($_POST['new_tab']),
            'max_links' => intval($_POST['max_links'] ?? 1),
        ];

        $link_id = sanitize_text_field($_POST['link_id'] ?? '');

        if ($link_id) {
            $result = UAM_Internal_Links::update_manual_link($link_id, $data);
        } else {
            $result = UAM_Internal_Links::save_manual_link($data);
        }

        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link rule saved!', 'link' => $result]);
    }

    private function handle_delete_manual_link()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $link_id = sanitize_text_field($_POST['link_id'] ?? '');
        if (empty($link_id))
            wp_send_json_error(['message' => 'Link ID required.']);

        UAM_Internal_Links::delete_manual_link($link_id);
        wp_send_json_success(['message' => 'Link rule deleted!']);
    }

    // ─── Comment Moderation ──────────────────────────────────────────────────

    private function handle_moderate_comment()
    {
        $text = sanitize_textarea_field($_POST['comment_text'] ?? '');
        $result = UAM_Comment_Moderator::analyze_comment($text);
        $this->send_json_result($result);
    }

    private function handle_bulk_moderate()
    {
        $limit = (int) ($_POST['limit'] ?? 10);
        $results = UAM_Comment_Moderator::bulk_analyze($limit);
        wp_send_json_success(['results' => $results]);
    }

    // ─── Analytics ───────────────────────────────────────────────────────────

    private function handle_analytics_report()
    {
        $data = UAM_Analytics::get_native_analytics();
        $result = UAM_Analytics::generate_report($data);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message(), 'raw_data' => $data]);
        }
        wp_send_json_success(['report' => json_decode($result, true), 'data' => $data]);
    }

    // ─── Image Alt Text ───────────────────────────────────────────────────────

    private function handle_generate_alt_text()
    {
        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Invalid image attachment ID.']);
        }
        $save = !empty($_POST['save']);
        if ($save) {
            $alt = UAM_Alt_Text::generate_and_save($attachment_id);
        } else {
            $alt = UAM_Alt_Text::generate_alt_text($attachment_id);
        }
        if (is_wp_error($alt)) {
            wp_send_json_error(['message' => $alt->get_error_message()]);
        }
        wp_send_json_success([
            'alt_text' => $alt,
            'attachment_id' => $attachment_id,
            'saved' => $save,
        ]);
    }

    private function handle_bulk_generate_alt_text()
    {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $limit = min((int) ($_POST['limit'] ?? 10), 20);
        $results = UAM_Alt_Text::bulk_generate($limit);
        wp_send_json_success([
            'results' => $results,
            'count' => count($results),
        ]);
    }

    // ─── SEO Meta Save ────────────────────────────────────────────────────────

    private function handle_save_seo_meta()
    {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $seo_title = sanitize_text_field($_POST['seo_title'] ?? '');
        $meta_desc = sanitize_text_field($_POST['meta_description'] ?? '');
        $focus_kw = sanitize_text_field($_POST['focus_keyword'] ?? '');

        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID is required.']);
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $saved = UAM_SEO_Meta::save_seo_meta($post_id, $seo_title, $meta_desc, $focus_kw);
        if ($saved) {
            wp_send_json_success([
                'message' => 'SEO meta saved successfully.',
                'seo_title' => $seo_title,
                'meta_desc' => $meta_desc,
                'focus_keyword' => $focus_kw,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save meta.']);
        }
    }

    // ─── Connection Test ──────────────────────────────────────────────────────

    private function handle_test_connection()
    {
        $provider = sanitize_key($_POST['provider'] ?? 'gemini');
        $result = UAM_API::test_connection($provider);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => 'Connection successful! API key is valid.']);
    }

    // ─── Provider Quick Switch ────────────────────────────────────────────────

    private function handle_quick_switch_provider()
    {
        $provider = sanitize_key($_POST['provider'] ?? 'gemini');
        if (!in_array($provider, ['gemini', 'openai'], true)) {
            wp_send_json_error(['message' => 'Invalid provider']);
        }
        UAM_Settings::update('provider', $provider);
        wp_send_json_success(['provider' => $provider, 'message' => "Switched to {$provider}"]);
    }

    // ─── SEO Audit ───────────────────────────────────────────────────────────

    private function handle_scan_seo_audit()
    {
        $post_type = sanitize_key($_POST['post_type'] ?? 'any');
        $results = UAM_SEO_Meta::scan_missing_seo($post_type);
        $stats = UAM_SEO_Meta::get_seo_stats();
        wp_send_json_success([
            'posts' => $results,
            'stats' => $stats,
        ]);
    }

    private function handle_get_post_audit()
    {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID is required.']);
        }
        $audit = UAM_SEO_Meta::get_post_seo_audit($post_id);
        if (is_wp_error($audit)) {
            wp_send_json_error(['message' => $audit->get_error_message()]);
        }
        wp_send_json_success($audit);
    }

    private function handle_optimize_post_seo()
    {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Invalid or unauthorized post.']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found.']);
        }

        $title = $post->post_title;
        $content = wp_strip_all_tags(substr($post->post_content, 0, 800));
        $results = ['meta_generated' => false, 'alt_texts_generated' => 0];

        // Generate missing SEO meta
        $seo_title = get_post_meta($post_id, '_uam_seo_title', true);
        $meta_desc = get_post_meta($post_id, '_uam_seo_description', true);
        $focus_kw = get_post_meta($post_id, '_uam_focus_keyword', true);

        if (empty($seo_title) || empty($meta_desc) || empty($focus_kw)) {
            $meta_result = UAM_Content_Generator::generate_meta($title, $content, $focus_kw);
            if (!is_wp_error($meta_result)) {
                $parsed = json_decode($meta_result, true);
                if ($parsed && json_last_error() === JSON_ERROR_NONE) {
                    $new_title = $parsed['seo_title'] ?? '';
                    $new_desc = $parsed['meta_description'] ?? '';
                    $new_kw = $parsed['focus_keyword'] ?? '';

                    if (empty($seo_title) && !empty($new_title)) {
                        update_post_meta($post_id, '_uam_seo_title', sanitize_text_field($new_title));
                        $results['seo_title'] = $new_title;
                    }
                    if (empty($meta_desc) && !empty($new_desc)) {
                        update_post_meta($post_id, '_uam_seo_description', sanitize_text_field($new_desc));
                        $results['meta_description'] = $new_desc;
                    }
                    if (empty($focus_kw) && !empty($new_kw)) {
                        update_post_meta($post_id, '_uam_focus_keyword', sanitize_text_field($new_kw));
                        $results['focus_keyword'] = $new_kw;
                    }
                    $results['meta_generated'] = true;
                }
            }
        }

        // Generate alt text for images missing it (limit to 5 per request to avoid rate limits)
        $missing_images = UAM_SEO_Meta::get_post_images_missing_alt($post_id);
        $missing_images = array_slice($missing_images, 0, 5);
        $alt_results = [];
        foreach ($missing_images as $i => $img) {
            if (!empty($img['attachment_id'])) {
                // Add delay between calls to avoid 429 rate limits
                if ($i > 0) {
                    sleep(1);
                }
                $alt = UAM_Alt_Text::generate_and_save($img['attachment_id']);
                $alt_results[] = [
                    'attachment_id' => $img['attachment_id'],
                    'filename' => $img['filename'],
                    'alt_text' => is_wp_error($alt) ? null : $alt,
                    'error' => is_wp_error($alt) ? $alt->get_error_message() : null,
                ];
            }
        }
        $results['alt_texts'] = $alt_results;
        $results['alt_texts_generated'] = count(array_filter($alt_results, function ($r) {
            return $r['alt_text'] !== null;
        }));

        wp_send_json_success($results);
    }

    // ─── Admin Chatbot ────────────────────────────────────────────────────────

    private function handle_admin_chatbot()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $user_message = sanitize_textarea_field($_POST['message'] ?? '');
        if (empty($user_message)) {
            wp_send_json_error(['message' => 'Message is empty.']);
        }

        $site_name  = get_bloginfo('name');
        $page_ctx   = sanitize_text_field($_POST['context'] ?? 'admin panel');
        $admin_name = wp_get_current_user()->display_name;

        $system_prompt = "You are an expert AI assistant inside the Ultimate AI Master WordPress plugin admin panel for site '{$site_name}'.\n"
            . "You are helping the site admin '{$admin_name}' who is currently on the '{$page_ctx}' page.\n"
            . "Answer questions about WordPress, SEO, content creation, AI features of this plugin, and web best-practices.\n"
            . "Be concise, professional, and friendly. Format replies in plain text or simple markdown (bold, lists are fine).\n"
            . "Do not reveal internal system details or API keys.";

        $response = UAM_API::generate($user_message, $system_prompt, [
            'temperature' => 0.65,
            'max_tokens'  => 600,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        wp_send_json_success(['result' => $response]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function send($result)
    {
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['result' => $result]);
    }

    private function send_json_result($result)
    {
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        $decoded = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            wp_send_json_success($decoded);
        } else {
            wp_send_json_success(['result' => $result]);
        }
    }
}
