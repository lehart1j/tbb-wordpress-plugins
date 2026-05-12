<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Admin_Forms {
	public const PAGE_SLUG = 'tbb-contact-forms';

	public function __construct() {
		add_action('admin_init', [$this, 'maybe_save']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function enqueue_assets(string $hook_suffix): void {
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ($page !== self::PAGE_SLUG) {
			return;
		}

		$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
		if ($action === 'edit') {
			wp_enqueue_script(
				'tbb-contact-form-admin',
				TBB_CONTACT_FORM_URL . 'assets/admin-forms.js',
				[],
				TBB_CONTACT_FORM_VERSION,
				true
			);
		}
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

		$field_names = isset($_POST['field_name']) ? (array) wp_unslash($_POST['field_name']) : [];
		$field_labels = isset($_POST['field_label']) ? (array) wp_unslash($_POST['field_label']) : [];
		$field_types = isset($_POST['field_type']) ? (array) wp_unslash($_POST['field_type']) : [];
		$field_required = isset($_POST['field_required']) ? (array) wp_unslash($_POST['field_required']) : [];
		$field_options = isset($_POST['field_options']) ? (array) wp_unslash($_POST['field_options']) : [];

		$fields = [];
		$count = max(count($field_names), count($field_labels), count($field_types));

		for ($i = 0; $i < $count; $i++) {
			$name = isset($field_names[$i]) ? sanitize_key((string) $field_names[$i]) : '';
			if ($name === '') {
				continue;
			}
			$label = isset($field_labels[$i]) ? sanitize_text_field((string) $field_labels[$i]) : $name;
			$type = isset($field_types[$i]) ? sanitize_text_field((string) $field_types[$i]) : 'text';
			$req = isset($field_required[$i]) && (string) $field_required[$i] === '1';
			$opts = isset($field_options[$i]) ? sanitize_text_field((string) $field_options[$i]) : '';

			$fields[] = [
				'name' => $name,
				'label' => $label !== '' ? $label : $name,
				'type' => $type,
				'required' => $req,
				'options' => $opts,
			];
		}

		if ($title === '') {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'title'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		if (empty($fields)) {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'fields'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		$has_email = false;
		foreach ($fields as $f) {
			if (($f['type'] ?? '') === 'email') {
				$has_email = true;
				break;
			}
		}
		if (!$has_email) {
			$url = add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'edit', 'id' => $id, 'tbb_err' => 'email'], admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}

		$saved_id = tbb_contact_form_save_form([
			'id' => $id,
			'title' => $title,
			'button_label' => $button_label !== '' ? $button_label : __('Contact us now', 'tbb-contact-form'),
			'modal_title' => $modal_title !== '' ? $modal_title : $title,
			'fields_json' => wp_json_encode($fields),
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
				<p><?php echo esc_html__('No forms yet. Create one to get a shortcode for your site.', 'tbb-contact-form'); ?></p>
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
								<a class="button button-small button-link-delete" href="<?php echo esc_url($del); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this form? Submissions stay in Messages.', 'tbb-contact-form')); ?>');"><?php echo esc_html__('Delete', 'tbb-contact-form'); ?></a>
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
			echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a form title.', 'tbb-contact-form') . '</p></div>';
		} elseif ($err === 'fields') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Add at least one field with a valid name (letters, numbers, underscores).', 'tbb-contact-form') . '</p></div>';
		} elseif ($err === 'email') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Include at least one field of type Email so replies can be routed correctly.', 'tbb-contact-form') . '</p></div>';
		}

		$title = $form ? (string) $form['title'] : '';
		$button_label = $form ? (string) $form['button_label'] : __('Contact us now', 'tbb-contact-form');
		$modal_title = $form ? (string) $form['modal_title'] : '';
		$fields = $form ? tbb_contact_form_parse_fields_json((string) $form['fields_json']) : [];

		if (empty($fields)) {
			$fields = [
				['name' => 'your_name', 'label' => __('Your name', 'tbb-contact-form'), 'type' => 'text', 'required' => true, 'options' => ''],
				['name' => 'your_email', 'label' => __('Email', 'tbb-contact-form'), 'type' => 'email', 'required' => true, 'options' => ''],
				['name' => 'message', 'label' => __('Message', 'tbb-contact-form'), 'type' => 'textarea', 'required' => true, 'options' => ''],
			];
		}

		$types = [
			'text' => __('Text', 'tbb-contact-form'),
			'email' => __('Email', 'tbb-contact-form'),
			'textarea' => __('Textarea', 'tbb-contact-form'),
			'tel' => __('Phone', 'tbb-contact-form'),
			'url' => __('URL', 'tbb-contact-form'),
			'number' => __('Number', 'tbb-contact-form'),
			'select' => __('Select', 'tbb-contact-form'),
		];

		?>
		<div class="wrap">
			<h1><?php echo $id > 0 ? esc_html__('Edit form', 'tbb-contact-form') : esc_html__('Add form', 'tbb-contact-form'); ?></h1>

			<?php if ($id > 0) : ?>
				<p><strong><?php echo esc_html__('Shortcode:', 'tbb-contact-form'); ?></strong> <code>[tbb_contact_form id="<?php echo esc_attr((string) $id); ?>"]</code></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="tbb-cf-form-builder">
				<?php wp_nonce_field('tbb_cf_save_form'); ?>
				<input type="hidden" name="tbb_cf_page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
				<input type="hidden" name="tbb_cf_form_save" value="1">
				<input type="hidden" name="form_id" value="<?php echo esc_attr((string) $id); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tbb_cf_title"><?php echo esc_html__('Form title', 'tbb-contact-form'); ?></label></th>
						<td><input name="title" id="tbb_cf_title" type="text" class="regular-text" value="<?php echo esc_attr($title); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_cf_button"><?php echo esc_html__('Button label', 'tbb-contact-form'); ?></label></th>
						<td><input name="button_label" id="tbb_cf_button" type="text" class="regular-text" value="<?php echo esc_attr($button_label); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tbb_cf_modal"><?php echo esc_html__('Popup title', 'tbb-contact-form'); ?></label></th>
						<td><input name="modal_title" id="tbb_cf_modal" type="text" class="regular-text" value="<?php echo esc_attr($modal_title); ?>" placeholder="<?php echo esc_attr__('Same as form title if empty', 'tbb-contact-form'); ?>"></td>
					</tr>
				</table>

				<h2><?php echo esc_html__('Fields', 'tbb-contact-form'); ?></h2>
				<p class="description"><?php echo esc_html__('Field name is used internally (e.g. your_email). Use one Email field so notifications include a reply address.', 'tbb-contact-form'); ?></p>

				<table class="widefat striped" id="tbb-cf-fields-table">
					<thead>
					<tr>
						<th><?php echo esc_html__('Label', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Field name', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Type', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Required', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Select options', 'tbb-contact-form'); ?></th>
						<th></th>
					</tr>
					</thead>
					<tbody id="tbb-cf-fields-body">
					<?php foreach ($fields as $row) : ?>
						<tr class="tbb-cf-field-row">
							<td><input type="text" name="field_label[]" class="regular-text" value="<?php echo esc_attr((string) $row['label']); ?>"></td>
							<td><input type="text" name="field_name[]" class="regular-text" value="<?php echo esc_attr((string) $row['name']); ?>" pattern="[a-z0-9_]+" title="<?php echo esc_attr__('Lowercase letters, numbers, underscore', 'tbb-contact-form'); ?>"></td>
							<td>
								<select name="field_type[]">
									<?php foreach ($types as $val => $lab) : ?>
										<option value="<?php echo esc_attr($val); ?>" <?php selected($row['type'], $val); ?>><?php echo esc_html($lab); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<?php $req_val = !empty($row['required']) ? '1' : '0'; ?>
								<select name="field_required[]">
									<option value="0" <?php selected($req_val, '0'); ?>><?php echo esc_html__('No', 'tbb-contact-form'); ?></option>
									<option value="1" <?php selected($req_val, '1'); ?>><?php echo esc_html__('Yes', 'tbb-contact-form'); ?></option>
								</select>
							</td>
							<td><input type="text" name="field_options[]" class="regular-text" value="<?php echo esc_attr((string) ($row['options'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Option A | Option B', 'tbb-contact-form'); ?>"></td>
							<td><button type="button" class="button tbb-cf-remove-row"><?php echo esc_html__('Remove', 'tbb-contact-form'); ?></button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="tbb-cf-add-field"><?php echo esc_html__('Add field', 'tbb-contact-form'); ?></button>
				</p>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save form', 'tbb-contact-form'); ?>">
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>"><?php echo esc_html__('Cancel', 'tbb-contact-form'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}
}
