<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Shortcode {
	public function __construct() {
		add_shortcode('tbb_contact_form', [$this, 'render_form']);
		add_shortcode('tbb_contact_button', [$this, 'render_legacy_button']);
		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
	}

	public function register_assets(): void {
		wp_register_style(
			'tbb-contact-form',
			TBB_CONTACT_FORM_URL . 'assets/contact-form.css',
			[],
			TBB_CONTACT_FORM_VERSION
		);
		wp_register_script(
			'tbb-contact-form',
			TBB_CONTACT_FORM_URL . 'assets/contact-form.js',
			[],
			TBB_CONTACT_FORM_VERSION,
			true
		);
	}

	/**
	 * Legacy shortcode: prefer [tbb_contact_form id="…"].
	 */
	public function render_legacy_button($atts): string {
		if (current_user_can('manage_options')) {
			return '<p class="tbb-cf-admin-note"><em>' . esc_html__('Use [tbb_contact_form id="FORM_ID"] — create a form under TBB Contact → Forms.', 'tbb-contact-form') . '</em></p>';
		}
		return '';
	}

	public function render_form($atts): string {
		$atts = shortcode_atts(
			[
				'id' => 0,
				'button_label' => '',
			],
			is_array($atts) ? $atts : [],
			'tbb_contact_form'
		);

		$form_id = absint($atts['id']);
		if ($form_id <= 0) {
			if (current_user_can('manage_options')) {
				return '<p class="tbb-cf-admin-note"><em>' . esc_html__('Contact form shortcode needs id="…" (the number from TBB Contact → Forms).', 'tbb-contact-form') . '</em></p>';
			}
			return '';
		}

		$form = tbb_contact_form_get_form($form_id);
		if (!$form) {
			if (current_user_can('manage_options')) {
				return '<p class="tbb-cf-admin-note"><em>' . esc_html__('Contact form not found. Check the id in the shortcode.', 'tbb-contact-form') . '</em></p>';
			}
			return '';
		}

		$fields = tbb_contact_form_parse_fields_json((string) $form['fields_json']);
		if (empty($fields)) {
			return '';
		}

		wp_enqueue_style('tbb-contact-form');
		wp_enqueue_script('tbb-contact-form');

		wp_localize_script('tbb-contact-form', 'TBBContactForm', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('tbb_contact_form'),
		]);

		$button_text = (string) $atts['button_label'] !== ''
			? (string) $atts['button_label']
			: (string) $form['button_label'];

		$modal_title = (string) $form['modal_title'] !== ''
			? (string) $form['modal_title']
			: (string) $form['title'];

		$button_class = trim('tbb-cf-button ');

		ob_start();
		?>
		<div class="tbb-cf-embed" data-tbb-cf-embed>
			<button type="button" class="<?php echo esc_attr($button_class); ?>" data-tbb-cf-open>
				<?php echo esc_html($button_text); ?>
			</button>

			<div class="tbb-cf-modal" data-tbb-cf-modal aria-hidden="true">
				<div class="tbb-cf-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($modal_title); ?>">
					<button type="button" class="tbb-cf-close" data-tbb-cf-close aria-label="<?php echo esc_attr__('Close', 'tbb-contact-form'); ?>">
						&times;
					</button>

					<div class="tbb-cf-header">
						<h3 class="tbb-cf-title"><?php echo esc_html($modal_title); ?></h3>
					</div>

					<form class="tbb-cf-form" data-tbb-cf-form>
						<?php foreach ($fields as $field) : ?>
							<?php
							$name = (string) $field['name'];
							$label = (string) $field['label'];
							$type = (string) $field['type'];
							$req = !empty($field['required']);
							$options = (string) ($field['options'] ?? '');
							?>
							<div class="tbb-cf-field">
								<label>
									<span><?php echo esc_html($label); ?><?php echo $req ? ' <span class="tbb-cf-req">*</span>' : ''; ?></span>
									<?php if ($type === 'textarea') : ?>
										<textarea name="<?php echo esc_attr($name); ?>" rows="5" <?php echo $req ? 'required' : ''; ?>></textarea>
									<?php elseif ($type === 'select') : ?>
										<?php
										$parts = array_map('trim', explode('|', $options));
										$parts = array_filter($parts);
										?>
										<select name="<?php echo esc_attr($name); ?>" <?php echo $req ? 'required' : ''; ?>>
											<?php if (!$req) : ?>
												<option value=""><?php echo esc_html__('— Select —', 'tbb-contact-form'); ?></option>
											<?php endif; ?>
											<?php foreach ($parts as $opt) : ?>
												<option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input
											name="<?php echo esc_attr($name); ?>"
											type="<?php echo esc_attr($type === 'number' ? 'number' : $type); ?>"
											<?php echo $req ? 'required' : ''; ?>
											<?php echo $type === 'email' ? 'autocomplete="email"' : ''; ?>
											<?php echo $type === 'text' && ($name === 'name' || strpos($name, 'name') !== false) ? 'autocomplete="name"' : ''; ?>
										>
									<?php endif; ?>
								</label>
							</div>
						<?php endforeach; ?>

						<input type="hidden" name="page_url" value="" data-tbb-cf-page-url>
						<input type="hidden" name="form_id" value="<?php echo esc_attr((string) $form_id); ?>">
						<input type="hidden" name="action" value="tbb_contact_submit">
						<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('tbb_contact_form')); ?>">

						<div class="tbb-cf-actions">
							<button type="submit" class="tbb-cf-submit">
								<?php echo esc_html__('Send', 'tbb-contact-form'); ?>
							</button>
							<div class="tbb-cf-status" aria-live="polite" data-tbb-cf-status></div>
						</div>
					</form>
				</div>
				<div class="tbb-cf-backdrop" data-tbb-cf-close tabindex="-1"></div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
