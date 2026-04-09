<?php
/**
 * Template for the filter bar.
 *
 * @var array  $atts    Shortcode attributes.
 * @var array  $options Plugin options.
 * @var array  $pets    Array of normalized pet data.
 */

defined( 'ABSPATH' ) || exit;

// Collect unique breeds for the dropdown.
$breeds = array();
foreach ( $pets as $pet ) {
	if ( ! empty( $pet['breed_primary'] ) ) {
		$breeds[ $pet['breed_primary'] ] = $pet['breed_primary'];
	}
}
ksort( $breeds );
?>
<div class="pm-filters" role="search" aria-label="<?php esc_attr_e( 'Filter adoptable cats', 'purrfect-match' ); ?>">

	<?php if ( 'true' === $atts['show_search'] ) : ?>
		<div class="pm-filters__search">
			<label for="pm-search" class="screen-reader-text"><?php esc_html_e( 'Search cats', 'purrfect-match' ); ?></label>
			<input type="search"
				id="pm-search"
				class="pm-filters__input"
				placeholder="<?php esc_attr_e( 'Search by name or breed...', 'purrfect-match' ); ?>"
				autocomplete="off" />
			<svg class="pm-filters__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
			</svg>
		</div>
	<?php endif; ?>

	<?php if ( 'true' === $atts['show_filters'] ) : ?>
		<div class="pm-filters__groups">
			<fieldset class="pm-filters__group">
				<legend class="pm-filters__label"><?php esc_html_e( 'Age', 'purrfect-match' ); ?></legend>
				<div class="pm-filters__pills" data-filter="age">
					<button type="button" class="pm-pill" data-value="baby"><?php esc_html_e( 'Baby', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="young"><?php esc_html_e( 'Young', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="adult"><?php esc_html_e( 'Adult', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="senior"><?php esc_html_e( 'Senior', 'purrfect-match' ); ?></button>
				</div>
			</fieldset>

			<fieldset class="pm-filters__group">
				<legend class="pm-filters__label"><?php esc_html_e( 'Gender', 'purrfect-match' ); ?></legend>
				<div class="pm-filters__pills" data-filter="gender">
					<button type="button" class="pm-pill" data-value="male"><?php esc_html_e( 'Male', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="female"><?php esc_html_e( 'Female', 'purrfect-match' ); ?></button>
				</div>
			</fieldset>

			<fieldset class="pm-filters__group">
				<legend class="pm-filters__label"><?php esc_html_e( 'Size', 'purrfect-match' ); ?></legend>
				<div class="pm-filters__pills" data-filter="size">
					<button type="button" class="pm-pill" data-value="small"><?php esc_html_e( 'Small', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="medium"><?php esc_html_e( 'Medium', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="large"><?php esc_html_e( 'Large', 'purrfect-match' ); ?></button>
					<button type="button" class="pm-pill" data-value="xlarge"><?php esc_html_e( 'Extra Large', 'purrfect-match' ); ?></button>
				</div>
			</fieldset>

			<?php if ( ! empty( $breeds ) ) : ?>
				<fieldset class="pm-filters__group">
					<legend class="pm-filters__label"><?php esc_html_e( 'Breed', 'purrfect-match' ); ?></legend>
					<label for="pm-breed-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by breed', 'purrfect-match' ); ?></label>
					<select id="pm-breed-filter" class="pm-filters__select" data-filter="breed">
						<option value=""><?php esc_html_e( 'All Breeds', 'purrfect-match' ); ?></option>
						<?php foreach ( $breeds as $breed ) : ?>
							<option value="<?php echo esc_attr( strtolower( $breed ) ); ?>"><?php echo esc_html( $breed ); ?></option>
						<?php endforeach; ?>
					</select>
				</fieldset>
			<?php endif; ?>

			<?php if ( ! empty( $options['show_favorites'] ) ) : ?>
				<div class="pm-filters__group pm-filters__group--favorites">
					<button type="button" class="pm-pill pm-pill--favorites" data-filter="favorites">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
						</svg>
						<?php esc_html_e( 'Favorites', 'purrfect-match' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<div class="pm-filters__group">
				<button type="button" class="pm-filters__reset" aria-label="<?php esc_attr_e( 'Clear all filters', 'purrfect-match' ); ?>">
					<?php esc_html_e( 'Clear Filters', 'purrfect-match' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<div class="pm-filters__count" aria-live="polite" aria-atomic="true"></div>
</div>
