<?php
/**
 * Plugin Name: TBB Contact Form
 * Description: Popup contact form that emails the admin and stores submissions for viewing in wp-admin.
 * Version: 1.2.1
 * Author: James Lehart | Lehart Productions Limited
 * License: GPL-2.0-or-later
 * Text Domain: tbb-contact-form
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TBB_CONTACT_FORM_VERSION', '1.2.1');
define('TBB_CONTACT_FORM_PATH', plugin_dir_path(__FILE__));
define('TBB_CONTACT_FORM_URL', plugin_dir_url(__FILE__));

require_once TBB_CONTACT_FORM_PATH . 'includes/db-forms.php';
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
	update_option('tbb_cf_db_version', 2);
}

/**
 * Run DB migrations when the plugin is updated without re-activating.
 */
function tbb_contact_form_maybe_migrate(): void {
	if ((int) get_option('tbb_cf_db_version', 0) >= 2) {
		return;
	}
	tbb_contact_form_create_forms_table();
	tbb_contact_form_upgrade_messages_table();
	update_option('tbb_cf_db_version', 2);
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
