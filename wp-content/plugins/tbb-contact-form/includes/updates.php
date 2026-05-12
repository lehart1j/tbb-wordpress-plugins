<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Optional GitHub Releases update support (monorepo-friendly).
 *
 * Repo: define `TBB_CONTACT_FORM_GITHUB_REPO` as `owner/repo` in wp-config.php before this file loads,
 * or rely on the default baked into the plugin for TBB deployments.
 *
 * Release tag format: `tbb-contact-form/v1.2.3` (prefix matches plugin folder name).
 * Attach a release asset named `tbb-contact-form.zip` whose root folder is `tbb-contact-form/`.
 *
 * Do not rely on GitHub’s auto “Source code” zip for this plugin — it zips the whole repo with the wrong layout.
 */

/** @return bool */
function tbb_contact_form_str_starts_with(string $haystack, string $needle): bool {
	if ($needle === '') {
		return true;
	}
	return strncmp($haystack, $needle, strlen($needle)) === 0;
}

/** @return bool */
function tbb_contact_form_str_ends_with(string $haystack, string $needle): bool {
	if ($needle === '') {
		return true;
	}
	$len = strlen($needle);
	return $len <= strlen($haystack) && substr_compare($haystack, $needle, -$len, $len) === 0;
}

/**
 * Default GitHub repo for this plugin’s monorepo (override with TBB_CONTACT_FORM_GITHUB_REPO).
 */
function tbb_contact_form_github_repo(): string {
	if (defined('TBB_CONTACT_FORM_GITHUB_REPO')) {
		return (string) constant('TBB_CONTACT_FORM_GITHUB_REPO');
	}
	return 'lehart1j/tbb-wordpress-plugins';
}

/**
 * GitHub rejects many default HTTP clients; ensure User-Agent on all GitHub requests (API + downloads).
 *
 * @param array $args Request args.
 * @param mixed $url  Request URL (must tolerate non-string from core / extensions).
 * @return array
 */
function tbb_contact_form_github_http_request_args($args, $url) {
	if (!is_array($args) || !is_string($url) || strpos($url, 'github.com') === false) {
		return $args;
	}
	if (function_exists('get_bloginfo') && function_exists('home_url')) {
		$args['user-agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/');
	}

	return $args;
}

function tbb_contact_form_maybe_enable_updates(string $plugin_file): void {
	if (!is_admin()) {
		return;
	}

	static $updates_hooks_registered = false;
	if ($updates_hooks_registered) {
		return;
	}

	$repo = apply_filters('tbb_contact_form_github_repo', tbb_contact_form_github_repo());
	if ($repo === '' || strpos($repo, '/') === false) {
		return;
	}

	$updates_hooks_registered = true;

	add_filter('http_request_args', 'tbb_contact_form_github_http_request_args', 10, 2);

	add_filter(
		'site_transient_update_plugins',
		static function ($transient) use ($repo, $plugin_file) {
			static $in_filter = false;

			if ($in_filter || !is_object($transient)) {
				return $transient;
			}

			$in_filter = true;

			try {
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

				if (!isset($transient->response) || !is_array($transient->response)) {
					$transient->response = [];
				}

				$transient->response[ $plugin_basename ] = (object) [
					'slug' => 'tbb-contact-form',
					'plugin' => $plugin_basename,
					'new_version' => $remote_version,
					'url' => 'https://github.com/' . $repo,
					'package' => $package,
				];

				return $transient;
			} catch (\Throwable $e) {
				return $transient;
			} finally {
				$in_filter = false;
			}
		},
		10,
		1
	);
}

function tbb_contact_form_fetch_latest_release_for_tag_prefix(string $repo, string $tag_prefix): ?array {
	$cache_key = 'tbb_cf_rel_' . md5($repo . '|' . $tag_prefix);
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return $cached;
	}

	$url = 'https://api.github.com/repos/' . $repo . '/releases?per_page=30';
	$args = [
		'timeout' => 15,
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
		if ($tag !== '' && tbb_contact_form_str_starts_with($tag, $tag_prefix)) {
			$match = $release;
			break;
		}
	}

	if (!is_array($match)) {
		return null;
	}

	set_transient($cache_key, $match, 10 * MINUTE_IN_SECONDS);
	return $match;
}

function tbb_contact_form_extract_version_from_tag(string $tag, string $prefix): ?string {
	if (!tbb_contact_form_str_starts_with($tag, $prefix)) {
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

	foreach ($release['assets'] as $asset) {
		if (!is_array($asset)) {
			continue;
		}
		$name = isset($asset['name']) ? (string) $asset['name'] : '';
		if (tbb_contact_form_str_ends_with(strtolower($name), '.zip') && !empty($asset['browser_download_url'])) {
			return (string) $asset['browser_download_url'];
		}
	}

	return null;
}
