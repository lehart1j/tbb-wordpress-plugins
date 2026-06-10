<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Vault_Admin {
	public const PAGE_SLUG = 'tbb-vault';

	public static function register(): void {
		add_action('admin_menu', [self::class, 'menu']);
		add_action('admin_init', [self::class, 'save_settings']);
		add_action('admin_init', [self::class, 'save_features']);
		add_action('admin_enqueue_scripts', [self::class, 'assets']);
	}

	public static function assets(string $hook): void {
		if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
			return;
		}
		wp_enqueue_style('tbb-vault-admin', TBB_VAULT_URL . 'assets/admin.css', [], TBB_VAULT_VERSION);
	}

	public static function menu(): void {
		add_menu_page(
			__('TBB Vault', 'tbb-vault'),
			__('TBB Vault', 'tbb-vault'),
			'manage_options',
			self::PAGE_SLUG,
			[self::class, 'render_tree'],
			'dashicons-portfolio',
			27
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__('Settings', 'tbb-vault'),
			__('Settings', 'tbb-vault'),
			'manage_options',
			self::PAGE_SLUG . '-settings',
			[self::class, 'render_settings']
		);
	}

	/**
	 * @return array{welcome_page_id: int}
	 */
	public static function options(): array {
		$o = get_option('tbb_vault_options', []);
		if (!is_array($o)) {
			$o = [];
		}
		return [
			'welcome_page_id' => isset($o['welcome_page_id']) ? absint($o['welcome_page_id']) : 0,
		];
	}

	public static function save_settings(): void {
		if (!isset($_POST['tbb_vault_settings_save'], $_POST['_wpnonce'])) {
			return;
		}
		if (!current_user_can('manage_options')) {
			return;
		}
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tbb_vault_settings')) {
			return;
		}
		$welcome = isset($_POST['welcome_page_id']) ? absint(wp_unslash($_POST['welcome_page_id'])) : 0;
		update_option('tbb_vault_options', ['welcome_page_id' => $welcome], false);
		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG . '-settings', 'updated' => '1'], admin_url('admin.php')));
		exit;
	}

	public static function save_features(): void {
		if (!isset($_POST['tbb_vault_features_save'], $_POST['_wpnonce'])) {
			return;
		}
		$page_id = isset($_POST['tbb_vault_page_id']) ? absint(wp_unslash($_POST['tbb_vault_page_id'])) : 0;
		if ($page_id <= 0 || !current_user_can('edit_post', $page_id)) {
			return;
		}
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tbb_vault_features_' . $page_id)) {
			return;
		}

		$defs = TBB_Vault_Features::definitions();
		$flags = [];
		foreach (array_keys($defs) as $slug) {
			$flags[$slug] = isset($_POST['tbb_feat'][$slug]) && (string) wp_unslash($_POST['tbb_feat'][$slug]) === '1';
		}
		TBB_Vault_Features::save($page_id, $flags);

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'tbb_vault_page' => (string) $page_id, 'saved' => '1'], admin_url('admin.php')));
		exit;
	}

	public static function render_settings(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission.', 'tbb-vault'));
		}
		$opts = self::options();
		if (isset($_GET['updated'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'tbb-vault') . '</p></div>';
		}
		$pages = get_pages(['sort_column' => 'post_title', 'hierarchical' => true]);
		?>
		<div class="wrap">
			<h1><?php esc_html_e('TBB Vault — Settings', 'tbb-vault'); ?></h1>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '-settings')); ?>">
				<?php wp_nonce_field('tbb_vault_settings'); ?>
				<input type="hidden" name="tbb_vault_settings_save" value="1">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="welcome_page_id"><?php esc_html_e('Welcome (root) page', 'tbb-vault'); ?></label></th>
						<td>
							<select name="welcome_page_id" id="welcome_page_id" class="regular-text">
								<option value="0"><?php esc_html_e('— Select —', 'tbb-vault'); ?></option>
								<?php foreach ($pages as $p) : ?>
									<option value="<?php echo esc_attr((string) $p->ID); ?>" <?php selected($opts['welcome_page_id'], $p->ID); ?>>
										<?php echo esc_html($p->post_title); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('The vault tree in TBB Vault is built from this page and all of its descendants.', 'tbb-vault'); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save', 'tbb-vault')); ?>
			</form>
		</div>
		<?php
	}

	public static function render_tree(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission.', 'tbb-vault'));
		}

		$opts = self::options();
		$focus_id = isset($_GET['tbb_vault_page']) ? absint(wp_unslash($_GET['tbb_vault_page'])) : 0;

		if ($opts['welcome_page_id'] <= 0) {
			echo '<div class="wrap"><h1>' . esc_html__('TBB Vault', 'tbb-vault') . '</h1>';
			echo '<p>' . esc_html__('Choose the welcome page under TBB Vault → Settings.', 'tbb-vault') . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '-settings')) . '">' . esc_html__('Open settings', 'tbb-vault') . '</a></p></div>';
			return;
		}

		echo '<div class="wrap tbb-vault-wrap"><h1>' . esc_html__('TBB Vault', 'tbb-vault') . '</h1>';

		if (isset($_GET['saved'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Features saved.', 'tbb-vault') . '</p></div>';
		}

		if ($focus_id > 0 && current_user_can('edit_post', $focus_id)) {
			self::render_features_form($focus_id, false);
			echo '<hr class="tbb-vault-divider"><h2>' . esc_html__('Vault tree', 'tbb-vault') . '</h2>';
		} else {
			echo '<p class="description">' . esc_html__('Click a page in the tree to configure vault features, or use the block editor for content.', 'tbb-vault') . '</p>';
		}

		$welcome = get_post($opts['welcome_page_id']);
		if (!$welcome instanceof WP_Post || $welcome->post_type !== 'page') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Welcome page not found. Update settings.', 'tbb-vault') . '</p></div></div>';
			return;
		}

		self::walk_tree($welcome, 0);
		echo '</div>';
	}

	private static function walk_tree(WP_Post $page, int $depth): void {
		$tpl = get_page_template_slug($page->ID);
		$tpl_label = self::template_label($tpl);
		$feat_url = add_query_arg(['page' => self::PAGE_SLUG, 'tbb_vault_page' => (string) $page->ID], admin_url('admin.php'));
		$edit_url = get_edit_post_link($page->ID);

		echo '<div class="tbb-vault-node" style="margin-left:' . esc_attr((string) ($depth * 20)) . 'px">';
		echo '<div class="tbb-vault-node__row">';
		echo '<strong>' . esc_html(get_the_title($page)) . '</strong> ';
		echo '<span class="tbb-vault-badge">' . esc_html($tpl_label) . '</span> ';
		echo '<a href="' . esc_url($feat_url) . '">' . esc_html__('Features', 'tbb-vault') . '</a>';
		if ($edit_url) {
			echo ' · <a href="' . esc_url($edit_url) . '">' . esc_html__('Edit page', 'tbb-vault') . '</a>';
		}
		echo '</div>';

		$children = get_pages([
			'parent' => $page->ID,
			'sort_column' => 'menu_order, post_title',
			'hierarchical' => false,
		]);
		foreach ($children as $child) {
			self::walk_tree($child, $depth + 1);
		}
		echo '</div>';
	}

	private static function template_label(string $slug): string {
		$labels = [
			TBB_Vault_Templates::WELCOME => __('Welcome', 'tbb-vault'),
			TBB_Vault_Templates::SECTION => __('Section', 'tbb-vault'),
			TBB_Vault_Templates::INNER => __('Inner', 'tbb-vault'),
		];
		return $labels[$slug] ?? __('Default / other', 'tbb-vault');
	}

	private static function render_features_form(int $page_id, bool $standalone = true): void {
		$post = get_post($page_id);
		if (!$post instanceof WP_Post) {
			return;
		}
		$tpl = get_page_template_slug($page_id);
		if ($tpl === '') {
			$tpl = TBB_Vault_Templates::INNER;
		}
		$features = TBB_Vault_Features::get_for_post($page_id);
		$defs = TBB_Vault_Features::definitions();

		if ($standalone) {
			echo '<div class="wrap tbb-vault-features"><h1>' . esc_html(sprintf(
				/* translators: %s page title */
				__('Features: %s', 'tbb-vault'),
				get_the_title($post)
			)) . '</h1>';
		} else {
			echo '<h2>' . esc_html(sprintf(
				/* translators: %s page title */
				__('Features: %s', 'tbb-vault'),
				get_the_title($post)
			)) . '</h2>';
		}

		$edit = get_edit_post_link($page_id);
		if ($edit) {
			echo '<p><a class="button" href="' . esc_url($edit) . '">' . esc_html__('Open in block editor (quick content fixes)', 'tbb-vault') . '</a></p>';
		}

		echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">';
		wp_nonce_field('tbb_vault_features_' . $page_id);
		echo '<input type="hidden" name="tbb_vault_features_save" value="1">';
		echo '<input type="hidden" name="tbb_vault_page_id" value="' . esc_attr((string) $page_id) . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ($defs as $slug => $label) {
			$checked = !empty($features[$slug]);
			echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
			echo '<label><input type="checkbox" name="tbb_feat[' . esc_attr($slug) . ']" value="1" ' . checked($checked, true, false) . '> ';
			echo '<code>' . esc_html($slug) . '</code></label>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button(__('Save features', 'tbb-vault'));
		echo '</form>';
		if ($standalone) {
			echo '</div>';
		}
	}
}
