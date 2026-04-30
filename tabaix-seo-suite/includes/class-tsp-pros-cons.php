<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_Pros_Cons — Pros & Cons Schema Block for Tabaix SEO Suite Pro
 *
 * Features:
 *  - [tsp_pros_cons] shortcode (no API needed)
 *  - AI generation via Gemini/OpenAI (with API key)
 *  - Outputs Review schema.org markup → Google star ratings
 *  - AJAX endpoint for admin UI generation
 *  - Beautiful frontend styling with green/red colours
 */
class TSP_Pros_Cons
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
        add_shortcode('tsp_pros_cons', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_ajax_tsp_generate_pros_cons', [$this, 'ajax_generate']);
        add_action('init', [$this, 'register_block']);
    }

    public function register_block()
    {
        register_block_type(__DIR__ . '/pros-cons-block.json');
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('tsp-pros-cons-style', plugins_url('../assets/css/tsp-pros-cons.css', __FILE__), [], '1.0.0');
    }

    /**
     * Shortcode: [tsp_pros_cons product="Product Name" rating="4.5"
     *             pros="Pro 1|Pro 2|Pro 3" cons="Con 1|Con 2"]
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'product' => 'Product',
            'rating'  => '4.0',
            'pros'    => '',
            'cons'    => '',
            'verdict' => '',
        ], $atts, 'tsp_pros_cons');

        $pros_list = array_filter(array_map('trim', explode('|', $atts['pros'])));
        $cons_list = array_filter(array_map('trim', explode('|', $atts['cons'])));
        $rating    = min(5, max(0, (float)$atts['rating']));
        $stars     = str_repeat('★', round($rating)) . str_repeat('☆', 5 - round($rating));

        $pros_html = '';
        foreach ($pros_list as $pro) {
            $pros_html .= '<li>' . esc_html($pro) . '</li>';
        }
        $cons_html = '';
        foreach ($cons_list as $con) {
            $cons_html .= '<li>' . esc_html($con) . '</li>';
        }

        // Schema.org Review markup
        $schema = [
            '@context'     => 'https://schema.org',
            '@type'        => 'Review',
            'itemReviewed' => ['@type' => 'Product', 'name' => $atts['product']],
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $rating, 'bestRating' => 5],
            'author'       => ['@type' => 'Person', 'name' => get_bloginfo('name')],
            'reviewBody'   => $atts['verdict'] ?: 'Comprehensive review with pros and cons.',
        ];

        return '<div class="tsp-pc-wrap">
            <div class="tsp-pc-header">
                ⚖️ ' . esc_html($atts['product']) . ' — Pros & Cons
                <small>Honest Review</small>
            </div>
            <div class="tsp-pc-body">
                <div class="tsp-pc-col tsp-pros">
                    <p class="tsp-pc-col-heading">✅ Pros</p>
                    <ul class="tsp-pc-list">' . $pros_html . '</ul>
                </div>
                <div class="tsp-pc-col tsp-cons">
                    <p class="tsp-pc-col-heading">❌ Cons</p>
                    <ul class="tsp-pc-list">' . $cons_html . '</ul>
                </div>
            </div>
            <div class="tsp-pc-rating">
                <span class="tsp-pc-stars">' . $stars . '</span>
                <strong>' . number_format($rating, 1) . '/5</strong>
                <span>— ' . esc_html($atts['product']) . '</span>
            </div>
            <script type="application/ld+json">' . wp_json_encode($schema) . '</script>
        </div>';
    }

    /**
     * AJAX: Generate Pros & Cons via AI (admin only).
     * POST: product_name, nonce
     */
    public function ajax_generate()
    {
        check_ajax_referer('uam_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Unauthorized']);

        $product = sanitize_text_field($_POST['product_name'] ?? '');
        if (empty($product)) wp_send_json_error(['message' => 'Product name is required.']);

        // Check if AI is available
        $has_key = !empty(UAM_Settings::get('gemini_api_key')) || !empty(UAM_Settings::get('openai_api_key'));

        if ($has_key) {
            $prompt = "Generate a balanced list of 5 pros and 4 cons for: \"{$product}\".\n"
                . "Also suggest an overall rating out of 5 and a one-sentence verdict.\n"
                . "Return ONLY valid JSON in this exact format, no other text:\n"
                . '{"pros":["pro1","pro2","pro3","pro4","pro5"],'
                . '"cons":["con1","con2","con3","con4"],'
                . '"rating":4.2,'
                . '"verdict":"One sentence overall verdict."}';

            $result = UAM_API::generate($prompt, '', ['max_tokens' => 400, 'temperature' => 0.5]);

            if (!is_wp_error($result)) {
                $result = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($result))));
                $data   = json_decode($result, true);
                if (is_array($data) && isset($data['pros'])) {
                    wp_send_json_success([
                        'pros'    => $data['pros'],
                        'cons'    => $data['cons'],
                        'rating'  => $data['rating'] ?? 4.0,
                        'verdict' => $data['verdict'] ?? '',
                        'source'  => 'ai',
                        'shortcode' => $this->build_shortcode($product, $data),
                    ]);
                }
            }
        }

        // Fallback: template-based without AI
        $fallback = [
            'pros'    => ["Well-designed and user-friendly", "Good value for money", "Reliable performance", "Easy setup process", "Positive user reviews"],
            'cons'    => ["Limited advanced features", "Could improve customer support", "Occasional updates needed", "Pricing may be high for some"],
            'rating'  => 4.0,
            'verdict' => "A solid choice for most users, with room for improvement.",
        ];

        wp_send_json_success([
            'pros'      => $fallback['pros'],
            'cons'      => $fallback['cons'],
            'rating'    => $fallback['rating'],
            'verdict'   => $fallback['verdict'],
            'source'    => 'template',
            'shortcode' => $this->build_shortcode($product, $fallback),
        ]);
    }

    private function build_shortcode($product, $data)
    {
        $pros = implode('|', array_map('wp_strip_all_tags', (array)($data['pros'] ?? [])));
        $cons = implode('|', array_map('wp_strip_all_tags', (array)($data['cons'] ?? [])));
        return sprintf(
            '[tsp_pros_cons product="%s" rating="%.1f" pros="%s" cons="%s" verdict="%s"]',
            esc_attr($product),
            (float)($data['rating'] ?? 4.0),
            esc_attr($pros),
            esc_attr($cons),
            esc_attr($data['verdict'] ?? '')
        );
    }
}
