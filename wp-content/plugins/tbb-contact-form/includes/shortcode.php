<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Shortcode {
	public function __construct() {
		add_shortcode('tbb_contact_button', [$this, 'render_button']);
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

	public function render_button($atts): string {
		$atts = shortcode_atts(
			[
				'label' => __('Contact us', 'tbb-contact-form'),
				'button_class' => '',
			],
			is_array($atts) ? $atts : []
		);

		wp_enqueue_style('tbb-contact-form');
		wp_enqueue_script('tbb-contact-form');

		wp_localize_script('tbb-contact-form', 'TBBContactForm', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('tbb_contact_form'),
		]);

		$button_class = trim('tbb-cf-button ' . (string) $atts['button_class']);

		ob_start();
		?>
		<button type="button" class="<?php echo esc_attr($button_class); ?>" data-tbb-cf-open>
			<?php echo esc_html((string) $atts['label']); ?>
		</button>

		<div class="tbb-cf-modal" data-tbb-cf-modal aria-hidden="true">
			<div class="tbb-cf-backdrop" data-tbb-cf-close tabindex="-1"></div>
			<div class="tbb-cf-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Contact form', 'tbb-contact-form'); ?>">
				<button type="button" class="tbb-cf-close" data-tbb-cf-close aria-label="<?php echo esc_attr__('Close', 'tbb-contact-form'); ?>">
					&times;
				</button>

				<div class="tbb-cf-header">
					<h3 class="tbb-cf-title"><?php echo esc_html__('Send a message', 'tbb-contact-form'); ?></h3>
				</div>

				<form class="tbb-cf-form" data-tbb-cf-form>
					<div class="tbb-cf-field">
						<label>
							<span><?php echo esc_html__('Name', 'tbb-contact-form'); ?></span>
							<input name="name" type="text" autocomplete="name" required>
						</label>
					</div>

					<div class="tbb-cf-field">
						<label>
							<span><?php echo esc_html__('Email', 'tbb-contact-form'); ?></span>
							<input name="email" type="email" autocomplete="email" required>
						</label>
					</div>

					<div class="tbb-cf-field">
						<label>
							<span><?php echo esc_html__('Subject', 'tbb-contact-form'); ?></span>
							<input name="subject" type="text" autocomplete="off">
						</label>
					</div>

					<div class="tbb-cf-field">
						<label>
							<span><?php echo esc_html__('Message', 'tbb-contact-form'); ?></span>
							<textarea name="message" rows="5" required></textarea>
						</label>
					</div>

					<input type="hidden" name="page_url" value="<?php echo esc_attr((string) wp_get_referer()); ?>">
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
		</div>
		<?php

		return (string) ob_get_clean();
	}
}

