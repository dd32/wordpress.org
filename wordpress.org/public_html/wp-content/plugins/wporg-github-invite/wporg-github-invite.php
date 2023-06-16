<?php
/**
 * Plugin Name:       GitHub Invite Collaborator
 * Description:       Invite external collaborators to your GitHub organizations
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Jonathan Bossenger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gh-invite-collaborator
 *
 * @package           gh-invite-collaborator
 */

define( 'GH_INVITE_COLLABORATOR_TOKEN', '***PAT HERE***' );
define( 'GH_INVITE_COLLABORATOR_ORG', 'WordPress' );

/**
 * Fetch the teams from the WordPress GitHub organization
 */
function gh_invite_collaborator_get_teams() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array( 'error' => 'You do not have permission to do this' );
	}

	$args = array(
		'headers' => array(
			'Authorization' => 'Bearer ' . GH_INVITE_COLLABORATOR_TOKEN,
			'Content-Type' => 'application/json',
			'X-GitHub-Api-Version' => '2022-11-28'
		)
	);
	$response = wp_remote_get(
		'https://api.github.com/orgs/'.GH_INVITE_COLLABORATOR_ORG.'/teams',
		$args
	);

	if ( is_wp_error( $response ) ) {
		return array('error' => $response->get_error_message());
	}

	return json_decode( $response['body'] );
}

/**
 * Invite a collaborator to the Psykrotek-Org GitHub organization
 */
function gh_invite_collaborator( $email, $team_id ) {
	$args = array(
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . GH_INVITE_COLLABORATOR_TOKEN,
			'Content-Type'         => 'application/json',
			'X-GitHub-Api-Version' => '2022-11-28'
		),
		'body'    => json_encode (
			array(
				'email'    => $email,
				'role'     => 'direct_member',
				'team_ids' => array( (int) $team_id )
			)
		)
	);

	$response = wp_remote_post(
		'https://api.github.com/orgs/'.GH_INVITE_COLLABORATOR_ORG.'/invitations',
		$args
	);

	if ( is_wp_error( $response ) ) {
		return array( 'error' => $response->get_error_message() );
	}

	return json_decode( $response['body'] );
}

/**
 * Process the invitation form
 */
add_action('init', 'gh_invite_collaborator_maybe_process_form');
function gh_invite_collaborator_maybe_process_form() {
	if( ! isset($_POST['action']) || $_POST['action'] !== 'gh_invite_collaborator' ) {
		return;
	}

	$email   = sanitize_email( $_POST['email'] );
	$team_id = sanitize_text_field( $_POST['team_id'] );

	$result = gh_invite_collaborator( $email, $team_id );

	if ( isset( $result->error ) ) {
		error_log( print_r( $result, true ) );
		add_action( 'admin_notices', 'admin_notice_gh_invite_collaborator__error' );
		return;
	}
	add_action( 'admin_notices', 'admin_notice_gh_invite_collaborator' );
}

function admin_notice_gh_invite_collaborator(){
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php _e( 'Success, collaborator invitation sent!', 'gh-invite-collaborator' ); ?></p>
	</div>
	<?php
}

function admin_notice_gh_invite_collaborator__error(){
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php _e( 'An error occurred inviting this collaborator!', 'gh-invite-collaborator' ); ?></p>
	</div>
	<?php
}

add_action( 'admin_menu', 'gh_invite_collaborator_submenu', 11 );
function gh_invite_collaborator_submenu() {
	add_submenu_page(
		'tools.php',
		esc_html__( 'Invite Github Collaborator', 'gh-invite-collaborator' ),
		esc_html__( 'Invite Github Collaborator', 'gh-invite-collaborator' ),
		'manage_options',
		'gh_invite_collaborator',
		'gh_invite_collaborator_render_admin_page'
	);
}

function gh_invite_collaborator_render_admin_page() {
	$teams = gh_invite_collaborator_get_teams();
	?>
	<div class="wrap" id="wp_learn_admin">
		<h1>Invite Github Collaborator </h1>
		<form method="post" action="<?php echo admin_url( 'tools.php?page=gh_invite_collaborator' ) ?>">
			<input type="hidden" name="action" value="gh_invite_collaborator">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="email">Email</label></th>
					<td><input type="email" name="email" id="email" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="team">Team</label></th>
					<td>
						<select name="team_id" id="team_id">
							<?php foreach ( $teams as $team ) { ?>
								<option
									value="<?php echo esc_attr( $team->id ) ?>"><?php echo esc_html( $team->name ) ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Invite Collaborator' ); ?>
	</div>
	<?php
}
