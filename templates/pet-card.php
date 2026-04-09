<?php
/**
 * Template for a single pet card.
 *
 * @var array $pet       Normalized pet data.
 * @var array $options   Plugin options.
 */

defined( 'ABSPATH' ) || exit;

$breed_display = $pet['breed_primary'];
if ( $pet['breed_mixed'] && $pet['breed_secondary'] ) {
	$breed_display .= ' / ' . $pet['breed_secondary'];
} elseif ( $pet['breed_mixed'] ) {
	$breed_display .= ' Mix';
}

$card_label = sprintf(
	/* translators: 1: pet name, 2: breed, 3: age, 4: gender */
	__( '%1$s, a %2$s, %3$s %4$s', 'purrfect-match' ),
	$pet['name'],
	$breed_display,
	strtolower( $pet['age'] ),
	strtolower( $pet['gender'] )
);

$alt_text = sprintf(
	/* translators: 1: pet name, 2: breed */
	__( '%1$s, a %2$s cat available for adoption', 'purrfect-match' ),
	$pet['name'],
	$breed_display
);
?>
<article class="pm-card"
	data-pet-id="<?php echo esc_attr( $pet['id'] ); ?>"
	data-breed="<?php echo esc_attr( strtolower( $pet['breed_primary'] ) ); ?>"
	data-age="<?php echo esc_attr( strtolower( $pet['age'] ) ); ?>"
	data-gender="<?php echo esc_attr( strtolower( $pet['gender'] ) ); ?>"
	data-size="<?php echo esc_attr( strtolower( $pet['size'] ) ); ?>"
	data-search="<?php echo esc_attr( strtolower( $pet['name'] . ' ' . $breed_display . ' ' . $pet['description_plain'] ) ); ?>"
	role="listitem"
	tabindex="0"
	aria-label="<?php echo esc_attr( $card_label ); ?>">

	<div class="pm-card__image-wrapper">
		<img class="pm-card__image pm-lazy"
			src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Crect fill='%23e0e0e0' width='400' height='300'/%3E%3C/svg%3E"
			data-src="<?php echo esc_url( $pet['photo_primary'] ); ?>"
			<?php if ( ! empty( $pet['photos'] ) && ! empty( $pet['photos'][0]['large'] ) ) : ?>
				data-srcset="<?php echo esc_url( $pet['photos'][0]['medium'] ); ?> 300w, <?php echo esc_url( $pet['photos'][0]['large'] ); ?> 600w"
				sizes="(max-width: 400px) 100vw, 300px"
			<?php endif; ?>
			alt="<?php echo esc_attr( $alt_text ); ?>"
			loading="lazy"
			width="400"
			height="300" />

		<?php if ( ! empty( $options['show_favorites'] ) ) : ?>
			<button class="pm-card__favorite"
				type="button"
				aria-label="<?php printf( esc_attr__( 'Add %s to favorites', 'purrfect-match' ), esc_attr( $pet['name'] ) ); ?>"
				data-pet-id="<?php echo esc_attr( $pet['id'] ); ?>">
				<svg class="pm-icon-heart" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
				</svg>
			</button>
		<?php endif; ?>
	</div>

	<div class="pm-card__body">
		<h3 class="pm-card__name"><?php echo esc_html( $pet['name'] ); ?></h3>
		<p class="pm-card__breed"><?php echo esc_html( $breed_display ); ?></p>
		<p class="pm-card__meta">
			<span class="pm-card__age"><?php echo esc_html( $pet['age'] ); ?></span>
			<span class="pm-card__separator" aria-hidden="true">&bull;</span>
			<span class="pm-card__gender"><?php echo esc_html( $pet['gender'] ); ?></span>
			<span class="pm-card__separator" aria-hidden="true">&bull;</span>
			<span class="pm-card__size"><?php echo esc_html( $pet['size'] ); ?></span>
		</p>
		<div class="pm-card__badges">
			<?php if ( true === $pet['environment']['cats'] ) : ?>
				<span class="pm-badge pm-badge--cats" title="<?php esc_attr_e( 'Good with cats', 'purrfect-match' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
					<span><?php esc_html_e( 'Cats', 'purrfect-match' ); ?></span>
				</span>
			<?php endif; ?>
			<?php if ( true === $pet['environment']['dogs'] ) : ?>
				<span class="pm-badge pm-badge--dogs" title="<?php esc_attr_e( 'Good with dogs', 'purrfect-match' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
					<span><?php esc_html_e( 'Dogs', 'purrfect-match' ); ?></span>
				</span>
			<?php endif; ?>
			<?php if ( true === $pet['environment']['children'] ) : ?>
				<span class="pm-badge pm-badge--kids" title="<?php esc_attr_e( 'Good with children', 'purrfect-match' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
					<span><?php esc_html_e( 'Kids', 'purrfect-match' ); ?></span>
				</span>
			<?php endif; ?>
		</div>
	</div>
</article>
