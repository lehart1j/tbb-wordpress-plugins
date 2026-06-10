<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_Vault {
	private static ?self $instance = null;

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		TBB_Vault_Features::register();
		TBB_Vault_Templates::register();
		TBB_Vault_Admin::register();
		TBB_Vault_Editor_UI::register();

		add_action('wp_enqueue_scripts', [$this, 'front_assets'], 20);
	}

	public function front_assets(): void {
		if (!is_page()) {
			return;
		}
		$id = get_queried_object_id();
		if ($id <= 0) {
			return;
		}
		if (!TBB_Vault_Templates::is_vault_template(get_page_template_slug($id))) {
			return;
		}
		wp_enqueue_style('tbb-vault', TBB_VAULT_URL . 'assets/vault.css', [], TBB_VAULT_VERSION);
	}
}
