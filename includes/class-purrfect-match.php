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
	 * REST controller (shared cache).
	 *
	 * @var Purrfect_Match_REST
	 */
	public $rest;

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
	 * Whether the page-level structured data has been emitted yet.
	 *
	 * @var bool
	 */
	protected $schema_emitted = false;

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

		$this->rest = new Purrfect_Match_REST();
		$this->rest->hooks();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'print_late_styles' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_shortcode( 'purrfect_match', array( $this, 'render_shortcode' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PURRFECT_MATCH_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'maybe_setup_notice' ) );
	}

	/**
	 * Enqueue admin assets, only on the plugin's settings screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function admin_assets( $hook ) {
		// Settings screen.
		if ( 'settings_page_' . Purrfect_Match_Settings::PAGE === $hook ) {
			wp_enqueue_style(
				'purrfect-match-admin',
				PURRFECT_MATCH_URL . 'assets/css/admin.css',
				array(),
				PURRFECT_MATCH_VERSION
			);
			wp_enqueue_script(
				'purrfect-match-admin',
				PURRFECT_MATCH_URL . 'assets/js/admin.js',
				array(),
				PURRFECT_MATCH_VERSION,
				true
			);
			wp_localize_script(
				'purrfect-match-admin',
				'PM_ADMIN',
				array(
					'copied'     => __( 'Copied!', 'purrfect-match' ),
					'copyFailed' => __( 'Copy failed', 'purrfect-match' ),
				)
			);
			return;
		}

		// Petfinder Explorer tool screen.
		if ( 'tools_page_' . Purrfect_Match_Settings::PAGE . '-explorer' === $hook ) {
			$options = Purrfect_Match_Settings::get_options();
			$orgs    = array_filter( array_map( 'trim', explode( ',', (string) $options['organization'] ) ) );

			wp_enqueue_style(
				'purrfect-match-explorer',
				PURRFECT_MATCH_URL . 'assets/css/explorer.css',
				array(),
				PURRFECT_MATCH_VERSION
			);
			wp_enqueue_script(
				'purrfect-match-explorer',
				PURRFECT_MATCH_URL . 'assets/js/explorer.js',
				array(),
				PURRFECT_MATCH_VERSION,
				true
			);
			wp_localize_script(
				'purrfect-match-explorer',
				'PM_EXPLORER',
				array(
					'org'     => $orgs ? reset( $orgs ) : '',
					'type'    => $options['type'],
					'apiBase' => $options['api_base'],
				)
			);
		}
	}

	/**
	 * Show a one-time setup nudge in wp-admin until an organization is set.
	 *
	 * @return void
	 */
	public function maybe_setup_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't nag on the plugin's own settings screen.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_' . Purrfect_Match_Settings::PAGE === $screen->id ) {
			return;
		}

		$options = Purrfect_Match_Settings::get_options();
		if ( '' !== trim( (string) $options['organization'] ) ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=' . Purrfect_Match_Settings::PAGE );
		echo '<div class="notice notice-info is-dismissible"><p>';
		printf(
			/* translators: %s: settings page link. */
			esc_html__( 'Purrfect Match is almost ready — add your Petfinder organization ID in %s to start showing adoptable pets.', 'purrfect-match' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings → Purrfect Match', 'purrfect-match' ) . '</a>'
		);
		echo '</p></div>';
	}

	/**
	 * Register public assets and enqueue styles early when the current query
	 * contains the shortcode.
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

		// Most shortcodes render after wp_head(). Detect them from the queried
		// posts so the stylesheet can still be printed in the document head,
		// without charging every front-end request for widget CSS.
		global $wp_query;
		$posts = isset( $wp_query->posts ) && is_array( $wp_query->posts ) ? $wp_query->posts : array();
		foreach ( $posts as $post ) {
			if ( is_object( $post ) && isset( $post->post_content ) && has_shortcode( $post->post_content, 'purrfect_match' ) ) {
				wp_enqueue_style( 'purrfect-match' );
				break;
			}
		}
	}

	/**
	 * Print the widget stylesheet for shortcodes injected after wp_head(), such
	 * as a dynamic sidebar or builder module. Conventional post shortcodes are
	 * detected early by register_assets() and never use this fallback.
	 *
	 * @return void
	 */
	public function print_late_styles() {
		if ( wp_style_is( 'purrfect-match', 'enqueued' ) && ! wp_style_is( 'purrfect-match', 'done' ) ) {
			wp_print_styles( 'purrfect-match' );
		}
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
				'organization'         => $options['organization'],
				'type'                 => $options['type'],
				'status'               => $options['status'],
				'sort'                 => 'default',
				'limit'                => $options['limit'],
				'per_page'             => $options['per_page'],
				'columns'              => $options['columns'],
				'hide_breed'           => $options['hide_breed'],
				'show_credit'          => $options['show_credit'],
				'title'                => $options['title'],
				'eyebrow'              => $options['eyebrow'],
				'subtitle'             => $options['subtitle'],
				'brand'                => $options['brand'],
				'org_name'             => $options['org_name'],
				'org_website'          => $options['org_website'],
				'adoption_form_url'    => $options['adoption_form_url'],
				'adoptapet_url'        => $options['adoptapet_url'],
				'petfinder_member_url' => $options['petfinder_member_url'],
				'api_base'             => $options['api_base'],
				's3_url'               => $options['s3_url'],
				'petfinder_url'        => $options['petfinder_url'],
			),
			$atts,
			'purrfect_match'
		);

		// Normalize types. limit 0 = "all".
		$atts['limit']       = min( 1000, absint( $atts['limit'] ) );
		$atts['per_page']    = min( 100, absint( $atts['per_page'] ) );
		$atts['columns']     = max( 2, min( 4, absint( $atts['columns'] ) ) );
		$atts['hide_breed']  = $this->truthy( $atts['hide_breed'] );
		$atts['show_credit'] = $this->truthy( $atts['show_credit'] );

		$allowed_types = array( 'cat', 'dog', 'rabbit', 'small-furry', 'bird', 'horse', 'barnyard', 'scales-fins-other' );
		if ( ! in_array( $atts['type'], $allowed_types, true ) ) {
			$atts['type'] = $options['type'];
		}

		$allowed_status = array( 'adoptable', 'adopted', 'found' );
		if ( ! in_array( $atts['status'], $allowed_status, true ) ) {
			$atts['status'] = $options['status'];
		}

		// Sort is intentionally shortcode-only. Convert the caller's value to
		// one of two internal modes before it reaches the browser/GraphQL layer.
		$sort         = is_scalar( $atts['sort'] ) ? strtolower( trim( (string) $atts['sort'] ) ) : 'default';
		$atts['sort'] = in_array( $sort, array( 'default', 'newest' ), true ) ? $sort : 'default';

		$organizations = array_filter( array_map( 'trim', explode( ',', (string) $atts['organization'] ) ) );
		$organizations = array_map(
			static function ( $id ) {
				return preg_replace( '/[^A-Za-z0-9\-]/', '', $id );
			},
			$organizations
		);
		$atts['organization'] = implode( ', ', array_filter( $organizations ) );

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $atts['brand'] ) ) {
			$atts['brand'] = '#e93396';
		}

		return $atts;
	}

	/**
	 * Derive CSS-ready brand tokens, including a readable foreground color.
	 *
	 * @param string $hex Six-digit hexadecimal color.
	 * @return array RGB channels and a contrast-safe foreground.
	 */
	protected function brand_tokens( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );

		$linearize = static function ( $channel ) {
			$value = $channel / 255;
			return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		};
		$luminance = ( 0.2126 * $linearize( $r ) ) + ( 0.7152 * $linearize( $g ) ) + ( 0.0722 * $linearize( $b ) );

		$contrast_ratio = static function ( $first, $second ) {
			$lighter = max( $first, $second );
			$darker  = min( $first, $second );
			return ( $lighter + 0.05 ) / ( $darker + 0.05 );
		};

		$candidates = array(
			'#1b1714' => ( 0.2126 * $linearize( 27 ) ) + ( 0.7152 * $linearize( 23 ) ) + ( 0.0722 * $linearize( 20 ) ),
			'#fffdf9' => ( 0.2126 * $linearize( 255 ) ) + ( 0.7152 * $linearize( 253 ) ) + ( 0.0722 * $linearize( 249 ) ),
		);
		$contrast   = '#1b1714';
		$best_ratio = 0;

		foreach ( $candidates as $candidate => $candidate_luminance ) {
			$ratio = $contrast_ratio( $luminance, $candidate_luminance );
			if ( $ratio > $best_ratio ) {
				$contrast   = $candidate;
				$best_ratio = $ratio;
			}
		}

		// Near the WCAG crossover point, use almost-black/almost-white rather
		// than pure endpoints so arbitrary organization colors still reach AA.
		if ( $best_ratio < 4.5 ) {
			$fallbacks = array(
				'#050403' => ( 0.2126 * $linearize( 5 ) ) + ( 0.7152 * $linearize( 4 ) ) + ( 0.0722 * $linearize( 3 ) ),
				'#fffefa' => ( 0.2126 * $linearize( 255 ) ) + ( 0.7152 * $linearize( 254 ) ) + ( 0.0722 * $linearize( 250 ) ),
			);
			foreach ( $fallbacks as $candidate => $candidate_luminance ) {
				$ratio = $contrast_ratio( $luminance, $candidate_luminance );
				if ( $ratio > $best_ratio ) {
					$contrast   = $candidate;
					$best_ratio = $ratio;
				}
			}
		}

		return array(
			'rgb'      => $r . ', ' . $g . ', ' . $b,
			'contrast' => $contrast,
		);
	}

	/**
	 * Localized copy used by the browser-rendered portions of the widget.
	 *
	 * @return array
	 */
	protected function frontend_strings() {
		return array(
			'breed'              => __( 'Breed', 'purrfect-match' ),
			'size'               => __( 'Size', 'purrfect-match' ),
			'age'                => __( 'Age', 'purrfect-match' ),
			'readStory'          => __( 'Read {name}\'s story', 'purrfect-match' ),
			'readStoryShort'     => __( 'Read story', 'purrfect-match' ),
			'hideStory'          => __( 'Hide {name}\'s story', 'purrfect-match' ),
			'back'               => __( 'Back', 'purrfect-match' ),
			'apply'              => __( 'Apply to adopt', 'purrfect-match' ),
			'viewProfile'        => __( 'View profile', 'purrfect-match' ),
			'meetPet'            => __( 'Meet {name}', 'purrfect-match' ),
			'newTab'             => __( '(opens in a new tab)', 'purrfect-match' ),
			'photoOf'            => __( 'Photo of {name}', 'purrfect-match' ),
			'filterTip'          => __( 'Choose a filter to narrow the list.', 'purrfect-match' ),
			'removeFilter'       => __( 'Remove {label} filter', 'purrfect-match' ),
			'showingCount'       => __( 'Showing {shown} of {total}', 'purrfect-match' ),
			'noMatchesTitle'     => __( 'No pets match those filters', 'purrfect-match' ),
			'noMatchesText'      => __( 'Try removing a filter to see more friends.', 'purrfect-match' ),
			'clearFilters'       => __( 'Clear filters', 'purrfect-match' ),
			'loading'            => __( 'Finding adoptable pets…', 'purrfect-match' ),
			'fallbackTitle'      => __( 'Our live listings are taking a cat nap', 'purrfect-match' ),
			'fallbackText'       => __( 'You can still browse adoptable pets on these platforms.', 'purrfect-match' ),
			'viewAdoptapet'      => __( 'View on Adopt-a-Pet', 'purrfect-match' ),
			'viewPetfinder'      => __( 'View on Petfinder', 'purrfect-match' ),
			'tryAgain'           => __( 'Try again', 'purrfect-match' ),
			'errorTitle'         => __( 'We couldn’t load the pets right now', 'purrfect-match' ),
			'errorText'          => __( 'Please try again in a moment.', 'purrfect-match' ),
			'emptyTitle'         => __( 'No adoptable pets right now', 'purrfect-match' ),
			'emptyText'          => __( 'Please check back soon — new friends arrive all the time.', 'purrfect-match' ),
			'notConfiguredTitle' => __( 'Purrfect Match isn’t set up yet', 'purrfect-match' ),
			'notConfiguredText'  => __( 'Add your Petfinder organization ID to start showing adoptable pets.', 'purrfect-match' ),
			'openSettings'       => __( 'Open settings', 'purrfect-match' ),
			'visitorEmptyTitle'  => __( 'No pets to show right now', 'purrfect-match' ),
			'visitorEmptyText'   => __( 'Please check back soon.', 'purrfect-match' ),
		);
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
		$s3_url = esc_url_raw( $atts['s3_url'] );

		$options = Purrfect_Match_Settings::get_options();

		return array(
			'apiBase'      => esc_url_raw( $atts['api_base'] ),
			's3Url'        => $s3_url ? trailingslashit( $s3_url ) : '',
			'petfinderUrl' => esc_url_raw( $atts['petfinder_url'] ),
			'organization'      => $orgs,
			'type'              => sanitize_text_field( $atts['type'] ),
			'status'            => sanitize_text_field( $atts['status'] ),
			'sort'              => 'newest' === $atts['sort'] ? 'newest' : 'default',
			'limit'             => (int) $atts['limit'],
			'perPage'           => (int) $atts['per_page'],
			'hideBreed'         => (bool) $atts['hide_breed'],
			'showBios'          => ! empty( $options['show_bios'] ),
			'showLocation'      => ! empty( $options['show_location'] ),
			'showBadge'         => ! empty( $options['show_badge'] ),
			'brand'             => $atts['brand'],
			'orgName'           => sanitize_text_field( $atts['org_name'] ),
			'adoptionFormUrl'   => esc_url_raw( $atts['adoption_form_url'] ),
			'adoptapetUrl'      => esc_url_raw( $atts['adoptapet_url'] ),
			'petfinderMemberUrl' => esc_url_raw( $atts['petfinder_member_url'] ),
			'canConfigure'      => current_user_can( 'manage_options' ),
			'settingsUrl'       => admin_url( 'options-general.php?page=' . Purrfect_Match_Settings::PAGE ),
			'serverCache'       => ! empty( $options['server_cache'] ),
			'seo'               => ! empty( $options['seo'] ),
			'restUrl'           => esc_url_raw( rest_url( Purrfect_Match_REST::NS . '/pets' ) ),
			// Only emit a write nonce for users who can actually write, so a
			// cached/anonymous page never carries a usable REST nonce.
			'restNonce'         => current_user_can( 'edit_pages' ) ? wp_create_nonce( 'wp_rest' ) : '',
			'canWrite'          => current_user_can( 'edit_pages' ),
			'strings'           => $this->frontend_strings(),
		);
	}

	/**
	 * Build the page-level JSON-LD (the shelter as an AnimalShelter), at most
	 * once per page. Returns a JSON string, or '' when disabled, already
	 * emitted, or no organization name is set.
	 *
	 * @param array $atts Resolved attributes.
	 * @return string
	 */
	protected function build_schema_ld( $atts ) {
		$options = Purrfect_Match_Settings::get_options();
		if ( empty( $options['seo'] ) || $this->schema_emitted || '' === (string) $atts['org_name'] ) {
			return '';
		}

		$shelter = array(
			'@context' => 'https://schema.org',
			'@type'    => 'AnimalShelter',
			'name'     => (string) $atts['org_name'],
		);
		if ( ! empty( $atts['org_website'] ) ) {
			$shelter['url'] = esc_url_raw( $atts['org_website'] );
		}

		$this->schema_emitted = true;

		return (string) wp_json_encode( $shelter );
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
			if ( ! wp_style_is( 'purrfect-match', 'registered' ) || ! wp_script_is( 'purrfect-match', 'registered' ) ) {
				$this->register_assets();
			}
			wp_enqueue_style( 'purrfect-match' );
			wp_enqueue_script( 'purrfect-match' );
			$this->enqueued = true;
		}

		$this->instance_count++;
		$instance_id = 'pm-' . $this->instance_count;
		$config      = $this->build_config( $atts );
		$schema_ld   = $this->build_schema_ld( $atts );
		$brand_tokens = $this->brand_tokens( $atts['brand'] );

		ob_start();
		include PURRFECT_MATCH_PATH . 'templates/widget.php';

		return ob_get_clean();
	}
}
