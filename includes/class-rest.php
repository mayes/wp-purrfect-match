<?php
/**
 * Optional shared server-side cache (REST).
 *
 * Petfinder's endpoint blocks server-side requests, so WordPress can't fetch
 * the listings itself. Instead:
 *   - Visitors READ a cached copy from this site (one fast DB read, no call
 *     to Petfinder).
 *   - Only logged-in editors/admins (edit_pages) WRITE/refresh the cache,
 *     populated by their own browser after a live fetch. This keeps the cache
 *     from being writable — and therefore poisonable — by the public or by
 *     lower-trust roles.
 *
 * The write endpoint relies on the REST cookie-auth nonce (X-WP-Nonce), so a
 * valid capability check requires a logged-in session; anonymous writes fail.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for the shared listings cache.
 */
class Purrfect_Match_REST {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NS = 'purrfect-match/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the cache routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/pets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_pets' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'key' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'put_pets' ),
					'permission_callback' => array( $this, 'can_write' ),
				),
			)
		);
	}

	/**
	 * Only logged-in editors/admins may refresh the cache.
	 *
	 * Gated on `edit_pages` (Editors and Administrators) rather than
	 * `edit_posts` so lower-trust roles (Contributors/Authors) cannot overwrite
	 * the listings served to every visitor.
	 *
	 * @return bool
	 */
	public function can_write() {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Transient name for a given client cache key.
	 *
	 * @param string $key Client-provided cache key.
	 * @return string
	 */
	protected function transient_name( $key ) {
		return 'pm_cache_' . md5( $key );
	}

	/**
	 * GET: return the cached pets for a key (or null when absent/expired).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_pets( $request ) {
		$key = (string) $request->get_param( 'key' );
		if ( '' === $key ) {
			return new WP_REST_Response( array( 'cats' => null ), 200 );
		}

		$data = get_transient( $this->transient_name( $key ) );
		if ( ! is_array( $data ) || ! isset( $data['cats'] ) ) {
			return new WP_REST_Response( array( 'cats' => null ), 200 );
		}

		return new WP_REST_Response(
			array(
				't'    => isset( $data['t'] ) ? (int) $data['t'] : 0,
				'cats' => $data['cats'],
			),
			200
		);
	}

	/**
	 * POST: store a freshly-fetched set (capable users only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function put_pets( $request ) {
		$key  = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$cats = $request->get_param( 'cats' );

		if ( '' === $key || ! is_array( $cats ) ) {
			return new WP_Error( 'pm_bad_request', 'Invalid cache payload.', array( 'status' => 400 ) );
		}

		$options = Purrfect_Match_Settings::get_options();
		$minutes = isset( $options['cache_minutes'] ) ? max( 1, (int) $options['cache_minutes'] ) : 120;

		set_transient(
			$this->transient_name( $key ),
			array(
				't'    => time() * 1000,
				'cats' => $this->sanitize_cats( $cats ),
			),
			$minutes * MINUTE_IN_SECONDS
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Strictly sanitize the posted pet list to a known shape.
	 *
	 * @param array $cats Raw pets.
	 * @return array
	 */
	protected function sanitize_cats( $cats ) {
		$out  = array();
		$cats = array_slice( $cats, 0, 1000 );

		foreach ( $cats as $cat ) {
			if ( ! is_array( $cat ) ) {
				continue;
			}
			// Bios are free text: keep line breaks but cap the length so a long
			// description (x up to 1000 pets) can't bloat a single transient.
			$bio     = isset( $cat['bio'] ) ? sanitize_textarea_field( (string) $cat['bio'] ) : '';
			$bio_len = function_exists( 'mb_strlen' ) ? mb_strlen( $bio ) : strlen( $bio );
			if ( $bio_len > 600 ) {
				$bio = function_exists( 'mb_substr' ) ? mb_substr( $bio, 0, 600 ) : substr( $bio, 0, 600 );
			}
			$out[] = array(
				'id'    => isset( $cat['id'] ) ? sanitize_text_field( (string) $cat['id'] ) : '',
				'name'  => isset( $cat['name'] ) ? sanitize_text_field( (string) $cat['name'] ) : '',
				'breed' => isset( $cat['breed'] ) ? sanitize_text_field( (string) $cat['breed'] ) : '',
				'size'  => isset( $cat['size'] ) ? sanitize_text_field( (string) $cat['size'] ) : '',
				'age'   => isset( $cat['age'] ) ? sanitize_text_field( (string) $cat['age'] ) : '',
				'bio'   => $bio,
				'city'  => isset( $cat['city'] ) ? sanitize_text_field( (string) $cat['city'] ) : '',
				'state' => isset( $cat['state'] ) ? sanitize_text_field( (string) $cat['state'] ) : '',
				'photo' => isset( $cat['photo'] ) ? esc_url_raw( (string) $cat['photo'] ) : '',
				'url'   => isset( $cat['url'] ) ? esc_url_raw( (string) $cat['url'] ) : '',
			);
		}

		return $out;
	}
}
