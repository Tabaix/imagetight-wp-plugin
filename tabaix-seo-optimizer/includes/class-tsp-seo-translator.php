<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_SEO_Translator — Enterprise Auto-Translator powered by ImageTight API
 * 
 * - Generates AI translations via Vercel Cloud when saving a post.
 * - Saves translations cleanly in post_meta (no bloated database tables).
 * - Creates virtual subdirectories (e.g., /ar/post-name) for perfect SEO.
 * - Injects hreflang tags for Google Indexing.
 * - Adds a frontend language switcher.
 */
class TSP_SEO_Translator
{
    private static $instance = null;

    // Supported Languages
    private $languages = [
        'ar' => 'Arabic',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'zh' => 'Chinese (Simplified)',
        'hi' => 'Hindi',
        'pt' => 'Portuguese'
    ];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Settings & Admin
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_translation_metabox']);
        add_action('save_post', [$this, 'handle_auto_translation'], 10, 3);

        // Frontend Virtual Pages (Rewrite Rules)
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Frontend Display
        add_filter('the_title', [$this, 'filter_title'], 10, 2);
        add_filter('the_content', [$this, 'filter_content']);
        add_action('wp_head', [$this, 'inject_hreflang_tags']);
    }

    /* ───────────────────────────────────────────────
       Admin Settings
    ─────────────────────────────────────────────── */
    public function register_settings()
    {
        register_setting('tabaix-seo-optimizer', 'tsp_translator_enabled', ['sanitize_callback' => 'absint', 'default' => 0]);
    }

    public function add_translation_metabox()
    {
        if (!get_option('tsp_translator_enabled', 0)) return;
        add_meta_box('tsp_translation_box', '🌍 AI Auto-Translator (SEO)', [$this, 'render_metabox'], ['post', 'page'], 'side', 'high');
    }

    public function render_metabox($post)
    {
        wp_nonce_field('tsp_translate_nonce', 'tsp_translate_nonce_val');
        $api_key = get_option('tsp_imagetight_api_key', '');

        if (empty($api_key)) {
            echo '<p style="color:red;">Please enter your Tabaix API key in the settings to enable translations.</p>';
            return;
        }

        echo '<p><strong>Generate translations upon saving:</strong></p>';
        foreach ($this->languages as $code => $name) {
            $existing = get_post_meta($post->ID, '_tsp_translation_' . $code . '_title', true);
            $status = $existing ? '<span style="color:green;">(Translated)</span>' : '';
            echo '<label style="display:block; margin-bottom:5px;">';
            echo '<input type="checkbox" name="tsp_translate_langs[]" value="' . esc_attr($code) . '"> ';
            echo esc_html($name) . ' ' . $status;
            echo '</label>';
        }
        echo '<p style="font-size:11px; color:#666;">Check languages to instantly translate via Cloud API on save. (Uses 1 SaaS credit per language).</p>';
    }

    /* ───────────────────────────────────────────────
       Translation Trigger (Save Post)
    ─────────────────────────────────────────────── */
    public function handle_auto_translation($post_id, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['tsp_translate_nonce_val']) || !wp_verify_nonce($_POST['tsp_translate_nonce_val'], 'tsp_translate_nonce')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (empty($_POST['tsp_translate_langs'])) return;

        $api_key = get_option('tsp_imagetight_api_key', '');
        if (empty($api_key)) return;

        $langs_to_translate = $_POST['tsp_translate_langs'];

        foreach ($langs_to_translate as $lang_code) {
            // Translate Title
            $title_payload = [
                'tabaix_license_key' => $api_key,
                'text' => $post->post_title,
                'target_language' => $this->languages[$lang_code]
            ];
            $title_response = wp_remote_post('https://imagetight-api.vercel.app/api/translate', [
                'body' => json_encode($title_payload),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30
            ]);

            // Translate Content
            $content_payload = [
                'tabaix_license_key' => $api_key,
                'text' => $post->post_content,
                'target_language' => $this->languages[$lang_code]
            ];
            $content_response = wp_remote_post('https://imagetight-api.vercel.app/api/translate', [
                'body' => json_encode($content_payload),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 60
            ]);

            if (!is_wp_error($title_response) && !is_wp_error($content_response)) {
                $title_data = json_decode(wp_remote_retrieve_body($title_response), true);
                $content_data = json_decode(wp_remote_retrieve_body($content_response), true);

                if (!empty($title_data['translated_text'])) {
                    update_post_meta($post_id, '_tsp_translation_' . $lang_code . '_title', sanitize_text_field($title_data['translated_text']));
                }
                if (!empty($content_data['translated_text'])) {
                    update_post_meta($post_id, '_tsp_translation_' . $lang_code . '_content', wp_kses_post($content_data['translated_text']));
                }
            }
        }
    }

    /* ───────────────────────────────────────────────
       SEO URL Routing (e.g., site.com/ar/post-name)
    ─────────────────────────────────────────────── */
    public function add_rewrite_rules()
    {
        if (!get_option('tsp_translator_enabled', 0)) return;
        $lang_codes = implode('|', array_keys($this->languages));
        // Add rule for /lang/post-name
        add_rewrite_rule(
            '^(' . $lang_codes . ')/([^/]+)/?$',
            'index.php?name=$matches[2]&tsp_lang=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'tsp_lang';
        return $vars;
    }

    /* ───────────────────────────────────────────────
       Frontend Filters (Replacing English with Translation)
    ─────────────────────────────────────────────── */
    public function filter_title($title, $post_id = null)
    {
        if (!is_singular() || !$post_id) return $title;
        $lang = get_query_var('tsp_lang');
        if (!$lang) return $title;

        $translated_title = get_post_meta($post_id, '_tsp_translation_' . $lang . '_title', true);
        return $translated_title ? $translated_title : $title;
    }

    public function filter_content($content)
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) return $content;

        // 1. Check if we are viewing a translated URL
        $lang = get_query_var('tsp_lang');
        if ($lang) {
            $translated_content = get_post_meta(get_the_ID(), '_tsp_translation_' . $lang . '_content', true);
            if ($translated_content) {
                $content = $translated_content;
            } else {
                $content = "<p><em>This translation is pending. Showing original content.</em></p>" . $content;
            }
        }

        // 2. Add Language Switcher Dropdown to the top
        return $this->get_language_switcher() . $content;
    }

    public function inject_hreflang_tags()
    {
        if (!is_singular()) return;
        $post_id = get_the_ID();
        $original_url = get_permalink($post_id);
        
        // Original tag
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($original_url) . '" />' . "\n";
        echo '<link rel="alternate" hreflang="en" href="' . esc_url($original_url) . '" />' . "\n";

        // Tags for each available translation
        foreach ($this->languages as $code => $name) {
            if (get_post_meta($post_id, '_tsp_translation_' . $code . '_title', true)) {
                $lang_url = home_url('/' . $code . '/' . basename($original_url) . '/');
                echo '<link rel="alternate" hreflang="' . esc_attr($code) . '" href="' . esc_url($lang_url) . '" />' . "\n";
            }
        }
    }

    private function get_language_switcher()
    {
        $post_id = get_the_ID();
        $original_url = get_permalink($post_id);
        $current_lang = get_query_var('tsp_lang') ?: 'en';

        $html = '<div class="tsp-language-switcher" style="margin-bottom: 20px; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-block;">';
        $html .= '<strong style="margin-right: 10px;">🌍 Read in:</strong>';
        $html .= '<select onchange="window.location.href=this.value" style="padding: 5px; border-radius: 5px;">';
        
        $en_selected = ($current_lang === 'en') ? 'selected' : '';
        $html .= '<option value="' . esc_url($original_url) . '" ' . $en_selected . '>English (Original)</option>';

        foreach ($this->languages as $code => $name) {
            if (get_post_meta($post_id, '_tsp_translation_' . $code . '_title', true)) {
                $lang_url = home_url('/' . $code . '/' . basename(untrailingslashit($original_url)) . '/');
                $selected = ($current_lang === $code) ? 'selected' : '';
                $html .= '<option value="' . esc_url($lang_url) . '" ' . $selected . '>' . esc_html($name) . '</option>';
            }
        }

        $html .= '</select></div>';
        return $html;
    }
}

// Initialize
TSP_SEO_Translator::get_instance();
