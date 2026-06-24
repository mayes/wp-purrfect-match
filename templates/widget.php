<?php
/**
 * Front-end widget markup.
 *
 * Expects the following variables from the including scope
 * (Purrfect_Match::render_shortcode):
 *
 * @var array  $atts        Resolved shortcode attributes.
 * @var array  $config      Runtime config for the browser script.
 * @var string $instance_id Unique DOM id for this widget instance.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_org_name    = $atts['org_name'];
$pm_org_website = $atts['org_website'];
$pm_hide_breed  = ! empty( $atts['hide_breed'] );
?>
<div
	class="pm-wrap"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-pm-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
	style="--pm-brand: <?php echo esc_attr( $atts['brand'] ); ?>; --pm-cols: <?php echo (int) $atts['columns']; ?>;"
>
	<div class="pm-shell">

		<div class="pm-banner">
			<span class="pm-blob pm-blob-1" aria-hidden="true"></span>
			<span class="pm-blob pm-blob-2" aria-hidden="true"></span>

			<div class="pm-banner-row">
				<div class="pm-banner-intro">
					<div class="pm-eyebrow">
						<span aria-hidden="true">&#128150;</span>
						<span><?php echo esc_html( $pm_org_name ); ?></span>
						<?php if ( ! empty( $atts['eyebrow'] ) ) : ?>
							<span class="pm-eyebrow-dot" aria-hidden="true">&bull;</span>
							<span class="pm-eyebrow-sub"><?php echo esc_html( $atts['eyebrow'] ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $atts['title'] ) ) : ?>
						<h2 class="pm-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $atts['subtitle'] ) ) : ?>
						<p class="pm-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
					<?php endif; ?>

					<div class="pm-links">
						<?php if ( ! empty( $pm_org_website ) ) : ?>
							<a class="pm-link" href="<?php echo esc_url( $pm_org_website ); ?>" target="_blank" rel="noopener noreferrer">
								<span aria-hidden="true">&#127760;</span>
								<?php
								/* translators: %s: organization website host. */
								echo esc_html( sprintf( __( 'Visit %s', 'purrfect-match' ), wp_parse_url( $pm_org_website, PHP_URL_HOST ) ? wp_parse_url( $pm_org_website, PHP_URL_HOST ) : $pm_org_website ) );
								?>
							</a>
						<?php endif; ?>
						<span class="pm-powered">
							<span aria-hidden="true">&#128062;</span>
							<?php
							/* translators: %s: organization name. */
							echo esc_html( sprintf( __( 'Powered by %s', 'purrfect-match' ), $pm_org_name ) );
							?>
						</span>
					</div>
				</div>

				<div class="pm-actions">
					<button type="button" class="pm-btn" data-pm-action="shuffle">
						<span aria-hidden="true">&#128256;</span> <?php esc_html_e( 'Shuffle', 'purrfect-match' ); ?>
					</button>
					<button type="button" class="pm-btn" data-pm-action="clear">
						<span aria-hidden="true">&#129532;</span> <?php esc_html_e( 'Clear', 'purrfect-match' ); ?>
					</button>
				</div>
			</div>

			<div class="pm-filters">
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
				<div class="pm-chips" data-pm-chips></div>
				<div class="pm-count" data-pm-count aria-live="polite"></div>
			</div>
		</div>

		<div class="pm-body">
			<div class="pm-grid" data-pm-grid aria-busy="false" aria-live="polite"></div>
			<div class="pm-footer">
				<?php
				/* translators: %s: organization name. */
				echo esc_html( sprintf( __( 'Adoptable pets via Petfinder • %s', 'purrfect-match' ), $pm_org_name ) );
				?>
			</div>
		</div>

	</div>
</div>
