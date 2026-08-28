<?php
/**
 * Front-end widget markup.
 *
 * Expects the following variables from Purrfect_Match::render_shortcode().
 *
 * @var array  $atts         Resolved shortcode attributes.
 * @var array  $config       Runtime config for the browser script.
 * @var string $instance_id  Unique DOM id for this widget instance.
 * @var string $schema_ld    Optional page-level JSON-LD.
 * @var array  $brand_tokens CSS-ready RGB and contrast values.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_org_name     = $atts['org_name'];
$pm_org_website  = $atts['org_website'];
$pm_hide_breed   = ! empty( $atts['hide_breed'] );
$pm_show_credit  = ! empty( $atts['show_credit'] );
$pm_schema_ld    = isset( $schema_ld ) ? $schema_ld : '';
$pm_title_id     = $instance_id . '-title';
$pm_results_id   = $instance_id . '-results-title';
$pm_description  = empty( $atts['title'] ) ? __( 'Adoptable pets', 'purrfect-match' ) : '';
$pm_title        = str_replace( '-', '‑', (string) $atts['title'] );
$pm_show_org_website = ! empty( $pm_org_website );

if ( $pm_show_org_website ) {
	$pm_org_host  = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $pm_org_website, PHP_URL_HOST ) ) );
	$pm_home_host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
	$pm_org_path  = untrailingslashit( (string) wp_parse_url( $pm_org_website, PHP_URL_PATH ) );
	$pm_home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

	// A link back to the page's own site adds noise without giving visitors a
	// new destination. Keep it for widgets embedded on a different site.
	if ( $pm_org_host && $pm_org_host === $pm_home_host && $pm_org_path === $pm_home_path ) {
		$pm_show_org_website = false;
	}
}
?>
<section
	class="pm-wrap pm-cols-<?php echo (int) $atts['columns']; ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-pm-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
	style="--pm-brand: <?php echo esc_attr( $atts['brand'] ); ?>; --pm-brand-rgb: <?php echo esc_attr( $brand_tokens['rgb'] ); ?>; --pm-on-brand: <?php echo esc_attr( $brand_tokens['contrast'] ); ?>; --pm-cols: <?php echo (int) $atts['columns']; ?>;"
	<?php if ( ! empty( $atts['title'] ) ) : ?>aria-labelledby="<?php echo esc_attr( $pm_title_id ); ?>"<?php else : ?>aria-label="<?php echo esc_attr( $pm_description ); ?>"<?php endif; ?>
>
	<?php if ( '' !== $pm_schema_ld ) : ?>
		<script type="application/ld+json"><?php echo $pm_schema_ld; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD is generated with wp_json_encode(). ?></script>
	<?php endif; ?>

	<div class="pm-shell">
		<header class="pm-banner">
			<span class="pm-blob pm-blob-1" aria-hidden="true"></span>
			<span class="pm-blob pm-blob-2" aria-hidden="true"></span>

			<div class="pm-banner-row">
				<div class="pm-banner-intro">
					<div class="pm-eyebrow">
						<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"></path></svg>
						<?php if ( '' !== $pm_org_name ) : ?>
							<span><?php echo esc_html( $pm_org_name ); ?></span>
							<?php if ( ! empty( $atts['eyebrow'] ) ) : ?>
								<span class="pm-eyebrow-dot" aria-hidden="true"></span>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $atts['eyebrow'] ) ) : ?>
							<span class="pm-eyebrow-sub"><?php echo esc_html( $atts['eyebrow'] ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $atts['title'] ) ) : ?>
						<h2 class="pm-title" id="<?php echo esc_attr( $pm_title_id ); ?>"><?php echo esc_html( $pm_title ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $atts['subtitle'] ) ) : ?>
						<p class="pm-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
					<?php endif; ?>

					<?php if ( $pm_show_org_website || '' !== $pm_org_name ) : ?>
						<div class="pm-links">
							<?php if ( $pm_show_org_website ) : ?>
								<a class="pm-link" href="<?php echo esc_url( $pm_org_website ); ?>" target="_blank" rel="noopener noreferrer">
									<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg>
									<?php
									/* translators: %s: organization website host. */
									echo esc_html( sprintf( __( 'Visit %s', 'purrfect-match' ), wp_parse_url( $pm_org_website, PHP_URL_HOST ) ? wp_parse_url( $pm_org_website, PHP_URL_HOST ) : $pm_org_website ) );
									?>
									<span class="pm-sr-only"><?php esc_html_e( '(opens in a new tab)', 'purrfect-match' ); ?></span>
								</a>
							<?php endif; ?>
							<?php if ( '' !== $pm_org_name ) : ?>
								<span class="pm-powered pm-sr-only">
									<?php
									/* translators: %s: organization name. */
									echo esc_html( sprintf( __( 'Powered by %s', 'purrfect-match' ), $pm_org_name ) );
									?>
								</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="pm-hero-mark" aria-hidden="true">
					<svg viewBox="0 0 120 120" focusable="false">
						<ellipse cx="60" cy="74" rx="27" ry="22"></ellipse>
						<circle cx="27" cy="47" r="11"></circle>
						<circle cx="49" cy="31" r="11"></circle>
						<circle cx="75" cy="31" r="11"></circle>
						<circle cx="96" cy="49" r="11"></circle>
					</svg>
				</div>
			</div>
		</header>

		<section class="pm-controls" aria-label="<?php esc_attr_e( 'Filter adoptable pets', 'purrfect-match' ); ?>">
			<div class="pm-controls-head">
				<div>
					<h3 class="pm-controls-title"><?php esc_html_e( 'Refine your search', 'purrfect-match' ); ?></h3>
					<p class="pm-controls-hint"><?php esc_html_e( 'Mix and match filters to find a new friend.', 'purrfect-match' ); ?></p>
				</div>
				<div class="pm-actions">
					<button type="button" class="pm-btn" data-pm-action="shuffle">
						<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"></path></svg>
						<?php esc_html_e( 'Shuffle', 'purrfect-match' ); ?>
					</button>
					<button type="button" class="pm-btn pm-btn-quiet" data-pm-action="clear" hidden>
						<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"></path></svg>
						<?php esc_html_e( 'Reset', 'purrfect-match' ); ?>
					</button>
				</div>
			</div>

			<div class="pm-filters pm-filters-<?php echo $pm_hide_breed ? '2' : '3'; ?>">
				<?php if ( ! $pm_hide_breed ) : ?>
					<div class="pm-field">
						<label class="pm-label" for="<?php echo esc_attr( $instance_id ); ?>-breed"><?php esc_html_e( 'Breed', 'purrfect-match' ); ?></label>
						<select class="pm-select" id="<?php echo esc_attr( $instance_id ); ?>-breed" data-pm-filter="breed" data-pm-all="<?php esc_attr_e( 'All breeds', 'purrfect-match' ); ?>">
							<option value="all"><?php esc_html_e( 'All breeds', 'purrfect-match' ); ?></option>
						</select>
					</div>
				<?php endif; ?>

				<div class="pm-field">
					<label class="pm-label" for="<?php echo esc_attr( $instance_id ); ?>-size"><?php esc_html_e( 'Size', 'purrfect-match' ); ?></label>
					<select class="pm-select" id="<?php echo esc_attr( $instance_id ); ?>-size" data-pm-filter="size" data-pm-all="<?php esc_attr_e( 'All sizes', 'purrfect-match' ); ?>">
						<option value="all"><?php esc_html_e( 'All sizes', 'purrfect-match' ); ?></option>
					</select>
				</div>

				<div class="pm-field">
					<label class="pm-label" for="<?php echo esc_attr( $instance_id ); ?>-age"><?php esc_html_e( 'Age', 'purrfect-match' ); ?></label>
					<select class="pm-select" id="<?php echo esc_attr( $instance_id ); ?>-age" data-pm-filter="age" data-pm-all="<?php esc_attr_e( 'All ages', 'purrfect-match' ); ?>">
						<option value="all"><?php esc_html_e( 'All ages', 'purrfect-match' ); ?></option>
					</select>
				</div>
			</div>

			<div class="pm-meta">
				<div class="pm-count" data-pm-count></div>
				<div class="pm-sr-only" data-pm-status role="status" aria-live="polite" aria-atomic="true"></div>
				<div class="pm-chips" data-pm-chips></div>
			</div>
		</section>

		<div class="pm-body">
			<h3 class="pm-sr-only" id="<?php echo esc_attr( $pm_results_id ); ?>"><?php esc_html_e( 'Adoptable pets', 'purrfect-match' ); ?></h3>
			<div class="pm-grid" data-pm-grid role="list" aria-labelledby="<?php echo esc_attr( $pm_results_id ); ?>" aria-busy="false"></div>

			<div class="pm-more" data-pm-more hidden>
				<button type="button" class="pm-btn pm-btn-brand" data-pm-action="more">
					<?php esc_html_e( 'Load more pets', 'purrfect-match' ); ?>
				</button>
			</div>

			<footer class="pm-footer">
				<span><?php esc_html_e( 'Listings provided by Petfinder', 'purrfect-match' ); ?></span>
				<?php if ( $pm_show_credit ) : ?>
					<span class="pm-credit">
						<?php
						printf(
							/* translators: %s: author link. */
							esc_html__( 'Plugin by %s', 'purrfect-match' ),
							'<a href="https://www.andrewmayes.com/" target="_blank" rel="noopener noreferrer">Andrew Mayes</a>'
						);
						?>
					</span>
				<?php endif; ?>
			</footer>
		</div>
	</div>
</section>
