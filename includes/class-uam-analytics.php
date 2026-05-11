<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Analytics
{

    /**
     * Generate an automated website performance report summary.
     */
    public static function generate_report($data)
    {
        $prompt = "Generate a professional website performance report summary based on this data:

Page views (30 days): {$data['pageviews']}
Unique visitors: {$data['visitors']}
Bounce rate: {$data['bounce_rate']}%
Avg session duration: {$data['session_duration']} seconds
Top posts: " . implode(', ', $data['top_posts']) . "
New comments: {$data['new_comments']}
New posts published: {$data['new_posts']}

Return ONLY valid JSON:
{
  \"executive_summary\": \"...\",
  \"performance_grade\": \"A|B|C|D|F\",
  \"key_highlights\": [\"...\",\"...\",\"...\"],
  \"areas_of_concern\": [\"...\",\"...\"],
  \"recommendations\": [\"...\",\"...\",\"...\"],
  \"next_steps\": [\"...\",\"...\"]
}";
        return TABAIX_SEO_API::generate($prompt);
    }

    /**
     * Get mock analytics data (WordPress-native, no GA dependency).
     */
    public static function get_native_analytics()
    {
        global $wpdb;

        $post_views = [];
        $posts = get_posts([
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'comment_count',
            'order' => 'DESC',
        ]);

        foreach ($posts as $p) {
            $post_views[] = $p->post_title;
        }

        $new_posts = wp_count_posts()->publish;
        $new_comments = wp_count_comments()->approved;

        return [
            'pageviews' => wp_rand(5000, 50000),
            'visitors' => wp_rand(1000, 10000),
            'bounce_rate' => wp_rand(35, 75),
            'session_duration' => wp_rand(60, 300),
            'top_posts' => $post_views ?: ['No posts yet'],
            'new_comments' => $new_comments,
            'new_posts' => $new_posts,
        ];
    }
}
