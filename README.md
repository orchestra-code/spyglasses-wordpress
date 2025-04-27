# Spyglasses for WordPress

Advanced AI agent and bot detection for WordPress websites.

## Description

Spyglasses helps you detect and monitor AI agents and bots that visit your WordPress site. With the increasing use of AI browsing assistants like Claude, Perplexity, and others, it's becoming more important to understand how these agents interact with your content.

This plugin is part of the [Spyglasses](https://www.spyglasses.io) platform, which provides comprehensive monitoring and analytics for bot and AI agent traffic.

## Features

- **AI Agent Detection**: Automatically detect traffic from AI browsing assistants
- **Real-time Monitoring**: Track and monitor AI agent visits to your site
- **Dashboard Insights**: View detailed analytics on AI traffic via the Spyglasses dashboard
- **Lightweight**: Minimal impact on site performance and user experience
- **Easy Setup**: Simple configuration with just an API key
- **Auto-Updating Patterns**: Automatically syncs with latest AI agent patterns daily

## Installation

### From WordPress.org

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Spyglasses"
3. Click "Install Now" and then "Activate"
4. Go to Settings > Spyglasses to configure the plugin

### Manual Installation

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin in your WordPress admin
3. Upload the zip file and click "Install Now"
4. Activate the plugin
5. Go to Settings > Spyglasses to configure the plugin

## Configuration

1. Sign up for a Spyglasses account at [spyglasses.io](https://www.spyglasses.io)
2. Get your API key from the Spyglasses dashboard
3. Enter your API key in the Spyglasses settings page in WordPress
4. That's it! Spyglasses will now detect and monitor AI agent traffic to your site

## How It Works

Spyglasses uses pattern recognition to identify AI agents and bots visiting your site. When an AI agent is detected, information about the visit is securely sent to the Spyglasses collector for analysis and reporting.

You can view all collected data in your Spyglasses dashboard.

### Pattern Syncing

The plugin automatically syncs detection patterns daily from the Spyglasses API to ensure you always have the latest patterns for detecting new AI agents. You can:

- Enable/disable automatic syncing
- Manually sync patterns at any time
- See when patterns were last updated and how many are active

This ensures you don't need to manually update the plugin whenever new AI agents are identified.

## Privacy & Transparency

Spyglasses only collects information about bot and AI agent traffic, not about your human visitors. The plugin checks user agents against known patterns of AI agents and only sends data to the Spyglasses collector if a match is found.

All code is open source and available for review in this repository.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## Support

If you need help with the plugin, please contact us at support@spyglasses.io or visit [our documentation](https://www.spyglasses.io/docs).

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details. 