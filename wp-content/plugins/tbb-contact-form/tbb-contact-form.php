<?php
/**
 * Plugin Name: TBB Contact Form
 * Description: Popup shell that embeds Contact Form 7 via shortcode; optional GitHub updates and stored messages from legacy submissions.
 * Version: 1.3.0
 * Author: James Lehart | Lehart Productions Limited
 * License: GPL-2.0-or-later
 * Text Domain: tbb-contact-form
 * Requires Plugins: contact-form-7
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TBB_CONTACT_FORM_VERSION', '1.3.0');
define('TBB_CONTACT_FORM_PATH', plugin_dir_path(__FILE__));
define('TBB_CONTACT_FORM_URL', plugin_dir_url(__FILE__));

require_once TBB_CONTACT_FORM_PATH . 'includes/db-forms.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/cf7.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/db.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/settings.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/ajax.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/shortcode.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/admin.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/updates.php';

register_activation_hook(__FILE__, 'tbb_contact_form_activate');
function tbb_contact_form_activate(): void {
	tbb_contact_form_create_table();
	tbb_contact_form_create_forms_table();
	tbb_contact_form_upgrade_messages_table();
	tbb_contact_form_upgrade_forms_cf7_column();
	update_option('tbb_cf_db_version', 3);
}

/**
 * Run DB migrations when the plugin is updated without re-activating.
 */
function tbb_contact_form_maybe_migrate(): void {
	$v = (int) get_option('tbb_cf_db_version', 0);
	if ($v < 2) {
		tbb_contact_form_create_forms_table();
		tbb_contact_form_upgrade_messages_table();
		$v = 2;
		update_option('tbb_cf_db_version', $v);
	}
	if ($v < 3) {
		tbb_contact_form_upgrade_forms_cf7_column();
		update_option('tbb_cf_db_version', 3);
	}
}
add_action('plugins_loaded', 'tbb_contact_form_maybe_migrate', 5);

add_action('plugins_loaded', function (): void {
	new TBB_Contact_Form_Ajax();
	new TBB_Contact_Form_Shortcode();
	if (is_admin()) {
		new TBB_Contact_Form_Admin();
	}
	tbb_contact_form_maybe_enable_updates(__FILE__);
});
