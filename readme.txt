=== Spyglasses - AI Traffic Analytics ===
Contributors: spyglasses
Tags: ai analytics, ai seo, ai visits, ai agents, analytics
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

AI traffic analytics for WordPress. Detect and monitor traffic from AI assistants like ChatGPT, Claude, Perplexity.

== Description ==

**Important: This plugin requires a free account with Spyglasses.** Sign up at [spyglasses.io](https://www.spyglasses.io) to get your API key. By using this plugin, you agree to our [Terms of Service](https://www.spyglasses.io/legal/terms) and [Privacy Policy](https://www.spyglasses.io/legal/privacy-policy).

Spyglasses provides advanced detection for AI agents, bots, and AI referrer traffic that interacts with your WordPress site. 

### Key Features

* **AI Agent Detection**: Automatically detect traffic from AI browsing assistants
* **AI Referrer Tracking**: Identify human visitors who clicked links from AI-generated content
* **Real-time Monitoring**: Track and monitor AI-related traffic to your site
* **Bot Blocking**: Selectively block specific AI agents or entire categories of bots
* **AI Model Trainer Protection**: Prevent AI systems from scraping your content for model training
* **Dashboard Insights**: View detailed analytics on AI traffic via the Spyglasses dashboard
* **Central Management**: Configure all blocking settings from your Spyglasses dashboard
* **Lightweight**: Minimal impact on site performance and user experience
* **Easy Setup**: Simple configuration with just an API key
* **Auto-Updating Patterns**: Automatically syncs with latest AI detection patterns daily
* **Smart Filtering**: Automatically excludes WordPress internal processes to prevent log pollution
* **Cache-Friendly**: Fully compatible with all major caching plugins including LiteSpeed Cache, WP Super Cache, W3TC, WP Rocket, and more
* **Reliable Fallbacks**: Local pattern storage ensures detection continues working even during API outages

### How It Works

Spyglasses uses pattern recognition to identify:

1. **AI agents and bots** visiting your site through user-agent detection
2. **Human visitors from AI platforms** through referrer detection

When AI-related traffic is detected, information about the visit is securely sent to the Spyglasses collector for analysis and reporting. You can also choose to block specific types of bots from accessing your site through your Spyglasses dashboard.

### Privacy & Transparency

Spyglasses only collects information about AI-related traffic, not about your regular human visitors. All code is open source and available for review on GitHub.

== External Services ==

This plugin connects to external Spyglasses API services to provide AI traffic detection and analytics functionality. **A Spyglasses account is required for the plugin to work.**

### Spyglasses Pattern API

**What it does:** Downloads the latest AI agent detection patterns to keep your site's bot detection up-to-date.

**API Endpoint:** https://www.spyglasses.io/api/patterns

**Data sent:** Your API key for authentication

**When:** Daily automatic sync (if enabled) or manual sync from plugin settings

**Purpose:** Ensures your site can detect the newest AI agents and bots as they emerge

### Spyglasses Collector API

**What it does:** Receives and processes data about AI agent and other bot visits to your website for analytics and reporting.

**API Endpoint:** https://www.spyglasses.io/api/collect

**Data sent when an AI agent or bot is detected:**
* IP Address of the AI agent/bot
* User Agent string of the AI agent/bot  
* URL/web page that was visited
* HTTP request headers from the AI agent/bot
* Request method (GET, POST, etc.)
* Response status code and timing
* Referrer information (if available)
* Timestamp of the visit

**When:** Only when traffic matching AI agent or bot patterns is detected on your site

**Purpose:** Provides analytics dashboard showing AI traffic patterns, blocked requests, and detailed visitor logs

**Important:** No data about regular human visitors is collected or sent. Only traffic that matches known AI agent patterns triggers data collection.

### Service Provider

Both services are provided by Orchestra AI, Inc.:

* **Website:** [spyglasses.io](https://www.spyglasses.io)
* **Terms of Service:** [spyglasses.io/legal/terms](https://www.spyglasses.io/legal/terms)
* **Privacy Policy:** [spyglasses.io/legal/privacy-policy](https://www.spyglasses.io/legal/privacy-policy)

== Installation ==

1. Upload the `spyglasses` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Spyglasses - AI Traffic Analytics to configure the plugin
4. Enter your Spyglasses API key
5. That's it! Spyglasses will now detect and monitor AI-related traffic to your site
6. Configure blocking rules in your Spyglasses dashboard at [spyglasses.io](https://www.spyglasses.io/app)

== Frequently Asked Questions ==

= Do I need a Spyglasses account? =

Yes, you'll need to sign up for a Spyglasses account at [spyglasses.io](https://www.spyglasses.io) to get an API key.

= Will this slow down my website? =

No, Spyglasses is designed to be lightweight and only processes requests from detected AI agents and referrers. It has minimal impact on your site's performance.

= What information is collected? =

Spyglasses collects information about AI-related traffic, including user agent, IP address, requested URL, HTTP headers, and referrer information. No personal information about your regular visitors is collected.

= What's the difference between AI agents and AI referrers? =

**AI agents** are bots that browse your site directly (like Claude-SearchBot or Perplexity-User). These have distinctive user-agent strings and can be blocked if desired.

**AI referrers** are regular human visitors who clicked a link to your site from AI-generated content (like ChatGPT, Claude, or Perplexity). These are detected by their referrer information and are never blocked because they're real people.

= How can I see the data collected? =

You can view all data collected in your Spyglasses dashboard. Log in to your account at [spyglasses.io](https://www.spyglasses.io) to access your dashboard.

= How does bot blocking work? =

Bot blocking settings are managed centrally through your Spyglasses dashboard. You can block bots at several levels: all AI model trainers, by category, by subcategory, by bot type, or by specific pattern. Blocked bots receive a 403 Forbidden response. Note that AI referrer traffic (humans from AI platforms) is never blocked.

= Where do I configure blocking settings? =

All blocking settings are now managed centrally through your Spyglasses dashboard at [spyglasses.io/app](https://www.spyglasses.io/app). This allows you to configure settings once and have them apply to all your WordPress sites using Spyglasses.

= Is this compatible with caching plugins? =

Yes, Spyglasses is fully compatible with all major caching plugins including LiteSpeed Cache, WP Super Cache, W3 Total Cache, WP Rocket, and others. The plugin automatically sets appropriate cache headers to ensure accurate detection while maintaining optimal performance.

= How are the detection patterns updated? =

By default, Spyglasses automatically syncs agent detection patterns daily from our API. This ensures you always have the latest patterns for new AI agents and referrers. You can manually sync patterns from the settings page or disable automatic syncing if you prefer.

= How do I troubleshoot issues with Spyglasses? =

If you encounter problems, enable "Debug Mode" in the Spyglasses settings page. This creates a detailed debug log at `wp-content/uploads/spyglasses-debug.log` with information about plugin operations, pattern loading, and detection events.

You can also enable WordPress debug logging by adding these lines to your wp-config.php:
```
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

After reproducing the issue, check both the Spyglasses debug log and the WordPress debug log (usually at `wp-content/uploads/debug.log`) for error messages. Look for messages starting with "Spyglasses:" and share them with support if needed.

Remember to disable debug logging when you have fixed the issue, as excessive logging can affect site performance.

== Screenshots ==

1. Spyglasses settings page
2. Spyglasses dashboard overview
3. AI agent detection in action
4. Bot blocking configuration
5. AI referrer tracking

== Changelog ==

= 1.2.0 =
* Adjusted script tags to conform to Wordpress guidance
* Updated readme to clarify an account on Spyglasses is needed
* Adjusted how log files are generated for debugging

= 1.1.7 =
* Revised slug to better describe the plugin

= 1.1.6 =
* Enhanced code compliance with WordPress.org best practices and coding standards
* Improved security with better output escaping and filesystem operations
* Updated image rendering to use WordPress-compliant methods

= 1.1.5 =
* Plugin now reports type WordPress when sending collector events

= 1.1.0 =
* **Major Update: Central Management**
* Bot blocking settings are now managed centrally through your Spyglasses dashboard
* Simplified WordPress admin interface - no more complex blocking configuration in WordPress
* All blocking rules, AI model trainer settings, and custom patterns are now configured at spyglasses.io/app
* Settings automatically sync across all your WordPress sites
* Improved reliability and consistency of blocking rules
* Backward compatibility maintained - existing local settings will be migrated automatically

= 1.0.1 =
* Tweaks to caching for better bot detection

= 1.0.0 =
* Major stability improvements for sites using caching plugins
* Enhanced cache compatibility with proper header management
* Improved error handling and recovery mechanisms
* Added local pattern storage as fallback during API outages
* Fixed detection issues on sites with aggressive caching configurations
* Enhanced debug logging with dedicated log file for better troubleshooting

= 0.3.2 =
* Fixed a bug with custom block and allow settings that could cause errors
* Improved handling of settings data for better stability
* Don't log visits for WordPress internal cron checks

= 0.3.1 =
* Don't log visits for WordPress internal cron checks.

= 0.3.0 =
* Added AI referrer tracking for visitors coming from AI platforms
* Added separate UI section for viewing tracked AI referrer sources
* Updated detection to differentiate between AI bots and human visitors from AI platforms
* Improved logging to provide richer AI traffic insights
* Updated patterns endpoint to include AI referrer information
* Optimized logging to improve performance

= 0.2.0 =
* Added bot blocking functionality
* Added option to block AI model trainers
* Added customizable block/allow lists for bots
* Added hierarchical view of bot patterns by category
* Added search functionality for bot patterns
* Improved logging to track blocked requests
* Updated pattern sync to include additional bot metadata

= 0.1.0 =
* Initial plugin release
* Added auto-sync feature for agent patterns
* Added manual sync option in settings

== Upgrade Notice ==

= 1.1.0 =
Important update: Bot blocking settings are now managed centrally through your Spyglasses dashboard. Visit spyglasses.io/app to configure your blocking rules. This provides better consistency and allows you to manage all your sites from one place.

= 1.0.0 =
Major update with improved caching plugin compatibility and enhanced reliability. Fixes detection issues on sites with aggressive caching and adds better error recovery.

= 0.3.2 =
Important bugfix: Fixes an issue that could cause "Custom Blocking Rules" settings to fail with an error.

= 0.3.1 =
Ignore WordPress internal cron requests.

= 0.3.0 =
This update adds AI referrer tracking to identify human visitors coming from AI platforms like ChatGPT, Perplexity, Claude, and other AI services.

= 0.2.0 =
This update adds bot blocking functionality to prevent unwanted AI agents and bots from accessing your site.

= 0.1.0 =
Initial plugin release