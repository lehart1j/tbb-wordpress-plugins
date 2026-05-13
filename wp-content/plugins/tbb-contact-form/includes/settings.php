<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Notification recipients saved in Settings (comma or newline separated).
 * Falls back to site admin email when empty or invalid.
 *
 * @return list<string>
 */
function tbb_contact_form_get_notification_emails(): array {
	$raw = get_option('tbb_cf_notification_emails', '');
	if (!is_string($raw) || trim($raw) === '') {
		$fallback = (string) get_option('admin_email');
		return is_email($fallback) ? [$fallback] : [];
	}

	$parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
	if (!is_array($parts)) {
		$parts = [];
	}

	$out = [];
	foreach ($parts as $p) {
		$e = sanitize_email((string) $p);
		if ($e !== '' && is_email($e)) {
			$out[] = $e;
		}
	}

	$out = array_values(array_unique($out));

	if (empty($out)) {
		$fallback = (string) get_option('admin_email');
		return is_email($fallback) ? [$fallback] : [];
	}

	return $out;
}
