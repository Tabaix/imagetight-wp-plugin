<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Settings
{

    private static $instance = null;
    const OPTION_KEY = 'tabaix_seo_settings';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public static function set_defaults()
    {
        $defaults = [
            'provider' => 'gemini',
            'gemini_api_key' => '',
            'openai_api_key' => '',
            'gemini_model' => TABAIX_SEO_API::DEFAULT_GEMINI_MODEL,
            'openai_model' => TABAIX_SEO_API::DEFAULT_OPENAI_MODEL,

            'moderation_auto' => 0,
            'image_model' => 'gemini',
            'analytics_enabled' => 1,
            'recommend_enabled' => 1,
            'alt_text_auto' => 0,
            'chatbot_enabled' => 0,
            'chatbot_greeting' => 'Hello! I am your AI assistant. How can I help you today?',
            'chatbot_position' => 'bottom-right',
        ];

        if (!get_option(self::OPTION_KEY)) {
            update_option(self::OPTION_KEY, $defaults);
        }
    }

    public function register_settings()
    {
        register_setting('tabaix_seo_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function sanitize($input)
    {
        $all_gemini = array_keys(TABAIX_SEO_API::gemini_models());
        $all_openai = array_keys(TABAIX_SEO_API::openai_models());

        $clean = [];
        $clean['provider'] = sanitize_key($input['provider'] ?? 'gemini');
        $clean['gemini_api_key'] = sanitize_text_field($input['gemini_api_key'] ?? '');
        $clean['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');

        $gm = sanitize_text_field($input['gemini_model'] ?? TABAIX_SEO_API::DEFAULT_GEMINI_MODEL);
        $clean['gemini_model'] = in_array($gm, $all_gemini, true) ? $gm : TABAIX_SEO_API::DEFAULT_GEMINI_MODEL;

        $om = sanitize_text_field($input['openai_model'] ?? TABAIX_SEO_API::DEFAULT_OPENAI_MODEL);
        $clean['openai_model'] = in_array($om, $all_openai, true) ? $om : TABAIX_SEO_API::DEFAULT_OPENAI_MODEL;

        $clean['moderation_auto'] = !empty($input['moderation_auto']) ? 1 : 0;
        $clean['image_model'] = sanitize_text_field($input['image_model'] ?? 'gemini');
        $clean['analytics_enabled'] = !empty($input['analytics_enabled']) ? 1 : 0;
        $clean['recommend_enabled'] = !empty($input['recommend_enabled']) ? 1 : 0;
        $clean['alt_text_auto'] = !empty($input['alt_text_auto']) ? 1 : 0;
        $clean['chatbot_enabled'] = !empty($input['chatbot_enabled']) ? 1 : 0;
        $clean['chatbot_greeting'] = sanitize_textarea_field($input['chatbot_greeting'] ?? 'Hello! I am your AI assistant. How can I help you today?');
        $clean['chatbot_position'] = in_array($input['chatbot_position'] ?? 'bottom-right', ['bottom-right', 'bottom-left'], true)
            ? $input['chatbot_position'] : 'bottom-right';
        return $clean;
    }

    public static function get($key = null, $default = '')
    {
        $opts = get_option(self::OPTION_KEY, []);
        if ($key === null)
            return $opts;

        $value = $opts[$key] ?? $default;

        if ($key === 'gemini_model' && !empty($value)) {
            $valid = array_keys(TABAIX_SEO_API::gemini_models());
            if (!in_array($value, $valid, true)) {
                $value = TABAIX_SEO_API::DEFAULT_GEMINI_MODEL;
                self::update('gemini_model', $value);
            }
        } elseif ($key === 'openai_model' && !empty($value)) {
            $valid = array_keys(TABAIX_SEO_API::openai_models());
            if (!in_array($value, $valid, true)) {
                $value = TABAIX_SEO_API::DEFAULT_OPENAI_MODEL;
                self::update('openai_model', $value);
            }
        }

        return $value;
    }

    public static function update($key, $value)
    {
        $opts = get_option(self::OPTION_KEY, []);
        $opts[$key] = $value;
        update_option(self::OPTION_KEY, $opts);
    }
}
