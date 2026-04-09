<?php
/**
 * Template for the pet detail modal shell.
 * Populated by JavaScript when a card is clicked.
 *
 * @var array $options Plugin options.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pm-modal" role="dialog" aria-modal="true" aria-labelledby="pm-modal-name" hidden>
	<div class="pm-modal__backdrop"></div>
	<div class="pm-modal__content">
		<button type="button" class="pm-modal__close" aria-label="<?php esc_attr_e( 'Close', 'purrfect-match' ); ?>">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>

		<div class="pm-modal__gallery">
			<img class="pm-modal__photo" id="pm-modal-photo" src="" alt="" />
			<div class="pm-modal__thumbnails" id="pm-modal-thumbs"></div>
		</div>

		<div class="pm-modal__details">
			<h2 class="pm-modal__name" id="pm-modal-name"></h2>
			<p class="pm-modal__breed" id="pm-modal-breed"></p>
			<div class="pm-modal__meta" id="pm-modal-meta"></div>
			<div class="pm-modal__badges" id="pm-modal-badges"></div>
			<div class="pm-modal__attributes" id="pm-modal-attrs"></div>
			<div class="pm-modal__tags" id="pm-modal-tags"></div>
			<div class="pm-modal__description" id="pm-modal-desc"></div>

			<div class="pm-modal__actions">
				<?php if ( ! empty( $options['adoption_url'] ) ) : ?>
					<a class="pm-btn pm-btn--primary" id="pm-modal-adopt"
						href="<?php echo esc_url( $options['adoption_url'] ); ?>"
						target="_blank"
						rel="noopener noreferrer">
						<?php echo esc_html( $options['adoption_text'] ?? __( 'Adopt Me!', 'purrfect-match' ) ); ?>
					</a>
				<?php endif; ?>

				<a class="pm-btn pm-btn--secondary" id="pm-modal-petfinder" href="" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View on Petfinder', 'purrfect-match' ); ?>
				</a>
			</div>

			<?php if ( ! empty( $options['show_sharing'] ) ) : ?>
				<div class="pm-modal__share" id="pm-modal-share">
					<span class="pm-modal__share-label"><?php esc_html_e( 'Share:', 'purrfect-match' ); ?></span>
					<button type="button" class="pm-share-btn" data-platform="facebook" aria-label="<?php esc_attr_e( 'Share on Facebook', 'purrfect-match' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
					</button>
					<button type="button" class="pm-share-btn" data-platform="twitter" aria-label="<?php esc_attr_e( 'Share on X', 'purrfect-match' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
					</button>
					<button type="button" class="pm-share-btn" data-platform="email" aria-label="<?php esc_attr_e( 'Share via email', 'purrfect-match' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
