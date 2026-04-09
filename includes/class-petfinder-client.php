<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Petfinder_Client {

	private const TOKEN_ENDPOINT = 'https://api.petfinder.com/v2/oauth2/token';
	private const API_BASE       = 'https://api.petfinder.com/v2/';
	private const TOKEN_TRANSIENT = 'purrfect_match_api_token';
	private const TOKEN_BUFFER   = 100;

	private function get_credentials(): array {
		$options = get_option( 'purrfect_match_options', array() );
		return array(
			'key'    => $options['api_key'] ?? '',
			'secret' => $options['api_secret'] ?? '',
		);
	}

	public function get_token(): string|false {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( false !== $cached ) {
			return $cached;
		}

		$creds = $this->get_credentials();
		if ( empty( $creds['key'] ) || empty( $creds['secret'] ) ) {
			return false;
		}

		$response = wp_remote_post( self::TOKEN_ENDPOINT, array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $creds['key'],
				'client_secret' => $creds['secret'],
			),
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return false;
		}

		$ttl = ( $body['expires_in'] ?? 3600 ) - self::TOKEN_BUFFER;
		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], max( $ttl, 60 ) );

		return $body['access_token'];
	}

	private function request( string $endpoint, array $params = array() ): array|\WP_Error {
		$token = $this->get_token();
		if ( false === $token ) {
			return new \WP_Error(
				'pm_auth_failed',
				__( 'Could not authenticate with Petfinder API. Check your API key and secret.', 'purrfect-match' )
			);
		}

		$url = self::API_BASE . $endpoint;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			// Token may have expired — clear it and retry once.
			delete_transient( self::TOKEN_TRANSIENT );
			return $this->request( $endpoint, $params );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = $body['detail'] ?? $body['title'] ?? __( 'Unknown API error.', 'purrfect-match' );
			return new \WP_Error( 'pm_api_error', $message, array( 'status' => $code ) );
		}

		return $body;
	}

	public function get_animals( array $params = array() ): array|\WP_Error {
		$options  = get_option( 'purrfect_match_options', array() );
		$defaults = array(
			'type'   => 'cat',
			'status' => 'adoptable',
			'limit'  => 100,
		);

		if ( ! empty( $options['organization_id'] ) && empty( $params['organization'] ) ) {
			$defaults['organization'] = $options['organization_id'];
		}

		$params = array_merge( $defaults, $params );

		// Remove empty values.
		$params = array_filter( $params, function ( $v ) {
			return '' !== $v && null !== $v;
		} );

		return $this->request( 'animals', $params );
	}

	public function get_animal( int $id ): array|\WP_Error {
		return $this->request( 'animals/' . $id );
	}

	public function test_connection(): bool|\WP_Error {
		$token = $this->get_token();
		if ( false === $token ) {
			return new \WP_Error(
				'pm_auth_failed',
				__( 'Authentication failed. Check your API key and secret.', 'purrfect-match' )
			);
		}

		$result = $this->request( 'animals', array( 'type' => 'cat', 'limit' => 1 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	public function clear_token(): void {
		delete_transient( self::TOKEN_TRANSIENT );
	}
}
