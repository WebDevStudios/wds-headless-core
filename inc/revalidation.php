<?php
/**
 * Revalidation functionality.
 *
 * @author WebDevStudios
 * @package WDS_Headless_Core
 * @since 2.1.3
 */
namespace WDS_Headless_Core;

use \WP_Post;

/**
 * Flush the frontend cache when a post is updated.
 *
 * This function will fire anytime a post is updated.
 * Including: the post status, comments, meta, terms, etc.
 *
 * @see https://developer.wordpress.org/reference/hooks/edit_post/
 * @see https://nextjs.org/docs/basic-features/data-fetching/incremental-static-regeneration#using-on-demand-revalidation
 * @since 2.1.3
 * @author WebDevStudios
 * @param int $post_ID  The post ID.
 * @param WP_Post $post The post object.
 */
function on_demand_revalidation( $post_ID, WP_Post $post ) {

	// These constsants are required. If they're not here, bail...
	if ( ! defined( 'HEADLESS_FRONTEND_URL' ) || ! defined( 'PREVIEW_SECRET_TOKEN' ) ) {
		error_log( 'Missing constants for on demand revalidation.' );
		return;
	}

	// No post ID? Bail...
	if ( ! $post_ID ) {
		error_log( 'Missing post ID for on demand revalidation.' );
		return;
	}

	// Define the post slug and remove frontend URL.
	$slug = str_replace( HEADLESS_FRONTEND_URL, '', get_the_permalink( $post_ID ) );

	// No slug? Bail...
	if ( ! $slug ) {
		error_log( 'Missing post slug for on demand revalidation.' );
		return;
	}

	// Send POST request to the frontend and revalidate the cache.
	$response = wp_remote_post(
		HEADLESS_FRONTEND_URL . 'api/wordpress/revalidate',
		[
			'blocking' => true,
			'headers'  => [
				'Content-Type' => 'application/json',
				'Expect'       => '',
			],
			'body' => wp_json_encode(
				[
					'secret' => PREVIEW_SECRET_TOKEN,
					'slug'   => "/${slug}",
				]
			),
		]
	);

	// Check response code.
	$response_code = wp_remote_retrieve_response_code( $response );

	// If there is an error, log it.
	if ( $response_code !== 200 ) {
		error_log( 'Failed to revalidate cache for post ' . $slug . '.' );
	}
}
add_action( 'edit_post', __NAMESPACE__ . '\on_demand_revalidation', 10, 3 );
