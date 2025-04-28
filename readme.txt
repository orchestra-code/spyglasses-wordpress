=== Spyglasses - Bot & AI Agent Detection ===
Contributors: orchestraai
Tags: bot detection, ai detection, security, analytics, claude, perplexity
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 0.1.0
Requires PHP: 7.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Advanced AI agent and bot detection for WordPress. Detect and monitor traffic from AI assistants like Claude, Perplexity, and others.

== Description ==

Spyglasses provides advanced detection for AI agents and bots that visit your WordPress site. With the increasing use of AI browsing assistants like Claude, Perplexity, and others, it's becoming more important to understand how these agents interact with your content.

### Key Features

* **AI Agent Detection**: Automatically detect traffic from AI browsing assistants
* **Real-time Monitoring**: Track and monitor AI agent visits to your site
* **Dashboard Insights**: View detailed analytics on AI traffic via the Spyglasses dashboard
* **Lightweight**: Minimal impact on site performance and user experience
* **Easy Setup**: Simple configuration with just an API key
* **Auto-Updating Patterns**: Automatically syncs with latest AI agent patterns daily

### How It Works

Spyglasses uses pattern recognition to identify AI agents and bots visiting your site. When an AI agent is detected, information about the visit is securely sent to the Spyglasses collector for analysis and reporting.

### Privacy & Transparency

Spyglasses only collects information about bot and AI agent traffic, not about your human visitors. All code is open source and available for review on GitHub.

== Installation ==

1. Upload the `spyglasses` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Spyglasses to configure the plugin
4. Enter your Spyglasses API key
5. That's it! Spyglasses will now detect and monitor AI agent traffic to your site

== Frequently Asked Questions ==

= Do I need a Spyglasses account? =

Yes, you'll need to sign up for a Spyglasses account at [spyglasses.io](https://www.spyglasses.io) to get an API key.

= Does this slow down my website? =

No, Spyglasses is designed to be lightweight and only processes requests from detected bots and AI agents. It has minimal impact on your site's performance.

= What information is collected? =

Spyglasses only collects information about bot and AI agent traffic, including user agent, IP address, requested URL, and HTTP headers. No personal information about your human visitors is collected.

= How can I see the data collected? =

You can view all data collected in your Spyglasses dashboard. Log in to your account at [spyglasses.io](https://www.spyglasses.io) to access your dashboard.

= Is this compatible with caching plugins? =

Yes, Spyglasses works with most caching plugins as it operates at the WordPress level, not the browser level.

= How are the agent patterns updated? =

By default, Spyglasses automatically syncs agent detection patterns daily from our API. This ensures you always have the latest patterns for new AI agents. You can also manually sync patterns from the settings page or disable automatic syncing if you prefer.

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

== Changelog ==

= 0.1.0 =
* Initial plugin release
* Added auto-sync feature for agent patterns
* Added manual sync option in settings

== Upgrade Notice ==

= 0.1.0 =
Initial plugin release 