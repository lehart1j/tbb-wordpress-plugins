<?php
/**
 * Plugin Name: TBB Vault
 * Description: MemberPress-friendly vault navigation: plugin page templates, per-page feature toggles, and a vault tree in wp-admin.
 * Version: 1.0.0
 * Author: James Lehart | Lehart Productions Limited
 * License: GPL-2.0-or-later
 * Text Domain: tbb-vault
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TBB_VAULT_VERSION', '1.0.0');
define('TBB_VAULT_PATH', plugin_dir_path(__FILE__));
define('TBB_VAULT_URL', plugin_dir_url(__FILE__));

require_once TBB_VAULT_PATH . 'includes/class-tbb-vault-features.php';
require_once TBB_VAULT_PATH . 'includes/class-tbb-vault-templates.php';
require_once TBB_VAULT_PATH . 'includes/class-tbb-vault-admin.php';
require_once TBB_VAULT_PATH . 'includes/class-tbb-vault-editor-ui.php';
require_once TBB_VAULT_PATH . 'includes/class-tbb-vault.php';

register_activation_hook(__FILE__, static function (): void {
	if (!get_option('tbb_vault_options')) {
		add_option('tbb_vault_options', ['welcome_page_id' => 0], '', false);
	}
});

add_action('plugins_loaded', static function (): void {
	TBB_Vault::instance();
});
