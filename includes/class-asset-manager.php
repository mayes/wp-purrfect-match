<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Asset_Manager {

	private bool $enqueued = false;

	public function enqueue_frontend( array $options = array() ): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		wp_enqueue_style(
			'purrfect-match-frontend',
			PURRFECT_MATCH_URL . 'assets/css/purrfect-match-frontend.css',
			array(),
			PURRFECT_MATCH_VERSION
		);

		wp_enqueue_script(
			'purrfect-match-frontend',
			PURRFECT_MATCH_URL . 'assets/js/purrfect-match-frontend.js',
			array(),
			PURRFECT_MATCH_VERSION,
			true
		);

		wp_localize_script( 'purrfect-match-frontend', 'purrfectMatchConfig', array(
			'restUrl'      => rest_url( 'purrfect-match/v1/' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'adoptionUrl'  => $options['adoption_url'] ?? '',
			'adoptionText' => $options['adoption_text'] ?? __( 'Adopt Me!', 'purrfect-match' ),
			'showFavorites' => ! empty( $options['show_favorites'] ),
			'showSharing'  => ! empty( $options['show_sharing'] ),
			'i18n'         => array(
				'noResults'      => __( 'No cats match your criteria. Try adjusting your filters.', 'purrfect-match' ),
				'addFavorite'    => __( 'Add to favorites', 'purrfect-match' ),
				'removeFavorite' => __( 'Remove from favorites', 'purrfect-match' ),
				'loadMore'       => __( 'Load More Cats', 'purrfect-match' ),
				'loading'        => __( 'Loading...', 'purrfect-match' ),
				'goodWithCats'   => __( 'Good with cats', 'purrfect-match' ),
				'goodWithDogs'   => __( 'Good with dogs', 'purrfect-match' ),
				'goodWithKids'   => __( 'Good with children', 'purrfect-match' ),
				'spayedNeutered' => __( 'Spayed/Neutered', 'purrfect-match' ),
				'houseTrained'   => __( 'House Trained', 'purrfect-match' ),
				'shotsCurrent'   => __( 'Shots Current', 'purrfect-match' ),
				'specialNeeds'   => __( 'Special Needs', 'purrfect-match' ),
				'showing'        => __( 'Showing', 'purrfect-match' ),
				'of'             => __( 'of', 'purrfect-match' ),
				'cats'           => __( 'cats', 'purrfect-match' ),
			),
		) );
	}
}
