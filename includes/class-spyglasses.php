<?php
/**
 * Main Spyglasses Plugin Class
 *
 * @package Spyglasses
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Spyglasses class
 */
class Spyglasses {
    /**
     * API Key
     * 
     * @var string
     */
    private $api_key;

    /**
     * Debug mode
     * 
     * @var bool
     */
    private $debug_mode;

    /**
     * Block AI model trainers
     * 
     * @var bool
     */
    private $block_ai_model_trainers;

    /**
     * Custom blocks list
     * 
     * @var array
     */
    private $custom_blocks;

    /**
     * Custom allows list
     * 
     * @var array
     */
    private $custom_allows;

    /**
     * Agent patterns from JSON
     * 
     * @var array
     */
    private $agent_patterns;
    
    /**
     * AI referrer patterns from JSON
     * 
     * @var array
     */
    private $ai_referrers;
    
    /**
     * Property settings from API
     *
     * @var array
     */
    private $property_settings;
    
    /**
     * Patterns API endpoint
     * 
     * @var string
     */
    private $patterns_endpoint = 'https://www.spyglasses.io/api/patterns';

    /**
     * Custom log file path
     * 
     * @var string
     */
    private $log_file;

    /**
     * Initialize the class
     */
    public function init() {
        try {
            // Set up custom log file
            $this->log_file = WP_CONTENT_DIR . '/spyglasses-debug.log';
            
            // Get settings with error handling
            $this->api_key = get_option('spyglasses_api_key', '');
            $this->debug_mode = get_option('spyglasses_debug_mode', 'no') === 'yes';
            
            $this->debug_log('Spyglasses init() started');
            $this->debug_log('Settings loaded - API Key: ' . (!empty($this->api_key) ? 'SET' : 'NOT SET') . ', Debug: ' . ($this->debug_mode ? 'ON' : 'OFF'));
            
            // Load custom rules and property settings from central management
            $this->load_custom_rules();
            
            $auto_sync = get_option('spyglasses_auto_sync_patterns', 'yes') === 'yes';
            
            // Load agent patterns
            $this->load_agent_patterns();
            
            // Hook into WordPress early to catch all types of requests (REST, admin-ajax, etc.)
            add_action('init', array($this, 'detect_bot'), 0);
            $this->debug_log('Hook registered for detect_bot on init with priority 0');
            
            // Register scheduled event for updating patterns if auto-sync is enabled
            if ($auto_sync) {
                if (!wp_next_scheduled('spyglasses_update_patterns')) {
                    wp_schedule_event(time(), 'daily', 'spyglasses_update_patterns');
                }
                // Add the missing cron handler
                add_action('spyglasses_update_patterns', array($this, 'update_agent_patterns'));
            } else {
                // Clear scheduled event if auto-sync is disabled
                $timestamp = wp_next_scheduled('spyglasses_update_patterns');
                if ($timestamp) {
                    wp_unschedule_event($timestamp, 'spyglasses_update_patterns');
                }
            }
            
            $this->debug_log('Spyglasses init() completed successfully');
        } catch (Exception $e) {
            $this->debug_log('FATAL ERROR in init(): ' . $e->getMessage());
            // Log fatal errors even when debug mode is off
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                $this->write_to_wp_debug_log('Spyglasses FATAL ERROR in init(): ' . $e->getMessage());
            }
        }
    }

    /**
     * Load custom blocking and allowing rules from Spyglasses platform
     */
    private function load_custom_rules() {
        try {
            // Initialize defaults
            $this->custom_blocks = array();
            $this->custom_allows = array();
            $this->block_ai_model_trainers = false;
            
            // Load from cached property settings if available
            if (isset($this->property_settings)) {
                $this->block_ai_model_trainers = !empty($this->property_settings['blockAiModelTrainers']);
                $this->custom_blocks = is_array($this->property_settings['customBlocks']) ? $this->property_settings['customBlocks'] : array();
                $this->custom_allows = is_array($this->property_settings['customAllows']) ? $this->property_settings['customAllows'] : array();
                
                $this->debug_log('Central management rules loaded - AI Model Trainers: ' . ($this->block_ai_model_trainers ? 'BLOCK' : 'ALLOW') . ', Blocks: ' . count($this->custom_blocks) . ', Allows: ' . count($this->custom_allows));
            } else {
                // Try to load from cached patterns data
                $cached_patterns = get_transient('spyglasses_agent_patterns');
                if ($cached_patterns && isset($cached_patterns['propertySettings'])) {
                    $this->property_settings = $cached_patterns['propertySettings'];
                    $this->block_ai_model_trainers = !empty($this->property_settings['blockAiModelTrainers']);
                    $this->custom_blocks = is_array($this->property_settings['customBlocks']) ? $this->property_settings['customBlocks'] : array();
                    $this->custom_allows = is_array($this->property_settings['customAllows']) ? $this->property_settings['customAllows'] : array();
                    
                    $this->debug_log('Central management rules loaded from cache - AI Model Trainers: ' . ($this->block_ai_model_trainers ? 'BLOCK' : 'ALLOW') . ', Blocks: ' . count($this->custom_blocks) . ', Allows: ' . count($this->custom_allows));
                } else {
                    $this->debug_log('No central management settings available - using defaults (all allowed)');
                }
            }
            
            if (!empty($this->custom_blocks)) {
                $this->debug_log('Block rules: ' . implode(', ', array_slice($this->custom_blocks, 0, 5)) . (count($this->custom_blocks) > 5 ? '...' : ''));
            }
            if (!empty($this->custom_allows)) {
                $this->debug_log('Allow rules: ' . implode(', ', array_slice($this->custom_allows, 0, 5)) . (count($this->custom_allows) > 5 ? '...' : ''));
            }
        } catch (Exception $e) {
            $this->debug_log('ERROR loading custom rules: ' . $e->getMessage());
            $this->custom_blocks = array();
            $this->custom_allows = array();
            $this->block_ai_model_trainers = false;
        }
    }

    /**
     * Custom debug logging to dedicated file
     */
    private function debug_log($message) {
        if (!$this->debug_mode) {
            return;
        }
        
        try {
            $timestamp = current_time('Y-m-d H:i:s');
            $log_entry = "[{$timestamp}] {$message}\n";
            
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            if ($wp_filesystem && $wp_filesystem->exists(dirname($this->log_file))) {
                // Check if we can write to the directory or file
                if ($wp_filesystem->is_writable(dirname($this->log_file)) || 
                    ($wp_filesystem->exists($this->log_file) && $wp_filesystem->is_writable($this->log_file))) {
                    
                    $existing_content = $wp_filesystem->exists($this->log_file) ? $wp_filesystem->get_contents($this->log_file) : '';
                    $wp_filesystem->put_contents($this->log_file, $existing_content . $log_entry, FS_CHMOD_FILE);
                }
            }
            
            // Also log to WordPress debug log if WP_DEBUG_LOG is enabled
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('wp_debug_backtrace_summary')) {
                // Use WordPress native logging without direct error_log call
                $this->write_to_wp_debug_log('Spyglasses: ' . $message);
            }
        } catch (Exception $e) {
            // Fallback to WordPress debug log only if WP_DEBUG_LOG is enabled
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('wp_debug_backtrace_summary')) {
                $this->write_to_wp_debug_log('Spyglasses: ' . $message);
                $this->write_to_wp_debug_log('Spyglasses: Failed to write to custom log: ' . $e->getMessage());
            }
        }
    }

    /**
     * Write to WordPress debug log using native WordPress methods
     */
    private function write_to_wp_debug_log($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // Use WordPress's internal debugging mechanism
            $log_file = WP_CONTENT_DIR . '/debug.log';
            
            // Use WP_Filesystem to check writability
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            if ($wp_filesystem && $wp_filesystem->is_writable(WP_CONTENT_DIR)) {
                $timestamp = current_time('c');
                $formatted_message = "[{$timestamp}] PHP: {$message}\n";
                
                // Get existing content and append new message
                $existing_content = $wp_filesystem->exists($log_file) ? $wp_filesystem->get_contents($log_file) : '';
                $wp_filesystem->put_contents($log_file, $existing_content . $formatted_message, FS_CHMOD_FILE);
            }
        }
    }

    /**
     * Load agent patterns from JSON files or cache
     */
    private function load_agent_patterns() {
        try {
            $this->debug_log('Loading agent patterns...');
            
            // Check for cached patterns first - but only use if they contain actual patterns
            $cached_patterns = get_transient('spyglasses_agent_patterns');
            if ($cached_patterns && isset($cached_patterns['patterns']) && !empty($cached_patterns['patterns'])) {
                $this->agent_patterns = $cached_patterns;
                $this->ai_referrers = isset($cached_patterns['aiReferrers']) ? $cached_patterns['aiReferrers'] : array();
                
                // Load property settings from cache
                if (isset($cached_patterns['propertySettings'])) {
                    $this->property_settings = $cached_patterns['propertySettings'];
                    $this->debug_log('Property settings loaded from cache');
                }
                
                $this->debug_log('Loaded ' . count($cached_patterns['patterns']) . ' patterns from cache');
                return;
            } else if ($cached_patterns && empty($cached_patterns['patterns'])) {
                $this->debug_log('Found empty cached patterns - clearing cache and reloading');
                delete_transient('spyglasses_agent_patterns');
            }
            
            // Try to load from local JSON file
            $agents_file = SPYGLASSES_PLUGIN_DIR . 'includes/agents/agents.json';
            
            if (file_exists($agents_file)) {
                $json_content = file_get_contents($agents_file);
                if ($json_content !== false) {
                    $decoded = json_decode($json_content, true);
                    if (is_array($decoded) && isset($decoded['patterns']) && !empty($decoded['patterns'])) {
                        $this->agent_patterns = $decoded;
                        $this->ai_referrers = isset($decoded['aiReferrers']) ? $decoded['aiReferrers'] : array();
                        $this->debug_log('Loaded ' . count($decoded['patterns']) . ' patterns from local JSON file');
                    } else {
                        $this->debug_log('ERROR: Local JSON file is empty or invalid');
                    }
                } else {
                    $this->debug_log('ERROR: Failed to read local JSON file');
                }
            } else {
                $this->debug_log('Local JSON file not found: ' . $agents_file);
            }
            
            // Try to update patterns from remote API if we have an API key and no valid patterns yet
            if (!empty($this->api_key) && (!isset($this->agent_patterns) || empty($this->agent_patterns['patterns']))) {
                $this->debug_log('Attempting to update patterns from API...');
                $this->update_agent_patterns();
            }
            
            // Initialize empty arrays if nothing was loaded (this should be rare now)
            if (!isset($this->agent_patterns) || empty($this->agent_patterns['patterns'])) {
                $this->agent_patterns = array('patterns' => array());
                $this->debug_log('WARNING: No patterns available - initialized empty array (detection will not work)');
            }
            if (!isset($this->ai_referrers)) {
                $this->ai_referrers = array();
                $this->debug_log('No AI referrers available - initialized empty array');
            }
        } catch (Exception $e) {
            $this->debug_log('ERROR in load_agent_patterns(): ' . $e->getMessage());
            // Initialize empty arrays on error
            $this->agent_patterns = array('patterns' => array());
            $this->ai_referrers = array();
        }
    }

    /**
     * Update agent patterns from the remote API
     */
    public function update_agent_patterns() {
        try {
            if (empty($this->api_key)) {
                $msg = 'No API key set for pattern sync.';
                $this->debug_log($msg);
                return $msg;
            }
        
            $this->debug_log('Requesting patterns from API: ' . $this->patterns_endpoint);
            
            $response = wp_remote_get($this->patterns_endpoint, array(
                'timeout' => 10,
                'headers' => array(
                    'x-api-key' => $this->api_key
                )
            ));
        
            if (is_wp_error($response)) {
                $msg = 'WP_Error during pattern sync: ' . $response->get_error_message();
                $this->debug_log($msg);
                return $msg;
            }
        
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
        
            if ($code !== 200) {
                $msg = "Pattern sync HTTP error $code: $body";
                $this->debug_log($msg);
                return $msg;
            }
        
            $patterns = json_decode($body, true);
        
            if (!is_array($patterns) || !isset($patterns['patterns']) || empty($patterns['patterns'])) {
                $msg = 'Invalid or empty pattern response: ' . substr($body, 0, 200);
                $this->debug_log($msg);
                // DON'T cache empty patterns - this was poisoning the cache
                return $msg;
            }
        
            // Only save and cache if we have valid patterns
            $this->agent_patterns = $patterns;
            $this->ai_referrers = isset($patterns['aiReferrers']) ? $patterns['aiReferrers'] : array();
            
            // Load property settings from API response
            if (isset($patterns['propertySettings'])) {
                $this->property_settings = $patterns['propertySettings'];
                $this->debug_log('Property settings loaded from API - AI Model Trainers: ' . (!empty($this->property_settings['blockAiModelTrainers']) ? 'BLOCK' : 'ALLOW'));
            }
            
            // Only cache when we have actual patterns to prevent poisoning the cache
            set_transient('spyglasses_agent_patterns', $patterns, DAY_IN_SECONDS);
        
            // Update last sync time
            update_option('spyglasses_last_pattern_sync', time());
        
            $this->debug_log('Agent patterns updated successfully - ' . count($patterns['patterns']) . ' patterns loaded');
        
            return true;
        } catch (Exception $e) {
            $msg = 'Exception in update_agent_patterns(): ' . $e->getMessage();
            $this->debug_log($msg);
            return $msg;
        }
    }

    /**
     * Detect if current user is a bot
     */
    public function detect_bot() {
        try {
            $this->debug_log('detect_bot() method called');
            
            // Reload settings to catch any recent changes
            $this->load_custom_rules();
            $this->block_ai_model_trainers = get_option('spyglasses_block_ai_model_trainers', 'no') === 'yes';
            
            // Skip if no API key is set
            if (empty($this->api_key)) {
                $this->debug_log('Skipping detection - no API key set');
                return;
            }

            // Skip WordPress cron requests (simple URL check) - sanitize REQUEST_URI
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            if (strpos($request_uri, 'wp-cron.php') !== false) {
                $this->debug_log('Skipping detection - WordPress cron request');
                return;
            }

            // Get user agent and referrer - sanitize server variables
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
            
            $this->debug_log('Processing request - UA: ' . substr($user_agent, 0, 100) . '..., Referrer: ' . $referrer);
            
            // Early return if we have nothing to check
            if (empty($user_agent) && empty($referrer)) {
                $this->debug_log('Skipping detection - no user agent or referrer');
                return;
            }

            // Check if it's a bot
            $is_bot = false;
            $bot_info = null;
            $should_block = false;
            $is_ai_referrer = false;
            $referrer_info = null;

            // Check user agent patterns first for bot detection
            if (!empty($user_agent) && isset($this->agent_patterns['patterns']) && is_array($this->agent_patterns['patterns'])) {
                $this->debug_log('Checking ' . count($this->agent_patterns['patterns']) . ' patterns against user agent');
                
                foreach ($this->agent_patterns['patterns'] as $pattern_data) {
                    if (isset($pattern_data['pattern'])) {
                        try {
                            // Properly escape the pattern for regex use
                            $escaped_pattern = preg_quote($pattern_data['pattern'], '/');
                            $pattern = '/' . $escaped_pattern . '/i';
                            if (preg_match($pattern, $user_agent)) {
                                $is_bot = true;
                                $bot_info = $pattern_data;
                                $this->debug_log('Bot detected - Pattern: ' . $pattern_data['pattern'] . ', Type: ' . ($pattern_data['type'] ?? 'unknown'));
                                
                                // Check if this pattern should be blocked
                                $should_block = $this->should_block_pattern($pattern_data);
                                $this->debug_log('Should block: ' . ($should_block ? 'YES' : 'NO'));
                                
                                break;
                            }
                        } catch (Exception $e) {
                            $this->debug_log('ERROR checking pattern ' . $pattern_data['pattern'] . ': ' . $e->getMessage());
                        }
                    }
                }
            } else {
                $this->debug_log('No patterns available for checking');
            }
            
            // Check referrer patterns for AI referrer detection (these are always tracked but never blocked)
            if (!empty($referrer) && !empty($this->ai_referrers)) {
                foreach ($this->ai_referrers as $ai_referrer) {
                    if (!empty($ai_referrer['patterns'])) {
                        foreach ($ai_referrer['patterns'] as $pattern) {
                            try {
                                // Properly escape the pattern for regex use
                                $escaped_pattern = preg_quote($pattern, '/');
                                if (preg_match('/' . $escaped_pattern . '/i', $referrer)) {
                                    $is_ai_referrer = true;
                                    $referrer_info = $ai_referrer;
                                    $this->debug_log('AI referrer detected: ' . $ai_referrer['name']);
                                    break 2; // Break both loops
                                }
                            } catch (Exception $e) {
                                $this->debug_log('ERROR checking AI referrer pattern ' . $pattern . ': ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            // If it's a bot, handle according to blocking rules
            if ($is_bot) {
                // Log the visit first (before potentially blocking)
                try {
                    $this->log_bot_visit($user_agent, $bot_info, $should_block);
                    $this->debug_log('Bot visit logged successfully');
                } catch (Exception $e) {
                    $this->debug_log('ERROR logging bot visit: ' . $e->getMessage());
                    // Continue even if logging fails
                }
                
                // If the bot should be blocked
                if ($should_block) {
                    $this->debug_log('BLOCKING bot request');
                    $this->spyglasses_forbid();
                    // Never returns
                } else {
                    $this->debug_log('Bot allowed - not blocking');
                    // Set cache headers to vary by User-Agent for allowed bots
                    $this->spyglasses_set_vary_headers();
                }
            }
            
            // If it's an AI referrer, just log the visit (never block)
            if ($is_ai_referrer) {
                try {
                    $this->log_referrer_visit($referrer, $referrer_info, false);
                    $this->debug_log('AI referrer visit logged successfully');
                } catch (Exception $e) {
                    $this->debug_log('ERROR logging AI referrer visit: ' . $e->getMessage());
                }
                // Set cache headers to vary by User-Agent for AI referrer traffic
                $this->spyglasses_set_vary_headers();
            }
            
            if (!$is_bot && !$is_ai_referrer) {
                $this->debug_log('No bot or AI referrer detected - normal visitor');
                // For normal visitors, we can allow normal caching
            }
        } catch (Exception $e) {
            $this->debug_log('FATAL ERROR in detect_bot(): ' . $e->getMessage());
            // Log fatal errors even when debug mode is off
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                $this->write_to_wp_debug_log('Spyglasses FATAL ERROR in detect_bot(): ' . $e->getMessage());
            }
        }
    }

    /**
     * Check if a pattern should be blocked based on rules
     * 
     * @param array $pattern_data The pattern data
     * @return bool Whether the pattern should be blocked
     */
    private function should_block_pattern($pattern_data) {
        try {
            $pattern = isset($pattern_data['pattern']) ? $pattern_data['pattern'] : '';
            $category = isset($pattern_data['category']) ? $pattern_data['category'] : 'Unknown';
            $subcategory = isset($pattern_data['subcategory']) ? $pattern_data['subcategory'] : 'Unclassified';
            $type = isset($pattern_data['type']) ? $pattern_data['type'] : 'unknown';
            
            $this->debug_log('Checking blocking rules for pattern: ' . $pattern . ', category: ' . $category . ', subcategory: ' . $subcategory);
            
            // Check if pattern is explicitly allowed
            if (in_array('pattern:' . $pattern, $this->custom_allows)) {
                $this->debug_log('Pattern explicitly allowed');
                return false;
            }
            
            // Check if any parent is explicitly allowed
            if (in_array('category:' . $category, $this->custom_allows) ||
                in_array('subcategory:' . $category . ':' . $subcategory, $this->custom_allows) ||
                in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->custom_allows)) {
                $this->debug_log('Parent category/subcategory/type explicitly allowed');
                return false;
            }
            
            // Check if pattern is explicitly blocked
            if (in_array('pattern:' . $pattern, $this->custom_blocks)) {
                $this->debug_log('Pattern explicitly blocked');
                return true;
            }
            
            // Check if any parent is explicitly blocked
            if (in_array('category:' . $category, $this->custom_blocks) ||
                in_array('subcategory:' . $category . ':' . $subcategory, $this->custom_blocks) ||
                in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->custom_blocks)) {
                $this->debug_log('Parent category/subcategory/type explicitly blocked');
                return true;
            }
            
            // Check for AI model trainers global setting
            if ($this->block_ai_model_trainers && !empty($pattern_data['isAiModelTrainer'])) {
                $this->debug_log('Blocked due to global AI model trainers setting');
                return true;
            }
            
            // Default to not blocking
            $this->debug_log('No blocking rules matched - allowing');
            return false;
        } catch (Exception $e) {
            $this->debug_log('ERROR in should_block_pattern(): ' . $e->getMessage());
            return false; // Default to allowing on error
        }
    }

    /**
     * Check if a referrer should be blocked based on rules
     * 
     * @param string $referrer_id The referrer ID
     * @return bool Whether the referrer should be blocked
     */
    private function should_block_referrer($referrer_id) {
        // AI referrers should never be blocked as they are human visitors
        // coming from AI platforms, not bots
        return false;
    }

    /**
     * Log the visit to the Spyglasses collector
     * 
     * @param string $source_type Type of visit ('bot' or 'referrer')
     * @param array $info Information about the bot or referrer
     * @param string $user_agent The user agent string (for bots)
     * @param string $referrer The referrer URL (for referrers)
     * @param bool $was_blocked Whether the visit was blocked
     */
    private function log_visit($source_type, $info, $user_agent = '', $referrer = '', $was_blocked = false) {
        try {
            $this->debug_log('Logging visit - Type: ' . $source_type . ', Blocked: ' . ($was_blocked ? 'YES' : 'NO'));
            
            // Start timing the response (like working version)
            $start_time = microtime(true);
            
            // Prepare payload - sanitize all server variables
            $https = isset($_SERVER['HTTPS']) ? sanitize_text_field(wp_unslash($_SERVER['HTTPS'])) : '';
            $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            
            $url = ($https === 'on' ? "https" : "http") . "://" . $http_host . $request_uri;
            
            // Get HTTP status code 
            $status_code = $was_blocked ? 403 : http_response_code();
            if (!$status_code) $status_code = 200; // Default to 200 if not set
            
            // Calculate response time in milliseconds (like working version)
            $response_time = (microtime(true) - $start_time) * 1000;
            
            // Use the working version's payload structure
            $request_data = array(
                'url' => esc_url_raw($url),
                'user_agent' => !empty($user_agent) ? $user_agent : (isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''),
                'ip_address' => $this->get_client_ip(),
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET',
                'request_path' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/',
                'request_query' => isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '',
                'request_body' => '', // Add empty request body
                'response_status' => $status_code,
                'response_time_ms' => $response_time,
                'headers' => $this->get_headers(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'platformType' => 'wordpress'
            );
            
            // Add metadata for blocking information (new functionality)
            if ($source_type === 'bot') {
                $request_data['metadata'] = array_merge(
                    $this->get_agent_metadata($info),
                    array('was_blocked' => $was_blocked)
                );
            } else if ($source_type === 'referrer') {
                $request_data['metadata'] = array(
                    'source_type' => 'ai_referrer',
                    'referrer_id' => sanitize_text_field($info['id']),
                    'referrer_name' => sanitize_text_field($info['name']),
                    'company' => sanitize_text_field($info['company']),
                    'was_blocked' => $was_blocked
                );
            }
            
            // Add referrer if available
            if (!empty($referrer)) {
                $request_data['referrer'] = esc_url_raw($referrer);
            }
            
            do_action('spyglasses_before_api_request', SPYGLASSES_COLLECTOR_ENDPOINT, array(
                'method' => 'POST',
                'body' => $request_data,
            ));
            
            // Send the data to the collector using working version's approach
            $response = wp_remote_post(SPYGLASSES_COLLECTOR_ENDPOINT, array(
                'timeout' => 5,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => $this->debug_mode, // Use working version's approach - only block if debug mode
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->api_key
                ),
                'body' => json_encode($request_data),
                'cookies' => array()
            ));

            if ($this->debug_mode) {
                if (is_wp_error($response)) {
                    $this->debug_log('Collector error: ' . $response->get_error_message());
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    if ($code >= 400) {
                        $this->debug_log("API error (HTTP $code): $body");
                    } else {
                        $this->debug_log('Visit logged to collector successfully');
                    }
                }
            } else {
                $this->debug_log('Visit logged to collector (non-blocking)');
            }
        } catch (Exception $e) {
            $this->debug_log('ERROR in log_visit(): ' . $e->getMessage());
        }
    }

    /**
     * Get agent metadata for logging
     * 
     * @param array $bot_info Information about the bot
     * @return array Metadata for the agent
     */
    private function get_agent_metadata($bot_info) {
        $metadata = array(
            'agent_type' => isset($bot_info['type']) ? $bot_info['type'] : 'unknown',
            'agent_category' => isset($bot_info['category']) ? $bot_info['category'] : 'Unknown',
            'agent_subcategory' => isset($bot_info['subcategory']) ? $bot_info['subcategory'] : 'Unclassified',
            'company' => isset($bot_info['company']) ? $bot_info['company'] : null,
            'is_compliant' => isset($bot_info['isCompliant']) ? $bot_info['isCompliant'] : false,
            'intent' => isset($bot_info['intent']) ? $bot_info['intent'] : 'unknown',
            'confidence' => 0.9, // High confidence for pattern matches
            'detection_method' => 'pattern_match'
        );
        
        return $metadata;
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        return $ip;
    }

    /**
     * Get request headers
     * 
     * @return array
     */
    private function get_headers() {
        $headers = array();
        
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[sanitize_text_field($header_name)] = sanitize_text_field(wp_unslash($value));
            }
        }
        
        return $headers;
    }

    /**
     * Log the bot visit to the Spyglasses collector
     * 
     * @param string $user_agent The user agent string
     * @param array $bot_info Information about the bot
     * @param bool $was_blocked Whether the bot was blocked
     */
    private function log_bot_visit($user_agent, $bot_info, $was_blocked = false) {
        $this->log_visit('bot', $bot_info, $user_agent, '', $was_blocked);
    }

    /**
     * Log the referrer visit to the Spyglasses collector
     * 
     * @param string $referrer The referrer URL
     * @param array $referrer_info Information about the referrer
     * @param bool $was_blocked Whether the request was blocked
     */
    private function log_referrer_visit($referrer, $referrer_info, $was_blocked = false) {
        $this->log_visit('referrer', $referrer_info, '', $referrer, $was_blocked);
    }

    /**
     * Block request with proper cache headers
     */
    private function spyglasses_forbid() {
        // LiteSpeed Cache specific: prevent caching of blocked requests
        if (function_exists('do_action')) {
            do_action('litespeed_control_set_nocache', 'Spyglasses block');
        }
        
        // Fallback for other cache plugins and universal cache prevention
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        
        // Additional headers for cache prevention
        if (!headers_sent()) {
            header('Cache-Control: private, no-store, max-age=0');
            header('Vary: User-Agent');
        }
        
        wp_die(
            'Access Denied.',
            'Access Denied',
            array(
                'response' => 403,
                'back_link' => false,
                'text_direction' => 'ltr'
            )
        );
    }

    /**
     * Set headers to vary cache by User-Agent
     */
    private function spyglasses_set_vary_headers() {
        // LiteSpeed Cache specific: force cache variation by User-Agent hash
        if (function_exists('add_filter') && isset($_SERVER['HTTP_USER_AGENT'])) {
            add_filter('litespeed_vary', function($list) {
                // Validate $_SERVER access before use
                if (isset($_SERVER['HTTP_USER_AGENT'])) {
                    $user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
                    $list['ua'] = substr(md5($user_agent), 0, 8);
                }
                return $list;
            });
            
            // Also force vary for AJAX-triggered cache variations
            if (function_exists('do_action')) {
                do_action('litespeed_vary_ajax_force');
            }
        }
        
        // Fallback for other cache plugins: standard Vary header
        if (!headers_sent()) {
            $existing_vary = '';
            foreach (headers_list() as $header) {
                if (stripos($header, 'Vary:') === 0) {
                    $existing_vary = trim(substr($header, 5));
                    break;
                }
            }
            
            if ($existing_vary && stripos($existing_vary, 'User-Agent') === false) {
                header('Vary: ' . $existing_vary . ', User-Agent');
            } else if (!$existing_vary) {
                header('Vary: User-Agent');
            }
        }
    }

    /**
     * Make API request to collect visitor data
     */
    private function send_to_api($data) {
        $api_key = get_option('spyglasses_api_key', '');
        
        if (empty($api_key)) {
            $this->debug_log('No API key configured');
            return false;
        }
        
        $api_url = SPYGLASSES_COLLECTOR_ENDPOINT;
        
        $body = wp_json_encode($data);
        
        $args = array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Spyglasses WordPress Plugin/' . SPYGLASSES_VERSION,
                'X-API-Key' => $api_key,
            ),
            'timeout' => 5,
            'blocking' => false, // Non-blocking request
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            $this->debug_log('API request failed: ' . $response->get_error_message());
            return false;
        }
        
        return true;
    }
} 