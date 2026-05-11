<?php
if (!defined('ABSPATH'))
    exit;

class TABAIX_SEO_Content_Generator
{

    // ── Blog Post Drafts ────────────────────────────────────────────────────────

    public static function generate_outline($topic, $keywords = '')
    {
        $kw = $keywords ? "Target keywords: {$keywords}." : '';
        $prompt = "You are an expert content strategist. Create a detailed blog post outline for the topic: \"{$topic}\". {$kw}
Include:
- An engaging blog title (H1)
- Introduction hook
- 5-7 main sections with descriptive H2 headings
- 2-3 bullet sub-points per section
- Conclusion section
- A CTA suggestion

Format the response in clean Markdown.";
        return TABAIX_SEO_API::generate($prompt);
    }

    public static function generate_intro($topic, $keywords = '')
    {
        $kw = $keywords ? " (Keywords: {$keywords})" : '';
        $prompt = "Write a compelling, SEO-friendly introduction paragraph for a blog post about: \"{$topic}\"{$kw}. 
The intro should:
- Hook the reader immediately
- Explain what they will learn
- Be 100-150 words
- Flow naturally and avoid keyword stuffing

Return only the introduction paragraph, no extra commentary.";
        return TABAIX_SEO_API::generate($prompt);
    }

    public static function generate_conclusion($topic, $main_points = '')
    {
        $pts = $main_points ? "Main points covered: {$main_points}." : '';
        $prompt = "Write a strong conclusion for a blog post about: \"{$topic}\". {$pts}
The conclusion should:
- Summarize the key takeaways
- Reinforce the main message
- Include a clear CTA
- Be 100-150 words

Return only the conclusion, no extra commentary.";
        return TABAIX_SEO_API::generate($prompt);
    }

    public static function generate_full_post($topic, $keywords = '', $word_count = 800)
    {
        $kw = $keywords ? "Target keywords: {$keywords}." : '';
        $prompt = "Write a complete, high-quality blog post about: \"{$topic}\". {$kw}
Requirements:
- Approximately {$word_count} words
- Engaging title (H1)
- Natural keyword integration
- Multiple H2/H3 subheadings
- Short readable paragraphs
- Bullet lists where appropriate
- Strong conclusion with CTA
- SEO optimized

Format in clean Markdown.";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Product Descriptions ────────────────────────────────────────────────────

    public static function generate_product_description($product_name, $features = '', $audience = '')
    {
        $feat = $features ? "Key features: {$features}." : '';
        $aud = $audience ? "Target audience: {$audience}." : '';
        $prompt = "Write a compelling WooCommerce product description for: \"{$product_name}\". {$feat} {$aud}
Include:
- An engaging opening hook
- Key benefits (not just features)
- Social proof suggestion
- Clear CTA
- 150-250 words
- Formatted in HTML suitable for WordPress";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Meta & SEO Titles ───────────────────────────────────────────────────────

    public static function generate_meta($title, $content_snippet = '', $keyword = '')
    {
        $snip = $content_snippet ? "Content snippet: " . substr($content_snippet, 0, 300) : '';
        $kw = $keyword ? "Primary keyword: {$keyword}." : '';
        $prompt = "Generate SEO-optimized meta data for a WordPress post.
Post title: \"{$title}\"
{$snip}
{$kw}

Return ONLY a valid JSON object with these fields:
{
  \"seo_title\": \"...\",
  \"meta_description\": \"...\",
  \"focus_keyword\": \"...\"
}
- seo_title: 50-60 characters, includes keyword near the start
- meta_description: 150-160 characters, compelling, includes keyword
- focus_keyword: single best keyword";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Social Media ────────────────────────────────────────────────────────────

    public static function generate_social_posts($topic, $url = '')
    {
        $link = $url ? "Article URL: {$url}" : '';
        $prompt = "Create social media posts for this topic: \"{$topic}\". {$link}
Generate posts for:
1. Twitter/X (max 280 chars, include hashtags)
2. LinkedIn (professional, 150-200 words, insightful)
3. Facebook (conversational, 100-150 words, engaging)
4. Instagram caption (vibrant, emoji-rich, 5 hashtags)

Return ONLY a valid JSON object with keys: twitter, linkedin, facebook, instagram.";
        return TABAIX_SEO_API::generate($prompt);
    }

    // ── Email Marketing ─────────────────────────────────────────────────────────

    public static function generate_email($type, $topic, $brand = '')
    {
        $br = $brand ? "Brand name: {$brand}." : '';
        $type_map = [
            'newsletter' => 'engaging weekly newsletter',
            'promotional' => 'promotional sales email',
            'welcome' => 'welcome email for new subscribers',
            'abandoned' => 'abandoned cart recovery email',
            'announcement' => 'product announcement email',
        ];
        $type_label = $type_map[$type] ?? 'marketing email';

        $prompt = "Write a professional {$type_label} about: \"{$topic}\". {$br}
Include:
- Attention-grabbing subject line
- Preview text (preheader)
- Personalized greeting
- Compelling body copy
- Clear CTA button text
- P.S. line

Return ONLY a valid JSON object with keys: subject, preheader, greeting, body, cta, ps";
        return TABAIX_SEO_API::generate($prompt);
    }
}
