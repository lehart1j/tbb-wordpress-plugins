<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Vault_Templates {
	public const WELCOME = 'tbb-vault/welcome.php';
	public const SECTION = 'tbb-vault/section.php';
	public const INNER = 'tbb-vault/inner.php';

	public static function register(): void {
		add_filter('theme_page_templates', [self::class, 'register_list'], 20, 4);
		add_filter('template_include', [self::class, 'load_plugin_template'], 99);
	}

	/**
	 * @param array<string, string> $post_templates
	 * @return array<string, string>
	 */
	public static function register_list($post_templates, $wp_theme, $post, $post_type): array {
		if ($post_type !== 'page') {
			return $post_templates;
		}
		$post_templates[self::WELCOME] = __('TBB Vault — Welcome', 'tbb-vault');
		$post_templates[self::SECTION] = __('TBB Vault — Section hub', 'tbb-vault');
		$post_templates[self::INNER] = __('TBB Vault — Inner content', 'tbb-vault');
		return $post_templates;
	}

	public static function load_plugin_template(string $template): string {
		if (!is_page()) {
			return $template;
		}
		$slug = (string) get_page_template_slug();
		$map = [
			self::WELCOME => 'page-welcome.php',
			self::SECTION => 'page-section.php',
			self::INNER => 'page-inner.php',
		];
		if (!isset($map[$slug])) {
			return $template;
		}
		$file = TBB_VAULT_PATH . 'templates/' . $map[$slug];
		if (is_readable($file)) {
			return $file;
		}
		return $template;
	}

	public static function is_vault_template(?string $slug): bool {
		return in_array($slug, [self::WELCOME, self::SECTION, self::INNER], true);
	}
}
