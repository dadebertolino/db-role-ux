<?php
/**
 * Pagina di riepilogo "Strumenti → DB Role UX" (sola lettura).
 *
 * Mostra all'amministratore la policy attiva così com'è dopo il filtro
 * db_role_ux_config: chi viene ripulito (anteprima per ruolo), cosa viene
 * nascosto, il redirect post-login e le card del launchpad. Non salva
 * nulla: la configurazione resta nel codice e nei filtri.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug della pagina sotto Strumenti.
 */
const DB_ROLE_UX_PAGE = 'db-role-ux';

add_action( 'admin_menu', 'db_role_ux_register_admin_page' );
function db_role_ux_register_admin_page() {
	add_management_page(
		'DB Role UX',
		'DB Role UX',
		'manage_options',
		DB_ROLE_UX_PAGE,
		'db_role_ux_render_admin_page'
	);
}

add_action( 'admin_enqueue_scripts', 'db_role_ux_admin_assets' );
function db_role_ux_admin_assets( $hook ) {
	if ( 'tools_page_' . DB_ROLE_UX_PAGE !== $hook ) {
		return;
	}
	$url = plugin_dir_url( DB_ROLE_UX_FILE ) . 'assets/css/';
	wp_enqueue_style( 'db-admin-ui', $url . 'db-admin-ui.css', array(), '1.0.0' );
	wp_enqueue_style( 'db-role-ux-admin', $url . 'admin.css', array( 'db-admin-ui' ), DB_ROLE_UX_VERSION );
}

add_filter( 'plugin_action_links_' . plugin_basename( DB_ROLE_UX_FILE ), 'db_role_ux_action_links' );
function db_role_ux_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">Riepilogo</a>', esc_url( admin_url( 'tools.php?page=' . DB_ROLE_UX_PAGE ) ) )
		);
	}
	return $links;
}

/**
 * Descrizione leggibile della modalità di selezione.
 *
 * @param array $config Configurazione effettiva.
 * @return string
 */
function db_role_ux_describe_target( array $config ) {
	$mode = isset( $config['target_mode'] ) ? $config['target_mode'] : 'non_admin';

	switch ( $mode ) {
		case 'capability':
			$cap = isset( $config['target_capability'] ) ? (string) $config['target_capability'] : '';
			return '' === $cap
				? 'Per capability, ma nessuna capability è indicata: nessuno viene ripulito.'
				: sprintf( 'Solo chi ha la capability %s.', $cap );

		case 'roles':
			$roles = isset( $config['target_roles'] ) ? array_filter( (array) $config['target_roles'] ) : array();
			return empty( $roles )
				? 'Per ruolo, ma nessun ruolo è indicato: nessuno viene ripulito.'
				: sprintf( 'Solo i ruoli: %s.', implode( ', ', $roles ) );

		case 'non_admin':
		default:
			return 'Tutti gli utenti loggati che non sono amministratori.';
	}
}

/**
 * Anteprima per ruolo: per ogni ruolo registrato, nome, numero di utenti
 * e se viene ripulito.
 *
 * @return array[] Righe con slug, name, users, targeted, admin.
 */
function db_role_ux_role_preview() {
	$wp_roles = wp_roles();
	$counts   = count_users();
	$rows     = array();

	foreach ( $wp_roles->roles as $slug => $role ) {
		$role_obj = get_role( $slug );
		$rows[]   = array(
			'slug'     => $slug,
			'name'     => translate_user_role( $role['name'] ),
			'users'    => isset( $counts['avail_roles'][ $slug ] ) ? (int) $counts['avail_roles'][ $slug ] : 0,
			'targeted' => db_role_ux_role_matches( $slug ),
			'admin'    => $role_obj && $role_obj->has_cap( 'manage_options' ),
		);
	}

	return $rows;
}

/**
 * Ruoli (nomi leggibili) che hanno una capability.
 *
 * @param string $capability Capability.
 * @return string[]
 */
function db_role_ux_roles_with_cap( $capability ) {
	$names = array();
	foreach ( wp_roles()->roles as $slug => $role ) {
		$role_obj = get_role( $slug );
		if ( $role_obj && $role_obj->has_cap( $capability ) ) {
			$names[] = translate_user_role( $role['name'] );
		}
	}
	return $names;
}

/**
 * Render della pagina.
 */
function db_role_ux_render_admin_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non hai i permessi per vedere questa pagina.', 403 );
	}

	$config   = db_role_ux_config();
	$widgets  = isset( $config['dashboard_widgets'] ) ? (array) $config['dashboard_widgets'] : array();
	$nodes    = isset( $config['adminbar_nodes'] ) ? (array) $config['adminbar_nodes'] : array();
	$pages    = isset( $config['admin_menu_pages'] ) ? (array) $config['admin_menu_pages'] : array();
	$nuke_all = ! empty( $config['dashboard_nuke_all'] );
	$redirect = ! empty( $config['login_redirect']['enabled'] ) && ! empty( $config['login_redirect']['url'] );
	$roles    = db_role_ux_role_preview();
	$groups   = db_launchpad_cards();
	?>
	<div class="wrap db-role-ux-wrap">

		<div class="db-ui-page-header">
			<h1>DB Role UX</h1>
			<div class="db-ui-actions">
				<span class="db-ui-badge db-ui-badge-muted">Sola lettura</span>
				<span class="db-ui-badge db-ui-badge-muted">v<?php echo esc_html( DB_ROLE_UX_VERSION ); ?></span>
			</div>
		</div>

		<div class="db-ui-alert db-ui-alert-info" role="note">
			<span class="db-ui-alert-icon" aria-hidden="true">ℹ️</span>
			<span>Questa pagina mostra la policy attiva. Chi ha <code>manage_options</code> vede sempre tutto: la pulizia colpisce solo chi ha meno privilegi. Per cambiare la policy usa il filtro <code>db_role_ux_config</code> (vedi il README).</span>
		</div>

		<div class="db-role-ux-stats">
			<div class="db-ui-stat">
				<span class="db-ui-stat-icon db-ui-stat-icon-primary" aria-hidden="true">👥</span>
				<div>
					<div class="db-ui-stat-value"><?php echo esc_html( count( array_filter( wp_list_pluck( $roles, 'targeted' ) ) ) ); ?> / <?php echo esc_html( count( $roles ) ); ?></div>
					<div class="db-ui-stat-label">Ruoli ripuliti</div>
				</div>
			</div>
			<div class="db-ui-stat">
				<span class="db-ui-stat-icon" aria-hidden="true">🧩</span>
				<div>
					<div class="db-ui-stat-value"><?php echo $nuke_all ? 'Tutti' : esc_html( count( $widgets ) ); ?></div>
					<div class="db-ui-stat-label">Widget della bacheca rimossi</div>
				</div>
			</div>
			<div class="db-ui-stat">
				<span class="db-ui-stat-icon" aria-hidden="true">▤</span>
				<div>
					<div class="db-ui-stat-value"><?php echo esc_html( count( $nodes ) + count( $pages ) ); ?></div>
					<div class="db-ui-stat-label">Voci di admin bar e menu rimosse</div>
				</div>
			</div>
			<div class="db-ui-stat">
				<span class="db-ui-stat-icon <?php echo $redirect ? 'db-ui-stat-icon-success' : ''; ?>" aria-hidden="true">↪</span>
				<div>
					<div class="db-ui-stat-value"><?php echo $redirect ? 'Attivo' : 'Spento'; ?></div>
					<div class="db-ui-stat-label">Redirect dopo il login</div>
				</div>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Chi viene ripulito</h2></div>
			<div class="db-ui-card-body">
				<p><?php echo esc_html( db_role_ux_describe_target( $config ) ); ?></p>
				<table class="db-ui-table">
					<thead>
						<tr>
							<th scope="col">Ruolo</th>
							<th scope="col">Utenti</th>
							<th scope="col">Backend</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $roles as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['name'] ); ?> <code><?php echo esc_html( $row['slug'] ); ?></code></td>
								<td><?php echo esc_html( $row['users'] ); ?></td>
								<td>
									<?php if ( $row['targeted'] ) : ?>
										<span class="db-ui-badge db-ui-badge-success">Semplificato</span>
									<?php elseif ( $row['admin'] ) : ?>
										<span class="db-ui-badge db-ui-badge-muted">Completo (amministratore)</span>
									<?php else : ?>
										<span class="db-ui-badge db-ui-badge-muted">Completo</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="db-role-ux-note">L'anteprima valuta le capability del ruolo. Un utente con capability aggiunte a mano può fare eccezione.</p>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Cosa viene nascosto</h2></div>
			<div class="db-ui-card-body db-role-ux-columns">
				<div>
					<h3>Widget della bacheca</h3>
					<?php if ( $nuke_all ) : ?>
						<p>Tutti (<code>dashboard_nuke_all</code>).</p>
					<?php elseif ( empty( $widgets ) ) : ?>
						<p class="db-role-ux-none">Nessuno.</p>
					<?php else : ?>
						<ul>
							<?php foreach ( $widgets as $widget ) : ?>
								<li><code><?php echo esc_html( isset( $widget[0] ) ? $widget[0] : '' ); ?></code> (<?php echo esc_html( isset( $widget[1] ) ? $widget[1] : 'normal' ); ?>)</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div>
					<h3>Admin bar</h3>
					<?php if ( empty( $nodes ) ) : ?>
						<p class="db-role-ux-none">Nessuna voce.</p>
					<?php else : ?>
						<ul>
							<?php foreach ( $nodes as $node ) : ?>
								<li><code><?php echo esc_html( $node ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div>
					<h3>Menu laterale</h3>
					<?php if ( empty( $pages ) ) : ?>
						<p class="db-role-ux-none">Nessuna voce.</p>
					<?php else : ?>
						<ul>
							<?php foreach ( $pages as $page ) : ?>
								<li><code><?php echo esc_html( $page ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div>
					<h3>Sempre</h3>
					<ul>
						<li>Pulsanti <em>Aiuto</em> e <em>Impostazioni schermata</em></li>
						<li>Bacheca su una sola colonna</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Redirect dopo il login</h2></div>
			<div class="db-ui-card-body">
				<?php if ( $redirect ) : ?>
					<p>Gli utenti ripuliti arrivano su <code><?php echo esc_html( $config['login_redirect']['url'] ); ?></code>. Gli amministratori seguono il comportamento standard di WordPress.</p>
				<?php else : ?>
					<p>Spento: tutti seguono il comportamento standard di WordPress.</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Area riservata</h2></div>
			<div class="db-ui-card-body">
				<p>Inserisci lo shortcode <code>[db_area_riservata]</code> in una pagina. Ogni gruppo di card compare solo a chi ha la capability indicata. Le card si modificano con il filtro <code>db_launchpad_cards</code>.</p>
				<table class="db-ui-table">
					<thead>
						<tr>
							<th scope="col">Gruppo</th>
							<th scope="col">Capability</th>
							<th scope="col">Card</th>
							<th scope="col">Ruoli che lo vedono</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $groups as $capability => $group ) : ?>
							<?php $who = db_role_ux_roles_with_cap( $capability ); ?>
							<tr>
								<td><?php echo esc_html( isset( $group['label'] ) ? $group['label'] : $capability ); ?></td>
								<td><code><?php echo esc_html( $capability ); ?></code></td>
								<td><?php echo esc_html( isset( $group['cards'] ) ? count( (array) $group['cards'] ) : 0 ); ?></td>
								<td>
									<?php if ( empty( $who ) ) : ?>
										<span class="db-ui-badge db-ui-badge-warning">Nessun ruolo</span>
									<?php else : ?>
										<?php echo esc_html( implode( ', ', $who ) ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

	</div>
	<?php
}
