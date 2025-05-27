=== Spyglasses - Bot & AI Agent Detection ===
Contributors: orchestraai
Tags: bot detection, ai detection, security, analytics, claude, perplexity
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 0.3.0
Requires PHP: 7.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Advanced AI agent detection for WordPress. Detect and monitor traffic from AI assistants like Claude, Perplexity, and track visitors from AI-generated content.

== Description ==

Spyglasses provides advanced detection for AI agents, bots, and AI referrer traffic that interacts with your WordPress site. With the increasing use of AI browsing assistants and AI content tools, it's becoming more important to understand how these technologies interact with your content.

### Key Features

* **AI Agent Detection**: Automatically detect traffic from AI browsing assistants
* **AI Referrer Tracking**: Identify human visitors who clicked links from AI-generated content
* **Real-time Monitoring**: Track and monitor AI-related traffic to your site
* **Bot Blocking**: Selectively block specific AI agents or entire categories of bots
* **AI Model Trainer Protection**: Prevent AI systems from scraping your content for model training
* **Dashboard Insights**: View detailed analytics on AI traffic via the Spyglasses dashboard
* **Lightweight**: Minimal impact on site performance and user experience
* **Easy Setup**: Simple configuration with just an API key
* **Auto-Updating Patterns**: Automatically syncs with latest AI detection patterns daily
* **Smart Filtering**: Automatically excludes WordPress internal processes to prevent log pollution

### How It Works

Spyglasses uses pattern recognition to identify:

1. **AI agents and bots** visiting your site through user-agent detection
2. **Human visitors from AI platforms** through referrer detection

When AI-related traffic is detected, information about the visit is securely sent to the Spyglasses collector for analysis and reporting. You can also choose to block specific types of bots from accessing your site.

### Privacy & Transparency

Spyglasses only collects information about AI-related traffic, not about your regular human visitors. All code is open source and available for review on GitHub.

== Installation ==

1. Upload the `spyglasses` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Spyglasses to configure the plugin
4. Enter your Spyglasses API key
5. That's it! Spyglasses will now detect and monitor AI-related traffic to your site
6. Optionally, configure blocking rules to prevent specific bots from accessing your content

== Frequently Asked Questions ==

= Do I need a Spyglasses account? =

Yes, you'll need to sign up for a Spyglasses account at [spyglasses.io](https://www.spyglasses.io) to get an API key.

= Does this slow down my website? =

No, Spyglasses is designed to be lightweight and only processes requests from detected AI agents and referrers. It has minimal impact on your site's performance.

= What information is collected? =

Spyglasses collects information about AI-related traffic, including user agent, IP address, requested URL, HTTP headers, and referrer information. No personal information about your regular visitors is collected.

= What's the difference between AI agents and AI referrers? =

**AI agents** are bots that browse your site directly (like Claude-SearchBot or Perplexity-User). These have distinctive user-agent strings and can be blocked if desired.

**AI referrers** are regular human visitors who clicked a link to your site from AI-generated content (like ChatGPT, Claude, or Perplexity). These are detected by their referrer information and are never blocked because they're real people.

= How can I see the data collected? =

You can view all data collected in your Spyglasses dashboard. Log in to your account at [spyglasses.io](https://www.spyglasses.io) to access your dashboard.

= How does bot blocking work? =

You can block bots at several levels: globally (all AI model trainers), by category, by subcategory, by bot type, or by specific pattern. Blocked bots receive a 403 Forbidden response. Note that AI referrer traffic (humans from AI platforms) is never blocked.

= Is this compatible with caching plugins? =

Yes, Spyglasses works with most caching plugins as it operates at the WordPress level, not the browser level.

= How are the detection patterns updated? =

By default, Spyglasses automatically syncs agent detection patterns daily from our API. This ensures you always have the latest patterns for new AI agents and referrers. You can also manually sync patterns from the settings page or disable automatic syncing if you prefer.

= How do I troubleshoot issues with Spyglasses? =

If you encounter problems (such as sync errors), enable "Debug Mode" in the Spyglasses settings page. This will log detailed error messages to your WordPress error log (usually at wp-content/debug.log).

To ensure logging works, add these lines to your wp-config.php if they are not already present:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

After reproducing the issue, check the debug.log file for messages starting with "Spyglasses:" and share them with support if needed.

== Screenshots ==

1. Spyglasses settings page
2. Spyglasses dashboard overview
3. AI agent detection in action
4. Bot blocking configuration
5. AI referrer tracking

== Changelog ==

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

= 0.3.1 =
Ignore WordPress internal cron requests.

= 0.3.0 =
This update adds AI referrer tracking to identify human visitors coming from AI platforms like ChatGPT, Perplexity, Claude, and other AI services.

= 0.2.0 =
This update adds bot blocking functionality to prevent unwanted AI agents and bots from accessing your site.

= 0.1.0 =
Initial plugin release