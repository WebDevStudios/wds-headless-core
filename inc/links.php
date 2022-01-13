<?php
/**
 * Link/redirection functionality.
 *
 * @author WebDevStudios
 * @package WDS_Headless_Core
 * @since 1.0.0
 */

namespace WDS_Headless_Core;

use \WP_Post;
use \WP_REST_Response;

/**
 * Customize the preview button in the WordPress admin to point to the headless client.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param  string  $link WordPress preview link.
 * @param  WP_Post $post Current post object.
 * @return string        The headless WordPress preview link.
 */
function set_headless_preview_link( string $link, WP_Post $post ) {
	if ( ! defined( 'HEADLESS_FRONTEND_URL' ) ) {
		return $link;
	}

	$base_url = HEADLESS_FRONTEND_URL;
	$slug     = strlen( $post->post_name ) > 0 ? $post->post_name : sanitize_title( $post->post_title );

	// Get GraphQL single name.
	$post_type = get_post_type_object( $post->post_type )->graphql_single_name ?? $post->post_type;

	// Preview link will have format: <domain>/api/preview?name=<slug>&id=<post-id>&post_type=<postType>&token=<preview-token>.
	return add_query_arg(
		[
			'name'      => $slug,
			'id'        => $post->ID,
			'post_type' => $post_type,
			'token'     => defined( 'PREVIEW_SECRET_TOKEN' ) ? PREVIEW_SECRET_TOKEN : '',
		],
		"{$base_url}api/preview"
	);
}
add_filter( 'preview_post_link', __NAMESPACE__ . '\set_headless_preview_link', 10, 2 );

/**
 * Customize WP home URL to point to frontend.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param  string $url    Complete home URL, including path.
 * @param  string $path   Path relative to home URL.
 * @param  string $scheme Context for home URL.
 * @return string         Frontend home URL.
 */
function set_headless_home_url( string $url, string $path, $scheme = null ) {
	if ( ! defined( 'HEADLESS_FRONTEND_URL' ) ) {
		return $url;
	}

	// Don't redirect REST requests.
	if ( 'rest' === $scheme ) {
		return $url;
	}

	// Don't redirect unless in WP admin.
	if ( ! is_admin() ) {
		return $url;
	}

	$base_url = HEADLESS_FRONTEND_URL;

	if ( ! $path ) {
		return $base_url;
	}

	// Remove excess slash from beginning of path.
	$path = ltrim( $path, '/' );

	return "{$base_url}{$path}";
}
add_filter( 'home_url', __NAMESPACE__ . '\set_headless_home_url', 10, 3 );

/**
 * Customize the REST preview link to point to the headless client.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param  WP_REST_Response $response Response object.
 * @param  WP_Post          $post     Current post object.
 * @return WP_REST_Response           Response object.
 */
function set_headless_rest_preview_link( WP_REST_Response $response, WP_Post $post ) {
	if ( 'draft' === $post->post_status ) {

		// Manually call preview filter for draft posts.
		$response->data['link'] = get_preview_post_link( $post );
	} elseif ( 'publish' === $post->post_status ) {

		// Override view link for published posts.
		if ( ! defined( 'HEADLESS_FRONTEND_URL' ) ) {
			return $response;
		}

		$base_url = HEADLESS_FRONTEND_URL;

		// Handle special-case pages.
		$options    = get_option( WDS_HEADLESS_CORE_OPTION_NAME );
		$error_page = is_array( $options ) ? $options['error_404_page'] : null;

		// Remove excess slash from end of frontend domain.
		$base_url = rtrim( $base_url, '/' );

		if ( $error_page && $post->ID === $error_page->ID ) {

			// Return 404 URL for error page.
			$response->data['link'] = "{$base_url}/404";
		} else {
			$permalink = get_permalink( $post );
			$site_url  = get_site_url();

			// Replace site URL if present.
			if ( false !== stristr( $permalink, $site_url ) ) {
				$permalink = str_ireplace( $site_url, $base_url, $permalink );
			}

			// Return URL based on post name.
			$response->data['link'] = $permalink;
		}
	}

	return $response;
}
add_filter( 'rest_prepare_page', __NAMESPACE__ . '\set_headless_rest_preview_link', 10, 2 );
add_filter( 'rest_prepare_post', __NAMESPACE__ . '\set_headless_rest_preview_link', 10, 2 );

/**
 * Override links within post content on save to point to FE.
 *
 * @author WebDevStudios
 * @since 1.1.0
 * @param int $post_id Post ID.
 */
function override_post_links( $post_id ) {

	// Unhook function to avoid infinite loop.
	remove_action( 'save_post', __NAMESPACE__ . '\override_post_links' );

	$post = get_post( $post_id );

	if ( ! $post || ! defined( 'HEADLESS_FRONTEND_URL' ) ) {
		return;
	}

	$post_content   = $post->post_content;
	$backend_domain = get_site_url();

	// Check if post content contains WP links.
	if ( false === stripos( $post_content, $backend_domain ) ) {
		return;
	}

	$frontend_domain  = HEADLESS_FRONTEND_URL;
	$new_post_content = $post_content;

	// Remove excess slash from end of frontend domain.
	$frontend_domain = rtrim( $frontend_domain, '/' );

	// Replace WP domain with FE domain.
	$new_post_content = str_ireplace( $backend_domain, $frontend_domain, $post_content );

	// Revert media links.
	$upload_dir       = wp_upload_dir();
	$upload_dir       = str_ireplace( $backend_domain, '', $upload_dir['baseurl'] );
	$new_post_content = str_ireplace( "{$frontend_domain}{$upload_dir}", "{$backend_domain}{$upload_dir}", $new_post_content );

	// Revert plugin links.
	$plugin_dir       = defined( 'WP_PLUGIN_URL' ) ? WP_PLUGIN_URL : '/wp-content/plugins';
	$plugin_dir       = str_ireplace( $backend_domain, '', $plugin_dir );
	$new_post_content = str_ireplace( "{$frontend_domain}{$plugin_dir}", "{$backend_domain}{$plugin_dir}", $new_post_content );

	// Save post.
	wp_update_post(
		[
			'ID'           => $post_id,
			'post_content' => wp_slash( $new_post_content ),
		]
	);

	// Re-hook function.
	add_action( 'save_post', __NAMESPACE__ . '\override_post_links' );
}
add_action( 'save_post', __NAMESPACE__ . '\override_post_links' );

/**
 * Redirects non-API requests for public URLs to the specified front-end URL.
 *
 * @see https://github.com/wpengine/faustjs/blob/aaad74cd6edac536a1df405552256ca66575c8cd/plugins/wpe-headless/includes/deny-public-access/callbacks.php#L20
 * @author WebDevStudios
 * @since 1.1.0
 * @param object $query The current query.
 */
function deny_public_access( $query ) {
	if (
		defined( 'DOING_CRON' ) ||
		defined( 'REST_REQUEST' ) ||
		is_admin() ||
		is_customize_preview() ||
		( function_exists( 'is_graphql_http_request' ) && is_graphql_http_request() ) || // From https://wordpress.org/plugins/wp-graphql/.
		! empty( $query->query_vars['rest_oauth1'] ) || // From https://oauth1.wp-api.org/.
		! property_exists( $query, 'request' ) ||
		! HEADLESS_FRONTEND_URL
	) {
		return;
	}

	$frontend_uri = trailingslashit( HEADLESS_FRONTEND_URL );

	// Get the request uri with query params.
	$request_uri = home_url( add_query_arg( null, null ) );

	// Return if dealing with upload URL.
	$uploads_path = wp_upload_dir();

	if ( false !== stristr( $request_uri, $uploads_path['baseurl'] ) ) {
		return;
	}

	$response_code = 302;
	$redirect_url  = str_replace( trailingslashit( get_home_url() ), $frontend_uri, $request_uri );

	header( 'X-Redirect-By: WDS Headless Core plugin' ); // For support teams. See https://developer.yoast.com/blog/x-redirect-by-header/.
	header( 'Location: ' . esc_url_raw( $redirect_url ), true, $response_code );
	exit;
}
add_action( 'parse_request', __NAMESPACE__ . '\deny_public_access', 99 );
