<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_News_Banner_Content {
	/**
	 * @return list<array{id: int, title: string, url: string, type: string, type_label: string, date: string, image: string, excerpt: string}>
	 */
	public static function get_items(): array {
		$settings = TBB_News_Banner_Settings::get();
		if (empty($settings['enabled'])) {
			return [];
		}

		$auto = self::from_auto($settings['post_types']);

		if (!empty($settings['use_manual'])) {
			$manual = self::from_manual($settings['manual_items']);
			if (count($manual) >= TBB_News_Banner_Settings::SLOT_COUNT) {
				return array_slice($manual, 0, TBB_News_Banner_Settings::SLOT_COUNT);
			}
			return array_slice(self::fill_slots($manual, $auto, TBB_News_Banner_Settings::SLOT_COUNT), 0, TBB_News_Banner_Settings::SLOT_COUNT);
		}

		return array_slice($auto, 0, TBB_News_Banner_Settings::SLOT_COUNT);
	}

	/**
	 * @param list<array{id: int, title: string, url: string, type: string, type_label: string, date: string}> $primary
	 * @param list<array{id: int, title: string, url: string, type: string, type_label: string, date: string}> $fallback
	 * @return list<array{id: int, title: string, url: string, type: string, type_label: string, date: string}>
	 */
	private static function fill_slots(array $primary, array $fallback, int $count): array {
		$out = $primary;
		$seen = [];
		foreach ($out as $item) {
			$seen[$item['type'] . ':' . $item['id']] = true;
		}
		foreach ($fallback as $item) {
			if (count($out) >= $count) {
				break;
			}
			$key = $item['type'] . ':' . $item['id'];
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * @param list<array{post_id: int, post_type: string}> $rows
	 * @return list<array{id: int, title: string, url: string, type: string, type_label: string, date: string}>
	 */
	private static function from_manual(array $rows): array {
		$out = [];
		foreach ($rows as $row) {
			$id = (int) ($row['post_id'] ?? 0);
			$type = (string) ($row['post_type'] ?? '');
			if ($id <= 0 || $type === '') {
				continue;
			}
			$item = self::post_to_item(get_post($id));
			if ($item !== null) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Latest published content across enabled post types (newest first).
	 *
	 * @param list<string> $post_types
	 * @return list<array{id: int, title: string, url: string, type: string, type_label: string, date: string}>
	 */
	private static function from_auto(array $post_types): array {
		$post_types = array_values(array_filter($post_types, 'post_type_exists'));
		if (empty($post_types)) {
			return [];
		}

		$q = new WP_Query([
			'post_type' => $post_types,
			'post_status' => 'publish',
			'posts_per_page' => TBB_News_Banner_Settings::SLOT_COUNT * 12,
			'orderby' => 'date',
			'order' => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows' => true,
		]);

		$out = [];
		$seen = [];
		if ($q->have_posts()) {
			foreach ($q->posts as $post) {
				if (!$post instanceof WP_Post) {
					continue;
				}
				$key = $post->post_type . ':' . $post->ID;
				if (isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$item = self::post_to_item($post);
				if ($item !== null) {
					$out[] = $item;
				}
				if (count($out) >= TBB_News_Banner_Settings::SLOT_COUNT) {
					break;
				}
			}
		}
		wp_reset_postdata();

		return $out;
	}

	/**
	 * Vault CPTs are listed on inner pages — they have no front-end single view.
	 *
	 * @return list<string>
	 */
	private static function vault_cpt_types(): array {
		return apply_filters(
			'tbb_news_banner_vault_cpt_types',
			['resources_pdf', 'video_bites', 'video_ppt', 'tbbz_live']
		);
	}

	private static function is_vault_cpt(WP_Post $post): bool {
		if (in_array($post->post_type, self::vault_cpt_types(), true)) {
			return true;
		}
		return is_object_in_taxonomy($post->post_type, 'content_page_name');
	}

	/**
	 * Banner links must not point at raw uploads (bypasses vault / MemberPress gates).
	 */
	private static function is_safe_banner_url(string $url): bool {
		$path = (string) wp_parse_url($url, PHP_URL_PATH);
		if ($path === '') {
			return true;
		}
		if (str_contains($path, '/wp-content/uploads/')) {
			return false;
		}
		return !preg_match('/\.(pdf|docx?|pptx?|xlsx?|zip|rar)(\?.*)?$/i', $path);
	}

	/**
	 * @param mixed $value ACF taxonomy field value (id, term object, or list).
	 * @return list<int>
	 */
	private static function normalize_term_ids($value): array {
		if ($value === null || $value === false || $value === '') {
			return [];
		}
		if (is_numeric($value)) {
			return [(int) $value];
		}
		if ($value instanceof WP_Term) {
			return [(int) $value->term_id];
		}
		if (!is_array($value)) {
			return [];
		}

		$ids = [];
		foreach ($value as $item) {
			$ids = array_merge($ids, self::normalize_term_ids($item));
		}
		return array_values(array_unique(array_filter($ids)));
	}

	/**
	 * @return list<WP_Post>
	 */
	private static function vault_inner_pages(): array {
		static $cache = null;
		if (is_array($cache)) {
			return $cache;
		}

		$templates = apply_filters('tbb_news_banner_vault_inner_templates', [
			'template-inner.php',
			'tbb-vault/inner.php',
		]);

		$pages = get_posts([
			'post_type' => 'page',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'meta_query' => [
				[
					'key' => '_wp_page_template',
					'value' => $templates,
					'compare' => 'IN',
				],
			],
		]);

		$cache = array_values(array_filter($pages, static fn($page): bool => $page instanceof WP_Post));
		return $cache;
	}

	/**
	 * Resolve a banner link for a post (vault CPTs always → parent inner page).
	 */
	private static function resolve_item_url(WP_Post $post): ?string {
		$filtered = apply_filters('tbb_news_banner_item_url', null, $post);
		if (is_string($filtered) && $filtered !== '' && self::is_safe_banner_url($filtered)) {
			return $filtered;
		}

		if (self::is_vault_cpt($post)) {
			return self::vault_inner_page_url_for_cpt($post);
		}

		if (!is_post_type_viewable($post->post_type)) {
			return null;
		}

		$url = get_permalink($post);
		if (!$url || !self::is_safe_banner_url($url)) {
			return null;
		}
		return $url;
	}

	/**
	 * Inner vault page that displays this CPT (via content_page_name + select_category_page).
	 */
	private static function vault_inner_page_url_for_cpt(WP_Post $post): ?string {
		$terms = wp_get_post_terms($post->ID, 'content_page_name', ['fields' => 'ids']);
		if (is_wp_error($terms)) {
			return null;
		}
		$term_ids = self::normalize_term_ids($terms);
		if (empty($term_ids)) {
			return null;
		}

		if (function_exists('get_field')) {
			foreach (self::vault_inner_pages() as $page) {
				$selected = self::normalize_term_ids(get_field('select_category_page', $page->ID));
				if (empty($selected) || !array_intersect($term_ids, $selected)) {
					continue;
				}
				$url = get_permalink($page);
				if ($url && self::is_safe_banner_url($url)) {
					return $url;
				}
			}
		}

		$pages = get_posts([
			'post_type' => 'page',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'meta_query' => [
				[
					'key' => 'select_category_page',
					'value' => array_map('strval', $term_ids),
					'compare' => 'IN',
				],
			],
		]);

		if (empty($pages[0]) || !$pages[0] instanceof WP_Post) {
			return null;
		}

		$url = get_permalink($pages[0]);
		if (!$url || !self::is_safe_banner_url($url)) {
			return null;
		}
		return $url;
	}

	/**
	 * @return array{id: int, title: string, url: string, type: string, type_label: string, date: string, image: string, excerpt: string}|null
	 */
	public static function post_to_item(?WP_Post $post): ?array {
		if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
			return null;
		}

		$url = self::resolve_item_url($post);
		if (!$url) {
			return null;
		}

		$title = get_the_title($post);
		if ($title === '') {
			$title = __('(No title)', 'tbb-news-banner');
		}

		$obj = get_post_type_object($post->post_type);
		$type_label = $obj instanceof WP_Post_Type
			? ($obj->labels->singular_name ?: $post->post_type)
			: $post->post_type;

		return [
			'id' => (int) $post->ID,
			'title' => $title,
			'url' => $url,
			'type' => $post->post_type,
			'type_label' => $type_label,
			'date' => get_the_date('', $post),
			'image' => self::preview_image_url($post),
			'excerpt' => self::preview_excerpt($post),
		];
	}

	private static function preview_image_url(WP_Post $post): string {
		$filtered = apply_filters('tbb_news_banner_item_image', null, $post);
		if (is_string($filtered) && $filtered !== '') {
			return $filtered;
		}

		$thumb = get_the_post_thumbnail_url($post, 'thumbnail');
		if (is_string($thumb) && $thumb !== '') {
			return $thumb;
		}

		if ($post->post_type === 'resources_pdf' && function_exists('get_field')) {
			$icon_id = get_field('pdf_image_icon', $post->ID);
			if (is_numeric($icon_id)) {
				$icon = wp_get_attachment_image_url((int) $icon_id, 'thumbnail');
				if (is_string($icon) && $icon !== '') {
					return $icon;
				}
			}
		}

		return '';
	}

	private static function preview_excerpt(WP_Post $post): string {
		$filtered = apply_filters('tbb_news_banner_item_excerpt', null, $post);
		if (is_string($filtered)) {
			return trim($filtered);
		}

		$manual = trim((string) $post->post_excerpt);
		if ($manual !== '') {
			return wp_trim_words(wp_strip_all_tags($manual), 14, '…');
		}

		$content = trim(wp_strip_all_tags(strip_shortcodes($post->post_content)));
		if ($content !== '') {
			return wp_trim_words($content, 14, '…');
		}

		$fallbacks = [
			'resources_pdf' => __('New downloadable resource.', 'tbb-news-banner'),
			'video_bites' => __('New short video.', 'tbb-news-banner'),
			'video_ppt' => __('New presentation video.', 'tbb-news-banner'),
			'tbbz_live' => __('New live session recording.', 'tbb-news-banner'),
		];

		return $fallbacks[$post->post_type] ?? '';
	}

	/**
	 * Recent posts for admin pickers.
	 *
	 * @param list<string> $post_types
	 * @return list<WP_Post>
	 */
	public static function recent_posts_for_admin(array $post_types, int $limit = 80): array {
		$post_types = array_values(array_filter($post_types, 'post_type_exists'));
		if (empty($post_types)) {
			return [];
		}

		$q = new WP_Query([
			'post_type' => $post_types,
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows' => true,
		]);

		$posts = [];
		if ($q->have_posts()) {
			foreach ($q->posts as $p) {
				if ($p instanceof WP_Post) {
					$posts[] = $p;
				}
			}
		}
		wp_reset_postdata();
		return $posts;
	}
}
