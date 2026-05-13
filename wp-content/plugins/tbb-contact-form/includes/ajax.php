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

		$form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
		if ($form_id <= 0) {
			wp_send_json_error(['message' => __('Invalid form.', 'tbb-contact-form')], 400);
		}

		$form = tbb_contact_form_get_form($form_id);
		if (!$form) {
			wp_send_json_error(['message' => __('Invalid form.', 'tbb-contact-form')], 400);
		}

		$defs = tbb_contact_form_parse_fields_json((string) $form['fields_json']);
		if (empty($defs)) {
			wp_send_json_error(['message' => __('This form has no fields.', 'tbb-contact-form')], 400);
		}

		$sanitized = [];
		foreach ($defs as $field) {
			$key = $field['name'];
			$raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : null;

			if ($field['required'] && ($raw === null || $raw === '')) {
				wp_send_json_error(
					[
						/* translators: %s field label */
						'message' => sprintf(__('Please fill in: %s', 'tbb-contact-form'), $field['label']),
					],
					400
				);
			}

			if ($raw === null || $raw === '') {
				$sanitized[$key] = '';
				continue;
			}

			$type = $field['type'];
			if ($type === 'email') {
				$val = sanitize_email((string) $raw);
				if ($field['required'] && !is_email($val)) {
					wp_send_json_error(['message' => __('Please enter a valid email address.', 'tbb-contact-form')], 400);
				}
				$sanitized[$key] = $val;
			} elseif ($type === 'textarea') {
				$sanitized[$key] = wp_kses_post((string) $raw);
			} elseif ($type === 'url') {
				$sanitized[$key] = esc_url_raw((string) $raw);
			} elseif ($type === 'number') {
				$sanitized[$key] = is_numeric($raw) ? (string) $raw : '';
			} elseif ($type === 'tel') {
				$sanitized[$key] = sanitize_text_field((string) $raw);
			} elseif ($type === 'select') {
				$allowed = array_map('trim', explode('|', (string) ($field['options'] ?? '')));
				$allowed = array_filter($allowed);
				$choice = sanitize_text_field((string) $raw);
				if ($field['required'] && ($choice === '' || !in_array($choice, $allowed, true))) {
					wp_send_json_error(['message' => __('Please choose a valid option.', 'tbb-contact-form')], 400);
				}
				if (!$field['required'] && $choice !== '' && !in_array($choice, $allowed, true)) {
					wp_send_json_error(['message' => __('Please choose a valid option.', 'tbb-contact-form')], 400);
				}
				$sanitized[$key] = $choice;
			} else {
				$sanitized[$key] = sanitize_text_field((string) $raw);
			}
		}

		$page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';

		$ip = '';
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		$user_agent = '';
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
		}

		$reply_email = '';
		$reply_name = '';
		foreach ($defs as $field) {
			if ($field['type'] === 'email' && $reply_email === '') {
				$reply_email = (string) ($sanitized[$field['name']] ?? '');
			}
		}
		foreach ($defs as $field) {
			if ($field['type'] === 'text' && ($field['name'] === 'name' || $field['name'] === 'your_name' || stripos($field['name'], 'name') !== false)) {
				$reply_name = (string) ($sanitized[$field['name']] ?? '');
				break;
			}
		}
		if ($reply_name === '') {
			foreach ($defs as $field) {
				if ($field['type'] === 'text' && $reply_name === '') {
					$candidate = (string) ($sanitized[$field['name']] ?? '');
					if ($candidate !== '') {
						$reply_name = $candidate;
						break;
					}
				}
			}
		}

		$lines = [];
		foreach ($defs as $field) {
			$k = $field['label'];
			$v = isset($sanitized[$field['name']]) ? (string) $sanitized[$field['name']] : '';
			$lines[] = $k . ': ' . wp_strip_all_tags($v);
		}
		$message_body = implode("\n", $lines);

		$mail_subject = sprintf(
			/* translators: %s form title */
			__('[%s] New submission', 'tbb-contact-form'),
			(string) $form['title']
		);

		$fields_json = wp_json_encode($sanitized);

		$insert_id = tbb_contact_form_insert_message([
			'form_id' => $form_id,
			'name' => $reply_name,
			'email' => $reply_email,
			'subject' => $mail_subject,
			'message' => $message_body,
			'fields_data' => $fields_json,
			'page_url' => $page_url,
			'ip_address' => $ip,
			'user_agent' => $user_agent,
		]);

		if ($insert_id <= 0) {
			wp_send_json_error(['message' => __('Sorry, something went wrong. Please try again.', 'tbb-contact-form')], 500);
		}

		$email_intro = [
			__('You received a new form submission.', 'tbb-contact-form'),
			'',
			sprintf(__('Form: %s', 'tbb-contact-form'), (string) $form['title']),
		];
		if ($page_url !== '') {
			$email_intro[] = sprintf(__('Page: %s', 'tbb-contact-form'), $page_url);
		}
		$email_intro[] = '';
		$body = implode("\n", $email_intro) . $message_body;

		$headers = [];
		if (is_email($reply_email)) {
			$from_name = $reply_name !== '' ? $reply_name : $reply_email;
			$headers[] = 'Reply-To: ' . $from_name . ' <' . $reply_email . '>';
		}

		$recipients = tbb_contact_form_get_notification_emails();
		if (!empty($recipients)) {
			wp_mail($recipients, $mail_subject, $body, $headers);
		}

		wp_send_json_success([
			'message' => __('Thanks! Your message was sent.', 'tbb-contact-form'),
			'id' => $insert_id,
		]);
	}
}
