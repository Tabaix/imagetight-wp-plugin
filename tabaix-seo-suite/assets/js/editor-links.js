/**
 * UAM Editor Links — Internal link suggestions inside the post editor.
 * Works with both Gutenberg (Block Editor) and Classic Editor.
 */
/* global jQuery, uamEditorLinks, wp */
(function ($) {
    'use strict';

    const cfg = window.uamEditorLinks || {};
    const ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = cfg.nonce || '';
    const postId = cfg.postId || 0;

    // ── Get editor content (supports both Gutenberg and Classic) ─────────
    function getEditorContent() {
        // Try Gutenberg first
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            const content = wp.data.select('core/editor').getEditedPostContent();
            if (content) return content;
        }
        // Try Classic Editor (TinyMCE)
        if (window.tinymce) {
            const editor = tinymce.get('content');
            if (editor) return editor.getContent();
        }
        // Fallback: textarea
        const $textarea = $('#content');
        if ($textarea.length) return $textarea.val();
        return '';
    }

    // ── Get post title ────────────────────────────────────────────────────
    function getPostTitle() {
        // Gutenberg
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            const title = wp.data.select('core/editor').getEditedPostAttribute('title');
            if (title) return title;
        }
        // Classic
        const $title = $('#title');
        if ($title.length) return $title.val();
        return '';
    }

    // ── Insert link into editor content ───────────────────────────────────
    function insertLinkIntoEditor(anchorText, targetUrl) {
        const content = getEditorContent();
        if (!content) return false;

        // Check if anchor text exists in content
        if (content.indexOf(anchorText) === -1) return false;

        // Check if already linked
        const linkedPattern = new RegExp('<a\\s[^>]*>[^<]*' + escapeRegex(anchorText) + '[^<]*</a>', 'i');
        if (linkedPattern.test(content)) return false;

        // Replace first occurrence (not inside tags)
        const linkHtml = '<a href="' + targetUrl + '">' + anchorText + '</a>';
        const escaped = escapeRegex(anchorText);
        // Avoid replacing inside existing HTML tags
        const regex = new RegExp('(?<!["\'>])(' + escaped + ')(?![^<]*</a>)', 'i');
        const newContent = content.replace(regex, linkHtml);

        if (newContent === content) return false;

        // Apply to editor
        if (window.wp && wp.data && wp.data.dispatch('core/block-editor')) {
            // Gutenberg: we need to update blocks
            // Parse the new content into blocks
            const blocks = wp.blocks.parse(newContent);
            wp.data.dispatch('core/block-editor').resetBlocks(blocks);
            return true;
        }

        if (window.tinymce) {
            const editor = tinymce.get('content');
            if (editor) {
                editor.setContent(newContent);
                editor.undoManager.add();
                return true;
            }
        }

        // Fallback: textarea
        const $textarea = $('#content');
        if ($textarea.length) {
            $textarea.val(newContent);
            return true;
        }

        return false;
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ── Status helpers ────────────────────────────────────────────────────
    function setStatus(text, type) {
        const $el = $('#uam-el-status');
        $el.removeClass('loading success error').addClass(type || '');
        if (type === 'loading') {
            $el.html('<span class="uam-el-spinner"></span> ' + text);
        } else {
            $el.text(text);
        }
    }

    // ── Scan for suggestions ──────────────────────────────────────────────
    function scanContent(useDraft) {
        const content = useDraft ? getEditorContent() : null;
        const title = getPostTitle();

        if (useDraft && (!content || content.length < 50)) {
            setStatus('Content is too short. Write more before scanning.', 'error');
            return;
        }

        setStatus('AI is analyzing your content...', 'loading');
        $('#uam-el-results').html('');

        const data = {
            action: 'uam_analyze_draft',
            nonce: nonce,
            post_id: postId,
            title: title,
        };

        if (useDraft) {
            data.content = content;
        } else {
            // Use saved post content
            data.content = getEditorContent();
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: data,
            success: function (res) {
                if (res.success) {
                    renderSuggestions(res.data);
                } else {
                    setStatus(res.data?.message || 'Analysis failed.', 'error');
                }
            },
            error: function () {
                setStatus('Request failed. Check your connection.', 'error');
            },
        });
    }

    // ── Render suggestions ────────────────────────────────────────────────
    function renderSuggestions(data) {
        const suggestions = data.suggestions || [];
        const stats = data.stats || {};

        // Update stats
        $('#uam-el-internal-count').text(stats.internal_count || 0);
        $('#uam-el-external-count').text(stats.external_count || 0);
        $('#uam-el-word-count').text(stats.word_count || 0);
        $('#uam-el-stats').slideDown(150);

        if (suggestions.length === 0) {
            setStatus('', '');
            $('#uam-el-results').html(
                '<div class="uam-el-empty">' +
                '<span class="dashicons dashicons-yes-alt"></span>' +
                'Great! No additional link suggestions for this content.' +
                '</div>'
            );
            return;
        }

        setStatus('Found ' + suggestions.length + ' suggestion' + (suggestions.length > 1 ? 's' : '') + '!', 'success');

        let html = '';
        suggestions.forEach(function (s, i) {
            const priority = (s.priority || 'medium').toLowerCase();
            html += '<div class="uam-el-suggestion priority-' + priority + '">';
            html += '<span class="uam-el-priority ' + priority + '">' + priority + '</span>';
            html += '<span class="uam-el-anchor">"' + escHtml(s.anchor_text || '') + '"</span>';
            html += '<div class="uam-el-target">→ <a href="' + escHtml(s.target_url || '#') + '" target="_blank">' + escHtml(s.target_title || 'Post #' + s.target_post_id) + '</a></div>';
            if (s.reason) {
                html += '<div class="uam-el-reason">' + escHtml(s.reason) + '</div>';
            }
            html += '<button type="button" class="uam-el-insert-btn" data-index="' + i + '" data-anchor="' + escHtml(s.anchor_text || '') + '" data-url="' + escHtml(s.target_url || '') + '">✅ Insert Link</button>';
            html += '</div>';
        });

        html += '<div class="uam-el-tip"><strong>💡 Tip:</strong> Click "Insert Link" to add the link directly to your content. The link will be added to the first matching phrase.</div>';

        $('#uam-el-results').html(html);
    }

    // ── Event handlers ────────────────────────────────────────────────────
    $(document).on('click', '#uam-el-scan', function () {
        scanContent(false);
    });

    $(document).on('click', '#uam-el-scan-draft', function () {
        scanContent(true);
    });

    // Insert link into editor
    $(document).on('click', '.uam-el-insert-btn', function () {
        const $btn = $(this);
        if ($btn.hasClass('inserted') || $btn.hasClass('failed')) return;

        const anchor = $btn.data('anchor');
        const url = $btn.data('url');

        if (!anchor || !url) {
            $btn.addClass('failed').text('❌ Missing data');
            return;
        }

        const success = insertLinkIntoEditor(anchor, url);
        if (success) {
            $btn.addClass('inserted').text('✅ Inserted!');
        } else {
            // Fallback: try via AJAX (server-side insert for saved posts)
            if (postId) {
                $btn.text('Inserting…').prop('disabled', true);
                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'uam_insert_link',
                        nonce: nonce,
                        post_id: postId,
                        anchor: anchor,
                        target_url: url,
                    },
                    success: function (res) {
                        if (res.success) {
                            $btn.addClass('inserted').text('✅ Inserted (saved)!');
                            // Reload editor content
                            setTimeout(function () {
                                if (window.wp && wp.data) {
                                    wp.data.dispatch('core/editor').refreshPost();
                                }
                            }, 500);
                        } else {
                            $btn.addClass('failed').text('❌ ' + (res.data?.message || 'Failed'));
                        }
                    },
                    error: function () {
                        $btn.addClass('failed').text('❌ Request failed');
                    },
                });
            } else {
                $btn.addClass('failed').text('❌ Phrase not found');
            }
        }
    });

})(jQuery);
