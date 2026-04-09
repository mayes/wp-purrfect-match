<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Pet_Data_Normalizer {

	public function normalize_list( array $api_response ): array {
		$animals = $api_response['animals'] ?? array();
		$pets    = array();

		foreach ( $animals as $animal ) {
			$pets[] = $this->normalize_animal( $animal );
		}

		return array(
			'pets'       => $pets,
			'pagination' => $api_response['pagination'] ?? array(),
		);
	}

	public function normalize_single( array $api_response ): array {
		$animal = $api_response['animal'] ?? $api_response;
		return $this->normalize_animal( $animal );
	}

	private function normalize_animal( array $animal ): array {
		$photos        = $this->normalize_photos( $animal['photos'] ?? array() );
		$photo_primary = ! empty( $photos ) ? $photos[0]['medium'] : PURRFECT_MATCH_URL . 'assets/images/placeholder-cat.svg';
		$description   = $animal['description'] ?? '';

		return array(
			'id'               => (int) ( $animal['id'] ?? 0 ),
			'name'             => sanitize_text_field( $animal['name'] ?? '' ),
			'species'          => sanitize_text_field( $animal['species'] ?? 'Cat' ),
			'breed_primary'    => sanitize_text_field( $animal['breeds']['primary'] ?? '' ),
			'breed_secondary'  => sanitize_text_field( $animal['breeds']['secondary'] ?? '' ),
			'breed_mixed'      => (bool) ( $animal['breeds']['mixed'] ?? false ),
			'age'              => sanitize_text_field( $animal['age'] ?? '' ),
			'gender'           => sanitize_text_field( $animal['gender'] ?? '' ),
			'size'             => sanitize_text_field( $animal['size'] ?? '' ),
			'color_primary'    => sanitize_text_field( $animal['colors']['primary'] ?? '' ),
			'description'      => wp_kses_post( $description ),
			'description_plain' => wp_strip_all_tags( $description ),
			'status'           => sanitize_text_field( $animal['status'] ?? 'adoptable' ),
			'photos'           => $photos,
			'photo_primary'    => $photo_primary,
			'attributes'       => array(
				'spayed_neutered' => (bool) ( $animal['attributes']['spayed_neutered'] ?? false ),
				'house_trained'   => (bool) ( $animal['attributes']['house_trained'] ?? false ),
				'declawed'        => (bool) ( $animal['attributes']['declawed'] ?? false ),
				'special_needs'   => (bool) ( $animal['attributes']['special_needs'] ?? false ),
				'shots_current'   => (bool) ( $animal['attributes']['shots_current'] ?? false ),
			),
			'environment'      => array(
				'cats'     => $animal['environment']['cats'] ?? null,
				'dogs'     => $animal['environment']['dogs'] ?? null,
				'children' => $animal['environment']['children'] ?? null,
			),
			'tags'             => array_map( 'sanitize_text_field', $animal['tags'] ?? array() ),
			'url'              => esc_url_raw( $animal['url'] ?? '' ),
			'published_at'     => sanitize_text_field( $animal['published_at'] ?? '' ),
			'contact'          => array(
				'email'   => sanitize_email( $animal['contact']['email'] ?? '' ),
				'phone'   => sanitize_text_field( $animal['contact']['phone'] ?? '' ),
				'address' => array(
					'city'    => sanitize_text_field( $animal['contact']['address']['city'] ?? '' ),
					'state'   => sanitize_text_field( $animal['contact']['address']['state'] ?? '' ),
					'postcode' => sanitize_text_field( $animal['contact']['address']['postcode'] ?? '' ),
				),
			),
		);
	}

	private function normalize_photos( array $photos ): array {
		$normalized = array();
		foreach ( $photos as $photo ) {
			$normalized[] = array(
				'small'  => esc_url_raw( $photo['small'] ?? '' ),
				'medium' => esc_url_raw( $photo['medium'] ?? '' ),
				'large'  => esc_url_raw( $photo['large'] ?? '' ),
				'full'   => esc_url_raw( $photo['full'] ?? '' ),
			);
		}
		return $normalized;
	}
}
