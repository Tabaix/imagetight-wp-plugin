<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Ajax
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
            'tabaix_seo_generate_outline',
            'tabaix_seo_generate_intro',
            'tabaix_seo_generate_conclusion',
            'tabaix_seo_generate_full_post',
            'tabaix_seo_generate_product_desc',
            'tabaix_seo_generate_meta',
            'tabaix_seo_generate_social',
            'tabaix_seo_generate_email',
            'tabaix_seo_analyze_readability',
            'tabaix_seo_analyze_keywords',
            'tabaix_seo_check_originality',
            'tabaix_seo_fix_grammar',
            'tabaix_seo_grammar_report',
            'tabaix_seo_predict_performance',
            'tabaix_seo_analyze_sentiment',
            'tabaix_seo_generate_image',
            'tabaix_seo_generate_product_image_prompt',
            'tabaix_seo_image_optimization_tips',
            'tabaix_seo_set_featured_image',
            'tabaix_seo_analyze_vision',

            'tabaix_seo_moderate_comment',
            'tabaix_seo_bulk_moderate',
            'tabaix_seo_analytics_report',
            'tabaix_seo_quick_switch_provider',
            'tabaix_seo_generate_alt_text',
            'tabaix_seo_bulk_generate_alt_text',
            'tabaix_seo_save_alt_text',
            'tabaix_seo_save_seo_meta',
            'tabaix_seo_test_connection',
            'tabaix_seo_scan_seo_audit',
            'tabaix_seo_get_post_audit',
            'tabaix_seo_optimize_post_seo',

            'tabaix_seo_scan_links',
            'tabaix_seo_ai_suggest_links',
            'tabaix_seo_insert_link',
            'tabaix_seo_save_autolink_rules',
            'tabaix_seo_extract_keywords',
            'tabaix_seo_check_broken_links',
            'tabaix_seo_fix_link',
            'tabaix_seo_save_manual_link',
            'tabaix_seo_delete_manual_link',
            'tabaix_seo_admin_chatbot',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [$this, 'dispatch']);
            // No nopriv for these sensitive admin actions unless explicitly needed
        }
    }

    public function dispatch()
    {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';

        // Security check for all AJAX actions
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'tabaix_seo_admin_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token (nonce).'], 403);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized: You do not have permission to perform this action.'], 403);
        }

        $method = str_replace('tabaix_seo_', 'handle_', $action);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            wp_send_json_error(['message' => 'Unknown action: ' . $action]);
        }
    }

    // ─── Content Generation ──────────────────────────────────────────────────

    private function handle_generate_outline()
    {
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
        if (empty($topic))
            wp_send_json_error(['message' => 'Topic is required']);
        $result = TABAIX_SEO_Content_Generator::generate_outline($topic, $keywords);
        $this->send($result);
    }

    private function handle_generate_intro()
    {
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_intro($topic, $keywords);
        $this->send($result);
    }

    private function handle_generate_conclusion()
    {
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $points = isset($_POST['main_points']) ? sanitize_text_field(wp_unslash($_POST['main_points'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_conclusion($topic, $points);
        $this->send($result);
    }

    private function handle_generate_full_post()
    {
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
        $word_count = isset($_POST['word_count']) ? (int) $_POST['word_count'] : 800;
        $result = TABAIX_SEO_Content_Generator::generate_full_post($topic, $keywords, $word_count);
        $this->send($result);
    }

    private function handle_generate_product_desc()
    {
        $name = isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '';
        $features = isset($_POST['features']) ? sanitize_textarea_field(wp_unslash($_POST['features'])) : '';
        $audience = isset($_POST['audience']) ? sanitize_text_field(wp_unslash($_POST['audience'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_product_description($name, $features, $audience);
        $this->send($result);
    }

    private function handle_generate_meta()
    {
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_meta($title, $content, $keyword);
        $this->send_json_result($result);
    }

    private function handle_generate_social()
    {
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_social_posts($topic, $url);
        $this->send_json_result($result);
    }

    private function handle_generate_email()
    {
        $type = isset($_POST['email_type']) ? sanitize_key(wp_unslash($_POST['email_type'])) : 'newsletter';
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $brand = isset($_POST['brand']) ? sanitize_text_field(wp_unslash($_POST['brand'])) : '';
        $result = TABAIX_SEO_Content_Generator::generate_email($type, $topic, $brand);
        $this->send_json_result($result);
    }

    // ─── SEO / Optimization ──────────────────────────────────────────────────

    private function handle_analyze_readability()
    {
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (empty($content))
            wp_send_json_error(['message' => 'Content required']);
        $result = TABAIX_SEO_SEO_Optimizer::analyze_readability($content);
        $this->send_json_result($result);
    }

    private function handle_analyze_keywords()
    {
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $keyword = isset($_POST['focus_keyword']) ? sanitize_text_field(wp_unslash($_POST['focus_keyword'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::analyze_keywords($content, $keyword);
        $this->send_json_result($result);
    }

    private function handle_check_originality()
    {
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::check_originality($content);
        $this->send_json_result($result);
    }

    private function handle_fix_grammar()
    {
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::fix_grammar($content);
        $this->send($result);
    }

    private function handle_grammar_report()
    {
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::grammar_report($content);
        $this->send_json_result($result);
    }

    private function handle_predict_performance()
    {
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $niche = isset($_POST['niche']) ? sanitize_text_field(wp_unslash($_POST['niche'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::predict_performance($title, $content, $niche);
        $this->send_json_result($result);
    }

    private function handle_analyze_sentiment()
    {
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $result = TABAIX_SEO_SEO_Optimizer::analyze_sentiment($content);
        $this->send_json_result($result);
    }

    // ─── Image ───────────────────────────────────────────────────────────────

    private function handle_analyze_vision()
    {
        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';

        if (!$attachment_id && !$image_url) {
            wp_send_json_error(['message' => 'Missing image']);
        }

        $image_path = '';
        if ($attachment_id) {
            $image_path = get_attached_file($attachment_id);
        }

        if (!$image_path && $attachment_id) {
            wp_send_json_error(['message' => 'Image file not found on server']);
        }

        $prompt = "Analyze this image and provide: 1) A concise SEO-friendly title (max 60 chars). 2) A descriptive alt text. 3) A brief caption. Return as JSON with keys: title, alt, caption.";

        $result = TABAIX_SEO_API::generate_with_image($prompt, $image_path);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $json = json_decode($result, true);
        if (!$json) {
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
        $title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
        $excerpt = isset($_POST['post_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['post_excerpt'])) : '';
        $style = isset($_POST['style']) ? sanitize_key(wp_unslash($_POST['style'])) : 'photorealistic';
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field(wp_unslash($_POST['aspect_ratio'])) : '16:9';

        $result = TABAIX_SEO_Image_Generator::generate_featured_image($title, $excerpt, $style, $aspect_ratio);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (isset($_POST['save_to_library']) && $_POST['save_to_library']) {
            $attach_id = TABAIX_SEO_Image_Generator::save_image_to_library($result, $post_id, "ai-generated-{$post_id}.png");
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
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $attach_id = isset($_POST['attach_id']) ? (int) $_POST['attach_id'] : 0;

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
        $product = isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '';
        $variant = isset($_POST['variant']) ? sanitize_text_field(wp_unslash($_POST['variant'])) : 'white background';
        $style = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : 'commercial photography';
        $result = TABAIX_SEO_Image_Generator::generate_product_image_prompt($product, $variant, $style);
        $this->send($result);
    }

    private function handle_image_optimization_tips()
    {
        $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';
        $size_kb = isset($_POST['file_size_kb']) ? (int) $_POST['file_size_kb'] : 0;
        $dimensions = isset($_POST['dimensions']) ? sanitize_text_field(wp_unslash($_POST['dimensions'])) : '';
        $result = TABAIX_SEO_Image_Generator::get_optimization_tips($filename, $size_kb, $dimensions);
        $this->send_json_result($result);
    }


    // ─── Internal Links ──────────────────────────────────────────────────────

    private function handle_scan_links()
    {
        $result = TABAIX_SEO_Internal_Links::scan_all_posts();
        wp_send_json_success($result);
    }

    private function handle_ai_suggest_links()
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $link_type = isset($_POST['link_type']) ? sanitize_key(wp_unslash($_POST['link_type'])) : 'all';

        $result = TABAIX_SEO_Internal_Links::ai_suggest_links($post_id, $link_type);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['suggestions' => $result]);
    }

    private function handle_insert_link()
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $anchor = isset($_POST['anchor']) ? sanitize_text_field(wp_unslash($_POST['anchor'])) : '';
        $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';

        if (!$post_id || empty($anchor) || empty($target_url))
            wp_send_json_error(['message' => 'Missing required fields.']);

        $options = [
            'nofollow' => !empty($_POST['nofollow']),
            'new_tab' => !empty($_POST['new_tab']),
            'title' => isset($_POST['link_title']) ? sanitize_text_field(wp_unslash($_POST['link_title'])) : '',
        ];

        $result = TABAIX_SEO_Internal_Links::insert_link($post_id, $anchor, $target_url, $options);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link inserted successfully!']);
    }

    private function handle_save_autolink_rules()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $rules = isset($_POST['rules']) ? (array)$_POST['rules'] : [];
        $enabled = isset($_POST['autolink_enabled']) ? (int)$_POST['autolink_enabled'] : 0;

        $clean_rules = [];
        foreach ($rules as $rule) {
            $keyword = isset($rule['keyword']) ? sanitize_text_field(wp_unslash($rule['keyword'])) : '';
            $url = isset($rule['url']) ? esc_url_raw(wp_unslash($rule['url'])) : '';
            $max = isset($rule['max_links']) ? (int)$rule['max_links'] : 1;
            if (!empty($keyword) && !empty($url)) {
                $clean_rules[] = [
                    'keyword' => $keyword,
                    'url' => $url,
                    'max_links' => max(1, $max),
                    'type' => (isset($rule['type']) && in_array($rule['type'], ['internal', 'external'])) ? $rule['type'] : 'internal',
                    'nofollow' => !empty($rule['nofollow']),
                    'new_tab' => !empty($rule['new_tab']),
                ];
            }
        }

        TABAIX_SEO_Internal_Links::save_autolink_rules($clean_rules);
        TABAIX_SEO_Settings::update('autolink_enabled', $enabled);

        wp_send_json_success(['message' => 'Auto-link rules saved!', 'count' => count($clean_rules)]);
    }

    private function handle_extract_keywords()
    {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $result = TABAIX_SEO_Internal_Links::extract_keywords($post_id);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['keywords' => $result]);
    }

    private function handle_check_broken_links()
    {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if (!$post_id)
            wp_send_json_error(['message' => 'Post ID required.']);

        $result = TABAIX_SEO_Internal_Links::check_broken_links($post_id);
        wp_send_json_success($result);
    }

    private function handle_fix_link()
    {
        if (!current_user_can('edit_posts'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $old_url = isset($_POST['old_url']) ? esc_url_raw(wp_unslash($_POST['old_url'])) : '';
        $new_url = isset($_POST['new_url']) ? esc_url_raw(wp_unslash($_POST['new_url'])) : '';
        $action = isset($_POST['fix_action']) ? sanitize_key(wp_unslash($_POST['fix_action'])) : 'replace';

        if (!$post_id || empty($old_url))
            wp_send_json_error(['message' => 'Post ID and URL are required.']);

        $result = TABAIX_SEO_Internal_Links::fix_link($post_id, $old_url, $new_url, $action);
        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link fixed successfully!']);
    }

    private function handle_save_manual_link()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $data = [
            'keyword' => isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '',
            'url' => isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '',
            'type' => isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'internal',
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'nofollow' => !empty($_POST['nofollow']),
            'new_tab' => !empty($_POST['new_tab']),
            'max_links' => isset($_POST['max_links']) ? (int)$_POST['max_links'] : 1,
        ];

        $link_id = isset($_POST['link_id']) ? sanitize_text_field(wp_unslash($_POST['link_id'])) : '';

        if ($link_id) {
            $result = TABAIX_SEO_Internal_Links::update_manual_link($link_id, $data);
        } else {
            $result = TABAIX_SEO_Internal_Links::save_manual_link($data);
        }

        if (is_wp_error($result))
            wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success(['message' => 'Link rule saved!', 'link' => $result]);
    }

    private function handle_delete_manual_link()
    {
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Unauthorized'], 403);

        $link_id = isset($_POST['link_id']) ? sanitize_text_field(wp_unslash($_POST['link_id'])) : '';
        if (empty($link_id))
            wp_send_json_error(['message' => 'Link ID required.']);

        TABAIX_SEO_Internal_Links::delete_manual_link($link_id);
        wp_send_json_success(['message' => 'Link rule deleted!']);
    }

    // ─── Comment Moderation ──────────────────────────────────────────────────

    private function handle_moderate_comment()
    {
        $text = isset($_POST['comment_text']) ? sanitize_textarea_field(wp_unslash($_POST['comment_text'])) : '';
        $result = TABAIX_SEO_Comment_Moderator::analyze_comment($text);
        $this->send_json_result($result);
    }

    private function handle_bulk_moderate()
    {
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $results = TABAIX_SEO_Comment_Moderator::bulk_analyze($limit);
        wp_send_json_success(['results' => $results]);
    }

    // ─── Analytics ───────────────────────────────────────────────────────────

    private function handle_analytics_report()
    {
        $data = TABAIX_SEO_Analytics::get_native_analytics();
        $result = TABAIX_SEO_Analytics::generate_report($data);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message(), 'raw_data' => $data]);
        }
        wp_send_json_success(['report' => json_decode($result, true), 'data' => $data]);
    }

    // ─── Image Alt Text ───────────────────────────────────────────────────────

    private function handle_generate_alt_text()
    {
        $attachment_id = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => 'Invalid image attachment ID.']);
        }
        $save = !empty($_POST['save']);
        if ($save) {
            $alt = TABAIX_SEO_Alt_Text::generate_and_save($attachment_id);
        } else {
            $alt = TABAIX_SEO_Alt_Text::generate_alt_text($attachment_id);
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
        $limit = isset($_POST['limit']) ? min((int)$_POST['limit'], 20) : 10;
        $results = TABAIX_SEO_Alt_Text::bulk_generate($limit);
        wp_send_json_success([
            'results' => $results,
            'count' => count($results),
        ]);
    }

    // ─── SEO Meta Save ────────────────────────────────────────────────────────

    private function handle_save_seo_meta()
    {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $seo_title = isset($_POST['seo_title']) ? sanitize_text_field(wp_unslash($_POST['seo_title'])) : '';
        $meta_desc = isset($_POST['meta_description']) ? sanitize_text_field(wp_unslash($_POST['meta_description'])) : '';
        $focus_kw = isset($_POST['focus_keyword']) ? sanitize_text_field(wp_unslash($_POST['focus_keyword'])) : '';

        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID is required.']);
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $saved = TABAIX_SEO_SEO_Meta::save_seo_meta($post_id, $seo_title, $meta_desc, $focus_kw);
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
        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'gemini';
        $result = TABAIX_SEO_API::test_connection($provider);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => 'Connection successful! API key is valid.']);
    }

    // ─── Provider Quick Switch ────────────────────────────────────────────────

    private function handle_quick_switch_provider()
    {
        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'gemini';
        if (!in_array($provider, ['gemini', 'openai'], true)) {
            wp_send_json_error(['message' => 'Invalid provider']);
        }
        TABAIX_SEO_Settings::update('provider', $provider);
        wp_send_json_success(['provider' => $provider, 'message' => "Switched to {$provider}"]);
    }

    // ─── SEO Audit ───────────────────────────────────────────────────────────

    private function handle_scan_seo_audit()
    {
        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'any';
        $results = TABAIX_SEO_SEO_Meta::scan_missing_seo($post_type);
        $stats = TABAIX_SEO_SEO_Meta::get_seo_stats();
        wp_send_json_success([
            'posts' => $results,
            'stats' => $stats,
        ]);
    }

    private function handle_get_post_audit()
    {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post ID is required.']);
        }
        $audit = TABAIX_SEO_SEO_Meta::get_post_seo_audit($post_id);
        if (is_wp_error($audit)) {
            wp_send_json_error(['message' => $audit->get_error_message()]);
        }
        wp_send_json_success($audit);
    }

    private function handle_optimize_post_seo()
    {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
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
        $seo_title = get_post_meta($post_id, '_tabaix_seo_title', true);
        $meta_desc = get_post_meta($post_id, '_tabaix_seo_description', true);
        $focus_kw = get_post_meta($post_id, '_tabaix_focus_keyword', true);

        if (empty($seo_title) || empty($meta_desc) || empty($focus_kw)) {
            $meta_result = TABAIX_SEO_Content_Generator::generate_meta($title, $content, $focus_kw);
            if (!is_wp_error($meta_result)) {
                $parsed = json_decode($meta_result, true);
                if ($parsed && json_last_error() === JSON_ERROR_NONE) {
                    $new_title = $parsed['seo_title'] ?? '';
                    $new_desc = $parsed['meta_description'] ?? '';
                    $new_kw = $parsed['focus_keyword'] ?? '';

                    if (empty($seo_title) && !empty($new_title)) {
                        update_post_meta($post_id, '_tabaix_seo_title', sanitize_text_field($new_title));
                        $results['seo_title'] = $new_title;
                    }
                    if (empty($meta_desc) && !empty($new_desc)) {
                        update_post_meta($post_id, '_tabaix_seo_description', sanitize_text_field($new_desc));
                        $results['meta_description'] = $new_desc;
                    }
                    if (empty($focus_kw) && !empty($new_kw)) {
                        update_post_meta($post_id, '_tabaix_focus_keyword', sanitize_text_field($new_kw));
                        $results['focus_keyword'] = $new_kw;
                    }
                    $results['meta_generated'] = true;
                }
            }
        }

        $missing_images = TABAIX_SEO_SEO_Meta::get_post_images_missing_alt($post_id);
        $missing_images = array_slice($missing_images, 0, 5);
        $alt_results = [];
        foreach ($missing_images as $i => $img) {
            if (!empty($img['attachment_id'])) {
                if ($i > 0) {
                    sleep(1);
                }
                $alt = TABAIX_SEO_Alt_Text::generate_and_save($img['attachment_id']);
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

        $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if (empty($user_message)) {
            wp_send_json_error(['message' => 'Message is empty.']);
        }

        $site_name  = get_bloginfo('name');
        $page_ctx   = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'admin panel';
        $admin_name = wp_get_current_user()->display_name;

        $system_prompt = "You are an expert AI assistant inside the Tabaix SEO Optimizer Pro plugin admin panel for site '{$site_name}'.\n"
            . "You are helping the site admin '{$admin_name}' who is currently on the '{$page_ctx}' page.\n"
            . "Answer questions about WordPress, SEO, content creation, AI features of this plugin, and web best-practices.\n"
            . "Be concise, professional, and friendly. Format replies in plain text or simple markdown (bold, lists are fine).\n"
            . "Do not reveal internal system details or API keys.";

        $response = TABAIX_SEO_API::generate($user_message, $system_prompt, [
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
