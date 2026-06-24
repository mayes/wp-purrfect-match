<?php
/**
 * Settings: options storage, defaults, and the admin settings page.
 *
 * @package PurrfectMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin's options and the Settings → Purrfect Match screen.
 */
class Purrfect_Match_Settings {

	/**
	 * Option key used in wp_options.
	 *
	 * @var string
	 */
	const OPTION = 'purrfect_match_options';

	/**
	 * Settings group / page slug.
	 *
	 * @var string
	 */
	const PAGE = 'purrfect-match';

	/**
	 * Cached, merged options.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default option values.
	 *
	 * These mirror the live CJ Paws pet-scroller embed:
	 *   organization FL1629, type cat, status adoptable, limit 24, hideBreed true.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Listing source / query.
			'organization'  => 'FL1629',
			'type'          => 'cat',
			'status'        => 'adoptable',
			'limit'         => 24,
			'columns'       => 3,
			'hide_breed'    => 0,

			// Presentation / copy.
			'title'         => 'Find your purr-fect match',
			'eyebrow'       => 'Adoptable Cats',
			'subtitle'      => 'Filter by breed, size, and age.',
			'brand'         => '#e93396',
			'org_name'      => 'CJ Paws',
			'org_website'   => 'https://cjpaws.org',

			// Endpoints (advanced — rarely changed).
			'api_base'      => 'https://psl.petfinder.com/graphql',
			's3_url'        => 'https://dbw3zep4prcju.cloudfront.net/',
			'petfinder_url' => 'https://www.petfinder.com/',
		);
	}

	/**
	 * Get the merged options (saved values over defaults).
	 *
	 * @return array
	 */
	public static function get_options() {
		if ( null === self::$cache ) {
			$saved        = get_option( self::OPTION, array() );
			$saved        = is_array( $saved ) ? $saved : array();
			self::$cache  = wp_parse_args( $saved, self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Purrfect Match', 'purrfect-match' ),
			__( 'Purrfect Match', 'purrfect-match' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the single option with a sanitizing callback and the fields UI.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'pm_section_listing',
			__( 'Listing', 'purrfect-match' ),
			array( $this, 'section_listing_intro' ),
			self::PAGE
		);

		$listing_fields = array(
			'organization' => array( __( 'Petfinder organization ID(s)', 'purrfect-match' ), 'text', __( 'Your Petfinder display ID (e.g. FL1629) or UUID. Separate multiple with commas.', 'purrfect-match' ) ),
			'type'         => array( __( 'Animal type', 'purrfect-match' ), 'type', __( 'Which kind of animal to list.', 'purrfect-match' ) ),
			'status'       => array( __( 'Adoption status', 'purrfect-match' ), 'status', '' ),
			'limit'        => array( __( 'Maximum pets to load', 'purrfect-match' ), 'number', __( '1–100. These are fetched once, then filtered instantly in the browser.', 'purrfect-match' ) ),
			'columns'      => array( __( 'Columns (desktop)', 'purrfect-match' ), 'columns', '' ),
			'hide_breed'   => array( __( 'Hide breed', 'purrfect-match' ), 'checkbox', __( 'Hide the breed name and the breed filter.', 'purrfect-match' ) ),
		);

		foreach ( $listing_fields as $key => $cfg ) {
			add_settings_field(
				'pm_' . $key,
				$cfg[0],
				array( $this, 'render_field' ),
				self::PAGE,
				'pm_section_listing',
				array(
					'key'  => $key,
					'type' => $cfg[1],
					'desc' => $cfg[2],
				)
			);
		}

		add_settings_section(
			'pm_section_appearance',
			__( 'Appearance & copy', 'purrfect-match' ),
			'__return_false',
			self::PAGE
		);

		$appearance_fields = array(
			'title'       => array( __( 'Heading', 'purrfect-match' ), 'text', '' ),
			'eyebrow'     => array( __( 'Eyebrow label', 'purrfect-match' ), 'text', __( 'Small label above the heading.', 'purrfect-match' ) ),
			'subtitle'    => array( __( 'Subheading', 'purrfect-match' ), 'text', '' ),
			'brand'       => array( __( 'Brand color', 'purrfect-match' ), 'color', __( 'Accent color for buttons, chips, and cards.', 'purrfect-match' ) ),
			'org_name'    => array( __( 'Organization name', 'purrfect-match' ), 'text', '' ),
			'org_website' => array( __( 'Organization website', 'purrfect-match' ), 'url', '' ),
		);

		foreach ( $appearance_fields as $key => $cfg ) {
			add_settings_field(
				'pm_' . $key,
				$cfg[0],
				array( $this, 'render_field' ),
				self::PAGE,
				'pm_section_appearance',
				array(
					'key'  => $key,
					'type' => $cfg[1],
					'desc' => $cfg[2],
				)
			);
		}

		add_settings_section(
			'pm_section_advanced',
			__( 'Advanced', 'purrfect-match' ),
			array( $this, 'section_advanced_intro' ),
			self::PAGE
		);

		$advanced_fields = array(
			'api_base'      => array( __( 'GraphQL endpoint', 'purrfect-match' ), 'url', '' ),
			's3_url'        => array( __( 'Photo CDN base URL', 'purrfect-match' ), 'url', '' ),
			'petfinder_url' => array( __( 'Petfinder base URL', 'purrfect-match' ), 'url', '' ),
		);

		foreach ( $advanced_fields as $key => $cfg ) {
			add_settings_field(
				'pm_' . $key,
				$cfg[0],
				array( $this, 'render_field' ),
				self::PAGE,
				'pm_section_advanced',
				array(
					'key'  => $key,
					'type' => $cfg[1],
					'desc' => $cfg[2],
				)
			);
		}
	}

	/**
	 * Listing section intro copy.
	 *
	 * @return void
	 */
	public function section_listing_intro() {
		echo '<p>' . esc_html__( 'Choose which pets to show. Add the shortcode below to any page or post.', 'purrfect-match' ) . '</p>';
		echo '<p><code>[purrfect_match]</code></p>';
	}

	/**
	 * Advanced section intro copy.
	 *
	 * @return void
	 */
	public function section_advanced_intro() {
		echo '<p>' . esc_html__( 'Defaults match the public Petfinder widget. Only change these if you know what you are doing.', 'purrfect-match' ) . '</p>';
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Field args: key, type, desc.
	 * @return void
	 */
	public function render_field( $args ) {
		$options = self::get_options();
		$key     = $args['key'];
		$type    = $args['type'];
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$name    = self::OPTION . '[' . $key . ']';
		$id      = 'pm_' . $key;

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Enabled', 'purrfect-match' )
				);
				break;

			case 'number':
				printf(
					'<input type="number" min="1" max="100" step="1" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="#e93396" /> <input type="color" value="%4$s" oninput="document.getElementById(\'%1$s\').value=this.value" aria-hidden="true" tabindex="-1" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $value ) ? $value : '#e93396' )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text code" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'type':
				$choices = array(
					'cat'              => __( 'Cats', 'purrfect-match' ),
					'dog'              => __( 'Dogs', 'purrfect-match' ),
					'rabbit'           => __( 'Rabbits', 'purrfect-match' ),
					'small-furry'      => __( 'Small & furry', 'purrfect-match' ),
					'bird'             => __( 'Birds', 'purrfect-match' ),
					'horse'            => __( 'Horses', 'purrfect-match' ),
					'barnyard'         => __( 'Barnyard', 'purrfect-match' ),
					'scales-fins-other' => __( 'Scales, fins & other', 'purrfect-match' ),
				);
				$this->render_select( $id, $name, $choices, $value );
				break;

			case 'status':
				$choices = array(
					'adoptable' => __( 'Adoptable', 'purrfect-match' ),
					'adopted'   => __( 'Adopted', 'purrfect-match' ),
					'found'     => __( 'Found', 'purrfect-match' ),
				);
				$this->render_select( $id, $name, $choices, $value );
				break;

			case 'columns':
				$choices = array(
					'2' => __( '2 columns', 'purrfect-match' ),
					'3' => __( '3 columns', 'purrfect-match' ),
					'4' => __( '4 columns', 'purrfect-match' ),
				);
				$this->render_select( $id, $name, $choices, (string) $value );
				break;

			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a <select> element.
	 *
	 * @param string $id      Element id.
	 * @param string $name    Element name.
	 * @param array  $choices value => label.
	 * @param string $current Current value.
	 * @return void
	 */
	protected function render_select( $id, $name, $choices, $current ) {
		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
		foreach ( $choices as $val => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $val ),
				selected( (string) $current, (string) $val, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Sanitize all option values before saving.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		// Organization: comma-separated list of display IDs / UUIDs.
		$orgs = isset( $input['organization'] ) ? (string) $input['organization'] : '';
		$orgs = array_filter( array_map( 'trim', explode( ',', $orgs ) ) );
		$orgs = array_map(
			static function ( $id ) {
				return preg_replace( '/[^A-Za-z0-9\-]/', '', $id );
			},
			$orgs
		);
		$out['organization'] = implode( ', ', $orgs );

		// Constrained selects.
		$allowed_types  = array( 'cat', 'dog', 'rabbit', 'small-furry', 'bird', 'horse', 'barnyard', 'scales-fins-other' );
		$out['type']    = in_array( ( isset( $input['type'] ) ? $input['type'] : '' ), $allowed_types, true ) ? $input['type'] : $defaults['type'];

		$allowed_status = array( 'adoptable', 'adopted', 'found' );
		$out['status']  = in_array( ( isset( $input['status'] ) ? $input['status'] : '' ), $allowed_status, true ) ? $input['status'] : $defaults['status'];

		// Numbers.
		$limit         = isset( $input['limit'] ) ? absint( $input['limit'] ) : $defaults['limit'];
		$out['limit']  = max( 1, min( 100, $limit ) );

		$columns        = isset( $input['columns'] ) ? absint( $input['columns'] ) : $defaults['columns'];
		$out['columns'] = max( 2, min( 4, $columns ) );

		// Checkbox.
		$out['hide_breed'] = empty( $input['hide_breed'] ) ? 0 : 1;

		// Text copy.
		$out['title']    = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : $defaults['title'] );
		$out['eyebrow']  = sanitize_text_field( isset( $input['eyebrow'] ) ? $input['eyebrow'] : $defaults['eyebrow'] );
		$out['subtitle'] = sanitize_text_field( isset( $input['subtitle'] ) ? $input['subtitle'] : $defaults['subtitle'] );
		$out['org_name'] = sanitize_text_field( isset( $input['org_name'] ) ? $input['org_name'] : $defaults['org_name'] );

		// Brand color (hex).
		$brand         = isset( $input['brand'] ) ? sanitize_text_field( $input['brand'] ) : $defaults['brand'];
		$out['brand']  = preg_match( '/^#[0-9a-fA-F]{6}$/', $brand ) ? $brand : $defaults['brand'];

		// URLs.
		$out['org_website']   = esc_url_raw( isset( $input['org_website'] ) ? $input['org_website'] : $defaults['org_website'] );
		$out['api_base']      = esc_url_raw( isset( $input['api_base'] ) ? $input['api_base'] : $defaults['api_base'] );
		$out['s3_url']        = esc_url_raw( isset( $input['s3_url'] ) ? $input['s3_url'] : $defaults['s3_url'] );
		$out['petfinder_url'] = esc_url_raw( isset( $input['petfinder_url'] ) ? $input['petfinder_url'] : $defaults['petfinder_url'] );

		// Reset the in-process cache so the new values are used immediately.
		self::$cache = null;

		return $out;
	}

	/**
	 * Render the settings page wrapper.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
