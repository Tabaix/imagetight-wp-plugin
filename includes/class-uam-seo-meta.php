<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles Image Alt Text Generation and SEO Meta saving/injection.
 */
class TABAIX_SEO_SEO_Meta
{

    private static $instance = null;

    // Post meta keys
    const META_SEO_TITLE       = '_tabaix_seo_title';
    const META_SEO_DESCRIPTION = '_tabaix_seo_description';
    const META_FOCUS_KEYWORD   = '_tabaix_seo_focus_keyword';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect if a competing SEO plugin is active that also outputs meta tags.
     * Returns 'yoast', 'rankmath', 'seopress', 'aioseo', or false.
     */
    public static function get_active_seo_plugin()
    {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend'))       return 'yoast';
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath'))         return 'rankmath';
        if (defined('SEOPRESS_VERSION') || class_exists('SEOPRESS_PRO_INIT')) return 'seopress';
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\Plugin\AIOSEO')) return 'aioseo';
        return false;
    }

    private function __construct()
    {
        // Meta box on post editor — always show (we generate the data)
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        $seo_plugin = self::get_active_seo_plugin();

        if ($seo_plugin) {
            // ── CONFLICT DETECTED ─────────────────────────────────────
            // Another SEO plugin is active. We suppress our own <head>
            // output to avoid duplicate tags. We instead feed our data
            // INTO the active plugin via its own meta keys so ITS output
            // contains our AI-generated titles/descriptions.
            // Result: one clean set of tags in <head>. No Ahrefs warnings.
            add_action('save_post', [$this, 'sync_to_active_seo_plugin'], 20);
        } else {
            // ── NO CONFLICT — we handle the <head> ourselves ──────────
            add_action('wp_head', [$this, 'inject_seo_tags'], 1);
            add_filter('document_title_parts', [$this, 'filter_document_title']);
        }
    }

    /**
     * After saving our meta box data, copy it into the active SEO plugin's
     * own meta keys. This way Yoast/RankMath outputs OUR AI-generated content
     * without us needing to duplicate the <head> output.
     */
    public function sync_to_active_seo_plugin($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $seo_title = get_post_meta($post_id, self::META_SEO_TITLE, true);
        $meta_desc = get_post_meta($post_id, self::META_SEO_DESCRIPTION, true);
        $focus_kw  = get_post_meta($post_id, self::META_FOCUS_KEYWORD, true);

        if (empty($seo_title) && empty($meta_desc)) return; // nothing to sync

        $plugin = self::get_active_seo_plugin();

        if ($plugin === 'yoast') {
            if (!empty($seo_title)) update_post_meta($post_id, '_yoast_wpseo_title',    $seo_title);
            if (!empty($meta_desc)) update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
            if (!empty($focus_kw))  update_post_meta($post_id, '_yoast_wpseo_focuskw',  $focus_kw);
        } elseif ($plugin === 'rankmath') {
            if (!empty($seo_title)) update_post_meta($post_id, 'rank_math_title',       $seo_title);
            if (!empty($meta_desc)) update_post_meta($post_id, 'rank_math_description', $meta_desc);
            if (!empty($focus_kw))  update_post_meta($post_id, 'rank_math_focus_keyword', $focus_kw);
        } elseif ($plugin === 'seopress') {
            if (!empty($seo_title)) update_post_meta($post_id, '_seopress_titles_title',   $seo_title);
            if (!empty($meta_desc)) update_post_meta($post_id, '_seopress_titles_desc',    $meta_desc);
        } elseif ($plugin === 'aioseo') {
            if (!empty($seo_title)) update_post_meta($post_id, '_aioseo_title',       $seo_title);
            if (!empty($meta_desc)) update_post_meta($post_id, '_aioseo_description', $meta_desc);
        }
    }

    public function enqueue_scripts($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) return;
        wp_enqueue_style('tabaix-seo-meta-style', plugins_url('../assets/css/uam-seo-meta.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('tabaix-seo-meta-script', plugins_url('../assets/js/uam-seo-meta.js', __FILE__), ['jquery'], '1.0.0', true);
        global $post;
        wp_localize_script('tabaix-seo-meta-script', 'tabaixSeoMetaData', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('tabaix_seo_admin_nonce'),
            'postId'    => isset($post->ID) ? $post->ID : 0,
            'postTitle' => isset($post->ID) ? get_the_title($post->ID) : ''
        ]);
    }

    // ── Meta Box ─────────────────────────────────────────────────────────

    public function add_meta_box()
    {
        $screens = ['post', 'page', 'product'];
        foreach ($screens as $screen) {
            add_meta_box(
                'tabaix-seo-meta-box',
                '✦ Tabaix SEO — SEO Settings',
                [$this, 'render_meta_box'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('tabaix_seo_meta_nonce', 'tabaix_seo_meta_nonce');
        $seo_title = get_post_meta($post->ID, self::META_SEO_TITLE, true);
        $meta_desc = get_post_meta($post->ID, self::META_SEO_DESCRIPTION, true);
        $focus_kw = get_post_meta($post->ID, self::META_FOCUS_KEYWORD, true);

        $seo_title_len = strlen($seo_title);
        $meta_desc_len = strlen($meta_desc);
        ?>
        <div id="uam-seo-metabox-wrap">

            <div class="uam-mb-tabs">
                <div class="uam-mb-tab active" data-tab="meta">SEO Meta</div>
                <div class="uam-mb-tab" data-tab="images">AI Images</div>
                <div class="uam-mb-tab" data-tab="vision">Vision Analysis</div>
            </div>

            <!-- Tab: SEO Meta -->
            <div id="uam-tab-meta" class="uam-mb-content active">
                <div class="uam-mb-row">
                    <label class="uam-mb-label">Focus Keyword</label>
                    <input type="text" id="tabaix_seo_focus_keyword" name="tabaix_seo_focus_keyword" class="uam-mb-input" value="<?php echo esc_attr($focus_kw); ?>">
                </div>
                <div class="uam-mb-row">
                    <label class="uam-mb-label">SEO Title</label>
                    <input type="text" id="tabaix_seo_title" name="tabaix_seo_title" class="uam-mb-input" value="<?php echo esc_attr($seo_title); ?>" maxlength="70">
                </div>
                <div class="uam-mb-row">
                    <label class="uam-mb-label">Meta Description</label>
                    <textarea id="tabaix_seo_meta_description" name="tabaix_seo_meta_description" class="uam-mb-textarea" maxlength="165"><?php echo esc_textarea($meta_desc); ?></textarea>
                </div>
                <div class="uam-mb-actions">
                    <button type="button" class="uam-mb-btn uam-mb-btn-primary" id="uam-btn-gen-meta">✦ Generate with AI</button>
                    <button type="button" class="uam-mb-btn uam-mb-btn-secondary" id="uam-btn-preview-search">👁 Preview</button>
                </div>
                <div id="uam-meta-preview" class="uam-mb-preview">
                    <div class="uam-mb-preview-title" id="uam-preview-title"></div>
                    <div class="uam-mb-preview-desc" id="uam-preview-desc"></div>
                </div>
            </div>

            <!-- Tab: AI Images -->
            <div id="uam-tab-images" class="uam-mb-content">
                <div class="uam-mb-row">
                    <label class="uam-mb-label">Image Prompt</label>
                    <textarea id="uam-img-prompt" class="uam-mb-textarea" placeholder="Describe the image..."></textarea>
                </div>
                <div class="uam-mb-row uam-mb-row-flex">
                    <div class="flex-1">
                        <label class="uam-mb-label">Style</label>
                        <select id="uam-img-style" class="uam-mb-select">
                            <option value="photorealistic">Photorealistic</option>
                            <option value="digital_art">Digital Art</option>
                            <option value="oil_painting">Oil Painting</option>
                        </select>
                    </div>
                </div>
                <div class="uam-mb-actions">
                    <button type="button" class="uam-mb-btn uam-mb-btn-primary" id="uam-btn-gen-img">🎨 Generate Image</button>
                </div>
                <div id="uam-img-preview-area" class="margin-top-15"></div>
                <div id="uam-img-actions" class="display-none margin-top-10 gap-8">
                    <button type="button" class="uam-mb-btn uam-mb-btn-primary" id="uam-btn-set-feat">⭐ Set as Featured</button>
                </div>
            </div>

            <!-- Tab: Vision Analysis -->
            <div id="uam-tab-vision" class="uam-mb-content">
                <p class="vision-desc">Analyze existing images to generate titles and alt text.</p>
                <div class="uam-mb-row">
                    <label class="uam-mb-label">Select Image</label>
                    <button type="button" class="uam-mb-btn uam-mb-btn-secondary" id="uam-btn-select-vision">🖼 Select from Library</button>
                    <div id="uam-vision-img-preview" class="margin-top-10"></div>
                    <input type="hidden" id="uam-vision-attach-id">
                </div>
                <div class="uam-mb-actions">
                    <button type="button" class="uam-mb-btn uam-mb-btn-primary" id="uam-btn-analyze-vision">🔍 Analyze Image</button>
                </div>
                <div id="uam-vision-results" class="margin-top-15"></div>
            </div>

            <div id="uam-mb-loader" class="uam-mb-loader">
                <div class="uam-mb-spinner"></div>
                <span>Processing...</span>
            </div>
        </div>
        <?php
    }

    public function save_meta_box($post_id)
    {
        if (!isset($_POST['tabaix_seo_meta_nonce']))
            return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tabaix_seo_meta_nonce'])), 'tabaix_seo_meta_nonce'))
            return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
        if (!current_user_can('edit_post', $post_id))
            return;

        $fields = [
            'tabaix_seo_title'            => self::META_SEO_TITLE,
            'tabaix_seo_meta_description' => self::META_SEO_DESCRIPTION,
            'tabaix_seo_focus_keyword'    => self::META_FOCUS_KEYWORD,
        ];

        foreach ($fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $value = sanitize_text_field(wp_unslash($_POST[$post_key]));
                if (empty($value)) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }
    }

    // ── Frontend Injection ────────────────────────────────────────────────

    public function filter_document_title($title_parts)
    {
        if (!is_singular())
            return $title_parts;
        $post_id = get_queried_object_id();
        $seo_title = get_post_meta($post_id, self::META_SEO_TITLE, true);
        if (!empty($seo_title)) {
            $title_parts['title'] = $seo_title;
        }
        return $title_parts;
    }

    public function inject_seo_tags()
    {
        if (!is_singular())
            return;

        $post_id = get_queried_object_id();
        $seo_title = get_post_meta($post_id, self::META_SEO_TITLE, true);
        $meta_desc = get_post_meta($post_id, self::META_SEO_DESCRIPTION, true);
        $focus_kw = get_post_meta($post_id, self::META_FOCUS_KEYWORD, true);

        if (empty($meta_desc)) {
            $post = get_post($post_id);
            $meta_desc = $post ? wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 30) : '';
        }

        if (!empty($meta_desc)) {
            printf('<meta name="description" content="%s">' . "\n", esc_attr($meta_desc));
        }

        if (!empty($focus_kw)) {
            printf('<meta name="keywords" content="%s">' . "\n", esc_attr($focus_kw));
        }

        // Open Graph
        $og_title = $seo_title ?: get_the_title($post_id);
        printf('<meta property="og:title" content="%s">' . "\n", esc_attr($og_title));
        if (!empty($meta_desc)) {
            printf('<meta property="og:description" content="%s">' . "\n", esc_attr($meta_desc));
        }
        printf('<meta property="og:url" content="%s">' . "\n", esc_url(get_permalink($post_id)));
        printf('<meta property="og:type" content="article">' . "\n");

        if (has_post_thumbnail($post_id)) {
            $thumb = get_the_post_thumbnail_url($post_id, 'large');
            printf('<meta property="og:image" content="%s">' . "\n", esc_url($thumb));
        }

        // Twitter Card
        printf('<meta name="twitter:card" content="summary_large_image">' . "\n");
        printf('<meta name="twitter:title" content="%s">' . "\n", esc_attr($og_title));
        if (!empty($meta_desc)) {
            printf('<meta name="twitter:description" content="%s">' . "\n", esc_attr($meta_desc));
        }
    }

    // ── Getters for AJAX ──────────────────────────────────────────────────

    public static function get_seo_title($post_id)
    {
        return get_post_meta($post_id, self::META_SEO_TITLE, true);
    }

    public static function get_meta_description($post_id)
    {
        return get_post_meta($post_id, self::META_SEO_DESCRIPTION, true);
    }

    public static function save_seo_meta($post_id, $seo_title, $meta_desc, $focus_kw = '')
    {
        if (!current_user_can('edit_post', $post_id))
            return false;
        update_post_meta($post_id, self::META_SEO_TITLE, sanitize_text_field($seo_title));
        update_post_meta($post_id, self::META_SEO_DESCRIPTION, sanitize_text_field($meta_desc));
        if ($focus_kw) {
            update_post_meta($post_id, self::META_FOCUS_KEYWORD, sanitize_text_field($focus_kw));
        }
        return true;
    }

    // ── SEO Audit: Scan posts/pages for missing SEO data ─────────────────

    /**
     * Scan all posts and pages for missing SEO meta data.
     * Returns an array of post data with their SEO status.
     */
    public static function scan_missing_seo($post_type = 'any', $limit = 50)
    {
        $args = [
            'post_type' => ($post_type === 'any') ? ['post', 'page'] : [$post_type],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $posts = get_posts($args);
        $results = [];

        foreach ($posts as $p) {
            $seo_title = get_post_meta($p->ID, self::META_SEO_TITLE, true);
            $meta_desc = get_post_meta($p->ID, self::META_SEO_DESCRIPTION, true);
            $focus_kw = get_post_meta($p->ID, self::META_FOCUS_KEYWORD, true);
            $has_thumb = has_post_thumbnail($p->ID);

            // Count images missing alt text in this post's content
            $missing_alt_count = count(self::get_post_images_missing_alt($p->ID));

            $issues = [];
            if (empty($seo_title))
                $issues[] = 'seo_title';
            if (empty($meta_desc))
                $issues[] = 'meta_description';
            if (empty($focus_kw))
                $issues[] = 'focus_keyword';
            if (!$has_thumb)
                $issues[] = 'featured_image';
            if ($missing_alt_count > 0)
                $issues[] = 'image_alt_text';

            $score = 100;
            $score -= empty($seo_title) ? 25 : 0;
            $score -= empty($meta_desc) ? 25 : 0;
            $score -= empty($focus_kw) ? 15 : 0;
            $score -= !$has_thumb ? 15 : 0;
            $score -= ($missing_alt_count > 0) ? 20 : 0;
            $score = max(0, $score);

            $results[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'post_type' => $p->post_type,
                'date' => $p->post_date,
                'edit_url' => get_edit_post_link($p->ID, ''),
                'permalink' => get_permalink($p->ID),
                'seo_title' => $seo_title,
                'meta_description' => $meta_desc,
                'focus_keyword' => $focus_kw,
                'has_featured_image' => $has_thumb,
                'missing_alt_count' => $missing_alt_count,
                'issues' => $issues,
                'seo_score' => $score,
            ];
        }

        return $results;
    }

    /**
     * Get a detailed SEO audit for a single post including content analysis.
     */
    public static function get_post_seo_audit($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found.');
        }

        $seo_title = get_post_meta($post_id, self::META_SEO_TITLE, true);
        $meta_desc = get_post_meta($post_id, self::META_SEO_DESCRIPTION, true);
        $focus_kw = get_post_meta($post_id, self::META_FOCUS_KEYWORD, true);
        $has_thumb = has_post_thumbnail($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($content);
        $missing_alts = self::get_post_images_missing_alt($post_id);

        // Build checklist
        $checks = [];

        // SEO Title check
        $title_len = strlen($seo_title);
        $checks[] = [
            'label' => 'SEO Title',
            'status' => !empty($seo_title) ? ($title_len >= 30 && $title_len <= 65 ? 'pass' : 'warn') : 'fail',
            'detail' => !empty($seo_title)
                ? "Set ({$title_len} chars)" . ($title_len < 30 || $title_len > 65 ? ' — Optimal: 30–65 chars' : '')
                : 'Missing — No custom SEO title set',
            'value' => $seo_title,
        ];

        // Meta Description check
        $desc_len = strlen($meta_desc);
        $checks[] = [
            'label' => 'Meta Description',
            'status' => !empty($meta_desc) ? ($desc_len >= 120 && $desc_len <= 165 ? 'pass' : 'warn') : 'fail',
            'detail' => !empty($meta_desc)
                ? "Set ({$desc_len} chars)" . ($desc_len < 120 || $desc_len > 165 ? ' — Optimal: 120–165 chars' : '')
                : 'Missing — No meta description set',
            'value' => $meta_desc,
        ];

        // Focus Keyword check
        $kw_in_title = !empty($focus_kw) && !empty($seo_title) && stripos($seo_title, $focus_kw) !== false;
        $kw_in_desc = !empty($focus_kw) && !empty($meta_desc) && stripos($meta_desc, $focus_kw) !== false;
        $kw_in_content = !empty($focus_kw) && stripos($content, $focus_kw) !== false;
        $checks[] = [
            'label' => 'Focus Keyword',
            'status' => !empty($focus_kw) ? 'pass' : 'fail',
            'detail' => !empty($focus_kw)
                ? "\"{$focus_kw}\"" . ($kw_in_title ? ' ✓ in title' : ' ✗ not in title') . ($kw_in_desc ? ' ✓ in description' : '') . ($kw_in_content ? ' ✓ in content' : ' ✗ not in content')
                : 'Missing — No focus keyword set',
            'value' => $focus_kw,
        ];

        // Featured Image
        $checks[] = [
            'label' => 'Featured Image',
            'status' => $has_thumb ? 'pass' : 'fail',
            'detail' => $has_thumb ? 'Set' : 'Missing — No featured image',
        ];

        // Content Length
        $checks[] = [
            'label' => 'Content Length',
            'status' => $word_count >= 300 ? ($word_count >= 600 ? 'pass' : 'warn') : 'fail',
            'detail' => "{$word_count} words" . ($word_count < 300 ? ' — Recommended: 300+ words' : ($word_count < 600 ? ' — Good, but 600+ is better' : ' — Great length!')),
        ];

        // Internal Links
        $internal_links = preg_match_all('/href\s*=\s*["\']' . preg_quote(home_url(), '/') . '/i', $post->post_content, $m);
        $checks[] = [
            'label' => 'Internal Links',
            'status' => $internal_links >= 2 ? 'pass' : ($internal_links >= 1 ? 'warn' : 'fail'),
            'detail' => $internal_links . ' internal link' . ($internal_links != 1 ? 's' : '') . ' found' . ($internal_links < 2 ? ' — Add more internal links' : ''),
        ];

        // Image Alt Text
        $total_images = self::count_post_images($post_id);
        $missing_alt_ct = count($missing_alts);
        $checks[] = [
            'label' => 'Image Alt Text',
            'status' => $missing_alt_ct === 0 ? ($total_images > 0 ? 'pass' : 'warn') : 'fail',
            'detail' => $total_images === 0
                ? 'No images found in content'
                : ($missing_alt_ct === 0 ? "All {$total_images} images have alt text" : "{$missing_alt_ct} of {$total_images} images missing alt text"),
            'missing_images' => $missing_alts,
        ];

        // Headings check
        $has_h2 = preg_match('/<h2/i', $post->post_content);
        $heading_count = preg_match_all('/<h[2-6]/i', $post->post_content, $m);
        $checks[] = [
            'label' => 'Heading Structure',
            'status' => $has_h2 ? 'pass' : 'fail',
            'detail' => $heading_count . ' subheading' . ($heading_count != 1 ? 's' : '') . ' found' . (!$has_h2 ? ' — Add H2 headings for better SEO' : ''),
        ];

        // Calculate overall score
        $pass_count = 0;
        $total_count = count($checks);
        foreach ($checks as $c) {
            if ($c['status'] === 'pass')
                $pass_count++;
            elseif ($c['status'] === 'warn')
                $pass_count += 0.5;
        }
        $overall_score = round(($pass_count / $total_count) * 100);

        return [
            'post_id' => $post_id,
            'title' => $post->post_title,
            'post_type' => $post->post_type,
            'edit_url' => get_edit_post_link($post_id, ''),
            'permalink' => get_permalink($post_id),
            'checks' => $checks,
            'overall_score' => $overall_score,
            'seo_title' => $seo_title,
            'meta_description' => $meta_desc,
            'focus_keyword' => $focus_kw,
        ];
    }

    /**
     * Find images in a post's content that are missing alt text.
     * Returns array of image data (src, attachment_id if found).
     */
    public static function get_post_images_missing_alt($post_id)
    {
        $post = get_post($post_id);
        if (!$post)
            return [];

        $missing = [];
        // Match all img tags in the content
        if (preg_match_all('/<img[^>]+>/i', $post->post_content, $matches)) {
            foreach ($matches[0] as $img_tag) {
                // Check if alt attribute is missing or empty
                $has_alt = false;
                if (preg_match('/alt\s*=\s*["\']([^"\']*?)["\']/i', $img_tag, $alt_match)) {
                    $has_alt = !empty(trim($alt_match[1]));
                }

                if (!$has_alt) {
                    $src = '';
                    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
                        $src = $src_match[1];
                    }

                    // Try to find attachment ID from wp-image-{ID} class
                    $attachment_id = 0;
                    if (preg_match('/wp-image-(\d+)/i', $img_tag, $class_match)) {
                        $attachment_id = (int) $class_match[1];
                    } elseif ($src) {
                        // Try to find by URL
                        $attachment_id = attachment_url_to_postid($src);
                    }

                    $missing[] = [
                        'src' => $src,
                        'attachment_id' => $attachment_id,
                        'filename' => $src ? basename(wp_parse_url($src, PHP_URL_PATH)) : '',
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * Count total images in a post's content.
     */
    public static function count_post_images($post_id)
    {
        $post = get_post($post_id);
        if (!$post)
            return 0;
        return preg_match_all('/<img[^>]+>/i', $post->post_content, $m);
    }

    /**
     * Get overall SEO statistics.
     */
    public static function get_seo_stats()
    {
        global $wpdb;

        $total_posts = (int) $wpdb->get_var("
            SELECT COUNT(ID) FROM {$wpdb->posts}
            WHERE post_type IN ('post','page')
              AND post_status = 'publish'
        ");

        $has_seo_title = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_uam_seo_title'
            WHERE p.post_type IN ('post','page')
              AND p.post_status = 'publish'
              AND pm.meta_value != ''
        ");

        $has_meta_desc = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_uam_seo_description'
            WHERE p.post_type IN ('post','page')
              AND p.post_status = 'publish'
              AND pm.meta_value != ''
        ");

        $has_focus_kw = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_uam_focus_keyword'
            WHERE p.post_type IN ('post','page')
              AND p.post_status = 'publish'
              AND pm.meta_value != ''
        ");

        $missing_alt_count = TABAIX_SEO_Alt_Text::count_missing_alt();

        return [
            'total_posts' => $total_posts,
            'has_seo_title' => $has_seo_title,
            'missing_seo_title' => $total_posts - $has_seo_title,
            'has_meta_desc' => $has_meta_desc,
            'missing_meta_desc' => $total_posts - $has_meta_desc,
            'has_focus_kw' => $has_focus_kw,
            'missing_focus_kw' => $total_posts - $has_focus_kw,
            'missing_alt_text' => $missing_alt_count,
        ];
    }
}
