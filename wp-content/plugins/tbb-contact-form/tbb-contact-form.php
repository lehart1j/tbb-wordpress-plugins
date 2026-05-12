<?php
/**
 * Plugin Name: TBB Contact Form
 * Description: Popup contact form that emails the admin and stores submissions for viewing in wp-admin.
 * Version: 1.0.0
 * Author: James Lehart | Lehart Productions Limited
 * License: GPL-2.0-or-later
 * Text Domain: tbb-contact-form
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TBB_CONTACT_FORM_VERSION', '1.0.0');
define('TBB_CONTACT_FORM_PATH', plugin_dir_path(__FILE__));
define('TBB_CONTACT_FORM_URL', plugin_dir_url(__FILE__));

require_once TBB_CONTACT_FORM_PATH . 'includes/db.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/ajax.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/shortcode.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/admin.php';
require_once TBB_CONTACT_FORM_PATH . 'includes/updates.php';

register_activation_hook(__FILE__, 'tbb_contact_form_activate');
function tbb_contact_form_activate(): void {
	tbb_contact_form_create_table();
}

add_action('plugins_loaded', function (): void {
	new TBB_Contact_Form_Ajax();
	new TBB_Contact_Form_Shortcode();
	if (is_admin()) {
		new TBB_Contact_Form_Admin();
	}
	tbb_contact_form_maybe_enable_updates(__FILE__);
});
