<?php
/**
 * Plugin Name: NotEnoughUpdates
 * Description: Disables WordPress updates for the core, themes, and plugins, but allows itself to update from GitHub releases. Prevents Elementor Pro and similar plugins from checking external updates.
 * Version: 1.0.2
 * Author: kathmandhu
 * Author URI: https://github.com/kathmandhu/wp-notenoughupdates
 * Plugin URI: https://github.com/kathmandhu/wp-notenoughupdates
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Disable WordPress core updates
add_filter('auto_update_core', '__return_false');
add_filter('pre_site_transient_update_core', '__return_null');

// Disable plugin updates, except for this plugin itself
add_filter('pre_site_transient_update_plugins', 'disable_plugin_updates');
function disable_plugin_updates($value) {
    $plugin_slug = 'wp-notenoughupdates/wp-notenoughupdates.php'; // Keep updates for this plugin
    if (isset($value->response)) {
        foreach ($value->response as $plugin_file => $plugin_data) {
            if ($plugin_file !== $plugin_slug) {
                unset($value->response[$plugin_file]); // Remove all other plugins from update checks
            }
        }
    }
    return $value;
}

// Disable theme updates
add_filter('pre_site_transient_update_themes', 'disable_theme_updates');
function disable_theme_updates($value) {
    if (isset($value->response)) {
        $value->response = []; // Clear the theme updates
    }
    return $value;
}

// Block HTTP requests to specific update sources
add_filter('http_request_args', 'block_elementor_pro_update_requests', 10, 2);
function block_elementor_pro_update_requests($args, $url) {
    // List of URLs to block for updates (Elementor Pro and similar plugins)
    $blocked_urls = array(
        'https://elementor.com', // Elementor Pro update API
        'https://pro.elementor.com', // Elementor Pro CDN
        'https://api.elementor.com', // Elementor Pro API
    );
    
    // Block any HTTP request that goes to the listed URLs
    foreach ($blocked_urls as $blocked_url) {
        if (strpos($url, $blocked_url) !== false) {
            // Cancel the request by returning false
            return array_merge($args, array('blocking' => false));
        }
    }
    
    return $args; // Return unmodified request if it's not an update URL
}

// Clear cache to ensure updates are hidden after activation
add_action('admin_init', 'clear_update_cache');
function clear_update_cache() {
    delete_site_transient('update_plugins');
    delete_site_transient('update_themes');
    delete_site_transient('update_core');
}

// Hook to check GitHub for the latest release and update the plugin
add_action('init', 'check_for_plugin_update');

function check_for_plugin_update() {
    $current_version = '1.0.2'; // Current plugin version
    $plugin_slug = 'wp-notenoughupdates';
    $repo_url = 'https://api.github.com/repos/kathmandhu/wp-notenoughupdates/releases/latest';

    // Check the latest release from GitHub
    $response = wp_remote_get($repo_url, array('headers' => array('User-Agent' => 'WordPress Plugin')));
    
    if (is_wp_error($response)) {
        return; // Bail if error occurs
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    
    if (isset($data->tag_name) && version_compare($current_version, $data->tag_name, '<')) {
        // There's a new version
        $zip_url = $data->zipball_url;

        // Download and update the plugin
        update_plugin_from_github($plugin_slug, $zip_url);
    }
}

function update_plugin_from_github($plugin_slug, $zip_url) {
    // Download the latest zip file
    $downloaded_file = download_url($zip_url);

    if (is_wp_error($downloaded_file)) {
        return false; // Bail if download failed
    }

    // Unzip the downloaded file
    $result = unzip_file($downloaded_file, WP_PLUGIN_DIR . '/' . $plugin_slug);

    if (is_wp_error($result)) {
        return false; // Bail if unzip failed
    }

    // Cleanup: Remove the downloaded file
    @unlink($downloaded_file);

    // Plugin successfully updated
    return true;
}
