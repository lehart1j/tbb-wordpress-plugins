<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Ajax {
	public function __construct() {
		add_action('wp_ajax_tbb_contact_submit', [$this, 'submit']);
		add_action('wp_ajax_nopriv_tbb_contact_submit', [$this, 'submit']);
	}

	public function submit(): void {
		check_ajax_referer('tbb_contact_form', 'nonce');

		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
		$message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';
		$page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';

		if ($name === '' || $email === '' || $message === '') {
			wp_send_json_error(['message' => __('Please fill in name, email, and message.', 'tbb-contact-form')], 400);
		}

		if (!is_email($email)) {
			wp_send_json_error(['message' => __('Please enter a valid email address.', 'tbb-contact-form')], 400);
		}

		$ip = '';
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		$user_agent = '';
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
		}

		$id = tbb_contact_form_insert_message([
			'name' => $name,
			'email' => $email,
			'subject' => $subject,
			'message' => $message,
			'page_url' => $page_url,
			'ip_address' => $ip,
			'user_agent' => $user_agent,
		]);

		if ($id <= 0) {
			wp_send_json_error(['message' => __('Sorry, something went wrong. Please try again.', 'tbb-contact-form')], 500);
		}

		$admin_email = (string) get_option('admin_email');
		$mail_subject = $subject !== '' ? $subject : __('New contact form submission', 'tbb-contact-form');
		$lines = [
			__('You received a new contact form submission:', 'tbb-contact-form'),
			'',
			sprintf(__('Name: %s', 'tbb-contact-form'), $name),
			sprintf(__('Email: %s', 'tbb-contact-form'), $email),
		];
		if ($page_url !== '') {
			$lines[] = sprintf(__('Page: %s', 'tbb-contact-form'), $page_url);
		}
		$lines[] = '';
		$lines[] = __('Message:', 'tbb-contact-form');
		$lines[] = wp_strip_all_tags($message);
		$body = implode("\n", $lines);

		$headers = [];
		if ($admin_email !== '') {
			$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
		}
		wp_mail($admin_email, $mail_subject, $body, $headers);

		wp_send_json_success([
			'message' => __('Thanks! Your message was sent.', 'tbb-contact-form'),
			'id' => $id,
		]);
	}
}

