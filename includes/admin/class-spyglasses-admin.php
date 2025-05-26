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
        register_setting('spyglasses_settings', 'spyglasses_block_ai_model_trainers', array(
            'default' => 'no'
        ));
        register_setting('spyglasses_settings', 'spyglasses_custom_blocks', array(
            'default' => array()
        ));
        register_setting('spyglasses_settings', 'spyglasses_custom_allows', array(
            'default' => array()
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
        
        // Add Blocking Section
        add_settings_section(
            'spyglasses_blocking_section',
            __('Bot Blocking Settings', 'spyglasses'),
            array($this, 'render_blocking_section'),
            'spyglasses'
        );
        
        add_settings_field(
            'spyglasses_block_ai_model_trainers',
            __('Block AI Model Trainers', 'spyglasses'),
            array($this, 'render_block_ai_model_trainers_field'),
            'spyglasses',
            'spyglasses_blocking_section'
        );
        
        add_settings_field(
            'spyglasses_pattern_blocking',
            __('Custom Blocking Rules', 'spyglasses'),
            array($this, 'render_pattern_blocking_field'),
            'spyglasses',
            'spyglasses_blocking_section'
        );
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
     * Render blocking section
     */
    public function render_blocking_section() {
        echo '<p>';
        echo __('Configure which bots and AI agents to block from accessing your site. Blocked requests will receive a 403 Forbidden response.', 'spyglasses');
        echo '</p>';
    }
    
    /**
     * Render block AI model trainers field
     */
    public function render_block_ai_model_trainers_field() {
        $block_ai_model_trainers = get_option('spyglasses_block_ai_model_trainers', 'no');
        
        echo '<label>';
        echo '<input type="checkbox" id="spyglasses_block_ai_model_trainers" name="spyglasses_block_ai_model_trainers" value="yes" ' . checked('yes', $block_ai_model_trainers, false) . ' />';
        echo __('Block AI model trainers (e.g. agents that collect data to train AI models)', 'spyglasses');
        echo '</label>';
        echo '<p class="description">';
        echo __('When enabled, AI agents that are known to collect data for training AI models will be blocked with a 403 Forbidden response.', 'spyglasses');
        echo '</p>';
    }
    
    /**
     * Render pattern blocking field
     */
    public function render_pattern_blocking_field() {
        $patterns = $this->get_patterns_hierarchy();
        
        if (empty($patterns)) {
            echo '<p class="description">';
            echo __('No patterns available. Please sync patterns first.', 'spyglasses');
            echo '</p>';
            return;
        }
        
        $custom_blocks = get_option('spyglasses_custom_blocks', array());
        $custom_allows = get_option('spyglasses_custom_allows', array());
        
        echo '<div class="spyglasses-patterns-wrapper">';
        echo '<div class="spyglasses-tabs">';
        echo '<button type="button" class="spyglasses-tab active" data-tab="categories">' . __('By Category', 'spyglasses') . '</button>';
        echo '<button type="button" class="spyglasses-tab" data-tab="patterns">' . __('By Pattern', 'spyglasses') . '</button>';
        echo '</div>';
        
        // Categories tab
        echo '<div class="spyglasses-tab-content active" id="categories-tab">';
        
        foreach ($patterns as $category => $subcategories) {
            $category_id = sanitize_title($category);
            $category_blocked = in_array('category:' . $category, $custom_blocks);
            $category_allowed = in_array('category:' . $category, $custom_allows);
            $flag_class = '';
            
            if (isset($subcategories['__flags'])) {
                $flags = $subcategories['__flags'];
                unset($subcategories['__flags']);
                
                if (!empty($flags['isAiModelTrainer'])) {
                    $flag_class .= ' spyglasses-ai-model-trainer';
                }
                if (!empty($flags['isAiVisitor'])) {
                    $flag_class .= ' spyglasses-ai-visitor';
                }
                if (!empty($flags['isCrawler'])) {
                    $flag_class .= ' spyglasses-crawler';
                }
            }
            
            echo '<div class="spyglasses-category' . $flag_class . '">';
            echo '<h4>';
            echo '<span class="toggle-indicator" data-target="' . $category_id . '">▶</span> ';
            echo esc_html($category);
            
            // Category action buttons
            echo '<div class="spyglasses-actions">';
            echo '<button type="button" class="button-link spyglasses-block-btn' . ($category_blocked ? ' active' : '') . '" data-id="category:' . esc_attr($category) . '">';
            echo __('Block', 'spyglasses');
            echo '</button>';
            echo '<button type="button" class="button-link spyglasses-allow-btn' . ($category_allowed ? ' active' : '') . '" data-id="category:' . esc_attr($category) . '">';
            echo __('Allow', 'spyglasses');
            echo '</button>';
            echo '</div>';
            echo '</h4>';
            
            echo '<div class="spyglasses-subcategories" id="' . $category_id . '" style="display: none;">';
            
            foreach ($subcategories as $subcategory => $types) {
                $subcategory_id = $category_id . '-' . sanitize_title($subcategory);
                $subcategory_blocked = in_array('subcategory:' . $category . ':' . $subcategory, $custom_blocks);
                $subcategory_allowed = in_array('subcategory:' . $category . ':' . $subcategory, $custom_allows);
                
                echo '<div class="spyglasses-subcategory">';
                echo '<h5>';
                echo '<span class="toggle-indicator" data-target="' . $subcategory_id . '">▶</span> ';
                echo esc_html($subcategory);
                
                // Subcategory action buttons
                echo '<div class="spyglasses-actions">';
                echo '<button type="button" class="button-link spyglasses-block-btn' . ($subcategory_blocked ? ' active' : '') . '" data-id="subcategory:' . esc_attr($category) . ':' . esc_attr($subcategory) . '">';
                echo __('Block', 'spyglasses');
                echo '</button>';
                echo '<button type="button" class="button-link spyglasses-allow-btn' . ($subcategory_allowed ? ' active' : '') . '" data-id="subcategory:' . esc_attr($category) . ':' . esc_attr($subcategory) . '">';
                echo __('Allow', 'spyglasses');
                echo '</button>';
                echo '</div>';
                echo '</h5>';
                
                echo '<div class="spyglasses-types" id="' . $subcategory_id . '" style="display: none;">';
                
                foreach ($types as $type => $patterns_array) {
                    $type_id = $subcategory_id . '-' . sanitize_title($type);
                    $type_blocked = in_array('type:' . $category . ':' . $subcategory . ':' . $type, $custom_blocks);
                    $type_allowed = in_array('type:' . $category . ':' . $subcategory . ':' . $type, $custom_allows);
                    
                    echo '<div class="spyglasses-type">';
                    echo '<h6>';
                    echo '<span class="toggle-indicator" data-target="' . $type_id . '">▶</span> ';
                    echo esc_html($type);
                    
                    // Type action buttons
                    echo '<div class="spyglasses-actions">';
                    echo '<button type="button" class="button-link spyglasses-block-btn' . ($type_blocked ? ' active' : '') . '" data-id="type:' . esc_attr($category) . ':' . esc_attr($subcategory) . ':' . esc_attr($type) . '">';
                    echo __('Block', 'spyglasses');
                    echo '</button>';
                    echo '<button type="button" class="button-link spyglasses-allow-btn' . ($type_allowed ? ' active' : '') . '" data-id="type:' . esc_attr($category) . ':' . esc_attr($subcategory) . ':' . esc_attr($type) . '">';
                    echo __('Allow', 'spyglasses');
                    echo '</button>';
                    echo '</div>';
                    echo '</h6>';
                    
                    echo '<div class="spyglasses-patterns" id="' . $type_id . '" style="display: none;">';
                    
                    foreach ($patterns_array as $pattern_data) {
                        $pattern_blocked = in_array('pattern:' . $pattern_data['pattern'], $custom_blocks);
                        $pattern_allowed = in_array('pattern:' . $pattern_data['pattern'], $custom_allows);
                        
                        echo '<div class="spyglasses-pattern">';
                        echo '<code>' . esc_html($pattern_data['pattern']) . '</code>';
                        
                        // Pattern action buttons
                        echo '<div class="spyglasses-actions">';
                        echo '<button type="button" class="button-link spyglasses-block-btn' . ($pattern_blocked ? ' active' : '') . '" data-id="pattern:' . esc_attr($pattern_data['pattern']) . '">';
                        echo __('Block', 'spyglasses');
                        echo '</button>';
                        echo '<button type="button" class="button-link spyglasses-allow-btn' . ($pattern_allowed ? ' active' : '') . '" data-id="pattern:' . esc_attr($pattern_data['pattern']) . '">';
                        echo __('Allow', 'spyglasses');
                        echo '</button>';
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>'; // End patterns
                    echo '</div>'; // End type
                }
                
                echo '</div>'; // End types
                echo '</div>'; // End subcategory
            }
            
            echo '</div>'; // End subcategories
            echo '</div>'; // End category
        }
        
        echo '</div>'; // End categories tab
        
        // Patterns tab (flat list)
        echo '<div class="spyglasses-tab-content" id="patterns-tab" style="display: none;">';
        echo '<input type="text" id="spyglasses-pattern-search" placeholder="' . esc_attr__('Search patterns...', 'spyglasses') . '" class="regular-text">';
        echo '<div class="spyglasses-pattern-list">';
        
        $all_patterns = $this->get_all_patterns_flat();
        foreach ($all_patterns as $pattern_data) {
            $pattern_blocked = in_array('pattern:' . $pattern_data['pattern'], $custom_blocks);
            $pattern_allowed = in_array('pattern:' . $pattern_data['pattern'], $custom_allows);
            $flag_class = '';
            
            if (!empty($pattern_data['isAiModelTrainer'])) {
                $flag_class .= ' spyglasses-ai-model-trainer';
            }
            if (!empty($pattern_data['isAiVisitor'])) {
                $flag_class .= ' spyglasses-ai-visitor';
            }
            if (!empty($pattern_data['isCrawler'])) {
                $flag_class .= ' spyglasses-crawler';
            }
            
            echo '<div class="spyglasses-pattern-item' . $flag_class . '">';
            echo '<div class="spyglasses-pattern-info">';
            echo '<code>' . esc_html($pattern_data['pattern']) . '</code>';
            echo '<span class="spyglasses-pattern-category">' . esc_html($pattern_data['category'] . ' > ' . $pattern_data['subcategory']) . '</span>';
            echo '</div>';
            
            // Pattern action buttons
            echo '<div class="spyglasses-actions">';
            echo '<button type="button" class="button-link spyglasses-block-btn' . ($pattern_blocked ? ' active' : '') . '" data-id="pattern:' . esc_attr($pattern_data['pattern']) . '">';
            echo __('Block', 'spyglasses');
            echo '</button>';
            echo '<button type="button" class="button-link spyglasses-allow-btn' . ($pattern_allowed ? ' active' : '') . '" data-id="pattern:' . esc_attr($pattern_data['pattern']) . '">';
            echo __('Allow', 'spyglasses');
            echo '</button>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>'; // End pattern list
        echo '</div>'; // End patterns tab
        
        // Hidden fields to store custom block and allow lists
        echo '<input type="hidden" id="spyglasses_custom_blocks" name="spyglasses_custom_blocks" value="' . esc_attr(json_encode($custom_blocks)) . '" />';
        echo '<input type="hidden" id="spyglasses_custom_allows" name="spyglasses_custom_allows" value="' . esc_attr(json_encode($custom_allows)) . '" />';
        
        echo '</div>'; // End patterns wrapper
        
        // Add the JavaScript for handling the UI interactions
        $this->add_pattern_blocking_script();
    }
    
    /**
     * Add JavaScript for pattern blocking UI
     */
    private function add_pattern_blocking_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Toggle sections
            $('.toggle-indicator').on('click', function() {
                var target = $(this).data('target');
                $('#' + target).toggle();
                $(this).text($(this).text() === '▶' ? '▼' : '▶');
            });
            
            // Tab switching
            $('.spyglasses-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.spyglasses-tab').removeClass('active');
                $(this).addClass('active');
                $('.spyglasses-tab-content').hide();
                $('#' + tab + '-tab').show();
            });
            
            // Search functionality
            $('#spyglasses-pattern-search').on('input', function() {
                var search = $(this).val().toLowerCase();
                $('.spyglasses-pattern-item').each(function() {
                    var pattern = $(this).find('code').text().toLowerCase();
                    var category = $(this).find('.spyglasses-pattern-category').text().toLowerCase();
                    if (pattern.includes(search) || category.includes(search)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            // Block button handling
            $('.spyglasses-block-btn').on('click', function() {
                var id = $(this).data('id');
                var $btn = $(this);
                var $allowBtn = $btn.closest('.spyglasses-actions').find('.spyglasses-allow-btn');
                var customBlocks = JSON.parse($('#spyglasses_custom_blocks').val() || '[]');
                var customAllows = JSON.parse($('#spyglasses_custom_allows').val() || '[]');
                
                if ($btn.hasClass('active')) {
                    // Remove from block list
                    customBlocks = customBlocks.filter(function(item) {
                        return item !== id;
                    });
                    $btn.removeClass('active');
                } else {
                    // Add to block list
                    if (!customBlocks.includes(id)) {
                        customBlocks.push(id);
                    }
                    $btn.addClass('active');
                    
                    // Remove from allow list if present
                    if ($allowBtn.hasClass('active')) {
                        customAllows = customAllows.filter(function(item) {
                            return item !== id;
                        });
                        $allowBtn.removeClass('active');
                    }
                }
                
                $('#spyglasses_custom_blocks').val(JSON.stringify(customBlocks));
                $('#spyglasses_custom_allows').val(JSON.stringify(customAllows));
            });
            
            // Allow button handling
            $('.spyglasses-allow-btn').on('click', function() {
                var id = $(this).data('id');
                var $btn = $(this);
                var $blockBtn = $btn.closest('.spyglasses-actions').find('.spyglasses-block-btn');
                var customBlocks = JSON.parse($('#spyglasses_custom_blocks').val() || '[]');
                var customAllows = JSON.parse($('#spyglasses_custom_allows').val() || '[]');
                
                if ($btn.hasClass('active')) {
                    // Remove from allow list
                    customAllows = customAllows.filter(function(item) {
                        return item !== id;
                    });
                    $btn.removeClass('active');
                } else {
                    // Add to allow list
                    if (!customAllows.includes(id)) {
                        customAllows.push(id);
                    }
                    $btn.addClass('active');
                    
                    // Remove from block list if present
                    if ($blockBtn.hasClass('active')) {
                        customBlocks = customBlocks.filter(function(item) {
                            return item !== id;
                        });
                        $blockBtn.removeClass('active');
                    }
                }
                
                $('#spyglasses_custom_blocks').val(JSON.stringify(customBlocks));
                $('#spyglasses_custom_allows').val(JSON.stringify(customAllows));
            });
        });
        </script>
        <style>
            .spyglasses-patterns-wrapper {
                margin-top: 15px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                background: #fff;
                padding: 15px;
            }
            
            .spyglasses-tabs {
                margin-bottom: 15px;
                border-bottom: 1px solid #ccd0d4;
            }
            
            .spyglasses-tab {
                padding: 8px 12px;
                background: none;
                border: none;
                border-bottom: 3px solid transparent;
                cursor: pointer;
                margin-right: 10px;
            }
            
            .spyglasses-tab.active {
                border-bottom-color: #2271b1;
                font-weight: 600;
            }
            
            .spyglasses-category, .spyglasses-subcategory, .spyglasses-type {
                margin-bottom: 10px;
            }
            
            .spyglasses-category h4, .spyglasses-subcategory h5, .spyglasses-type h6 {
                margin: 0;
                padding: 8px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .spyglasses-subcategory h5 {
                background: #f0f0f0;
            }
            
            .spyglasses-type h6 {
                background: #e8e8e8;
            }
            
            .spyglasses-pattern {
                padding: 8px;
                margin: 5px 0;
                border: 1px solid #e5e5e5;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .spyglasses-pattern-item {
                padding: 8px;
                margin: 5px 0;
                border: 1px solid #e5e5e5;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .spyglasses-pattern-info {
                display: flex;
                flex-direction: column;
            }
            
            .spyglasses-pattern-category {
                font-size: 0.8em;
                color: #666;
                margin-top: 3px;
            }
            
            .spyglasses-actions {
                display: flex;
                gap: 10px;
            }
            
            .spyglasses-block-btn, .spyglasses-allow-btn {
                padding: 2px 6px;
                cursor: pointer;
                border-radius: 3px;
            }
            
            .spyglasses-block-btn {
                color: #a00;
            }
            
            .spyglasses-allow-btn {
                color: #0a0;
            }
            
            .spyglasses-block-btn.active {
                background-color: #a00;
                color: #fff;
            }
            
            .spyglasses-allow-btn.active {
                background-color: #0a0;
                color: #fff;
            }
            
            .toggle-indicator {
                cursor: pointer;
                margin-right: 5px;
            }
            
            /* Indicators for special categories */
            .spyglasses-ai-model-trainer {
                border-left: 3px solid #e74c3c;
            }
            
            .spyglasses-ai-visitor {
                border-left: 3px solid #3498db;
            }
            
            .spyglasses-crawler {
                border-left: 3px solid #f39c12;
            }
            
            #spyglasses-pattern-search {
                margin-bottom: 15px;
                width: 100%;
            }
        </style>
        <?php
    }
    
    /**
     * Get patterns in a hierarchical structure
     * 
     * @return array Patterns organized by category, subcategory, and type
     */
    private function get_patterns_hierarchy() {
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        if (!$cached_patterns || !isset($cached_patterns['patterns'])) {
            return array();
        }
        
        $hierarchy = array();
        
        foreach ($cached_patterns['patterns'] as $pattern) {
            $category = isset($pattern['category']) ? $pattern['category'] : 'Unknown';
            $subcategory = isset($pattern['subcategory']) ? $pattern['subcategory'] : 'Unclassified';
            $type = isset($pattern['type']) ? $pattern['type'] : 'unknown';
            
            // Initialize category if not exists
            if (!isset($hierarchy[$category])) {
                $hierarchy[$category] = array(
                    '__flags' => array(
                        'isAiVisitor' => !empty($pattern['isAiVisitor']),
                        'isAiModelTrainer' => !empty($pattern['isAiModelTrainer']),
                        'isCrawler' => !empty($pattern['isCrawler'])
                    )
                );
            } else {
                // Update flags
                $hierarchy[$category]['__flags']['isAiVisitor'] = $hierarchy[$category]['__flags']['isAiVisitor'] || !empty($pattern['isAiVisitor']);
                $hierarchy[$category]['__flags']['isAiModelTrainer'] = $hierarchy[$category]['__flags']['isAiModelTrainer'] || !empty($pattern['isAiModelTrainer']);
                $hierarchy[$category]['__flags']['isCrawler'] = $hierarchy[$category]['__flags']['isCrawler'] || !empty($pattern['isCrawler']);
            }
            
            // Initialize subcategory if not exists
            if (!isset($hierarchy[$category][$subcategory])) {
                $hierarchy[$category][$subcategory] = array();
            }
            
            // Initialize type if not exists
            if (!isset($hierarchy[$category][$subcategory][$type])) {
                $hierarchy[$category][$subcategory][$type] = array();
            }
            
            // Add pattern to type
            $hierarchy[$category][$subcategory][$type][] = $pattern;
        }
        
        return $hierarchy;
    }
    
    /**
     * Get all patterns in a flat list
     * 
     * @return array All patterns in a flat array
     */
    private function get_all_patterns_flat() {
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        if (!$cached_patterns || !isset($cached_patterns['patterns'])) {
            return array();
        }
        
        return $cached_patterns['patterns'];
    }
} 