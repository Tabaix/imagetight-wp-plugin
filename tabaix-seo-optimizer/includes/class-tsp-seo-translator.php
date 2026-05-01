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

    // Supported Languages (Master List)
    private $all_languages = [
        'ar' => 'Arabic',
        'zh-CN' => 'Chinese (Simplified)',
        'nl' => 'Dutch',
        'fr' => 'French',
        'de' => 'German',
        'hi' => 'Hindi',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'es' => 'Spanish',
        'tr' => 'Turkish',
        'ur' => 'Urdu'
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
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('save_post', [$this, 'handle_auto_translation'], 10, 3);

        // Frontend Virtual Pages (Rewrite Rules)
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Frontend Display
        add_filter('the_title', [$this, 'filter_title'], 10, 2);
        add_filter('the_content', [$this, 'filter_content']);
        add_action('wp_head', [$this, 'inject_hreflang_tags']);
        add_action('template_redirect', [$this, 'auto_redirect_by_browser_language']);
    }

    /* ───────────────────────────────────────────────
       Admin Settings
    ─────────────────────────────────────────────── */
    public function register_settings()
    {
        register_setting('tsp_translator_group', 'tsp_seo_langs');
        register_setting('tsp_translator_group', 'tsp_free_langs');
    }

    public function register_admin_page()
    {
        add_submenu_page(
            'uam-dashboard',
            'Global Translations',
            '🌍 Translations',
            'manage_options',
            'tsp-global-translations',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        $seo_langs = get_option('tsp_seo_langs', []);
        $free_langs = get_option('tsp_free_langs', []);
        if (!is_array($seo_langs)) $seo_langs = [];
        if (!is_array($free_langs)) $free_langs = [];

        echo '<div class="wrap">';
        echo '<h1>Global SEO Translations</h1>';
        echo '<p>Configure how your blog posts are translated across your entire site automatically.</p>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('tsp_translator_group');

        echo '<h3>Premium SEO Languages (Cost: 1 API Credit per Post)</h3>';
        echo '<p>These languages will be automatically translated and permanently saved to your database whenever you publish a new post. This guarantees Google indexing and high organic traffic.</p>';
        
        foreach ($this->all_languages as $code => $name) {
            $checked = in_array($code, $seo_langs) ? 'checked' : '';
            echo '<label style="display:inline-block; width:200px; margin-bottom:10px;">';
            echo '<input type="checkbox" name="tsp_seo_langs[]" value="' . esc_attr($code) . '" ' . $checked . '> ' . esc_html($name);
            echo '</label>';
        }

        echo '<hr style="margin: 20px 0;">';
        echo '<h3>Free Live Translations (Cost: 0 Credits)</h3>';
        echo '<p>These languages will appear in the frontend dropdown but will be translated instantly in the users browser using Google Translate. Excellent for global accessibility, but they do NOT rank in Google.</p>';

        foreach ($this->all_languages as $code => $name) {
            // Option to skip showing it in Free if it's already selected as Premium, 
            // but we allow them to check both if they want, the frontend logic handles it cleanly.
            $checked = in_array($code, $free_langs) ? 'checked' : '';
            echo '<label style="display:inline-block; width:200px; margin-bottom:10px;">';
            echo '<input type="checkbox" name="tsp_free_langs[]" value="' . esc_attr($code) . '" ' . $checked . '> ' . esc_html($name);
            echo '</label>';
        }

        echo '<br><br>';
        submit_button('Save Global Translation Settings');
        echo '</form>';
        echo '</div>';
    }

    /* ───────────────────────────────────────────────
       Translation Trigger (Save Post - Global)
    ─────────────────────────────────────────────── */
    public function handle_auto_translation($post_id, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if ($post->post_status !== 'publish') return; // Only translate when it's fully published

        $api_key = get_option('tsp_imagetight_api_key', '');
        if (empty($api_key)) return;

        $langs_to_translate = get_option('tsp_seo_langs', []);
        if (empty($langs_to_translate) || !is_array($langs_to_translate)) return;

        foreach ($langs_to_translate as $lang_code) {
            // Prevent re-translating if it already exists
            if (get_post_meta($post_id, '_tsp_translation_' . $lang_code . '_title', true)) continue;
            // Translate Title
            $title_payload = [
                'tabaix_license_key' => $api_key,
                'text' => $post->post_title,
                'target_language' => $this->all_languages[$lang_code] ?? $lang_code
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
                'target_language' => $this->all_languages[$lang_code] ?? $lang_code
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
        $lang_codes = implode('|', array_keys($this->all_languages));
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

        // 1. Check if we are viewing a translated SEO URL
        $lang = get_query_var('tsp_lang');
        if ($lang) {
            $translated_content = get_post_meta(get_the_ID(), '_tsp_translation_' . $lang . '_content', true);
            if ($translated_content) {
                $content = $translated_content;
            } else {
                $content = "<p><em>This translation is pending. Showing original content.</em></p>" . $content;
            }
        }

        // 2. Add Language Switcher Dropdown & Google Translate Fallback
        return $this->get_language_switcher() . $content . $this->get_google_translate_script();
    }

    private function get_google_translate_script()
    {
        return '
        <div id="google_translate_element" style="display:none;"></div>
        <script type="text/javascript">
            function googleTranslateElementInit() {
                new google.translate.TranslateElement({pageLanguage: "en", autoDisplay: false}, "google_translate_element");
            }
            function triggerGoogleTranslate(langCode) {
                var selectField = document.querySelector("select.goog-te-combo");
                if (selectField) {
                    selectField.value = langCode;
                    selectField.dispatchEvent(new Event("change"));
                }
            }
        </script>
        <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
        ';
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

    /* ───────────────────────────────────────────────
       Auto-Detect Browser Language & Redirect (Amazon Style)
    ─────────────────────────────────────────────── */
    public function auto_redirect_by_browser_language()
    {
        // Only run on single posts where no language is explicitly requested yet
        if (!is_singular() || get_query_var('tsp_lang')) return;
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return;

        // Prevent redirect loops using a cookie
        if (isset($_COOKIE['tsp_lang_redirected'])) return;

        $post_id = get_the_ID();
        $browser_langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        
        foreach ($browser_langs as $lang_string) {
            $lang_code = substr($lang_string, 0, 2); // e.g. "en-US" -> "en", "ar-SA" -> "ar"
            
            // If the browser language is English, do nothing (stay on default)
            if ($lang_code === 'en') {
                setcookie('tsp_lang_redirected', '1', time() + 86400, '/');
                return;
            }

            // If the browser language matches one of our supported Premium translations
            if (array_key_exists($lang_code, $this->all_languages)) {
                // Check if this specific post has actually been translated to that language
                if (get_post_meta($post_id, '_tsp_translation_' . $lang_code . '_title', true)) {
                    
                    // Mark cookie so we don't force redirect them if they manually switch back to English
                    setcookie('tsp_lang_redirected', '1', time() + 86400, '/');
                    
                    // Redirect them automatically to the translated /ar/ version!
                    $original_url = get_permalink($post_id);
                    $lang_url = home_url('/' . $lang_code . '/' . basename(untrailingslashit($original_url)) . '/');
                    
                    wp_redirect($lang_url, 302);
                    exit;
                }
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
        
        // Custom dropdown logic
        $html .= '<select onchange="if(this.value.startsWith(\'http\')) { window.location.href=this.value; } else if(this.value !== \'\') { triggerGoogleTranslate(this.value); }" style="padding: 5px; border-radius: 5px; min-width: 150px;">';
        
        $en_selected = ($current_lang === 'en') ? 'selected' : '';
        $html .= '<option value="' . esc_url($original_url) . '" ' . $en_selected . '>🇬🇧 English (Original)</option>';

        $seo_langs = get_option('tsp_seo_langs', []);
        if (is_array($seo_langs) && !empty($seo_langs)) {
            $html .= '<optgroup label="Premium SEO Translations">';
            foreach ($this->all_languages as $code => $name) {
                if (in_array($code, $seo_langs) && get_post_meta($post_id, '_tsp_translation_' . $code . '_title', true)) {
                    $lang_url = home_url('/' . $code . '/' . basename(untrailingslashit($original_url)) . '/');
                    $selected = ($current_lang === $code) ? 'selected' : '';
                    $html .= '<option value="' . esc_url($lang_url) . '" ' . $selected . '>✨ ' . esc_html($name) . '</option>';
                }
            }
            $html .= '</optgroup>';
        }

        // Free Fallback Languages (Google Translate)
        $free_langs = get_option('tsp_free_langs', []);
        if (is_array($free_langs) && !empty($free_langs)) {
            $html .= '<optgroup label="Live Translations (Free)">';
            foreach ($free_langs as $code) {
                // Do not display in free list if it was already rendered in the Premium list
                if (in_array($code, $seo_langs) && get_post_meta($post_id, '_tsp_translation_' . $code . '_title', true)) {
                    continue; 
                }
                
                if (isset($this->all_languages[$code])) {
                    $html .= '<option value="' . esc_attr($code) . '">' . esc_html($this->all_languages[$code]) . '</option>';
                }
            }
            $html .= '</optgroup>';
        }

        $html .= '</select></div>';
        return $html;
    }
}

// Initialize
TSP_SEO_Translator::get_instance();
