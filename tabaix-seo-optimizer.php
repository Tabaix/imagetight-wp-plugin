<?php
/**
 * Plugin Name: Tabaix SEO Optimizer Pro
 * Plugin URI:  https://imagetight.com/seo-plugin
 * Description: The all-in-one WordPress SEO + AI plugin — Content Generator, SEO Audit, Internal Linking, Alt Text, Image Optimizer (ImageTight), Table of Contents, Pros & Cons Schema, Auto Translate, Chatbot & more. Powered by YOUR OWN free Gemini or OpenAI API key.
 * Version:     2.0.0
 * Author:      Tayyab Ali (Tabaix)
 * Author URI:  https://tabaix.com
 * License:     GPL-2.0+
 * Text Domain: tabaix-seo-optimizer-pro
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────
define('TABAIX_SEO_VERSION',     '2.0.0');
define('TABAIX_SEO_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('TABAIX_SEO_PLUGIN_URL',  plugin_dir_url(__FILE__));
define('TABAIX_SEO_PLUGIN_FILE', __FILE__);
define('TABAIX_SEO_TEXT_DOMAIN', 'tabaix-seo-optimizer-pro');

// ─── Autoload Includes ────────────────────────────────────────────────────────
$includes = [
    'includes/class-uam-settings.php',
    'includes/class-uam-api.php',
    'includes/class-uam-content-generator.php',
    'includes/class-uam-seo-optimizer.php',
    'includes/class-uam-image-generator.php',
    'includes/class-uam-analytics.php',
    'includes/class-uam-comment-moderator.php',
    'includes/class-uam-recommendations.php',
    'includes/class-uam-seo-meta.php',
    'includes/class-uam-alt-text.php',
    'includes/class-uam-internal-links.php',
    'includes/class-uam-editor-links.php',
    'includes/class-uam-chatbot.php',
    'includes/class-uam-ajax.php',
    'includes/class-uam-admin.php',
    'includes/class-tsp-toc.php',
    'includes/class-tsp-pros-cons.php',
    'includes/class-tsp-imagetight.php',
    'includes/class-tsp-head-deduplicator.php',
    'includes/class-tsp-social-share.php',
    'includes/class-tsp-seo-translator.php',
];

foreach ($includes as $file) {
    $path = TABAIX_SEO_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
function tabaix_seo_init()
{
    // Core services
    TABAIX_SEO_Settings::get_instance();
    TABAIX_SEO_Ajax::get_instance();
    TABAIX_SEO_Chatbot::get_instance();
    TABAIX_SEO_Comment_Moderator::get_instance();
    TABAIX_SEO_Recommendations::get_instance();
    TABAIX_SEO_SEO_Meta::get_instance();
    TABAIX_SEO_Alt_Text::get_instance();

    // Auto-link filter
    if (TABAIX_SEO_Settings::get('autolink_enabled', 1)) {
        add_filter('the_content', ['TABAIX_SEO_Internal_Links', 'apply_manual_links'], 30);
    }

    // Admin only
    if (is_admin()) {
        TABAIX_SEO_Admin::get_instance();
        TABAIX_SEO_Editor_Links::get_instance();
        add_action('wp_ajax_tabaix_seo_analyze_draft', ['TABAIX_SEO_Editor_Links', 'handle_analyze_draft']);

        // ImageTight module — admin AJAX + menu
        TABAIX_SEO_ImageTight::get_instance();

        // Wire ImageTight page into the main admin menu
        add_action('admin_menu', 'tabaix_seo_register_imagetight_menu', 35);
    }

    // Frontend features
    TABAIX_SEO_TOC::get_instance();
    TABAIX_SEO_Pros_Cons::get_instance();
    TABAIX_SEO_ImageTight::get_instance();
    TABAIX_SEO_Head_Deduplicator::get_instance();
    TABAIX_SEO_Social_Share::get_instance();
    TABAIX_SEO_SEO_Translator::get_instance();
}
add_action('plugins_loaded', 'tabaix_seo_init');

/**
 * Register the ImageTight sub-menu under the main Tabaix SEO Suite menu.
 */
function tabaix_seo_register_imagetight_menu()
{
    add_submenu_page(
        'tabaix-seo-dashboard',
        'Image Optimizer',
        '🗜️ Image Optimizer',
        'upload_files',
        'tabaix-seo-image-optimizer',
        function () {
            TABAIX_SEO_ImageTight::get_instance()->render_page();
        }
    );
}

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook(__FILE__, 'tabaix_seo_activate');
function tabaix_seo_activate()
{
    TABAIX_SEO_Settings::set_defaults();
    $opts = get_option(TABAIX_SEO_Settings::OPTION_KEY, []);
    if (!isset($opts['autolink_enabled'])) TABAIX_SEO_Settings::update('autolink_enabled', 1);
    if (!isset($opts['toc_enabled']))      TABAIX_SEO_Settings::update('toc_enabled', 1);
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'tabaix_seo_deactivate');
function tabaix_seo_deactivate()
{
    flush_rewrite_rules();
}
