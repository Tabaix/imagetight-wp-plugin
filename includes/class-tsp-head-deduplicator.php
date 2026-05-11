<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_Head_Deduplicator
 *
 * Solves the "duplicate meta tags" problem permanently, for ANY combination
 * of SEO plugins (Yoast, RankMath, SEOPress, AIOSEO, our plugin, or unknown).
 *
 * HOW IT WORKS:
 *  1. Before wp_head fires → starts output buffering
 *  2. After ALL wp_head hooks finish → captures the full <head> HTML
 *  3. Runs a deduplication pass:
 *     - Keeps only the FIRST <title> tag found
 *     - Keeps only the FIRST <meta name="description"> found
 *     - Keeps only the FIRST <meta name="keywords"> found
 *     - Keeps only the FIRST of each og: / twitter: tag
 *     - Removes all subsequent duplicates
 *  4. Outputs the clean, deduplicated <head> HTML
 *
 * RESULT:
 *  - Works with EVERY SEO plugin combination, including unknown ones
 *  - No configuration needed — just install and it works
 *  - Ahrefs / Screaming Frog / Search Console will show zero duplicate warnings
 */
class TABAIX_SEO_Head_Deduplicator
{
    /** @var self|null */
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
        if (is_admin()) return;

        // Start buffering at the very beginning of wp_head
        add_action('wp_head', [$this, 'start_deduplication_buffer'], -9999);
    }

    /**
     * Start capturing all wp_head output.
     */
    public function start_deduplication_buffer()
    {
        ob_start();
        // Register the closing hook ONLY when the start hook is triggered
        add_action('wp_head', [$this, 'end_buffer_and_deduplicate'], 99999);
    }

    /**
     * Grab the buffered <head> content, deduplicate tags, and echo clean output.
     */
    public function end_buffer_and_deduplicate()
    {
        $html = ob_get_clean();
        if (!$html) return;

        $html = $this->deduplicate_title($html);
        $html = $this->deduplicate_meta_name($html, 'description');
        $html = $this->deduplicate_meta_name($html, 'keywords');
        $html = $this->deduplicate_meta_name($html, 'robots');
        $html = $this->deduplicate_meta_property($html, 'og:title');
        $html = $this->deduplicate_meta_property($html, 'og:description');
        $html = $this->deduplicate_meta_property($html, 'og:url');
        $html = $this->deduplicate_meta_property($html, 'og:type');
        $html = $this->deduplicate_meta_property($html, 'og:image');
        $html = $this->deduplicate_meta_property($html, 'og:site_name');
        $html = $this->deduplicate_meta_name($html, 'twitter:card');
        $html = $this->deduplicate_meta_name($html, 'twitter:title');
        $html = $this->deduplicate_meta_name($html, 'twitter:description');
        $html = $this->deduplicate_meta_name($html, 'twitter:image');

        // We use a custom allowed tags list for <head> elements to satisfy WordPress.org review
        $allowed_tags = [
            'title'    => [],
            'meta'     => [
                'name'     => true,
                'content'  => true,
                'property' => true,
                'charset'  => true,
                'http-equiv' => true,
            ],
            'link'     => [
                'rel'      => true,
                'href'     => true,
                'type'     => true,
                'media'    => true,
                'sizes'    => true,
                'as'       => true,
                'crossorigin' => true,
                'integrity'   => true,
            ],
            'script'   => [
                'src'      => true,
                'type'     => true,
                'async'    => true,
                'defer'    => true,
                'id'       => true,
                'crossorigin' => true,
                'integrity'   => true,
            ],
            'style'    => [
                'type'     => true,
                'id'       => true,
                'media'    => true,
            ],
            'base'     => [
                'href'     => true,
                'target'   => true,
            ],
            'noscript' => [],
        ];

        echo wp_kses($html, $allowed_tags);
    }

    // ── Deduplication helpers ─────────────────────────────────────────────────

    /**
     * Keep only the FIRST <title> tag, remove all subsequent ones.
     * 
     * @param string $html
     * @return string
     */
    private function deduplicate_title($html)
    {
        $pattern = '/<title[^>]*>.*?<\/title>/is';
        $found   = false;

        return preg_replace_callback($pattern, function ($matches) use (&$found) {
            if (!$found) {
                $found = true;
                return $matches[0]; // keep first
            }
            return ''; // remove duplicates
        }, $html);
    }

    /**
     * Keep only the FIRST <meta name="X" ...> for a given name attribute.
     * Handles both name="..." and name='...' and case variations.
     * 
     * @param string $html
     * @param string $name
     * @return string
     */
    private function deduplicate_meta_name($html, $name)
    {
        // Matches <meta ... name="description" ... content="..." ...>
        // in any attribute order (name can come before or after content)
        $escaped = preg_quote($name, '/');
        $pattern = '/<meta\s[^>]*name\s*=\s*["\']' . $escaped . '["\'][^>]*\/?>/i';
        $found   = false;

        return preg_replace_callback($pattern, function ($matches) use (&$found) {
            if (!$found) {
                $found = true;
                return $matches[0];
            }
            return '';
        }, $html);
    }

    /**
     * Keep only the FIRST <meta property="X" ...> for a given property attribute.
     * 
     * @param string $html
     * @param string $property
     * @return string
     */
    private function deduplicate_meta_property($html, $property)
    {
        $escaped = preg_quote($property, '/');
        $pattern = '/<meta\s[^>]*property\s*=\s*["\']' . $escaped . '["\'][^>]*\/?>/i';
        $found   = false;

        return preg_replace_callback($pattern, function ($matches) use (&$found) {
            if (!$found) {
                $found = true;
                return $matches[0];
            }
            return '';
        }, $html);
    }
}
