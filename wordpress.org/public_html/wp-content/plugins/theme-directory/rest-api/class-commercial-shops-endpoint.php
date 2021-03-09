<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Query;

class Commercial_Shops_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'shops' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'commercial-shops', $args );
		register_rest_route( 'themes/1.1', 'commercial-shops', $args );
		register_rest_route( 'themes/1.2', 'commercial-shops', $args );
	}

	/**
	 * Endpoint to handle get_commercial_shops API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function shops( $request ) {
		$theme_shops = new WP_Query( array(
			'post_type'      => 'theme_shop',
			'posts_per_page' => -1,
			'orderby'        => 'rand(' . gmdate('YmdH') . ')',
		) );

		$shops = [];

		while ( $theme_shops->have_posts() ) {
			$theme_shops->the_post();

			$shops[] = (object)[
				'shop'  => get_the_title(),
				'slug'  => sanitize_title( get_the_title() ),
				'haiku' => get_the_content(),
				'image' => post_custom( 'image_url' ) ?: sprintf( '//s0.wp.com/mshots/v1/%s?w=572', urlencode( post_custom( 'url' ) ) ),
				'url'   => post_custom( 'url' ),
			];
		}

		return (object) compact( 'shops' );
	}

}
new Commercial_Shops_Endpoint();
