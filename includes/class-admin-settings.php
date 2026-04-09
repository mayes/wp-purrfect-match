<?php

namespace PurrfectMatch;

defined( 'ABSPATH' ) || exit;

class Admin_Settings {

	private Petfinder_Client $api_client;

	public function __construct( Petfinder_Client $api_client ) {
		$this->api_client = $api_client;
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_menu_page(): void {
		add_menu_page(
			__( 'Purrfect Match', 'purrfect-match' ),
			__( 'Purrfect Match', 'purrfect-match' ),
			'manage_options',
			'purrfect-match',
			array( $this, 'render_settings_page' ),
			'dashicons-heart',
			80
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_purrfect-match' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'purrfect-match-admin',
			PURRFECT_MATCH_URL . 'assets/css/purrfect-match-admin.css',
			array(),
			PURRFECT_MATCH_VERSION
		);
		wp_enqueue_script(
			'purrfect-match-admin',
			PURRFECT_MATCH_URL . 'assets/js/purrfect-match-admin.js',
			array(),
			PURRFECT_MATCH_VERSION,
			true
		);
		wp_localize_script( 'purrfect-match-admin', 'purrfectMatchAdmin', array(
			'restUrl' => rest_url( 'purrfect-match/v1/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'testing'    => __( 'Testing...', 'purrfect-match' ),
				'success'    => __( 'Connection successful!', 'purrfect-match' ),
				'error'      => __( 'Connection failed: ', 'purrfect-match' ),
				'flushing'   => __( 'Clearing...', 'purrfect-match' ),
				'flushed'    => __( 'Cache cleared!', 'purrfect-match' ),
				'flushError' => __( 'Failed to clear cache.', 'purrfect-match' ),
			),
		) );
	}

	public function register_settings(): void {
		register_setting( 'purrfect_match', 'purrfect_match_options', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_options' ),
			'default'           => $this->get_defaults(),
		) );

		// API Configuration section.
		add_settings_section( 'pm_api', __( 'API Configuration', 'purrfect-match' ), array( $this, 'render_api_section' ), 'purrfect-match' );
		add_settings_field( 'api_key', __( 'Petfinder API Key', 'purrfect-match' ), array( $this, 'render_text_field' ), 'purrfect-match', 'pm_api', array( 'field' => 'api_key' ) );
		add_settings_field( 'api_secret', __( 'Petfinder API Secret', 'purrfect-match' ), array( $this, 'render_password_field' ), 'purrfect-match', 'pm_api', array( 'field' => 'api_secret' ) );
		add_settings_field( 'organization_id', __( 'Organization ID', 'purrfect-match' ), array( $this, 'render_text_field' ), 'purrfect-match', 'pm_api', array( 'field' => 'organization_id', 'description' => __( 'Your Petfinder organization ID (e.g., NJ333).', 'purrfect-match' ) ) );

		// Display Settings section.
		add_settings_section( 'pm_display', __( 'Display Settings', 'purrfect-match' ), null, 'purrfect-match' );
		add_settings_field( 'default_layout', __( 'Default Layout', 'purrfect-match' ), array( $this, 'render_select_field' ), 'purrfect-match', 'pm_display', array(
			'field'   => 'default_layout',
			'options' => array( 'grid' => __( 'Grid', 'purrfect-match' ), 'list' => __( 'List', 'purrfect-match' ) ),
		) );
		add_settings_field( 'pets_per_page', __( 'Pets Per Page', 'purrfect-match' ), array( $this, 'render_number_field' ), 'purrfect-match', 'pm_display', array( 'field' => 'pets_per_page', 'min' => 1, 'max' => 100 ) );
		add_settings_field( 'primary_color', __( 'Primary Color', 'purrfect-match' ), array( $this, 'render_color_field' ), 'purrfect-match', 'pm_display', array( 'field' => 'primary_color' ) );
		add_settings_field( 'card_style', __( 'Card Style', 'purrfect-match' ), array( $this, 'render_select_field' ), 'purrfect-match', 'pm_display', array(
			'field'   => 'card_style',
			'options' => array( 'rounded' => __( 'Rounded', 'purrfect-match' ), 'square' => __( 'Square', 'purrfect-match' ) ),
		) );

		// Feature Toggles section.
		add_settings_section( 'pm_features', __( 'Features', 'purrfect-match' ), null, 'purrfect-match' );
		add_settings_field( 'show_filters', __( 'Show Filters', 'purrfect-match' ), array( $this, 'render_checkbox_field' ), 'purrfect-match', 'pm_features', array( 'field' => 'show_filters' ) );
		add_settings_field( 'show_search', __( 'Show Search', 'purrfect-match' ), array( $this, 'render_checkbox_field' ), 'purrfect-match', 'pm_features', array( 'field' => 'show_search' ) );
		add_settings_field( 'show_favorites', __( 'Enable Favorites', 'purrfect-match' ), array( $this, 'render_checkbox_field' ), 'purrfect-match', 'pm_features', array( 'field' => 'show_favorites' ) );
		add_settings_field( 'show_sharing', __( 'Enable Social Sharing', 'purrfect-match' ), array( $this, 'render_checkbox_field' ), 'purrfect-match', 'pm_features', array( 'field' => 'show_sharing' ) );

		// Adoption Settings section.
		add_settings_section( 'pm_adoption', __( 'Adoption Settings', 'purrfect-match' ), null, 'purrfect-match' );
		add_settings_field( 'adoption_url', __( 'Adoption Application URL', 'purrfect-match' ), array( $this, 'render_url_field' ), 'purrfect-match', 'pm_adoption', array( 'field' => 'adoption_url', 'description' => __( 'Link to your adoption application form.', 'purrfect-match' ) ) );
		add_settings_field( 'adoption_text', __( 'Adopt Button Text', 'purrfect-match' ), array( $this, 'render_text_field' ), 'purrfect-match', 'pm_adoption', array( 'field' => 'adoption_text' ) );

		// Cache section.
		add_settings_section( 'pm_cache', __( 'Cache', 'purrfect-match' ), null, 'purrfect-match' );
		add_settings_field( 'cache_ttl', __( 'Cache Duration (seconds)', 'purrfect-match' ), array( $this, 'render_number_field' ), 'purrfect-match', 'pm_cache', array( 'field' => 'cache_ttl', 'min' => 60, 'max' => 86400, 'description' => __( 'How long to cache API responses. Default: 3600 (1 hour).', 'purrfect-match' ) ) );
	}

	public function sanitize_options( array $input ): array {
		$old     = get_option( 'purrfect_match_options', $this->get_defaults() );
		$output  = array();

		$output['api_key']         = sanitize_text_field( $input['api_key'] ?? '' );
		$output['api_secret']      = ! empty( $input['api_secret'] ) ? sanitize_text_field( $input['api_secret'] ) : ( $old['api_secret'] ?? '' );
		$output['organization_id'] = sanitize_text_field( $input['organization_id'] ?? '' );
		$output['cache_ttl']       = absint( $input['cache_ttl'] ?? 3600 );
		$output['pets_per_page']   = min( 100, max( 1, absint( $input['pets_per_page'] ?? 12 ) ) );
		$output['default_layout']  = in_array( $input['default_layout'] ?? '', array( 'grid', 'list' ), true ) ? $input['default_layout'] : 'grid';
		$output['show_filters']    = ! empty( $input['show_filters'] );
		$output['show_search']     = ! empty( $input['show_search'] );
		$output['show_favorites']  = ! empty( $input['show_favorites'] );
		$output['show_sharing']    = ! empty( $input['show_sharing'] );
		$output['adoption_url']    = esc_url_raw( $input['adoption_url'] ?? '' );
		$output['adoption_text']   = sanitize_text_field( $input['adoption_text'] ?? 'Adopt Me!' );
		$output['primary_color']   = sanitize_hex_color( $input['primary_color'] ?? '#6C63FF' ) ?: '#6C63FF';
		$output['card_style']      = in_array( $input['card_style'] ?? '', array( 'rounded', 'square' ), true ) ? $input['card_style'] : 'rounded';

		return $output;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		?>
		<div class="wrap pm-admin">
			<h1><?php esc_html_e( 'Purrfect Match Settings', 'purrfect-match' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'purrfect_match' );
				do_settings_sections( 'purrfect-match' );
				submit_button();
				?>
			</form>

			<div class="pm-admin__actions">
				<h2><?php esc_html_e( 'Tools', 'purrfect-match' ); ?></h2>
				<p>
					<button type="button" class="button" id="pm-test-api">
						<?php esc_html_e( 'Test API Connection', 'purrfect-match' ); ?>
					</button>
					<span id="pm-test-result" class="pm-admin__result"></span>
				</p>
				<p>
					<button type="button" class="button" id="pm-flush-cache">
						<?php esc_html_e( 'Clear Pet Cache', 'purrfect-match' ); ?>
					</button>
					<span id="pm-flush-result" class="pm-admin__result"></span>
				</p>
			</div>

			<div class="pm-admin__usage">
				<h2><?php esc_html_e( 'Usage', 'purrfect-match' ); ?></h2>
				<p><?php esc_html_e( 'Use the shortcode on any page or post:', 'purrfect-match' ); ?></p>
				<code>[purrfect_match]</code>
				<p><?php esc_html_e( 'Or use the Purrfect Match block in the block editor.', 'purrfect-match' ); ?></p>
				<h3><?php esc_html_e( 'Shortcode Attributes', 'purrfect-match' ); ?></h3>
				<code>[purrfect_match layout="grid" per_page="12" columns="3" breed="" age="" gender="" size="" show_filters="true" show_search="true"]</code>
			</div>
		</div>
		<?php
	}

	public function render_api_section(): void {
		printf(
			'<p>%s <a href="https://www.petfinder.com/developers/" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'Enter your Petfinder API v2 credentials.', 'purrfect-match' ),
			esc_html__( 'Get your API key here.', 'purrfect-match' )
		);
	}

	public function render_text_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$value   = $options[ $args['field'] ] ?? '';
		printf(
			'<input type="text" name="purrfect_match_options[%s]" value="%s" class="regular-text" />',
			esc_attr( $args['field'] ),
			esc_attr( $value )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function render_password_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$has     = ! empty( $options[ $args['field'] ] );
		printf(
			'<input type="password" name="purrfect_match_options[%s]" value="" class="regular-text" placeholder="%s" />',
			esc_attr( $args['field'] ),
			$has ? esc_attr__( 'Leave blank to keep current secret', 'purrfect-match' ) : ''
		);
	}

	public function render_url_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$value   = $options[ $args['field'] ] ?? '';
		printf(
			'<input type="url" name="purrfect_match_options[%s]" value="%s" class="regular-text" />',
			esc_attr( $args['field'] ),
			esc_attr( $value )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function render_number_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$value   = $options[ $args['field'] ] ?? '';
		printf(
			'<input type="number" name="purrfect_match_options[%s]" value="%s" class="small-text" min="%d" max="%d" />',
			esc_attr( $args['field'] ),
			esc_attr( $value ),
			$args['min'] ?? 0,
			$args['max'] ?? 999999
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function render_select_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$value   = $options[ $args['field'] ] ?? '';
		printf( '<select name="purrfect_match_options[%s]">', esc_attr( $args['field'] ) );
		foreach ( $args['options'] as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function render_checkbox_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$checked = ! empty( $options[ $args['field'] ] );
		printf(
			'<input type="checkbox" name="purrfect_match_options[%s]" value="1" %s />',
			esc_attr( $args['field'] ),
			checked( $checked, true, false )
		);
	}

	public function render_color_field( array $args ): void {
		$options = get_option( 'purrfect_match_options', $this->get_defaults() );
		$value   = $options[ $args['field'] ] ?? '#6C63FF';
		printf(
			'<input type="color" name="purrfect_match_options[%s]" value="%s" />',
			esc_attr( $args['field'] ),
			esc_attr( $value )
		);
	}

	private function get_defaults(): array {
		return array(
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
	}
}
