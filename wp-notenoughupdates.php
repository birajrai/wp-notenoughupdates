<?php
/**
 * Plugin Name: NotEnoughUpdates
 * Description: Disables WordPress updates for the core, themes, and plugins, but allows itself to update from GitHub releases.
 * Version: 1.0.0
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
add_filter('pre_site_transient_update_plugins', 'disable_plugin_updates');
add_filter('pre_site_transient_update_themes', 'disable_theme_updates');

// Disable plugin updates, except for this plugin itself
function disable_plugin_updates($value) {
    $plugin_slug = 'wp-notenoughupdates/wp-notenoughupdates.php';
    if (isset($value->response)) {
        foreach ($value->response as $plugin_file => $plugin_data) {
            if ($plugin_file !== $plugin_slug) {
                unset($value->response[$plugin_file]);
            }
        }
    }
    return $value;
}

// Disable theme updates
function disable_theme_updates($value) {
    if (isset($value->response)) {
        $value->response = [];
    }
    return $value;
}

// Hook to check GitHub for the latest release and update the plugin
add_action('init', 'check_for_plugin_update');

function check_for_plugin_update() {
    $current_version = '1.0.0'; // Current plugin version
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
