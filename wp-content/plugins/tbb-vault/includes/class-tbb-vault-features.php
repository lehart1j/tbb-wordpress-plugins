<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Vault_Features {
	public const META_KEY = '_tbb_vault_features';

	public static function register(): void {
		// Meta is read/written via update_post_meta only (no block binding).
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function definitions(): array {
		$defs = [
			'show_page_title' => __('Show page title', 'tbb-vault'),
			'show_featured_image' => __('Show featured image', 'tbb-vault'),
			'show_child_nav' => __('Show buttons / links to child pages', 'tbb-vault'),
			'show_breadcrumbs' => __('Show breadcrumb trail (parent pages)', 'tbb-vault'),
			'show_content' => __('Show main page content', 'tbb-vault'),
			'show_back_to_parent' => __('Show “Back” link to parent page', 'tbb-vault'),
			'show_memberpress_account' => __('Show MemberPress account link (if theme supports it)', 'tbb-vault'),
		];

		return (array) apply_filters('tbb_vault_feature_definitions', $defs);
	}

	/**
	 * @return array<string, bool>
	 */
	public static function defaults_for_template(string $template_slug): array {
		$all = array_fill_keys(array_keys(self::definitions()), false);
		switch ($template_slug) {
			case 'tbb-vault/welcome.php':
				return array_merge($all, [
					'show_page_title' => true,
					'show_featured_image' => true,
					'show_child_nav' => true,
					'show_content' => true,
				]);
			case 'tbb-vault/section.php':
				return array_merge($all, [
					'show_page_title' => true,
					'show_featured_image' => true,
					'show_child_nav' => true,
					'show_breadcrumbs' => true,
					'show_content' => true,
					'show_back_to_parent' => true,
				]);
			case 'tbb-vault/inner.php':
			default:
				return array_merge($all, [
					'show_page_title' => true,
					'show_featured_image' => false,
					'show_breadcrumbs' => true,
					'show_content' => true,
					'show_back_to_parent' => true,
				]);
		}
	}

	/**
	 * @param array<string, mixed>|null $stored
	 * @return array<string, bool>
	 */
	public static function merge(int $post_id, ?array $stored, string $template_slug): array {
		$defs = self::definitions();
		$base = self::defaults_for_template($template_slug);
		$out = [];
		foreach (array_keys($defs) as $slug) {
			$out[$slug] = !empty($base[$slug]);
			if (is_array($stored) && array_key_exists($slug, $stored)) {
				$out[$slug] = (bool) $stored[$slug];
			}
		}
		return (array) apply_filters('tbb_vault_page_features', $out, $post_id, $template_slug);
	}

	public static function get_for_post(int $post_id): array {
		$tpl = (string) get_page_template_slug($post_id);
		if ($tpl === '') {
			$tpl = 'tbb-vault/inner.php';
		}
		$raw = get_post_meta($post_id, self::META_KEY, true);
		$stored = is_array($raw) ? $raw : null;
		return self::merge($post_id, $stored, $tpl);
	}

	/**
	 * @param array<string, bool> $flags
	 */
	public static function save(int $post_id, array $flags): bool {
		$defs = self::definitions();
		$clean = [];
		foreach (array_keys($defs) as $slug) {
			$clean[$slug] = !empty($flags[$slug]);
		}
		return (bool) update_post_meta($post_id, self::META_KEY, $clean);
	}
}
