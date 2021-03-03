<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_REST_Response;

class Info_Endpoint {

	function __construct() {
		/*
		 * These map from api.wordpress.org/themes/info/$version/ to wp-json/themes/$version/info
		 */
		register_rest_route( 'themes/1.0', '/info', array(
			'callback' => array( $this, 'info_10' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'themes/1.1', '/info', array(
			'callback' => array( $this, 'info_11' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'themes/1.2', '/info', array(
			'callback' => array( $this, 'info_12' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Endpoint to handle theme_information API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool true
	 */
	function info_12( $request ) {
		define( 'THEMES_API_VERSION', '1.2' );
	
		return $this->info_11( $request );
	}

	function info_11( $request ) {
		defined( 'THEMES_API_VERSION' ) || define( 'THEMES_API_VERSION', '1.1' );

		$api = wporg_themes_query_api(
			'theme_information',
			$request->get_params(),
			'api_object'
		);

		$response = new WP_REST_Response( $api->get_result( 'raw' ) );

		if ( ! empty( $api->bad_input ) ) {
			$response->set_status( 400 );
		}

		return $response;
	}

	function info_10( $request ) {
		define( 'THEMES_API_VERSION', '1.0' );

		$api = wporg_themes_query_api(
			'theme_information',
			$request->get_params(),
			'api_object'
		);

		// PHP output.
		echo $api->get_result( 'php' );
		exit;
	}

}
new Info_Endpoint();
