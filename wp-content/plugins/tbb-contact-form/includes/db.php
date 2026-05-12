<?php

if (!defined('ABSPATH')) {
	exit;
}

function tbb_contact_form_table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'tbb_contact_messages';
}

function tbb_contact_form_create_table(): void {
	global $wpdb;

	$table = tbb_contact_form_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(190) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		subject VARCHAR(190) NOT NULL DEFAULT '',
		message LONGTEXT NOT NULL,
		page_url TEXT NULL,
		user_agent TEXT NULL,
		ip_address VARCHAR(64) NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta($sql);
}

function tbb_contact_form_insert_message(array $data): int {
	global $wpdb;

	$table = tbb_contact_form_table_name();

	$inserted = $wpdb->insert(
		$table,
		[
			'name' => (string) ($data['name'] ?? ''),
			'email' => (string) ($data['email'] ?? ''),
			'subject' => (string) ($data['subject'] ?? ''),
			'message' => (string) ($data['message'] ?? ''),
			'page_url' => (string) ($data['page_url'] ?? ''),
			'user_agent' => (string) ($data['user_agent'] ?? ''),
			'ip_address' => (string) ($data['ip_address'] ?? ''),
			'created_at' => gmdate('Y-m-d H:i:s'),
		],
		['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
	);

	if (!$inserted) {
		return 0;
	}

	return (int) $wpdb->insert_id;
}

function tbb_contact_form_get_messages(int $limit, int $offset): array {
	global $wpdb;
	$table = tbb_contact_form_table_name();

	$limit = max(1, $limit);
	$offset = max(0, $offset);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, name, email, subject, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		),
		ARRAY_A
	);
}

function tbb_contact_form_count_messages(): int {
	global $wpdb;
	$table = tbb_contact_form_table_name();

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

function tbb_contact_form_get_message(int $id): ?array {
	global $wpdb;
	$table = tbb_contact_form_table_name();

	$row = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
		ARRAY_A
	);

	return is_array($row) ? $row : null;
}

function tbb_contact_form_delete_message(int $id): bool {
	global $wpdb;
	$table = tbb_contact_form_table_name();

	return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
}
