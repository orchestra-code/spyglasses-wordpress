<?php
/**
 * Spyglasses Admin Class
 *
 * @package Spyglasses
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Spyglasses Admin class
 */
class Spyglasses_Admin {
    /**
     * Initialize the admin class
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(SPYGLASSES_PLUGIN_DIR . 'spyglasses.php'), array($this, 'add_settings_link'));
        
        // Handle manual pattern sync
        add_action('admin_init', array($this, 'handle_manual_pattern_sync'));

        // Add this debugging code temporarily
        add_action('spyglasses_before_api_request', function($url, $args) {
            error_log('Spyglasses API request: ' . $url);
            error_log('Payload: ' . json_encode($args['body']));
        }, 10, 2);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Spyglasses', 'spyglasses'),
            __('Spyglasses', 'spyglasses'),
            'manage_options',
            'spyglasses',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('spyglasses_settings', 'spyglasses_api_key');
        register_setting('spyglasses_settings', 'spyglasses_debug_mode');
        register_setting('spyglasses_settings', 'spyglasses_auto_sync_patterns', array(
            'default' => 'yes'
        ));
        
        add_settings_section(
            'spyglasses_main_section',
            __('Main Settings', 'spyglasses'),
            array($this, 'render_main_section'),
            'spyglasses'
        );
        
        add_settings_field(
            'spyglasses_api_key',
            __('API Key', 'spyglasses'),
            array($this, 'render_api_key_field'),
            'spyglasses',
            'spyglasses_main_section'
        );
        
        add_settings_field(
            'spyglasses_auto_sync_patterns',
            __('Auto-Sync Patterns', 'spyglasses'),
            array($this, 'render_auto_sync_field'),
            'spyglasses',
            'spyglasses_main_section'
        );
        
        add_settings_field(
            'spyglasses_manual_sync',
            __('Manual Sync', 'spyglasses'),
            array($this, 'render_manual_sync_button'),
            'spyglasses',
            'spyglasses_main_section'
        );
        
        add_settings_field(
            'spyglasses_debug_mode',
            __('Debug Mode', 'spyglasses'),
            array($this, 'render_debug_mode_field'),
            'spyglasses',
            'spyglasses_main_section'
        );
    }

    /**
     * Render main settings section
     */
    public function render_main_section() {
        echo '<p>';
        echo sprintf(
            __('Spyglasses helps you detect and monitor AI agents and bots that visit your site. Sign up for an account at %s to get your API key.', 'spyglasses'),
            '<a href="https://www.spyglasses.io" target="_blank">spyglasses.io</a>'
        );
        echo '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option('spyglasses_api_key', '');
        
        echo '<input type="text" id="spyglasses_api_key" name="spyglasses_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">';
        echo __('Enter your Spyglasses API key. You can find this in your Spyglasses dashboard.', 'spyglasses');
        echo '</p>';
        
        if (empty($api_key)) {
            echo '<p class="description" style="color: #d63638;">';
            echo sprintf(
                __('No API key set. Please sign up at %s to get your API key.', 'spyglasses'),
                '<a href="https://www.spyglasses.io" target="_blank">spyglasses.io</a>'
            );
            echo '</p>';
        }
    }
    
    /**
     * Render auto-sync patterns field
     */
    public function render_auto_sync_field() {
        $auto_sync = get_option('spyglasses_auto_sync_patterns', 'yes');
        
        echo '<label>';
        echo '<input type="checkbox" id="spyglasses_auto_sync_patterns" name="spyglasses_auto_sync_patterns" value="yes" ' . checked('yes', $auto_sync, false) . ' />';
        echo __('Automatically sync agent patterns daily (recommended)', 'spyglasses');
        echo '</label>';
        echo '<p class="description">';
        echo __('Keeps the agent patterns updated from the Spyglasses API. Disable if you want to manage patterns manually.', 'spyglasses');
        echo '</p>';
    }
    
    /**
     * Render manual sync button
     */
    public function render_manual_sync_button() {
        $last_sync = get_option('spyglasses_last_pattern_sync', 0);
        $pattern_stats = $this->get_pattern_statistics();
        
        echo '<form method="post">';
        wp_nonce_field('spyglasses_manual_sync', 'spyglasses_sync_nonce');
        echo '<input type="hidden" name="spyglasses_manual_sync" value="1" />';
        echo '<input type="submit" class="button" value="' . __('Sync Patterns Now', 'spyglasses') . '" />';
        echo '</form>';
        
        if ($last_sync > 0) {
            echo '<p class="description">';
            echo sprintf(
                __('Last synced: %1$s. Total patterns: %2$d.', 'spyglasses'),
                human_time_diff($last_sync, time()) . ' ' . __('ago', 'spyglasses'),
                $pattern_stats['total']
            );
            echo '</p>';
            
            if (!empty($pattern_stats['categories'])) {
                echo '<div class="pattern-stats">';
                echo '<h4>' . __('Agent Categories', 'spyglasses') . '</h4>';
                echo '<ul>';
                foreach ($pattern_stats['categories'] as $category => $count) {
                    echo '<li><strong>' . esc_html($category) . ':</strong> ' . esc_html($count) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
    }

    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $debug_mode = get_option('spyglasses_debug_mode', 'no');
        
        echo '<label>';
        echo '<input type="checkbox" id="spyglasses_debug_mode" name="spyglasses_debug_mode" value="yes" ' . checked('yes', $debug_mode, false) . ' />';
        echo __('Enable debug mode (logs errors to WordPress error log)', 'spyglasses');
        echo '</label>';
    }
    
    /**
     * Get statistics about the patterns
     * 
     * @return array Statistics about the patterns
     */
    private function get_pattern_statistics() {
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        if (!$cached_patterns || !isset($cached_patterns['patterns'])) {
            return array(
                'total' => 0,
                'categories' => array()
            );
        }
        
        $stats = array(
            'total' => count($cached_patterns['patterns']),
            'categories' => array()
        );
        
        foreach ($cached_patterns['patterns'] as $pattern) {
            $category = isset($pattern['category']) ? $pattern['category'] : 'Unknown';
            
            if (!isset($stats['categories'][$category])) {
                $stats['categories'][$category] = 0;
            }
            
            $stats['categories'][$category]++;
        }
        
        return $stats;
    }
    
    /**
     * Handle manual pattern sync
     */
    public function handle_manual_pattern_sync() {
        if (!isset($_POST['spyglasses_manual_sync']) || !isset($_POST['spyglasses_sync_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['spyglasses_sync_nonce'], 'spyglasses_manual_sync')) {
            add_settings_error('spyglasses', 'sync-error', __('Security check failed.', 'spyglasses'), 'error');
            return;
        }
        
        $api_key = get_option('spyglasses_api_key', '');
        if (empty($api_key)) {
            add_settings_error('spyglasses', 'sync-error', __('API key is required for syncing patterns.', 'spyglasses'), 'error');
            return;
        }
        
        $spyglasses = new Spyglasses();
        $success = $spyglasses->update_agent_patterns();
        
        if ($success) {
            update_option('spyglasses_last_pattern_sync', time());
            add_settings_error('spyglasses', 'sync-success', __('Agent patterns synced successfully.', 'spyglasses'), 'success');
        } else {
            add_settings_error('spyglasses', 'sync-error', __('Failed to sync agent patterns. Please check your API key and try again.', 'spyglasses'), 'error');
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="spyglasses-admin-header">
                <div class="spyglasses-logo">
                    <img src="<?php echo SPYGLASSES_PLUGIN_URL; ?>assets/images/spyglasses-logo.png" alt="Spyglasses Logo" onerror="this.style.display='none'">
                </div>
                <div class="spyglasses-header-actions">
                    <a href="https://www.spyglasses.io/app" target="_blank" class="button button-secondary">
                        <?php _e('Go to Dashboard', 'spyglasses'); ?>
                    </a>
                    <a href="https://www.spyglasses.io/docs" target="_blank" class="button button-secondary">
                        <?php _e('Documentation', 'spyglasses'); ?>
                    </a>
                </div>
            </div>
            
            <?php settings_errors('spyglasses'); ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('spyglasses_settings');
                do_settings_sections('spyglasses');
                submit_button();
                ?>
            </form>
            
            <div class="spyglasses-admin-footer">
                <h2><?php _e('About Spyglasses', 'spyglasses'); ?></h2>
                <p>
                    <?php _e('Spyglasses is a powerful tool that helps you detect and monitor AI agents and bots that visit your site. With Spyglasses, you can:', 'spyglasses'); ?>
                </p>
                <ul class="spyglasses-features">
                    <li><?php _e('Detect AI agents and bots in real-time', 'spyglasses'); ?></li>
                    <li><?php _e('Monitor bot traffic to your site', 'spyglasses'); ?></li>
                    <li><?php _e('Understand how AI agents interact with your content', 'spyglasses'); ?></li>
                    <li><?php _e('Protect your content from unwanted scraping', 'spyglasses'); ?></li>
                </ul>
                <p>
                    <?php echo sprintf(
                        __('Don\'t have an account yet? %sSign up for Spyglasses%s to get started.', 'spyglasses'),
                        '<a href="https://www.spyglasses.io" target="_blank">', '</a>'
                    ); ?>
                </p>
            </div>
        </div>
        
        <style>
            .spyglasses-admin-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            
            .spyglasses-logo img {
                max-height: 50px;
            }
            
            .spyglasses-header-actions .button {
                margin-left: 10px;
            }
            
            .spyglasses-admin-footer {
                margin-top: 30px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            
            .spyglasses-features {
                list-style-type: disc;
                margin-left: 20px;
            }
            
            .spyglasses-features li {
                margin-bottom: 10px;
            }
            
            .pattern-stats {
                margin-top: 15px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #eee;
                border-radius: 4px;
            }
            
            .pattern-stats h4 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            
            .pattern-stats ul {
                margin: 0;
                list-style-type: disc;
                padding-left: 20px;
            }
        </style>
        <?php
    }

    /**
     * Add settings link on plugins page
     * 
     * @param array $links Array of plugin action links
     * @return array Modified array of plugin action links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=spyglasses') . '">' . __('Settings', 'spyglasses') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
} 