<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Plugin {

	private static ?Plugin $instance = null;
	private Petfinder_Client $api_client;
	private Pet_Cache $cache;
	private Admin_Settings $admin;
	private Shortcode $shortcode;
	private Rest_Controller $rest;
	private Asset_Manager $assets;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		$dir = PURRFECT_MATCH_DIR . 'includes/';
		require_once $dir . 'class-petfinder-client.php';
		require_once $dir . 'class-pet-data-normalizer.php';
		require_once $dir . 'class-pet-cache.php';
		require_once $dir . 'class-admin-settings.php';
		require_once $dir . 'class-shortcode.php';
		require_once $dir . 'class-rest-controller.php';
		require_once $dir . 'class-asset-manager.php';
	}

	private function init_components(): void {
		$this->api_client = new Petfinder_Client();
		$normalizer       = new Pet_Data_Normalizer();
		$this->cache      = new Pet_Cache( $this->api_client, $normalizer );
		$this->assets     = new Asset_Manager();
		$this->shortcode  = new Shortcode( $this->cache, $this->assets );
		$this->rest       = new Rest_Controller( $this->cache, $this->api_client );
		$this->admin      = new Admin_Settings( $this->api_client );
	}

	private function register_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		register_activation_hook( PURRFECT_MATCH_DIR . 'wp-purrfect-match.php', array( $this, 'activate' ) );
		register_deactivation_hook( PURRFECT_MATCH_DIR . 'wp-purrfect-match.php', array( $this, 'deactivate' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'purrfect-match', false, dirname( PURRFECT_MATCH_BASENAME ) . '/languages' );
	}

	public function register_block(): void {
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( PURRFECT_MATCH_DIR . 'blocks/pet-listing' );
		}
	}

	public function activate(): void {
		$defaults = array(
			'api_key'         => '',
			'api_secret'      => '',
			'organization_id' => '',
			'cache_ttl'       => 3600,
			'pets_per_page'   => 12,
			'default_layout'  => 'grid',
			'show_filters'    => true,
			'show_search'     => true,
			'show_favorites'  => true,
			'show_sharing'    => true,
			'adoption_url'    => '',
			'adoption_text'   => 'Adopt Me!',
			'primary_color'   => '#6C63FF',
			'card_style'      => 'rounded',
		);

		if ( false === get_option( 'purrfect_match_options' ) ) {
			add_option( 'purrfect_match_options', $defaults );
		}
	}

	public function deactivate(): void {
		$this->cache->flush_cache();
	}

	public function get_shortcode(): Shortcode {
		return $this->shortcode;
	}

	public function get_cache(): Pet_Cache {
		return $this->cache;
	}
}
