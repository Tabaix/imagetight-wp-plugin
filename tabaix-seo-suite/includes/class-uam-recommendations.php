<?php
if (!defined('ABSPATH'))
    exit;

class UAM_Recommendations
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
        if (UAM_Settings::get('recommend_enabled')) {
            add_shortcode('uam_recommendations', [$this, 'render_shortcode']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        }
    }

    public function enqueue()
    {
        if (is_singular()) {
            wp_enqueue_style('uam-recommendations', UAM_PLUGIN_URL . 'assets/css/recommendations.css', [], UAM_VERSION);
        }
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(['count' => 4, 'title' => 'You Might Also Like'], $atts);
        $posts = self::get_recommendations(get_the_ID(), (int) $atts['count']);

        if (empty($posts))
            return '';

        ob_start();
        ?>
        <div class="uam-recommendations">
            <h3 class="uam-rec-title">
                <?php echo esc_html($atts['title']); ?>
            </h3>
            <div class="uam-rec-grid">
                <?php foreach ($posts as $p): ?>
                    <article class="uam-rec-card">
                        <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="uam-rec-thumb">
                            <?php if (has_post_thumbnail($p->ID)): ?>
                                <?php echo get_the_post_thumbnail($p->ID, 'medium', ['loading' => 'lazy']); ?>
                            <?php else: ?>
                                <div class="uam-rec-no-thumb"></div>
                            <?php endif; ?>
                        </a>
                        <div class="uam-rec-meta">
                            <span class="uam-rec-cat">
                                <?php
                                $cats = get_the_category($p->ID);
                                echo $cats ? esc_html($cats[0]->name) : '';
                                ?>
                            </span>
                            <h4><a href="<?php echo esc_url(get_permalink($p->ID)); ?>">
                                    <?php echo esc_html($p->post_title); ?>
                                </a></h4>
                            <span class="uam-rec-date">
                                <?php echo get_the_date('', $p->ID); ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get AI-suggested related posts (using taxonomy-based + keyword matching).
     */
    public static function get_recommendations($post_id, $count = 4)
    {
        $post = get_post($post_id);
        $terms = wp_get_post_terms($post_id, ['category', 'post_tag'], ['fields' => 'ids']);

        if (empty($terms)) {
            // Fallback: recent posts
            return get_posts([
                'posts_per_page' => $count,
                'post__not_in' => [$post_id],
                'post_status' => 'publish',
            ]);
        }

        return get_posts([
            'posts_per_page' => $count,
            'post__not_in' => [$post_id],
            'post_status' => 'publish',
            'tax_query' => [
                'relation' => 'OR',
                ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $terms],
                ['taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => $terms],
            ],
        ]);
    }

    /**
     * Use AI to get personalized recommendations based on reading history.
     */
    public static function ai_recommendations($viewed_titles = [], $current_title = '')
    {
        if (empty($viewed_titles))
            return [];

        $history = implode(', ', array_slice($viewed_titles, -5));
        $prompt = "A user has recently read these blog posts: {$history}.
They are currently reading: \"{$current_title}\".

Suggest 4 blog post topics they would likely enjoy next. Return ONLY valid JSON:
{\"suggestions\": [\"Topic 1\",\"Topic 2\",\"Topic 3\",\"Topic 4\"]}";

        return UAM_API::generate($prompt);
    }
}
