<?php
/**
 * Watch SVN for new revisions and import those into the directory.
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */

namespace WordPressdotorg\Theme_Directory\Jobs;
use Exception;
use SimpleXMLElement;
use WPORG_Themes_Upload;

/**
 * Class SVN_Import
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */
class SVN_Import {

	const SVN_URL = 'https://themes.svn.wordpress.org/';

	/**
	 * Check for new SVN revisions on the target repo, and queue an import job for each matching.
	 */
	public static function watcher_trigger() {
		$last_revision = (int) get_option( 'svn_import_last_revision', 0 );
		if ( ! $last_revision ) {
			trigger_error( 'Theme Importing aborting, no starting revision known. Set "svn_import_last_revision"', E_USER_WARNING );
			return;
		}

		$current_revision = (int) trim( exec( 'svn info --show-item=revision https://themes.svn.wordpress.org/' ) );

		// Don't need to parse anything if the revisions match.
		if ( $last_revision >= $current_revision ) {
			return;
		}

		// We don't need to include the details of the last revision next time we check for changes.
		$last_revision++;

		// Get the changes since then..
		$svn_xml = shell_exec( "svn log https://themes.svn.wordpress.org/ -v --xml -r {$last_revision}:{$current_revision}" );
		$xml = new SimpleXMLElement( $svn_xml );
		if ( ! $xml ) {
			return false;
		}

		$theme_changes = array_filter( array_map( function( $element ) { 

			// Get slug/version.
			$slug = '';
			$version = '';
			foreach ( $element->xpath( 'paths/path[@kind="dir"]') as $path ) {
				if ( preg_match( '!^/(?P<slug>[^/]+)/(?P<version>[^/]+)(/.+)?$!', (string) $path, $m ) ) {
					$slug    = $m['slug'];
					$version = $m['version'];
					break;
				}
			}
			if ( ! $slug || ! $version ) {
				return false;
			}

			$changeset = (int) $element->xpath( '@revision' )[0];
			$author    = (string) $element->xpath( 'author' )[0];
			$msg       = (string) $element->xpath( 'msg' )[0];

			$info = compact( 'slug', 'version', 'changeset', 'author', 'msg' );

			// Allow for including/skipping revisions based on external conditionals.
			$should_import = 'themedropbox' !== $author;
			if ( ! apply_filters( 'themes_svn_should_import', $should_import, $info ) ) {
				return false;
			}

			return $info;
		}, $xml->xpath('/log/logentry') ) );

		$theme_changes = array_unique( $theme_changes, SORT_REGULAR );

		foreach ( $theme_changes as $change ) {
			wp_schedule_single_event( time(), 'theme_directory_svn_import', array( $change ) );
		}

		update_option( 'svn_import_last_revision', $current_revision );
	}

	/**
	 * Import a Theme from SVN into the directory on a cron task.
	 */
	public static function import_trigger( $args ) {
		// Actually do an import for a slug/version.
		include_once dirname( __DIR__ ) . '/class-wporg-themes-upload.php';

		if ( empty( $args['slug'] ) || empty( $args['version'] ) ) {
			trigger_error( 'Theme Import aborted, invalid input provided: ' . json_Encode( $args ), E_USER_WARNING );
			return;
		}

		$uploader = new WPORG_Themes_Upload;

		$ret = $uploader->process_update_from_svn(
			$args['slug'],
			$args['version'],
			$args['changeset'] ?? false,
			$args['author'] ?? false
		);

		if ( is_wp_error( $ret ) ) {
			throw new Exception( "Theme Import Failure: " . $ret->get_error_code . ' ' . $ret->get_error_message() );
		}
	}
}
