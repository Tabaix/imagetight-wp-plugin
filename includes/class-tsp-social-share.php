<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_Social_Share — Social Sharing Buttons for Tabaix All-in-One SEO & Optimizer
 *
 * Adds beautiful social sharing buttons (Facebook, X/Twitter, WhatsApp,
 * LinkedIn, Pinterest, Telegram) to posts/pages.
 *
 * Settings: position (below_content | floating), enabled networks.
 */
class TABAIX_SEO_Social_Share
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
        add_filter('the_content',         [$this, 'inject_share_buttons']);
        add_action('wp_footer',           [$this, 'render_floating_buttons']);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_assets']);
        add_action('admin_init',          [$this, 'register_settings']);
    }

    /* ───────────────────────────────────────────────
       Settings
    ─────────────────────────────────────────────── */
    public function register_settings()
    {
        register_setting('tabaix-seo-optimizer-pro', 'tabaix_seo_ss_enabled',    ['sanitize_callback' => 'absint',              'default' => 1]);
        register_setting('tabaix-seo-optimizer-pro', 'tabaix_seo_ss_position',   ['sanitize_callback' => 'sanitize_text_field', 'default' => 'below_content']);
        register_setting('tabaix-seo-optimizer-pro', 'tabaix_seo_ss_networks',   ['sanitize_callback' => [$this, 'sanitize_networks'], 'default' => ['facebook','twitter','whatsapp','linkedin','pinterest','telegram','reddit','email','copy_link']]);
        register_setting('tabaix-seo-optimizer-pro', 'tabaix_seo_ss_label',      ['sanitize_callback' => 'sanitize_text_field', 'default' => 'Share this post:']);
        register_setting('tabaix-seo-optimizer-pro', 'tabaix_seo_ss_show_count', ['sanitize_callback' => 'absint',              'default' => 0]);
    }

    public function sanitize_networks($value)
    {
        $allowed = ['facebook','twitter','whatsapp','linkedin','pinterest','telegram','reddit','email','copy_link'];
        if (!is_array($value)) return [];
        return array_values(array_intersect($value, $allowed));
    }

    /* ───────────────────────────────────────────────
       Assets
    ─────────────────────────────────────────────── */
    public function enqueue_assets()
    {
        if (!is_singular()) return;
        if (!get_option('tabaix_seo_ss_enabled', 1)) return;

        wp_enqueue_style('tabaix-seo-social-share', plugins_url('../assets/css/tsp-social-share.css', __FILE__), [], '1.1.0');
        wp_enqueue_script('tabaix-seo-social-share', plugins_url('../assets/js/tsp-social-share.js', __FILE__), [], '1.1.0', true);
    }

    /* ───────────────────────────────────────────────
       Share URL helpers
    ─────────────────────────────────────────────── */
    private function get_share_links()
    {
        $url   = rawurlencode(get_permalink());
        $title = rawurlencode(get_the_title());
        $image = '';

        // Try to get the featured image for Pinterest
        if (has_post_thumbnail()) {
            $image = rawurlencode(get_the_post_thumbnail_url(null, 'large'));
        }

        return [
            'facebook'  => [
                'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
                'label' => 'Facebook',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
                'color' => '#1877f2',
            ],
            'twitter'   => [
                'url'   => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
                'label' => 'X (Twitter)',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                'color' => '#000000',
            ],
            'whatsapp'  => [
                'url'   => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url,
                'label' => 'WhatsApp',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
                'color' => '#25d366',
            ],
            'linkedin'  => [
                'url'   => 'https://www.linkedin.com/shareArticle?mini=true&url=' . $url . '&title=' . $title,
                'label' => 'LinkedIn',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
                'color' => '#0077b5',
            ],
            'pinterest' => [
                'url'   => 'https://pinterest.com/pin/create/button/?url=' . $url . '&description=' . $title . ($image ? '&media=' . $image : ''),
                'label' => 'Pinterest',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>',
                'color' => '#bd081c',
            ],
            'telegram'  => [
                'url'   => 'https://t.me/share/url?url=' . $url . '&text=' . $title,
                'label' => 'Telegram',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
                'color' => '#2ca5e0',
            ],
            'reddit'    => [
                'url'   => 'https://reddit.com/submit?url=' . $url . '&title=' . $title,
                'label' => 'Reddit',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 11.5c0-1.65-1.35-3-3-3-.96 0-1.86.48-2.42 1.24-1.64-1-3.75-1.64-6.07-1.72.08-1.1.4-3.05 1.52-3.7.72-.4 1.73-.24 3 .5C17.2 6.3 18.46 7.5 20 7.5c1.65 0 3-1.35 3-3s-1.35-3-3-3c-1.38 0-2.54.94-2.88 2.22-1.43-.72-2.64-.8-3.6-.25-1.64.94-1.95 3.47-2 4.55-2.33.08-4.45.7-6.1 1.72C4.86 8.98 3.96 8.5 3 8.5c-1.65 0-3 1.35-3 3 0 1.32.84 2.44 2.05 2.84-.03.22-.05.44-.05.66 0 3.86 4.5 7 10 7s10-3.14 10-7c0-.22-.02-.44-.05-.66 1.2-.4 2.05-1.54 2.05-2.84zM2.3 11.5c0-.94.76-1.7 1.7-1.7.67 0 1.27.4 1.52.98-1.1.6-2.02 1.36-2.7 2.22-.32-.42-.52-.94-.52-1.5zM20 3.8c.8 0 1.45.65 1.45 1.45s-.65 1.45-1.45 1.45-1.45-.65-1.45-1.45.65-1.45 1.45-1.45zM8.3 15.6c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5zm3.7 4.1c-2.3 0-4.16-.9-4.2-2.06h1.4c.05.6.87 1.16 2.8 1.16 1.95 0 2.76-.56 2.8-1.16h1.4c-.05 1.16-1.9 2.06-4.2 2.06zm3.2-2.6c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.98-1.52c-.68-.86-1.6-1.62-2.7-2.22.25-.58.85-.98 1.52-.98.94 0 1.7.76 1.7 1.7 0 .56-.2 1.08-.52 1.5z"/></svg>',
                'color' => '#ff4500',
            ],
            'email'     => [
                'url'   => 'mailto:?subject=' . $title . '&body=' . $url,
                'label' => 'Email',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
                'color' => '#64748B',
                'no_popup'=> true,
            ],
            'copy_link' => [
                'url'   => get_permalink(),
                'label' => 'Copy Link',
                'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>',
                'color' => '#10b981',
                'onclick'=> 'navigator.clipboard.writeText(this.href);var t=this.querySelector(".tsp-ss-text");var o=t.innerText;t.innerText="Copied!";setTimeout(()=>{t.innerText=o;},2000);return false;',
            ],
        ];
    }

    /* ───────────────────────────────────────────────
       Render HTML
    ─────────────────────────────────────────────── */
    private function render_buttons_html($context = 'inline')
    {
        if (!is_singular() || !get_option('tabaix_seo_ss_enabled', 1)) return '';
        $networks  = get_option('tabaix_seo_ss_networks', ['facebook','twitter','whatsapp','linkedin','telegram']);
        $label     = get_option('tabaix_seo_ss_label', 'Share this post:');
        $all_links = $this->get_share_links();

        ob_start();
        ?>
        <div class="tsp-ss-wrap tsp-ss-<?php echo esc_attr($context); ?>">
            <?php if ($label && $context === 'inline') : ?>
                <p class="tsp-ss-label"><?php echo esc_html($label); ?></p>
            <?php endif; ?>
            <div class="tsp-ss-buttons">
                <?php foreach ($networks as $network) :
                    if (!isset($all_links[$network])) continue;
                    $link = $all_links[$network];
                    $onclick = $link['onclick'] ?? (empty($link['no_popup']) ? "window.open(this.href,'share','width=600,height=400'); return false;" : "");
                ?>
                <a
                    href="<?php echo esc_url($link['url']); ?>"
                    class="tsp-ss-btn tsp-ss-<?php echo esc_attr($network); ?>"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    aria-label="<?php echo esc_attr('Share on ' . $link['label']); ?>"
                    style="--btn-color: <?php echo esc_attr($link['color']); ?>;"
                    <?php if ($onclick) echo 'onclick="' . esc_attr($onclick) . '"'; ?>
                >
                    <span class="tsp-ss-icon"><?php echo $link['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="tsp-ss-text"><?php echo esc_html($link['label']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ───────────────────────────────────────────────
       Hooks
    ─────────────────────────────────────────────── */
    public function inject_share_buttons($content)
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) return $content;
        if (get_option('tabaix_seo_ss_position', 'below_content') !== 'below_content') return $content;
        if (!get_option('tabaix_seo_ss_enabled', 1)) return $content;

        return $content . $this->render_buttons_html('inline');
    }

    public function render_floating_buttons()
    {
        if (!is_singular()) return;
        if (get_option('tabaix_seo_ss_position', 'below_content') !== 'floating') return;
        if (!get_option('tabaix_seo_ss_enabled', 1)) return;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->render_buttons_html('floating');
    }
}
