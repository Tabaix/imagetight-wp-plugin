<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Handles the Frontend Chatbot Widget and AI responses.
 */
class TABAIX_SEO_Chatbot
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
        // Hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_widget']);

        // AJAX Dispatch
        add_action('wp_ajax_tabaix_seo_chatbot_message', [$this, 'handle_chatbot_message']);
        add_action('wp_ajax_nopriv_tabaix_seo_chatbot_message', [$this, 'handle_chatbot_message']);

        // Shortcode
        add_shortcode('tabaix_seo_chatbot', [$this, 'render_shortcode']);
    }

    /**
     * Enqueue chatbot styles and scripts on the frontend.
     */
    public function enqueue_assets()
    {
        if (!TABAIX_SEO_Settings::get('chatbot_enabled')) {
            return;
        }

        wp_enqueue_style('tabaix-seo-chatbot', TABAIX_SEO_PLUGIN_URL . 'assets/css/chatbot.css', [], TABAIX_SEO_VERSION);
        wp_enqueue_script('tabaix-seo-chatbot', TABAIX_SEO_PLUGIN_URL . 'assets/js/chatbot.js', [], TABAIX_SEO_VERSION, true);

        // Localize data for JS
        wp_localize_script('tabaix-seo-chatbot', 'tabaixSeoChatbot', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tabaix_seo_chatbot_nonce'),
            'greeting'=> TABAIX_SEO_Settings::get('chatbot_greeting', 'Hello! How can I help you today?'),
        ]);
    }

    /**
     * Render the chatbot widget HTML in the footer.
     */
    public function render_widget()
    {
        if (!TABAIX_SEO_Settings::get('chatbot_enabled')) {
            return;
        }

        $position = TABAIX_SEO_Settings::get('chatbot_position', 'bottom-right');
        $greeting  = TABAIX_SEO_Settings::get('chatbot_greeting', 'Hello! I am your AI assistant. How can I help you today?');
        ?>
        <div id="tabaix-seo-chatbot-widget" class="tabaix-seo-chatbot-<?php echo esc_attr($position); ?>">
            <button id="tabaix-seo-chatbot-toggle" aria-label="Open Chat">
                <svg id="tabaix-seo-chat-icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path
                        d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z">
                    </path>
                </svg>
                <svg id="tabaix-seo-close-icon" style="display:none" viewBox="0 0 24 24" width="28" height="28" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <div id="uam-chatbot-panel" aria-hidden="true">
                <div id="uam-chatbot-header">
                    <div class="uam-chatbot-avatar">🤖</div>
                    <div class="uam-chatbot-info">
                        <strong>AI Support Master</strong>
                        <span class="uam-chatbot-status">Online & Ready</span>
                    </div>
                </div>

                <div id="tabaix-seo-chatbot-messages">
                    <!-- Messages will be injected here -->
                </div>

                <div id="tabaix-seo-chatbot-typing" class="tabaix-seo-hidden">
                    <span></span><span></span><span></span>
                </div>

                <div id="tabaix-seo-chatbot-input-area">
                    <textarea id="tabaix-seo-chatbot-input" rows="1" placeholder="Ask me anything..."></textarea>
                    <button id="tabaix-seo-chatbot-send" aria-label="Send Message">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the chatbot AJAX message request.
     */
    public function handle_chatbot_message()
    {
        check_ajax_referer('tabaix_seo_chatbot_nonce', 'nonce');

        $user_message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        if (empty($user_message)) {
            wp_send_json_error(['message' => 'Message is empty.']);
        }

        // Build a helpful context-aware system prompt
        $site_name = get_bloginfo('name');
        $system_prompt = "You are a friendly and helpful AI assistant for the website '{$site_name}'.\n" .
            "Your goal is to assist visitors with their questions in a professional and concise manner.\n" .
            "If you don't know the answer, politely suggest they contact the site administrator.\n" .
            "Respond in plain text or simple markdown.";

        $response = TABAIX_SEO_API::generate($user_message, $system_prompt, [
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        wp_send_json_success(['result' => $response]);
    }

    /**
     * Shortcode to render the chatbot on specific pages.
     */
    public function render_shortcode($atts)
    {
        ob_start();
        $this->render_widget();
        return ob_get_clean();
    }
}
