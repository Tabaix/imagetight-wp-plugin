<?php
/**
 * Plugin Name: ImageTight Pro Companion
 * Plugin URI:  https://imagetight.tabaix.com
 * Description: The ultimate 1-click in-browser image compressor and SEO optimizer for WordPress. Powered by ImageTight design.
 * Version:     2.0.0
 * Author:      Tabaix
 * Author URI:  https://tabaix.com
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ITC_Pro_Companion {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_itc_scan_images', array( $this, 'ajax_scan_images' ) );
        add_action( 'wp_ajax_itc_process_image', array( $this, 'ajax_process_image' ) );
        add_action( 'wp_ajax_itc_save_key', array( $this, 'ajax_save_key' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_compress_upload' ), 10, 2 );
        add_action( 'wp_ajax_itc_check_quota', array( $this, 'ajax_check_quota' ) );
    }

    public function ajax_save_key() {
        check_ajax_referer( 'itc_admin_nonce', 'nonce' );
        if(isset($_POST['api_key'])) {
            update_option('itc_api_key', sanitize_text_field($_POST['api_key']));
            wp_send_json_success('Settings securely saved.');
        }
        wp_send_json_error();
    }

    public function ajax_check_quota() {
        check_ajax_referer( 'itc_admin_nonce', 'nonce' );
        $api_key = get_option('itc_api_key');
        if(empty($api_key)) wp_send_json_error('No key set');
        
        $api_url = 'https://imagetight-api.vercel.app/api/compress';
        $quota_url = str_replace('/compress', '/quota', $api_url) . '?api_key=' . urlencode($api_key);
        
        $response = wp_remote_get($quota_url);
        if(is_wp_error($response)) wp_send_json_error();
        
        wp_send_json_success(json_decode(wp_remote_retrieve_body($response)));
    }

    public function add_admin_menu() {
        add_menu_page( 'ImageTight Pro', 'ImageTight Pro', 'upload_files', 'imagetight', array( $this, 'admin_page_html' ), 'dashicons-performance', 10 );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'toplevel_page_imagetight' ) return;
        wp_enqueue_style( 'itc-admin-style', plugins_url( 'assets/admin.css', __FILE__ ), array(), '2.0.0' );
        wp_enqueue_script( 'itc-admin-script', plugins_url( 'assets/admin.js', __FILE__ ), array( 'jquery' ), '2.0.0', true );
        wp_localize_script( 'itc-admin-script', 'itc_data', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'itc_admin_nonce' ),
        ) );
    }

    public function admin_page_html() {
        if ( ! current_user_can( 'upload_files' ) ) return;
        ?>
        <style>
            #itc-bulk-btn:hover { background: #4f46e5 !important; transform: scale(1.02); }
            #itc-bulk-btn:disabled { opacity: 0.5; cursor: not-allowed; }
            .itc-progress-bar-wrap { width: 100%; background: #e2e8f0; height: 10px; border-radius: 5px; margin-bottom: 20px; display: none; overflow: hidden; }
            .itc-progress-bar { height: 100%; background: #22c55e; width: 0%; transition: width 0.3s ease; }
        </style>
        <div class="itc-wrap">
            <header class="itc-header">
                <div>
                    <h1 class="itc-title"><span class="dashicons dashicons-performance"></span> IMAGETIGHT PRO</h1>
                    <p class="itc-subtitle">Powered by the ImageTight Cloud API.</p>
                </div>
                <div class="itc-stats">
                    <div class="stat-box" style="margin-bottom: 10px;">
                        <span class="stat-label">API Key</span>
                        <input type="password" id="itc-api-key" value="<?php echo esc_attr(get_option('itc_api_key')); ?>" placeholder="License Key..." style="width:200px; padding: 5px; font-size:12px;" />
                        <button onclick="itc_save_key()" class="button button-secondary">Save Settings</button>
                    </div>
                    <div id="itc-quota-display" style="display:none; background:#1e293b; color:#fff; padding:10px 15px; border-radius:5px; font-size:13px;">
                        <span class="dashicons dashicons-chart-pie"></span> Quota Remaining: <strong id="itc-quota-val">Loading...</strong>
                    </div>
                </div>
            </header>

            <div class="itc-actions" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button id="itc-scan-btn" class="itc-btn itc-btn-primary">Scan Media Library</button>
                <button id="itc-bulk-btn" class="itc-btn pointer" style="display: none; background: #6366f1; color: white; border: none; padding: 10px 20px; font-weight: 800; text-transform: uppercase; border-radius: 8px; cursor: pointer;">Bulk Optimize All 🚀</button>
            </div>

            <div id="itc-bulk-progress" class="itc-progress-bar-wrap">
                <div class="itc-progress-bar"></div>
            </div>

            <div id="itc-results-grid" class="itc-grid" style="display: none;">
                <!-- Loaded via AJAX -->
            </div>
        </div>
        <?php
    }

    public function ajax_scan_images() {
        check_ajax_referer( 'itc_admin_nonce', 'nonce' );
        $threshold = 150 * 1024; // 150KB threshold
        $heavy_images = array();

        $query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 500,
            'post_mime_type' => array( 'image/jpeg', 'image/png' ),
        ) );

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $file = get_attached_file( $post->ID );
                if ( file_exists( $file ) || strpos($file, 'http') === 0 ) { // Accommodate some CDNs returning URLs
                    $size = @filesize( $file );
                    if ( !$size && function_exists('curl_init') ) {
                         // Fallback size check for remote files
                         $ch = curl_init(wp_get_attachment_url($post->ID));
                         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                         curl_setopt($ch, CURLOPT_HEADER, TRUE);
                         curl_setopt($ch, CURLOPT_NOBODY, TRUE);
                         $data = curl_exec($ch);
                         $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                         curl_close($ch);
                    }

                    if ( $size > $threshold ) {
                        $alt = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
                        $heavy_images[] = array(
                            'id'        => $post->ID,
                            'title'     => $post->post_title,
                            'filename'  => wp_basename( $file ),
                            'size_raw'  => $size,
                            'size'      => size_format( $size, 2 ),
                            'thumb'     => wp_get_attachment_image_url( $post->ID, 'medium' ),
                            'url'       => wp_get_attachment_url( $post->ID ),
                            'alt'       => $alt
                        );
                    }
                }
            }
        }
        wp_send_json_success( array( 'count' => count( $heavy_images ), 'images' => $heavy_images ) );
    }

    private static function replace_urls_in_db($attachment_id, $new_filename, $old_file) {
        global $wpdb;
        $old_url_guess = str_replace( $new_filename, basename( $old_file ), wp_get_attachment_url( $attachment_id ) );
        $new_url = wp_get_attachment_url( $attachment_id );
        
        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $old_url_guess, $new_url ) );
        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s", $old_url_guess, $new_url, '%' . $wpdb->esc_like($old_url_guess) . '%' ) );
    }

    public function auto_compress_upload( $metadata, $attachment_id ) {
        if ( ! current_user_can( 'upload_files' ) ) return $metadata;
        $api_key = get_option('itc_api_key');
        if ( empty($api_key) ) return $metadata; // Skip if no API key
        
        $api_url = 'https://imagetight-api.vercel.app/api/compress';
        $old_file = get_attached_file( $attachment_id );
        $mime_type = get_post_mime_type($attachment_id);
        
        // Only run for original jpeg/png uploads to prevent recursive loops on WebP
        if ( function_exists('curl_init') && file_exists($old_file) && in_array($mime_type, ['image/jpeg', 'image/png']) ) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => $api_url, 
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_POST => true,
              CURLOPT_POSTFIELDS => array(
                'api_key' => $api_key,
                'domain'  => site_url(),
                'image' => new CURLFile($old_file, mime_content_type($old_file), basename($old_file))
              ),
            ));
            
            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpcode == 200 && $response) {
                $path_info = pathinfo( $old_file );
                $new_filename = $path_info['filename'] . '.webp';
                $new_filepath = $path_info['dirname'] . '/' . $new_filename;
                
                file_put_contents($new_filepath, $response);
                
                if ( $old_file !== $new_filepath ) {
                    @unlink( $old_file );
                    if ( isset($metadata['sizes']) ) {
                        foreach ( $metadata['sizes'] as $size => $size_info ) @unlink( $path_info['dirname'] . '/' . $size_info['file'] );
                    }
                }
                update_attached_file( $attachment_id, $new_filepath );
                wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' ) );
                
                // Do not recursive loop standard wp_generate_attachment_metadata.
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $new_metadata = wp_generate_attachment_metadata( $attachment_id, $new_filepath );
                self::replace_urls_in_db($attachment_id, $new_filename, $old_file);
                
                return $new_metadata; 
            }
        }
        return $metadata; 
    }

    public function ajax_process_image() {
        check_ajax_referer( 'itc_admin_nonce', 'nonce' );
        
        $attachment_id = intval( $_POST['attachment_id'] );
        $alt_text = sanitize_text_field( $_POST['alt_text'] );
        $title_text = sanitize_text_field( $_POST['title_text'] );

        // Update SEO Data instantly
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
        wp_update_post( array( 'ID' => $attachment_id, 'post_title' => $title_text ) );

        // If a file was uploaded, process the replacement
        if ( ! empty( $_FILES['replacement_file'] ) && $_FILES['replacement_file']['error'] === UPLOAD_ERR_OK ) {
            $uploaded_file = $_FILES['replacement_file'];
            $old_file = get_attached_file( $attachment_id );

            if ( $old_file && file_exists( $old_file ) ) {
                $path_info = pathinfo( $old_file );
                $new_ext = pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION );
                $new_filename = $path_info['filename'] . '.' . $new_ext;
                $new_filepath = $path_info['dirname'] . '/' . $new_filename;

                if ( move_uploaded_file( $uploaded_file['tmp_name'], $new_filepath ) ) {
                    if ( $old_file !== $new_filepath ) {
                        @unlink( $old_file );
                        $metadata = wp_get_attachment_metadata( $attachment_id );
                        if ( isset( $metadata['sizes'] ) ) {
                            foreach ( $metadata['sizes'] as $size => $size_info ) {
                                @unlink( $path_info['dirname'] . '/' . $size_info['file'] );
                            }
                        }
                    }

                    update_attached_file( $attachment_id, $new_filepath );
                    $wp_filetype = wp_check_filetype( $new_filename, null );
                    wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $wp_filetype['type'] ) );

                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    $new_metadata = wp_generate_attachment_metadata( $attachment_id, $new_filepath );
                    wp_update_attachment_metadata( $attachment_id, $new_metadata );

                    // DB Search and Replace
                    if ( $old_file !== $new_filepath ) {
                        self::replace_urls_in_db($attachment_id, $new_filename, $old_file);
                    }
                    
                    wp_send_json_success( array( 'message' => 'Compressed via Vercel Edge!', 'new_size' => size_format( filesize( $new_filepath ), 2 ) ) );
                }
            }
        } else {
            // Trigger cURL to your API if client requests it
            $api_key = get_option('itc_api_key');
            $api_url = 'https://imagetight-api.vercel.app/api/compress';
            
            if(empty($api_key)) {
                wp_send_json_error("Please save your ImageTight License key first.");
            }

            $old_file = get_attached_file( $attachment_id );
            $tmp_file_created = false;
            
            // Handle remote S3 files via tmp download
            if ( !file_exists($old_file) ) {
                 require_once( ABSPATH . 'wp-admin/includes/file.php' );
                 $attachment_url = wp_get_attachment_url($attachment_id);
                 $tmp_file = download_url( $attachment_url );
                 if ( !is_wp_error($tmp_file) ) {
                     $old_file = $tmp_file;
                     $tmp_file_created = true;
                 } else {
                     wp_send_json_error( "Failed to read attachment file for upload." );
                 }
            }
            
            // Build Multipart Form Data request for WordPress WP_Http
            if ( function_exists('curl_init') && file_exists($old_file) ) {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                  CURLOPT_URL => $api_url, 
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_POST => true,
                  CURLOPT_POSTFIELDS => array(
                    'api_key' => $api_key,
                    'domain'  => site_url(),
                    'image' => new CURLFile($old_file, mime_content_type($old_file), basename($old_file))
                  ),
                ));

                $response = curl_exec($curl);
                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                
                if ( $tmp_file_created ) {
                    @unlink($old_file);
                    $old_file = get_attached_file( $attachment_id ); // restore original logic path
                }

                if ($httpcode == 200 && $response) {
                    $path_info = pathinfo( $old_file );
                    $new_filename = $path_info['filename'] . '.webp';
                    $new_filepath = $path_info['dirname'] . '/' . $new_filename;
                    
                    file_put_contents($new_filepath, $response);
                    
                    if ( $old_file !== $new_filepath ) {
                        @unlink( $old_file );
                        $metadata = wp_get_attachment_metadata( $attachment_id );
                        if ( isset($metadata['sizes']) ) {
                            foreach ( $metadata['sizes'] as $size => $size_info ) @unlink( $path_info['dirname'] . '/' . $size_info['file'] );
                        }
                    }

                    update_attached_file( $attachment_id, $new_filepath );
                    wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' ) );
                    
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $new_filepath ) );

                    // DB Search and Replace
                    if ( $old_file !== $new_filepath ) {
                        self::replace_urls_in_db($attachment_id, $new_filename, $old_file);
                    }

                    wp_send_json_success( array( 'message' => 'Compressed via Vercel Edge API!', 'new_size' => size_format( filesize( $new_filepath ), 2 ) ) );
                } else if ($httpcode == 402) {
                    wp_send_json_error( "Payment required. Please top up your credit package." );
                } else {
                    wp_send_json_error( "Invalid key or limit reached ($httpcode)." );
                }
            } else {
                 wp_send_json_error( "System error: cURL not installed or file unreadable." );
            }
        }
        
        // If no file was sent, we just saved SEO
        wp_send_json_success( array( 'message' => 'SEO Data Saved!' ) );
    }
}

new ITC_Pro_Companion();
