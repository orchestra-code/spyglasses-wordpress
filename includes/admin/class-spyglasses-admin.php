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
        add_menu_page(
            __('Spyglasses', 'spyglasses'),
            __('Spyglasses', 'spyglasses'),
            'manage_options',
            'spyglasses',
            array($this, 'render_settings_page'),
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents(SPYGLASSES_PLUGIN_DIR . 'assets/images/spyglasses-icon.svg')),
            60
        );
        remove_submenu_page('spyglasses', 'spyglasses');
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
        
        // Add Central Management Section
        add_settings_section(
            'spyglasses_central_section',
            __('Central Management', 'spyglasses'),
            array($this, 'render_central_section'),
            'spyglasses'
        );
        
        add_settings_field(
            'spyglasses_central_blocking',
            __('Bot Blocking Settings', 'spyglasses'),
            array($this, 'render_central_blocking_field'),
            'spyglasses',
            'spyglasses_central_section'
        );
        
        add_settings_field(
            'spyglasses_central_referrers',
            __('AI Referrer Tracking', 'spyglasses'),
            array($this, 'render_central_referrers_field'),
            'spyglasses',
            'spyglasses_central_section'
        );
    }

    /**
     * Sanitize JSON array input
     * 
     * @param mixed $input The input to sanitize
     * @return string JSON string of sanitized array
     */
    public function sanitize_json_array($input) {
        // If it's already a string (JSON), decode it first
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                return '[]'; // Return empty array if decoding fails
            }
        }
        
        // If it's not an array, return empty array
        if (!is_array($input)) {
            return '[]';
        }
        
        // Sanitize each item in the array
        $sanitized = array_map('sanitize_text_field', $input);
        
        // Remove empty items
        $sanitized = array_filter($sanitized, function($item) {
            return !empty(trim($item));
        });
        
        // Re-index array to avoid gaps
        $sanitized = array_values($sanitized);
        
        return json_encode($sanitized);
    }

    /**
     * Render main settings section
     */
    public function render_main_section() {
        echo '<p>';
        echo sprintf(
            __('Spyglasses helps you detect and monitor AI agents and bots that visit your site. Sign up for an account at %s to get your API key.', 'spyglasses'),
            '<a href="https://www.spyglasses.io?ref=wp-plugin" target="_blank">spyglasses.io</a>'
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
                '<a href="https://www.spyglasses.io?ref=wp-plugin" target="_blank">spyglasses.io</a>'
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
        echo '<p class="description">';
        echo __('If you encounter issues, enable debug mode and check <code>wp-content/debug.log</code> for details. Make sure <code>WP_DEBUG</code> and <code>WP_DEBUG_LOG</code> are enabled in <code>wp-config.php</code>.', 'spyglasses');
        echo '</p>';
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
        $spyglasses->init();
        $result = $spyglasses->update_agent_patterns();
        
        if ($result === true) {
            update_option('spyglasses_last_pattern_sync', time());
            add_settings_error('spyglasses', 'sync-success', __('Agent patterns synced successfully.', 'spyglasses'), 'success');
        } else {
            $error_message = __('Failed to sync agent patterns. Please check your API key and try again.', 'spyglasses');
            if (!empty($result) && is_string($result)) {
                $error_message .= ' Debug: ' . esc_html($result);
            }
            add_settings_error('spyglasses', 'sync-error', $error_message, 'error');
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
                    <img src="<?php echo SPYGLASSES_PLUGIN_URL; ?>assets/images/spyglasses-logo.webp" alt="Spyglasses Logo" onerror="this.style.display='none'">
                </div>
                <div class="spyglasses-header-actions">
                    <a href="https://www.spyglasses.io/app?ref=wp-plugin" target="_blank" class="button button-secondary">
                        <?php _e('Go to Dashboard', 'spyglasses'); ?>
                    </a>
                    <a href="https://www.spyglasses.io/docs?ref=wp-plugin" target="_blank" class="button button-secondary">
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
                        '<a href="https://www.spyglasses.io?ref=wp-plugin" target="_blank">', '</a>'
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

    /**
     * Render central management section
     */
    public function render_central_section() {
        echo '<p>';
        echo sprintf(
            __('Bot blocking settings and custom rules are now managed centrally through your Spyglasses dashboard. Visit %s to configure these settings.', 'spyglasses'),
            '<a href="https://www.spyglasses.io/app?ref=wp-plugin" target="_blank">your Spyglasses dashboard</a>'
        );
        echo '</p>';
    }
    
    /**
     * Render central blocking field
     */
    public function render_central_blocking_field() {
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        $property_settings = isset($cached_patterns['propertySettings']) ? $cached_patterns['propertySettings'] : null;
        
        if (!$property_settings) {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . __('Central settings not available. Please sync patterns to load your blocking configuration.', 'spyglasses') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="spyglasses-central-settings">';
        
        // AI Model Trainers setting
        echo '<div class="spyglasses-setting-row">';
        echo '<h4>' . __('AI Model Trainers', 'spyglasses') . '</h4>';
        $ai_status = !empty($property_settings['blockAiModelTrainers']) ? 'blocked' : 'allowed';
        $ai_class = $ai_status === 'blocked' ? 'blocked' : 'allowed';
        echo '<span class="spyglasses-status ' . $ai_class . '">' . ucfirst($ai_status) . '</span>';
        echo '<p class="description">' . __('AI agents that collect data for training models', 'spyglasses') . '</p>';
        echo '</div>';
        
        // Custom blocking rules
        echo '<div class="spyglasses-setting-row">';
        echo '<h4>' . __('Custom Blocking Rules', 'spyglasses') . '</h4>';
        $block_count = is_array($property_settings['customBlocks']) ? count($property_settings['customBlocks']) : 0;
        echo '<span class="spyglasses-count">' . sprintf(__('%d rules', 'spyglasses'), $block_count) . '</span>';
        echo '<p class="description">' . __('Specific patterns, categories, or types set to block', 'spyglasses') . '</p>';
        if ($block_count > 0) {
            echo '<div class="spyglasses-rules-preview">';
            echo '<strong>' . __('Sample rules:', 'spyglasses') . '</strong> ';
            $sample_rules = array_slice($property_settings['customBlocks'], 0, 3);
            echo '<code>' . implode('</code>, <code>', array_map('esc_html', $sample_rules)) . '</code>';
            if ($block_count > 3) {
                echo ' <em>' . sprintf(__('and %d more...', 'spyglasses'), $block_count - 3) . '</em>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Custom allow rules
        echo '<div class="spyglasses-setting-row">';
        echo '<h4>' . __('Custom Allow Rules', 'spyglasses') . '</h4>';
        $allow_count = is_array($property_settings['customAllows']) ? count($property_settings['customAllows']) : 0;
        echo '<span class="spyglasses-count">' . sprintf(__('%d rules', 'spyglasses'), $allow_count) . '</span>';
        echo '<p class="description">' . __('Specific patterns, categories, or types set to allow (override blocks)', 'spyglasses') . '</p>';
        if ($allow_count > 0) {
            echo '<div class="spyglasses-rules-preview">';
            echo '<strong>' . __('Sample rules:', 'spyglasses') . '</strong> ';
            $sample_rules = array_slice($property_settings['customAllows'], 0, 3);
            echo '<code>' . implode('</code>, <code>', array_map('esc_html', $sample_rules)) . '</code>';
            if ($allow_count > 3) {
                echo ' <em>' . sprintf(__('and %d more...', 'spyglasses'), $allow_count - 3) . '</em>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>'; // End central settings
        
        // Add styling
        ?>
        <style>
            .spyglasses-central-settings {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 15px;
                margin-top: 10px;
            }
            
            .spyglasses-setting-row {
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            
            .spyglasses-setting-row:last-child {
                border-bottom: none;
            }
            
            .spyglasses-setting-row h4 {
                margin: 0 0 5px 0;
                display: inline-block;
            }
            
            .spyglasses-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 0.85em;
                font-weight: 600;
                margin-left: 10px;
            }
            
            .spyglasses-status.blocked {
                background-color: #dc3232;
                color: #fff;
            }
            
            .spyglasses-status.allowed {
                background-color: #46b450;
                color: #fff;
            }
            
            .spyglasses-count {
                background-color: #f0f0f1;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 0.85em;
                margin-left: 10px;
            }
            
            .spyglasses-rules-preview {
                margin-top: 5px;
                padding: 8px;
                background-color: #f9f9f9;
                border-radius: 3px;
                font-size: 0.9em;
            }
            
            .spyglasses-rules-preview code {
                background: none;
                padding: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Render central referrers field  
     */
    public function render_central_referrers_field() {
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        $ai_referrers = isset($cached_patterns['aiReferrers']) ? $cached_patterns['aiReferrers'] : array();
        
        if (empty($ai_referrers)) {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . __('No AI referrers available. Please sync patterns to load AI referrer sources.', 'spyglasses') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="spyglasses-referrers-summary">';
        echo '<p class="description">';
        echo __('Spyglasses tracks human visitors who arrive at your site via links in AI-generated content. These are regular visitors using browsers, not bots, so they are never blocked.', 'spyglasses');
        echo '</p>';
        
        echo '<div class="spyglasses-referrer-count">';
        echo '<h4>' . sprintf(__('Tracking %d AI Referrer Sources', 'spyglasses'), count($ai_referrers)) . '</h4>';
        echo '</div>';
        
        echo '<div class="spyglasses-referrer-grid">';
        
        // Show first few referrers with logos
        $displayed = 0;
        foreach ($ai_referrers as $referrer) {
            if ($displayed >= 6) break; // Limit display
            
            echo '<div class="spyglasses-referrer-card">';
            
            if (!empty($referrer['logoUrl'])) {
                echo '<img src="' . esc_url($referrer['logoUrl']) . '" alt="' . esc_attr($referrer['name']) . '" class="spyglasses-referrer-logo" />';
            }
            
            echo '<div class="spyglasses-referrer-name">' . esc_html($referrer['name']) . '</div>';
            
            if (!empty($referrer['company'])) {
                echo '<div class="spyglasses-referrer-company">' . esc_html($referrer['company']) . '</div>';
            }
            
            echo '</div>';
            $displayed++;
        }
        
        if (count($ai_referrers) > 6) {
            echo '<div class="spyglasses-referrer-card spyglasses-more-card">';
            echo '<div class="spyglasses-more-count">+' . (count($ai_referrers) - 6) . '</div>';
            echo '<div class="spyglasses-referrer-name">' . __('More sources', 'spyglasses') . '</div>';
            echo '</div>';
        }
        
        echo '</div>'; // End referrer grid
        echo '</div>'; // End referrers summary
        
        // Add styling
        ?>
        <style>
            .spyglasses-referrers-summary {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 15px;
                margin-top: 10px;
            }
            
            .spyglasses-referrer-count h4 {
                margin: 10px 0;
                color: #1d2327;
            }
            
            .spyglasses-referrer-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            
            .spyglasses-referrer-card {
                text-align: center;
                padding: 15px;
                border: 1px solid #e5e5e5;
                border-radius: 4px;
                background: #fafafa;
            }
            
            .spyglasses-referrer-logo {
                max-width: 40px;
                max-height: 40px;
                margin-bottom: 8px;
            }
            
            .spyglasses-referrer-name {
                font-weight: 600;
                font-size: 0.9em;
                margin-bottom: 3px;
            }
            
            .spyglasses-referrer-company {
                font-size: 0.8em;
                color: #666;
            }
            
            .spyglasses-more-card {
                background: #f0f0f1;
                border-style: dashed;
            }
            
            .spyglasses-more-count {
                font-size: 1.5em;
                font-weight: 600;
                color: #666;
                margin-bottom: 5px;
            }
        </style>
        <?php
    }
} 