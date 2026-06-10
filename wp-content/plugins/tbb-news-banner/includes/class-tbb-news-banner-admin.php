<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_News_Banner_Admin {
	public const PAGE_SLUG = 'tbb-news-banner';

	public static function register(): void {
		add_action('admin_menu', [self::class, 'menu']);
		add_action('admin_enqueue_scripts', [self::class, 'assets']);
	}

	public static function menu(): void {
		add_menu_page(
			__('News Banner', 'tbb-news-banner'),
			__('News Banner', 'tbb-news-banner'),
			'manage_options',
			self::PAGE_SLUG,
			[self::class, 'render'],
			'dashicons-megaphone',
			28
		);
	}

	public static function assets(string $hook): void {
		if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
			return;
		}
		wp_enqueue_style('tbb-news-banner-admin', TBB_NEWS_BANNER_URL . 'assets/admin.css', [], TBB_NEWS_BANNER_VERSION);
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'tbb-news-banner'));
		}

		$settings = TBB_News_Banner_Settings::get();
		$types = TBB_News_Banner_Settings::discover_post_types();
		$enabled_types = $settings['post_types'];
		$recent = TBB_News_Banner_Content::recent_posts_for_admin($enabled_types, 100);
		$preview = TBB_News_Banner_Content::get_items();

		?>
		<div class="wrap tbb-nb-admin">
			<h1><?php esc_html_e('TBB News Banner', 'tbb-news-banner'); ?></h1>
			<p class="description">
				<?php esc_html_e('Shows three latest items above the site header. Filter which content types feed the pool, or pick three items manually to override automatic selection.', 'tbb-news-banner'); ?>
			</p>

			<?php if (isset($_GET['settings-updated'])) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'tbb-news-banner'); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php" id="tbb-nb-settings-form">
				<?php settings_fields('tbb_news_banner'); ?>

				<h2><?php esc_html_e('General', 'tbb-news-banner'); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Banner', 'tbb-news-banner'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
								<?php esc_html_e('Enable news banner on the front end', 'tbb-news-banner'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_nb_label"><?php esc_html_e('Prefix label', 'tbb-news-banner'); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="tbb_nb_label" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[label]" value="<?php echo esc_attr((string) $settings['label']); ?>">
							<p class="description"><?php esc_html_e('Shown before the rotating items (e.g. “Latest”).', 'tbb-news-banner'); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e('Content pool (automatic)', 'tbb-news-banner'); ?></h2>
				<p class="description"><?php esc_html_e('When manual override is off, the three most recently published items from the selected types are shown.', 'tbb-news-banner'); ?></p>
				<fieldset class="tbb-nb-types">
					<?php foreach ($types as $slug => $label) : ?>
						<label class="tbb-nb-types__item">
							<input type="checkbox" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[post_types][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $enabled_types, true)); ?>>
							<?php echo esc_html($label); ?> <code><?php echo esc_html($slug); ?></code>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<h2><?php esc_html_e('Manual override', 'tbb-news-banner'); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Override', 'tbb-news-banner'); ?></th>
						<td>
							<label>
								<input type="checkbox" id="tbb_nb_use_manual" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[use_manual]" value="1" <?php checked(!empty($settings['use_manual'])); ?>>
								<?php esc_html_e('Use manual selection (three chosen items replace automatic picks; leave a slot on “Auto” to fill from the pool)', 'tbb-news-banner'); ?>
							</label>
						</td>
					</tr>
				</table>

				<div class="tbb-nb-manual" id="tbb-nb-manual-slots">
					<?php for ($i = 0; $i < TBB_News_Banner_Settings::SLOT_COUNT; $i++) :
						$slot = $settings['manual_items'][$i] ?? ['post_id' => 0, 'post_type' => ''];
						$selected_key = ((int) $slot['post_id'] > 0 && $slot['post_type'] !== '')
							? $slot['post_type'] . ':' . $slot['post_id']
							: '';
						?>
						<div class="tbb-nb-slot">
							<label for="tbb_nb_manual_<?php echo esc_attr((string) $i); ?>">
								<?php
								echo esc_html(sprintf(
									/* translators: %d slot number 1–3 */
									__('Slot %d', 'tbb-news-banner'),
									$i + 1
								));
								?>
							</label>
							<select name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[manual_items][<?php echo esc_attr((string) $i); ?>][picker]" id="tbb_nb_manual_<?php echo esc_attr((string) $i); ?>" class="tbb-nb-picker">
								<option value=""><?php esc_html_e('— Auto —', 'tbb-news-banner'); ?></option>
								<?php foreach ($recent as $post) :
									$key = $post->post_type . ':' . $post->ID;
									$obj = get_post_type_object($post->post_type);
									$type_label = $obj instanceof WP_Post_Type ? $obj->labels->singular_name : $post->post_type;
									?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($selected_key, $key); ?>>
										<?php echo esc_html($type_label . ': ' . get_the_title($post) . ' (' . get_the_date('', $post) . ')'); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[manual_items][<?php echo esc_attr((string) $i); ?>][post_id]" value="<?php echo esc_attr((string) (int) $slot['post_id']); ?>" class="tbb-nb-hidden-id">
							<input type="hidden" name="<?php echo esc_attr(TBB_News_Banner_Settings::OPTION); ?>[manual_items][<?php echo esc_attr((string) $i); ?>][post_type]" value="<?php echo esc_attr((string) $slot['post_type']); ?>" class="tbb-nb-hidden-type">
						</div>
					<?php endfor; ?>
				</div>

				<?php submit_button(__('Save settings', 'tbb-news-banner')); ?>
			</form>

			<hr>
			<h2><?php esc_html_e('Preview (current front-end output)', 'tbb-news-banner'); ?></h2>
			<?php if (empty($preview)) : ?>
				<p><em><?php esc_html_e('No items to display. Enable the banner and ensure published content exists in the selected types.', 'tbb-news-banner'); ?></em></p>
			<?php else : ?>
				<ul class="tbb-nb-preview-list">
					<?php foreach ($preview as $item) : ?>
						<li class="tbb-nb-preview-item">
							<?php if (($item['image'] ?? '') !== '') : ?>
								<img class="tbb-nb-preview-thumb" src="<?php echo esc_url($item['image']); ?>" alt="" width="40" height="40" loading="lazy">
							<?php endif; ?>
							<span class="tbb-nb-preview-copy">
								<span class="tbb-nb-preview-type"><?php echo esc_html($item['type_label']); ?></span>
								<a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['title']); ?></a>
								<?php if (($item['excerpt'] ?? '') !== '') : ?>
									<span class="tbb-nb-preview-excerpt"><?php echo esc_html($item['excerpt']); ?></span>
								<?php endif; ?>
								<span class="tbb-nb-preview-date"><?php echo esc_html($item['date']); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			const slots = document.querySelectorAll('.tbb-nb-slot');
			slots.forEach(function (slot) {
				const picker = slot.querySelector('.tbb-nb-picker');
				const idField = slot.querySelector('.tbb-nb-hidden-id');
				const typeField = slot.querySelector('.tbb-nb-hidden-type');
				if (!picker || !idField || !typeField) return;
				function sync() {
					const v = picker.value || '';
					if (!v || v.indexOf(':') === -1) {
						idField.value = '0';
						typeField.value = '';
						return;
					}
					const parts = v.split(':');
					const id = parts.pop();
					const type = parts.join(':');
					idField.value = id;
					typeField.value = type;
				}
				picker.addEventListener('change', sync);
				sync();
			});
			const form = document.getElementById('tbb-nb-settings-form');
			if (form) {
				form.addEventListener('submit', function () {
					slots.forEach(function (slot) {
						const picker = slot.querySelector('.tbb-nb-picker');
						if (picker) picker.dispatchEvent(new Event('change'));
					});
				});
			}
		})();
		</script>
		<?php
	}
}
