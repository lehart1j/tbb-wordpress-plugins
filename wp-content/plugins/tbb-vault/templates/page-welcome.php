<?php
/**
 * TBB Vault — Welcome hub (replaces theme template-welcome.php when assigned).
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once TBB_VAULT_PATH . 'includes/template-tags.php';

get_header();

$can_view = (bool) apply_filters('tbb_vault_can_view_page', true, get_queried_object_id());
?>

<div id="primary" class="content-area primary tbb-vault tbb-vault--welcome">
	<main id="main" class="site-main">
		<?php
		while (have_posts()) {
			the_post();
			do_action('tbb_vault_before_welcome', get_post());
			if (!$can_view) {
				do_action('tbb_vault_memberpress_gate', get_post());
				break;
			}
			if (tbb_vault_feature_enabled('show_breadcrumbs')) {
				$ids = tbb_vault_breadcrumb_ids(get_the_ID());
				if (!empty($ids)) {
					echo '<nav class="tbb-vault-breadcrumbs" aria-label="' . esc_attr__('Breadcrumbs', 'tbb-vault') . '">';
					foreach ($ids as $bid) {
						echo '<a href="' . esc_url(get_permalink($bid)) . '">' . esc_html(get_the_title($bid)) . '</a> <span class="tbb-vault-bc-sep">/</span> ';
					}
					echo '<span class="tbb-vault-bc-current">' . esc_html(get_the_title()) . '</span>';
					echo '</nav>';
				}
			}
			if (tbb_vault_feature_enabled('show_page_title')) {
				the_title('<h1 class="entry-title">', '</h1>');
			}
			if (tbb_vault_feature_enabled('show_featured_image') && has_post_thumbnail()) {
				echo '<div class="tbb-vault-featured">';
				the_post_thumbnail('large', ['class' => 'tbb-vault-featured__img']);
				echo '</div>';
			}
			if (tbb_vault_feature_enabled('show_content')) {
				echo '<div class="entry-content">';
				the_content();
				echo '</div>';
			}
			if (tbb_vault_feature_enabled('show_memberpress_account')) {
				$url = tbb_vault_memberpress_account_url();
				echo '<p class="tbb-vault-account-link"><a class="button" href="' . esc_url($url) . '">' . esc_html__('Member area', 'tbb-vault') . '</a></p>';
			}
			if (tbb_vault_feature_enabled('show_child_nav')) {
				$children = get_pages([
					'parent' => get_the_ID(),
					'sort_column' => 'menu_order, post_title',
					'hierarchical' => false,
				]);
				if (!empty($children)) {
					echo '<ul class="tbb-vault-child-nav tbb-vault-child-nav--buttons">';
					foreach ($children as $child) {
						echo '<li><a class="tbb-vault-btn" href="' . esc_url(get_permalink($child)) . '">' . esc_html($child->post_title) . '</a></li>';
					}
					echo '</ul>';
				}
			}
			do_action('tbb_vault_after_welcome', get_post());
		}
		?>
	</main>
</div>

<?php
get_footer();
