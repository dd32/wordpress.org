<?php
/**
 * Plugin Name: WPORG Plugin Author Import (local dev)
 * Description: When a plugin post is created without a post_author (e.g. via
 *              import-plugin.php --create in CLI), fetch the plugin's author
 *              from the wordpress.org REST API and create the local user so the
 *              admin UI has a real author to display.
 */

namespace WordPressdotorg\Env;

/**
 * Intercept plugin post inserts that have no author and resolve one from wp.org.
 *
 * Runs only in CLI to avoid touching real submissions from authenticated users.
 *
 * @param array $data    Sanitised post data about to be inserted.
 * @param array $postarr Original post data passed to wp_insert_post().
 * @return array Post data, possibly with post_author rewritten.
 */
function maybe_populate_plugin_author( $data, $postarr ) {
	if ( 'cli' !== php_sapi_name() ) {
		return $data;
	}

	if ( ( $data['post_type'] ?? '' ) !== 'plugin' ) {
		return $data;
	}

	// Only on new inserts, and only when no author was supplied.
	if ( ! empty( $postarr['ID'] ) ) {
		return $data;
	}
	if ( ! empty( $data['post_author'] ) ) {
		return $data;
	}

	$slug = $data['post_name'] ?? '';
	if ( ! $slug ) {
		return $data;
	}

	$author = fetch_plugin_author( $slug );
	if ( ! $author ) {
		return $data;
	}

	$user_id = ensure_local_user( $author );
	if ( $user_id ) {
		$data['post_author'] = $user_id;
		fwrite( STDERR, "[wporg-plugin-author-import] {$slug}: post_author set to {$user_id} ({$author['slug']}).\n" );
	}

	return $data;
}
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\maybe_populate_plugin_author', 5, 2 );

/**
 * Fetch the embedded author object for a plugin slug from the wp.org REST API.
 *
 * @param string $slug Plugin slug.
 * @return array|null Author array (id, slug, name, ...) or null on failure.
 */
function fetch_plugin_author( $slug ) {
	$url = add_query_arg(
		array(
			'slug'     => $slug,
			'_embed'   => 1,
			'per_page' => 1,
		),
		'https://wordpress.org/plugins/wp-json/wp/v2/plugin'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$results = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $results[0]['_embedded']['author'][0]['slug'] ) ) {
		return null;
	}

	return $results[0]['_embedded']['author'][0];
}

/**
 * Find or create a local user matching the given wp.org author payload.
 *
 * @param array $author Author array from the REST API (must include slug).
 * @return int User ID, or 0 on failure.
 */
function ensure_local_user( array $author ) {
	$slug = $author['slug'];

	$existing = get_user_by( 'slug', $slug );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	$user_id = wp_insert_user( array(
		'user_login'    => $slug,
		'user_nicename' => $slug,
		'user_email'    => $slug . '@example.invalid',
		'display_name'  => $author['name'] ?? $slug,
		'user_url'      => $author['url'] ?? '',
		'user_pass'     => wp_generate_password(),
		'role'          => 'subscriber',
	) );

	if ( is_wp_error( $user_id ) ) {
		fwrite( STDERR, "[wporg-plugin-author-import] wp_insert_user failed for {$slug}: " . $user_id->get_error_message() . "\n" );
		return 0;
	}

	return (int) $user_id;
}
