<?php

if (!defined('ABSPATH')) {
	exit;
}

final class TBB_News_Banner_Frontend {
	public static function register(): void {
		add_action('wp_enqueue_scripts', [self::class, 'assets'], 5);
		add_action('wp_body_open', [self::class, 'render'], 5);
		add_action('astra_header_before', [self::class, 'render'], 5);
		add_filter('body_class', [self::class, 'body_class']);
	}

	public static function body_class(array $classes): array {
		if (self::should_show()) {
			$classes[] = 'tbb-news-banner-active';
		}
		return $classes;
	}

	private static function should_show(): bool {
		if (is_admin() || wp_doing_ajax() || is_feed()) {
			return false;
		}
		$settings = TBB_News_Banner_Settings::get();
		if (empty($settings['enabled'])) {
			return false;
		}
		return count(TBB_News_Banner_Content::get_items()) > 0;
	}

	public static function assets(): void {
		if (!self::should_show()) {
			return;
		}
		wp_enqueue_style(
			'tbb-news-banner',
			TBB_NEWS_BANNER_URL . 'assets/banner.css',
			[],
			TBB_NEWS_BANNER_VERSION
		);
		wp_enqueue_script(
			'tbb-news-banner',
			TBB_NEWS_BANNER_URL . 'assets/banner.js',
			[],
			TBB_NEWS_BANNER_VERSION,
			true
		);
	}

	public static function render(): void {
		static $done = false;
		if ($done || !self::should_show()) {
			return;
		}
		$done = true;

		$settings = TBB_News_Banner_Settings::get();
		$items = TBB_News_Banner_Content::get_items();
		if (empty($items)) {
			return;
		}

		$label = (string) $settings['label'];
		?>
		<div class="tbb-news-banner" id="tbb-news-banner" role="region" aria-label="<?php esc_attr_e('Latest updates', 'tbb-news-banner'); ?>">
			<div class="tbb-news-banner__inner">
				<?php if ($label !== '') : ?>
					<span class="tbb-news-banner__label"><?php echo esc_html($label); ?></span>
				<?php endif; ?>
				<div class="tbb-news-banner__track" data-tbb-nb-track>
					<?php foreach ($items as $index => $item) :
						$has_excerpt = ($item['excerpt'] ?? '') !== '';
						?>
						<a
							class="tbb-news-banner__item<?php echo $index === 0 ? ' is-active' : ''; ?><?php echo $has_excerpt ? ' has-excerpt' : ''; ?>"
							href="<?php echo esc_url($item['url']); ?>"
							data-tbb-nb-item
						>
							<span class="tbb-news-banner__media">
								<?php if (($item['image'] ?? '') !== '') : ?>
									<img
										class="tbb-news-banner__thumb"
										src="<?php echo esc_url($item['image']); ?>"
										alt=""
										loading="lazy"
										decoding="async"
										width="64"
										height="64"
									>
								<?php else :
									$initial = function_exists('mb_substr')
										? mb_strtoupper(mb_substr($item['type_label'], 0, 1))
										: strtoupper(substr($item['type_label'], 0, 1));
									?>
									<span class="tbb-news-banner__thumb tbb-news-banner__thumb--fallback" aria-hidden="true">
										<?php echo esc_html($initial); ?>
									</span>
								<?php endif; ?>
							</span>
							<span class="tbb-news-banner__body">
								<span class="tbb-news-banner__meta">
									<span class="tbb-news-banner__type"><?php echo esc_html($item['type_label']); ?></span>
									<span class="tbb-news-banner__title"><?php echo esc_html($item['title']); ?></span>
								</span>
								<?php if ($has_excerpt) : ?>
									<span class="tbb-news-banner__excerpt"><?php echo esc_html($item['excerpt']); ?></span>
								<?php endif; ?>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
				<button type="button" class="tbb-news-banner__dismiss" data-tbb-nb-dismiss aria-label="<?php esc_attr_e('Dismiss banner', 'tbb-news-banner'); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
		</div>
		<?php
	}
}
