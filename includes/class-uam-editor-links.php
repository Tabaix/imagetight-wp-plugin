<?php
if (!defined('ABSPATH'))
    exit;

/**
 * UAM Editor Links — Internal link suggestions inside the post editor.
 *
 * Adds a meta box to the post/page editor that:
 *  - Analyzes draft content in real-time (no need to save first)
 *  - Suggests internal links with AI-generated unique anchor text
 *  - Inserts links directly into the editor content
 *  - Deduplicates and validates all suggestions
 */
class TABAIX_SEO_Editor_Links
{
    private static $instance = null;

    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_assets']);
    }

    /**
     * Register the meta box for posts and pages.
     */
    public function register_meta_box()
    {
        $post_types = ['post', 'page'];
        foreach ($post_types as $type) {
            add_meta_box(
                'uam-editor-links',
                '🔗 AI Internal Link Suggestions',
                [$this, 'render_meta_box'],
                $type,
                'side',
                'default'
            );
        }
    }

    /**
     * Enqueue editor-specific JS only on post edit screens.
     */
    public function enqueue_editor_assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        wp_enqueue_style('tabaix-seo-editor-links', TABAIX_SEO_PLUGIN_URL . 'assets/css/editor-links.css', [], TABAIX_SEO_VERSION);
        wp_enqueue_script('tabaix-seo-editor-links', TABAIX_SEO_PLUGIN_URL . 'assets/js/editor-links.js', ['jquery'], TABAIX_SEO_VERSION, true);
        wp_localize_script('tabaix-seo-editor-links', 'tabaixSeoEditorLinks', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tabaix_seo_admin_nonce'),
            'postId'  => get_the_ID(),
        ]);
    }

    /**
     * Render the meta box content.
     */
    public function render_meta_box($post)
    {
        ?>
        <div id="uam-el-container">
            <p class="uam-el-description">
                Analyze your content to find internal linking opportunities. Works with draft or published content.
            </p>

            <button type="button" class="button button-primary uam-el-btn" id="uam-el-scan">
                🧠 Suggest Links
            </button>
            <button type="button" class="button uam-el-btn uam-el-btn-secondary" id="uam-el-scan-draft"
                title="Analyze current draft content without saving">
                📝 Scan Draft
            </button>

            <div id="uam-el-status" class="uam-el-status"></div>
            <div id="uam-el-results"></div>

            <div id="uam-el-stats" class="uam-el-stats" style="display:none;">
                <div class="uam-el-stat">
                    <span class="uam-el-stat-num" id="uam-el-internal-count">0</span>
                    <span class="uam-el-stat-label">Internal Links</span>
                </div>
                <div class="uam-el-stat">
                    <span class="uam-el-stat-num" id="uam-el-external-count">0</span>
                    <span class="uam-el-stat-label">External Links</span>
                </div>
                <div class="uam-el-stat">
                    <span class="uam-el-stat-num" id="uam-el-word-count">0</span>
                    <span class="uam-el-stat-label">Words</span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Analyze draft content and return AI link suggestions.
     * Enforces unique, high-quality anchor text with deduplication.
     */
    public static function handle_analyze_draft()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (empty($content) || strlen(wp_strip_all_tags($content)) < 50) {
            wp_send_json_error(['message' => 'Content is too short for analysis. Write more content first.']);
        }

        // Count existing links & extract existing anchors
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $link_matches, PREG_SET_ORDER);
        $site_url = home_url();
        $internal_count = 0;
        $external_count = 0;
        $existing_anchors = [];
        foreach ($link_matches as $m) {
            $href = $m[1];
            $anchor_text = wp_strip_all_tags($m[2]);
            $existing_anchors[] = $anchor_text;
            if (strpos($href, $site_url) === 0 || strpos($href, '/') === 0) {
                $internal_count++;
            } else {
                $external_count++;
            }
        }

        $word_count = str_word_count(wp_strip_all_tags($content));
        $existing_list = !empty($existing_anchors)
            ? implode(', ', array_map(function ($a) {
                return '"' . $a . '"'; }, $existing_anchors))
            : 'None';

        // Get all published posts to suggest links from (excluding current)
        $exclude = $post_id ? [$post_id] : [];
        $all_posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'post__not_in' => $exclude,
            'fields' => 'ids',
        ]);

        if (empty($all_posts)) {
            wp_send_json_error(['message' => 'No other published posts found to link to.']);
        }

        // Build available posts list
        $post_list = '';
        $post_map = [];
        foreach ($all_posts as $pid) {
            $p_title = get_the_title($pid);
            $p_url = get_permalink($pid);
            $post_list .= "- ID:{$pid} | \"{$p_title}\" | {$p_url}\n";
            $post_map[$pid] = ['title' => $p_title, 'url' => $p_url];
        }

        // Trim content for API
        $content_clean = wp_strip_all_tags($content);
        $content_trimmed = mb_substr($content_clean, 0, 3000);

        $prompt = "You are a senior SEO strategist helping a WordPress author add internal links while writing. Analyze the draft below and suggest where to add internal links.\n\n" .
                  "**Post Title:** {$title}\n\n" .
                  "**Current Draft Content:**\n{$content_trimmed}\n\n" .
                  "**Available Posts to Link To:**\n{$post_list}\n\n" .
                  "**Current Link Stats:**\n" .
                  "- Internal links already in content: {$internal_count}\n" .
                  "- External links: {$external_count}\n" .
                  "- Word count: {$word_count}\n\n" .
                  "**Already Linked Anchors (DO NOT reuse these):** {$existing_list}\n\n" .
                  "**STRICT RULES — Follow every rule exactly:**\n\n" .
                  "1. **UNIQUE ANCHOR TEXT**: Every anchor_text MUST be completely different from every other suggestion. Never repeat the same phrase or a close variation. Each anchor must be a distinct phrase from a different part of the content.\n\n" .
                  "2. **EXACT MATCH REQUIRED**: The anchor_text MUST be an EXACT phrase that appears word-for-word in the Draft Content above. Copy it character-for-character — same capitalization, same spacing, no modifications whatsoever.\n\n" .
                  "3. **NATURAL LENGTH**: Vary anchor text length naturally:\n" .
                  "   - Some short (2-3 words): \"content strategy\"\n" .
                  "   - Some medium (3-5 words): \"search engine optimization tips\"\n" .
                  "   - Some long (4-7 words): \"how to improve your site speed\"\n\n" .
                  "4. **CONTEXTUAL RELEVANCE**: Only suggest links where the anchor text topic genuinely matches the target post's topic. The link must feel natural and helpful to the reader.\n\n" .
                  "5. **NO GENERIC PHRASES**: Never use \"click here\", \"read more\", \"this article\", \"learn more\", etc. Use descriptive, meaningful, topic-specific phrases.\n\n" .
                  "6. **DIFFERENT SECTIONS**: Pick anchor phrases from different paragraphs of the content, spread them out evenly.\n\n" .
                  "7. **EACH TARGET UNIQUE**: Never link to the same target post more than once.\n\n" .
                  "8. **PRIORITY RANKING**: Rate each as \"high\" (highly relevant, strong SEO value), \"medium\" (relevant, solid addition), or \"low\" (somewhat related, optional).\n\n" .
                  "9. **QUALITY OVER QUANTITY**: Suggest 3-8 links maximum. Only include suggestions that genuinely improve the article.\n\n" .
                  "**Return ONLY a valid JSON array, no other text:**\n" .
                  "[\n" .
                  "  {\n" .
                  "    \"anchor_text\": \"exact phrase copied from content\",\n" .
                  "    \"target_post_id\": 123,\n" .
                  "    \"reason\": \"one sentence explaining why this link adds value\",\n" .
                  "    \"priority\": \"high\"\n" .
                  "  }\n" .
                  "]";

        $result = TABAIX_SEO_API::generate($prompt);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Parse JSON
        $result = trim($result);
        $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
        $result = preg_replace('/\s*```$/', '', $result);

        $suggestions = json_decode($result, true);
        if (!is_array($suggestions)) {
            wp_send_json_error(['message' => 'Could not parse AI response. Please try again.']);
        }

        // ── Post-processing: enforce uniqueness & validity ──
        $seen_anchors = [];
        $seen_targets = [];
        $filtered = [];

        foreach ($suggestions as $s) {
            $anchor = trim($s['anchor_text'] ?? '');
            $tid = intval($s['target_post_id'] ?? 0);
            $anchor_lower = mb_strtolower($anchor);

            // Skip empty
            if (empty($anchor) || !$tid)
                continue;

            // Skip if anchor text doesn't exist in the actual content
            if (stripos($content_clean, $anchor) === false)
                continue;

            // Skip duplicate anchors
            if (isset($seen_anchors[$anchor_lower]))
                continue;

            // Skip duplicate targets
            if (isset($seen_targets[$tid]))
                continue;

            // Skip if anchor is already linked
            $already_linked = false;
            foreach ($existing_anchors as $ea) {
                if (mb_strtolower(trim($ea)) === $anchor_lower) {
                    $already_linked = true;
                    break;
                }
            }
            if ($already_linked)
                continue;

            // Skip generic anchors
            $generic = ['click here', 'read more', 'learn more', 'this article', 'here', 'link', 'this post'];
            if (in_array($anchor_lower, $generic))
                continue;

            $seen_anchors[$anchor_lower] = true;
            $seen_targets[$tid] = true;

            // Enrich with post data
            if (isset($post_map[$tid])) {
                $s['target_title'] = $post_map[$tid]['title'];
                $s['target_url'] = $post_map[$tid]['url'];
            }
            $s['priority'] = in_array(strtolower($s['priority'] ?? ''), ['high', 'medium', 'low']) ? strtolower($s['priority']) : 'medium';

            $filtered[] = $s;
        }

        wp_send_json_success([
            'suggestions' => $filtered,
            'stats' => [
                'internal_count' => $internal_count,
                'external_count' => $external_count,
                'word_count' => $word_count,
            ],
        ]);
    }
}
