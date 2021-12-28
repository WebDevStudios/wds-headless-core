<?php
/**
 * Settings functionality.
 *
 * @todo Move settings out of ACF.
 * @see https://github.com/WebDevStudios/wds-headless-wordpress/issues/10
 * @author WebDevStudios
 * @package WDS_Headless_Core
 * @since 1.0.0
 */

namespace WDS_Headless_Core;

/**
 * Register custom headless settings.
 *
 * @author WebDevStudios
 * @since NEXT
 */
function register_settings() {
	if ( ! defined( 'WDS_HEADLESS_CORE_OPTION_NAME' ) ) {
		return;
	}

	register_setting(
		WDS_HEADLESS_CORE_OPTION_NAME,
		'additional_settings',
		[
			'description'       => esc_html__( 'Headless Config Settings', 'wds-headless-core' ),
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
			'type'              => 'array',
		]
	);

}
add_action( 'init', __NAMESPACE__ . '\register_settings' );
/**
 * Customize ACF JSON loader.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param array $paths ACF JSON paths.
 * @return array       Filtered ACF JSON paths.
 */
function load_json( array $paths ) : array {
	return array_merge(
		$paths,
		[
			WDS_HEADLESS_CORE_PLUGIN_DIR . 'acf-json',
		]
	);
}
add_filter( 'acf/settings/load_json', __NAMESPACE__ . '\load_json', 10 );

if ( ! class_exists( 'acf_pro' ) ) {
	return;
}

/**
 * Register custom Options page.
 *
 * Note: This is an ACF Pro only feature!
 *
 * @see https://www.advancedcustomfields.com/resources/acf_add_options_page/
 * @author WebDevStudios
 * @since 1.0.0
 */
function acf_options_page() {
	acf_add_options_page(
		[
			'page_title'      => esc_html__( 'Headless Config', 'wds-headless-core' ),
			'menu_title'      => esc_html__( 'Headless Config', 'wds-headless-core' ),
			'menu_slug'       => 'headless-config',
			'capability'      => 'edit_posts',
			'icon_url'        => 'dashicons-admin-generic',
			'redirect'        => false,
			'show_in_graphql' => true,
		]
	);
}
add_action( 'acf/init', __NAMESPACE__ . '\acf_options_page' );
