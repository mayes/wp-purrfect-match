<?php
/**
 * Template for the empty state when no pets are found.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pm-empty" role="status">
	<svg class="pm-empty__icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<circle cx="12" cy="12" r="10"/>
		<path d="M8 15s1.5 2 4 2 4-2 4-2"/>
		<line x1="9" y1="9" x2="9.01" y2="9"/>
		<line x1="15" y1="9" x2="15.01" y2="9"/>
	</svg>
	<h3 class="pm-empty__title"><?php esc_html_e( 'No cats found', 'purrfect-match' ); ?></h3>
	<p class="pm-empty__text"><?php esc_html_e( 'Try adjusting your filters or check back later for new arrivals.', 'purrfect-match' ); ?></p>
</div>
