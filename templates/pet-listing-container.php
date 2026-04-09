<?php
/**
 * Main container template for the pet listing.
 *
 * @var array  $pets    Array of normalized pet data.
 * @var array  $atts    Shortcode attributes.
 * @var array  $options Plugin options.
 */

defined( 'ABSPATH' ) || exit;

$card_radius = 'rounded' === ( $options['card_style'] ?? 'rounded' ) ? '12px' : '0';
?>
<div class="pm-container"
	data-layout="<?php echo esc_attr( $atts['layout'] ); ?>"
	data-columns="<?php echo esc_attr( $atts['columns'] ); ?>"
	style="--pm-primary: <?php echo esc_attr( $options['primary_color'] ?? '#6C63FF' ); ?>; --pm-radius: <?php echo esc_attr( $card_radius ); ?>;"
	role="region"
	aria-label="<?php esc_attr_e( 'Adoptable cats', 'purrfect-match' ); ?>">

	<?php if ( 'true' === $atts['show_filters'] || 'true' === $atts['show_search'] ) : ?>
		<?php include PURRFECT_MATCH_DIR . 'templates/filter-bar.php'; ?>
	<?php endif; ?>

	<?php if ( ! empty( $pets ) ) : ?>
		<div class="pm-grid" role="list">
			<?php foreach ( $pets as $pet ) : ?>
				<?php include PURRFECT_MATCH_DIR . 'templates/pet-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<?php include PURRFECT_MATCH_DIR . 'templates/no-results.php'; ?>
	<?php endif; ?>

	<?php include PURRFECT_MATCH_DIR . 'templates/pet-modal.php'; ?>

	<script type="application/json" data-pm-pets><?php echo wp_json_encode( $pets ); ?></script>
</div>
