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
     * Agent patterns from JSON
     * 
     * @var array
     */
    private $agent_patterns;
    
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
        $auto_sync = get_option('spyglasses_auto_sync_patterns', 'yes') === 'yes';
        
        // Load agent patterns
        $this->load_agent_patterns();
        
        // Hook into WordPress
        add_action('init', array($this, 'detect_bot'));
        
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
            return;
        }
        
        // Try to load from local JSON file
        $agents_file = SPYGLASSES_PLUGIN_DIR . 'includes/agents/agents.json';
        
        if (file_exists($agents_file)) {
            $json_content = file_get_contents($agents_file);
            $this->agent_patterns = json_decode($json_content, true);
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
            );
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
        // Skip if no API key
        if (empty($this->api_key)) {
            return;
        }
        
        $response = wp_remote_get($this->patterns_endpoint, array(
            'timeout' => 10,
            'headers' => array(
                'x-api-key' => $this->api_key
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $patterns = json_decode($body, true);
            
            if (is_array($patterns) && isset($patterns['patterns'])) {
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
        } else {
            if ($this->debug_mode) {
                $error_message = is_wp_error($response) ? $response->get_error_message() : 'Unknown error';
                error_log('Spyglasses: Error updating agent patterns: ' . $error_message);
            }
        }
        
        return false;
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

        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        if (empty($user_agent)) {
            return;
        }

        // Check if it's a bot
        $is_bot = false;
        $bot_info = null;

        // Iterate through patterns
        if (isset($this->agent_patterns['patterns']) && is_array($this->agent_patterns['patterns'])) {
            foreach ($this->agent_patterns['patterns'] as $pattern_data) {
                if (isset($pattern_data['pattern'])) {
                    $pattern = '/' . $pattern_data['pattern'] . '/i';
                    if (preg_match($pattern, $user_agent)) {
                        $is_bot = true;
                        $bot_info = $pattern_data;
                        break;
                    }
                }
            }
        }

        // If it's a bot, log the visit
        if ($is_bot) {
            $this->log_bot_visit($user_agent, $bot_info);
        }
    }

    /**
     * Log the bot visit to the Spyglasses collector
     * 
     * @param string $user_agent The user agent string
     * @param array $bot_info Information about the bot
     */
    private function log_bot_visit($user_agent, $bot_info) {
        // Start timing the response
        $start_time = microtime(true);
        
        // Prepare payload
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Get HTTP status code 
        $status_code = http_response_code();
        if (!$status_code) $status_code = 200; // Default to 200 if not set
        
        // Calculate response time in milliseconds
        $response_time = (microtime(true) - $start_time) * 1000;
        
        do_action('spyglasses_before_api_request', SPYGLASSES_COLLECTOR_ENDPOINT, [
            'method' => 'POST',
            'body' => [
                'url' => $url,
                'user_agent' => $user_agent,
                'ip_address' => $this->get_client_ip(),
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
                'request_path' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/',
                'request_query' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
                'request_body' => '', // Add empty request body
                'response_status' => $status_code,
                'response_time_ms' => $response_time,
                'headers' => $this->get_headers(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ]
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
            'body' => json_encode([
                'url' => $url,
                'user_agent' => $user_agent,
                'ip_address' => $this->get_client_ip(),
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
                'request_path' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/',
                'request_query' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
                'request_body' => '', // Add empty request body
                'response_status' => $status_code,
                'response_time_ms' => $response_time,
                'headers' => $this->get_headers(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ]),
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
} 