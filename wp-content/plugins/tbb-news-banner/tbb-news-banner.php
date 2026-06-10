<?php
/**
 * Plugin Name: TBB News Banner
 * Description: Fixed news strip above the header showing the latest site content, with admin filters and manual three-item override.
 * Version: 1.1.1
 * Author: James Lehart | Lehart Productions Limited
 * License: GPL-2.0-or-later
 * Text Domain: tbb-news-banner
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TBB_NEWS_BANNER_VERSION', '1.1.1');
define('TBB_NEWS_BANNER_PATH', plugin_dir_path(__FILE__));
define('TBB_NEWS_BANNER_URL', plugin_dir_url(__FILE__));
define('TBB_NEWS_BANNER_OPTION', 'tbb_news_banner_settings');

require_once TBB_NEWS_BANNER_PATH . 'includes/class-tbb-news-banner-settings.php';
require_once TBB_NEWS_BANNER_PATH . 'includes/class-tbb-news-banner-content.php';
require_once TBB_NEWS_BANNER_PATH . 'includes/class-tbb-news-banner-admin.php';
require_once TBB_NEWS_BANNER_PATH . 'includes/class-tbb-news-banner-frontend.php';

add_action('plugins_loaded', static function (): void {
	TBB_News_Banner_Settings::register();
	TBB_News_Banner_Admin::register();
	TBB_News_Banner_Frontend::register();
});

register_activation_hook(__FILE__, static function (): void {
	if (!get_option(TBB_NEWS_BANNER_OPTION)) {
		add_option(TBB_NEWS_BANNER_OPTION, TBB_News_Banner_Settings::defaults(), '', false);
	}
});
