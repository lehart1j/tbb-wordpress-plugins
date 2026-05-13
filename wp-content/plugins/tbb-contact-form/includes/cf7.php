<?php

if (!defined('ABSPATH')) {
	exit;
}

function tbb_contact_form_cf7_is_active(): bool {
	return class_exists('WPCF7_ContactForm') && function_exists('wpcf7_contact_form');
}

/**
 * Resolve a pasted CF7 shortcode to a contact form object (numeric id, hash id, or title).
 */
function tbb_contact_form_cf7_resolve(string $raw) {
	$raw = trim($raw);
	if ($raw === '' || !tbb_contact_form_cf7_is_active()) {
		return null;
	}

	if (!preg_match('/^\[\s*contact-form-7\s+(.+)\s*\]\s*$/is', $raw, $m)) {
		return null;
	}

	$inner = $m[1];
	$atts = shortcode_parse_atts($inner);
	if (!is_array($atts)) {
		return null;
	}

	$id = isset($atts['id']) ? trim((string) $atts['id']) : '';
	$title = isset($atts['title']) ? trim((string) $atts['title']) : '';

	$contact_form = null;
	if ($id !== '' && function_exists('wpcf7_get_contact_form_by_hash')) {
		$by_hash = wpcf7_get_contact_form_by_hash($id);
		if ($by_hash) {
			$contact_form = $by_hash;
		}
	}
	if (!$contact_form && $id !== '' && ctype_digit($id)) {
		$contact_form = wpcf7_contact_form((int) $id);
	}
	if (!$contact_form && $title !== '' && function_exists('wpcf7_get_contact_form_by_title')) {
		$contact_form = wpcf7_get_contact_form_by_title($title);
	}

	return is_object($contact_form) && class_exists('WPCF7_ContactForm') && is_a($contact_form, 'WPCF7_ContactForm', false)
		? $contact_form
		: null;
}

/**
 * Canonical shortcode string stored in the database (matches CF7’s usual shape).
 *
 * @param object $cf WPCF7_ContactForm instance.
 */
function tbb_contact_form_cf7_build_shortcode($cf): string {
	if (!is_object($cf) || !method_exists($cf, 'id')) {
		return '';
	}
	$fid = (int) $cf->id();
	$t = method_exists($cf, 'title') ? (string) $cf->title() : '';
	$out = '[contact-form-7 id="' . $fid . '"';
	if ($t !== '') {
		$out .= ' title="' . htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
	}
	$out .= ']';
	return $out;
}

function tbb_contact_form_cf7_enqueue_assets(): void {
	if (!tbb_contact_form_cf7_is_active()) {
		return;
	}
	if (function_exists('wpcf7_enqueue_scripts')) {
		wpcf7_enqueue_scripts();
	}
	if (function_exists('wpcf7_enqueue_styles')) {
		wpcf7_enqueue_styles();
	}
}
