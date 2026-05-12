<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Contact_Form_Admin_Messages {
	public const PAGE_SLUG = 'tbb-contact-messages';

	public function handle_routing(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ($screen !== self::PAGE_SLUG) {
			return;
		}

		$action = isset($_GET['tbb_action']) ? sanitize_text_field(wp_unslash($_GET['tbb_action'])) : '';

		if ($action === 'delete') {
			$this->handle_delete();
		}
	}

	public function render(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'tbb-contact-form'));
		}

		$action = isset($_GET['tbb_action']) ? sanitize_text_field(wp_unslash($_GET['tbb_action'])) : '';
		$view_id = isset($_GET['message_id']) ? absint($_GET['message_id']) : 0;

		if ($action === 'view' && $view_id > 0) {
			$this->render_single($view_id);
			return;
		}

		$this->render_list();
	}

	private function handle_delete(): void {
		$id = isset($_GET['message_id']) ? absint($_GET['message_id']) : 0;
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

		if ($id <= 0 || !wp_verify_nonce($nonce, 'tbb_cf_delete_' . $id)) {
			return;
		}

		tbb_contact_form_delete_message($id);

		wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
		exit;
	}

	private function render_list(): void {
		$per_page = 20;
		$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$offset = ($paged - 1) * $per_page;

		$total = tbb_contact_form_count_messages();
		$messages = tbb_contact_form_get_messages($per_page, $offset);
		$total_pages = (int) max(1, (int) ceil($total / $per_page));

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Messages', 'tbb-contact-form'); ?></h1>

			<?php if (empty($messages)) : ?>
				<p><?php echo esc_html__('No messages yet.', 'tbb-contact-form'); ?></p>
			<?php else : ?>
				<table class="widefat fixed striped">
					<thead>
					<tr>
						<th><?php echo esc_html__('Date (UTC)', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Form', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Name', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Email', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Subject / preview', 'tbb-contact-form'); ?></th>
						<th><?php echo esc_html__('Actions', 'tbb-contact-form'); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($messages as $m) : ?>
						<?php
						$id = (int) $m['id'];
						$view_url = add_query_arg(
							[
								'page' => self::PAGE_SLUG,
								'tbb_action' => 'view',
								'message_id' => $id,
							],
							admin_url('admin.php')
						);
						$delete_url = wp_nonce_url(
							add_query_arg(
								[
									'page' => self::PAGE_SLUG,
									'tbb_action' => 'delete',
									'message_id' => $id,
								],
								admin_url('admin.php')
							),
							'tbb_cf_delete_' . $id
						);
						$form_title = (string) ($m['form_title'] ?? '');
						if ($form_title === '' && (int) ($m['form_id'] ?? 0) === 0) {
							$form_title = '—';
						}
						?>
						<tr>
							<td><?php echo esc_html((string) $m['created_at']); ?></td>
							<td><?php echo esc_html($form_title); ?></td>
							<td><?php echo esc_html((string) $m['name']); ?></td>
							<td><a href="mailto:<?php echo esc_attr((string) $m['email']); ?>"><?php echo esc_html((string) $m['email']); ?></a></td>
							<td><?php echo esc_html((string) $m['subject']); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url($view_url); ?>">
									<?php echo esc_html__('View', 'tbb-contact-form'); ?>
								</a>
								<a class="button button-small button-link-delete" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this message?', 'tbb-contact-form')); ?>');">
									<?php echo esc_html__('Delete', 'tbb-contact-form'); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ($total_pages > 1) : ?>
					<div class="tablenav">
						<div class="tablenav-pages">
							<?php
							echo paginate_links([
								'base' => add_query_arg('paged', '%#%'),
								'format' => '',
								'prev_text' => __('&laquo;', 'tbb-contact-form'),
								'next_text' => __('&raquo;', 'tbb-contact-form'),
								'total' => $total_pages,
								'current' => $paged,
							]);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_single(int $id): void {
		$msg = tbb_contact_form_get_message($id);

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Message', 'tbb-contact-form'); ?></h1>

			<p>
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>">
					<?php echo esc_html__('Back to list', 'tbb-contact-form'); ?>
				</a>
			</p>

			<?php if (!$msg) : ?>
				<p><?php echo esc_html__('Message not found.', 'tbb-contact-form'); ?></p>
				<?php return; ?>
			<?php endif; ?>

			<?php
			$form = null;
			if (!empty($msg['form_id'])) {
				$form = tbb_contact_form_get_form((int) $msg['form_id']);
			}
			?>

			<table class="widefat striped">
				<tbody>
					<tr>
						<th><?php echo esc_html__('Date (UTC)', 'tbb-contact-form'); ?></th>
						<td><?php echo esc_html((string) $msg['created_at']); ?></td>
					</tr>
					<?php if ($form) : ?>
						<tr>
							<th><?php echo esc_html__('Form', 'tbb-contact-form'); ?></th>
							<td><?php echo esc_html((string) $form['title']); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th><?php echo esc_html__('Page', 'tbb-contact-form'); ?></th>
						<td>
							<?php if (!empty($msg['page_url'])) : ?>
								<a href="<?php echo esc_url((string) $msg['page_url']); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html((string) $msg['page_url']); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			$fields_data = isset($msg['fields_data']) ? (string) $msg['fields_data'] : '';
			$decoded = $fields_data !== '' ? json_decode($fields_data, true) : null;
			if (is_array($decoded) && !empty($decoded)) :
				?>
				<h2><?php echo esc_html__('Submitted fields', 'tbb-contact-form'); ?></h2>
				<table class="widefat striped">
					<tbody>
					<?php foreach ($decoded as $k => $v) : ?>
						<tr>
							<th style="width:220px;"><?php echo esc_html((string) $k); ?></th>
							<td style="white-space: pre-wrap;"><?php echo esc_html(is_scalar($v) ? (string) $v : wp_json_encode($v)); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<h2><?php echo esc_html__('Legacy fields', 'tbb-contact-form'); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php echo esc_html__('Name', 'tbb-contact-form'); ?></th><td><?php echo esc_html((string) $msg['name']); ?></td></tr>
						<tr><th><?php echo esc_html__('Email', 'tbb-contact-form'); ?></th><td><a href="mailto:<?php echo esc_attr((string) $msg['email']); ?>"><?php echo esc_html((string) $msg['email']); ?></a></td></tr>
						<tr><th><?php echo esc_html__('Subject', 'tbb-contact-form'); ?></th><td><?php echo esc_html((string) $msg['subject']); ?></td></tr>
						<tr><th><?php echo esc_html__('Message', 'tbb-contact-form'); ?></th><td style="white-space: pre-wrap;"><?php echo esc_html((string) $msg['message']); ?></td></tr>
					</tbody>
				</table>
			<?php endif; ?>

			<table class="widefat striped" style="margin-top:1em;">
				<tbody>
					<tr>
						<th><?php echo esc_html__('IP', 'tbb-contact-form'); ?></th>
						<td><?php echo esc_html((string) ($msg['ip_address'] ?? '')); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__('User Agent', 'tbb-contact-form'); ?></th>
						<td style="word-break: break-word;"><?php echo esc_html((string) ($msg['user_agent'] ?? '')); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
