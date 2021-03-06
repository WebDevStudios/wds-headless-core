<?php
/**
 * WP GraphQL settings.
 *
 * @see https://wordpress.org/plugins/wp-graphql/
 * @author WebDevStudios
 * @package WDS_Headless_Core
 * @since 1.0.0
 */

namespace WDS_Headless_Core;

/**
 * Add query to GraphQL to retrieve homepage settings.
 *
 * @author WebDevStudios
 * @since 1.0.0
 */
function add_homepage_settings_query() {
	register_graphql_object_type(
		'HomepageSettings',
		[
			'description' => esc_html__( 'Front and posts archive page data', 'wds-headless-core' ),
			'fields'      => [
				'frontPage' => [ 'type' => 'Page' ],
				'postsPage' => [ 'type' => 'Page' ],
			],
		]
	);

	register_graphql_field(
		'RootQuery',
		'homepageSettings',
		[
			'type'        => 'HomepageSettings',
			'description' => esc_html__( 'Returns front and posts archive page data', 'wds-headless-core' ),
			'resolve'     => function( $source, array $args, \WPGraphQL\AppContext $context ) {
				global $wpdb;

				// Get homepage settings.
				$settings = $wpdb->get_row(
					"
				SELECT
					(select option_value from {$wpdb->prefix}options where option_name = 'page_for_posts') as 'page_for_posts',
					(select option_value from {$wpdb->prefix}options where option_name = 'page_on_front') as 'page_on_front'
				",
					ARRAY_A
				);

				// Format settings data.
				$settings_data = [];

				foreach ( $settings as $key => $value ) {
					// Get page data.
					$page_data = ! empty( $value ?? 0 ) ? $context->get_loader( 'post' )->load_deferred( intval( $value ) ) : null;

					switch ( $key ) {
						case 'page_for_posts':
							$settings_data['postsPage'] = $page_data;
							break;

						case 'page_on_front':
							$settings_data['frontPage'] = $page_data;
							break;
					}
				}

				return $settings_data;
			},
		]
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\add_homepage_settings_query' );

/**
 * Allow access to additional fields via non-authed GraphQL request.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param  array  $fields     The fields to allow when the data is designated as restricted to the current user.
 * @param  string $model_name Name of the model the filter is currently being executed in.
 * @return array                   Allowed fields.
 */
function graphql_allowed_fields( array $fields, string $model_name ) {
	if ( 'PostTypeObject' !== $model_name ) {
		return $fields;
	}

	// Add label fields.
	$fields[] = 'label';
	$fields[] = 'labels';

	return $fields;
}
add_filter( 'graphql_allowed_fields_on_restricted_type', __NAMESPACE__ . '\graphql_allowed_fields', 10, 6 );

/**
 * Include users without published posts in SQL query.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param array                      $query_args          The query args to be used with the executable query to get data.
 * @param AbstractConnectionResolver $connection_resolver Instance of the connection resolver.
 * @return array
 */
function public_unpublished_users( array $query_args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection_resolver ) {// phpcs:ignore
	if ( $connection_resolver instanceof \WPGraphQL\Data\Connection\UserConnectionResolver ) {
		unset( $query_args['has_published_posts'] );
	}

	return $query_args;
}
add_filter( 'graphql_connection_query_args', __NAMESPACE__ . '\public_unpublished_users', 10, 2 );

/**
 * Make all Users public including in non-authenticated WPGraphQL requests.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param string $visibility The current visibility of a user.
 * @param string $model_name The model name of the user model.
 * @return string
 */
function public_users( string $visibility, string $model_name ) {
	if ( 'UserObject' === $model_name ) {
		$visibility = 'public';
	}

	return $visibility;
}
add_filter( 'graphql_object_visibility', __NAMESPACE__ . '\public_users', 10, 2 );

/**
 * Edit the error messages on user registration.
 *
 * @author WebDevStudios
 * @since 1.0.0
 * @param \WP_Error $errors A WP_Error object containing any errors encountered during registration.
 * @return \WP_Error
 */
function filter_registration_errors( \WP_Error $errors ) {
	if ( ! $errors->has_errors() ) {
		return $errors;
	}

	$new_errors_obj = new \WP_Error();
	foreach ( $errors->get_error_codes() as $error_code ) {
		switch ( $error_code ) {
			case 'empty_username':
				$error_msg = esc_html__( 'Please enter a username.', 'wds-headless-core' );
				break;
			case 'invalid_username':
				$error_msg = esc_html__( 'This username is invalid because it uses illegal characters. Please enter a valid username.', 'wds-headless-core' );
				break;
			case 'username_exists':
				$error_msg = esc_html__( 'This username is already registered. Please choose another one.', 'wds-headless-core' );
				break;
			case 'empty_email':
				$error_msg = esc_html__( 'Please enter your email address.', 'wds-headless-core' );
				break;
			case 'invalid_email':
				$error_msg = esc_html__( 'Please enter a valid email address.', 'wds-headless-core' );
				break;
			case 'email_exists':
				$error_msg = esc_html__( 'This username is already registered. Please choose another one.', 'wds-headless-core' );
				break;
			default:
				$error_msg = esc_html__( 'Registration failed. Please contact the admin.', 'wds-headless-core' );
				break;
		}
		$new_errors_obj->add( $error_code, $error_msg );
	}
	return $new_errors_obj;
}

/**
 * Add hooks that should only occur in the context of a GraphQL Request.
 *
 * @author WebDevStudios
 * @since 1.0.0
 */
function graphql_request_init() {
	add_filter( 'registration_errors', __NAMESPACE__ . '\filter_registration_errors' );
}
add_action( 'init_graphql_request', __NAMESPACE__ . '\graphql_request_init' );

/**
 * Register custom headless settings with GraphQL.
 * Mirrors GraphQL schema of original ACF headless settings to avoid breaking frontend queries.
 *
 * @author WebDevStudios
 * @since 2.0.0
 * @param object $type_registry The GraphQL type registry.
 */
function register_headless_settings( $type_registry ) {
	if (
		! function_exists( 'register_graphql_object_type' ) ||
		! function_exists( 'register_graphql_field' ) ||
		! function_exists( 'register_graphql_union_type' ) ||
		! defined( 'WDS_HEADLESS_CORE_OPTION_NAME' ) ) {
		return;
	}

	register_graphql_union_type(
		'PageUnion',
		[
			'typeNames'   => [ 'Page' ],
			'resolveType' => function( $post ) use ( $type_registry ) {
				$post_type        = $post->post_type;
				$post_type_object = get_post_type_object( $post_type );
				$graphql_name     = $post_type_object->graphql_single_name ?? null;

				return $type_registry->get_type( $graphql_name );
			},
		]
	);

	register_graphql_object_type(
		'AdditionalSettings',
		[
			'description' => esc_html__( 'Headless config settings', 'wds-headless-core' ),
			'fields'      => [
				'error404Page' => [ 'type' => 'PageUnion' ],
			],
		]
	);

	register_graphql_object_type(
		'HeadlessConfig',
		[
			'description' => esc_html__( 'Headless config', 'wds-headless-core' ),
			'fields'      => [
				'additionalSettings' => [ 'type' => 'AdditionalSettings' ],
			],
		]
	);

	register_graphql_field(
		'RootQuery',
		'headlessConfig',
		[
			'type'        => 'HeadlessConfig',
			'description' => esc_html__( 'Connection between the RootQuery type and the headlessConfig type', 'wds-headless-core' ),
			'resolve'     => function( $source, array $args, \WPGraphQL\AppContext $context ) {
				$options           = get_option( WDS_HEADLESS_CORE_OPTION_NAME );
				$error_404_page_id = $options['error_404_page'];
				$error_404_page_id = is_nan( $error_404_page_id ) ? null : absint( $error_404_page_id );
				$error_404_page    = $context->get_loader( 'post' )->load_deferred( $error_404_page_id );

				return [
					'additionalSettings' => [
						'error404Page' => $error_404_page,
					],
				];
			},
		]
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\register_headless_settings' );

/**
 * Register Gravatar URL with GraphQL.
 *
 * @author WebDevStudios
 * @since 2.1.0
 */
function register_gravatar_url() {
	register_graphql_field(
		'Commenter',
		'gravatarUrl',
		[
			'type'        => 'String',
			'description' => esc_html__( 'Adds a Gravatar URL to the Comment Author', 'wds-headless-core' ),
			'resolve'     => function( $comment_author, $args, $context, $info ) {
				$object = null;

				// Check if dealing with user or guest commenter.
				if ( $comment_author->__get( 'userId' ) ) { // User.

					// Get the user ID.
					$user_id = $comment_author->__get( 'userId' );

					// Fetch the user.
					$object = get_user_by( 'ID', $user_id );
				} else { // Guest commenter.

					// Get the comment ID.
					$comment_id = $comment_author->__get( 'databaseId' );

					// Fetch the comment.
					$object = get_comment( $comment_id );
				}

				// Set avatar args.
				$args = [
					'size' => '150',
				];

				// Fetch the gravatar url.
				$gravatar_url = get_avatar_url( $object, $args );

				// In case something goes wrong, fallback to the mystery person avatar.
				if ( false === $gravatar_url ) {
					$gravatar_url = "https://secure.gravatar.com/avatar/5cf23001579ee91aff54a2dcd6e5acc9?s={$args['size']}&d=mm&r=g";
				}

				return $gravatar_url;
			},
		]
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\register_gravatar_url' );
