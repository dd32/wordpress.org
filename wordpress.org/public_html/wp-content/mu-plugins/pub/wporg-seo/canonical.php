<?php
namespace WordPressdotorg\SEO\Canonical;
/**
 * Adds canonical-related functionality.
 * @see https://core.trac.wordpress.org/ticket/18660
 */

/**
 * Outputs a <link rel="canonical"> on most pages.
 */
function rel_canonical_link() {
	if ( $url = get_canonical_url() ) {
		printf(
			'<link rel="canonical" href="%s">' . "\n",
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\rel_canonical_link' );
add_action( 'login_head',  __NAMESPACE__ . '\rel_canonical_link' );

remove_action( 'wp_head', 'rel_canonical' );

/**
 * Get the current Canonical URL.
 */
function get_canonical_url() {
	global $wp, $wp_rewrite;

	$queried_object = get_queried_object();
	$url = false;

	if ( is_tax() || is_tag() || is_category() ) {
		$url = get_term_link( $queried_object );
	} elseif ( is_singular() ) {
		$url = get_permalink( $queried_object );
	} elseif ( is_search() ) {
		$url = home_url( 'search/' . urlencode( get_query_var( 's' ) ) . '/' );
	} elseif ( is_author() ) {
		// On WordPress.org get_author_posts_url() returns profile.wordpress.org links. Build it manually.
		$url = home_url( 'author/' . $queried_object->user_nicename . '/' );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( $queried_object->name );
	} elseif ( is_home() ) {
		$url = get_post_type_archive_link( 'post' );
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_date() ) {
		if ( is_day() ) {
			$url = get_day_link( get_query_var('year'), get_query_var('monthnum'), get_query_var('day') );
		} elseif ( is_month() ) {
			$url = get_month_link( get_query_var('year'), get_query_var('monthnum') );
		} elseif ( is_year() ) {
			$url = get_year_link( get_query_var('year') );
		}
	}

	// Filter to override the above logics.
	$url = apply_filters( 'wporg_canonical_base_url', $url );

	// Certain routes, such as `get_term_link()` can return WP_Error objects.
	if ( is_wp_error( $url ) ) {
		$url = false;
	}

	// Ensure trailing slashed paths.
	if ( $url ) {
		if ( false !== stripos( $url, '?' ) ) {
			[ $url, $query ] = explode( '?', $url, 2 );
			$url = trailingslashit( $url ) . '?' . $query;
		} else {
			$url = trailingslashit( $url );
		}
	}

	if ( $url && is_paged() ) {
		if ( false !== stripos( $url, '?' ) ) {
			// We're not actually sure 100% here if the current url supports rewrite rules.
			$url = add_query_arg( 'paged', (int) get_query_var( 'paged' ), $url );
		} else {
			$url = rtrim( $url, '/' ) . '/' . $wp_rewrite->pagination_base . '/' . (int) get_query_var( 'paged' ) . '/';
		}
	}

	// Add order/orderby to Archives.
	if ( is_archive() || is_search() || is_home() ) {
		// Check $wp since `get_query_var()` will return default values too.
		if ( !empty( $wp->query_vars[ 'order'] ) ) {
			$url = add_query_arg( 'order', get_query_var( 'order' ), $url );
		}
		if ( !empty( $wp->query_vars[ 'orderby'] ) ) {
			$url = add_query_arg( 'orderby', strtolower( get_query_var( 'orderby' ) ), $url );
		}
	}

	$url = apply_filters( 'wporg_canonical_url', $url );

	// Force canonical links to be lowercase.
	// See https://meta.trac.wordpress.org/ticket/4414
	$url = mb_strtolower( $url, 'UTF-8' );

	return $url;
}
