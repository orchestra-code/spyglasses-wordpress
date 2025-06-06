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
            $this->block_ai_model_trainers = get_option('spyglasses_block_ai_model_trainers', 'no') === 'yes';
            
            $this->debug_log('Spyglasses init() started');
            $this->debug_log('Settings loaded - API Key: ' . (!empty($this->api_key) ? 'SET' : 'NOT SET') . ', Debug: ' . ($this->debug_mode ? 'ON' : 'OFF') . ', Block AI Trainers: ' . ($this->block_ai_model_trainers ? 'ON' : 'OFF'));
            
            // Ensure custom blocks and allows are proper arrays with better error handling
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
            error_log('Spyglasses FATAL ERROR in init(): ' . $e->getMessage());
        }
    }

    /**
     * Load custom blocking and allowing rules with error handling
     */
    private function load_custom_rules() {
        try {
            // Get custom blocks
            $custom_blocks_option = get_option('spyglasses_custom_blocks', '[]');
            if (is_array($custom_blocks_option)) {
                $this->custom_blocks = $custom_blocks_option;
            } else {
                $decoded = json_decode($custom_blocks_option, true);
                $this->custom_blocks = is_array($decoded) ? $decoded : array();
            }
            
            // Get custom allows
            $custom_allows_option = get_option('spyglasses_custom_allows', '[]');
            if (is_array($custom_allows_option)) {
                $this->custom_allows = $custom_allows_option;
            } else {
                $decoded = json_decode($custom_allows_option, true);
                $this->custom_allows = is_array($decoded) ? $decoded : array();
            }
            
            $this->debug_log('Custom rules loaded - Blocks: ' . count($this->custom_blocks) . ', Allows: ' . count($this->custom_allows));
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
            
            // Write to custom log file
            if (is_writable(dirname($this->log_file)) || is_writable($this->log_file)) {
                file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
            
            // Also log to WordPress error log as backup
            error_log('Spyglasses: ' . $message);
        } catch (Exception $e) {
            // Fallback to error_log only
            error_log('Spyglasses: ' . $message);
            error_log('Spyglasses: Failed to write to custom log: ' . $e->getMessage());
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

            // Skip WordPress cron requests (simple URL check)
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, 'wp-cron.php') !== false) {
                $this->debug_log('Skipping detection - WordPress cron request');
                return;
            }

            // Get user agent and referrer
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
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
            error_log('Spyglasses FATAL ERROR in detect_bot(): ' . $e->getMessage());
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
            
            // Prepare payload
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            
            // Get HTTP status code 
            $status_code = $was_blocked ? 403 : http_response_code();
            if (!$status_code) $status_code = 200; // Default to 200 if not set
            
            // Calculate response time in milliseconds (like working version)
            $response_time = (microtime(true) - $start_time) * 1000;
            
            // Use the working version's payload structure
            $request_data = array(
                'url' => $url,
                'user_agent' => !empty($user_agent) ? $user_agent : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
                'ip_address' => $this->get_client_ip(),
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
                'request_path' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/',
                'request_query' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
                'request_body' => '', // Add empty request body
                'response_status' => $status_code,
                'response_time_ms' => $response_time,
                'headers' => $this->get_headers(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
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
                    'referrer_id' => $info['id'],
                    'referrer_name' => $info['name'],
                    'company' => $info['company'],
                    'was_blocked' => $was_blocked
                );
            }
            
            // Add referrer if available
            if (!empty($referrer)) {
                $request_data['referrer'] = $referrer;
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
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
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
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
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
                $list['ua'] = substr(md5($_SERVER['HTTP_USER_AGENT']), 0, 8);
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
} 