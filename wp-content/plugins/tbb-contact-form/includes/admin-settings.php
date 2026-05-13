<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Admin_Settings {
	public const PAGE_SLUG = 'tbb-contact-settings';
	public const OPTION_GROUP = 'tbb_cf_settings';
	public const OPTION_EMAILS = 'tbb_cf_notification_emails';

	public function __construct() {
		add_action('admin_init', [$this, 'register']);
	}

	public function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_EMAILS,
			[
				'type' => 'string',
				'sanitize_callback' => [$this, 'sanitize_emails'],
				'default' => '',
			]
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 * @return string Normalized comma-separated list for storage.
	 */
	public function sanitize_emails($value): string {
		if (!is_string($value)) {
			return '';
		}

		$parts = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($parts)) {
			return '';
		}

		$out = [];
		foreach ($parts as $p) {
			$e = sanitize_email(trim((string) $p));
			if ($e !== '' && is_email($e)) {
				$out[] = $e;
			}
		}

		return implode(', ', array_values(array_unique($out)));
	}

	public function render(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'tbb-contact-form'));
		}

		$stored = get_option(self::OPTION_EMAILS, '');
		$display = is_string($stored) ? str_replace(', ', "\n", $stored) : '';
		if ($display === '' && $stored !== '') {
			$display = (string) $stored;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('TBB Contact — Settings', 'tbb-contact-form'); ?></h1>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields(self::OPTION_GROUP); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="tbb_cf_notification_emails"><?php echo esc_html__('Notification email addresses', 'tbb-contact-form'); ?></label>
						</th>
						<td>
							<textarea
								name="<?php echo esc_attr(self::OPTION_EMAILS); ?>"
								id="tbb_cf_notification_emails"
								class="large-text code"
								rows="6"
								placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>"
							><?php echo esc_textarea($display); ?></textarea>
							<p class="description">
								<?php echo esc_html__('Form submissions are sent to these addresses. Enter one per line, or separate with commas. Invalid entries are removed. If empty, the site administration email is used.', 'tbb-contact-form'); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(__('Save settings', 'tbb-contact-form')); ?>
			</form>
		</div>
		<?php
	}
}
