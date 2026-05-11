<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Comment_Moderator
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
        if (TABAIX_SEO_Settings::get('moderation_auto')) {
            add_filter('preprocess_comment', [$this, 'auto_moderate']);
        }
    }

    /**
     * Auto-moderation hook on comment submission.
     */
    public function auto_moderate($comment_data)
    {
        $result = self::analyze_comment($comment_data['comment_content']);

        if (!is_wp_error($result)) {
            $json = json_decode($result, true);
            if ($json && isset($json['decision'])) {
                if ($json['decision'] === 'spam' || $json['decision'] === 'reject') {
                    wp_die(esc_html__('Your comment was flagged as inappropriate by our AI moderator.', 'tabaix-seo-optimizer-pro'), 403);
                }
                if ($json['decision'] === 'hold') {
                    $comment_data['comment_approved'] = '0'; // Hold for review
                }
            }
        }

        return $comment_data;
    }

    /**
     * Analyze a single comment.
     */
    public static function analyze_comment($comment_text)
    {
        $prompt = "Moderate this blog comment as an AI moderation system.

Comment:
\"\"\"
{$comment_text}
\"\"\"

Return ONLY valid JSON:
{
  \"decision\": \"approve|hold|spam|reject\",
  \"confidence\": <0-100>,
  \"reasons\": [\"...\"],
  \"spam_indicators\": [\"...\"],
  \"toxicity_score\": <0-100>,
  \"sentiment\": \"positive|neutral|negative\",
  \"summary\": \"Brief assessment\"
}

Criteria:
- approve: genuine, relevant comment
- hold: borderline, needs human review
- spam: promotional links, gibberish, bot-generated
- reject: hate speech, offensive content, threats";
        return TABAIX_SEO_API::generate($prompt);
    }

    /**
     * Bulk analyze pending comments.
     */
    public static function bulk_analyze($limit = 10)
    {
        $comments = get_comments([
            'status' => 'hold',
            'number' => $limit,
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ]);

        $results = [];
        foreach ($comments as $comment) {
            $analysis = self::analyze_comment($comment->comment_content);
            $results[] = [
                'comment_id' => $comment->comment_ID,
                'author' => $comment->comment_author,
                'excerpt' => substr($comment->comment_content, 0, 100),
                'analysis' => is_wp_error($analysis) ? ['error' => $analysis->get_error_message()] : json_decode($analysis, true),
            ];
        }

        return $results;
    }
}
