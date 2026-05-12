<?php

if (!defined('ABSPATH')) {
	exit;
}

function tbb_contact_forms_table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'tbb_contact_forms';
}

function tbb_contact_form_create_forms_table(): void {
	global $wpdb;

	$table = tbb_contact_forms_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(190) NOT NULL DEFAULT '',
		button_label VARCHAR(190) NOT NULL DEFAULT '',
		modal_title VARCHAR(190) NOT NULL DEFAULT '',
		fields_json LONGTEXT NOT NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY updated_at (updated_at)
	) {$charset_collate};";

	dbDelta($sql);
}

function tbb_contact_form_upgrade_messages_table(): void {
	global $wpdb;
	$table = tbb_contact_form_table_name();

	$col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'form_id'");
	if (empty($col)) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id");
	}

	$col2 = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'fields_data'");
	if (empty($col2)) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query("ALTER TABLE {$table} ADD COLUMN fields_data LONGTEXT NULL AFTER message");
	}
}

function tbb_contact_form_list_forms(): array {
	global $wpdb;
	$table = tbb_contact_forms_table_name();

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (array) $wpdb->get_results("SELECT id, title, button_label, updated_at FROM {$table} ORDER BY title ASC", ARRAY_A);
}

function tbb_contact_form_get_form(int $id): ?array {
	global $wpdb;
	$table = tbb_contact_forms_table_name();

	$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
	return is_array($row) ? $row : null;
}

function tbb_contact_form_save_form(array $data): int {
	global $wpdb;
	$table = tbb_contact_forms_table_name();

	$now = gmdate('Y-m-d H:i:s');
	$row = [
		'title' => (string) ($data['title'] ?? ''),
		'button_label' => (string) ($data['button_label'] ?? ''),
		'modal_title' => (string) ($data['modal_title'] ?? ''),
		'fields_json' => (string) ($data['fields_json'] ?? '[]'),
		'updated_at' => $now,
	];

	$id = isset($data['id']) ? (int) $data['id'] : 0;

	if ($id > 0) {
		$wpdb->update($table, $row, ['id' => $id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
		return $id;
	}

	$row['created_at'] = $now;
	$wpdb->insert($table, $row, ['%s', '%s', '%s', '%s', '%s', '%s']);
	return (int) $wpdb->insert_id;
}

function tbb_contact_form_delete_form(int $id): bool {
	global $wpdb;
	$table = tbb_contact_forms_table_name();
	return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
}

/**
 * @return list<array{name:string,label:string,type:string,required:bool,options?:string}>
 */
function tbb_contact_form_parse_fields_json(string $json): array {
	$decoded = json_decode($json, true);
	if (!is_array($decoded)) {
		return [];
	}

	$allowed_types = ['text', 'email', 'textarea', 'tel', 'url', 'number', 'select'];
	$out = [];

	foreach ($decoded as $row) {
		if (!is_array($row)) {
			continue;
		}
		$name = isset($row['name']) ? sanitize_key((string) $row['name']) : '';
		if ($name === '') {
			continue;
		}
		$type = isset($row['type']) ? (string) $row['type'] : 'text';
		if (!in_array($type, $allowed_types, true)) {
			$type = 'text';
		}
		$out[] = [
			'name' => $name,
			'label' => isset($row['label']) ? sanitize_text_field((string) $row['label']) : $name,
			'type' => $type,
			'required' => !empty($row['required']),
			'options' => isset($row['options']) ? sanitize_text_field((string) $row['options']) : '',
		];
	}

	return $out;
}
