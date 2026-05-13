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
			return '<p class="tbb-cf-admin-note"><em>' . esc_html__('Use [tbb_contact_form id="FORM_ID"] — create a popup under TBB Contact → Forms.', 'tbb-contact-form') . '</em></p>';
		}
		return '';
	}

	public function render_form($atts): string {
		$atts = shortcode_atts(
			[
				'id' => 0,
				'button_label' => '',
				'layout' => '',
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

		$cf7_shortcode = isset($form['cf7_shortcode']) ? trim((string) $form['cf7_shortcode']) : '';
		if ($cf7_shortcode === '') {
			if (current_user_can('manage_options')) {
				return '<p class="tbb-cf-admin-note"><em>' . esc_html__('This popup has no Contact Form 7 shortcode yet. Edit the form under TBB Contact → Forms and paste the CF7 shortcode.', 'tbb-contact-form') . '</em></p>';
			}
			return '';
		}

		if (!tbb_contact_form_cf7_is_active()) {
			if (current_user_can('manage_options')) {
				return '<p class="tbb-cf-admin-note"><em>' . esc_html__('Install and activate Contact Form 7 so the embedded form can load.', 'tbb-contact-form') . '</em></p>';
			}
			return '';
		}

		tbb_contact_form_cf7_enqueue_assets();

		wp_enqueue_style('tbb-contact-form');
		wp_enqueue_script('tbb-contact-form');

		$button_text = (string) $atts['button_label'] !== ''
			? (string) $atts['button_label']
			: (string) $form['button_label'];

		$modal_title = (string) $form['modal_title'] !== ''
			? (string) $form['modal_title']
			: (string) $form['title'];

		$button_class = trim('tbb-cf-button ');

		$layout_raw = isset($atts['layout']) ? strtolower(trim((string) $atts['layout'])) : '';
		$portal_layout = ($layout_raw === 'portal');
		$embed_class = 'tbb-cf-embed' . ($portal_layout ? ' tbb-cf-embed--portal-layout' : '');

		$heading_id = 'tbb-cf-heading-' . $form_id;

		ob_start();
		?>
		<div class="<?php echo esc_attr($embed_class); ?>" data-tbb-cf-embed>
			<button type="button" class="<?php echo esc_attr($button_class); ?>" data-tbb-cf-open>
				<?php echo esc_html($button_text); ?>
			</button>

			<div class="tbb-cf-modal" data-tbb-cf-modal aria-hidden="true">
				<div class="tbb-cf-backdrop" data-tbb-cf-close tabindex="-1" aria-hidden="true"></div>
				<div class="tbb-cf-dialog">
					<div class="tbb-cf-panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
						<button type="button" class="tbb-cf-close" data-tbb-cf-close aria-label="<?php echo esc_attr__('Close', 'tbb-contact-form'); ?>">
							&times;
						</button>

						<div class="tbb-cf-header">
							<h3 id="<?php echo esc_attr($heading_id); ?>" class="tbb-cf-title"><?php echo esc_html($modal_title); ?></h3>
						</div>

						<div class="tbb-cf-cf7" data-tbb-cf-cf7>
							<?php
							// Stored only from wp-admin after validation; output is CF7 HTML.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode($cf7_shortcode);
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
