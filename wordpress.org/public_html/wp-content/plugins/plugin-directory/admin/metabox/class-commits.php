<?php
/**
 * Revision list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Revision list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Commits {
	/**
	 * Number of commits to show.
	 */
	const REVS_TO_SHOW = 25;

	/**
	 * Displays links to the last 50 commits for the plugin.
	 */
	public static function display() {
		global $wpdb;
		$post = get_post();

		$changes = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM trac_plugins WHERE `slug` = %s AND category = 'changeset' ORDER BY `pubdate` DESC LIMIT %d",
			$post->post_name,
			self::REVS_TO_SHOW
		) );

		echo '<table class="widefat changesets">';
		echo '<thead>
			<tr>
				<th>Author</th>
				<th>When</th>
				<th>Message</th>
				<th>Actions</th>
			</tr>
		</thead>';

		foreach ( $changes as $i => $change ) {
			$actions = [];
			$user    = get_user_by( 'user_login', $change->username );

			$changeset = false;
			if ( preg_match( '!/changeset/(\d+)/?$!', $change->link, $m ) && $change->username != PLUGIN_SVN_MANAGEMENT_USER ) {
				$changeset = $m[1];

				// Allow reverting of the latest plugin commit only.
				if ( $i === 0 ) {
					$actions[] = sprintf(
						'<a data-link="%s" class="button delete undo" >Revert Latest Commit</a>',
						add_query_arg(
							array(
								'action'      => 'plugin-svn-revert-change',
								'slug'        => $post->post_name,
								'changeset'   => $changeset,
								'reason'      => '',
								'_ajax_nonce' => wp_create_nonce( 'wporg_plugins_svn_revert-' . $post->post_name . '-' . $changeset ),
							),
							admin_url( 'admin-ajax.php' )
						)
					);
				}
			}

			$actions = apply_filters( 'plugin_directory_admin_commits_actions', $actions, $change, $changes );

			printf(
				"<tr>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>\n",
				sprintf(
					'<a href="https://profiles.wordpress.org/%s/">%s</a>',
					$user->user_nicename ?? $change->username,
					$user->user_login ?? $change->username
				),
				esc_html( $change->pubdate ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $change->link ),
					esc_html( $change->title )
				),
				implode( ' ', $actions )
			);
		}

		echo '</table>';
		printf(
			'<small>Showing the last %d revisions</small>',
			self::REVS_TO_SHOW
		);
	}

	/**
	 * admin-ajax.php handler for queueing a plugin import.
	 */
	static function revert() {
		$plugin_slug = sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) );
		$changeset   = intval( wp_unslash( $_REQUEST['changeset'] ) );
		$reason      = wp_unslash( $_REQUEST['reason'] );

		$reason = 'testing';

		check_ajax_referer( 'wporg_plugins_svn_revert-' . $plugin_slug . '-' . $changeset );

		$temp_dir = Filesystem::temp_directory( 'svn-revert' );

		$checkout = SVN::checkout( 'https://plugins.svn.wordpress.org/', $temp_dir, [ 'depth' => 'empty' ] );
		$up = SVN::up( "{$temp_dir}/{$plugin_slug}" );
		$merge = SVN::merge( $temp_dir, [ 'c' => "-{$changeset}" ] );

		$commit = SVN::commit( "{$temp_dir}/{$plugin_slug}", "{$plugin_slug}: Revert [{$changeset}]: $reason" );

		var_dump( get_defined_vars() );
	}
}
