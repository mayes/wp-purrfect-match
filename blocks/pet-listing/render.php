<?php
/**
 * Server-side render callback for the Purrfect Match block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$plugin    = PurrfectMatch\Plugin::get_instance();
$shortcode = $plugin->get_shortcode();

echo $shortcode->render( array(
	'layout'       => $attributes['layout'] ?? 'grid',
	'per_page'     => $attributes['perPage'] ?? 12,
	'columns'      => $attributes['columns'] ?? 3,
	'show_filters' => ( $attributes['showFilters'] ?? true ) ? 'true' : 'false',
	'show_search'  => ( $attributes['showSearch'] ?? true ) ? 'true' : 'false',
	'breed'        => $attributes['breed'] ?? '',
	'age'          => $attributes['age'] ?? '',
	'gender'       => $attributes['gender'] ?? '',
	'size'         => $attributes['size'] ?? '',
) );
