<?php
namespace WordPressdotorg\GitHub\MakeInviter;

/**
 * Plugin Name:       GitHub Invite Member
 * Description:       Invite Members to the WordPress organization
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            the WordPress.org Community.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           WordPressdotorg\GitHub\MakeInviter
 *
 * Hat-tip to Jonathan Bossenger for the original plugin code.
 */

const ORG   = 'WordPress';
const TOKEN = \GH_INVITE_ADMIN_TOKEN;

add_action(
	'admin_menu',
	function() {
		add_submenu_page(
			'tools.php',
			'Invite Github Member',
			'Invite Github Member',
			'manage_options',
			'gh_invite_collaborator',
			__NAMESPACE__ . '\render'
		);
	}
);

function render() {
	$allowed_teams = get_allowed_teams();
	$all_teams     = get_teams();
	$teams         = [];

	foreach ( $allowed_teams as $id ) {
		$team = wp_list_filter( $all_teams, [ 'id' => $id ] );
		if ( ! $team ) {
			continue;
		}
		$team = array_shift( $team );

		// Add the parent..
		if ( isset( $team->parent ) ) {
			$teams[] = $team->parent;
		}

		$teams[] = $team;
	}

	// Add any sub-teams that are not allowed to be selected..
	foreach ( $teams as $team ) {
		foreach ( $all_teams as $t ) {
			if ( $t->parent && $t->parent->id === $team->id && ! in_array( $t->id, $allowed_teams, true ) ) {
				$teams[] = clone $t;
			}
		}
	}

	// Mark any as disabled as needed.
	foreach ( $teams as $team ) {
		$team->disabled = ! in_array( $team->id, $allowed_teams, true );
	}

	if ( isset( $_GET['updated'] ) ) {
		$class   = 'success';
		$message = '';
		switch ( $_GET['updated'] ) {
			case 'success':
				$message = 'Success, collaborator invitation sent!';
				break;
			case 'canceled':
				$message = 'Invitation canceled';
				break;
			case 'error':
				$class   = 'error';
				$message = 'An error occurred inviting this collaborator!';
				break;
			case 'settings':
				$message = 'Settings updated';
				break;
			case 'no-github':
				$class   = 'error';
				$message = 'The specified WordPress.org account does not have a linked GitHub account.';
				break;
		}

		if ( $message && isset( $_GET['message'] ) ) {
			$message .= '<br><em>' . esc_html( $_GET['message'] ) . '</em>';
		}

		if ( $message ) {
			printf(
				'<div class="notice notice-%s is-dismissable"><p>%s</p></div>',
				$class,
				$message
			);
		}
	}

	?>
	<div class="wrap" id="wp_learn_admin">
	<h1>Invite GitHub Member</h1>
	<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
		<input type="hidden" name="action" value="github_invite">
		<?php wp_nonce_field( 'github_invite' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="invite">GitHub Email, or WordPress.org Profile URL</label></th>
				<td><input type="text" name="invite" id="invite" class="regular-text" placeholder="https://profiles.wordpress.org/<?php echo wp_get_current_user()->user_nicename; ?>/"></td>
			</tr>
			<tr>
				<th scope="row"><label for="team">Teams</label></th>
				<td>
					<?php
					if ( ! $teams ) {
						echo '<em>No teams have been configured. Please ask a super-admin via #meta to enable at least one team.</em>';
					}

					render_team_list( $teams );
					?>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Invite Collaborator' ); ?>
	</form>

	<h1>Pending Invites</h1>
	<form>
		<table class="form-table">
			<tr>
				<th scope="row">Pending Invitations</th>
				<td>
					<?php
					$pending_invites = get_pending_invites();
					if ( ! $pending_invites ) {
						echo '<em>No pending invitations</em>';
					}

					foreach ( $pending_invites as $pending ) {
						$can_cancel = in_array( $pending->id, get_option( 'invited_gh_users', [] ), true ) || is_super_admin();
						$cancel_url = $can_cancel ? wp_nonce_url( admin_url( 'admin-post.php?action=github_cancel_invite&invite=' . $pending->id ), 'github_cancel_invite_' . $pending->id ) : false;
						printf(
							'<p>
								<strong><code>%s</code></strong>
								<em>%s ago</em>
								%s
							</p>',
							$pending->login ?: $pending->email,
							human_time_diff( strtotime( $pending->created_at ) ),
							$cancel_url ? '<a class="button" href="' . esc_url( $cancel_url ) . '">Cancel</a>' : ''
						);
					}
					?>
				</td>
			</tr>
		</table>
	</form>
	<?php

	// Allow super-admins to set the teams the site users can invite for.
	if ( is_super_admin() ) {
		?>
		<hr>
		<h1>Settings</h1>
		<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
			<input type="hidden" name="action" value="github_invite_settings">
			<?php wp_nonce_field( 'github_invite_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="team">Allowed Team(s) for this site <span style="color: red">(super-admin only)</span></label></th>
					<td>
						<?php render_team_list( $all_teams, $allowed_teams ); ?>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}
}

/**
 * Render the team list.
 */
function render_team_list( $teams, $checked = array(), $for_parent = 0 ) {
	if ( $for_parent ) {
		$teams = array_filter( $teams, function( $t ) use ( $for_parent ) {
			return $for_parent === $t->parent->id ?? 0;
		} );

		if ( ! $teams ) {
			return false;
		}

		echo '<div class="childen" style="margin-left: 1em">';
	}

	foreach ( $teams as $team ) {
		if ( isset( $team->parent ) && ! $for_parent ) {
			continue;
		}

		?>
		<div>
			<label>
				<input
					type="checkbox"
					name="team_id[]"
					value="<?php echo esc_attr( $team->id ) ?>"
					<?php
						checked( in_array( $team->id, $checked ) );
						disabled( !empty( $team->disabled ) );
					?>
				/>
				<?php echo esc_html( $team->name ) ?>
			</label>
			<?php
			// Any child teams of this team?
			render_team_list( $teams, $checked, $team->id );
			?>
		</div>
	<?php }

	if ( $for_parent ) {
		echo '</div>';
	}
}

/**
 * Get the allowed teams for this site.
 */
function get_allowed_teams() {
	$allowed_teams = array_map( 'intval', get_option( 'gh_invite_allowed_teams', array() ) );

	// Some teams cannot be selected.
	$never = [
		1114244, // Security team.
	];

	return array_diff( $allowed_teams, $never );
}

/* POST Handlers */

/**
 * Process the invitation.
 */
add_action( 'admin_post_github_invite', function() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	check_admin_referer( 'github_invite' );

	$input    = wp_unslash( $_POST['invite'] );
	$team_ids = (array) wp_unslash( $_POST['team_id'] );
	$team_ids = array_intersect( $team_ids, get_allowed_teams() );
	$team_ids = array_map( 'intval', $team_ids );

	$updated = 'success';
	$message = null;
	$invite  = false;

	if ( ! $team_ids ) {
		$updated = 'error';
		$message = 'No teams selected';
	} elseif ( preg_match( '!^https://profiles.wordpress.org/(?<slug>[^/]+)!i', $input, $m ) ) {
		$user           = get_user_by( 'slug', $m['slug'] );
		$github_details = json_decode( $wpdb->get_var( $wpdb->prepare(
			'SELECT user_details FROM wporg_github_users WHERE user_id = %d',
			$user->ID
		) ) );

		if ( ! $user || ! $github_details || ! $invite ) {
			$updated = 'no-github';
		} else {
			$invite = $github_details->id;
		}
	} elseif ( is_email( $input ) ) {
		$invite = $input;
	} else {
		$updated = 'error';
	}

	if ( $invite ) {
		$result = invite_member( $invite, $team_ids );

		if ( $result->id ) {
			// Note that it was invited via this site..
			$invited_gh_users = get_option( 'invited_gh_users', [] );
			$invited_gh_users[] = $result->id;
			update_option( 'invited_gh_users', $invited_gh_users );

			delete_site_transient( 'gh_invites' );

			// Log it to Slack.
			$teams          = get_teams();
			$readable_teams = array_map( static function( $id ) use( $teams ) {
				return array_values( wp_list_filter( $teams, [ 'id' => $id ] ) )[0]->name ?? $id;
			}, $team_ids );

			$log = sprintf(
				'%s invited to organisation by %s to team(s) %s',
				$result->login ?: $result->email,
				wp_get_current_user()->user_login,
				implode( ', ', $readable_teams )
			);

			slack_dm( $log ); // , GH_INVITE_SLACK_GITHUBADMINS );
		}

		if ( isset( $result->errors ) ) {
			$updated = 'error';
			$message = $result->errors[0]->message ?? '';
		} elseif ( $result->login ?: $result->email ) {
			$message = sprintf( 'Invited User: %s', $result->login ?: $result->email );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			compact( 'updated', 'message' ),
			admin_url( 'tools.php?page=gh_invite_collaborator' )
		)
	);
	die();
} );

/**
 * Allow a super-admin to specify which teams a user may be invited to from this site.
 */
add_action( 'admin_post_github_invite_settings', function() {
	if ( ! is_super_admin() || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	check_admin_referer( 'github_invite_settings' );

	$team_ids = wp_unslash( $_POST['team_id'] );
	$team_ids = array_map( 'intval', $team_ids );

	update_option( 'gh_invite_allowed_teams', $team_ids );

	wp_safe_redirect( admin_url( 'tools.php?page=gh_invite_collaborator&updated=settings' ) );
	die();
} );

/**
 * Cancel an invitation.
 */
add_action( 'admin_post_github_cancel_invite', function() {
	if ( ! is_super_admin() || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	$id = wp_unslash( $_GET['invite'] );

	check_admin_referer( 'github_cancel_invite_' . $id );

	cancel_invite( $id );

	delete_site_transient( 'gh_invites' );

	wp_safe_redirect( admin_url( 'tools.php?page=gh_invite_collaborator&updated=canceled' ) );
	die();
} );

/* API Methods */

/**
 * Quick GitHub API method.
 *
 * @param string $endpoint The API endpoint to call.
 * @param mixed  $body     The body to send with the request.
 */
function api( $endpoint, $body = false, $method = '' ) {
	if ( ! str_starts_with( $endpoint, 'https://' ) ) {
		$endpoint = 'https://api.github.com/' . ltrim( $endpoint, '/' );
	}

	$method = $method ?: ( $body ? 'POST' : 'GET' );

	$args = array(
		'method' => $method,
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'BEARER ' . TOKEN,
			'Content-Type'         => 'application/json',
			'X-GitHub-Api-Version' => '2022-11-28'
		),
	);

	if ( $body ) {
		$args['body']   = json_encode( $body );
	}

	$response = wp_remote_request( $endpoint, $args );

	if ( is_wp_error( $response ) ) {
		// Matches the GitHub error format. Includes the HTTP response code only due to privacy concerns.
		return [
			'errors' => [
				[
					'message' => 'GitHub API error: ' . wp_remote_retrieve_response_code( $response )
				]
			]
		];
	}

	return json_decode( $response['body'] );
}

/**
 * Fetch the teams from the WordPress GitHub organization
 */
function get_teams() {
	$teams = get_site_transient( 'gh_teams', false );
	if ( false === $teams ) {
		$teams = api( '/orgs/' . ORG . '/teams?per_page=100' );

		set_site_transient( 'gh_teams', $teams, 5 * MINUTE_IN_SECONDS );
	}

	return $teams;
}

/**
 * Fetch the pending invites from the WordPress GitHub organization
 */
function get_pending_invites() {
	$invites = get_site_transient( 'gh_invites', false );
	if ( false === $invites ) {
		$invites = api( '/orgs/' . ORG . '/invitations' );

		set_site_transient( 'gh_invites', $invites, 5 * MINUTE_IN_SECONDS );
	}

	return $invites;
}

/**
 * Invite a member to the organisation, with specific Teams.
 *
 * @param int|string $who The GitHub user ID, or email of the user to invite.
 */
function invite_member( $who, array $team_ids ) {
	$args = [
		'role'       => 'direct_member',
		'team_ids'   => $team_ids
	];

	if ( is_int( $who ) ) {
		$args['invitee_id'] = $who;
	} else {
		$args['email'] = $who;
	}

	return api(
		'/orgs/' . ORG . '/invitations',
		$args
	);
}

/**
 * Cancel an invitation by ID
 */
function cancel_invite( $id ) {
	return api(
		'/orgs/' . ORG . '/invitations/' . $id,
		[],
		'DELETE'
	);
}