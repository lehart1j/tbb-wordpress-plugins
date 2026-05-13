<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Admin_Forms {
	public const PAGE_SLUG = 'tbb-contact-forms';

	public function __construct() {
		add_action('admin_init', [$this, 'maybe_save']);
	}

	public function maybe_save(): void {
		if (!isset($_POST['tbb_cf_form_save']) || !isset($_POST['_wpnonce'])) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		$page = isset($_POST['tbb_cf_page']) ? sanitize_text_field(wp_unslash($_POST['tbb_cf_page'])) : '';
		if ($page !== self::PAGE_SLUG) {
			return;
		}

		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tbb_cf_save_form')) {
			return;
		}

		$id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
		$title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$button_label = isset($_POST['button_label']) ? sanitize_text_field(wp_unslash($_POST['button_label'])) : '';
		$modal_title = isset($_POST['modal_title']) ? sanitize_text_field(wp_unslash($_POST['modal_title'])) : '';
		$cf7_pasted = isset($_POST['cf7_shortcode']) ? trim(wp_unslash((string) $_POST['cf7_shortcode'])) : '';

		if ($title === '') {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'title'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		if (!tbb_contact_form_cf7_is_active()) {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'cf7_plugin'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		$resolved = tbb_contact_form_cf7_resolve($cf7_pasted);
		if (!$resolved) {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'cf7'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		$cf7_shortcode = tbb_contact_form_cf7_build_shortcode($resolved);
		if ($cf7_shortcode === '') {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'cf7'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		$saved_id = tbb_contact_form_save_form([
			'id' => $id,
			'title' => $title,
			'button_label' => $button_label !== '' ? $button_label : __('Contact us now', 'tbb-contact-form'),
			'modal_title' => $modal_title !== '' ? $modal_title : $title,
			'cf7_shortcode' => $cf7_shortcode,
			'fields_json' => '[]',
		]);

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'updated' => '1', 'action' => 'edit', 'id' => $saved_id], admin_url('admin.php')));
		exit;
	}

	public function handle_delete(): void {
		$screen = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ($screen !== self::PAGE_SLUG) {
			return;
		}

		$action = isset($_GET['tbb_action']) ? sanitize_text_field(wp_unslash($_GET['tbb_action'])) : '';
		if ($action !== 'delete_form') {
			return;
		}

		$id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

		if ($id <= 0 || !wp_verify_nonce($nonce, 'tbb_cf_delete_form_' . $id)) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		tbb_contact_form_delete_form($id);
		wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&deleted=1'));
		exit;
	}

	public function render(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'tbb-contact-form'));
		}

		$this->handle_delete();

		$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

		if ($action === 'edit') {
			$this->render_edit();
			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		$forms = tbb_contact_form_list_forms();

		if (isset($_GET['deleted'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form deleted.', 'tbb-contact-form') . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__('Forms', 'tbb-contact-form'); ?></h1>
			<a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=edit&id=0')); ?>" class="page-title-action">
				<?php echo esc_html__('Add new', 'tbb-contact-form'); ?>
			</a>
			<hr class="wp-header-end">

			<?php if (empty($forms)) : ?>
				<p><?php echo esc_html__('No popups yet. Create one, paste a Contact Form 7 shortcode, then place the TBB shortcode on your site.', 'tbb-contact-form'); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
					<tr>
						<th><?php echo esc_html__('Title', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Button label', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Shortcode', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Updated', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Actions', 'tbb-contact-form'); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($forms as $f) : ?>
						<?php
						$fid = (int) $f['id'];
						$edit = admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=edit&id=' . $fid);
						$del = wp_nonce_url(
							admin_url('admin.php?page=' . self::PAGE_SLUG . '&tbb_action=delete_form&id=' . $fid),
							'tbb_cf_delete_form_' . $fid
						);
						$shortcode = '[tbb_contact_form id="' . $fid . '"]';
						?>
						<tr>
							<td><strong><?php echo esc_html((string) $f['title']); ?></strong></td>
							<td><?php echo esc_html((string) $f['button_label']); ?></td>
							<td><code><?php echo esc_html($shortcode); ?></code></td>
							<td><?php echo esc_html((string) $f['updated_at']); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url($edit); ?>"><?php echo esc_html__('Edit', 'tbb-contact-form'); ?></a>
								<a class="button button-small button-link-delete" href="<?php echo esc_url($del); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this popup? Submissions stay in Messages.', 'tbb-contact-form')); ?>');"><?php echo esc_html__('Delete', 'tbb-contact-form'); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_edit(): void {
		$id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$form = $id > 0 ? tbb_contact_form_get_form($id) : null;

		if ($id > 0 && !$form) {
			echo '<div class="wrap"><p>' . esc_html__('Form not found.', 'tbb-contact-form') . '</p></div>';
			return;
		}

		if (isset($_GET['updated'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form saved.', 'tbb-contact-form') . '</p></div>';
		}

		$err = isset($_GET['tbb_err']) ? sanitize_text_field(wp_unslash($_GET['tbb_err'])) : '';
		if ($err === 'title') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a title.', 'tbb-contact-form') . '</p></div>';
		} elseif ($err === 'cf7') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Paste a valid Contact Form 7 shortcode (e.g. from Contact → Contact Forms → copy). The form must exist.', 'tbb-contact-form') . '</p></div>';
		} elseif ($err === 'cf7_plugin') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Install and activate the Contact Form 7 plugin before saving.', 'tbb-contact-form') . '</p></div>';
		}

		$title = $form ? (string) $form['title'] : '';
		$button_label = $form ? (string) $form['button_label'] : __('Contact us now', 'tbb-contact-form');
		$modal_title = $form ? (string) $form['modal_title'] : '';
		$cf7_shortcode = $form && isset($form['cf7_shortcode']) ? (string) $form['cf7_shortcode'] : '';

		?>
		<div class="wrap">
			<h1><?php echo $id > 0 ? esc_html__('Edit popup', 'tbb-contact-form') : esc_html__('Add popup', 'tbb-contact-form'); ?></h1>

			<?php if ($id > 0) : ?>
				<p><strong><?php echo esc_html__('Shortcode:', 'tbb-contact-form'); ?></strong> <code>[tbb_contact_form id="<?php echo esc_attr((string) $id); ?>"]</code></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
				<?php wp_nonce_field('tbb_cf_save_form'); ?>
				<input type="hidden" name="tbb_cf_page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
				<input type="hidden" name="tbb_cf_form_save" value="1">
				<input type="hidden" name="form_id" value="<?php echo esc_attr((string) $id); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tbb_cf_title"><?php echo esc_html__('Title', 'tbb-contact-form'); ?></label></th>
						<td><input name="title" id="tbb_cf_title" type="text" class="regular-text" value="<?php echo esc_attr($title); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_cf_button"><?php echo esc_html__('Button label', 'tbb-contact-form'); ?></label></th>
						<td><input name="button_label" id="tbb_cf_button" type="text" class="regular-text" value="<?php echo esc_attr($button_label); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_cf_modal"><?php echo esc_html__('Popup title', 'tbb-contact-form'); ?></label></th>
						<td><input name="modal_title" id="tbb_cf_modal" type="text" class="regular-text" value="<?php echo esc_attr($modal_title); ?>" placeholder="<?php echo esc_attr__('Same as title if empty', 'tbb-contact-form'); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_cf_cf7"><?php echo esc_html__('Contact Form 7 shortcode', 'tbb-contact-form'); ?></label></th>
						<td>
							<textarea name="cf7_shortcode" id="tbb_cf_cf7" class="large-text code" rows="3" placeholder='[contact-form-7 id="123" title="Contact form 1"]'><?php echo esc_textarea($cf7_shortcode); ?></textarea>
							<p class="description">
								<?php echo esc_html__('Copy the shortcode from Contact → Contact Forms. Submissions and mail are handled by Contact Form 7, not this plugin.', 'tbb-contact-form'); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save', 'tbb-contact-form'); ?>">
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>"><?php echo esc_html__('Cancel', 'tbb-contact-form'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}
}
