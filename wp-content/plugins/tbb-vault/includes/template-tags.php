<?php
/**
 * Template helpers (loaded by vault page templates).
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array<string, bool>
 */
function tbb_vault_features(): array {
	$id = get_queried_object_id();
	return $id > 0 ? TBB_Vault_Features::get_for_post($id) : [];
}

function tbb_vault_feature_enabled(string $slug): bool {
	$f = tbb_vault_features();
	return !empty($f[$slug]);
}

function tbb_vault_welcome_page_id(): int {
	$o = TBB_Vault_Admin::options();
	return (int) $o['welcome_page_id'];
}

/**
 * @return int[] Ancestor page IDs from vault root down to immediate parent (empty if none).
 */
function tbb_vault_breadcrumb_ids(int $post_id): array {
	$root = tbb_vault_welcome_page_id();
	if ($root <= 0 || $post_id <= 0) {
		return [];
	}

	$chain = [];
	$current = get_post($post_id);
	while ($current instanceof WP_Post && $current->post_parent > 0) {
		$chain[] = (int) $current->post_parent;
		$current = get_post($current->post_parent);
	}
	$chain = array_reverse($chain);
	$idx = array_search($root, $chain, true);
	if ($idx === false) {
		return [];
	}
	return array_slice($chain, $idx);
}

function tbb_vault_memberpress_account_url(): string {
	if (class_exists('MeprOptions')) {
		$opts = \MeprOptions::fetch();
		if ($opts && !empty($opts->account_page_id)) {
			$url = get_permalink((int) $opts->account_page_id);
			if ($url) {
				return $url;
			}
		}
	}
	if (function_exists('mepr_account_url')) {
		return (string) mepr_account_url();
	}
	return home_url('/');
}
