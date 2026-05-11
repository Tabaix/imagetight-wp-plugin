<?php
if (!defined('ABSPATH'))
    exit;

/**
 * UAM Internal Links — Full-featured AI-powered linking engine.
 *
 * Features:
 *  - AI keyword extraction from post content
 *  - AI-powered internal & external link suggestions
 *  - Broken link detection & repair
 *  - Manual link editor (add/edit/remove internal & external links)
 *  - Auto-link rules (keyword → URL mapping)
 *  - Orphan content detection
 *  - Bulk link operations
 *  - Link health monitoring
 */
class TABAIX_SEO_Internal_Links
{
    const META_LINK_SCAN = '_uam_link_scan';
    const OPTION_AUTOLINK_RULES = 'tabaix_seo_autolink_rules';
    const OPTION_MANUAL_LINKS = 'tabaix_seo_manual_links';

    // ─── AI Keyword Extraction ──────────────────────────────────────────────

    /**
     * Use AI to extract SEO-relevant keywords from post content.
     *
     * @param int $post_id The post to analyze.
     * @return array|WP_Error Array of keywords or error.
     */
    public static function extract_keywords($post_id)
    {
        $post = get_post($post_id);
        if (!$post)
            return new WP_Error('no_post', 'Post not found.');

        $content = wp_strip_all_tags($post->post_content);
        if (strlen($content) < 50)
            return new WP_Error('short_content', 'Post content is too short for analysis.');

        $content_trimmed = mb_substr($content, 0, 3000);

        $prompt = "You are a senior SEO analyst. Extract the most important keywords and key phrases from this article content.\n\n" .
                  "**Post Title:** {$post->post_title}\n\n" .
                  "**Content:**\n{$content_trimmed}\n\n" .
                  "**RULES:**\n" .
                  "1. Extract 8-15 keywords/phrases\n" .
                  "2. Include a mix of:\n" .
                  "   - Short-tail keywords (1-2 words): e.g. \"SEO\", \"WordPress\"\n" .
                  "   - Long-tail keywords (3-5 words): e.g. \"improve page loading speed\"\n" .
                  "3. Each keyword MUST actually appear in or closely relate to the content\n" .
                  "4. Focus on keywords with SEO link-building potential\n" .
                  "5. For each keyword, suggest whether it's better for internal linking (to other posts on the site) or external linking (to authority resources)\n" .
                  "6. Rate search volume potential as \"high\", \"medium\", or \"low\"\n\n" .
                  "**Return ONLY a valid JSON array, no other text:**\n" .
                  "[\n" .
                  "  {\n" .
                  "    \"keyword\": \"the keyword or phrase\",\n" .
                  "    \"type\": \"short-tail or long-tail\",\n" .
                  "    \"link_type\": \"internal or external\",\n" .
                  "    \"volume\": \"high\",\n" .
                  "    \"context\": \"one sentence about why this keyword matters for SEO\"\n" .
                  "  }\n" .
                  "]";

        $result = TABAIX_SEO_API::generate($prompt);

        if (is_wp_error($result))
            return $result;

        // Parse JSON
        $result = trim($result);
        $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
        $result = preg_replace('/\s*```$/', '', $result);

        $keywords = json_decode($result, true);
        if (!is_array($keywords))
            return new WP_Error('parse_error', 'Could not parse AI response. Please try again.');

        // Validate
        $filtered = [];
        $seen = [];
        foreach ($keywords as $kw) {
            $keyword = trim($kw['keyword'] ?? '');
            if (empty($keyword))
                continue;
            $lower = mb_strtolower($keyword);
            if (isset($seen[$lower]))
                continue;
            $seen[$lower] = true;

            $filtered[] = [
                'keyword' => $keyword,
                'type' => in_array($kw['type'] ?? '', ['short-tail', 'long-tail']) ? $kw['type'] : 'short-tail',
                'link_type' => in_array($kw['link_type'] ?? '', ['internal', 'external']) ? $kw['link_type'] : 'internal',
                'volume' => in_array($kw['volume'] ?? '', ['high', 'medium', 'low']) ? $kw['volume'] : 'medium',
                'context' => sanitize_text_field($kw['context'] ?? ''),
            ];
        }

        return $filtered;
    }

    // ─── AI Link Suggestions (Internal + External) ───────────────────────────

    /**
     * Use AI to suggest both internal AND external links for a post.
     *
     * @param int    $post_id   The post to analyze.
     * @param string $link_type 'all', 'internal', or 'external'
     * @return array|WP_Error
     */
    public static function ai_suggest_links($post_id, $link_type = 'all')
    {
        $post = get_post($post_id);
        if (!$post)
            return new WP_Error('no_post', 'Post not found.');

        $content = wp_strip_all_tags($post->post_content);
        if (strlen($content) < 50)
            return new WP_Error('short_content', 'Post content is too short for analysis.');

        // Get all published posts/pages
        $all_posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'post__not_in' => [$post_id],
            'fields' => 'ids',
        ]);

        $post_list = '';
        $post_map = [];
        foreach ($all_posts as $pid) {
            $p_title = get_the_title($pid);
            $p_url = get_permalink($pid);
            $post_list .= "- ID:{$pid} | \"{$p_title}\" | {$p_url}\n";
            $post_map[$pid] = ['title' => $p_title, 'url' => $p_url];
        }

        $content_trimmed = mb_substr($content, 0, 3000);

        // Extract existing links
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $post->post_content, $existing_links);
        $existing_anchors = array_map('wp_strip_all_tags', $existing_links[1] ?? []);
        $existing_list = !empty($existing_anchors) ? implode(', ', array_map(function ($a) {
            return '"' . $a . '"';
        }, $existing_anchors)) : 'None';

        $custom_gemini_key = TABAIX_SEO_Settings::get('gemini_api_key');
        $tabaix_license_key = get_option('itc_api_key', '');

        // Vercel Cloud Execution: Use Vercel Edge compute if SaaS license is provided, BUT use the user's API key
        if (!empty($tabaix_license_key) && !empty($custom_gemini_key)) {
            $proxy_url = 'https://imagetight-api.vercel.app/api/scan-links';
            
            $site_posts_payload = [];
            foreach ($post_map as $pid => $data) {
                $site_posts_payload[] = ['id' => $pid, 'title' => $data['title'], 'url' => $data['url']];
            }

            $payload = [
                'tabaix_license_key' => $tabaix_license_key,
                'gemini_key'         => $custom_gemini_key, // Passed through so Vercel uses the User's quota!
                'post_title'         => $post->post_title,
                'post_content'       => $content_trimmed,
                'site_posts'         => $site_posts_payload,
                'link_type'          => $link_type
            ];

            $response = wp_remote_post($proxy_url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($payload),
                'timeout' => 90,
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('vercel_error', 'Cloud Scanner error: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($code === 200 && !empty($data['suggestions'])) {
                $suggestions = $data['suggestions'];
            } else {
                return new WP_Error('scan_failed', $data['error'] ?? 'Vercel scanner failed.');
            }
        } else {
            // --- Fallback to standard local generation if no SaaS key ---
            $type_instruction = '';
            if ($link_type === 'internal') {
                $type_instruction = 'ONLY suggest internal links (to posts from the Available Posts list below).';
            } elseif ($link_type === 'external') {
                $type_instruction = 'ONLY suggest external links to high-authority websites (e.g. Wikipedia, official documentation, reputable industry sites). Do NOT use posts from the Available Posts list.';
            } else {
                $type_instruction = 'Suggest a mix of both internal links (to posts from the Available Posts list) AND external links (to high-authority websites). Aim for roughly 60% internal and 40% external.';
            }

            $prompt = "You are a senior SEO strategist specializing in link building for WordPress sites.\n\n" .
                      "**Your Task:** Analyze this post and suggest high-quality links. {$type_instruction}\n\n" .
                      "**Post Title:** {$post->post_title}\n\n" .
                      "**Post Content:**\n{$content_trimmed}\n\n" .
                      "**Available Internal Posts to Link To:**\n{$post_list}\n\n" .
                      "**Already Linked (DO NOT reuse these):** {$existing_list}\n\n" .
                      "**STRICT RULES — Follow every rule exactly:**\n\n" .
                      "1. **UNIQUE ANCHOR TEXT**: Every anchor_text MUST be a distinct phrase from a different part of the content.\n" .
                      "2. **EXACT MATCH REQUIRED**: The anchor_text MUST be an EXACT phrase that appears word-for-word in the Post Content above. Copy it exactly.\n" .
                      "3. **NATURAL LENGTH**: Vary anchor text length (2-7 words).\n" .
                      "4. **CONTEXTUAL RELEVANCE**: Only suggest links where the anchor text is genuinely related to the target content.\n" .
                      "5. **NO GENERIC PHRASES**: Never use \"click here\", \"read more\", \"this article\", \"learn more\", etc.\n" .
                      "6. **DIFFERENT SECTIONS**: Pick phrases from different paragraphs.\n" .
                      "7. **EACH TARGET UNIQUE**: Don't link to the same target more than once.\n" .
                      "8. **EXTERNAL LINKS**: For external links, suggest links to reputable, authoritative sources that add genuine value. Provide the actual URL. External links should open in a new tab and have rel=\"noopener noreferrer\".\n" .
                      "9. **LINK TYPE**: Mark each suggestion as \"internal\" or \"external\".\n" .
                      "10. **PRIORITY**: Rate each as \"high\", \"medium\", or \"low\".\n" .
                      "11. Suggest 5-10 links maximum. Quality over quantity.\n\n" .
                      "**Return ONLY a valid JSON array, no other text:**\n" .
                      "[\n" .
                      "  {\n" .
                      "    \"anchor_text\": \"exact phrase from content\",\n" .
                      "    \"target_post_id\": 123,\n" .
                      "    \"target_url\": \"https://example.com/page\",\n" .
                      "    \"target_title\": \"Page Title\",\n" .
                      "    \"link_type\": \"internal\",\n" .
                      "    \"reason\": \"why this link adds SEO value\",\n" .
                      "    \"priority\": \"high\"\n" .
                      "  }\n" .
                      "]\n\n" .
                      "For internal links, use target_post_id and set target_url to the post URL.\n" .
                      "For external links, set target_post_id to 0 and provide the external target_url and target_title.";

        $result = TABAIX_SEO_API::generate($prompt);

        if (is_wp_error($result))
            return $result;

            $result = trim($result);
            $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
            $result = preg_replace('/\s*```$/', '', $result);

            $suggestions = json_decode($result, true) ?? [];
        } // End of Vercel vs Local if-block

        if (!is_array($suggestions) || empty($suggestions))
            return new WP_Error('parse_error', 'Could not parse AI suggestions. Please try again.');

        // Post-processing
        $seen_anchors = [];
        $seen_targets = [];
        $filtered = [];

        foreach ($suggestions as $s) {
            $anchor = trim($s['anchor_text'] ?? '');
            $target_id = intval($s['target_post_id'] ?? 0);
            $target_url = esc_url_raw($s['target_url'] ?? '');
            $is_external = ($s['link_type'] ?? 'internal') === 'external';
            $anchor_lower = mb_strtolower($anchor);

            if (empty($anchor))
                continue;

            // Validate URL
            if (empty($target_url) && $target_id && isset($post_map[$target_id])) {
                $target_url = $post_map[$target_id]['url'];
            }
            if (empty($target_url))
                continue;

            // Skip if anchor doesn't exist in content
            if (stripos($content, $anchor) === false)
                continue;

            // Deduplication
            if (isset($seen_anchors[$anchor_lower]))
                continue;

            $target_key = $is_external ? $target_url : $target_id;
            if (isset($seen_targets[$target_key]))
                continue;

            // Skip generic anchors
            $generic = ['click here', 'read more', 'learn more', 'this article', 'here', 'link', 'this post'];
            if (in_array($anchor_lower, $generic))
                continue;

            $seen_anchors[$anchor_lower] = true;
            $seen_targets[$target_key] = true;

            // Enrich internal links
            if (!$is_external && $target_id && isset($post_map[$target_id])) {
                $s['target_title'] = $post_map[$target_id]['title'];
                $s['target_url'] = $post_map[$target_id]['url'];
            }

            $s['link_type'] = $is_external ? 'external' : 'internal';
            $s['priority'] = in_array(strtolower($s['priority'] ?? ''), ['high', 'medium', 'low']) ? strtolower($s['priority']) : 'medium';
            $s['target_url'] = $target_url;

            $filtered[] = $s;
        }

        return $filtered;
    }

    // ─── Find Related Posts ──────────────────────────────────────────────────

    /**
     * Find posts related to a given post by shared keywords/terms.
     */
    public static function find_related_posts($post_id, $limit = 10)
    {
        $post = get_post($post_id);
        if (!$post)
            return [];

        $categories = wp_get_post_categories($post_id, ['fields' => 'ids']);
        $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);

        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$post_id],
            'orderby' => 'relevance',
        ];

        $tax_queries = [];
        if (!empty($categories)) {
            $tax_queries[] = [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $categories,
            ];
        }
        if (!empty($tags)) {
            $tax_queries[] = [
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tags,
            ];
        }

        if (!empty($tax_queries)) {
            $tax_queries['relation'] = 'OR';
            $args['tax_query'] = $tax_queries;
        } else {
            $title_words = array_filter(explode(' ', $post->post_title), function ($w) {
                return strlen($w) > 3;
            });
            if (!empty($title_words)) {
                $args['s'] = implode(' ', array_slice($title_words, 0, 3));
            }
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $related) {
            $results[] = [
                'id' => $related->ID,
                'title' => $related->post_title,
                'url' => get_permalink($related->ID),
                'post_type' => $related->post_type,
                'excerpt' => wp_trim_words($related->post_content, 20, '…'),
            ];
        }

        return $results;
    }

    // ─── Broken Link Detection ──────────────────────────────────────────────

    /**
     * Check all links in a post for broken (404) or redirected URLs.
     *
     * @param int $post_id
     * @return array Results with link status.
     */
    public static function check_broken_links($post_id)
    {
        $post = get_post($post_id);
        if (!$post)
            return [];

        $content = $post->post_content;
        $site_url = home_url();

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);

        $results = [];

        foreach ($matches as $m) {
            $url = $m[1];
            $anchor = wp_strip_all_tags($m[2]);

            // Skip anchors (#), mailto, tel, javascript
            if (preg_match('/^(#|mailto:|tel:|javascript:)/i', $url))
                continue;

            // Determine if internal or external
            $is_internal = (strpos($url, $site_url) === 0 || strpos($url, '/') === 0);

            $link_result = [
                'url' => $url,
                'anchor' => $anchor,
                'type' => $is_internal ? 'internal' : 'external',
                'status' => 'checking',
                'status_code' => 0,
                'redirect_url' => '',
            ];

            // Check internal links by trying to resolve to a post
            if ($is_internal) {
                $linked_id = url_to_postid($url);
                $link_result['post_id'] = $linked_id;

                if ($linked_id) {
                    $linked_post = get_post($linked_id);
                    if ($linked_post && $linked_post->post_status === 'publish') {
                        $link_result['status'] = 'ok';
                        $link_result['status_code'] = 200;
                    } elseif ($linked_post && $linked_post->post_status === 'trash') {
                        $link_result['status'] = 'broken';
                        $link_result['status_code'] = 410; // Gone
                    } else {
                        $link_result['status'] = 'broken';
                        $link_result['status_code'] = 404;
                    }
                } else {
                    // Could be a valid page like /about or /contact — do HTTP check
                    $response = wp_remote_head($url, [
                        'timeout' => 10,
                        'redirection' => 3,
                        'sslverify' => false,
                    ]);
                    if (is_wp_error($response)) {
                        $link_result['status'] = 'error';
                        $link_result['error'] = $response->get_error_message();
                    } else {
                        $code = wp_remote_retrieve_response_code($response);
                        $link_result['status_code'] = $code;
                        if ($code >= 200 && $code < 400) {
                            $link_result['status'] = 'ok';
                        } elseif ($code >= 300 && $code < 400) {
                            $link_result['status'] = 'redirect';
                            $link_result['redirect_url'] = wp_remote_retrieve_header($response, 'location');
                        } else {
                            $link_result['status'] = 'broken';
                        }
                    }
                }
            } else {
                // External link — HTTP HEAD check
                $response = wp_remote_head($url, [
                    'timeout' => 15,
                    'redirection' => 5,
                    'sslverify' => false,
                    'user-agent' => 'Mozilla/5.0 (compatible; UAM Link Checker)',
                ]);

                if (is_wp_error($response)) {
                    $link_result['status'] = 'error';
                    $link_result['error'] = $response->get_error_message();
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    $link_result['status_code'] = $code;
                    if ($code >= 200 && $code < 300) {
                        $link_result['status'] = 'ok';
                    } elseif ($code >= 300 && $code < 400) {
                        $link_result['status'] = 'redirect';
                        $link_result['redirect_url'] = wp_remote_retrieve_header($response, 'location');
                    } else {
                        $link_result['status'] = 'broken';
                    }
                }
            }

            $results[] = $link_result;
        }

        // Categorize
        $broken = array_filter($results, function ($r) {
            return $r['status'] === 'broken';
        });
        $redirects = array_filter($results, function ($r) {
            return $r['status'] === 'redirect';
        });
        $ok = array_filter($results, function ($r) {
            return $r['status'] === 'ok';
        });
        $errors = array_filter($results, function ($r) {
            return $r['status'] === 'error';
        });

        return [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'total' => count($results),
            'ok_count' => count($ok),
            'broken_count' => count($broken),
            'redirect_count' => count($redirects),
            'error_count' => count($errors),
            'links' => $results,
        ];
    }

    // ─── Fix Broken Link ────────────────────────────────────────────────────

    /**
     * Replace a broken link URL in post content.
     *
     * @param int    $post_id  The post containing the link.
     * @param string $old_url  The broken URL to replace.
     * @param string $new_url  The replacement URL.
     * @param string $action   'replace', 'remove', or 'nofollow'
     * @return bool|WP_Error
     */
    public static function fix_link($post_id, $old_url, $new_url = '', $action = 'replace')
    {
        $post = get_post($post_id);
        if (!$post)
            return new WP_Error('no_post', 'Post not found.');

        $content = $post->post_content;

        switch ($action) {
            case 'remove':
                // Remove entire <a> tag, keep text
                $pattern = '/<a\s[^>]*href=["\']' . preg_quote($old_url, '/') . '["\'][^>]*>(.*?)<\/a>/is';
                $new_content = preg_replace($pattern, '$1', $content);
                break;

            case 'nofollow':
                // Add rel="nofollow" to link
                $pattern = '/<a\s([^>]*href=["\']' . preg_quote($old_url, '/') . '["\'][^>]*)>/is';
                $new_content = preg_replace_callback($pattern, function ($m) {
                    $attrs = $m[1];
                    if (strpos($attrs, 'rel=') !== false) {
                        $attrs = preg_replace('/rel=["\']([^"\']*)["\']/', 'rel="$1 nofollow"', $attrs);
                    } else {
                        $attrs .= ' rel="nofollow"';
                    }
                    return '<a ' . $attrs . '>';
                }, $content);
                break;

            case 'replace':
            default:
                if (empty($new_url))
                    return new WP_Error('no_url', 'Replacement URL is required.');
                $new_content = str_replace($old_url, esc_url($new_url), $content);
                break;
        }

        if ($new_content === $content)
            return new WP_Error('no_change', 'No changes were made.');

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content,
        ], true);

        return is_wp_error($updated) ? $updated : true;
    }

    // ─── Manual Link Management ─────────────────────────────────────────────

    /**
     * Get all manually saved link associations.
     */
    public static function get_manual_links()
    {
        return get_option(self::OPTION_MANUAL_LINKS, []);
    }

    /**
     * Save a manual link rule (keyword → URL mapping for both internal & external).
     */
    public static function save_manual_link($data)
    {
        $links = self::get_manual_links();

        $new_link = [
            'id' => uniqid('ml_'),
            'keyword' => sanitize_text_field($data['keyword'] ?? ''),
            'url' => esc_url_raw($data['url'] ?? ''),
            'type' => in_array($data['type'] ?? '', ['internal', 'external']) ? $data['type'] : 'internal',
            'title' => sanitize_text_field($data['title'] ?? ''),
            'nofollow' => !empty($data['nofollow']),
            'new_tab' => !empty($data['new_tab']),
            'max_links' => max(1, intval($data['max_links'] ?? 1)),
            'enabled' => true,
            'created' => current_time('mysql'),
        ];

        if (empty($new_link['keyword']) || empty($new_link['url']))
            return new WP_Error('missing_fields', 'Keyword and URL are required.');

        $links[] = $new_link;
        update_option(self::OPTION_MANUAL_LINKS, $links);

        return $new_link;
    }

    /**
     * Update a manual link by ID.
     */
    public static function update_manual_link($id, $data)
    {
        $links = self::get_manual_links();

        foreach ($links as &$link) {
            if ($link['id'] === $id) {
                if (isset($data['keyword']))
                    $link['keyword'] = sanitize_text_field($data['keyword']);
                if (isset($data['url']))
                    $link['url'] = esc_url_raw($data['url']);
                if (isset($data['type']))
                    $link['type'] = in_array($data['type'], ['internal', 'external']) ? $data['type'] : $link['type'];
                if (isset($data['title']))
                    $link['title'] = sanitize_text_field($data['title']);
                if (isset($data['nofollow']))
                    $link['nofollow'] = !empty($data['nofollow']);
                if (isset($data['new_tab']))
                    $link['new_tab'] = !empty($data['new_tab']);
                if (isset($data['max_links']))
                    $link['max_links'] = max(1, intval($data['max_links']));
                if (isset($data['enabled']))
                    $link['enabled'] = !empty($data['enabled']);

                update_option(self::OPTION_MANUAL_LINKS, $links);
                return $link;
            }
        }

        return new WP_Error('not_found', 'Link rule not found.');
    }

    /**
     * Delete a manual link by ID.
     */
    public static function delete_manual_link($id)
    {
        $links = self::get_manual_links();
        $links = array_filter($links, function ($l) use ($id) {
            return $l['id'] !== $id;
        });
        update_option(self::OPTION_MANUAL_LINKS, array_values($links));
        return true;
    }

    /**
     * Apply manual link rules to content (frontend filter).
     */
    public static function apply_manual_links($content)
    {
        $links = self::get_manual_links();
        if (empty($links))
            return $content;

        foreach ($links as $link) {
            if (empty($link['enabled']))
                continue;

            $keyword = $link['keyword'];
            $url = $link['url'];
            $max = intval($link['max_links'] ?? 1);

            $attrs = 'href="' . esc_url($url) . '"';
            $attrs .= ' class="uam-manual-link"';
            if (!empty($link['title']))
                $attrs .= ' title="' . esc_attr($link['title']) . '"';
            if (!empty($link['nofollow']))
                $attrs .= ' rel="nofollow noopener"';
            if (!empty($link['new_tab']))
                $attrs .= ' target="_blank"';

            $replacement = '<a ' . $attrs . '>' . esc_html($keyword) . '</a>';
            $escaped = preg_quote($keyword, '/');

            $content = preg_replace(
                '/(?<!["\'>\/])(\b' . $escaped . '\b)(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)/i',
                $replacement,
                $content,
                $max
            );
        }

        return $content;
    }

    // ─── Scan Post for Link Stats ────────────────────────────────────────────

    /**
     * Analyze a post's content for internal/external link counts.
     */
    public static function scan_post_links($post_id)
    {
        $post = get_post($post_id);
        if (!$post)
            return [];

        $content = $post->post_content;
        $site_url = home_url();

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);

        $internal = [];
        $external = [];
        $nofollow_count = 0;

        foreach ($matches as $m) {
            $href = $m[1];
            $anchor = wp_strip_all_tags($m[2]);
            $full_tag = $m[0];

            $has_nofollow = (bool) preg_match('/rel=["\'][^"\']*nofollow/i', $full_tag);
            $has_new_tab = (bool) preg_match('/target=["\']_blank/i', $full_tag);

            if ($has_nofollow)
                $nofollow_count++;

            $link = [
                'url' => $href,
                'anchor' => $anchor,
                'nofollow' => $has_nofollow,
                'new_tab' => $has_new_tab,
            ];

            if (strpos($href, $site_url) === 0 || (strpos($href, '/') === 0 && strpos($href, '//') !== 0)) {
                $linked_id = url_to_postid($href);
                $link['post_id'] = $linked_id;
                $link['post_title'] = $linked_id ? get_the_title($linked_id) : '';
                $internal[] = $link;
            } else {
                $external[] = $link;
            }
        }

        return [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post_id),
            'internal_count' => count($internal),
            'external_count' => count($external),
            'nofollow_count' => $nofollow_count,
            'internal_links' => $internal,
            'external_links' => $external,
            'word_count' => str_word_count(wp_strip_all_tags($content)),
        ];
    }

    // ─── Scan All Posts ──────────────────────────────────────────────────────

    /**
     * Scan all published posts for link statistics.
     */
    public static function scan_all_posts()
    {
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $results = [];
        $orphans = [];
        $all_linked_ids = [];

        foreach ($posts as $pid) {
            $scan = self::scan_post_links($pid);
            $results[] = $scan;

            foreach ($scan['internal_links'] as $link) {
                if (!empty($link['post_id'])) {
                    $all_linked_ids[] = $link['post_id'];
                }
            }
        }

        // Find orphan posts
        $all_linked_ids = array_unique($all_linked_ids);
        foreach ($posts as $pid) {
            if (!in_array($pid, $all_linked_ids)) {
                $orphans[] = [
                    'id' => $pid,
                    'title' => get_the_title($pid),
                    'url' => get_permalink($pid),
                ];
            }
        }

        // Calculate stats
        $total_internal = array_sum(array_column($results, 'internal_count'));
        $total_external = array_sum(array_column($results, 'external_count'));
        $total_nofollow = array_sum(array_column($results, 'nofollow_count'));
        $posts_no_links = count(array_filter($results, function ($r) {
            return $r['internal_count'] === 0;
        }));

        return [
            'posts' => $results,
            'orphans' => $orphans,
            'stats' => [
                'total_posts' => count($posts),
                'total_internal' => $total_internal,
                'total_external' => $total_external,
                'total_nofollow' => $total_nofollow,
                'orphan_count' => count($orphans),
                'no_links_count' => $posts_no_links,
                'avg_internal' => count($posts) ? round($total_internal / count($posts), 1) : 0,
            ],
        ];
    }

    // ─── Insert Link into Post Content ───────────────────────────────────────

    /**
     * Insert an internal or external link into a post's content.
     *
     * @param int    $post_id    Source post ID.
     * @param string $anchor     Anchor text to find in content.
     * @param string $target_url URL to link to.
     * @param array  $options    Optional link attributes (nofollow, new_tab, title).
     * @return bool|WP_Error
     */
    public static function insert_link($post_id, $anchor, $target_url, $options = [])
    {
        $post = get_post($post_id);
        if (!$post)
            return new WP_Error('no_post', 'Post not found.');

        $content = $post->post_content;

        if (stripos($content, $anchor) === false)
            return new WP_Error('anchor_not_found', "Anchor text \"{$anchor}\" not found in post content.");

        // Don't link if already inside an <a> tag
        $pattern = '/<a\s[^>]*>' . preg_quote($anchor, '/') . '<\/a>/is';
        if (preg_match($pattern, $content))
            return new WP_Error('already_linked', "Anchor text \"{$anchor}\" is already linked.");

        // Build link attributes
        $attrs = 'href="' . esc_url($target_url) . '"';
        if (!empty($options['title']))
            $attrs .= ' title="' . esc_attr($options['title']) . '"';
        if (!empty($options['nofollow']))
            $attrs .= ' rel="nofollow noopener noreferrer"';
        if (!empty($options['new_tab']))
            $attrs .= ' target="_blank"';

        $replacement = '<a ' . $attrs . '>' . $anchor . '</a>';
        $escaped_anchor = preg_quote($anchor, '/');

        $new_content = preg_replace(
            '/(?<!["\'>])(' . $escaped_anchor . ')(?![^<]*<\/a>)/i',
            $replacement,
            $content,
            1
        );

        if ($new_content === $content)
            return new WP_Error('no_change', 'Could not insert link — anchor text may be inside HTML tags.');

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content,
        ], true);

        return is_wp_error($updated) ? $updated : true;
    }

    // ─── Auto-Link Rules ─────────────────────────────────────────────────────

    /**
     * Get all auto-link rules.
     */
    public static function get_autolink_rules()
    {
        return get_option(self::OPTION_AUTOLINK_RULES, []);
    }

    /**
     * Save auto-link rules.
     */
    public static function save_autolink_rules($rules)
    {
        $clean = [];
        foreach ($rules as $rule) {
            if (!empty($rule['keyword']) && !empty($rule['url'])) {
                $clean[] = [
                    'keyword' => sanitize_text_field($rule['keyword']),
                    'url' => esc_url_raw($rule['url']),
                    'max_links' => intval($rule['max_links'] ?? 1),
                    'type' => in_array($rule['type'] ?? '', ['internal', 'external']) ? $rule['type'] : 'internal',
                    'nofollow' => !empty($rule['nofollow']),
                    'new_tab' => !empty($rule['new_tab']),
                ];
            }
        }
        return update_option(self::OPTION_AUTOLINK_RULES, $clean);
    }

    /**
     * Apply auto-link rules to post content (frontend filter).
     */
    public static function apply_autolinks($content)
    {
        $rules = self::get_autolink_rules();
        if (empty($rules))
            return $content;

        foreach ($rules as $rule) {
            $keyword = $rule['keyword'];
            $url = $rule['url'];
            $max = intval($rule['max_links'] ?? 1);

            $attrs = 'href="' . esc_url($url) . '" class="uam-autolink"';
            if (!empty($rule['nofollow']))
                $attrs .= ' rel="nofollow noopener"';
            if (!empty($rule['new_tab']))
                $attrs .= ' target="_blank"';

            $escaped = preg_quote($keyword, '/');
            $link = '<a ' . $attrs . '>' . esc_html($keyword) . '</a>';

            $content = preg_replace(
                '/(?<!["\'>\/])(\b' . $escaped . '\b)(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)/i',
                $link,
                $content,
                $max
            );
        }

        return $content;
    }

    /**
     * Register the auto-link and manual-link content filters.
     */
    public static function register_autolink_filter()
    {
        add_filter('the_content', [__CLASS__, 'apply_autolinks'], 99);
        add_filter('the_content', [__CLASS__, 'apply_manual_links'], 100);
    }
}
