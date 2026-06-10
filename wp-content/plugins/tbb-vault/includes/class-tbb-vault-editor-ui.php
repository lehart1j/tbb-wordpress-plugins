<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Vault_Editor_UI {
	public static function register(): void {
		add_action('add_meta_boxes', [self::class, 'meta_boxes']);
	}

	public static function meta_boxes(): void {
		global $post;
		if (!$post instanceof WP_Post || $post->post_type !== 'page') {
			return;
		}
		if (!TBB_Vault_Templates::is_vault_template(get_page_template_slug($post->ID))) {
			return;
		}
		if (!current_user_can('edit_post', $post->ID)) {
			return;
		}

		add_meta_box(
			'tbb_vault_quick',
			__('TBB Vault', 'tbb-vault'),
			[self::class, 'render_meta_box'],
			'page',
			'side',
			'high'
		);
	}

	public static function render_meta_box(WP_Post $post): void {
		$url = add_query_arg(
			[
				'page' => TBB_Vault_Admin::PAGE_SLUG,
				'tbb_vault_page' => (string) $post->ID,
			],
			admin_url('admin.php')
		);
		$edit_url = get_edit_post_link($post->ID, 'raw');
		?>
		<p><?php esc_html_e('This page uses a TBB Vault template.', 'tbb-vault'); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url($url); ?>">
				<?php esc_html_e('Configure vault features', 'tbb-vault'); ?>
			</a>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url($edit_url); ?>">
				<?php esc_html_e('Open block editor', 'tbb-vault'); ?>
			</a>
		</p>
		<?php
	}
}
