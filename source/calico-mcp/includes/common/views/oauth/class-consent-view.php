<?php
/**
 * Renders the OAuth authorization (consent) screen.
 *
 * @package Miniorange_Secure_MCP_Server
 */

namespace CalicoMCP\Common\Views\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CalicoMCP\Common\Repositories\NHI_Store;
use CalicoMCP\Common\Services\Logging\Debug_Logger;

/**
 * Class Consent_View
 *
 * Presentation layer for the OAuth consent screen. The OAuth server gathers the
 * request parameters and client record and delegates the markup to this view.
 * The screen is scope-aware: it shows the connecting user exactly which abilities
 * their role(s) will expose to the client (or a clear empty state), using the same
 * resolver the MCP endpoint applies at request time.
 */
class Consent_View {

	/**
	 * Renders the consent screen and ends the request.
	 *
	 * @param array<string, string> $params The collected OAuth parameters.
	 * @param array<string, mixed>  $client The registered client row.
	 * @return void
	 */
	public static function render( array $params, array $client ) {
		$user          = wp_get_current_user();
		$client_name   = '' !== $client['client_name'] ? $client['client_name'] : $params['client_id'];
		$site_name     = get_bloginfo( 'name' );
		$person        = '' !== $user->display_name ? $user->display_name : $user->user_login;
		$redirect_host = (string) wp_parse_url( $params['redirect_uri'], PHP_URL_HOST );
		$app_initial   = strtoupper( substr( $client_name, 0, 1 ) );
		$user_initial  = strtoupper( substr( $person, 0, 1 ) );

		// ── Role-scoped ability preview ─────────────────────────────────────────
		// Compute exactly what this user's role(s) will be granted, using the same
		// resolver the MCP endpoint applies. null = unrestricted (all abilities),
		// empty array = nothing, otherwise the specific ability names.
		$roles = ( isset( $user->roles ) && is_array( $user->roles ) ) ? array_values( $user->roles ) : array();

		$wp_roles   = wp_roles();
		$role_names = array();
		foreach ( $roles as $slug ) {
			$role_names[] = isset( $wp_roles->roles[ $slug ]['name'] ) ? $wp_roles->roles[ $slug ]['name'] : $slug;
		}
		$roles_label = ! empty( $role_names ) ? implode( ', ', $role_names ) : __( 'your role', 'calico-mcp' );

		$allowed      = NHI_Store::resolve_for_roles( $roles );
		$unrestricted = ( null === $allowed );
		$nhi_enabled  = NHI_Store::count_enabled();

		$ability_labels = array();
		$unregistered   = 0;
		if ( function_exists( 'wp_get_abilities' ) ) {
			$label_map = array();
			foreach ( wp_get_abilities() as $ab ) {
				$label_map[ $ab->get_name() ] = $ab->get_label();
			}
			if ( $unrestricted ) {
				$ability_labels = array_values( $label_map );
			} else {
				foreach ( (array) $allowed as $name ) {
					if ( isset( $label_map[ $name ] ) ) {
						$ability_labels[] = $label_map[ $name ];
						continue;
					}

					// No registry entry behind this grant, so the raw name stands in as the
					// label. The row renders identically to a real one, which is how this
					// screen can show a full, reassuring list of tools that tools/list will
					// never carry. Counted so the divergence is visible in the log.
					$ability_labels[] = $name;
					++$unregistered;
				}
			}
		}

		$has_abilities = $unrestricted || ! empty( $allowed );
		$ability_count = count( $ability_labels );

		Debug_Logger::info(
			Debug_Logger::CHANNEL_OAUTH,
			'Consent screen shown.',
			array(
				'client_id'           => $params['client_id'],
				'user_login'          => (string) $user->user_login,
				'roles'               => ! empty( $roles ) ? implode( ',', $roles ) : '(none)',
				'allowed_count'       => $unrestricted ? 'unrestricted' : count( (array) $allowed ),
				'listed_count'        => $ability_count,
				'unregistered_listed' => $unregistered,
			)
		);

		$consent_css = CALICOMCP_PLUGIN_DIR . 'views/public/css/consent.css';
		$consent_ver = file_exists( $consent_css ) ? (string) filemtime( $consent_css ) : CALICOMCP_VERSION;
		wp_enqueue_style(
			'calicomcp-consent',
			plugins_url( 'views/public/css/consent.css', CALICOMCP_PLUGIN_FILE ),
			array(),
			$consent_ver
		);

		nocache_headers();

		$check_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
		$warn_svg  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title><?php esc_html_e( 'Authorize access', 'calico-mcp' ); ?></title>
<?php wp_print_styles( array( 'calicomcp-consent' ) ); ?>
</head>
<body>
<main class="calicomcp-card" role="main" aria-labelledby="calicomcp-title">
	<div class="calicomcp-brand">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
		<?php esc_html_e( 'Calico MCP', 'calico-mcp' ); ?>
	</div>
	<div class="calicomcp-connect" aria-hidden="true">
		<div class="calicomcp-tile"><?php echo esc_html( $app_initial ); ?></div>
		<div class="calicomcp-link"><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span></div>
		<div class="calicomcp-tile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20Z"/></svg></div>
	</div>
	<h1 id="calicomcp-title"><?php esc_html_e( 'Authorize access', 'calico-mcp' ); ?></h1>
	<p class="calicomcp-sub">
		<?php
		printf(
			/* translators: 1: requesting application name, 2: site name. */
			esc_html__( '%1$s wants to connect to %2$s and use its AI abilities.', 'calico-mcp' ),
			'<strong>' . esc_html( $client_name ) . '</strong>',
			'<strong>' . esc_html( $site_name ) . '</strong>'
		);
		?>
	</p>

	<div class="calicomcp-scope<?php echo $has_abilities ? '' : ' calicomcp-scope--warn'; ?>">
		<?php if ( $unrestricted ) : ?>
			<div class="calicomcp-all"><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG literal. ?><span><?php esc_html_e( 'All registered abilities are available to your role.', 'calico-mcp' ); ?></span></div>
			<?php if ( ! empty( $ability_labels ) ) : ?>
			<ul class="calicomcp-abilities">
				<?php foreach ( $ability_labels as $label ) : ?>
				<li class="calicomcp-ability"><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG literal. ?><span class="calicomcp-ability__name"><?php echo esc_html( $label ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		<?php elseif ( $has_abilities ) : ?>
			<ul class="calicomcp-abilities">
				<?php foreach ( $ability_labels as $label ) : ?>
				<li class="calicomcp-ability"><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG literal. ?><span class="calicomcp-ability__name"><?php echo esc_html( $label ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="calicomcp-empty">
				<?php echo $warn_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG literal. ?>
				<div>
					<?php if ( 0 === $nhi_enabled ) : ?>
					<strong><?php esc_html_e( 'No access policy is enabled yet', 'calico-mcp' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: %s: the signed-in user's role name(s). */
							esc_html__( 'This site has no enabled NHI, so no tools are available to your role (%s). Please contact your site administrator to request access before connecting.', 'calico-mcp' ),
							esc_html( $roles_label )
						);
						?>
					</p>
					<?php else : ?>
					<strong><?php esc_html_e( 'No tools for your role', 'calico-mcp' ); ?></strong>
					<p>
						<?php esc_html_e( 'You don’t have any abilities assigned. Please contact your Site Administrator to request access before connecting.', 'calico-mcp' ); ?>
					</p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<p class="calicomcp-note">
		<?php esc_html_e( 'Every action runs as you and is limited to your role’s capabilities. Access tokens are stored as keyed hashes and expire automatically.', 'calico-mcp' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="calicomcp_authorize" />
		<?php
		foreach ( $params as $key => $value ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $key ), esc_attr( $value ) );
		}
		wp_nonce_field( 'calicomcp_authorize' );
		?>
		<div class="calicomcp-actions">
			<?php if ( $has_abilities ) : ?>
			<button type="submit" name="calicomcp_decision" value="approve" class="calicomcp-btn calicomcp-btn--primary">
				<?php esc_html_e( 'Allow access', 'calico-mcp' ); ?>
			</button>
			<button type="submit" name="calicomcp_decision" value="deny" class="calicomcp-btn calicomcp-btn--ghost">
				<?php esc_html_e( 'Deny', 'calico-mcp' ); ?>
			</button>
			<?php else : ?>
			<button type="submit" name="calicomcp_decision" value="deny" class="calicomcp-btn calicomcp-btn--primary">
				<?php esc_html_e( 'Cancel', 'calico-mcp' ); ?>
			</button>
			<?php endif; ?>
		</div>
	</form>
	<div class="calicomcp-user">
		<span class="calicomcp-ava"><?php echo esc_html( $user_initial ); ?></span>
		<span>
			<?php
			printf(
				/* translators: %s: current user display name. */
				esc_html__( 'Signed in as %s', 'calico-mcp' ),
				'<strong>' . esc_html( $person ) . '</strong>'
			);
			?>
		</span>
	</div>
		<?php if ( '' !== $redirect_host ) : ?>
	<p class="calicomcp-foot">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
		<span>
			<?php
			printf(
				/* translators: %s: redirect destination host. */
				esc_html__( 'You\'ll be returned to %s', 'calico-mcp' ),
				'<strong>' . esc_html( $redirect_host ) . '</strong>'
			);
			?>
		</span>
	</p>
			<?php endif; ?>
</main>
</body>
</html>
		<?php
		exit;
	}
}
