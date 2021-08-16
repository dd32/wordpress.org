<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_REST_Server;

class SVN_Auth extends Base {

	function __construct() {
		register_rest_route( 'themes/v1', 'svn-auth', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'svn_auth' ),
			'permission_callback' => array( $this, 'permission_check_internal_api_bearer' ),
		) );
	}

	/**
	 * Generates a SVN auth file.
	 */
	function svn_auth() {
		global $wpdb;

		// Raw SQL to avoid loading every user/post object.
		$themes = $wpdb->get_results(
			"SELECT p.post_name as slug, u.user_login as user
			FROM {$wpdb->posts} p
			JOIN {$wpdb->users} u ON p.post_author = u.ID
			WHERE p.post_type = 'repopackage' AND p.post_status IN( 'publish', 'delist' )
			ORDER BY p.ID ASC
			"
		);

		// Some users need write access to all themes, such as the dropbox user.
		$all_access_users   = get_option( 'svn_all_access', array( 'themedropbox' ) );
		$all_access_users[] = 'themedropbox';
		foreach ( array_unique( $all_access_users ) as $u ) {
			printf(
				"[%s]\n%s = rw\n\n",
				'/',
				$u
			);
		}

		// Theme Authors.
		foreach ( $themes as $r ) {
			printf(
				"[%s]\n%s = rw\n\n",
				'/' . ltrim( $r->slug, '/' ),
				$r->user
			);

		}

		exit();
	}
}
new SVN_Auth();
