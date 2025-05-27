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
     * Initialize the class
     */
    public function init() {
        // Get settings
        $this->api_key = get_option('spyglasses_api_key', '');
        $this->debug_mode = get_option('spyglasses_debug_mode', 'no') === 'yes';
        $this->block_ai_model_trainers = get_option('spyglasses_block_ai_model_trainers', 'no') === 'yes';
        
        // Ensure custom blocks and allows are proper arrays
        $custom_blocks_option = get_option('spyglasses_custom_blocks', '[]');
        $custom_allows_option = get_option('spyglasses_custom_allows', '[]');
        
        if (!is_array($custom_blocks_option)) {
            $this->custom_blocks = json_decode($custom_blocks_option, true);
            if (!is_array($this->custom_blocks)) {
                $this->custom_blocks = array(); // Fallback to empty array if JSON decode fails
            }
        } else {
            $this->custom_blocks = $custom_blocks_option;
        }
        
        if (!is_array($custom_allows_option)) {
            $this->custom_allows = json_decode($custom_allows_option, true);
            if (!is_array($this->custom_allows)) {
                $this->custom_allows = array(); // Fallback to empty array if JSON decode fails
            }
        } else {
            $this->custom_allows = $custom_allows_option;
        }
        
        $auto_sync = get_option('spyglasses_auto_sync_patterns', 'yes') === 'yes';
        
        // Load agent patterns
        $this->load_agent_patterns();
        
        // Hook into WordPress
        add_action('init', array($this, 'detect_bot'), 5); // Set priority to 5 to run early
        
        // Register scheduled event for updating patterns if auto-sync is enabled
        if ($auto_sync) {
            if (!wp_next_scheduled('spyglasses_update_patterns')) {
                wp_schedule_event(time(), 'daily', 'spyglasses_update_patterns');
            }
            add_action('spyglasses_update_patterns', array($this, 'update_agent_patterns'));
        } else {
            // Clear scheduled event if auto-sync is disabled
            $timestamp = wp_next_scheduled('spyglasses_update_patterns');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'spyglasses_update_patterns');
            }
        }
    }

    /**
     * Load agent patterns from JSON files or cache
     */
    private function load_agent_patterns() {
        // Check for cached patterns first
        $cached_patterns = get_transient('spyglasses_agent_patterns');
        if ($cached_patterns) {
            $this->agent_patterns = $cached_patterns;
            $this->ai_referrers = isset($cached_patterns['aiReferrers']) ? $cached_patterns['aiReferrers'] : array();
            return;
        }
        
        // Try to load from local JSON file
        $agents_file = SPYGLASSES_PLUGIN_DIR . 'includes/agents/agents.json';
        
        if (file_exists($agents_file)) {
            $json_content = file_get_contents($agents_file);
            $this->agent_patterns = json_decode($json_content, true);
            $this->ai_referrers = isset($this->agent_patterns['aiReferrers']) ? $this->agent_patterns['aiReferrers'] : array();
        } else {
            // Use default patterns if file doesn't exist
            $this->agent_patterns = array(
                'version' => '1.0.0',
                'patterns' => array(
                    array(
                        'pattern' => 'Perplexity-User\\/[0-9]',
                        'url' => 'https://docs.perplexity.ai/guides/bots',
                        'type' => 'perplexity-user',
                        'category' => 'AI Agent',
                        'subcategory' => 'AI Assistants',
                        'company' => 'Perplexity AI',
                        'isCompliant' => true,
                        'intent' => 'UserQuery'
                    ),
                    array(
                        'pattern' => 'Claude-User\\/[0-9]',
                        'url' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
                        'type' => 'claude-user',
                        'category' => 'AI Agent',
                        'subcategory' => 'AI Assistants',
                        'company' => 'Anthropic',
                        'isCompliant' => true,
                        'intent' => 'UserQuery'
                    ),
                    array(
                        'pattern' => 'Claude-SearchBot\\/[0-9]',
                        'url' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
                        'type' => 'claude-searchbot',
                        'category' => 'AI Crawler',
                        'subcategory' => 'Search Enhancement Crawlers',
                        'company' => 'Anthropic',
                        'isCompliant' => true,
                        'intent' => 'Search'
                    ),
                ),
                'aiReferrers' => array()
            );
            $this->ai_referrers = array();
        }
        
        // Try to update patterns from remote API immediately 
        // if we have an API key and no cached patterns
        if (!empty($this->api_key)) {
            $this->update_agent_patterns();
        }
    }
    
    /**
     * Update agent patterns from the remote API
     */
    public function update_agent_patterns() {
        if (empty($this->api_key)) {
            if ($this->debug_mode) {
                error_log('Spyglasses: No API key set for pattern sync.');
            }
            return 'No API key set for pattern sync.';
        }
    
        $response = wp_remote_get($this->patterns_endpoint, array(
            'timeout' => 10,
            'headers' => array(
                'x-api-key' => $this->api_key
            )
        ));
    
        if (is_wp_error($response)) {
            $msg = 'WP_Error during pattern sync: ' . $response->get_error_message();
            if ($this->debug_mode) {
                error_log('Spyglasses: ' . $msg);
            }
            return $msg;
        }
    
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
    
        if ($code !== 200) {
            $msg = "Pattern sync HTTP error $code: $body";
            if ($this->debug_mode) {
                error_log('Spyglasses: ' . $msg);
            }
            return $msg;
        }
    
        $patterns = json_decode($body, true);
    
        if (!is_array($patterns) || !isset($patterns['patterns'])) {
            $msg = 'Invalid pattern response: ' . $body;
            if ($this->debug_mode) {
                error_log('Spyglasses: ' . $msg);
            }
            return $msg;
        }
    
        // Save the updated patterns to cache
        $this->agent_patterns = $patterns;
        set_transient('spyglasses_agent_patterns', $patterns, DAY_IN_SECONDS);
    
        // Also update the local file for fallback
        $agents_file = SPYGLASSES_PLUGIN_DIR . 'includes/agents/agents.json';
        @file_put_contents($agents_file, $body);
    
        // Update last sync time
        update_option('spyglasses_last_pattern_sync', time());
    
        if ($this->debug_mode) {
            error_log('Spyglasses: Agent patterns updated successfully');
        }
    
        return true;
    }

    /**
     * Detect if current user is a bot
     */
    public function detect_bot() {
        // Skip if no API key is set
        if (empty($this->api_key)) {
            return;
        }

        // Skip if this is an admin page or AJAX request
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Skip WordPress cron requests
        if ($this->is_wordpress_cron_request()) {
            if ($this->debug_mode) {
                error_log('Spyglasses: Skipping WordPress cron request');
            }
            return;
        }

        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Get referrer
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Early return if we have nothing to check
        if (empty($user_agent) && empty($referrer)) {
            return;
        }

        // Check if it's a bot
        $is_bot = false;
        $bot_info = null;
        $should_block = false;
        $is_ai_referrer = false;
        $referrer_info = null;

        // Check user agent patterns first for bot detection
        if (!empty($user_agent)) {
            if (isset($this->agent_patterns['patterns']) && is_array($this->agent_patterns['patterns'])) {
                foreach ($this->agent_patterns['patterns'] as $pattern_data) {
                    if (isset($pattern_data['pattern'])) {
                        $pattern = '/' . $pattern_data['pattern'] . '/i';
                        if (preg_match($pattern, $user_agent)) {
                            $is_bot = true;
                            $bot_info = $pattern_data;
                            
                            // Check if this pattern should be blocked
                            if ($this->should_block_pattern($pattern_data)) {
                                $should_block = true;
                            }
                            
                            break;
                        }
                    }
                }
            }
        }
        
        // Check referrer patterns for AI referrer detection (these are always tracked but never blocked)
        if (!empty($referrer) && !empty($this->ai_referrers)) {
            foreach ($this->ai_referrers as $ai_referrer) {
                if (!empty($ai_referrer['patterns'])) {
                    foreach ($ai_referrer['patterns'] as $pattern) {
                        if (preg_match('/' . $pattern . '/i', $referrer)) {
                            $is_ai_referrer = true;
                            $referrer_info = $ai_referrer;
                            break 2; // Break both loops
                        }
                    }
                }
            }
        }

        // If it's a bot, handle according to blocking rules
        if ($is_bot) {
            // If the bot should be blocked and we're not in an admin context
            if ($should_block) {
                // Log the blocked visit before sending the 403 response
                $this->log_bot_visit($user_agent, $bot_info, true);
                
                // Send 403 Forbidden response
                status_header(403);
                header('Content-Type: text/plain');
                echo 'Access Denied.';
                exit;
            } else {
                // Log the visit as normal
                $this->log_bot_visit($user_agent, $bot_info, false);
            }
        }
        
        // If it's an AI referrer, just log the visit (never block)
        if ($is_ai_referrer) {
            $this->log_referrer_visit($referrer, $referrer_info, false);
        }
    }

    /**
     * Determines if the current request is a WordPress cron job
     * 
     * @return bool True if this is a WordPress cron request
     */
    private function is_wordpress_cron_request() {
        // Check for the specific endpoint
        $is_cron_endpoint = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            // Check if the URI contains wp-cron.php
            $is_cron_endpoint = (strpos($_SERVER['REQUEST_URI'], 'wp-cron.php') !== false);
        }
        
        // Check for the cron query parameter
        $has_cron_param = isset($_GET['doing_wp_cron']);
        
        // Check for WordPress version in user agent
        $is_wp_user_agent = false;
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            // WordPress cron has user agent like "WordPress/x.x.x; https://example.com"
            $is_wp_user_agent = (strpos($user_agent, 'WordPress/') === 0);
            
            // Extra verification: check if the site URL is in the user agent
            // This helps ensure it's a legitimate WordPress cron and not an external request
            if ($is_wp_user_agent) {
                $site_url = get_site_url();
                $site_domain = parse_url($site_url, PHP_URL_HOST);
                if (!empty($site_domain) && strpos($user_agent, $site_domain) === false) {
                    // If the site domain isn't in the user agent, it might not be a legitimate cron
                    $is_wp_user_agent = false;
                }
            }
        }
        
        // Also check for the WordPress constant that's defined during cron
        $defined_as_cron = defined('DOING_CRON') && DOING_CRON;
        
        // Consider it a cron if the endpoint matches AND (has cron param OR has WP user agent OR DOING_CRON is defined)
        return $is_cron_endpoint && ($has_cron_param || $is_wp_user_agent || $defined_as_cron);
    }

    /**
     * Check if a pattern should be blocked based on rules
     * 
     * @param array $pattern_data The pattern data
     * @return bool Whether the pattern should be blocked
     */
    private function should_block_pattern($pattern_data) {
        // Check if pattern is explicitly allowed
        if (in_array('pattern:' . $pattern_data['pattern'], $this->custom_allows)) {
            return false;
        }
        
        $category = isset($pattern_data['category']) ? $pattern_data['category'] : 'Unknown';
        $subcategory = isset($pattern_data['subcategory']) ? $pattern_data['subcategory'] : 'Unclassified';
        $type = isset($pattern_data['type']) ? $pattern_data['type'] : 'unknown';
        
        // Check if any parent is explicitly allowed
        if (in_array('category:' . $category, $this->custom_allows) ||
            in_array('subcategory:' . $category . ':' . $subcategory, $this->custom_allows) ||
            in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->custom_allows)) {
            return false;
        }
        
        // Check if pattern is explicitly blocked
        if (in_array('pattern:' . $pattern_data['pattern'], $this->custom_blocks)) {
            return true;
        }
        
        // Check if any parent is explicitly blocked
        if (in_array('category:' . $category, $this->custom_blocks) ||
            in_array('subcategory:' . $category . ':' . $subcategory, $this->custom_blocks) ||
            in_array('type:' . $category . ':' . $subcategory . ':' . $type, $this->custom_blocks)) {
            return true;
        }
        
        // Check for AI model trainers global setting
        if ($this->block_ai_model_trainers && !empty($pattern_data['isAiModelTrainer'])) {
            return true;
        }
        
        // Default to not blocking
        return false;
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
        // Start timing the response
        $start_time = microtime(true);
        
        // Prepare payload
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Get HTTP status code 
        $status_code = $was_blocked ? 403 : http_response_code();
        if (!$status_code) $status_code = 200; // Default to 200 if not set
        
        // Calculate response time in milliseconds
        $response_time = (microtime(true) - $start_time) * 1000;
        
        // Prepare metadata based on source type
        $metadata = array('was_blocked' => $was_blocked);
        
        if ($source_type === 'bot') {
            // Bot-specific metadata
            $metadata = array_merge($metadata, $this->get_agent_metadata($info));
        } else if ($source_type === 'referrer') {
            // Referrer-specific metadata
            $metadata = array_merge($metadata, array(
                'source_type' => 'ai_referrer',
                'referrer_id' => $info['id'],
                'referrer_name' => $info['name'],
                'company' => $info['company']
            ));
        }
        
        // Prepare common request data
        $request_data = [
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
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'metadata' => $metadata
        ];
        
        // Add referrer if available
        if (!empty($referrer)) {
            $request_data['referrer'] = $referrer;
        }
        
        do_action('spyglasses_before_api_request', SPYGLASSES_COLLECTOR_ENDPOINT, [
            'method' => 'POST',
            'body' => $request_data
        ]);
        
        // Send the data to the collector
        $response = wp_remote_post(SPYGLASSES_COLLECTOR_ENDPOINT, array(
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => $this->debug_mode, // Only block for response if debug mode is on
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key
            ),
            'body' => json_encode($request_data),
            'cookies' => array()
        ));

        if ($this->debug_mode) {
            if (is_wp_error($response)) {
                error_log('Spyglasses collector error: ' . $response->get_error_message());
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code >= 400) {
                    error_log("Spyglasses API error (HTTP $code): $body");
                } elseif ($was_blocked) {
                    if ($source_type === 'bot') {
                        error_log("Spyglasses blocked bot: $user_agent");
                    } else if ($source_type === 'referrer') {
                        error_log("Spyglasses blocked AI referrer: $referrer");
                    }
                }
            }
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
} 