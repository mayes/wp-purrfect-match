<?php
/**
 * Core plugin: assets, shortcode, and the runtime config passed to the browser.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller (singleton).
 */
class Purrfect_Match {

	/**
	 * Singleton instance.
	 *
	 * @var Purrfect_Match|null
	 */
	protected static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var Purrfect_Match_Settings
	 */
	public $settings;

	/**
	 * Whether assets have been enqueued for this request.
	 *
	 * @var bool
	 */
	protected $enqueued = false;

	/**
	 * Counter to give each widget instance a unique id.
	 *
	 * @var int
	 */
	protected $instance_count = 0;

	/**
	 * Get the singleton.
	 *
	 * @return Purrfect_Match
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Wire up hooks.
	 *
	 * @return void
	 */
	protected function boot() {
		$this->settings = new Purrfect_Match_Settings();
		$this->settings->hooks();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'purrfect_match', array( $this, 'render_shortcode' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PURRFECT_MATCH_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Register (but do not enqueue) front-end assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'purrfect-match',
			PURRFECT_MATCH_URL . 'assets/css/purrfect-match.css',
			array(),
			PURRFECT_MATCH_VERSION
		);

		wp_register_script(
			'purrfect-match',
			PURRFECT_MATCH_URL . 'assets/js/purrfect-match.js',
			array(),
			PURRFECT_MATCH_VERSION,
			true
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url      = admin_url( 'options-general.php?page=' . Purrfect_Match_Settings::PAGE );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'purrfect-match' ) . '</a>';
		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Interpret a value as a boolean ("true"/"1"/"yes"/"on" => true).
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	protected function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Resolve the effective attributes for a shortcode instance, merging
	 * saved settings with any per-shortcode overrides.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	protected function resolve_atts( $atts ) {
		$options = Purrfect_Match_Settings::get_options();

		$atts = shortcode_atts(
			array(
				'organization'  => $options['organization'],
				'type'          => $options['type'],
				'status'        => $options['status'],
				'limit'         => $options['limit'],
				'columns'       => $options['columns'],
				'hide_breed'    => $options['hide_breed'],
				'title'         => $options['title'],
				'eyebrow'       => $options['eyebrow'],
				'subtitle'      => $options['subtitle'],
				'brand'         => $options['brand'],
				'org_name'      => $options['org_name'],
				'org_website'   => $options['org_website'],
				'api_base'      => $options['api_base'],
				's3_url'        => $options['s3_url'],
				'petfinder_url' => $options['petfinder_url'],
			),
			$atts,
			'purrfect_match'
		);

		// Normalize types.
		$atts['limit']      = max( 1, min( 100, absint( $atts['limit'] ) ) );
		$atts['columns']    = max( 2, min( 4, absint( $atts['columns'] ) ) );
		$atts['hide_breed'] = $this->truthy( $atts['hide_breed'] );

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $atts['brand'] ) ) {
			$atts['brand'] = '#e93396';
		}

		return $atts;
	}

	/**
	 * Build the configuration object handed to the browser script.
	 *
	 * @param array $atts Resolved attributes.
	 * @return array
	 */
	protected function build_config( $atts ) {
		$orgs = array_filter( array_map( 'trim', explode( ',', (string) $atts['organization'] ) ) );
		$orgs = array_values( $orgs );

		return array(
			'apiBase'      => esc_url_raw( $atts['api_base'] ),
			's3Url'        => esc_url_raw( $atts['s3_url'] ),
			'petfinderUrl' => esc_url_raw( $atts['petfinder_url'] ),
			'organization' => $orgs,
			'type'         => sanitize_text_field( $atts['type'] ),
			'status'       => sanitize_text_field( $atts['status'] ),
			'limit'        => (int) $atts['limit'],
			'hideBreed'    => (bool) $atts['hide_breed'],
			'brand'        => $atts['brand'],
			'orgName'      => sanitize_text_field( $atts['org_name'] ),
		);
	}

	/**
	 * Shortcode callback: enqueue assets and render the widget markup.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts = $this->resolve_atts( $atts );

		if ( ! $this->enqueued ) {
			wp_enqueue_style( 'purrfect-match' );
			wp_enqueue_script( 'purrfect-match' );
			$this->enqueued = true;
		}

		$this->instance_count++;
		$instance_id = 'pm-' . $this->instance_count;
		$config      = $this->build_config( $atts );

		ob_start();
		include PURRFECT_MATCH_PATH . 'templates/widget.php';

		return ob_get_clean();
	}
}
