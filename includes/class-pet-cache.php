<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Pet_Cache {

	private const CACHE_PREFIX = 'purrfect_match_pets_';

	private Petfinder_Client $client;
	private Pet_Data_Normalizer $normalizer;

	public function __construct( Petfinder_Client $client, Pet_Data_Normalizer $normalizer ) {
		$this->client     = $client;
		$this->normalizer = $normalizer;
	}

	public function get_pets( array $params = array() ): array|\WP_Error {
		$cache_key = $this->get_cache_key( $params );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->client->get_animals( $params );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$normalized = $this->normalizer->normalize_list( $response );
		set_transient( $cache_key, $normalized, $this->get_ttl() );

		return $normalized;
	}

	public function get_pet( int $id ): array|\WP_Error {
		$cache_key = self::CACHE_PREFIX . 'single_' . $id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->client->get_animal( $id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$normalized = $this->normalizer->normalize_single( $response );
		set_transient( $cache_key, $normalized, $this->get_ttl() );

		return $normalized;
	}

	public function flush_cache(): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
			)
		);
		$this->client->clear_token();
	}

	private function get_cache_key( array $params ): string {
		ksort( $params );
		return self::CACHE_PREFIX . md5( wp_json_encode( $params ) );
	}

	private function get_ttl(): int {
		$options = get_option( 'purrfect_match_options', array() );
		return absint( $options['cache_ttl'] ?? 3600 );
	}
}
