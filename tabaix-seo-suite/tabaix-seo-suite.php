<?php
/**
 * Plugin Name: Tabaix All-in-One SEO & Optimizer
 * Plugin URI:  https://imagetight.tabaix.com/seo-plugin
 * Description: The all-in-one WordPress SEO + AI plugin — Content Generator, SEO Audit, Internal Linking, Alt Text, Image Optimizer (ImageTight), Table of Contents, Pros & Cons Schema, Auto Translate, Chatbot & more. Powered by YOUR OWN free Gemini or OpenAI API key.
 * Version:     2.0.0
 * Author:      Tayyab Ali (Tabaix)
 * Author URI:  https://tabaix.com
 * License:     GPL-2.0+
 * Text Domain: tabaix-seo-optimizer
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────
define('TSP_VERSION',     '2.0.0');
define('TSP_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('TSP_PLUGIN_URL',  plugin_dir_url(__FILE__));
define('TSP_PLUGIN_FILE', __FILE__);
define('TSP_TEXT_DOMAIN', 'tabaix-seo-optimizer');

// Legacy alias so all existing include files still work unchanged
define('UAM_VERSION',     TSP_VERSION);
define('UAM_PLUGIN_DIR',  TSP_PLUGIN_DIR);
define('UAM_PLUGIN_URL',  TSP_PLUGIN_URL);
define('UAM_PLUGIN_FILE', TSP_PLUGIN_FILE);
define('UAM_TEXT_DOMAIN', TSP_TEXT_DOMAIN);

// ─── Autoload Includes ────────────────────────────────────────────────────────
$includes = [
    // Core (original ultimate-ai-master modules — unchanged)
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

    // ── New features added in Tabaix SEO Suite Pro v2 ──
    'includes/class-tsp-toc.php',           // Table of Contents (auto + shortcode)
    'includes/class-tsp-pros-cons.php',     // Pros & Cons Schema block
    'includes/class-tsp-imagetight.php',    // ImageTight image optimizer module
    'includes/class-tsp-head-deduplicator.php', // Removes duplicate meta tags (works with ANY SEO plugin)
];

foreach ($includes as $file) {
    $path = UAM_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
function uam_init()
{
    // Core services
    UAM_Settings::get_instance();
    UAM_Ajax::get_instance();
    UAM_Chatbot::get_instance();
    UAM_Comment_Moderator::get_instance();
    UAM_Recommendations::get_instance();
    UAM_SEO_Meta::get_instance();
    UAM_Alt_Text::get_instance();

    // Auto-link filter
    if (UAM_Settings::get('autolink_enabled', 1)) {
        add_filter('the_content', ['UAM_Internal_Links', 'apply_manual_links'], 30);
    }

    // Admin only
    if (is_admin()) {
        UAM_Admin::get_instance();
        UAM_Editor_Links::get_instance();
        add_action('wp_ajax_uam_analyze_draft', ['UAM_Editor_Links', 'handle_analyze_draft']);

        // ImageTight module — admin AJAX + menu
        TSP_ImageTight::get_instance();

        // Wire ImageTight page into the main admin menu
        add_action('admin_menu', 'tsp_register_imagetight_menu', 35);
    }

    // Frontend features (work on both frontend & admin-ajax)
    TSP_TOC::get_instance();        // Table of Contents
    TSP_Pros_Cons::get_instance();  // Pros & Cons shortcode + AJAX
    TSP_ImageTight::get_instance(); // AJAX handlers also needed on frontend wp_ajax_*

    // Nuclear duplicate meta tag remover — works with ANY plugin combination
    // Runs last (inside wp_head output buffer) removing dupes before browser sees them
    TSP_Head_Deduplicator::get_instance();
}
add_action('plugins_loaded', 'uam_init');

/**
 * Register the ImageTight sub-menu under the main Tabaix SEO Suite menu.
 * The main menu is registered by UAM_Admin as 'uam-dashboard'.
 */
function tsp_register_imagetight_menu()
{
    add_submenu_page(
        'uam-dashboard',
        'Image Optimizer',
        '🗜️ Image Optimizer',
        'upload_files',
        'tsp-image-optimizer',
        function () {
            TSP_ImageTight::get_instance()->render_page();
        }
    );
}

// ─── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook(__FILE__, 'uam_activate');
function uam_activate()
{
    UAM_Settings::set_defaults();
    $opts = get_option(UAM_Settings::OPTION_KEY, []);
    if (!isset($opts['autolink_enabled'])) UAM_Settings::update('autolink_enabled', 1);
    if (!isset($opts['toc_enabled']))      UAM_Settings::update('toc_enabled', 1);
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'uam_deactivate');
function uam_deactivate()
{
    flush_rewrite_rules();
}
