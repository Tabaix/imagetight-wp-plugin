/**
 * Ultimate AI Master — Admin JavaScript
 * Handles all AJAX calls and dynamic UI interactions.
 */
/* global uamAdmin, jQuery */
(function ($) {
  'use strict';

  const UAM = {
    ajaxUrl: (window.uamAdmin || {}).ajaxUrl || '/wp-admin/admin-ajax.php',
    nonce: (window.uamAdmin || {}).nonce || '',
    provider: (window.uamAdmin || {}).provider || 'gemini',

    // ── Core AJAX helper ──────────────────────────────────────────────────
    ajax(action, data, successCb, errorCb) {
      return $.ajax({
        url: this.ajaxUrl,
        method: 'POST',
        data: Object.assign({ action, nonce: this.nonce }, data),
        success(res) {
          if (res.success) {
            successCb(res.data);
          } else {
            const msg = res.data?.message || 'Something went wrong. Check your API key in Settings.';
            if (errorCb) errorCb(msg); else UAM.showError(msg);
          }
        },
        error(xhr) {
          const msg = 'Request failed: ' + (xhr.statusText || 'Network error');
          if (errorCb) errorCb(msg); else UAM.showError(msg);
        },
      });
    },

    // ── UI Helpers ────────────────────────────────────────────────────────
    setLoading($btn, loading) {
      if (loading) { $btn.addClass('uam-loading').prop('disabled', true); }
      else { $btn.removeClass('uam-loading').prop('disabled', false); }
    },

    showLoader($el) {
      $el.html('<div class="uam-loader"><div class="uam-spinner"></div> Generating…</div>');
    },

    showError(msg, $el) {
      const html = `<div class="uam-notice uam-notice-error">⚠️ ${this.escHtml(msg)}</div>`;
      if ($el) $el.html(html); else console.error('[UAM]', msg);
    },

    escHtml(str) {
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    },

    // ── Render markdown-like text ─────────────────────────────────────────
    renderMarkdown(text) {
      if (!text) return '';
      return text
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^---$/gm, '<hr>')
        .replace(/^\* (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/^(.+)$/gm, (line) => {
          if (/^<[hHulpHr]/.test(line.trim())) return line;
          return line;
        });
    },

    // ── Score bar renderer ────────────────────────────────────────────────
    renderScoreBar(label, score, max = 100) {
      const pct = Math.round((score / max) * 100);
      const color = pct >= 75 ? '#10b981' : pct >= 45 ? '#f59e0b' : '#ef4444';
      return `
        <div class="uam-score-bar-wrap">
          <span style="font-size:12px;font-weight:600;min-width:130px;color:var(--uam-text2)">${this.escHtml(label)}</span>
          <div class="uam-score-bar-bg">
            <div class="uam-score-bar" style="width:${pct}%;background:${color}"></div>
          </div>
          <span class="uam-score-label">${score}${max === 100 ? '%' : ''}</span>
        </div>`;
    },

    // ── Keyword chips ─────────────────────────────────────────────────────
    renderChips(items, color = 'var(--uam-accent)') {
      if (!Array.isArray(items) || !items.length) return '<em style="color:var(--uam-text3)">None</em>';
      return items.map(i =>
        `<span style="display:inline-block;background:rgba(99,102,241,.12);color:${color};border:1px solid rgba(99,102,241,.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;margin:3px 2px">${this.escHtml(i)}</span>`
      ).join('');
    },

    // ── List renderer ─────────────────────────────────────────────────────
    renderList(items, icon = '→') {
      if (!Array.isArray(items) || !items.length) return '<em style="color:var(--uam-text3)">None</em>';
      return `<ul class="uam-list-items">${items.map(i => `<li>${icon} ${this.escHtml(i)}</li>`).join('')}</ul>`;
    },
    showToast(msg, type = 'success') {
      const isError = type === 'error';
      const bg = isError ? 'linear-gradient(135deg,#ef4444,#dc2626)' : 'linear-gradient(135deg,#6366f1,#8b5cf6)';
      const toast = $(`<div style="position:fixed;top:28px;right:28px;z-index:999999;background:${bg};color:#fff;padding:12px 20px;border-radius:10px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.3)">
        ${isError ? '⚠️' : '✓'} ${msg}
      </div>`).appendTo('body');
      setTimeout(() => toast.fadeOut(400, function () { $(this).remove(); }), 3000);
    }
  };

  // ════════════════════════════════════════════════════════
  // 1. TABS
  // ════════════════════════════════════════════════════════
  $(document).on('click', '.uam-tab', function () {
    const $tabs = $(this).closest('.uam-tabs-nav');
    const $panes = $tabs.next('.uam-tab-pane').parent().find('.uam-tab-pane');
    $tabs.find('.uam-tab').removeClass('active');
    $(this).addClass('active');
    $panes.removeClass('active');
    $('#tab-' + $(this).data('tab')).addClass('active');
  });

  // ════════════════════════════════════════════════════════
  // 2. PROVIDER QUICK SWITCH
  // ════════════════════════════════════════════════════════
  $(document).on('click', '.uam-provider-pill', function () {
    const provider = $(this).data('provider');
    const $btn = $(this);
    UAM.setLoading($btn, true);
    UAM.ajax('uam_quick_switch_provider', { provider }, function (data) {
      UAM.setLoading($btn, false);
      UAM.provider = provider;
      $('.uam-provider-pill').removeClass('active');
      $(`[data-provider="${provider}"]`).addClass('active');
      const toast = $(`<div style="position:fixed;top:28px;right:28px;z-index:99999;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:12px 20px;border-radius:10px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 28px rgba(99,102,241,.45)">
        ✓ Switched to ${data.provider === 'openai' ? 'OpenAI GPT' : 'Google Gemini'}
      </div>`).appendTo('body');
      setTimeout(() => toast.fadeOut(400, function () { $(this).remove(); }), 2500);
    }, function () { UAM.setLoading($btn, false); });
  });

  // Settings page provider toggle
  $(document).on('change', 'input[name="provider"]', function () {
    const val = $(this).val();
    $('.uam-provider-opt').removeClass('active');
    $(this).closest('.uam-provider-opt').addClass('active');
  });

  // Toggle password visibility
  $(document).on('click', '.uam-toggle-password', function () {
    const target = $(this).data('target');
    const $input = $(`[name="${target}"]`);
    $input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
  });

  // ════════════════════════════════════════════════════════
  // 3. CONTENT GENERATOR
  // ════════════════════════════════════════════════════════

  // Populate title when post is selected
  $(document).on('change', '#img-post-id', function () {
    const $opt = $(this).find(':selected');
    if ($opt.val() !== '0') {
      $('#img-title').val($opt.text());
    }
  });

  // Copy button
  $(document).on('click', '#btn-copy-content, #btn-copy-product', function () {
    const target = $(this).attr('id') === 'btn-copy-content' ? '#content-result' : '#product-result';
    const text = $(target).text();
    navigator.clipboard.writeText(text).then(() => {
      $(this).text('✓ Copied!');
      setTimeout(() => $(this).text('📋 Copy'), 2000);
    });
  });

  // Blog Post: Outline
  $(document).on('click', '#btn-outline', function () {
    const $btn = $(this), $out = $('#content-result');
    const topic = $('#bp-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_outline', { topic, keywords: $('#bp-keywords').val() }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`<div class="uam-result-area">${UAM.renderMarkdown(d.result)}</div>`);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Blog Post: Intro
  $(document).on('click', '#btn-intro', function () {
    const $btn = $(this), $out = $('#content-result');
    const topic = $('#bp-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_intro', { topic, keywords: $('#bp-keywords').val() }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`<p style="line-height:1.8;color:var(--uam-text)">${UAM.escHtml(d.result)}</p>`);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Blog Post: Conclusion
  $(document).on('click', '#btn-conclusion', function () {
    const $btn = $(this), $out = $('#content-result');
    const topic = $('#bp-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_conclusion', { topic, main_points: '' }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`<p style="line-height:1.8;color:var(--uam-text)">${UAM.escHtml(d.result)}</p>`);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Blog Post: Full Post
  $(document).on('click', '#btn-full-post', function () {
    const $btn = $(this), $out = $('#content-result');
    const topic = $('#bp-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_full_post', {
      topic,
      keywords: $('#bp-keywords').val(),
      word_count: $('#bp-wordcount').val(),
    }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(UAM.renderMarkdown(d.result));
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Product Description
  $(document).on('click', '#btn-product-desc', function () {
    const $btn = $(this), $out = $('#product-result');
    const name = $('#prod-name').val().trim();
    if (!name) return $out.html('<div class="uam-notice uam-notice-error">Please enter a product name.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_product_desc', {
      product_name: name,
      features: $('#prod-features').val(),
      audience: $('#prod-audience').val(),
    }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`<div style="line-height:1.8">${d.result}</div>`);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Social Media Posts
  $(document).on('click', '#btn-social', function () {
    const $btn = $(this), $out = $('#social-result');
    const topic = $('#social-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    $out.html('<div class="uam-loader"><div class="uam-spinner"></div> Crafting posts…</div>');
    UAM.ajax('uam_generate_social', { topic, url: $('#social-url').val() }, function (d) {
      UAM.setLoading($btn, false);
      const icons = { twitter: '𝕏', linkedin: 'in', facebook: 'f', instagram: '📸' };
      let html = '';
      const platforms = ['twitter', 'linkedin', 'facebook', 'instagram'];
      platforms.forEach(p => {
        if (d[p]) {
          html += `<div class="uam-social-card">
            <div class="uam-social-card-header">
              <span class="uam-social-platform">${icons[p] || ''} ${p.charAt(0).toUpperCase() + p.slice(1)}</span>
              <button class="uam-btn uam-btn-ghost uam-btn-sm uam-copy-social" data-platform="${p}">📋 Copy</button>
            </div>
            <div class="uam-social-text" id="social-${p}">${UAM.escHtml(d[p])}</div>
          </div>`;
        }
      });
      $out.html(html || '<div class="uam-notice uam-notice-error">No posts generated.</div>');
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Copy individual social post
  $(document).on('click', '.uam-copy-social', function () {
    const p = $(this).data('platform');
    const text = $(`#social-${p}`).text();
    navigator.clipboard.writeText(text).then(() => {
      $(this).text('✓ Copied!');
      setTimeout(() => $(this).text('📋 Copy'), 2000);
    });
  });

  // Email Generator
  $(document).on('click', '#btn-email', function () {
    const $btn = $(this), $out = $('#email-result');
    const topic = $('#email-topic').val().trim();
    if (!topic) return $out.html('<div class="uam-notice uam-notice-error">Please enter a topic.</div>');
    UAM.setLoading($btn, true);
    $out.html('<div class="uam-loader"><div class="uam-spinner"></div> Drafting email…</div>');
    UAM.ajax('uam_generate_email', {
      email_type: $('#email-type').val(),
      topic,
      brand: $('#email-brand').val(),
    }, function (d) {
      UAM.setLoading($btn, false);
      const fields = [
        ['Subject Line', d.subject],
        ['Preheader', d.preheader],
        ['Greeting', d.greeting],
        ['Body', d.body],
        ['CTA Button', d.cta],
        ['P.S.', d.ps],
      ];
      let html = '';
      fields.forEach(([label, val]) => {
        if (val) html += `<div class="uam-email-field">
          <div class="uam-email-field-label">${label}</div>
          <div class="uam-email-field-value">${UAM.escHtml(val)}</div>
        </div>`;
      });
      $out.html(html || '<div class="uam-notice uam-notice-error">Could not parse email response.</div>');
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // ════════════════════════════════════════════════════════
  // 4. SEO & OPTIMIZER
  // ════════════════════════════════════════════════════════

  // Readability
  $(document).on('click', '#btn-readability', function () {
    const $btn = $(this), $out = $('#readability-result');
    const content = $('#read-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_analyze_readability', { content }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="margin-bottom:16px">
          ${UAM.renderScoreBar('Readability Score', d.score || 0)}
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
          <div><strong style="color:var(--uam-text2);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Grade Level</strong><br>
            <span style="font-size:22px;font-weight:800;color:var(--uam-accent)">${UAM.escHtml(d.grade_level || 'N/A')}</span></div>
          <div><strong style="color:var(--uam-text2);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Avg Sentence Length</strong><br>
            <span style="font-size:22px;font-weight:800;color:var(--uam-accent)">${d.avg_sentence_length || 0}</span> words</div>
        </div>
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">Summary</strong><br>
          <p style="margin:6px 0;line-height:1.6">${UAM.escHtml(d.summary || '')}</p></div>
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">Issues Found</strong><br>${UAM.renderList(d.issues)}</div>
        <div><strong style="color:var(--uam-text2);font-size:12px">Suggestions</strong><br>${UAM.renderList(d.suggestions, '💡')}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Keywords
  $(document).on('click', '#btn-keywords', function () {
    const $btn = $(this), $out = $('#keywords-result');
    const content = $('#kw-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_analyze_keywords', { content, focus_keyword: $('#kw-focus').val() }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
          <div style="text-align:center;padding:14px;background:rgba(99,102,241,.08);border-radius:10px">
            <div style="font-size:26px;font-weight:800;background:linear-gradient(135deg,#6366f1,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent">${d.focus_keyword_density ?? 'N/A'}${d.focus_keyword_density != null ? '%' : ''}</div>
            <div style="font-size:11px;color:var(--uam-text2);text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Keyword Density</div>
          </div>
          <div style="text-align:center;padding:14px;background:rgba(99,102,241,.08);border-radius:10px">
            <div style="font-size:26px;font-weight:800;background:linear-gradient(135deg,#6366f1,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent">${d.focus_keyword_count ?? 'N/A'}</div>
            <div style="font-size:11px;color:var(--uam-text2);text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Keyword Count</div>
          </div>
        </div>
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">Recommended Keywords</strong><br>${UAM.renderChips(d.recommended_keywords)}</div>
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">LSI Keywords</strong><br>${UAM.renderChips(d.lsi_keywords, 'var(--uam-accent3)')}</div>
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">Keyword Gaps</strong><br>${UAM.renderChips(d.keyword_gaps, 'var(--uam-amber)')}</div>
        <div><strong style="color:var(--uam-text2);font-size:12px">Placement Tips</strong><br>${UAM.renderList(d.placement_suggestions, '📍')}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Originality
  $(document).on('click', '#btn-originality', function () {
    const $btn = $(this), $out = $('#originality-result');
    const content = $('#orig-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_check_originality', { content }, function (d) {
      UAM.setLoading($btn, false);
      const scoreColor = d.originality_score >= 75 ? '#10b981' : d.originality_score >= 45 ? '#f59e0b' : '#ef4444';
      const badgeMap = { unique: 'approve', generic: 'hold', 'likely-duplicate': 'spam' };
      $out.html(`
        <div style="text-align:center;padding:20px 0">
          <div style="font-size:52px;font-weight:900;color:${scoreColor}">${d.originality_score || 0}<small style="font-size:22px">%</small></div>
          <div style="font-size:12px;color:var(--uam-text2);margin-top:4px">Originality Score</div>
          <span class="uam-badge uam-badge-${badgeMap[d.assessment] || 'hold'}" style="margin-top:10px">${d.assessment || 'N/A'}</span>
        </div>
        ${UAM.renderScoreBar('Score', d.originality_score || 0)}
        <div style="margin-bottom:12px"><strong style="color:var(--uam-text2);font-size:12px">Generic Phrases Detected</strong><br>${UAM.renderChips(d.generic_phrases, 'var(--uam-amber)')}</div>
        <div><strong style="color:var(--uam-text2);font-size:12px">Rewrite Suggestions</strong><br>${UAM.renderList(d.rewrite_suggestions, '✏️')}</div>
        <p style="margin-top:14px;line-height:1.6;color:var(--uam-text2)">${UAM.escHtml(d.summary || '')}</p>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Placeholder for any other 3. POST ANALYSIS handlers...


  $(document).on('click', '#btn-bp-set-featured', function () {
    const attach_id = $(this).data('attach-id');
    if (!attach_id) return UAM.showToast('Please save image to library first.', 'error');
    // We don't have a post ID yet in the generator page unless it's an existing post.
    // For now, let's assume this is for a new post or we can't set it yet.
    // Wait, the content generator page doesn't know which post we are writing.
    UAM.showToast('Post ID not found. Use this after saving the post as draft.', 'error');
  });

  $(document).on('click', '#btn-bp-insert-image', function () {
    const src = $('#bp-img-preview img').attr('src');
    if (src) {
      navigator.clipboard.writeText(src);
      UAM.showToast('Image URL copied to clipboard!');
    }
  });

  // Grammar Fix
  $(document).on('click', '#btn-fix-grammar', function () {
    const $btn = $(this), $out = $('#grammar-result');
    const content = $('#gram-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_fix_grammar', { content }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="display:flex;gap:8px;margin-bottom:12px">
          <button class="uam-btn uam-btn-ghost uam-btn-sm" id="copy-corrected">📋 Copy Corrected</button>
          <button class="uam-btn uam-btn-secondary uam-btn-sm" id="apply-corrected">⬆️ Apply to Editor</button>
        </div>
        <div id="corrected-text" style="line-height:1.8;white-space:pre-wrap;color:var(--uam-text)">${UAM.escHtml(d.result)}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Copy corrected text
  $(document).on('click', '#copy-corrected', function () {
    const text = $('#corrected-text').text();
    navigator.clipboard.writeText(text).then(() => {
      $(this).text('✓ Copied!');
      setTimeout(() => $(this).text('📋 Copy Corrected'), 2000);
    });
  });

  // Grammar Report
  $(document).on('click', '#btn-grammar-report', function () {
    const $btn = $(this), $out = $('#grammar-result');
    const content = $('#gram-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_grammar_report', { content }, function (d) {
      UAM.setLoading($btn, false);
      let errHtml = '';
      if (d.errors && d.errors.length) {
        errHtml = d.errors.map(e => `
          <div style="padding:10px 12px;border:1px solid var(--uam-border2);border-radius:8px;margin-bottom:8px">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">
              <span class="uam-badge uam-badge-${e.type === 'Grammar' ? 'spam' : e.type === 'Spelling' ? 'hold' : 'pending'}">${e.type}</span>
            </div>
            <div style="font-size:12px"><del style="color:var(--uam-red)">${UAM.escHtml(e.original)}</del> → <strong style="color:var(--uam-green)">${UAM.escHtml(e.correction)}</strong></div>
            <div style="font-size:11px;color:var(--uam-text2);margin-top:4px">${UAM.escHtml(e.explanation)}</div>
          </div>`).join('');
      }
      const qualityColor = { excellent: '#10b981', good: '#06b6d4', fair: '#f59e0b', poor: '#ef4444' };
      $out.html(`
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--uam-border2)">
          <div style="text-align:center">
            <div style="font-size:36px;font-weight:800;color:${qualityColor[d.overall_quality] || 'var(--uam-text)'}">${d.error_count || 0}</div>
            <div style="font-size:11px;color:var(--uam-text2)">Errors Found</div>
          </div>
          <span class="uam-badge ${d.overall_quality === 'excellent' ? 'uam-badge-approve' : d.overall_quality === 'poor' ? 'uam-badge-spam' : 'uam-badge-pending'}">${d.overall_quality || 'N/A'} Quality</span>
        </div>
        ${errHtml || '<div class="uam-empty">✅ No errors found!</div>'}
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Sentiment
  $(document).on('click', '#btn-sentiment', function () {
    const $btn = $(this), $out = $('#sentiment-result');
    const content = $('#sent-content').val().trim();
    if (!content) return UAM.showError('Please enter content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_analyze_sentiment', { content }, function (d) {
      UAM.setLoading($btn, false);
      const badge = { positive: 'approve', neutral: 'hold', negative: 'spam' };
      const emotions = d.emotions || {};
      const emotionBars = Object.entries(emotions).map(([em, val]) =>
        UAM.renderScoreBar(em.charAt(0).toUpperCase() + em.slice(1), val)
      ).join('');
      $out.html(`
        <div style="text-align:center;padding:16px 0;margin-bottom:16px;border-bottom:1px solid var(--uam-border2)">
          <span class="uam-badge uam-badge-${badge[d.overall_sentiment] || 'hold'}" style="font-size:14px;padding:6px 20px">${d.overall_sentiment || 'N/A'}</span>
          <div style="font-size:28px;font-weight:800;color:var(--uam-accent);margin:10px 0">${d.sentiment_score > 0 ? '+' : ''}${d.sentiment_score || 0}</div>
          <div style="font-size:12px;color:var(--uam-text2)">Sentiment Score (-100 to +100)</div>
        </div>
        <div style="margin-bottom:14px">
          <strong style="color:var(--uam-text2);font-size:12px">Tone</strong>
          <p style="margin:6px 0">${UAM.escHtml(d.tone || '')}</p>
        </div>
        <div style="margin-bottom:14px">
          <strong style="color:var(--uam-text2);font-size:12px">Emotional Breakdown</strong>
          <div style="margin-top:10px">${emotionBars}</div>
        </div>
        <div style="margin-bottom:14px">
          <strong style="color:var(--uam-text2);font-size:12px">Predicted Audience Reaction</strong>
          <p style="margin:6px 0;line-height:1.6">${UAM.escHtml(d.audience_reaction || '')}</p>
        </div>
        <div><strong style="color:var(--uam-text2);font-size:12px">Suggestions</strong><br>${UAM.renderList(d.suggestions, '💡')}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Performance Prediction
  $(document).on('click', '#btn-performance', function () {
    const $btn = $(this), $out = $('#performance-result');
    const title = $('#perf-title').val().trim();
    const content = $('#perf-content').val().trim();
    if (!title || !content) return UAM.showError('Please enter a title and content.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_predict_performance', { title, content, niche: $('#perf-niche').val() }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px">
          ${[['Overall Score', d.predicted_score], ['SEO Score', d.seo_score], ['Engagement', d.engagement_score]].map(([label, val]) => `
            <div style="text-align:center;padding:14px;background:rgba(99,102,241,.08);border-radius:10px">
              <div style="font-size:28px;font-weight:800;color:var(--uam-accent)">${val || 0}<small style="font-size:14px">%</small></div>
              <div style="font-size:11px;color:var(--uam-text2);text-transform:uppercase;letter-spacing:.5px;margin-top:4px">${label}</div>
            </div>`).join('')}
        </div>
        <div style="margin-bottom:10px;display:flex;align-items:center;gap:10px">
          <strong style="color:var(--uam-text2);font-size:12px">Viral Potential:</strong>
          <span class="uam-badge ${d.viral_potential === 'high' ? 'uam-badge-approve' : d.viral_potential === 'medium' ? 'uam-badge-pending' : 'uam-badge-spam'}">${d.viral_potential || 'N/A'}</span>
        </div>
        ${UAM.renderScoreBar('Predicted Score', d.predicted_score || 0)}
        ${UAM.renderScoreBar('SEO Score', d.seo_score || 0)}
        ${UAM.renderScoreBar('Engagement', d.engagement_score || 0)}
        <div style="margin:14px 0 10px"><strong style="color:var(--uam-green);font-size:12px">✓ Strengths</strong><br>${UAM.renderList(d.strengths)}</div>
        <div style="margin-bottom:10px"><strong style="color:var(--uam-amber);font-size:12px">⚠ Weaknesses</strong><br>${UAM.renderList(d.weaknesses)}</div>
        <div><strong style="color:var(--uam-accent);font-size:12px">💡 Recommendations</strong><br>${UAM.renderList(d.recommendations, '💡')}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Meta & SEO
  $(document).on('click', '#btn-meta', function () {
    const $btn = $(this), $out = $('#meta-result');
    const title = $('#meta-title').val().trim();
    if (!title) return UAM.showError('Please enter a post title.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_meta', { title, content: $('#meta-content').val(), keyword: $('#meta-keyword').val() }, function (d) {
      UAM.setLoading($btn, false);
      const seoLen = (d.seo_title || '').length;
      const metaLen = (d.meta_description || '').length;
      const titleOk = seoLen >= 50 && seoLen <= 60;
      const metaOk = metaLen >= 150 && metaLen <= 160;
      $out.html(`
        <div style="margin-bottom:16px">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--uam-accent);margin-bottom:6px">SEO Title</div>
          <div style="font-size:16px;font-weight:700;color:#60a5fa;margin-bottom:4px">${UAM.escHtml(d.seo_title || '')}</div>
          <div style="font-size:11px;color:${titleOk ? 'var(--uam-green)' : 'var(--uam-amber)'}">${seoLen} chars ${titleOk ? '✓ Optimal' : '⚠ Adjust length'}</div>
        </div>
        <div style="margin-bottom:16px">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--uam-accent);margin-bottom:6px">Meta Description</div>
          <div style="font-size:13px;color:var(--uam-text2);line-height:1.6;margin-bottom:4px">${UAM.escHtml(d.meta_description || '')}</div>
          <div style="font-size:11px;color:${metaOk ? 'var(--uam-green)' : 'var(--uam-amber)'}">${metaLen} chars ${metaOk ? '✓ Optimal' : '⚠ Adjust length'}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--uam-accent);margin-bottom:6px">Focus Keyword</div>
          ${UAM.renderChips([d.focus_keyword || 'N/A'])}
        </div>
        <div style="margin-top:16px;padding:12px;background:rgba(0,0,0,.2);border-radius:8px">
          <div style="font-size:11px;color:var(--uam-text3);margin-bottom:8px">Google Search Preview</div>
          <div style="font-size:18px;color:#60a5fa;font-weight:500">${UAM.escHtml(d.seo_title || '')}</div>
          <div style="font-size:13px;color:#4ade80;margin:2px 0">example.com/your-post-url</div>
          <div style="font-size:13px;color:#94a3b8;line-height:1.5">${UAM.escHtml(d.meta_description || '')}</div>
        </div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // ════════════════════════════════════════════════════════
  // 5. IMAGE AI
  // ════════════════════════════════════════════════════════
  $(document).on('click', '#btn-gen-image', function () {
    const $btn = $(this), $area = $('#image-preview-area'), $actions = $('#image-actions');
    const title = $('#img-title').val().trim();
    if (!title) return alert('Please enter a post title.');
    UAM.setLoading($btn, true);
    $area.html('<div class="uam-loader" style="height:280px;flex-direction:column;gap:14px"><div class="uam-spinner" style="width:36px;height:36px;border-width:3px"></div><p style="margin:0;color:var(--uam-text2)">Generating your image…</p></div>');
    $actions.hide();
    UAM.ajax('uam_generate_image', {
      post_title: title,
      post_excerpt: $('#img-excerpt').val(),
      style: $('#img-style').val(),
      post_id: $('#img-post-id').val(),
      save_to_library: $('#img-save-library').is(':checked') ? 1 : 0,
    }, function (d) {
      UAM.setLoading($btn, false);
      const src = d.image_url;
      $area.html(`<img src="${src}" alt="AI Generated Image" style="width:100%;border-radius:10px">`);
      $actions.show();
      $('#btn-download-image').attr('href', src);
      if (d.attach_id) {
        $('#btn-set-featured').data('attach-id', d.attach_id).data('post-id', $('#img-post-id').val());
      }
    }, function (msg) {
      UAM.setLoading($btn, false);
      $area.html(`<div class="uam-notice uam-notice-error" style="margin:20px">⚠️ ${UAM.escHtml(msg)}</div>`);
    });
  });
  $(document).on('click', '#btn-set-featured', function () {
    const attach_id = $(this).data('attach-id');
    const post_id = $(this).data('post-id');
    if (!attach_id || post_id === '0') return UAM.showToast('Please save image to library and select a post first.', 'error');
    const $btn = $(this);
    UAM.setLoading($btn, true);
    UAM.ajax('uam_set_featured_image', { attach_id, post_id }, function (d) {
      UAM.setLoading($btn, false);
      UAM.showToast(d.message);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showToast(msg, 'error'); });
  });

  // Product Image Prompt
  $(document).on('click', '#btn-product-img', function () {
    const $btn = $(this), $out = $('#product-img-result');
    const product = $('#pi-product').val().trim();
    if (!product) return UAM.showError('Please enter a product name.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_product_image_prompt', {
      product_name: product,
      variant: $('#pi-variant').val(),
      style: $('#pi-style').val(),
    }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="background:rgba(99,102,241,.08);border:1px dashed var(--uam-border);border-radius:10px;padding:16px;line-height:1.7;font-size:13px">
          ${UAM.escHtml(d.result)}
        </div>
        <button class="uam-btn uam-btn-ghost uam-btn-sm" style="margin-top:10px" id="copy-img-prompt">📋 Copy Prompt</button>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  $(document).on('click', '#copy-img-prompt', function () {
    const text = $('#product-img-result div').text();
    navigator.clipboard.writeText(text).then(() => {
      $(this).text('✓ Copied!');
      setTimeout(() => $(this).text('📋 Copy Prompt'), 2000);
    });
  });

  // Optimization Tips
  $(document).on('click', '#btn-optimize', function () {
    const $btn = $(this), $out = $('#optimize-result');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_image_optimization_tips', {
      filename: $('#opt-filename').val(),
      file_size_kb: $('#opt-size').val(),
      dimensions: $('#opt-dimensions').val(),
    }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
          <div style="padding:14px;background:rgba(99,102,241,.08);border-radius:10px;text-align:center">
            <div style="font-size:26px;font-weight:800;color:var(--uam-green)">${d.recommended_size_kb || 'N/A'}<small style="font-size:14px">KB</small></div>
            <div style="font-size:11px;color:var(--uam-text2);margin-top:4px">Target File Size</div>
          </div>
          <div style="padding:14px;background:rgba(99,102,241,.08);border-radius:10px;text-align:center">
            <div style="font-size:26px;font-weight:800;color:var(--uam-accent)">${d.estimated_savings_percent || 0}<small style="font-size:14px">%</small></div>
            <div style="font-size:11px;color:var(--uam-text2);margin-top:4px">Estimated Savings</div>
          </div>
        </div>
        <div style="margin-bottom:12px">
          <strong style="color:var(--uam-text2);font-size:12px">Recommended Format</strong>
          ${UAM.renderChips([d.format_suggestion || 'webp'], 'var(--uam-green)')}
          <br><strong style="color:var(--uam-text2);font-size:12px">Recommended Dimensions</strong>
          ${UAM.renderChips([d.recommended_dimensions || 'N/A'])}
        </div>
        <div><strong style="color:var(--uam-text2);font-size:12px">Optimization Tips</strong><br>${UAM.renderList(d.optimization_tips, '⚡')}</div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // ════════════════════════════════════════════════════════
  // 6. COMMENT MODERATION
  // ════════════════════════════════════════════════════════
  $(document).on('click', '#btn-mod-comment', function () {
    const $btn = $(this), $out = $('#mod-result');
    const comment_text = $('#mod-comment').val().trim();
    if (!comment_text) return UAM.showError('Please enter a comment.', $out);
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_moderate_comment', { comment_text }, function (d) {
      UAM.setLoading($btn, false);
      const decBadge = { approve: 'approve', hold: 'hold', spam: 'spam', reject: 'spam' };
      $out.html(`
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <span class="uam-badge uam-badge-${decBadge[d.decision] || 'hold'}" style="font-size:14px;padding:6px 16px">${(d.decision || 'N/A').toUpperCase()}</span>
          <span style="font-size:12px;color:var(--uam-text2)">Confidence: <strong style="color:var(--uam-text)">${d.confidence || 0}%</strong></span>
        </div>
        ${UAM.renderScoreBar('Toxicity Score', d.toxicity_score || 0)}
        <div style="margin:14px 0 8px"><strong style="color:var(--uam-text2);font-size:12px">Reasons</strong><br>${UAM.renderList(d.reasons)}</div>
        <div style="margin-bottom:8px"><strong style="color:var(--uam-text2);font-size:12px">Spam Indicators</strong><br>${UAM.renderChips(d.spam_indicators, 'var(--uam-red)')}</div>
        <p style="font-size:12px;color:var(--uam-text2);margin-top:10px">${UAM.escHtml(d.summary || '')}</p>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Bulk Moderate
  $(document).on('click', '#btn-bulk-moderate', function () {
    const $btn = $(this);
    UAM.setLoading($btn, true);
    UAM.ajax('uam_bulk_moderate', { limit: 10 }, function (d) {
      UAM.setLoading($btn, false);
      const decBadge = { approve: 'approve', hold: 'hold', spam: 'spam', reject: 'spam' };
      if (d.results && d.results.length) {
        d.results.forEach(r => {
          const analysis = r.analysis || {};
          const $badge = $(`#status-${r.comment_id}`);
          const decision = analysis.decision || 'hold';
          $badge.removeClass('uam-badge-pending')
            .addClass(`uam-badge-${decBadge[decision] || 'hold'}`)
            .text(decision.toUpperCase());
        });
      }
    }, function (msg) { UAM.setLoading($btn, false); alert('Error: ' + msg); });
  });

  // ════════════════════════════════════════════════════════
  // 7. ANALYTICS REPORT
  // ════════════════════════════════════════════════════════
  $(document).on('click', '#btn-analytics-report', function () {
    const $btn = $(this), $out = $('#analytics-report-result');
    UAM.setLoading($btn, true);
    $out.html('<div class="uam-loader" style="height:120px"><div class="uam-spinner"></div> Generating AI report…</div>');
    UAM.ajax('uam_analytics_report', {}, function (d) {
      UAM.setLoading($btn, false);
      const report = d.report || {};
      const raw = d.data || {};
      const gradeMap = { A: 'uam-badge-approve', B: 'uam-badge-approve', C: 'uam-badge-pending', D: 'uam-badge-spam', F: 'uam-badge-spam' };
      $out.html(`
        <div class="uam-analytics-report">
          <div class="uam-report-summary">
            <div class="uam-grade-badge">${report.performance_grade || 'B'}</div>
            <h4>Executive Summary</h4>
            <p style="line-height:1.7;color:var(--uam-text2)">${UAM.escHtml(report.executive_summary || '')}</p>
          </div>
          <div class="uam-report-card">
            <h4>📊 Site Stats</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              ${[['Est. Page Views', raw.pageviews], ['Est. Visitors', raw.visitors], ['Bounce Rate', raw.bounce_rate + '%'], ['Avg Session', raw.session_duration + 's']].map(([l, v]) =>
        `<div style="padding:10px;background:rgba(99,102,241,.06);border-radius:8px;text-align:center">
                  <div style="font-size:20px;font-weight:800;color:var(--uam-accent)">${v}</div>
                  <div style="font-size:10px;color:var(--uam-text2)">${l}</div>
                </div>`).join('')}
            </div>
          </div>
          <div class="uam-report-card">
            <h4>✅ Key Highlights</h4>
            ${UAM.renderList(report.key_highlights, '✓')}
          </div>
          <div class="uam-report-card">
            <h4>⚠️ Areas of Concern</h4>
            ${UAM.renderList(report.areas_of_concern, '⚠')}
          </div>
          <div class="uam-report-card">
            <h4>💡 Recommendations</h4>
            ${UAM.renderList(report.recommendations, '💡')}
          </div>
          <div class="uam-report-card">
            <h4>🚀 Next Steps</h4>
            ${UAM.renderList(report.next_steps, '→')}
          </div>
        </div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // ════════════════════════════════════════════════════════
  // 8. ALT TEXT GENERATOR
  // ════════════════════════════════════════════════════════

  // Show image thumbnail when selection changes
  $(document).on('change', '#alt-attachment-id', function () {
    const $opt = $(this).find(':selected');
    const thumb = $opt.data('thumb');
    const existingAlt = $opt.data('alt') || '';
    if (thumb) {
      $('#alt-preview-img').attr('src', thumb).attr('alt', existingAlt);
      $('#alt-edit-field').val(existingAlt);
      $('#alt-image-preview').show();
      $('#alt-result').html(existingAlt
        ? `<div class="uam-notice" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.2);color:#10b981">✓ This image already has alt text. You can regenerate it below.</div>`
        : `<div class="uam-placeholder">No alt text yet. Click "Generate & Save" to create one.</div>`);
    } else {
      $('#alt-image-preview').hide();
    }
  });

  // Preview only (no save)
  $(document).on('click', '#btn-preview-alt', function () {
    const $btn = $(this), $out = $('#alt-result');
    const id = $('#alt-attachment-id').val();
    if (!id) return $out.html('<div class="uam-notice uam-notice-error">Please select an image first.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_alt_text', { attachment_id: id, save: 0 }, function (d) {
      UAM.setLoading($btn, false);
      $('#alt-edit-field').val(d.alt_text);
      $('#alt-image-preview').show();
      $out.html(`
        <div style="background:rgba(99,102,241,.08);border:1px solid var(--uam-border2);border-radius:10px;padding:16px;line-height:1.6">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--uam-accent);margin-bottom:8px">Generated Alt Text (Preview)</div>
          <p style="margin:0;font-size:14px;color:var(--uam-text)">${UAM.escHtml(d.alt_text)}</p>
          <div style="font-size:11px;color:var(--uam-text3);margin-top:8px">${d.alt_text.length} characters • Not saved yet — click "Generate & Save" to apply</div>
        </div>
      `);
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Generate & save
  $(document).on('click', '#btn-gen-alt', function () {
    const $btn = $(this), $out = $('#alt-result');
    const id = $('#alt-attachment-id').val();
    if (!id) return $out.html('<div class="uam-notice uam-notice-error">Please select an image first.</div>');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);
    UAM.ajax('uam_generate_alt_text', { attachment_id: id, save: 1 }, function (d) {
      UAM.setLoading($btn, false);
      $('#alt-edit-field').val(d.alt_text);
      $('#alt-image-preview').show();
      $out.html(`
        <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:10px;padding:16px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <span style="font-size:16px">✅</span>
            <strong style="color:#10b981;font-size:13px">Alt text generated & saved!</strong>
          </div>
          <p style="margin:0;font-size:14px;color:var(--uam-text)">${UAM.escHtml(d.alt_text)}</p>
          <div style="font-size:11px;color:var(--uam-text3);margin-top:8px">${d.alt_text.length} characters</div>
        </div>
      `);
      // Update the dropdown option to show ✓
      $(`#alt-attachment-id option[value="${id}"]`).text(
        $(`#alt-attachment-id option[value="${id}"]`).text().replace(' (no alt)', '') + ' ✓'
      ).css('color', '');
    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // Copy alt text
  $(document).on('click', '#btn-copy-alt', function () {
    const text = $('#alt-edit-field').val();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      $(this).text('✓ Copied!');
      setTimeout(() => $(this).text('📋 Copy'), 2000);
    });
  });

  // Save edited alt text to image
  $(document).on('click', '#btn-save-alt', function () {
    const id = $('#alt-edit-id').val();
    const alt = $('#alt-edit-field').val().trim();
    if (!id) return;

    const $btn = $(this);
    UAM.setLoading($btn, true);
    UAM.ajax('uam_save_alt_text', { attachment_id: id, alt_text: alt }, function (d) {
      UAM.setLoading($btn, false);
      UAM.showToast('✨ Alt text saved successfully!');
    }, function (msg) {
      UAM.setLoading($btn, false);
      UAM.showToast(msg, 'error');
    });
  });

  // Bulk alt text generation
  $(document).on('click', '#btn-bulk-alt', function () {
    const $btn = $(this), $bulkOut = $('#alt-bulk-results'), $out = $('#alt-result');
    const limit = $('#alt-bulk-limit').val();
    UAM.setLoading($btn, true);
    $bulkOut.html('<div class="uam-loader"><div class="uam-spinner"></div> Processing images…</div>');
    $out.html('');
    UAM.ajax('uam_bulk_generate_alt_text', { limit }, function (d) {
      UAM.setLoading($btn, false);
      if (!d.results || !d.results.length) {
        $bulkOut.html('<div class="uam-notice" style="color:#10b981">✅ All images already have alt text!</div>');
        return;
      }
      const rows = d.results.map(r => `
        <div style="padding:10px 12px;border:1px solid var(--uam-border2);border-radius:8px;margin-bottom:8px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <strong style="font-size:12px;color:var(--uam-text)">${UAM.escHtml(r.filename)}</strong>
            ${r.error ? '<span class="uam-badge uam-badge-spam">Failed</span>' : '<span class="uam-badge uam-badge-approve">✓ Saved</span>'}
          </div>
          ${r.alt_text ? `<p style="margin:0;font-size:12px;color:var(--uam-text2)">${UAM.escHtml(r.alt_text)}</p>` : ''}
          ${r.error ? `<p style="margin:4px 0 0;font-size:11px;color:var(--uam-red)">${UAM.escHtml(r.error)}</p>` : ''}
        </div>
      `).join('');
      $bulkOut.html(`
        <div style="margin-bottom:12px;font-size:13px;font-weight:600;color:var(--uam-text)">
          Processed ${d.count} image${d.count !== 1 ? 's' : ''}
        </div>
        ${rows}
      `);
    }, function (msg) { UAM.setLoading($btn, false); $bulkOut.html(`<div class="uam-notice uam-notice-error">Error: ${UAM.escHtml(msg)}</div>`); });
  });

  // Auto-generate toggle (saves setting via admin settings)
  $(document).on('change', '#alt-auto-toggle', function () {
    const enabled = this.checked ? 1 : 0;
    // This setting is saved via the Settings page form -- just show a reminder
    const $label = $(this).closest('.uam-toggle-group').find('span');
    $label.text(enabled ? 'Auto-generate on upload (save in Settings to persist)' : 'Auto-generate on upload');
  });

  // Media library quick-generate link
  $(document).on('click', '.uam-gen-alt-link', function (e) {
    e.preventDefault();
    const id = $(this).data('id');
    const $link = $(this);
    $link.text('Generating…');
    UAM.ajax('uam_generate_alt_text', { attachment_id: id, save: 1 }, function (d) {
      $link.closest('div').find('span:first').html(`<span style="color:#10b981;font-size:11px;font-weight:600">${d.alt_text.substring(0, 40)}${d.alt_text.length > 40 ? '…' : ''}</span><br><span style="font-size:10px;color:#6366f1">✦ AI Generated</span>`);
      $link.text('✓ Done');
    }, function (msg) {
      $link.text('✦ Generate');
      alert('Alt text error: ' + msg);
    });
  });

  // ════════════════════════════════════════════════════════
  // 9. META SAVE TO POST
  // ════════════════════════════════════════════════════════

  $(document).on('click', '#btn-save-meta-to-post', function () {
    const $btn = $(this), $out = $('#meta-result');
    const post_id = $('#meta-save-post-id').val();
    if (!post_id) return alert('Please select a post to save to.');

    // Get the last generated values from the result area (or from form fields)
    const seo_title = $('#meta-title').val().trim();
    const meta_description = $('#meta-content').val().trim();
    const focus_keyword = $('#meta-keyword').val().trim();

    if (!seo_title && !meta_description) {
      return alert('Please generate meta first, or fill in the Post Title and Content Snippet fields.');
    }

    UAM.setLoading($btn, true);
    UAM.ajax('uam_save_seo_meta', { post_id, seo_title, meta_description, focus_keyword }, function (d) {
      UAM.setLoading($btn, false);
      const toast = $(`<div style="position:fixed;top:28px;right:28px;z-index:99999;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:12px 20px;border-radius:10px;font-family:Inter,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 28px rgba(16,185,129,.45)">
        💾 SEO meta saved to post!
      </div>`).appendTo('body');
      setTimeout(() => toast.fadeOut(400, function () { $(this).remove(); }), 2500);
      // Show confirmation in result area
      const prevHtml = $out.html();
      $out.prepend('<div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#10b981;font-weight:600">✅ Saved to post! Meta tags will now be injected in the page &lt;head&gt;.</div>');
    }, function (msg) {
      UAM.setLoading($btn, false);
      alert('Save failed: ' + msg);
    });
  });

  // ════════════════════════════════════════════════════════
  // 10. SETTINGS — TEST CONNECTION
  // ════════════════════════════════════════════════════════

  function testProvider(provider, $btn, $result) {
    $result.html('<span style="color:#94a3b8">🔄 Testing…</span>');
    UAM.setLoading($btn, true);
    UAM.ajax('uam_test_connection', { provider }, function (d) {
      UAM.setLoading($btn, false);
      $result.html('<span style="color:#10b981;font-weight:600">✅ Connection successful! API key is valid.</span>');
    }, function (msg) {
      UAM.setLoading($btn, false);
      $result.html(`<span style="color:#ef4444;font-weight:600">❌ ${UAM.escHtml(msg)}</span>`);
    });
  }

  $(document).on('click', '#btn-test-gemini', function () {
    testProvider('gemini', $(this), $('#gemini-test-result'));
  });

  $(document).on('click', '#btn-test-openai', function () {
    testProvider('openai', $(this), $('#openai-test-result'));
  });

  // ════════════════════════════════════════════════════════
  // 11. VISION AI ANALYZER (ADMIN)
  // ════════════════════════════════════════════════════════

  $(document).on('click', '#btn-select-vision-admin', function (e) {
    e.preventDefault();
    if (typeof wp === 'undefined' || !wp.media) return alert('WP Media Library not loaded.');
    const frame = wp.media({ title: 'Select Image for Analysis', button: { text: 'Analyze this image' }, multiple: false });
    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      $('#vision-admin-attach-id').val(attachment.id);
      $('#vision-admin-preview').html(`<img src="${attachment.url}" style="max-width:100%;max-height:200px;border-radius:10px;border:1px solid var(--uam-border)">`);
    });
    frame.open();
  });

  $(document).on('click', '#btn-analyze-vision-admin', function () {
    const attach_id = $('#vision-admin-attach-id').val();
    if (!attach_id) return UAM.showToast('Please select an image first.', 'error');

    const $btn = $(this), $out = $('#vision-admin-results');
    UAM.setLoading($btn, true);
    UAM.showLoader($out);

    UAM.ajax('uam_analyze_vision', { attachment_id: attach_id }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div class="uam-vision-result-box" style="background:rgba(99,102,241,.08);border:1px solid var(--uam-border);border-radius:10px;padding:16px" 
             data-title="${UAM.escHtml(d.title)}" data-alt="${UAM.escHtml(d.alt)}">
          <div style="margin-bottom:12px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--uam-text2)">SEO Title</label>
            <div style="margin-top:4px;font-weight:600" class="vis-title">${UAM.escHtml(d.title)}</div>
          </div>
          <div style="margin-bottom:12px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--uam-text2)">Alt Text</label>
            <div style="margin-top:4px" class="vis-alt">${UAM.escHtml(d.alt)}</div>
          </div>
          <div style="margin-bottom:12px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--uam-text2)">Caption</label>
            <div style="margin-top:4px">${UAM.escHtml(d.caption || 'None')}</div>
          </div>
          <div style="display:flex;gap:10px;margin-top:15px">
            <button class="uam-btn uam-btn-ghost uam-btn-sm" id="btn-copy-vision-json" style="flex:1">📋 Copy JSON</button>
            <button class="uam-btn uam-btn-primary uam-btn-sm" id="btn-vision-apply-seo" style="flex:1">✅ Apply to SEO Meta</button>
          </div>
        </div>
      `);

      // Handler for copying JSON
      $(document).off('click', '#btn-copy-vision-json').on('click', '#btn-copy-vision-json', function () {
        navigator.clipboard.writeText(JSON.stringify(d, null, 2));
        UAM.showToast('Results copied!');
      });

      // Handler for applying to SEO Meta
      $(document).off('click', '#btn-vision-apply-seo').on('click', '#btn-vision-apply-seo', function () {
        const postId = $('#vision-admin-post-id').val();
        if (postId === '0') {
          UAM.showToast('Please select a post first.', 'error');
          return;
        }
        const $applyBtn = $(this);
        const container = $applyBtn.closest('.uam-vision-result-box');

        UAM.setLoading($applyBtn, true);
        UAM.ajax('uam_save_seo_meta', {
          post_id: postId,
          seo_title: container.data('title'),
          meta_description: container.data('alt')
        }, function (res) {
          UAM.setLoading($applyBtn, false);
          UAM.showToast('✨ SEO Meta applied to post!');
        }, function (msg) {
          UAM.setLoading($applyBtn, false);
          UAM.showToast(msg, 'error');
        });
      });

    }, function (msg) { UAM.setLoading($btn, false); UAM.showError(msg, $out); });
  });

  // ════════════════════════════════════════════════════════
  // 12. SEO AUDIT
  // ════════════════════════════════════════════════════════

  let _auditPosts = []; // cached scan results

  function renderStatusIcon(has) {
    return has
      ? '<span class="uam-audit-status pass">✓</span>'
      : '<span class="uam-audit-status fail">✗</span>';
  }

  function scoreClass(score) {
    if (score >= 75) return 'score-good';
    if (score >= 40) return 'score-ok';
    return 'score-bad';
  }

  function renderAuditRow(post) {
    const score = post.seo_score || 0;
    return `<tr data-post-id="${post.ID}" data-issues="${(post.missing || []).join(',')}" data-has-issues="${(post.missing || []).length > 0 ? 1 : 0}">
      <td>
        <div class="uam-audit-post-title">
          <span class="title">${UAM.escHtml(post.post_title)}</span>
          <span class="type-badge">${UAM.escHtml(post.post_type)}</span>
        </div>
      </td>
      <td>${renderStatusIcon(post.has_seo_title)}</td>
      <td>${renderStatusIcon(post.has_meta_desc)}</td>
      <td>${renderStatusIcon(post.has_focus_kw)}</td>
      <td>${renderStatusIcon(post.has_featured_img)}</td>
      <td><span class="uam-audit-score ${scoreClass(score)}">${score}</span></td>
      <td>
        <div class="uam-audit-actions">
          <button class="uam-btn-audit uam-btn-audit-view" onclick="window._uamViewAudit(${post.ID})">🔍 Audit</button>
          <button class="uam-btn-audit uam-btn-audit-fix" onclick="window._uamOptimize(${post.ID}, this)">✨ AI Fix</button>
        </div>
      </td>
    </tr>`;
  }

  function renderLoadingRows(count) {
    let html = '';
    for (let i = 0; i < count; i++) {
      html += `<tr class="uam-audit-loading"><td><span class="shimmer" style="width:${60 + Math.random() * 30}%"></span></td><td><span class="shimmer" style="width:40px"></span></td><td><span class="shimmer" style="width:40px"></span></td><td><span class="shimmer" style="width:40px"></span></td><td><span class="shimmer" style="width:40px"></span></td><td><span class="shimmer" style="width:40px"></span></td><td><span class="shimmer" style="width:100px"></span></td></tr>`;
    }
    return html;
  }

  function updateStats(stats) {
    const $title = $('#stat-missing-title');
    const $desc = $('#stat-missing-desc');
    const $kw = $('#stat-missing-kw');
    const $alt = $('#stat-missing-alt');

    function setVal($el, val) {
      $el.text(val);
      $el.removeClass('warn ok');
      if (val > 0) $el.addClass('warn');
      else $el.addClass('ok');
    }

    setVal($title, stats.missing_seo_title || 0);
    setVal($desc, stats.missing_meta_desc || 0);
    setVal($kw, stats.missing_focus_kw || 0);
    setVal($alt, stats.images_missing_alt || 0);
  }

  function applyFilter(filter) {
    $('#audit-table-body tr').each(function () {
      const $row = $(this);
      if (!$row.data('post-id')) return; // skip placeholder rows
      const issues = ($row.data('issues') || '').toString();
      const hasIssues = $row.data('has-issues');

      if (filter === 'all') {
        $row.show();
      } else if (filter === 'issues') {
        $row.toggle(hasIssues == 1);
      } else {
        $row.toggle(issues.indexOf(filter) !== -1);
      }
    });
  }

  // Scan
  $(document).on('click', '#btn-scan-seo', function () {
    const $btn = $(this);
    const postType = $('#audit-post-type').val();
    const $status = $('#audit-scan-status');
    const $tbody = $('#audit-table-body');

    UAM.setLoading($btn, true);
    $status.text('Scanning...');
    $tbody.html(renderLoadingRows(6));

    UAM.ajax('uam_scan_seo_audit', { post_type: postType }, function (d) {
      UAM.setLoading($btn, false);
      _auditPosts = d.posts || [];
      const stats = d.stats || {};
      updateStats(stats);

      if (_auditPosts.length === 0) {
        $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--uam-text3)">🎉 No published posts/pages found. Create some content first!</td></tr>');
        $status.text('');
        return;
      }

      let html = '';
      _auditPosts.forEach(p => { html += renderAuditRow(p); });
      $tbody.html(html);

      const issueCount = _auditPosts.filter(p => (p.missing || []).length > 0).length;
      $status.html(`<span style="color:var(--uam-green)">✓ Scanned ${_auditPosts.length} items</span> — <span style="color:${issueCount > 0 ? 'var(--uam-amber)' : 'var(--uam-green)'}">${issueCount} with issues</span>`);

      // Apply current filter
      applyFilter($('#audit-filter-issues').val());
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.html(`<span style="color:var(--uam-red)">❌ ${UAM.escHtml(msg)}</span>`);
      $tbody.html('<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--uam-red)">Failed to scan. Please try again.</td></tr>');
    });
  });

  // Filter
  $(document).on('change', '#audit-filter-issues', function () {
    applyFilter($(this).val());
  });

  // View Audit Detail
  window._uamViewAudit = function (postId) {
    const $detail = $('#audit-detail');
    const $content = $('#audit-detail-content');
    const $title = $('#audit-detail-title');

    $detail.show();
    $content.html('<div class="uam-placeholder">🔍 Loading audit details...</div>');
    $title.text('Post SEO Audit');

    $('html, body').animate({ scrollTop: $detail.offset().top - 80 }, 300);

    UAM.ajax('uam_get_post_audit', { post_id: postId }, function (d) {
      $title.text(`SEO Audit: ${d.title || 'Post #' + postId}`);

      const checks = d.checks || {};
      let checksHtml = '<div class="uam-audit-checks">';

      const checkItems = [
        { key: 'has_seo_title', label: 'SEO Title', pass: checks.has_seo_title, detail: d.seo_title || 'Not set' },
        { key: 'has_meta_desc', label: 'Meta Description', pass: checks.has_meta_desc, detail: d.meta_description || 'Not set' },
        { key: 'has_focus_kw', label: 'Focus Keyword', pass: checks.has_focus_kw, detail: d.focus_keyword || 'Not set' },
        { key: 'has_featured_img', label: 'Featured Image', pass: checks.has_featured_img, detail: checks.has_featured_img ? 'Set' : 'Not set' },
        { key: 'title_length', label: 'Title Length', pass: checks.title_length_ok, detail: `${d.title_length || 0} characters${checks.title_length_ok ? ' (good)' : ' (should be 50-60)'}` },
        { key: 'desc_length', label: 'Description Length', pass: checks.desc_length_ok, detail: `${d.desc_length || 0} characters${checks.desc_length_ok ? ' (good)' : ' (should be 140-160)'}` },
        { key: 'content_length', label: 'Content Length', pass: checks.content_length_ok, detail: `${d.word_count || 0} words${checks.content_length_ok ? ' (good)' : ' (aim for 300+)'}` },
        { key: 'has_internal_links', label: 'Internal Links', pass: checks.has_internal_links, detail: `${d.internal_links || 0} found` },
      ];

      checkItems.forEach(item => {
        const icon = item.pass ? '✅' : '❌';
        checksHtml += `
          <div class="uam-audit-check-item">
            <span class="uam-audit-check-icon">${icon}</span>
            <div>
              <div class="uam-audit-check-label">${item.label}</div>
              <div class="uam-audit-check-value">${UAM.escHtml(item.detail)}</div>
            </div>
          </div>`;
      });
      checksHtml += '</div>';

      // Score
      const score = d.seo_score || 0;
      let scoreHtml = `
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:16px;background:rgba(99,102,241,.06);border-radius:var(--uam-radius-sm);border:1px solid var(--uam-border2)">
          <span class="uam-audit-score ${scoreClass(score)}" style="width:56px;height:56px;font-size:18px">${score}</span>
          <div>
            <strong style="font-size:15px;color:var(--uam-text)">Overall SEO Score</strong>
            <div style="font-size:12px;color:var(--uam-text2);margin-top:2px">${score >= 75 ? 'Great! Your SEO is well-optimized.' : score >= 40 ? 'Needs improvement. Address the issues below.' : 'Critical: Many SEO elements are missing.'}</div>
          </div>
          <a href="${d.edit_url || '#'}" target="_blank" class="uam-btn uam-btn-ghost uam-btn-sm" style="margin-left:auto">✏️ Edit Post</a>
        </div>`;

      // Images missing alt
      let imagesHtml = '';
      if (d.images_missing_alt && d.images_missing_alt.length > 0) {
        imagesHtml = `
          <div class="uam-audit-images-list">
            <h4 style="font-size:13px;font-weight:700;color:var(--uam-text);margin:0 0 10px">🖼️ Images Missing Alt Text (${d.images_missing_alt.length})</h4>`;
        d.images_missing_alt.forEach(img => {
          imagesHtml += `
            <div class="uam-audit-img-item">
              ${img.src ? `<img class="uam-audit-img-thumb" src="${img.src}" alt="">` : ''}
              <div class="uam-audit-img-info">
                <div class="uam-audit-img-name">${UAM.escHtml(img.filename || 'Unknown image')}</div>
                <div class="uam-audit-img-status">⚠ No alt text</div>
              </div>
            </div>`;
        });
        imagesHtml += '</div>';
      }

      // Recommendations
      let recsHtml = '';
      if (d.recommendations && d.recommendations.length > 0) {
        recsHtml = '<div style="margin-top:16px"><h4 style="font-size:13px;font-weight:700;color:var(--uam-text);margin:0 0 10px">💡 Recommendations</h4><ul style="padding-left:20px;color:var(--uam-text2);font-size:12px;line-height:1.8">';
        d.recommendations.forEach(r => {
          recsHtml += `<li>${UAM.escHtml(r)}</li>`;
        });
        recsHtml += '</ul></div>';
      }

      $content.html(scoreHtml + checksHtml + imagesHtml + recsHtml);
    }, function (msg) {
      $content.html(`<div style="color:var(--uam-red);padding:20px;text-align:center">❌ ${UAM.escHtml(msg)}</div>`);
    });
  };

  // Optimize Post
  window._uamOptimize = function (postId, btnEl) {
    const $btn = $(btnEl);
    const origText = $btn.html();
    $btn.html('⏳ Optimizing...').prop('disabled', true);

    UAM.ajax('uam_optimize_post_seo', { post_id: postId }, function (d) {
      $btn.html('✅ Done!').css('color', 'var(--uam-green)');
      setTimeout(() => {
        $btn.html(origText).prop('disabled', false).css('color', '');
      }, 2000);

      // Update the row status
      const $row = $(`tr[data-post-id="${postId}"]`);
      if ($row.length) {
        // Update status columns based on what was generated
        const $cells = $row.find('td');
        if (d.seo_title) $cells.eq(1).html(renderStatusIcon(true));
        if (d.meta_description) $cells.eq(2).html(renderStatusIcon(true));
        if (d.focus_keyword) $cells.eq(3).html(renderStatusIcon(true));
      }

      // Show a summary
      let summary = [];
      if (d.meta_generated) summary.push('✅ SEO meta generated');
      if (d.alt_texts_generated > 0) summary.push(`✅ ${d.alt_texts_generated} image alt text(s) generated`);
      if (summary.length === 0) summary.push('ℹ️ All SEO data was already set');

      $('#audit-scan-status').html(`<span style="color:var(--uam-green)">${summary.join(' | ')}</span>`);
    }, function (msg) {
      $btn.html(origText).prop('disabled', false);
      $('#audit-scan-status').html(`<span style="color:var(--uam-red)">❌ Optimization failed: ${UAM.escHtml(msg)}</span>`);
    });
  };

  // Close detail panel
  $(document).on('click', '#btn-close-audit-detail', function () {
    $('#audit-detail').slideUp(200);
  });

  // ════════════════════════════════════════════════════════
  // 12. CHATBOT SETTINGS SAVE
  // ════════════════════════════════════════════════════════
  $(document).on('submit', '#chatbot-settings-form', function (e) {
    e.preventDefault();
    const $btn = $('#btn-save-chatbot');
    const $status = $('#chatbot-save-status');
    UAM.setLoading($btn, true);
    $status.text('Saving…').css('color', 'var(--uam-text2)');

    const data = {
      chatbot_enabled: $(this).find('[name="chatbot_enabled"]').is(':checked') ? 1 : 0,
      chatbot_greeting: $(this).find('[name="chatbot_greeting"]').val(),
      chatbot_position: $(this).find('[name="chatbot_position"]').val(),
    };

    UAM.ajax('uam_save_chatbot_settings', data, function (res) {
      UAM.setLoading($btn, false);
      $status.text('✅ ' + (res.message || 'Saved!')).css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
    });
  });

  // ════════════════════════════════════════════════════════
  // 13. INTERNAL LINKS — 6 Tabs
  // ════════════════════════════════════════════════════════

  // ── Tab Navigation ──────────────────────────────────────
  $(document).on('click', '.uam-tabs-nav .uam-tab', function () {
    const tab = $(this).data('tab');
    $(this).addClass('active').siblings('.uam-tab').removeClass('active');
    const $panes = $(this).closest('.uam-wrap').find('.uam-tab-pane');
    $panes.removeClass('active');
    $panes.filter('#tab-' + tab).addClass('active');
  });

  // ── Link Type Toggle (radio buttons) ───────────────────
  $(document).on('change', 'input[name="il_link_type"]', function () {
    $(this).closest('.uam-provider-toggle').find('.uam-provider-opt').removeClass('active');
    $(this).closest('.uam-provider-opt').addClass('active');
  });

  // ── Enable buttons when post selected ──────────────────
  $(document).on('change', '#il-post-select', function () {
    $('#btn-ai-suggest').prop('disabled', !$(this).val());
  });
  $(document).on('change', '#kw-post-select', function () {
    $('#btn-extract-kw').prop('disabled', !$(this).val());
  });
  $(document).on('change', '#bl-post-select', function () {
    $('#btn-check-broken').prop('disabled', !$(this).val());
  });

  // ────────────────────────────────────────────────────────
  // 9. ALT TEXT GENERATOR
  // ────────────────────────────────────────────────────────

  $(document).on('click', '#btn-select-alt-image', function (e) {
    e.preventDefault();
    if (typeof wp === 'undefined' || !wp.media) return alert('WP Media Library not loaded.');
    const frame = wp.media({ title: 'Select Image for Alt Text', button: { text: 'Select Image' }, multiple: false });
    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      $('#alt-attachment-id').val(attachment.id);
      $('#alt-preview-area').html(`<img src="${attachment.url}" style="max-width:100%;max-height:180px;border-radius:10px;border:1px solid var(--uam-border)">`);
      $('#alt-current-val').text(attachment.alt || '(No alt text currently)');
    });
    frame.open();
  });

  $(document).on('click', '#btn-gen-alt', function () {
    const attach_id = $('#alt-attachment-id').val();
    if (!attach_id) return UAM.showToast('Please select an image first.', 'error');

    const $btn = $(this), $out = $('#alt-status-area');
    UAM.setLoading($btn, true);
    $out.html('<div class="uam-loader"><div class="uam-spinner"></div> Analyzing image…</div>');

    UAM.ajax('uam_generate_alt_text', { attachment_id: attach_id }, function (d) {
      UAM.setLoading($btn, false);
      $out.html(`
        <div class="uam-alt-result">
          <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--uam-text2)">AI Generated Alt Text</label>
          <textarea id="alt-edit-field" class="uam-textarea" style="margin-top:8px;font-size:13px">${UAM.escHtml(d.alt_text)}</textarea>
          <input type="hidden" id="alt-edit-id" value="${attach_id}">
          <button class="uam-btn uam-btn-primary uam-btn-sm" id="btn-save-alt" style="margin-top:10px;width:100%">💾 Save to Image</button>
        </div>
      `);
    }, function (msg) {
      UAM.setLoading($btn, false);
      UAM.showError(msg, $out);
    });
  });

  // ────────────────────────────────────────────────────────
  // 12. INTERNAL LINKS (ADMIN)
  // ────────────────────────────────────────────────────────

  // ── TAB 1: Scan All Posts ──────────────────────────────
  $(document).on('click', '#btn-scan-links', function () {
    const $btn = $(this);
    const $status = $('#il-scan-status');
    UAM.setLoading($btn, true);
    $status.text('Scanning posts…').css('color', 'var(--uam-text2)');

    UAM.ajax('uam_scan_links', {}, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ Scan complete!').css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);

      const s = data.stats;
      // Stats grid
      $('#il-stat-posts').text(s.total_posts);
      $('#il-stat-internal').text(s.total_internal);
      $('#il-stat-external').text(s.total_external);
      $('#il-stat-nofollow').text(s.nofollow_count || 0);
      $('#il-stat-avg').text(s.avg_internal);
      $('#il-stat-orphans').text(s.orphan_count);
      $('#il-stat-nolinks').text(s.no_links_count || 0);
      $('#il-stats-grid').slideDown(200);

      // Report table
      let html = '<table class="il-report-table"><thead><tr>';
      html += '<th>Post Title</th><th>Internal</th><th>External</th><th>Nofollow</th><th>Words</th>';
      html += '</tr></thead><tbody>';
      data.posts.forEach(function (p) {
        const cls = p.internal_count >= 3 ? 'il-count-good' : (p.internal_count >= 1 ? 'il-count-warn' : 'il-count-bad');
        html += `<tr>
          <td>${UAM.escHtml(p.post_title)}</td>
          <td class="${cls}">${p.internal_count}</td>
          <td>${p.external_count}</td>
          <td>${p.nofollow_count || 0}</td>
          <td>${p.word_count.toLocaleString()}</td>
        </tr>`;
      });
      html += '</tbody></table>';
      $('#il-report-table').html(html);
      $('#il-report-panel').slideDown(200);

      // Orphans
      if (data.orphans && data.orphans.length > 0) {
        let oHtml = '';
        data.orphans.forEach(function (o) {
          oHtml += `<div class="il-orphan-item">
            <span class="il-orphan-title">📄 ${UAM.escHtml(o.title)}</span>
            <span class="il-orphan-actions">
              <a href="${o.url}" target="_blank">View ↗</a>
            </span>
          </div>`;
        });
        $('#il-orphans-list').html(oHtml);
        $('#il-orphans-panel').slideDown(200);
      }
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
    });
  });

  // ── TAB 2: AI Link Suggestions (with type filter) ──────
  $(document).on('click', '#btn-ai-suggest', function () {
    const postId = $('#il-post-select').val();
    if (!postId) return;
    const $btn = $(this);
    const $status = $('#il-suggest-status');
    const $results = $('#il-suggestions');
    const linkType = $('input[name="il_link_type"]:checked').val() || 'all';

    UAM.setLoading($btn, true);
    $status.text('🧠 AI is analyzing your content…').css('color', 'var(--uam-text2)');
    $results.html('<div class="uam-loader"><div class="uam-spinner"></div> Generating suggestions…</div>');

    UAM.ajax('uam_ai_suggest_links', { post_id: postId, link_type: linkType }, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ Done!').css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);

      if (!data.suggestions || data.suggestions.length === 0) {
        $results.html('<div class="uam-placeholder">No suggestions found for this post with the selected link type.</div>');
        return;
      }

      let html = '';
      data.suggestions.forEach(function (s) {
        const type = s.type || 'internal';
        const typeBadge = `<span class="il-suggestion-type ${type}">${type === 'external' ? '🌍 External' : '🏠 Internal'}</span>`;
        const nofollowTag = s.nofollow ? ' <span style="font-size:10px;color:var(--uam-amber)">[nofollow]</span>' : '';
        const newTabTag = s.new_tab ? ' <span style="font-size:10px;color:var(--uam-text3)">[↗ new tab]</span>' : '';

        html += `<div class="il-suggestion-card">
          <div class="il-suggestion-anchor">"${UAM.escHtml(s.anchor_text)}" ${typeBadge}</div>
          <div class="il-suggestion-target">→ <strong>${UAM.escHtml(s.target_title || s.target_url || 'Post #' + (s.target_post_id || ''))}</strong>${nofollowTag}${newTabTag}</div>
          <div class="il-suggestion-reason">${UAM.escHtml(s.reason || '')}</div>
          <div class="il-suggestion-actions">
            <button class="uam-btn uam-btn-primary uam-btn-sm btn-insert-link"
              data-post-id="${postId}"
              data-anchor="${UAM.escHtml(s.anchor_text)}"
              data-url="${UAM.escHtml(s.target_url || '')}"
              data-nofollow="${s.nofollow ? 1 : 0}"
              data-newtab="${s.new_tab ? 1 : 0}">
              ✅ Insert Link
            </button>
          </div>
        </div>`;
      });
      $results.html(html);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
      $results.html('');
    });
  });

  // ── Insert Link (from AI suggestions) ──────────────────
  $(document).on('click', '.btn-insert-link', function () {
    const $btn = $(this);
    const postId = $btn.data('post-id');
    const anchor = $btn.data('anchor');
    const url = $btn.data('url');
    const nofollow = $btn.data('nofollow');
    const new_tab = $btn.data('newtab');

    $btn.text('Inserting…').prop('disabled', true);

    UAM.ajax('uam_insert_link', {
      post_id: postId,
      anchor: anchor,
      target_url: url,
      nofollow: nofollow,
      new_tab: new_tab
    }, function () {
      $btn.text('✅ Inserted!').addClass('uam-btn-success').prop('disabled', true);
    }, function (msg) {
      $btn.text('❌ ' + msg).css('color', 'var(--uam-red)').prop('disabled', true);
    });
  });

  // ── TAB 3: Keyword Extraction ──────────────────────────
  $(document).on('click', '#btn-extract-kw', function () {
    const postId = $('#kw-post-select').val();
    if (!postId) return;
    const $btn = $(this);
    const $status = $('#kw-status');
    const $results = $('#kw-results');

    UAM.setLoading($btn, true);
    $status.text('🔑 Extracting keywords…').css('color', 'var(--uam-text2)');
    $results.html('<div class="uam-loader"><div class="uam-spinner"></div> Analyzing content…</div>');

    UAM.ajax('uam_extract_keywords', { post_id: postId }, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ Done!').css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);

      const keywords = data.keywords || [];
      if (!keywords.length) {
        $results.html('<div class="uam-placeholder">No keywords extracted. Try a post with more content.</div>');
        return;
      }

      let html = `<div style="margin-bottom:12px;font-size:12px;color:var(--uam-text2);font-weight:600">${keywords.length} keywords found</div>`;
      keywords.forEach(function (kw) {
        const typeClass = (kw.type || 'short-tail').replace(/\s+/g, '-').toLowerCase();
        const linkClass = (kw.link_type || 'internal').toLowerCase();
        const volume = kw.search_volume || 'N/A';

        html += `<div class="uam-kw-card">
          <span class="kw-name">${UAM.escHtml(kw.keyword)}</span>
          <div class="kw-meta">
            <span class="kw-tag ${typeClass}">${UAM.escHtml(kw.type || 'keyword')}</span>
            <span class="kw-tag ${linkClass}">${linkClass === 'external' ? '🌍 ext' : '🏠 int'}</span>
            <span class="kw-volume">📊 ${UAM.escHtml(String(volume))}</span>
          </div>
        </div>`;
      });
      $results.html(html);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
      $results.html('');
    });
  });

  // ── TAB 4: Broken Link Checker ─────────────────────────
  $(document).on('click', '#btn-check-broken', function () {
    const postId = $('#bl-post-select').val();
    if (!postId) return;
    const $btn = $(this);
    const $status = $('#bl-status');
    const $results = $('#bl-results');
    const $summary = $('#bl-summary');

    UAM.setLoading($btn, true);
    $status.text('🩺 Checking links…').css('color', 'var(--uam-text2)');
    $results.html('<div class="uam-loader"><div class="uam-spinner"></div> Checking each link — this may take a moment…</div>');

    UAM.ajax('uam_check_broken_links', { post_id: postId }, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ Check complete!').css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);

      const links = data.links || [];
      const stats = { total: links.length, ok: 0, broken: 0, redirect: 0 };
      links.forEach(l => {
        if (l.status === 'ok') stats.ok++;
        else if (l.status === 'broken') stats.broken++;
        else if (l.status === 'redirect') stats.redirect++;
      });

      // Summary stats
      $('#bl-total').text(stats.total);
      $('#bl-ok').text(stats.ok);
      $('#bl-broken').text(stats.broken);
      $('#bl-redirect').text(stats.redirect);
      $summary.slideDown(200);

      if (!links.length) {
        $results.html('<div class="uam-placeholder">No links found in this post.</div>');
        return;
      }

      const statusIcon = { ok: '✅', broken: '❌', redirect: '↪️', error: '⚠️' };
      let html = '';
      links.forEach(function (l) {
        const st = l.status || 'error';
        const code = l.http_code ? ` (${l.http_code})` : '';
        html += `<div class="bl-item" data-url="${UAM.escHtml(l.url)}">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span class="bl-status-badge ${st}">${statusIcon[st] || '?'} ${st.toUpperCase()}${code}</span>
            <span style="font-size:10px;color:var(--uam-text3)">${l.type || ''}</span>
          </div>
          <div class="bl-item-url"><a href="${UAM.escHtml(l.url)}" target="_blank">${UAM.escHtml(l.url)}</a></div>
          <div class="bl-item-anchor">Anchor: "${UAM.escHtml(l.anchor_text || 'N/A')}"</div>`;

        // Show fix actions only for broken/redirect links
        if (st === 'broken' || st === 'redirect' || st === 'error') {
          html += `<div class="bl-item-actions">
            <button class="uam-btn uam-btn-sm uam-btn-ghost bl-fix-btn" data-post-id="${postId}"
              data-url="${UAM.escHtml(l.url)}" data-action="nofollow" title="Add nofollow to this link">🔒 Nofollow</button>
            <button class="uam-btn uam-btn-sm uam-btn-ghost bl-fix-btn" data-post-id="${postId}"
              data-url="${UAM.escHtml(l.url)}" data-action="remove" title="Remove this link">🗑️ Remove</button>
            <button class="uam-btn uam-btn-sm uam-btn-secondary bl-replace-btn" data-post-id="${postId}"
              data-url="${UAM.escHtml(l.url)}" title="Replace with new URL">🔄 Replace</button>
          </div>`;
        }
        html += '</div>';
      });
      $results.html(html);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
      $results.html('');
    });
  });

  // ── Fix broken link — nofollow / remove ────────────────
  $(document).on('click', '.bl-fix-btn', function () {
    const $btn = $(this);
    const postId = $btn.data('post-id');
    const url = $btn.data('url');
    const action = $btn.data('action');

    $btn.text('Fixing…').prop('disabled', true);

    UAM.ajax('uam_fix_link', { post_id: postId, url: url, action: action }, function () {
      $btn.text('✅ Done!').css('color', 'var(--uam-green)');
      if (action === 'remove') {
        $btn.closest('.bl-item').fadeOut(300, function () { $(this).remove(); });
      }
    }, function (msg) {
      $btn.text('❌ Failed').css('color', 'var(--uam-red)');
      setTimeout(() => {
        $btn.text(action === 'nofollow' ? '🔒 Nofollow' : '🗑️ Remove').css('color', '').prop('disabled', false);
      }, 2000);
    });
  });

  // ── Replace broken link — prompt for new URL ───────────
  $(document).on('click', '.bl-replace-btn', function () {
    const $btn = $(this);
    const postId = $btn.data('post-id');
    const oldUrl = $btn.data('url');
    const newUrl = prompt('Enter the replacement URL:', oldUrl);
    if (!newUrl || newUrl === oldUrl) return;

    $btn.text('Replacing…').prop('disabled', true);

    UAM.ajax('uam_fix_link', { post_id: postId, url: oldUrl, action: 'replace', new_url: newUrl }, function () {
      $btn.text('✅ Replaced!').css('color', 'var(--uam-green)');
      $btn.closest('.bl-item').find('.bl-item-url a').text(newUrl).attr('href', newUrl);
      $btn.closest('.bl-item').find('.bl-status-badge').removeClass('broken redirect error').addClass('ok').html('✅ OK');
    }, function (msg) {
      $btn.text('❌ Failed').css('color', 'var(--uam-red)');
      setTimeout(() => $btn.text('🔄 Replace').css('color', '').prop('disabled', false), 2000);
    });
  });

  // ── TAB 5: Manual Link Rules ───────────────────────────
  $(document).on('click', '#btn-save-manual-link', function () {
    const $btn = $(this);
    const $status = $('#ml-status');
    const keyword = $('#ml-keyword').val().trim();
    const url = $('#ml-url').val().trim();

    if (!keyword || !url) {
      $status.text('❌ Keyword and URL are required.').css('color', 'var(--uam-red)');
      return;
    }

    UAM.setLoading($btn, true);
    $status.text('Saving…').css('color', 'var(--uam-text2)');

    UAM.ajax('uam_save_manual_link', {
      keyword: keyword,
      url: url,
      title: $('#ml-title').val().trim(),
      type: $('#ml-type').val(),
      max_links: $('#ml-max').val() || 1,
      nofollow: $('#ml-nofollow').is(':checked') ? 1 : 0,
      new_tab: $('#ml-newtab').is(':checked') ? 1 : 0,
    }, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ Rule saved!').css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);

      // Clear form
      $('#ml-keyword, #ml-url, #ml-title').val('');
      $('#ml-nofollow').prop('checked', false);
      $('#ml-newtab').prop('checked', true);
      $('#ml-max').val(1);

      // Reload the list (add the new item to the top)
      const link = data.link || {};
      const type = link.type || 'internal';
      const newItem = `<div class="uam-link-rule-item" data-id="${link.id || ''}">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <span class="uam-badge ${type === 'external' ? 'uam-badge-spam' : 'uam-badge-approve'}" style="font-size:10px;">${type === 'external' ? '🌍 External' : '🏠 Internal'}</span>
          ${link.nofollow ? '<span class="uam-badge uam-badge-pending" style="font-size:10px;">nofollow</span>' : ''}
          <span style="color:#10b981;font-size:10px;font-weight:700;">● Active</span>
        </div>
        <div style="margin-bottom:4px;">
          <strong style="color:var(--uam-accent);">"${UAM.escHtml(link.keyword || keyword)}"</strong>
          <span style="color:var(--uam-text2);margin:0 6px;">→</span>
          <a href="${UAM.escHtml(link.url || url)}" target="_blank" style="font-size:12px;word-break:break-all;">${UAM.escHtml(link.url || url)}</a>
        </div>
        <div style="display:flex;gap:6px;margin-top:6px;">
          <button class="uam-btn uam-btn-sm uam-btn-ghost ml-delete-btn" data-id="${link.id || ''}">🗑️ Delete</button>
        </div>
      </div>`;

      const $list = $('#ml-list');
      $list.find('.uam-placeholder').remove();
      $list.prepend(newItem);

      // Update count
      const count = $list.find('.uam-link-rule-item').length;
      $('#ml-count').text(count);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
    });
  });

  // ── Delete Manual Link Rule ────────────────────────────
  $(document).on('click', '.ml-delete-btn', function () {
    const $btn = $(this);
    const id = $btn.data('id');
    if (!confirm('Delete this link rule?')) return;

    $btn.text('Deleting…').prop('disabled', true);

    UAM.ajax('uam_delete_manual_link', { link_id: id }, function () {
      $btn.closest('.uam-link-rule-item').fadeOut(300, function () {
        $(this).remove();
        const count = $('#ml-list .uam-link-rule-item').length;
        $('#ml-count').text(count);
        if (count === 0) {
          $('#ml-list').html('<div class="uam-placeholder">No manual link rules yet. Add one from the left panel.</div>');
        }
      });
    }, function (msg) {
      $btn.text('🗑️ Delete').prop('disabled', false);
      alert('Delete failed: ' + msg);
    });
  });

  // ── TAB 6: Auto-Link Rules (Enhanced) ──────────────────
  $(document).on('click', '#btn-add-rule', function () {
    const idx = $('#il-rules-container .il-rule-row').length;
    const html = `<div class="il-rule-row" data-index="${idx}">
      <input type="text" class="il-rule-keyword uam-input" placeholder="Keyword" value="" style="flex:1;">
      <input type="url" class="il-rule-url uam-input" placeholder="https://your-site.com/page" value="" style="flex:1.5;">
      <select class="il-rule-type uam-select" style="width:100px;">
        <option value="internal">🏠 Internal</option>
        <option value="external">🌍 External</option>
      </select>
      <input type="number" class="il-rule-max" placeholder="Max" min="1" max="10" value="1" title="Max per post" style="width:55px;">
      <label title="Nofollow" style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;">
        <input type="checkbox" class="il-rule-nofollow"> NF
      </label>
      <label title="New tab" style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;">
        <input type="checkbox" class="il-rule-newtab"> ↗
      </label>
      <button type="button" class="uam-btn uam-btn-sm il-rule-remove" title="Remove">✕</button>
    </div>`;
    $('#il-rules-container').append(html);
  });

  $(document).on('click', '.il-rule-remove', function () {
    $(this).closest('.il-rule-row').remove();
  });

  $(document).on('click', '#btn-save-rules', function () {
    const $btn = $(this);
    const $status = $('#il-rules-status');
    UAM.setLoading($btn, true);
    $status.text('Saving…').css('color', 'var(--uam-text2)');

    // Collect rules with new fields
    const rules = [];
    $('#il-rules-container .il-rule-row').each(function () {
      rules.push({
        keyword: $(this).find('.il-rule-keyword').val(),
        url: $(this).find('.il-rule-url').val(),
        max_links: $(this).find('.il-rule-max').val() || 1,
        type: $(this).find('.il-rule-type').val() || 'internal',
        nofollow: $(this).find('.il-rule-nofollow').is(':checked') ? 1 : 0,
        new_tab: $(this).find('.il-rule-newtab').is(':checked') ? 1 : 0,
      });
    });

    UAM.ajax('uam_save_autolink_rules', {
      rules: rules,
      autolink_enabled: $('#il-autolink-enabled').is(':checked') ? 1 : 0,
    }, function (data) {
      UAM.setLoading($btn, false);
      $status.text('✅ ' + (data.message || 'Saved!')).css('color', 'var(--uam-green)');
      setTimeout(() => $status.text(''), 3000);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
    });
  });
  // ════════════════════════════════════════════════════════
  // 13. IMAGE GENERATION
  // ════════════════════════════════════════════════════════

  /**
   * Shared image generation AJAX call.
   * @param {Object} params  - { title, excerpt, style, post_id, save_to_library, aspect_ratio }
   * @param {jQuery} $btn    - The button element (for loading state)
   * @param {jQuery} $preview - The preview container
   * @param {jQuery} $actions - The actions bar
   * @param {jQuery} $status  - Status text element
   */
  function generateImage(params, $btn, $preview, $actions, $status) {
    UAM.setLoading($btn, true);
    $status.text('🎨 Generating image... this may take up to 60s').css('color', 'var(--uam-text2)');
    $preview.html('<div class="uam-img-loading"><div class="uam-spinner"></div>Creating your image with AI...</div>');
    $actions.hide();

    UAM.ajax('uam_generate_image', {
      post_title: params.title || '',
      post_excerpt: params.excerpt || '',
      style: params.style || 'photorealistic',
      post_id: params.post_id || 0,
      save_to_library: params.save_to_library ? 1 : 0,
      aspect_ratio: params.aspect_ratio || '16:9',
    }, function (data) {
      UAM.setLoading($btn, false);
      const url = data.image_url || '';
      if (!url) {
        $status.text('⚠️ No image returned').css('color', 'var(--uam-amber)');
        $preview.html('<div class="uam-placeholder">No image was returned. Try again.</div>');
        return;
      }

      $status.text('✅ Image generated!' + (data.attach_id ? ' (saved to Media Library, ID: ' + data.attach_id + ')' : '')).css('color', 'var(--uam-green)');
      $preview.html('<img src="' + url + '" alt="AI Generated Image">');
      $actions.show();

      // Store URL on actions for download/copy
      $actions.data('image-url', url);
      $actions.find('a[download]').attr('href', url);

      setTimeout(function () { $status.text(''); }, 8000);
    }, function (msg) {
      UAM.setLoading($btn, false);
      $status.text('❌ ' + msg).css('color', 'var(--uam-red)');
      $preview.html('<div class="uam-placeholder">Image generation failed. Please try again.</div>');
    });
  }

  // ── Images AI Page: Featured Image Generator ──────────
  $(document).on('click', '#btn-gen-image', function () {
    const $btn = $(this);
    const title = $('#img-title').val().trim();
    if (!title) {
      $('#image-preview-area').html('<div class="uam-notice uam-notice-error">Please enter a title first.</div>');
      return;
    }
    generateImage({
      title: title,
      excerpt: $('#img-excerpt').val().trim(),
      style: $('#img-style').val(),
      post_id: $('#img-post-id').val() || 0,
      save_to_library: $('#img-save-library').is(':checked'),
      aspect_ratio: $('#img-ratio').val() || '1:1',
    }, $btn, $('#image-preview-area'), $('#image-actions'), $('<span>'));
  });

  // Auto-fill title from selected post (Image AI page)
  $(document).on('change', '#img-post-id', function () {
    const postId = $(this).val();
    if (postId !== '0') {
      const title = $(this).find('option:selected').text().trim();
      $('#img-title').val(title);
    }
  });

  // Download generated image (Image AI page)
  $(document).on('click', '#btn-download-image', function (e) {
    const url = $('#image-actions').data('image-url');
    if (url) {
      $(this).attr('href', url);
    }
  });

  // ── Blog Post: Inline Image Generator ─────────────────
  $(document).on('click', '#btn-bp-gen-image', function () {
    const $btn = $(this);
    let prompt = $('#bp-img-prompt').val().trim();
    const topic = $('#bp-topic').val().trim();

    // If no custom prompt, build from topic
    if (!prompt) {
      if (!topic) {
        $('#bp-img-status').text('⚠️ Enter a prompt or a post title first.').css('color', 'var(--uam-amber)');
        return;
      }
      prompt = topic;
    }

    generateImage({
      title: prompt,
      excerpt: '',
      style: $('#bp-img-style').val(),
      post_id: 0,
      save_to_library: $('#bp-img-save-library').is(':checked'),
      aspect_ratio: $('#bp-img-ratio').val(),
    }, $btn, $('#bp-img-preview'), $('#bp-img-actions'), $('#bp-img-status'));
  });

  // Auto-generate prompt from blog post title
  $(document).on('click', '#btn-bp-auto-prompt', function () {
    const topic = $('#bp-topic').val().trim();
    if (!topic) {
      $('#bp-img-status').text('⚠️ Enter a post title first.').css('color', 'var(--uam-amber)');
      setTimeout(function () { $('#bp-img-status').text(''); }, 3000);
      return;
    }
    const style = $('#bp-img-style').val();
    const prompt = 'A professional ' + style + ' image for a blog post titled: "' + topic + '". High quality, visually compelling, suitable as a featured image.';
    $('#bp-img-prompt').val(prompt);
    $('#bp-img-status').text('✨ Prompt generated from title!').css('color', 'var(--uam-green)');
    setTimeout(function () { $('#bp-img-status').text(''); }, 3000);
  });

  // Download (Blog Post inline)
  $(document).on('click', '#btn-bp-download-image', function (e) {
    const url = $('#bp-img-actions').data('image-url');
    if (url) {
      $(this).attr('href', url);
    }
  });

  // Copy image URL to clipboard (Blog Post inline)
  $(document).on('click', '#btn-bp-insert-image', function () {
    const url = $('#bp-img-actions').data('image-url');
    if (url) {
      navigator.clipboard.writeText(url).then(() => {
        $(this).text('✅ Copied!');
        setTimeout(() => $(this).text('📌 Copy Image URL'), 2000);
      });
    }
  });

  // Auto-generate prompt from post title (Image AI page)
  $(document).on('click', '#btn-img-auto-prompt', function () {
    const title = $('#img-title').val().trim();
    if (!title) {
      UAM.showToast('Please enter a post title first.', 'error');
      return;
    }
    const style = $('#img-style').val();
    const prompt = 'A professional, high-quality ' + style + ' image for a blog post titled: "' + title + '". Visually stunning, cinematic lighting, 8k resolution.';
    $('#img-excerpt').val(prompt);
    UAM.showToast('✨ AI prompt generated from title!');
  });

  // ════════════════════════════════════════════════════════
  // 14. INIT
  // ════════════════════════════════════════════════════════
  $(function () {
    // WP admin dark body overrides
    document.documentElement.style.setProperty('--wp-admin-theme-color', '#6366f1');

    // Make all result areas scrollable
    $('.uam-report-area, .uam-result-area').on('wheel', function (e) {
      e.stopPropagation();
    });
  });

  // ════════════════════════════════════════════════════════
  // 15. ADMIN CHATBOT
  // ════════════════════════════════════════════════════════
  (function () {
    if (!(window.uamAdmin && window.uamAdmin.chatbotEnabled)) return;

    const $panel  = $('#uam-admin-chatbot');
    const $toggle = $('#uam-admin-chat-toggle');
    const $close  = $('#uam-admin-chat-close');
    const $msgs   = $('#uam-achat-messages');
    const $input  = $('#uam-achat-input');
    const $send   = $('#uam-achat-send');
    const $typing = $('#uam-achat-typing');
    const pageCtx = (window.uamAdmin && window.uamAdmin.currentPage) || 'admin panel';

    let isOpen = false;

    // ── Open / close ───────────────────────────────────────────────────────
    function openPanel() {
      $panel.show();
      isOpen = true;
      $toggle.addClass('uam-admin-chat-btn--active');
      $input.focus();
    }
    function closePanel() {
      $panel.hide();
      isOpen = false;
      $toggle.removeClass('uam-admin-chat-btn--active');
    }

    $toggle.on('click', function () { isOpen ? closePanel() : openPanel(); });
    $close.on('click',  closePanel);

    // ── Helpers ────────────────────────────────────────────────────────────
    function scrollBottom() {
      $msgs.scrollTop($msgs[0].scrollHeight);
    }

    function addMessage(text, role /* 'user'|'bot'|'error' */) {
      const cls = role === 'user' ? 'uam-achat-user' : (role === 'error' ? 'uam-achat-error' : 'uam-achat-bot');
      const escaped = UAM.escHtml(text);
      // Convert simple markdown (bold, italic, code) in bot messages
      const rendered = role !== 'user'
        ? escaped
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.+?)`/g, '<code style="background:rgba(99,102,241,.18);padding:1px 5px;border-radius:4px;font-size:12px">$1</code>')
            .replace(/\n/g, '<br>')
        : escaped;
      const $msg = $('<div class="uam-achat-msg ' + cls + '"><div class="uam-achat-bubble">' + rendered + '</div></div>');
      $msgs.append($msg);
      scrollBottom();
    }

    // ── Send ───────────────────────────────────────────────────────────────
    function sendMessage() {
      const msg = $input.val().trim();
      if (!msg) return;

      addMessage(msg, 'user');
      $input.val('').css('height', 'auto');

      $typing.removeClass('uam-hidden');
      $send.prop('disabled', true);
      scrollBottom();

      $.ajax({
        url: UAM.ajaxUrl,
        method: 'POST',
        data: {
          action:  'uam_admin_chatbot',
          nonce:   UAM.nonce,
          message: msg,
          context: pageCtx,
        },
        success: function (res) {
          $typing.addClass('uam-hidden');
          $send.prop('disabled', false);
          if (res.success && res.data && res.data.result) {
            addMessage(res.data.result, 'bot');
          } else {
            const errMsg = (res.data && res.data.message) ? res.data.message : 'Something went wrong. Check API key in Settings.';
            addMessage('⚠️ ' + errMsg, 'error');
          }
        },
        error: function (xhr) {
          $typing.addClass('uam-hidden');
          $send.prop('disabled', false);
          addMessage('⚠️ Request failed: ' + (xhr.statusText || 'Network error'), 'error');
        },
      });
    }

    $send.on('click', sendMessage);

    // Enter to send (Shift+Enter = new line)
    $input.on('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-grow textarea
    $input.on('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
  })();

})(jQuery);

