<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Optional GitHub Releases update support.
 *
 * How it works:
 * - If you define TBB_CONTACT_FORM_GITHUB_REPO as "owner/repo", the plugin will check GitHub releases.
 * - If the repo is private, also define TBB_CONTACT_FORM_GITHUB_TOKEN so it can read releases.
 *
 * Notes:
 * - For a monorepo (many plugins in one repo), use a tag prefix for each plugin:
 *     tbb-contact-form/v1.0.1
 * - Attach a zip asset built from this plugin folder, named:
 *     tbb-contact-form.zip
 */

function tbb_contact_form_maybe_enable_updates(string $plugin_file): void {
	if (!is_admin()) {
		return;
	}

	if (!defined('TBB_CONTACT_FORM_GITHUB_REPO')) {
		return;
	}

	$repo = (string) constant('TBB_CONTACT_FORM_GITHUB_REPO');
	if ($repo === '' || strpos($repo, '/') === false) {
		return;
	}

	add_filter('site_transient_update_plugins', function ($transient) use ($repo, $plugin_file) {
		if (!is_object($transient)) {
			return $transient;
		}

		$plugin_basename = plugin_basename($plugin_file);

		$release = tbb_contact_form_fetch_latest_release_for_tag_prefix($repo, 'tbb-contact-form/');
		if (!$release || empty($release['tag_name'])) {
			return $transient;
		}

		$tag = (string) $release['tag_name'];
		$remote_version = tbb_contact_form_extract_version_from_tag($tag, 'tbb-contact-form/');
		if ($remote_version === null) {
			return $transient;
		}

		if (version_compare($remote_version, TBB_CONTACT_FORM_VERSION, '<=')) {
			return $transient;
		}

		$package = tbb_contact_form_pick_release_zip($release, 'tbb-contact-form');
		if (!$package) {
			return $transient;
		}

		$transient->response[$plugin_basename] = (object) [
			'slug' => 'tbb-contact-form',
			'plugin' => $plugin_basename,
			'new_version' => $remote_version,
			'url' => 'https://github.com/' . $repo,
			'package' => $package,
		];

		return $transient;
	});
}

function tbb_contact_form_fetch_latest_release_for_tag_prefix(string $repo, string $tag_prefix): ?array {
	$cache_key = 'tbb_cf_rel_' . md5($repo . '|' . $tag_prefix);
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return $cached;
	}

	// We cannot use /releases/latest in a monorepo; instead scan recent releases and pick the
	// newest one whose tag_name starts with $tag_prefix.
	$url = 'https://api.github.com/repos/' . $repo . '/releases?per_page=30';
	$args = [
		'timeout' => 10,
		'headers' => [
			'Accept' => 'application/vnd.github+json',
			'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
		],
	];

	if (defined('TBB_CONTACT_FORM_GITHUB_TOKEN') && (string) constant('TBB_CONTACT_FORM_GITHUB_TOKEN') !== '') {
		$args['headers']['Authorization'] = 'token ' . (string) constant('TBB_CONTACT_FORM_GITHUB_TOKEN');
	}

	$res = wp_remote_get($url, $args);
	if (is_wp_error($res)) {
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code($res);
	if ($code !== 200) {
		return null;
	}

	$body = (string) wp_remote_retrieve_body($res);
	$json = json_decode($body, true);
	if (!is_array($json)) {
		return null;
	}

	$match = null;
	foreach ($json as $release) {
		if (!is_array($release)) {
			continue;
		}
		$tag = isset($release['tag_name']) ? (string) $release['tag_name'] : '';
		if ($tag !== '' && str_starts_with($tag, $tag_prefix)) {
			$match = $release;
			break; // API returns newest first.
		}
	}

	if (!is_array($match)) {
		return null;
	}

	set_transient($cache_key, $match, 10 * MINUTE_IN_SECONDS);
	return $match;
}

function tbb_contact_form_extract_version_from_tag(string $tag, string $prefix): ?string {
	if (!str_starts_with($tag, $prefix)) {
		return null;
	}

	$rest = substr($tag, strlen($prefix));
	if ($rest === false || $rest === '') {
		return null;
	}

	return ltrim($rest, 'v');
}

function tbb_contact_form_pick_release_zip(array $release, string $expected_basename): ?string {
	if (empty($release['assets']) || !is_array($release['assets'])) {
		// Fallback to the auto-generated source zipball (works for public repos only).
		if (!empty($release['zipball_url'])) {
			return (string) $release['zipball_url'];
		}
		return null;
	}

	foreach ($release['assets'] as $asset) {
		if (!is_array($asset)) {
			continue;
		}
		$name = isset($asset['name']) ? (string) $asset['name'] : '';
		if ($name === $expected_basename . '.zip' && !empty($asset['browser_download_url'])) {
			return (string) $asset['browser_download_url'];
		}
	}

	// If they attached any zip, use the first zip we see.
	foreach ($release['assets'] as $asset) {
		if (!is_array($asset)) {
			continue;
		}
		$name = isset($asset['name']) ? (string) $asset['name'] : '';
		if (str_ends_with(strtolower($name), '.zip') && !empty($asset['browser_download_url'])) {
			return (string) $asset['browser_download_url'];
		}
	}

	return null;
}

