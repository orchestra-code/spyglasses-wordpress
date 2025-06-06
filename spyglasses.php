<?php
/**
 * Plugin Name: Spyglasses
 * Plugin URI: https://www.spyglasses.io
 * Description: Advanced bot and AI agent detection for WordPress websites.
 * Version: 1.0.1
 * Author: Orchestra AI, Inc.
 * Author URI: https://www.spyglasses.io
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: spyglasses
 * Requires at least: 5.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.2
 * Network: false
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPYGLASSES_VERSION', '1.0.1');
define('SPYGLASSES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPYGLASSES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPYGLASSES_COLLECTOR_ENDPOINT', 'https://www.spyglasses.io/api/collect');

// Include required files
require_once SPYGLASSES_PLUGIN_DIR . 'includes/class-spyglasses.php';
require_once SPYGLASSES_PLUGIN_DIR . 'includes/admin/class-spyglasses-admin.php';

/**
 * Initialize the plugin
 */
function spyglasses_init() {
    // Initialize the main plugin class
    $spyglasses = new Spyglasses();
    $spyglasses->init();

    // Initialize the admin interface if in admin area
    if (is_admin()) {
        $admin = new Spyglasses_Admin();
        $admin->init();
    }
}
add_action('plugins_loaded', 'spyglasses_init');

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, 'spyglasses_activate');
function spyglasses_activate() {
    // Initialize default settings
    add_option('spyglasses_api_key', '');
    add_option('spyglasses_debug_mode', 'no');
    add_option('spyglasses_auto_sync_patterns', 'yes');
    add_option('spyglasses_block_ai_model_trainers', 'no');
    
    // Make sure these are always stored as JSON strings
    add_option('spyglasses_custom_blocks', '[]');
    add_option('spyglasses_custom_allows', '[]');
    
    // Schedule the pattern update event
    if (!wp_next_scheduled('spyglasses_update_patterns')) {
        wp_schedule_event(time(), 'daily', 'spyglasses_update_patterns');
    }
    
    // Trigger initial pattern update if we have an API key
    $api_key = get_option('spyglasses_api_key', '');
    if (!empty($api_key)) {
        $spyglasses = new Spyglasses();
        $spyglasses->update_agent_patterns();
    }
}

/**
 * Register deactivation hook
 */
register_deactivation_hook(__FILE__, 'spyglasses_deactivate');
function spyglasses_deactivate() {
    // Clear scheduled events
    $timestamp = wp_next_scheduled('spyglasses_update_patterns');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'spyglasses_update_patterns');
    }
    
    // Remove transient cache
    delete_transient('spyglasses_agent_patterns');
}

/**
 * Register uninstall hook
 */
register_uninstall_hook(__FILE__, 'spyglasses_uninstall');
function spyglasses_uninstall() {
    // Remove plugin settings
    delete_option('spyglasses_api_key');
    delete_option('spyglasses_debug_mode');
    delete_option('spyglasses_auto_sync_patterns');
    delete_option('spyglasses_block_ai_model_trainers');
    delete_option('spyglasses_custom_blocks');
    delete_option('spyglasses_custom_allows');
    delete_option('spyglasses_last_pattern_sync');
    
    // Remove transient cache
    delete_transient('spyglasses_agent_patterns');
    
    // Remove custom log file if it exists
    $log_file = WP_CONTENT_DIR . '/spyglasses-debug.log';
    if (file_exists($log_file)) {
        unlink($log_file);
    }
} 