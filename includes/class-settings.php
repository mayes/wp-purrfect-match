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
	 * Defaults are intentionally generic so the plugin ships with no
	 * organization preset — each site must enter its own Petfinder
	 * organization ID before any listings appear. Endpoint defaults match
	 * Petfinder's public Custom Pet List Widget (pet-scroller).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Listing source / query.
			'organization'        => '',
			'type'                => 'cat',
			'status'              => 'adoptable',
			'limit'               => 0,
			'per_page'            => 24,
			'columns'             => 3,
			'hide_breed'          => 0,
			'adoption_form_url'   => '',

			// Presentation / copy.
			'title'               => 'Find your purr-fect match',
			'eyebrow'             => 'Adoptable Pets',
			'subtitle'            => 'Filter by breed, size, and age.',
			'brand'               => '#e93396',
			'org_name'            => '',
			'org_website'         => '',

			// Fallback links (shown if the live listings can't load).
			'adoptapet_url'       => '',
			'petfinder_member_url' => '',

			// Performance.
			'server_cache'        => 1,
			'cache_minutes'       => 15,

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

		add_management_page(
			__( 'Petfinder Explorer', 'purrfect-match' ),
			__( 'Petfinder Explorer', 'purrfect-match' ),
			'manage_options',
			self::PAGE . '-explorer',
			array( $this, 'render_explorer_page' )
		);
	}

	/**
	 * Render the Petfinder Explorer tool page.
	 *
	 * @return void
	 */
	public function render_explorer_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap pm-explorer">
			<h1><span aria-hidden="true">🔎</span> <?php esc_html_e( 'Petfinder Explorer', 'purrfect-match' ); ?></h1>
			<p class="pmx-intro">
				<?php esc_html_e( 'Run live GraphQL queries against Petfinder from your own site (where requests are allowed). Use “Discover extra fields” to see what data each animal exposes — handy for deciding what to show on cards.', 'purrfect-match' ); ?>
			</p>

			<div class="pmx-bar">
				<label for="pmx-endpoint"><?php esc_html_e( 'Endpoint', 'purrfect-match' ); ?></label>
				<input type="text" id="pmx-endpoint" class="pmx-endpoint regular-text" value="https://psl.petfinder.com/graphql" spellcheck="false" />
			</div>

			<div class="pmx-presets">
				<button type="button" class="button" data-preset="org"><?php esc_html_e( 'GetOrganization', 'purrfect-match' ); ?></button>
				<button type="button" class="button" data-preset="search"><?php esc_html_e( 'SearchAnimal', 'purrfect-match' ); ?></button>
				<button type="button" class="button" data-preset="attrs"><?php esc_html_e( 'AllAnimalAttributes', 'purrfect-match' ); ?></button>
				<button type="button" class="button" data-preset="introspect"><?php esc_html_e( 'Introspect Animal', 'purrfect-match' ); ?></button>
				<button type="button" class="button button-primary" id="pmx-discover"><?php esc_html_e( '🔬 Discover extra fields', 'purrfect-match' ); ?></button>
			</div>

			<div class="pmx-cols">
				<div class="pmx-pane">
					<label class="pmx-lbl" for="pmx-query"><?php esc_html_e( 'Query', 'purrfect-match' ); ?></label>
					<textarea id="pmx-query" spellcheck="false"></textarea>
					<label class="pmx-lbl" for="pmx-vars"><?php esc_html_e( 'Variables (JSON)', 'purrfect-match' ); ?></label>
					<textarea id="pmx-vars" spellcheck="false">{}</textarea>
					<div class="pmx-run-row">
						<button type="button" class="button pmx-run" id="pmx-run"><?php esc_html_e( 'Run ▶', 'purrfect-match' ); ?></button>
						<span class="pmx-status" id="pmx-status"><?php esc_html_e( 'Ready', 'purrfect-match' ); ?></span>
					</div>
				</div>
				<div class="pmx-pane">
					<label class="pmx-lbl"><?php esc_html_e( 'Response', 'purrfect-match' ); ?></label>
					<pre id="pmx-out"><?php esc_html_e( 'Pick a preset or click “Discover extra fields”, then Run.', 'purrfect-match' ); ?></pre>
				</div>
			</div>

			<div class="pmx-tip">
				<?php esc_html_e( 'Tip: “Discover extra fields” resolves your organization and probes which animal fields exist (description, photos, videos, sex, attributes, environment…). Paste the result to decide what to add to the cards.', 'purrfect-match' ); ?>
			</div>
		</div>
		<?php
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
			'limit'        => array( __( 'Maximum pets to load', 'purrfect-match' ), 'number', __( 'Leave at 0 to always show every adoptable pet (recommended — no need to update it as your numbers change). Set a number only to cap it.', 'purrfect-match' ) ),
			'per_page'     => array( __( 'Pets per page', 'purrfect-match' ), 'number', __( 'How many to show before “Load more” (set 0 to show all at once).', 'purrfect-match' ) ),
			'columns'      => array( __( 'Columns (desktop)', 'purrfect-match' ), 'columns', '' ),
			'hide_breed'   => array( __( 'Hide breed', 'purrfect-match' ), 'checkbox', __( 'Hide the breed name and the breed filter.', 'purrfect-match' ) ),
			'adoption_form_url' => array( __( 'Adoption form URL', 'purrfect-match' ), 'url', __( 'Optional. Adds an “Apply to adopt” button to each pet, linking here with ?pet=Name&pet_id=… so your form can prefill which pet.', 'purrfect-match' ) ),
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
			'pm_section_fallback',
			__( 'Fallback links', 'purrfect-match' ),
			array( $this, 'section_fallback_intro' ),
			self::PAGE
		);

		$fallback_fields = array(
			'adoptapet_url'        => array( __( 'Adopt-a-Pet shelter URL', 'purrfect-match' ), 'url', __( 'Your Adopt-a-Pet listing page.', 'purrfect-match' ) ),
			'petfinder_member_url' => array( __( 'Petfinder member URL', 'purrfect-match' ), 'url', __( 'Your public Petfinder shelter page.', 'purrfect-match' ) ),
		);

		foreach ( $fallback_fields as $key => $cfg ) {
			add_settings_field(
				'pm_' . $key,
				$cfg[0],
				array( $this, 'render_field' ),
				self::PAGE,
				'pm_section_fallback',
				array(
					'key'  => $key,
					'type' => $cfg[1],
					'desc' => $cfg[2],
				)
			);
		}

		add_settings_section(
			'pm_section_performance',
			__( 'Performance', 'purrfect-match' ),
			array( $this, 'section_performance_intro' ),
			self::PAGE
		);

		$performance_fields = array(
			'server_cache'  => array( __( 'Shared cache', 'purrfect-match' ), 'checkbox', __( 'Serve a cached copy of the listings from this site so visitors don’t each call Petfinder. Refreshed automatically when a logged-in editor/admin views a page with the widget.', 'purrfect-match' ) ),
			'cache_minutes' => array( __( 'Cache lifetime (minutes)', 'purrfect-match' ), 'number', __( 'How long a cached copy stays fresh before it’s refreshed.', 'purrfect-match' ) ),
		);

		foreach ( $performance_fields as $key => $cfg ) {
			add_settings_field(
				'pm_' . $key,
				$cfg[0],
				array( $this, 'render_field' ),
				self::PAGE,
				'pm_section_performance',
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
		echo '<p>' . esc_html__( 'Enter your Petfinder organization ID, then add the shortcode below to any page or post.', 'purrfect-match' ) . '</p>';
		echo '<p>' . esc_html__( 'Your organization ID is the short code in your Petfinder shelter URL — for example, the "FL1629" in petfinder.com/member/us/fl/.../FL1629. A UUID also works.', 'purrfect-match' ) . '</p>';
		echo '<p><code>[purrfect_match]</code></p>';
	}

	/**
	 * Fallback section intro copy.
	 *
	 * @return void
	 */
	public function section_fallback_intro() {
		echo '<p>' . esc_html__( 'If the live listings ever fail to load, the widget can point visitors to your other adoption pages instead. Leave blank to just show a “try again” message.', 'purrfect-match' ) . '</p>';
	}

	/**
	 * Performance section intro copy.
	 *
	 * @return void
	 */
	public function section_performance_intro() {
		echo '<p>' . esc_html__( 'Petfinder can’t be queried from the server, so listings load in the browser. The shared cache lets the first logged-in editor’s visit save a copy here that all visitors then read — fewer calls to Petfinder, faster loads.', 'purrfect-match' ) . '</p>';
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
				$min = in_array( $key, array( 'per_page', 'limit' ), true ) ? 0 : 1;
				$max = ( 'limit' === $key ) ? 1000 : ( ( 'cache_minutes' === $key ) ? 1440 : 100 );
				printf(
					'<input type="number" min="' . (int) $min . '" max="' . (int) $max . '" step="1" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
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

		// Numbers. limit 0 = "all" (fetch everything, bounded by a safety ceiling).
		$out['limit'] = isset( $input['limit'] ) ? min( 1000, absint( $input['limit'] ) ) : $defaults['limit'];

		// Per page: 0 is allowed and means "show all at once".
		$out['per_page'] = isset( $input['per_page'] ) ? min( 100, absint( $input['per_page'] ) ) : $defaults['per_page'];

		$columns        = isset( $input['columns'] ) ? absint( $input['columns'] ) : $defaults['columns'];
		$out['columns'] = max( 2, min( 4, $columns ) );

		// Checkboxes.
		$out['hide_breed']   = empty( $input['hide_breed'] ) ? 0 : 1;
		$out['server_cache'] = empty( $input['server_cache'] ) ? 0 : 1;

		// Cache lifetime (minutes).
		$cache_minutes        = isset( $input['cache_minutes'] ) ? absint( $input['cache_minutes'] ) : $defaults['cache_minutes'];
		$out['cache_minutes'] = max( 1, min( 1440, $cache_minutes ) );

		// Text copy.
		$out['title']    = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : $defaults['title'] );
		$out['eyebrow']  = sanitize_text_field( isset( $input['eyebrow'] ) ? $input['eyebrow'] : $defaults['eyebrow'] );
		$out['subtitle'] = sanitize_text_field( isset( $input['subtitle'] ) ? $input['subtitle'] : $defaults['subtitle'] );
		$out['org_name'] = sanitize_text_field( isset( $input['org_name'] ) ? $input['org_name'] : $defaults['org_name'] );

		// Brand color (hex).
		$brand         = isset( $input['brand'] ) ? sanitize_text_field( $input['brand'] ) : $defaults['brand'];
		$out['brand']  = preg_match( '/^#[0-9a-fA-F]{6}$/', $brand ) ? $brand : $defaults['brand'];

		// URLs.
		$out['org_website']          = esc_url_raw( isset( $input['org_website'] ) ? $input['org_website'] : $defaults['org_website'] );
		$out['adoption_form_url']    = esc_url_raw( isset( $input['adoption_form_url'] ) ? $input['adoption_form_url'] : $defaults['adoption_form_url'] );
		$out['adoptapet_url']        = esc_url_raw( isset( $input['adoptapet_url'] ) ? $input['adoptapet_url'] : $defaults['adoptapet_url'] );
		$out['petfinder_member_url'] = esc_url_raw( isset( $input['petfinder_member_url'] ) ? $input['petfinder_member_url'] : $defaults['petfinder_member_url'] );
		$out['api_base']             = esc_url_raw( isset( $input['api_base'] ) ? $input['api_base'] : $defaults['api_base'] );
		$out['s3_url']               = esc_url_raw( isset( $input['s3_url'] ) ? $input['s3_url'] : $defaults['s3_url'] );
		$out['petfinder_url']        = esc_url_raw( isset( $input['petfinder_url'] ) ? $input['petfinder_url'] : $defaults['petfinder_url'] );

		// Reset the in-process cache so the new values are used immediately.
		self::$cache = null;

		return $out;
	}

	/**
	 * Render each registered settings section wrapped in its own card, instead
	 * of the default flat Settings API output. This is robust regardless of how
	 * much intro markup a section prints.
	 *
	 * @return void
	 */
	protected function render_sections_as_cards() {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ self::PAGE ] ) ) {
			return;
		}

		foreach ( (array) $wp_settings_sections[ self::PAGE ] as $section ) {
			echo '<section class="pm-card-section">';

			if ( ! empty( $section['title'] ) ) {
				echo '<h2 class="pm-card-section-title">' . esc_html( $section['title'] ) . '</h2>';
			}

			if ( ! empty( $section['callback'] ) ) {
				echo '<div class="pm-card-section-intro">';
				call_user_func( $section['callback'], $section );
				echo '</div>';
			}

			if ( isset( $wp_settings_fields[ self::PAGE ][ $section['id'] ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( self::PAGE, $section['id'] );
				echo '</table>';
			}

			echo '</section>';
		}
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

		$options = self::get_options();
		$brand   = preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $options['brand'] ) ? $options['brand'] : '#e93396';
		$configured = '' !== trim( (string) $options['organization'] );
		?>
		<div class="wrap pm-admin" style="--pm-admin-brand: <?php echo esc_attr( $brand ); ?>;">

			<div class="pm-admin-hero">
				<h1>
					<span aria-hidden="true">🐾</span>
					<?php esc_html_e( 'Purrfect Match', 'purrfect-match' ); ?>
					<span class="pm-ver">v<?php echo esc_html( PURRFECT_MATCH_VERSION ); ?></span>
				</h1>
				<p><?php esc_html_e( 'Show your shelter’s adoptable Petfinder pets in a beautiful, filterable grid — no API key required.', 'purrfect-match' ); ?></p>
			</div>

			<div class="pm-admin-cols">
				<div class="pm-admin-main">
					<form action="options.php" method="post">
						<?php
						settings_fields( self::PAGE );
						$this->render_sections_as_cards();
						submit_button( __( 'Save changes', 'purrfect-match' ) );
						?>
					</form>
				</div>

				<aside class="pm-admin-side">
					<div class="pm-admin-card">
						<h3><?php esc_html_e( 'Shortcode', 'purrfect-match' ); ?></h3>
						<p><?php esc_html_e( 'Paste this into any page or post:', 'purrfect-match' ); ?></p>
						<div class="pm-shortcode">
							<code>[purrfect_match]</code>
							<button type="button" class="pm-copy" data-clipboard="[purrfect_match]"><?php esc_html_e( 'Copy', 'purrfect-match' ); ?></button>
						</div>
					</div>

					<div class="pm-admin-card">
						<h3><?php esc_html_e( 'Getting started', 'purrfect-match' ); ?></h3>
						<ol class="pm-admin-steps">
							<li>
								<?php
								if ( $configured ) {
									esc_html_e( 'Organization ID is set ✓', 'purrfect-match' );
								} else {
									esc_html_e( 'Enter your Petfinder organization ID (e.g. FL1629).', 'purrfect-match' );
								}
								?>
							</li>
							<li><?php esc_html_e( 'Adjust the look and filters to taste.', 'purrfect-match' ); ?></li>
							<li><?php esc_html_e( 'Add the shortcode to a page.', 'purrfect-match' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'Tip: leave “Maximum pets to load” at 0 to always show every pet.', 'purrfect-match' ); ?></p>
					</div>

					<div class="pm-admin-card pm-admin-credit">
						<?php
						printf(
							/* translators: %s: author link. */
							esc_html__( 'Made with 🐾 by %s', 'purrfect-match' ),
							'<a href="https://www.andrewmayes.com/" target="_blank" rel="noopener noreferrer">Andrew Mayes</a>'
						);
						?>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}
}
