=== Tabaix SEO Optimizer Pro ===
Contributors: tabaix, tabaix.com
Tags: image compression, webp, avif, seo, internal linking, artificial intelligence, content generation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive optimization plugin featuring local in-browser image compression (WebP/AVIF), automated AI internal linking, and content generation.

== Description ==

**Tabaix SEO Suite & ImageTight** is an all-in-one performance and SEO toolkit designed to fix slow load times and optimize your site architecture.

= Core Features =

**Image Optimizer (ImageTight)**
* Auto-Compress on Upload — Automatically convert large images securely.
* Next-Gen Formats — Deliver WebP or AVIF to fix Google PageSpeed warnings.
* HEIC Support — Upload iPhone photos (HEIC) without errors.
* Media Library Scanner — Find and replace heavy images with a single click.
* Edge-Powered — Offload heavy compression to external cloud processing to save your server CPU.

**AI Content & SEO Suite**
* Internal Link Scanner — Build intelligent site silos and find semantic link opportunities instantly.
* Content Generation — Draft structured blog outlines and meta descriptions.
* Dual AI Provider Support — Bring your own key for Google AI Studio (Gemini) or OpenAI.

= Usage =

1. Upload the plugin and activate it.
2. Navigate to **Tabaix Options** in your sidebar.
3. Under the **Image Optimizer** tab, configure your quality settings and format (WebP/AVIF).
4. Run the Media Library Scanner to find heavy legacy images.
5. Provide your optional Google Gemini API key to unlock the Internal Link Scanner AI.

== Installation ==

1. Upload the `tabaix-seo-optimizer` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress **Plugins** menu.
3. Configure your API options under the settings menu.

== Frequently Asked Questions ==

= Do I need a paid subscription to use this? =
No. The core plugin allows you to use your own free Google AI Studio key for text generation and internal linking analysis. 

= Does the image compression slow down my server? =
No. Image compression is offloaded to the Vercel Edge Network, meaning your WordPress hosting CPU is completely protected from heavy image processing tasks.

= What image formats are supported? =
WebP, AVIF, JPEG, and PNG. We also support native decoding of Apple HEIC files.

= How does the internal link scanner work? =
It analyzes your existing posts and uses advanced AI to build semantic relationships, suggesting exact anchor text and URLs to naturally interlink your content for better SEO structure.

== Screenshots ==

1. Dashboard with quick actions and site overview
2. Image Optimizer — Media Library scanner finding heavy images
3. Internal Linking — Semantic post suggestions
4. Content Generator — SEO meta drafting
5. Settings — API configuration

== External Services ==

This plugin connects to the following external services:

= ImageTight API =
Used for: Bulk image compression of WordPress media library images
Data sent: Image files from your media library when you run compression
API endpoint: https://imagetight-api.vercel.app
Terms of Service: https://imagetight.com/terms
Privacy Policy: https://imagetight.com/privacy

= OpenAI API =
Used for: AI-powered SEO suggestions and content analysis
Data sent: Post content and meta data when you use AI features
You must provide your own OpenAI API key
Terms of Service: https://openai.com/terms
Privacy Policy: https://openai.com/privacy

= Google Gemini API =
Used for: AI image generation and content suggestions
Data sent: Text prompts when you use AI generation features
You must provide your own Google AI Studio API key
Terms of Service: https://ai.google.dev/terms
Privacy Policy: https://policies.google.com/privacy

NOTE: The ImageTight compression feature sends image files to 
imagetight-api.vercel.app for processing. Images are not stored 
permanently. See privacy policy for details.

== Source Code ==
The full source code for this plugin is available at:
https://github.com/Tabaix/image-tight

== Changelog ==

= 2.0.0 =
* Initial public release as Tabaix All-in-One SEO & Optimizer.
* Integrated ImageTight web API for edge compression.
* Added Internal Link AI Scanner.
* Added support for Google Gemini via BYOK (Bring Your Own Key).

== Upgrade Notice ==

= 2.0.0 =
Initial release. No upgrade needed.
