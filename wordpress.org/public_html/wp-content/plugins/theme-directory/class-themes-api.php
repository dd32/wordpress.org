<?php

/**
 * The WordPress.org Themes API.
 *
 * Class Themes_API
 */
class Themes_API {

	/**
	 * Array of request parameters.
	 *
	 * @var array
	 */
	public $request = array();

	/**
	 * Array of parameters for WP_Query.
	 *
	 * @var array
	 */
	public $query = array();

	/**
	 * Holds the result of a WP_Query query.
	 *
	 * @var array
	 */
	public $result = array();

	/**
	 * API response.
	 *
	 * @var null|array|object
	 */
	public $response = null;

	/**
	 * Field defaults, overridden by individual sections.
	 *
	 * @var array
	 */
	public $fields = array(
		'description'        => false,
		'downloaded'         => false,
		'downloadlink'       => false,
		'last_updated'       => false,
		'creation_time'      => false,
		'parent'             => false,
		'rating'             => false,
		'ratings'            => false,
		'reviews_url'        => false,
		'screenshot_count'   => false,
		'screenshot_url'     => true,
		'screenshots'        => false,
		'sections'           => false,
		'tags'               => false,
		'template'           => false,
		'versions'           => false,
		'theme_url'          => false,
		'extended_author'    => false,
		'photon_screenshots' => false,
		'active_installs'    => false,
		'requires'           => false,
		'requires_php'       => false,
		'trac_tickets'       => false,
	);

	/**
	 * Name of the cache group.
	 *
	 * @var string
	 */
	private $cache_group = 'theme-info';

	/**
	 * The amount of time to keep information cached.
	 *
	 * @var int
	 */
	private $cache_life = 600; // 10 minutes.

	/**
	 * Flag the input as having been malformed.
	 * 
	 * @var bool
	 */
	public $bad_input = false;

	/**
	 * Constructor.
	 *
	 * @param string $action
	 * @param array $request
	 */
	public function __construct( $action = '', $request = array() ) {
		$this->request = (object) $request;

		// Filter out bad inputs.
		$scalar_only_fields = [
			'author',
			'browse',
			'user',
			'locale',
			'per_page',
			'slug',
			'search',
			'theme',
			'wp_version',
		];
		foreach ( $scalar_only_fields as $field ) {
			if ( isset( $this->request->$field ) && ! is_scalar( $this->request->$field ) ) {
				unset( $this->request->$field );
				$this->bad_input = true;
			}
		}

		// Favorites requests require a user to fetch favorites for.
		if ( isset( $this->request->browse ) && 'favorites' === $this->request->browse && ! isset( $this->request->user ) ) {
			$this->request->user = '';
			$this->bad_input = true;
		}

		$array_of_string_fields = [
			'fields',
			'slugs',
			'tag',
		];
		foreach ( $array_of_string_fields as $field ) {
			if ( isset( $this->request->$field ) ) {
				$this->request->$field = $this->array_of_strings( $this->request->$field );

				// If the resulting field is invalid, ignore it entirely.
				if ( ! $this->request->$field ) {
					unset( $this->request->$field );
					$this->bad_input = true;
				}
			}
		}

		// The locale we should use is specified by the request
		add_filter( 'locale', array( $this, 'filter_locale' ) );

		/*
		 * Supported actions:
		 * query_themes, theme_information, hot_tags, feature_list.
		 */
		$valid_actions = array( 'query_themes', 'theme_information', 'hot_tags', 'feature_list', 'get_commercial_shops' );
		if ( in_array( $action, $valid_actions, true ) && method_exists( $this, $action ) ) {
			$this->$action();
		} else {
			// Assume a friendly wp hacker :)
			if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
				wp_die( 'Action not implemented. <a href="https://codex.wordpress.org/WordPress.org_API">API Docs</a>' );
			} else {
				$this->response = (object) array( 'error' => 'Action not implemented' );
			}
		}
	}

	/**
	 * Filter get_locale() to use the locale which is specified in the request.
	 */
	function filter_locale( $locale ) {
		if ( ! empty( $this->request->locale ) ) {
			$locale = (string) $this->request->locale;
		}

		return $locale;
	}

	/**
	 * Prepares result.
	 *
	 * @return string|void
	 */
	public function get_result( $format = 'raw' ) {
		$response = $this->response;

		// Back-compat behaviour for the 1.0/1.1 API's
		if ( defined( 'THEMES_API_VERSION' ) && THEMES_API_VERSION < 1.2 ) {
			if ( isset( $this->response->error ) && 'Theme not found' == $this->response->error ) {
				$response = false;
			}
		}

		if ( 'json' === $format ) {
			return wp_json_encode( $response );
		} elseif ( 'php' === $format ) {
			return serialize( $response );
		} elseif ( 'api_object' === $format ) {
			return $this;
		} else { // 'raw' === $format, or anything else.
			return $response;
		}
	}

	/* Action functions */

	/**
	 * Gets theme tags, ordered by how popular they are.
	 */
	public function hot_tags() {
		$cache_key = sanitize_key( __METHOD__ ) . ( $this->request->number ?? '' );

		if ( false !== ( $this->response = wp_cache_get( $cache_key, $this->cache_group ) ) ) {
			return;
		}

		$request = new WP_REST_Request( 'GET', '/themes/1.0/tags' );
		$request->set_query_params( [
			'number' => $this->request->number ?? 0,
		] );

		$response = rest_do_request( $request );

		$this->response = rest_get_server()->response_to_data( $response, false );

		wp_cache_add( $cache_key, $this->response, $this->cache_group, $this->cache_life );
	}

	/**
	 * Gets a list of valid "features" aka theme tags.
	 */
	public function feature_list() {
		$request = new WP_REST_Request( 'GET', '/themes/1.2/features' );
		$request->set_query_params( [
			'wp_version' => $this->request->wp_version ?? 0,
		] );
		$request->set_header( 'user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '' );

		$response = rest_do_request( $request );

		$this->response = rest_get_server()->response_to_data( $response, false );
	}

	/**
	 * Retrieve specific information about multiple theme.
	 */
	public function theme_information_multiple() {
		if ( empty( $this->request->slugs ) ) {
			$this->response = (object) array( 'error' => 'Slugs not provided' );
			return;
		}

		$slugs = (array) $this->request->slugs;

		if ( count( $slugs ) > 100 ) {
			$this->response = (object) array( 'error' => 'A maximum of 100 themes can be queried at once.' );
			return;
		}

		$response = array();
		unset( $this->request->slugs ); // So it doesn't affect caching.
		foreach ( $slugs as $slug ) {
			$this->request->slug = $slug;
			$this->theme_information();
			$response[ $slug ] = $this->response;
		}

		$this->response = $response;
	}

	/**
	 * Retrieve specific information about a theme.
	 */
	public function theme_information() {
		global $post;

		// Support the 'slugs' parameter to fetch multiple themes at once.
		if ( ! empty( $this->request->slugs ) ) {
			$this->theme_information_multiple();
			return;
		}

		// Theme slug to identify theme.
		if ( empty( $this->request->slug ) || ! trim( $this->request->slug ) ) {
			$this->response = (object) array( 'error' => 'Slug not provided' );
			return;
		}

		$this->request->slug = trim( $this->request->slug );

		// Set which fields wanted by default:
		$defaults = array(
			'sections'     => true,
			'rating'       => true,
			'downloaded'   => true,
			'downloadlink' => true,
			'last_updated' => true,
			'homepage'     => true,
			'tags'         => true,
			'template'     => true,
		);
		if ( defined( 'THEMES_API_VERSION' ) && THEMES_API_VERSION >= 1.2 ) {
			$defaults['extended_author'] = true;
			$defaults['num_ratings'] = true;
			$defaults['reviews_url'] = true;
			$defaults['parent'] = true;
			$defaults['requires'] = true;
			$defaults['requires_php'] = true;
			$defaults['creation_time'] = true;
		}

		$this->request->fields = (array) ( $this->request->fields ?? [] );

		$this->fields = array_merge( $this->fields, $defaults, (array) $this->request->fields );

		$this->response = $this->fill_theme( $this->request->slug );
	}

	/**
	 * Get a list of themes.
	 *
	 *  Object:
	 *      info (array)
	 *          page (int)
	 *          pages (int)
	 *          results (int)
	 *      themes (array)
	 *          name
	 *          slug
	 *          version
	 *          author
	 *          rating
	 *          num_ratings
	 *          homepage
	 *          description
	 *          preview_url
	 *          download_url
	 */
	public function query_themes() {
		// Set which fields wanted by default:
		$defaults = array(
			'description' => true,
			'rating'      => true,
			'homepage'    => true,
			'template'    => true,
		);
		if ( defined( 'THEMES_API_VERSION' ) && THEMES_API_VERSION >= 1.2 ) {
			$defaults['extended_author'] = true;
			$defaults['num_ratings'] = true;
			$defaults['parent'] = true;
			$defaults['requires'] = true;
			$defaults['requires_php'] = true;
		}

		$this->request->fields = (array) ( $this->request->fields ?? [] );

		$this->fields = array_merge( $this->fields, $defaults, $this->request->fields );

		// If there is a cached result, return that.
		$cache_key = sanitize_key( __METHOD__ . ':' . get_locale() . ':' . md5( serialize( $this->request ) . serialize( $this->fields ) ) );
		if ( false !== ( $this->response = wp_cache_get( $cache_key, $this->cache_group ) ) && empty( $this->request->cache_buster ) ) {
			return;
		}

		$this->result = $this->perform_wp_query();

		// Basic information about the request.
		$this->response = (object) array(
			'info'   => array(),
			'themes' => array(),
		);

		// Basic information about the request.
		$this->response->info = array(
			'page'    => max( 1, $this->result->query_vars['paged'] ),
			'pages'   => max( 1, $this->result->max_num_pages ),
			'results' => (int) $this->result->found_posts,
		);

		// Fill up the themes lists.
		foreach ( (array) $this->result->posts as $theme ) {
			$this->response->themes[] = $this->fill_theme( $theme );
		}

		wp_cache_set( $cache_key, $this->response, $this->cache_group, $this->cache_life );
	}

	public function perform_wp_query() {
		$this->query = array(
			'post_type'   => 'repopackage',
			'post_status' => 'publish',
		);
		if ( isset( $this->request->page ) ) {
			$this->query['paged'] = (int) $this->request->page;
		}
		if ( isset( $this->request->per_page ) ) {
			// Maximum of 999 themes per page, and a minimum of 1.
			$this->query['posts_per_page'] = min( (int) $this->request->per_page, 999 );
			if ( $this->query['posts_per_page'] < 1 ) {
				unset( $this->query['posts_per_page'] );
			}
		}

		// Views
		if ( ! empty( $this->request->browse ) ) {
			$this->query['browse'] = (string) $this->request->browse;

			if ( 'featured' == $this->request->browse ) {
				$this->cache_life = HOUR_IN_SECONDS;
			} elseif ( 'favorites' == $this->request->browse ) {
				$this->query['favorites_user'] = $this->request->user;
			}

		}

		// Tags
		if ( ! empty( $this->request->tag ) ) {
			$this->request->tag = (array) $this->request->tag;

			// Replace updated tags.
			$updated_tags = array(
				'fixed-width'    => 'fixed-layout',
				'flexible-width' => 'fluid-layout',
			);
			foreach ( $updated_tags as $old => $new ) {
				if ( $key = array_search( $old, $this->request->tag ) ) {
					$this->request->tag[ $key ] = $new;
				}
			}

			$this->query['tax_query'] = array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => $this->request->tag,
					'operator' => 'AND',
				),
			);
		}

		// Search
		if ( ! empty( $this->request->search ) ) {
			$this->query['s'] = (string) $this->request->search;
		}

		// Direct theme
		if ( ! empty( $this->request->theme ) ) {
			$this->query['name'] = (string) $this->request->theme;

			add_filter( 'parse_query', array( $this, 'direct_theme_query' ) );
		}

		// Author
		if ( ! empty( $this->request->author ) ) {
			$this->query['author_name'] = (string) $this->request->author;
		}

		// Query
		return new WP_Query( $this->query );
	}

	/**
	 * Get a list of commercial theme shops.
	 *
	 *  Object:
	 *      shops (array)
	 *          (object)
	 *              shop (string)
	 *              slug (string)
	 *              haiku (string)
	 *              image (string)
	 *              url (string)
	 */
	function get_commercial_shops() {
		if ( false !== ( $this->response = wp_cache_get( 'commercial_theme_shops', $this->cache_group ) ) && empty( $this->request->cache_buster ) ) {
			return;
		}

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/themes/1.0/commercial-shops' )
		);

		$this->response = rest_get_server()->response_to_data( $response, false );

		wp_cache_set( 'commercial_theme_shops', $this->response, $this->cache_group, 15 * 60 );
	}

	/**
	 * Fill it up with information.
	 *
	 * @param  WP_Theme $theme
	 *
	 * @return object
	 */
	public function fill_theme( $theme ) {
		$slug = is_object( $theme ) ? $theme->post_name : $theme;

		$cache_key = get_locale() . ':' . $slug;
		$theme = wp_cache_get( $cache_key, $this->cache_group );

		if ( ! $theme || ! empty( $this->request->cache_buster ) ) {
			$request = new WP_REST_Request( 'GET', '/themes/1.2/info' );
			$request->set_query_params( [
				'slug' => $slug
			] );

			$response = rest_do_request( $request );

			$theme = rest_get_server()->response_to_data( $response, false );

			wp_cache_set( $cache_key, $theme, $this->cache_group, $this->cache_life );
		}

		// Filter out the fields we don't need.
		if ( empty( $theme->error ) ) {
			foreach ( $this->fields as $field => $wanted ) {
				if ( empty( $wanted ) && isset( $theme->$field ) ) {
					unset( $theme->$field );
				}
			}
		}

		return $theme;
	}

	/* Filter */

	/**
	 * Marks queries for single themes as archive queries.
	 *
	 * When themes are queried directly, namely the `name` parameter is set, WordPress assumes this is a singular view.
	 * If a theme is not published and the user doing the request is not logged in, the query returns empty. In case
	 * the requested theme has a version that is awaiting approval, that would not be a desired outcome.
	 *
	 * @param WP_Query $query
	 *
	 * @return WP_Query
	 */
	public function direct_theme_query( $query ) {
		$query->is_single   = false;
		$query->is_singular = false;

		$query->is_post_type_archive = true;
		$query->is_archive           = true;

		return $query;
	}

	/* Helper functions */

	/**
	 * Creates download link.
	 *
	 * @param  WP_Post $theme
	 * @param  string $version
	 *
	 * @return string
	 */
	public static function create_download_link( $theme, $version ) {
		$url  = 'http://downloads.wordpress.org/theme/';
		$file = $theme->post_name . '.' . $version . '.zip';

		$file = preg_replace( '/[^a-z0-9_.-]/i', '', $file );
		$file = preg_replace( '/[.]+/', '.', $file );

		return set_url_scheme( $url . $file );
	}

	/**
	 * Fixes mangled descriptions.
	 *
	 * @param string $description
	 *
	 * @return string
	 */
	private function fix_mangled_description( $description ) {
		$description = str_replace( '&quot;"', '"', $description );
		$description = str_replace( 'href="//', 'href="http://', $description );
		$description = strip_tags( $description );

		return $description;
	}

	/**
	 * Helper method to return an array of trimmed strings.
	 */
	protected function array_of_strings( $input ) {
		if ( is_string( $input ) ) {
			$input = explode( ',', $input );
		}

		if ( ! $input || ! is_array( $input ) ) {
			return [];
		}

		foreach ( $input as $k => $v ) {
			if ( ! is_scalar( $v ) ) {
				unset( $input[ $k ] );
			} elseif ( is_string( $v ) ) {
				// Don't affect non-strings such as int's and bools.
				$input[ $k ] = trim( $v );
			}
		}

		// Only unique if it's a non-associative array.
		if ( wp_is_numeric_array( $input ) ) {
			$input = array_unique( $input );
		}

		return $input;
	}
}
