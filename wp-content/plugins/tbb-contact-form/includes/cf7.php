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
 * Canonical shortcode using the form’s numeric post ID (fallback when paste has no id token).
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

/**
 * Shortcode to store after save: keeps a pasted CF7 **hash** id (e.g. cd38aee) instead of rewriting
 * to the numeric post id, as long as it still resolves to the same form.
 *
 * @param object $cf WPCF7_ContactForm instance from tbb_contact_form_cf7_resolve().
 */
function tbb_contact_form_cf7_shortcode_for_storage(string $pasted, $cf): string {
	if (!is_object($cf) || !method_exists($cf, 'id')) {
		return '';
	}
	$post_id = (int) $cf->id();

	$pasted = trim($pasted);
	if (!preg_match('/^\[\s*contact-form-7\s+(.+)\s*\]\s*$/is', $pasted, $m)) {
		return tbb_contact_form_cf7_build_shortcode($cf);
	}
	$atts = shortcode_parse_atts($m[1]);
	if (!is_array($atts)) {
		return tbb_contact_form_cf7_build_shortcode($cf);
	}

	$id_raw = isset($atts['id']) ? trim((string) $atts['id']) : '';
	$title_from_user = isset($atts['title']) ? trim((string) $atts['title']) : '';

	$id_attr_value = '';

	// Numeric id first — otherwise values like "8959" match the hex-hash pattern.
	if ($id_raw !== '' && ctype_digit($id_raw)) {
		$n = absint($id_raw);
		if ($n === $post_id) {
			$id_attr_value = (string) $n;
		}
	} elseif ($id_raw !== '' && preg_match('/^[0-9a-f]{7,}$/i', $id_raw) && function_exists('wpcf7_get_contact_form_by_hash')) {
		$hash = strtolower(preg_replace('/[^0-9a-f]/i', '', $id_raw));
		$hform = wpcf7_get_contact_form_by_hash($hash);
		if ($hform && (int) $hform->id() === $post_id) {
			$id_attr_value = $hash;
		}
	}

	if ($id_attr_value === '') {
		return tbb_contact_form_cf7_build_shortcode($cf);
	}

	$t = $title_from_user !== '' ? $title_from_user : (method_exists($cf, 'title') ? (string) $cf->title() : '');

	$out = '[contact-form-7 id="' . $id_attr_value . '"';
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
