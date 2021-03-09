<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class Query_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => [ $this, 'query' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'page' => [
					'default' => 1,
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 99,
				],
				'per_page' => [
					'default'  => (int) get_option( 'posts_per_page', 12 ),
					'type'     => 'integer',
					'minimum'  => 1,
					'maximum'  => 999,
				],
				'browse' => [
					'type'              => 'string',
					'enum'              => [
						'featured',
						'favorites',
						'popular',
						'new',
					],
					'validate_callback' => function( $value, $request ) {
						if ( 'favorites' === $value && ! $request['favorites_user'] ) {
							return new WP_Error(
								'missing_favorites_user',
								"The 'favorites_user' parameter is missing."
							);
						}

						return true;
					},
				],
				'favorites_user' => [
					'type'              => 'string',
					'minLength'         => 1,
					'validate_callback' => function( $value, $request ) {
						if ( 'favorites' !== $request['browse'] ) {
							return new WP_Error(
								'not_browse_favorites',
								"The 'browse' parameter must be set to 'favorites'."
							);
						}

						return true;
					},
				],
				'tag' => [
					'type' => 'array',
					'items' => [
						'type' => 'string',
					],
					'sanitize_callback' => function( $value ) {
						$value = (array) $value;

						// Replace updated tags.
						$updated_tags = [
							'fixed-width'    => 'fixed-layout',
							'flexible-width' => 'fluid-layout',
						];
						foreach ( $updated_tags as $old => $new ) {
							if ( false !== ( $key = array_search( $old, $value ) ) ) {
								$value[ $key ] = $new;
							}
						}

						return $value;
					}
				],
				'search' => [
					'type' => 'string',
				],
				'theme' => [
					'type' => 'string',
				],
				'author' => [
					'type' => 'string',
				],
				'format' => [
					'type'    => 'string',
					'default' => 'objects',
					'enum'    => [
						'objects',
						'ids',
						'slugs',
					]
				]
			]
		);

		register_rest_route( 'themes/1.0', 'query', $args );
		register_rest_route( 'themes/1.1', 'query', $args );
		register_rest_route( 'themes/1.2', 'query', $args );
	}

	/**
	 * Endpoint to handle query API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function query( $request ) {
		$qv = [
			'post_type'   => 'repopackage',
			'post_status' => [
				'publish'
			]
		];

		$map = [
			// WP_Query var => API Query var
			'paged'          => 'page',
			'posts_per_page' => 'per_page',
			'browse'         => 'browse',
			's'              => 'search',
			'name'           => 'theme',
			'author_name'    => 'author',
			'favorites_user' => 'user',
		];

		foreach ( $map as $wp_var => $api_var ) {
			if ( $request[ $api_var ] ) {
				$qv[ $wp_var ] = $request[ $api_var ];
			}
		}

		if ( $request['tag'] ) {
			$qv['tax_query'] = [ [
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => $request['tag'],
				'operator' => 'AND',
			] ];
		}

		// Query for the IDs only.
		$qv['fields'] = 'ids';
		$wp_query = new WP_Query( $qv );

		// Basic information about the request.
		$response = [
			'info'   => [
				'page'   => max( 1, $request['page'] ),
				'pages'  => max( 1, $wp_query->max_num_pages ),
				'result' => $wp_query->found_posts,
			],
			'themes' => [],
		];

		if ( 'ids' === $request['format'] ) {
			// return the IDs directly.
			$response['themes'] = $wp_query->posts;

		} elseif ( 'slugs' === $request['format'] ) {
			// Convert IDs to slugs.
			$response['themes'] = array_map(
				function( $theme_id ) {
					return get_post( $theme_id )->post_name;
				},
				$wp_query->posts
			);

		} else {
			// As objects - perform an internal API request to fill up the themes.
			$info_api = new Info_Endpoint();
			$request  = new WP_Rest_Request();

			// Fill up the themes lists.
			foreach ( $wp_query->posts as $theme_id ) {
				$request->set_param( 'slug', $theme_id );
				$response['themes'][] = $info_api->info( $request );
			}

		}

		return $response;
	}

}
new Query_Endpoint();
