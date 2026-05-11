<?php
if (!defined('ABSPATH')) exit;

/**
 * TSP_ImageTight — Image Compression Module for Tabaix All-in-One SEO & Optimizer
 *
 * Integrates the full ImageTight plugin functionality directly into the
 * Tabaix All-in-One SEO & Optimizer admin interface as a dedicated module tab.
 *
 * The standalone imagetight-companion.php plugin still works independently.
 * This module SHARES the same option keys (itc_api_key etc.) so settings
 * are unified if both are installed — or fully independent if only suite is used.
 *
 * Features:
 *  - Scan media library for heavy/unoptimized images
 *  - 1-click compress via ImageTight Vercel Edge API
 *  - Bulk optimize all heavy images
 *  - Restore from backup
 *  - Auto-compress on upload (optional)
 *  - Quota checker
 *  - Settings (API key, quality, format, threshold, backup)
 */
class TABAIX_SEO_ImageTight
{
    private static $instance = null;

    // Shared option keys (same as standalone plugin for compatibility)
    const OPT_API_KEY   = 'tabaix_seo_itc_api_key';
    const OPT_QUALITY   = 'tabaix_seo_itc_compression_quality';
    const OPT_FORMAT    = 'tabaix_seo_itc_output_format';
    const OPT_THRESHOLD = 'tabaix_seo_itc_scan_threshold';
    const OPT_AUTO      = 'tabaix_seo_itc_auto_compress';
    const OPT_BACKUP    = 'tabaix_seo_itc_backup_originals';
    const OPT_GEMINI_KEY= 'tabaix_seo_itc_gemini_api_key';
    const OPT_LANGUAGE  = 'tabaix_seo_itc_language';

    const API_ENDPOINT  = 'https://imagetight-api.vercel.app/api/compress';
    const QUOTA_URL     = 'https://imagetight-api.vercel.app/api/quota';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register AJAX handlers (prefixed tsp_ to avoid conflicts with standalone)
        add_action('wp_ajax_tabaix_seo_itc_scan',         [$this, 'ajax_scan']);
        add_action('wp_ajax_tabaix_seo_itc_compress',      [$this, 'ajax_compress']);
        add_action('wp_ajax_tabaix_seo_itc_restore',       [$this, 'ajax_restore']);
        add_action('wp_ajax_tabaix_seo_itc_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_tabaix_seo_itc_quota',         [$this, 'ajax_quota']);

        // Auto-compress on upload (if enabled)
        add_filter('wp_generate_attachment_metadata', [$this, 'auto_compress_on_upload'], 10, 2);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts($hook)
    {
        // Only load on our settings page or if it's the media page
        if (strpos($hook, 'uam-seo-suite') === false && $hook !== 'upload.php') return;

        wp_enqueue_script('tsp-imagetight-script', plugins_url('../assets/js/tsp-imagetight.js', __FILE__), ['jquery'], '1.0.0', true);
        
        $api_key = get_option(self::OPT_API_KEY, '');
        wp_localize_script('tsp-imagetight-script', 'tsp_itc_data', [
            'nonce'  => wp_create_nonce('tabaix_seo_admin_nonce'),
            'hasKey' => !empty($api_key)
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // ADMIN PAGE HTML
    // ══════════════════════════════════════════════════════════════════
    public function render_page()
    {
        global $wpdb;
        $api_key    = get_option(self::OPT_API_KEY, '');
        $quality    = (int)get_option(self::OPT_QUALITY, 75);
        $format     = get_option(self::OPT_FORMAT, 'webp');
        $threshold  = (int)get_option(self::OPT_THRESHOLD, 150);
        $auto       = (int)get_option(self::OPT_AUTO, 0);
        $backup     = (int)get_option(self::OPT_BACKUP, 1);
        $gemini_key = get_option(self::OPT_GEMINI_KEY, '');
        $language   = get_option(self::OPT_LANGUAGE, 'English');
        $has_key    = !empty($api_key);

        $total_img  = wp_count_attachments('image');
        $total      = isset($total_img->inherit) ? (int)$total_img->inherit : 0;
        $optimized  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_itc_is_optimized' AND meta_value='1'");
        $saved_raw  = (int)$wpdb->get_var("SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key='_itc_bytes_saved'");
        $saved_fmt  = $saved_raw ? size_format($saved_raw, 2) : '0 B';
        $pending    = max(0, $total - $optimized);
        $pct        = $total > 0 ? round(($optimized / $total) * 100) : 0;
        ?>

        <div class="tsp-wrap wrap">
            <div class="tss-header">
                <span class="dashicons dashicons-performance" style="font-size:28px;width:28px;height:28px;"></span>
                <div>
                    <h1>🗜️ ImageTight — Image Optimizer</h1>
                    <small style="color:#94A3B8;">Compress & convert your WordPress media library via Vercel Edge API</small>
                </div>
                <span id="tsp-quota-badge" style="margin-left:auto;background:#1E293B;color:#22C55E;padding:10px 18px;border-radius:10px;font-weight:800;font-size:13px;display:none;">
                    📊 <span id="tsp-quota-val">—</span> credits left
                </span>
            </div>

            <?php if (!$has_key): ?>
            <div class="tss-notice tss-notice-warn">
                ⚠️ <strong>No ImageTight API key set.</strong>
                Get your free API key at <a href="https://imagetight.tabaix.com" target="_blank"><strong>imagetight.tabaix.com</strong></a>
                then enter it below in Settings.
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                <?php
                $stats = [
                    ['label'=>'Total Images',   'value'=>$total,       'color'=>'#0F172A'],
                    ['label'=>'Optimized',       'value'=>$optimized,   'color'=>'#16A34A'],
                    ['label'=>'Pending',         'value'=>$pending,     'color'=>'#DC2626'],
                    ['label'=>'Storage Saved',   'value'=>$saved_fmt,   'color'=>'#2563EB'],
                ];
                foreach ($stats as $s):
                ?>
                <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;text-align:center;">
                    <div style="font-size:30px;font-weight:900;color:<?php echo esc_attr($s['color']); ?>;"><?php echo esc_html($s['value']); ?></div>
                    <div style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;margin-top:4px;"><?php echo esc_html($s['label']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabs -->
            <div class="tss-tabs">
                <a class="tss-tab active" href="#" data-tab="tsp-tab-scan">📂 Media Scanner</a>
                <a class="tss-tab" href="#" data-tab="tsp-tab-optimized">✅ Optimized Images</a>
                <a class="tss-tab" href="#" data-tab="tsp-tab-settings">⚙️ Settings</a>
            </div>

            <!-- Tab: Scanner -->
            <div id="tsp-tab-scan" class="tsp-tab-pane tss-section" style="display:block;">
                <h2>📂 Media Library Scanner</h2>
                <p style="color:#64748B;font-size:13px;margin-bottom:16px;">
                    Scan your media library for heavy images (over <?php echo intval($threshold); ?>KB) and compress them 1-click to WebP/AVIF via the ImageTight API.
                </p>
                <div style="display:flex;gap:10px;margin-bottom:20px;align-items:center;flex-wrap:wrap;">
                    <button id="tsp-scan-btn" class="tss-btn" <?php echo !$has_key ? 'disabled' : ''; ?>>
                        🔍 Scan for Heavy Images
                    </button>
                    <button id="tsp-bulk-btn" class="tss-btn" style="display:none;background:#6366F1;" <?php echo !$has_key ? 'disabled' : ''; ?>>
                        🚀 Bulk Optimize All Pending
                    </button>
                    <span id="tsp-scan-status" style="font-size:13px;color:#64748B;"></span>
                </div>
                <div id="tsp-progress-wrap" style="display:none;background:#E2E8F0;border-radius:8px;overflow:hidden;height:10px;margin-bottom:16px;">
                    <div id="tsp-progress-bar" style="height:100%;background:#22C55E;width:0%;transition:width .3s;"></div>
                </div>
                <div id="tsp-scan-results"></div>
            </div>

            <!-- Tab: Optimized -->
            <div id="tsp-tab-optimized" class="tsp-tab-pane tss-section" style="display:none;">
                <h2>✅ Optimized Images — Restore Backup</h2>
                <div id="tsp-optimized-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
                <?php
                $opt_query = new WP_Query([
                    'post_type'       => 'attachment',
                    'post_status'     => 'inherit',
                    'posts_per_page'  => 60,
                    'meta_key'        => '_itc_is_optimized',
                    'meta_value'      => '1',
                ]);
                if ($opt_query->have_posts()):
                    foreach ($opt_query->posts as $att):
                        $thumb      = wp_get_attachment_image_url($att->ID, 'thumbnail');
                        $saved_b    = (int)get_post_meta($att->ID, '_itc_bytes_saved', true);
                        $has_backup = !empty(get_post_meta($att->ID, '_itc_backup_path', true));
                ?>
                    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:12px;text-align:center;">
                        <img src="<?php echo esc_url($thumb); ?>" style="width:100%;height:100px;object-fit:cover;border-radius:8px;margin-bottom:8px;">
                        <div style="font-size:11px;color:#065F46;font-weight:700;">💾 <?php echo esc_html(size_format($saved_b)); ?> saved</div>
                        <?php if ($has_backup): ?>
                        <button class="tss-btn tss-btn-sm tsp-restore-btn" data-id="<?php echo esc_attr($att->ID); ?>"
                            style="background:#EF4444;margin-top:8px;font-size:11px;padding:4px 10px;">
                            ↩ Restore
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach;
                else: ?>
                    <p style="color:#94A3B8;font-style:italic;">No optimized images yet. Run the scanner first.</p>
                <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Settings -->
            <div id="tsp-tab-settings" class="tsp-tab-pane tss-section" style="display:none;">
                <h2>⚙️ ImageTight Settings</h2>
                <div class="tss-notice">
                    💡 Get your free API key at
                    <a href="https://imagetight.tabaix.com/pricing" target="_blank"><strong>imagetight.tabaix.com/pricing</strong></a>
                    — free tier includes 100 compressions/month.
                </div>
                <div class="tss-row">
                    <label class="tss-label">ImageTight API Key</label>
                    <input type="password" id="tsp-itc-apikey" class="tss-input" style="max-width:500px;"
                        value="<?php echo esc_attr($api_key); ?>" placeholder="it_live_..." />
                    <button class="tss-btn tss-btn-sm" id="tsp-itc-test-key" style="margin-top:8px;">🔑 Test & Save Key</button>
                    <span id="tsp-itc-key-status" style="font-size:12px;margin-left:10px;"></span>
                </div>
                <div class="tss-row" style="margin-top:20px;background:rgba(59,130,246,0.05);padding:16px;border-radius:12px;border:1px solid rgba(59,130,246,0.2);">
                    <label class="tss-label" style="color:#2563EB;">✨ Google Gemini AI Key (Free AI Alt Text)</label>
                    <p style="font-size:12px;color:#64748B;margin-bottom:10px;">Enter your free Gemini API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>. This will securely trigger our Vercel Edge API to generate high-quality SEO Alt Text for your images during compression!</p>
                    <input type="password" id="tsp-itc-gemini-key" class="tss-input" style="max-width:500px;"
                        value="<?php echo esc_attr($gemini_key); ?>" placeholder="AIzaSy..." />
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
                    <div class="tss-row">
                        <label class="tss-label">Output Format</label>
                        <select id="tsp-itc-format" class="tss-input">
                            <option value="webp" <?php selected($format,'webp'); ?>>WebP (Recommended)</option>
                            <option value="avif" <?php selected($format,'avif'); ?>>AVIF (Smallest)</option>
                            <option value="jpeg" <?php selected($format,'jpeg'); ?>>JPEG (Compatible)</option>
                        </select>
                    </div>
                    <div class="tss-row">
                        <label class="tss-label">AI Alt Text Language</label>
                        <select id="tsp-itc-language" class="tss-input">
                            <?php
                            $languages = ['English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Russian', 'Chinese', 'Japanese', 'Korean', 'Arabic', 'Urdu', 'Hindi', 'Bengali'];
                            foreach ($languages as $lang) {
                                echo '<option value="' . esc_attr($lang) . '" ' . selected($language, $lang, false) . '>' . esc_html($lang) . '</option>';
                            }
                            ?>
                        </select>
                        <small style="color:#94A3B8;">Select the language for your AI-generated alt text.</small>
                    </div>
                    <div class="tss-row">
                        <label class="tss-label">Quality (1–100)</label>
                        <input type="number" id="tsp-itc-quality" class="tss-input" value="<?php echo esc_attr($quality); ?>" min="1" max="100" />
                    </div>
                    <div class="tss-row">
                        <label class="tss-label">Heavy Image Threshold (KB)</label>
                        <input type="number" id="tsp-itc-threshold" class="tss-input" value="<?php echo esc_attr($threshold); ?>" min="50" />
                        <small style="color:#94A3B8;">Images larger than this will be flagged for compression.</small>
                    </div>
                    <div class="tss-row">
                        <label class="tss-label">Options</label>
                        <label style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                            <input type="checkbox" id="tsp-itc-auto" <?php checked($auto,1); ?>> Auto-compress new uploads
                        </label>
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" id="tsp-itc-backup" <?php checked($backup,1); ?>> Keep backup of originals
                        </label>
                    </div>
                </div>
                <button class="tss-btn" id="tsp-itc-save-settings" style="margin-top:20px;">💾 Save Settings</button>
                <span id="tsp-itc-save-status" style="font-size:12px;margin-left:12px;color:#065F46;"></span>
            </div>
        </div>
        <?php
    }

    // ══════════════════════════════════════════════════════════════════
    // AJAX: Scan for heavy images
    // ══════════════════════════════════════════════════════════════════
    public function ajax_scan()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error();

        $threshold_kb = (int)get_option(self::OPT_THRESHOLD, 150) * 1024;

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 200,
            'meta_query'     => [['key' => '_itc_is_optimized', 'compare' => 'NOT EXISTS']],
        ]);

        $results = [];
        foreach ($attachments as $att) {
            $path = get_attached_file($att->ID);
            if (!$path || !file_exists($path)) continue;
            $size = filesize($path);
            if ($size < $threshold_kb) continue;

            $results[] = [
                'id'       => $att->ID,
                'filename' => basename($path),
                'path'     => $path,
                'size'     => $size,
                'size_fmt' => size_format($size, 2),
                'thumb'    => wp_get_attachment_image_url($att->ID, 'thumbnail'),
            ];
        }

        wp_send_json_success(['images' => $results, 'count' => count($results)]);
    }

    // ══════════════════════════════════════════════════════════════════
    // AJAX: Compress single image
    // ══════════════════════════════════════════════════════════════════
    public function ajax_compress()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error();

        $image_id = (int)($_POST['image_id'] ?? 0);
        $api_key  = get_option(self::OPT_API_KEY, '');
        $quality  = (int)get_option(self::OPT_QUALITY, 75);
        $format   = get_option(self::OPT_FORMAT, 'webp');
        $do_backup= (int)get_option(self::OPT_BACKUP, 1);
        $gemini_key= get_option(self::OPT_GEMINI_KEY, '');
        $language = get_option(self::OPT_LANGUAGE, 'English');

        if (!$image_id || empty($api_key)) wp_send_json_error(['message' => 'Missing image ID or API key.']);

        $file_path = get_attached_file($image_id);
        if (!$file_path || !file_exists($file_path)) wp_send_json_error(['message' => 'File not found.']);

        $original_size = filesize($file_path);

        // Backup original
        $backup_path = '';
        if ($do_backup) {
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/imagetight-backups/';
            wp_mkdir_p($backup_dir);
            $backup_path = $backup_dir . basename($file_path) . '.itc_backup';
            copy($file_path, $backup_path);
        }

        // Send to ImageTight API
        $file_data = file_get_contents($file_path);
        $boundary  = wp_generate_password(20, false);
        $body      = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"image\"; filename=\"" . basename($file_path) . "\"\r\n"
            . "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n"
            . $file_data . "\r\n"
            . "--{$boundary}--";

        $api_url = add_query_arg([
            'api_key' => $api_key,
            'quality' => $quality,
            'format'  => $format,
            'language'=> $language,
        ], self::API_ENDPOINT);

        if (!empty($gemini_key)) {
            $api_url = add_query_arg('gemini_key', $gemini_key, $api_url);
        }

        $response = wp_remote_post($api_url, [
            'timeout' => 60,
            'headers' => ['Content-Type' => "multipart/form-data; boundary={$boundary}"],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            if ($backup_path && file_exists($backup_path)) wp_delete_file($backup_path);
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if ($backup_path && file_exists($backup_path)) wp_delete_file($backup_path);
            wp_send_json_error(['message' => "API error (HTTP {$code})"]);
        }

        // Save compressed file
        $compressed = wp_remote_retrieve_body($response);
        file_put_contents($file_path, $compressed);

        $new_size   = strlen($compressed);
        $saved      = max(0, $original_size - $new_size);

        // Check for AI Generated Alt Text in headers (base64 encoded)
        $alt_text_b64 = wp_remote_retrieve_header($response, 'x-generated-alt');
        if (!empty($alt_text_b64)) {
            $alt_text = base64_decode($alt_text_b64);
            if ($alt_text) {
                update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            }
        }

        // Update metadata
        update_post_meta($image_id, '_itc_is_optimized', '1');
        update_post_meta($image_id, '_itc_bytes_saved',  $saved);
        update_post_meta($image_id, '_itc_original_size', $original_size);
        if ($backup_path) update_post_meta($image_id, '_itc_backup_path', $backup_path);

        wp_send_json_success([
            'saved'     => $saved,
            'saved_fmt' => size_format($saved, 2),
            'new_size'  => size_format($new_size, 2),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // AJAX: Restore original
    // ══════════════════════════════════════════════════════════════════
    public function ajax_restore()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error();

        $image_id    = (int)($_POST['image_id'] ?? 0);
        $backup_path = get_post_meta($image_id, '_itc_backup_path', true);

        if (empty($backup_path) || !file_exists($backup_path)) {
            wp_send_json_error(['message' => 'Backup file not found.']);
        }

        $file_path = get_attached_file($image_id);
        copy($backup_path, $file_path);
        wp_delete_file($backup_path);

        delete_post_meta($image_id, '_itc_is_optimized');
        delete_post_meta($image_id, '_itc_bytes_saved');
        delete_post_meta($image_id, '_itc_backup_path');
        delete_post_meta($image_id, '_itc_original_size');

        wp_send_json_success(['message' => 'Image restored.']);
    }

    // ══════════════════════════════════════════════════════════════════
    // AJAX: Save settings
    // ══════════════════════════════════════════════════════════════════
    public function ajax_save_settings()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        update_option(self::OPT_API_KEY,   sanitize_text_field($_POST['api_key'] ?? ''));
        update_option(self::OPT_QUALITY,   min(100, max(1, (int)($_POST['quality'] ?? 75))));
        update_option(self::OPT_FORMAT,    sanitize_key($_POST['format'] ?? 'webp'));
        update_option(self::OPT_THRESHOLD, max(50, (int)($_POST['threshold'] ?? 150)));
        update_option(self::OPT_AUTO,      (int)($_POST['auto'] ?? 0));
        update_option(self::OPT_BACKUP,    (int)($_POST['backup'] ?? 1));
        update_option(self::OPT_GEMINI_KEY, sanitize_text_field($_POST['gemini_key'] ?? ''));
        update_option(self::OPT_LANGUAGE,  sanitize_text_field($_POST['language'] ?? 'English'));

        wp_send_json_success('Settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════
    // AJAX: Check quota
    // ══════════════════════════════════════════════════════════════════
    public function ajax_quota()
    {
        check_ajax_referer('tabaix_seo_admin_nonce', 'nonce');
        $api_key = get_option(self::OPT_API_KEY, '');
        if (empty($api_key)) wp_send_json_error('No API key');

        $url      = add_query_arg('api_key', urlencode($api_key), self::QUOTA_URL);
        $response = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($response)) wp_send_json_error();

        wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
    }

    // ══════════════════════════════════════════════════════════════════
    // Auto-compress on upload
    // ══════════════════════════════════════════════════════════════════
    public function auto_compress_on_upload($metadata, $attachment_id)
    {
        if (!(int)get_option(self::OPT_AUTO, 0)) return $metadata;
        if (empty(get_option(self::OPT_API_KEY, ''))) return $metadata;

        $_POST['image_id'] = $attachment_id;
        $_REQUEST['nonce'] = wp_create_nonce('tabaix_seo_admin_nonce');

        $this->ajax_compress();
        return $metadata;
    }
}
