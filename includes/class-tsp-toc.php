<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_TOC — Table of Contents for Tabaix All-in-One SEO & Optimizer
 *
 * Features:
 *  - Auto-inserts TOC into all single posts (configurable)
 *  - [tsp_toc] shortcode for manual placement
 *  - Reads H2, H3, H4 headings
 *  - Smooth scroll + Back To Top button
 *  - Collapsible on mobile
 *  - Schema.org ItemList markup for SEO
 *  - No API key required — works free always
 */
class TABAIX_SEO_TOC
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
        // Auto-insert TOC into content
        add_filter('the_content', [$this, 'auto_insert_toc'], 20);

        // Add anchor IDs to headings
        add_filter('the_content', [$this, 'inject_heading_ids'], 15);

        // Shortcode for manual placement
        add_shortcode('tabaix_seo_toc', [$this, 'render_shortcode']);

        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Register Gutenberg block
        add_action('init', [$this, 'register_block']);
    }

    public function register_block()
    {
        register_block_type(__DIR__ . '/toc-block.json', [
            'render_callback' => [$this, 'render_block']
        ]);
    }

    public function enqueue_assets()
    {
        if (!is_singular()) return;
        wp_enqueue_style('tabaix-seo-toc-style', plugins_url('../assets/css/tsp-toc.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('tabaix-seo-toc-script', plugins_url('../assets/js/tsp-toc.js', __FILE__), [], '1.0.0', true);
    }

    /**
     * Auto-insert TOC after first paragraph in single posts.
     */
    public function auto_insert_toc($content)
    {
        if (!TABAIX_SEO_Settings::get('toc_enabled', 1)) return $content;
        if (!is_singular('post')) return $content;

        // Only if post has at least 2 H2s
        if (substr_count(strtolower($content), '<h2') < 2) return $content;

        $toc = $this->build_toc($content);
        if (!$toc) return $content;

        // Back to top button (once per page)
        $btn = '<button id="tsp-back-top" class="tsp-back-top" aria-label="Back to top" title="Back to top">↑</button>';

        // Insert after first paragraph
        $content = preg_replace('/<\/p>/', '</p>' . $toc, $content, 1);
        return $content . $btn;
    }

    /**
     * [tsp_toc] shortcode.
     */
    public function render_shortcode($atts)
    {
        global $post;
        if (!$post) return '';
        return $this->build_toc($post->post_content);
    }

    /**
     * Build TOC HTML from content string.
     */
    public function build_toc($content)
    {
        preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h[2-4]>/i', $content, $matches, PREG_SET_ORDER);
        if (count($matches) < 2) return '';

        $items = '';
        $schema_items = [];
        $i = 0;

        foreach ($matches as $m) {
            $level = $m[1];
            $text  = wp_strip_all_tags($m[2]);
            $id    = sanitize_title($text);
            if (!$id) $id = 'heading-' . ++$i;

            $items .= sprintf(
                '<li class="tsp-toc-h%s"><a href="#%s">%s</a></li>',
                esc_attr($level),
                esc_attr($id),
                esc_html($text)
            );

            $schema_items[] = [
                '@type'    => 'ListItem',
                'position' => ++$i,
                'name'     => $text,
                'url'      => get_permalink() . '#' . $id,
            ];
        }

        $schema = json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $schema_items,
        ]);

        return '<nav class="tsp-toc-wrap" aria-label="Table of Contents">
            <div class="tsp-toc-title">
                <span>📋</span> Table of Contents
                <button class="tsp-toc-toggle" aria-label="Toggle">▼</button>
            </div>
            <ul class="tsp-toc-list">' . $items . '</ul>
            <script type="application/ld+json">' . $schema . '</script>
        </nav>';
    }

    /**
     * Add id= attributes to H2–H4 tags for anchor links.
     */
    public function inject_heading_ids($content)
    {
        if (!is_singular()) return $content;
        return preg_replace_callback('/<h([2-4])([^>]*)>(.*?)<\/h[2-4]>/i', function($m) {
            $level = $m[1];
            $attrs = $m[2];
            $text  = $m[3];
            if (strpos($attrs, 'id=') !== false) return $m[0]; // already has ID
            $id = sanitize_title(wp_strip_all_tags($text));
            if (!$id) $id = 'heading-' . wp_rand(1000,9999);
            return "<h{$level} id=\"{$id}\"{$attrs}>{$text}</h{$level}>";
        }, $content);
    }

    /**
     * Render the TOC Block
     */
    public function render_block($attributes, $content)
    {
        $post = get_post();
        if (!$post) {
            return '';
        }

        $headings = $this->extract_headings($post->post_content, $attributes);

        if (empty($headings)) {
            return '';
        }

        ob_start();
        ?>
        <nav class="tabai-toc-container <?php echo esc_attr('layout-' . ($attributes['layout'] ?? 'boxed')); ?>"
            aria-label="<?php esc_attr_e('Table of Contents', 'tabaix-seo-optimizer-pro'); ?>">
            <?php if (!empty($attributes['showTitle'])): ?>
                <div class="tabai-toc-header">
                    <h2 class="tabai-toc-title"><?php echo esc_html($attributes['title'] ?? 'Table of Contents'); ?></h2>
                    <button class="tabai-toc-toggle" aria-expanded="true"
                        aria-label="<?php esc_attr_e('Toggle Table of Contents', 'tabaix-seo-optimizer-pro'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
            <?php endif; ?>

            <ul class="tabai-toc-list <?php echo esc_attr('style-' . ($attributes['listStyle'] ?? 'bullets')); ?>">
                <?php foreach ($headings as $heading): ?>
                    <li class="tabai-toc-item tabai-toc-level-<?php echo esc_attr($heading['level']); ?>">
                        <a href="#<?php echo esc_attr($heading['id']); ?>" class="tabai-toc-link">
                            <?php echo wp_kses_post($heading['text']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if (!empty($attributes['backToTop'])): ?>
            <button class="tabai-toc-back-to-top" aria-label="<?php esc_attr_e('Back to Top', 'tabaix-seo-optimizer'); ?>"
                style="display:none;">
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
        <?php endif; ?>

        <?php if (!empty($attributes['floatingMobileBtn'])): ?>
            <button class="tabai-toc-mobile-trigger" aria-label="<?php esc_attr_e('Table of Contents', 'tabaix-seo-optimizer'); ?>">
                <span class="dashicons dashicons-list-view"></span>
            </button>
        <?php endif; ?>

        <?php if (!empty($attributes['enableSchema'])):
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => array_map(function ($heading, $index) {
                    return [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $heading['text'],
                        'url' => get_permalink() . '#' . $heading['id']
                    ];
                }, $headings, array_keys($headings))
            ];
            ?>
            <script type="application/ld+json">
                <?php echo wp_json_encode($schema); ?>
            </script>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    /**
     * Extract headings helper for block
     */
    private function extract_headings($content, $attributes)
    {
        $matches = [];
        preg_match_all('/<(h[2-4])(.*?)>(.*?)<\/\1>/i', $content, $matches, PREG_SET_ORDER);

        $headings = [];
        $exclude_keywords = isset($attributes['excludeKeywords']) ? array_map('trim', explode(',', $attributes['excludeKeywords'])) : [];
        $excluded_specific = isset($attributes['excludedHeadings']) ? (array) $attributes['excludedHeadings'] : [];
        $skip_first = !empty($attributes['skipFirstHeading']);

        foreach ($matches as $index => $match) {
            if ($skip_first && $index === 0) {
                continue;
            }

            $tag = strtolower($match[1]);
            $attrs = $match[2];
            $raw_text = $match[3];
            $clean_text = strip_tags($raw_text);

            if (empty($attributes['show' . strtoupper($tag)])) {
                continue;
            }

            foreach ($exclude_keywords as $keyword) {
                if (!empty($keyword) && stripos($clean_text, $keyword) !== false) {
                    continue 2;
                }
            }

            if (in_array($clean_text, $excluded_specific)) {
                continue;
            }

            $id = '';
            if (preg_match('/id=["\']([^"\']+)["\']/', $attrs, $id_match)) {
                $id = $id_match[1];
            } else {
                $id = sanitize_title($clean_text);
                if (!$id)
                    $id = 'heading-' . wp_rand(1000, 9999);
            }

            $headings[] = [
                'level' => substr($tag, 1),
                'text' => $clean_text,
                'id' => $id
            ];
        }

        return $headings;
    }
}
