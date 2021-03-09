<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

class Tags_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'tags' ),
			'permission_callback' => '__return_true',
			'args'                => [
				'number' => [
					'type'    => 'integer',
					'default' => 0,
					'minimum' => 0,
					'maximum' => 99
				]
			]
		);

		register_rest_route( 'themes/1.0', 'tags', $args );
		register_rest_route( 'themes/1.1', 'tags', $args );
		register_rest_route( 'themes/1.2', 'tags', $args );
	}

	/**
	 * Endpoint to handle tags API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function tags( $request ) {
		$tags = get_tags( array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'hide_empty' => false,
			'number'     => $request['number'],
		) );

		$response = [];

		// Format in the API representation.
		foreach ( $tags as $tag ) {
			$response[ $tag->slug ] = [
				'name'  => $tag->name,
				'slug'  => $tag->slug,
				'count' => $tag->count,
			];
		}

		return $response;
	}

}
new Tags_Endpoint();
