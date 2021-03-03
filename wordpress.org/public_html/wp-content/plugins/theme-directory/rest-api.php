<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

/**
 * The WordPress REST API only allows jsonp support via the _jsonp parameter,
 * and it must be set prior to the REST API Server being initialized, prior to any
 * rest api specific filters are run.
 * 
 * This maps the parameter this API uses ?callback= to the REST API parameter.
 */
function enable_jsonp_support() {
	global $wp;

	if (
		! isset( $_GET['callback'] ) ||
		empty( $wp->query_vars['rest_route'] ) ||
		'/themes/' !== substr( $wp->query_vars['rest_route'], 0, 8 )
	) {
		return;
	}

	$_GET['_jsonp'] = $_GET['callback'];

	unset( $_GET['callback'], $_REQUEST['callback'] );
}
add_action( 'parse_request', 'enable_jsonp_support', 9 );

// Include the REST API Endpoints at the appropriate time.
add_action( 'rest_api_init', function() {
	require __DIR__ . '/rest-api/class-internal-stats.php';
	require __DIR__ . '/rest-api/class-info-endpoint.php';
	require __DIR__ . '/rest-api/class-query-endpoint.php';
} );