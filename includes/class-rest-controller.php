<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Rest_Controller {

	private Pet_Cache $cache;
	private Petfinder_Client $api_client;

	public function __construct( Pet_Cache $cache, Petfinder_Client $api_client ) {
		$this->cache      = $cache;
		$this->api_client = $api_client;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$namespace = 'purrfect-match/v1';

		register_rest_route( $namespace, '/pets', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_pets' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'page'   => array(
					'type'              => 'integer',
					'default'           => 1,
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
				'limit'  => array(
					'type'              => 'integer',
					'default'           => 12,
					'minimum'           => 1,
					'maximum'           => 100,
					'sanitize_callback' => 'absint',
				),
				'breed'  => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'age'    => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'gender' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'size'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( $namespace, '/pets/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_pet' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( $namespace, '/test-connection', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'test_connection' ),
			'permission_callback' => array( $this, 'admin_permission_check' ),
		) );

		register_rest_route( $namespace, '/flush-cache', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'flush_cache' ),
			'permission_callback' => array( $this, 'admin_permission_check' ),
		) );
	}

	public function get_pets( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = array_filter( array(
			'page'   => $request->get_param( 'page' ),
			'limit'  => $request->get_param( 'limit' ),
			'breed'  => $request->get_param( 'breed' ),
			'age'    => $request->get_param( 'age' ),
			'gender' => $request->get_param( 'gender' ),
			'size'   => $request->get_param( 'size' ),
		), function ( $v ) {
			return '' !== $v && null !== $v;
		} );

		$result = $this->cache->get_pets( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public function get_pet( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = $request->get_param( 'id' );
		$result = $this->cache->get_pet( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public function test_connection(): \WP_REST_Response|\WP_Error {
		$result = $this->api_client->test_connection();

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'pm_connection_failed',
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function flush_cache(): \WP_REST_Response {
		$this->cache->flush_cache();
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function admin_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}
}
