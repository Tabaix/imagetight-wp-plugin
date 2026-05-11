<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_SEO_Optimizer
{

    // ── Readability Analysis ────────────────────────────────────────────────────

    public static function analyze_readability($content)
    {
        $stripped = wp_strip_all_tags($content);
        $prompt = "Analyze the readability of the following content and return a JSON report.

CONTENT:
\"\"\"
{$stripped}
\"\"\"

Return ONLY a valid JSON object with:
{
  \"score\": <1-100 readability score>,
  \"grade_level\": \"<e.g. Grade 8>\",
  \"avg_sentence_length\": <number>,
  \"passive_voice_percent\": <percent>,
  \"issues\": [\"<issue1>\", \"<issue2>\"],
  \"suggestions\": [\"<suggestion1>\", \"<suggestion2>\"],
  \"summary\": \"<2-sentence overall assessment>\"
}";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Keyword Optimization ────────────────────────────────────────────────────

    public static function analyze_keywords($content, $focus_keyword = '')
    {
        $stripped = wp_strip_all_tags($content);
        $fk = $focus_keyword ? "Focus keyword: \"{$focus_keyword}\"." : '';
        $prompt = "Perform keyword analysis on the following content. {$fk}

CONTENT:
\"\"\"
{$stripped}
\"\"\"

Return ONLY a valid JSON object with:
{
  \"focus_keyword_density\": <percent or null>,
  \"focus_keyword_count\": <number or null>,
  \"recommended_keywords\": [\"keyword1\",\"keyword2\",\"keyword3\"],
  \"keyword_gaps\": [\"missing1\",\"missing2\"],
  \"placement_suggestions\": [\"<suggestion1>\",\"<suggestion2>\"],
  \"lsi_keywords\": [\"lsi1\",\"lsi2\",\"lsi3\"]
}";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Plagiarism Check (AI-based originality scoring) ─────────────────────────

    public static function check_originality($content)
    {
        $stripped = wp_strip_all_tags($content);
        $prompt = "Evaluate the originality and uniqueness of the following content. Identify if it sounds generic, templated, or potentially duplicated.

CONTENT:
\"\"\"
{$stripped}
\"\"\"

Return ONLY a valid JSON object with:
{
  \"originality_score\": <0-100>,
  \"assessment\": \"unique|generic|likely-duplicate\",
  \"generic_phrases\": [\"phrase1\",\"phrase2\"],
  \"rewrite_suggestions\": [\"<suggestion1>\",\"<suggestion2>\"],
  \"summary\": \"<brief assessment>\"
}";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Grammar & Spelling Correction ───────────────────────────────────────────

    public static function fix_grammar($content)
    {
        $prompt = "You are a professional editor. Correct ALL grammar, spelling, punctuation, and style issues in the following text. 
Preserve the original meaning, tone, and structure. 
Return the corrected text only — no explanation, no quotes around it.

TEXT TO CORRECT:
{$content}";
        return TABAIX_SEO_API::generate($prompt);
    }

    public static function grammar_report($content)
    {
        $stripped = wp_strip_all_tags($content);
        $prompt = "Identify grammar, spelling, and punctuation issues in this content. Return ONLY a JSON object:
{
  \"error_count\": <number>,
  \"errors\": [
    {\"type\":\"Grammar|Spelling|Punctuation\", \"original\":\"...\", \"correction\":\"...\", \"explanation\":\"...\"}
  ],
  \"overall_quality\": \"excellent|good|fair|poor\"
}

CONTENT:
\"\"\"
{$stripped}
\"\"\"";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Content Performance Prediction ─────────────────────────────────────────

    public static function predict_performance($title, $content, $niche = '')
    {
        $stripped = wp_strip_all_tags(substr($content, 0, 1000));
        $niche_str = $niche ? "Niche: {$niche}." : '';
        $prompt = "Predict the content performance of this blog post. {$niche_str}

Title: \"{$title}\"
Content preview:
\"\"\"{$stripped}\"\"\"

Return ONLY valid JSON:
{
  \"predicted_score\": <1-100>,
  \"viral_potential\": \"low|medium|high\",
  \"seo_score\": <1-100>,
  \"engagement_score\": <1-100>,
  \"strengths\": [\"...\",\"...\"],
  \"weaknesses\": [\"...\",\"...\"],
  \"recommendations\": [\"...\",\"...\"]
}";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Sentiment Analysis ──────────────────────────────────────────────────────

    public static function analyze_sentiment($content)
    {
        $stripped = wp_strip_all_tags($content);
        $prompt = "Analyze the audience sentiment and emotional tone of this content.

CONTENT:
\"\"\"
{$stripped}
\"\"\"

Return ONLY valid JSON:
{
  \"overall_sentiment\": \"positive|neutral|negative\",
  \"sentiment_score\": <-100 to 100>,
  \"emotions\": {\"joy\":0,\"trust\":0,\"surprise\":0,\"sadness\":0,\"fear\":0,\"anger\":0},
  \"tone\": \"professional|conversational|authoritative|casual|inspirational\",
  \"audience_reaction\": \"<predicted reaction>\",
  \"suggestions\": [\"...\"]
}";
        return TABAIX_SEO_API::generate($prompt);
    }
}
