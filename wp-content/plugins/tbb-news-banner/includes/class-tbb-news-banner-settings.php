<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_News_Banner_Settings {
	public const OPTION = TBB_NEWS_BANNER_OPTION;
	public const SLOT_COUNT = 3;

	public static function register(): void {
		add_action('admin_init', [self::class, 'register_setting']);
	}

	public static function register_setting(): void {
		register_setting(
			'tbb_news_banner',
			self::OPTION,
			[
				'type' => 'array',
				'sanitize_callback' => [self::class, 'sanitize'],
				'default' => self::defaults(),
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'enabled' => true,
			'post_types' => self::default_post_types(),
			'use_manual' => false,
			'manual_items' => [
				['post_id' => 0, 'post_type' => ''],
				['post_id' => 0, 'post_type' => ''],
				['post_id' => 0, 'post_type' => ''],
			],
			'label' => __('Latest', 'tbb-news-banner'),
		];
	}

	/**
	 * Sensible defaults for TBB vault content + standard posts.
	 *
	 * @return list<string>
	 */
	public static function default_post_types(): array {
		$candidates = ['post', 'page', 'resources_pdf', 'video_bites', 'video_ppt', 'tbbz_live'];
		$out = [];
		foreach ($candidates as $pt) {
			if (post_type_exists($pt)) {
				$out[] = $pt;
			}
		}
		if (empty($out)) {
			$out[] = 'post';
		}
		return $out;
	}

	/**
	 * Public post types available for the pool (excludes internal types).
	 *
	 * @return array<string, string> slug => label
	 */
	public static function discover_post_types(): array {
		$types = get_post_types(['public' => true], 'objects');
		$exclude = apply_filters(
			'tbb_news_banner_excluded_post_types',
			['attachment', 'elementor_library', 'e-floating-buttons', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles']
		);

		$out = [];
		foreach ($types as $slug => $obj) {
			if (in_array($slug, $exclude, true)) {
				continue;
			}
			if (!$obj instanceof WP_Post_Type) {
				continue;
			}
			$out[$slug] = $obj->labels->singular_name ?: $slug;
		}
		asort($out);
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$raw = get_option(self::OPTION, []);
		if (!is_array($raw)) {
			return self::defaults();
		}
		return self::merge_with_defaults($raw);
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function merge_with_defaults(array $raw): array {
		$def = self::defaults();
		$merged = wp_parse_args($raw, $def);

		$types = isset($raw['post_types']) && is_array($raw['post_types'])
			? array_values(array_filter(array_map('sanitize_key', $raw['post_types'])))
			: $def['post_types'];
		$merged['post_types'] = $types;

		$manual = [];
		$raw_manual = isset($raw['manual_items']) && is_array($raw['manual_items']) ? $raw['manual_items'] : [];
		for ($i = 0; $i < self::SLOT_COUNT; $i++) {
			$row = isset($raw_manual[$i]) && is_array($raw_manual[$i]) ? $raw_manual[$i] : [];
			$manual[] = [
				'post_id' => isset($row['post_id']) ? absint($row['post_id']) : 0,
				'post_type' => isset($row['post_type']) ? sanitize_key((string) $row['post_type']) : '',
			];
		}
		$merged['manual_items'] = $manual;

		$merged['enabled'] = !empty($raw['enabled']);
		$merged['use_manual'] = !empty($raw['use_manual']);
		$merged['label'] = isset($raw['label']) ? sanitize_text_field((string) $raw['label']) : $def['label'];

		return $merged;
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize($input): array {
		if (!is_array($input)) {
			return self::defaults();
		}

		$discovered = array_keys(self::discover_post_types());
		$types = [];
		if (isset($input['post_types']) && is_array($input['post_types'])) {
			foreach ($input['post_types'] as $pt) {
				$pt = sanitize_key((string) $pt);
				if ($pt !== '' && in_array($pt, $discovered, true)) {
					$types[] = $pt;
				}
			}
		}
		if (empty($types)) {
			$types = self::default_post_types();
		}

		$manual = [];
		if (isset($input['manual_items']) && is_array($input['manual_items'])) {
			for ($i = 0; $i < self::SLOT_COUNT; $i++) {
				$row = isset($input['manual_items'][$i]) && is_array($input['manual_items'][$i])
					? $input['manual_items'][$i]
					: [];
				$pid = isset($row['post_id']) ? absint($row['post_id']) : 0;
				$ptype = isset($row['post_type']) ? sanitize_key((string) $row['post_type']) : '';
				if ($pid > 0 && $ptype !== '' && post_type_exists($ptype)) {
					$p = get_post($pid);
					if ($p instanceof WP_Post && $p->post_type === $ptype && $p->post_status === 'publish') {
						$manual[] = ['post_id' => $pid, 'post_type' => $ptype];
						continue;
					}
				}
				$manual[] = ['post_id' => 0, 'post_type' => ''];
			}
		} else {
			$manual = self::defaults()['manual_items'];
		}

		return [
			'enabled' => !empty($input['enabled']),
			'post_types' => array_values(array_unique($types)),
			'use_manual' => !empty($input['use_manual']),
			'manual_items' => $manual,
			'label' => isset($input['label']) ? sanitize_text_field((string) $input['label']) : __('Latest', 'tbb-news-banner'),
		];
	}
}
