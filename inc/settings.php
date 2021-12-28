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
 * Add headless settings page link.
 *
 * @author WebDevStudios
 * @since NEXT
 */
function add_settings_link() {
	add_options_page(
		esc_html__( 'Headless Config', 'wds-headless-core' ),
		esc_html__( 'Headless Config', 'wds-headless-core' ),
		'edit_posts',
		'headless-config',
		__NAMESPACE__ . '\display_settings_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_link' );

/**
 * Display headless settings page.
 *
 * @author WebDevStudios
 * @since NEXT
 */
function display_settings_page() {
	if ( ! defined( 'WDS_HEADLESS_CORE_OPTION_NAME' ) ) {
		return;
	}
	?>

	<div class="wrap">
		<h2><?php esc_html_e( 'Headless Config', 'wds-headless-core' ); ?></h2>

		<div>
			<form method="post" action="options.php" enctype="multipart/form-data">

				<?php
					settings_fields( WDS_HEADLESS_CORE_OPTION_NAME );
					do_settings_sections( 'headless-config' );
					submit_button();
				?>

			</form>
		</div>
	</div>

	<?php
}

/** Register headless settings fields.
 *
 * @author WebDevStudios
 * @since NEXT
 */
function add_settings_fields() {
	// Custom page options.
	add_settings_section(
		'pages',
		esc_html__( 'Custom Page Options', 'wds-headless-core' ),
		null,
		'headless-config'
	);

	// Error 404 page.
	add_settings_field(
		'error_404_page',
		esc_html__( '404 Page', 'wds-headless-core' ),
		__NAMESPACE__ . '\display_error_404_page',
		'headless-config',
		'pages'
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\add_settings_fields' );

/** Display Error 404 Page input.
 *
 * @author WebDevStudios
 * @since NEXT
 */
function display_error_404_page() {
	if ( ! defined( 'WDS_HEADLESS_CORE_OPTION_NAME' ) ) {
		return;
	}

	$field_id      = 'error_404_page';
	$options       = get_option( WDS_HEADLESS_CORE_OPTION_NAME );
	$selected_page = $options[ $field_id ] ?? '';
	$pages         = get_posts(
		[
			'numberposts' => -1,
			'post_type'   => 'page',
			'post_status' => 'publish',
		]
	);
	?>

	<div>
	<p style="margin: 0 0 1rem 0; font-style: italic;"><?php esc_html_e( 'Optional. Select a custom 404 page. The content entered on this page will appear on the headless frontend.', 'wds-headless-core' ); ?></p>
	<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>">
		<option><?php esc_html_e( '-- Select page --', 'wds-headless-core' ); ?></option>

		<?php foreach ( $pages as $page ) : ?>

			<option value="<?php echo esc_attr( $page->ID ); ?>" <?php echo esc_html( $selected_page === $page->ID ? 'checked="checked"' : '' ); ?>><?php echo esc_attr( $page->post_title ); ?></option>

		<?php endforeach; ?>

	</select>
	</div>

	 <?php
}

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
