<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Shortcode {

	private Pet_Cache $cache;
	private Asset_Manager $assets;

	public function __construct( Pet_Cache $cache, Asset_Manager $assets ) {
		$this->cache  = $cache;
		$this->assets = $assets;
		add_shortcode( 'purrfect_match', array( $this, 'render' ) );
	}

	public function render( array|string $atts = array() ): string {
		$options = get_option( 'purrfect_match_options', array() );

		$atts = shortcode_atts( array(
			'layout'       => $options['default_layout'] ?? 'grid',
			'per_page'     => $options['pets_per_page'] ?? 12,
			'columns'      => '3',
			'breed'        => '',
			'age'          => '',
			'gender'       => '',
			'size'         => '',
			'show_filters' => ( ! empty( $options['show_filters'] ) ) ? 'true' : 'false',
			'show_search'  => ( ! empty( $options['show_search'] ) ) ? 'true' : 'false',
		), (array) $atts, 'purrfect_match' );

		// Build API params from shortcode attributes.
		$api_params = array(
			'limit' => min( 100, absint( $atts['per_page'] ) ),
		);

		if ( ! empty( $atts['breed'] ) ) {
			$api_params['breed'] = sanitize_text_field( $atts['breed'] );
		}
		if ( ! empty( $atts['age'] ) ) {
			$api_params['age'] = sanitize_text_field( $atts['age'] );
		}
		if ( ! empty( $atts['gender'] ) ) {
			$api_params['gender'] = sanitize_text_field( $atts['gender'] );
		}
		if ( ! empty( $atts['size'] ) ) {
			$api_params['size'] = sanitize_text_field( $atts['size'] );
		}

		$result = $this->cache->get_pets( $api_params );

		if ( is_wp_error( $result ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return sprintf(
					'<div class="pm-error"><p>%s %s</p></div>',
					esc_html__( 'Purrfect Match Error:', 'purrfect-match' ),
					esc_html( $result->get_error_message() )
				);
			}
			return '';
		}

		$pets = $result['pets'] ?? array();

		// Enqueue frontend assets.
		$this->assets->enqueue_frontend( $options );

		ob_start();
		include PURRFECT_MATCH_DIR . 'templates/pet-listing-container.php';
		return ob_get_clean();
	}
}
