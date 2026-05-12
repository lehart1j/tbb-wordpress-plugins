<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Admin {
	/** @var TBB_Contact_Form_Admin_Forms */
	private $forms_admin;

	public function __construct() {
		require_once TBB_CONTACT_FORM_PATH . 'includes/admin-forms.php';
		require_once TBB_CONTACT_FORM_PATH . 'includes/admin-messages.php';

		$this->forms_admin = new TBB_Contact_Form_Admin_Forms();

		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'route_messages_actions']);
	}

	public function route_messages_actions(): void {
		$messages = new TBB_Contact_Form_Admin_Messages();
		$messages->handle_routing();
	}

	public function register_menu(): void {
		add_menu_page(
			__('TBB Contact', 'tbb-contact-form'),
			__('TBB Contact', 'tbb-contact-form'),
			'manage_options',
			TBB_Contact_Form_Admin_Forms::PAGE_SLUG,
			[$this, 'render_forms'],
			'dashicons-email-alt2',
			26
		);

		add_submenu_page(
			TBB_Contact_Form_Admin_Forms::PAGE_SLUG,
			__('Messages', 'tbb-contact-form'),
			__('Messages', 'tbb-contact-form'),
			'manage_options',
			TBB_Contact_Form_Admin_Messages::PAGE_SLUG,
			[$this, 'render_messages']
		);
	}

	public function render_forms(): void {
		$this->forms_admin->render();
	}

	public function render_messages(): void {
		$messages = new TBB_Contact_Form_Admin_Messages();
		$messages->render();
	}
}
