<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_REST_Response;
use WPORG_Themes_Repo_Package;
use Themes_API;

class Info_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => [ $this, 'info' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'slug' => [
					'required'          => true,
					'type'              => [ 'string', 'integer' ],
					'sanitize_callback' => function( $value ) {
						return is_string( $value ) ? trim( $value ) : $value;
					},
				],
				'locale'          => [
					'default' => 'en_US',
					'type'    => 'string',
				],
			],
		);

		register_rest_route( 'themes/1.2', 'info(/(?P<slug>[^/]+))?', $args );

		$args['callback'] = [ $this, 'info_11' ];
		register_rest_route( 'themes/1.1', 'info(/(?P<slug>[^/]+))?', $args );
		register_rest_route( 'themes/1.0', 'info(/(?P<slug>[^/]+))?', $args );
	}

	/**
	 * Endpoint to handle 1.0/1.1 theme_information API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function info_11( $request ) {
		$response = $this->info( $request );

		// Back-compat, for older endpoints it returns false on error.
		if ( !empty( $response->data['error'] ) && 'Theme not found' === $response->data['error'] ) {
			$response->set_data( false );
		}

		return $response;
	}

	/**
	 * Endpoint to handle theme_information API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function info( $request ) {

		// Switch to the locale.
		if ( $request['locale'] && 'en_US' !== $request['locale'] ) {
			switch_to_locale( $request['locale'] );
		}

		if ( is_int( $request['slug'] ) ) {
			$themes = [];
			$theme  = get_post( $request['slug'] );
			if (
				$theme &&
				'repopackage' === $theme->post_type &&
				in_array(
					$theme->post_status,
					[ 'publish', 'delist' ]
				)
			) {
				$themes[] = $theme;
			}
		} else {
			$themes = get_posts( [
				'name'        => $request['slug'],
				'post_type'   => 'repopackage',
				'post_status' => [ 'publish', 'delist' ],
			] );
		}

		if ( ! $themes ) {
			$response = new WP_REST_Response( [
				'error' => 'Theme not found',
			] );
			$response->set_status( 404 );

			return $response;
		}

		$theme         = $themes[0];
		$repo_package  = new WPORG_Themes_Repo_Package( $theme->ID );
		$version       = $repo_package->latest_version();

		$phil = [
			'name'              => $theme->post_title,
			'slug'              => $theme->post_name,
			'version'           => $version,
			'preview_url'       => "https://wp-themes.com/{$theme->post_name}/",
			'reviews_url'       => "https://wordpress.org/support/theme/{$theme->post_name}/reviews/",
			'homepage'          => "https://wordpress.org/themes/{$theme->post_name}/",
			'theme_url'         => wporg_themes_get_version_meta( $theme->ID, '_theme_url', $version ),
			'download_link'     => Themes_API::create_download_link( $theme, $version ),
			'last_updated'      => get_post_modified_time( 'Y-m-d', true, $theme->ID, true ),
			'last_updated_time' => get_post_modified_time( 'Y-m-d H:i:s', true, $theme->ID, true ),
			'creation_time'     => get_post_time( 'Y-m-d H:i:s', true, $theme->ID, true ),
			'active_installs'   => (int) get_post_meta( $theme->ID, '_active_installs', true ),
			'requires'          => wporg_themes_get_version_meta( $theme->ID, '_requires', $version ),
			'requires_php'      => wporg_themes_get_version_meta( $theme->ID, '_requires_php', $version ),
			'trac_tickets'      => get_post_meta( $theme->ID, '_ticket_id', true ),
			'template'          => $theme->post_parent ? get_post( $theme->post_parent )->post_name : $theme->post_name,
			'description'       => ( function( $theme ) {

				// TODO: Why is this junk in the database?
				$description = trim( $theme->post_content );
				$description = str_replace( '&quot;"', '"', $description );
				$description = str_replace( 'href="//', 'href="http://', $description );
				$description = strip_tags( $description );

				return $description;
			} )( $theme ),

			'author'            => ( function( $theme, $version ) {
				$author = get_user_by( 'id', $theme->post_author );

				return (object) [
					// WordPress.org user details.
					'user_nicename' => $author->user_nicename,
					'profile'       => 'https://profiles.wordpress.org/' . $author->user_nicename,
					'avatar'        => 'https://secure.gravatar.com/avatar/' . md5( $author->user_email ) . '?s=96&d=monsterid&r=g',
					'display_name'  => $author->display_name ?: $author->user_nicename,

					// Theme headers details.
					'author'        => wporg_themes_get_version_meta( $theme->ID, '_author', $version ),
					'author_url'    => wporg_themes_get_version_meta( $theme->ID, '_author_url', $version ),
				];
			} )( $theme, $version ),

			'tags'              => ( function( $theme ) {
				$r = [];
				foreach ( wp_get_post_tags( $theme->ID ) as $tag ) {
					$r[ $tag->slug ] = $tag->name;
				}
				return $r;
			} )( $theme ),

			'versions'          => ( function( $theme ) {
				$versions = [];
				foreach ( array_keys( get_post_meta( $theme->ID, '_status', true ) ) as $version ) {
					$versions[ $version ] = Themes_API::create_download_link( $theme, $version );
				}
				return $versions;
			} )( $theme ),

			'parent' => ( function( $theme ) {
				if ( ! $theme->post_parent ) {
					return false;
				}

				$parent = get_post( $theme->post_parent );

				return [
					'slug'     => $parent->post_name,
					'name'     => $parent->post_title,
					'homepage' => "https://wordpress.org/themes/{$parent->post_name}/",
				];
			} )( $theme ),

			'downloaded' => ( function( $theme ) {
				if ( defined( 'IS_WPORG' ) && IS_WPORG ) {
					global $wpdb;
					return (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT SUM( downloads ) FROM bb_themes_stats WHERE slug = %s",
						$theme->post_name
					) );
				}

				return (int) $theme->downloaded;
			} )( $theme ),
		];

		// Screenshots
		$phil += ( function( $theme, $version ) {
			$screenshots = get_post_meta( $theme->ID, '_screenshot', true );

			$screenshot = sprintf(
				'https://ts.w.org/wp-content/themes/%1$s/%2$s?ver=%3$s',
				$theme->post_name,
				$screenshots[ $version ],
				$version
			);

			return [
				'screenshot_url'        => $screenshot,
				'photon_screenshots' => (array) sprintf(
					'https://i0.wp.com/themes.svn.wordpress.org/%1$s/%2$s/%3$s',
					$theme->post_name,
					$version,
					$screenshots[ $version ]
				),
				'screenshot_count'      => 1,
				'screenshots'           => [
					$screenshot,
				]
			];
		} ) ( $theme, $version );

		// Ratings
		$phil += ( function( $theme ) {
			if ( class_exists( 'WPORG_Ratings' ) ) {
				return [
					'ratings'     => \WPORG_Ratings::get_rating_counts( 'theme', $theme->post_name ),
					'rating'      => \WPORG_Ratings::get_avg_rating( 'theme', $theme->post_name ) * 20,
					'num_ratings' => \WPORG_Ratings::get_rating_count( 'theme', $theme->post_name ),
				];
			} else {
				// Fallback to postmeta if it exists.
				return [
					'ratings'     => (array) $theme->ratings,
					'rating'      => (int) $theme->rating,
					'num_ratings' => (int) $theme->num_ratings,
				];
			}
		} ) ( $theme );

		if ( class_exists( 'GlotPress_Translate_Bridge' ) && 'en_US' !== get_locale() ) {
			$glotpress_project = "wp-themes/{$phil->slug}";

			$phil->name = GlotPress_Translate_Bridge::translate( $phil->name, $glotpress_project );

			$phil->description = GlotPress_Translate_Bridge::translate( $phil->description, $glotpress_project );

			foreach ( $phil->sections as $section => $content ) {
				$phil->sections[ $section ] = GlotPress_Translate_Bridge::translate( $content, $glotpress_project );
			}
		}

		return (object) $phil;
	}

}
new Info_Endpoint();
