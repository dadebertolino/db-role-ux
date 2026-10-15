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
	// Anteprima della fascia di benvenuto con gli stessi stili della bacheca.
	wp_enqueue_style( 'db-role-ux-dashboard', $url . 'dashboard.css', array(), DB_ROLE_UX_VERSION );
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_media();
	wp_enqueue_script( 'db-role-ux-admin', plugin_dir_url( DB_ROLE_UX_FILE ) . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), DB_ROLE_UX_VERSION, true );
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
 * URL di una scheda della pagina.
 *
 * @param string $tab  Scheda: riepilogo | bacheca.
 * @param array  $args Parametri aggiuntivi.
 * @return string
 */
function db_role_ux_page_url( $tab = 'riepilogo', array $args = array() ) {
	return add_query_arg(
		array_merge(
			array(
				'page' => DB_ROLE_UX_PAGE,
				'tab'  => $tab,
			),
			$args
		),
		admin_url( 'tools.php' )
	);
}

/**
 * Render della pagina: intestazione, schede, contenuto della scheda.
 */
function db_role_ux_render_admin_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non hai i permessi per vedere questa pagina.', 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo navigazione fra schede.
	$tab  = isset( $_GET['tab'] ) && 'bacheca' === $_GET['tab'] ? 'bacheca' : 'riepilogo';
	$tabs = array(
		'riepilogo' => 'Riepilogo',
		'bacheca'   => 'Bacheca per ruolo',
	);
	?>
	<div class="wrap db-role-ux-wrap">

		<div class="db-ui-page-header">
			<h1>DB Role UX</h1>
			<div class="db-ui-actions">
				<span class="db-ui-badge db-ui-badge-muted">v<?php echo esc_html( DB_ROLE_UX_VERSION ); ?></span>
			</div>
		</div>

		<nav class="nav-tab-wrapper db-role-ux-tabs" aria-label="Sezioni di DB Role UX">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( db_role_ux_page_url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php
		if ( 'bacheca' === $tab ) {
			db_role_ux_render_role_editor();
		} else {
			db_role_ux_render_summary();
		}
		?>
	</div>
	<?php
}

/**
 * Scheda Riepilogo: la policy attiva, in sola lettura.
 */
function db_role_ux_render_summary() {
	$config   = db_role_ux_config();
	$widgets  = isset( $config['dashboard_widgets'] ) ? (array) $config['dashboard_widgets'] : array();
	$nodes    = isset( $config['adminbar_nodes'] ) ? (array) $config['adminbar_nodes'] : array();
	$pages    = isset( $config['admin_menu_pages'] ) ? (array) $config['admin_menu_pages'] : array();
	$nuke_all = ! empty( $config['dashboard_nuke_all'] );
	$redirect = ! empty( $config['login_redirect']['enabled'] ) && ! empty( $config['login_redirect']['url'] );
	$roles    = db_role_ux_role_preview();
	$groups   = db_launchpad_cards();
	?>
		<div class="db-ui-alert db-ui-alert-info" role="note">
			<span class="db-ui-alert-icon" aria-hidden="true">ℹ️</span>
			<span>Questa pagina mostra la policy attiva. Chi ha <code>manage_options</code> vede sempre tutto: la pulizia colpisce solo chi ha meno privilegi. La pulizia globale si cambia con il filtro <code>db_role_ux_config</code> (vedi il README); widget e link per ruolo si gestiscono nella scheda <em>Bacheca per ruolo</em>.</span>
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
							<th scope="col">Bacheca per ruolo</th>
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
								<td><a href="<?php echo esc_url( db_role_ux_page_url( 'bacheca', array( 'role' => $row['slug'] ) ) ); ?>"><?php echo esc_html( db_role_ux_describe_role_settings( isset( $config['roles'][ $row['slug'] ] ) ? $config['roles'][ $row['slug'] ] : array() ) ); ?></a></td>
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

	<?php
}

/**
 * Sintesi delle impostazioni di un ruolo, per il riepilogo.
 *
 * @param array $settings Impostazioni del ruolo.
 * @return string
 */
function db_role_ux_describe_role_settings( $settings ) {
	$settings = array_merge( db_role_ux_empty_role_settings(), (array) $settings );
	$parts    = array();
	$counts   = array(
		'widgets'        => array( 'widget aggiunto', 'widget aggiunti' ),
		'hide_widgets'   => array( 'widget nascosto', 'widget nascosti' ),
		'menu_links'     => array( 'voce di menu', 'voci di menu' ),
		'adminbar_links' => array( 'voce in admin bar', 'voci in admin bar' ),
	);
	foreach ( $counts as $key => $labels ) {
		$n = count( (array) $settings[ $key ] );
		if ( $n ) {
			$parts[] = $n . ' ' . ( 1 === $n ? $labels[0] : $labels[1] );
		}
	}
	if ( ! empty( $settings['banner'] ) ) {
		$parts[] = 'fascia di benvenuto';
	}
	return empty( $parts ) ? 'Personalizza' : implode( ', ', $parts );
}

/**
 * =====================================================================
 *  SCHEDA "BACHECA PER RUOLO"
 *  Un modulo per ruolo. Senza JavaScript tutti i campi dei widget sono
 *  visibili; con JavaScript si vedono solo quelli del tipo scelto.
 * =====================================================================
 */
function db_role_ux_render_role_editor() {
	$wp_roles = wp_roles();
	$slugs    = array_keys( $wp_roles->roles );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo scelta del ruolo da mostrare.
	$current = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';
	if ( ! in_array( $current, $slugs, true ) ) {
		$current = in_array( 'subscriber', $slugs, true ) ? 'subscriber' : reset( $slugs );
	}

	$stored   = db_role_ux_stored_roles();
	$settings = isset( $stored[ $current ] ) ? $stored[ $current ] : db_role_ux_empty_role_settings();
	$role_obj = get_role( $current );
	$is_admin = $role_obj && $role_obj->has_cap( 'manage_options' );
	$seen     = db_role_ux_seen_widgets();
	$groups   = db_launchpad_cards();
	$config   = db_role_ux_config();
	$name     = translate_user_role( $wp_roles->roles[ $current ]['name'] );

	uasort(
		$seen,
		function ( $a, $b ) {
			return strcasecmp( $a['title'], $b['title'] );
		}
	);
	$extra_hidden = array_diff( $settings['hide_widgets'], array_map( 'strval', array_keys( $seen ) ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- esito del salvataggio, solo messaggio.
	$saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
	if ( 'ok' === $saved ) {
		echo '<div class="db-ui-alert db-ui-alert-success" role="status"><span class="db-ui-alert-icon" aria-hidden="true">✅</span><span>Impostazioni del ruolo salvate.</span></div>';
	} elseif ( 'reset' === $saved ) {
		echo '<div class="db-ui-alert db-ui-alert-success" role="status"><span class="db-ui-alert-icon" aria-hidden="true">✅</span><span>Impostazioni del ruolo azzerate.</span></div>';
	}
	if ( has_filter( 'db_role_ux_config' ) && ( isset( $config['roles'][ $current ] ) ? $config['roles'][ $current ] : null ) !== ( isset( $stored[ $current ] ) ? $stored[ $current ] : null ) ) {
		echo '<div class="db-ui-alert db-ui-alert-warning" role="note"><span class="db-ui-alert-icon" aria-hidden="true">⚠️</span><span>Un filtro <code>db_role_ux_config</code> modifica le impostazioni di questo ruolo: in bacheca vale il filtro, non quanto salvato qui.</span></div>';
	}
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" class="db-role-ux-role-picker">
		<input type="hidden" name="page" value="<?php echo esc_attr( DB_ROLE_UX_PAGE ); ?>">
		<input type="hidden" name="tab" value="bacheca">
		<label for="db-role-ux-role">Ruolo</label>
		<select name="role" id="db-role-ux-role">
			<?php foreach ( $wp_roles->roles as $slug => $role ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $current ); ?>><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button">Mostra</button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="db-role-ux-editor">
		<input type="hidden" name="action" value="db_role_ux_save_role">
		<input type="hidden" name="role" value="<?php echo esc_attr( $current ); ?>">
		<?php wp_nonce_field( 'db_role_ux_save_role_' . $current ); ?>

		<?php db_role_ux_render_banner_fields( $settings['banner'], $name ); ?>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Widget da nascondere — <?php echo esc_html( $name ); ?></h2></div>
			<div class="db-ui-card-body">
				<?php if ( $is_admin ) : ?>
					<div class="db-ui-alert db-ui-alert-info" role="note"><span class="db-ui-alert-icon" aria-hidden="true">ℹ️</span><span>Questo ruolo ha <code>manage_options</code>: per il principio fail-safe non gli si nasconde nulla. Widget e link aggiunti qui sotto valgono comunque.</span></div>
				<?php else : ?>
					<?php if ( empty( $seen ) ) : ?>
						<p class="db-role-ux-none">Nessun widget rilevato finora. I widget vengono rilevati quando qualcuno apre la bacheca: aprila e torna qui, oppure scrivi gli ID qui sotto.</p>
					<?php else : ?>
						<fieldset>
							<legend class="screen-reader-text">Widget rilevati in bacheca</legend>
							<ul class="db-role-ux-checklist">
								<?php foreach ( $seen as $id => $info ) : ?>
									<?php $field_id = 'db-role-ux-hide-' . sanitize_html_class( (string) $id ); ?>
									<li>
										<input type="checkbox" name="hide_widgets[]" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $id ); ?>"<?php checked( in_array( (string) $id, $settings['hide_widgets'], true ) ); ?>>
										<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $info['title'] ); ?> <code><?php echo esc_html( $id ); ?></code></label>
									</li>
								<?php endforeach; ?>
							</ul>
						</fieldset>
					<?php endif; ?>
					<p class="db-ui-field">
						<label for="db-role-ux-hide-extra">Altri ID da nascondere (uno per riga)</label>
						<textarea name="hide_extra" id="db-role-ux-hide-extra" rows="2" class="large-text code"><?php echo esc_textarea( implode( "\n", $extra_hidden ) ); ?></textarea>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Widget aggiunti</h2></div>
			<div class="db-ui-card-body">
				<p class="db-role-ux-note">I link si scrivono uno per riga: <code>Etichetta | URL</code>. L'URL può essere assoluto (<code>https://…</code>), una pagina del sito (<code>/area-riservata/</code>) o una pagina del backend (<code>edit.php?post_type=page</code>).</p>
				<?php
				$widgets   = $settings['widgets'];
				$widgets[] = null; // Modulo vuoto per un nuovo widget.
				foreach ( $widgets as $i => $widget ) {
					db_role_ux_render_widget_fields( $i, $widget, $groups, $role_obj );
				}
				?>
			</div>
		</div>

		<div class="db-ui-card">
			<div class="db-ui-card-header"><h2>Link nel menu laterale e nella admin bar</h2></div>
			<div class="db-ui-card-body db-role-ux-columns">
				<p class="db-ui-field">
					<label for="db-role-ux-menu-links">Menu laterale, sotto <em>Bacheca</em></label>
					<textarea name="menu_links" id="db-role-ux-menu-links" rows="4" class="large-text code" aria-describedby="db-role-ux-menu-help"><?php echo esc_textarea( db_role_ux_format_links( $settings['menu_links'], true ) ); ?></textarea>
					<span class="db-ui-field-help" id="db-role-ux-menu-help"><code>Etichetta | URL | icona</code>; l'icona (facoltativa) è un nome <a href="https://developer.wordpress.org/resource/dashicons/">Dashicons</a>, per esempio <code>dashicons-calendar</code>.</span>
				</p>
				<p class="db-ui-field">
					<label for="db-role-ux-adminbar-links">Admin bar (backend e sito)</label>
					<textarea name="adminbar_links" id="db-role-ux-adminbar-links" rows="4" class="large-text code" aria-describedby="db-role-ux-adminbar-help"><?php echo esc_textarea( db_role_ux_format_links( $settings['adminbar_links'] ) ); ?></textarea>
					<span class="db-ui-field-help" id="db-role-ux-adminbar-help"><code>Etichetta | URL</code></span>
				</p>
			</div>
		</div>

		<p class="db-role-ux-submit">
			<button type="submit" class="button button-primary" name="do" value="save">Salva <?php echo esc_html( $name ); ?></button>
			<button type="submit" class="button" name="do" value="reset">Azzera le impostazioni del ruolo</button>
		</p>
	</form>
	<?php
}

/**
 * Campi della fascia di benvenuto, con anteprima di quella salvata.
 *
 * @param array  $banner    Fascia salvata (array vuoto = nessuna).
 * @param string $role_name Nome leggibile del ruolo.
 */
function db_role_ux_render_banner_fields( array $banner, $role_name ) {
	$enabled = ! empty( $banner );
	$banner  = array_merge(
		array(
			'logo'    => 'site',
			'logo_id' => 0,
			'title'   => '',
			'text'    => '',
			'color'   => '',
		),
		$banner
	);
	$preview = $enabled ? db_role_ux_render_banner(
		$banner,
		array(
			'{nome}'  => 'Nome Cognome',
			'{ruolo}' => $role_name,
			'{sito}'  => get_bloginfo( 'name' ),
		)
	) : '';
	$thumb   = $banner['logo_id'] ? wp_get_attachment_image( $banner['logo_id'], 'thumbnail', false, array( 'alt' => '' ) ) : '';
	?>
	<div class="db-ui-card">
		<div class="db-ui-card-header"><h2>Fascia di benvenuto — <?php echo esc_html( $role_name ); ?></h2></div>
		<div class="db-ui-card-body">
			<p>
				<input type="checkbox" name="banner[enabled]" id="db-role-ux-banner-enabled" value="1"<?php checked( $enabled ); ?>>
				<label for="db-role-ux-banner-enabled">Mostra una fascia di benvenuto in cima alla bacheca di questo ruolo</label>
			</p>

			<?php if ( $preview ) : ?>
				<div class="db-role-ux-preview">
					<p class="db-role-ux-note">Anteprima, con un nome di esempio:</p>
					<?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape in db_role_ux_render_banner(). ?>
				</div>
			<?php endif; ?>

			<fieldset class="db-ui-field db-role-ux-banner-logo">
				<legend>Logo</legend>
				<?php foreach ( DB_ROLE_UX_BANNER_LOGOS as $value => $label ) : ?>
					<label class="db-role-ux-radio">
						<input type="radio" name="banner[logo]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $value, $banner['logo'] ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<?php if ( ! get_theme_mod( 'custom_logo' ) ) : ?>
					<span class="db-ui-field-help">Il sito non ha un logo (Aspetto → Personalizza → Identità del sito): con <em>Logo del sito</em> la fascia resta senza immagine.</span>
				<?php endif; ?>
				<p class="db-role-ux-media">
					<label for="db-role-ux-banner-logo-id">ID dell'immagine</label>
					<input type="number" min="0" step="1" class="small-text" name="banner[logo_id]" id="db-role-ux-banner-logo-id" value="<?php echo esc_attr( $banner['logo_id'] ? $banner['logo_id'] : '' ); ?>">
					<button type="button" class="button db-role-ux-media-pick" hidden>Scegli dalla Libreria media</button>
					<span class="db-role-ux-media-thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup di wp_get_attachment_image(). ?></span>
				</p>
			</fieldset>

			<p class="db-ui-field">
				<label for="db-role-ux-banner-title">Titolo</label>
				<input type="text" class="large-text" name="banner[title]" id="db-role-ux-banner-title" value="<?php echo esc_attr( $banner['title'] ); ?>" aria-describedby="db-role-ux-banner-help">
			</p>
			<p class="db-ui-field">
				<label for="db-role-ux-banner-text">Testo (HTML di base ammesso)</label>
				<textarea name="banner[text]" id="db-role-ux-banner-text" rows="3" class="large-text" aria-describedby="db-role-ux-banner-help"><?php echo esc_textarea( $banner['text'] ); ?></textarea>
				<span class="db-ui-field-help" id="db-role-ux-banner-help">Segnaposto: <code>{nome}</code> (nome dell'utente), <code>{ruolo}</code> (i suoi ruoli), <code>{sito}</code> (nome del sito).</span>
			</p>
			<p class="db-ui-field">
				<label for="db-role-ux-banner-color">Colore d'accento (barra laterale)</label>
				<input type="text" class="db-role-ux-color" name="banner[color]" id="db-role-ux-banner-color" value="<?php echo esc_attr( $banner['color'] ); ?>" placeholder="#2271b1" pattern="#([0-9a-fA-F]{3}){1,2}">
			</p>
		</div>
	</div>
	<?php
}

/**
 * Campi di un widget aggiunto (o del modulo vuoto per uno nuovo).
 *
 * @param int          $i        Indice nel modulo.
 * @param array|null   $widget   Widget salvato, null = nuovo.
 * @param array        $groups   Gruppi di card dell'Area riservata.
 * @param WP_Role|null $role_obj Ruolo modificato.
 */
function db_role_ux_render_widget_fields( $i, $widget, array $groups, $role_obj ) {
	$is_new = null === $widget;
	$widget = array_merge(
		array(
			'id'      => '',
			'type'    => 'links',
			'title'   => '',
			'context' => 'normal',
			'links'   => array(),
			'content' => '',
			'groups'  => array(),
		),
		(array) $widget
	);
	$name   = 'widgets[' . (int) $i . ']';
	$prefix = 'db-role-ux-w' . (int) $i;
	?>
	<fieldset class="db-role-ux-widget" data-type="<?php echo esc_attr( $widget['type'] ); ?>">
		<legend><?php echo $is_new ? 'Nuovo widget' : esc_html( $widget['title'] ); ?></legend>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $widget['id'] ); ?>">

		<div class="db-role-ux-widget__row">
			<p class="db-ui-field">
				<label for="<?php echo esc_attr( $prefix ); ?>-title">Titolo<?php echo $is_new ? ' (lascia vuoto per non aggiungere)' : ''; ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[title]" id="<?php echo esc_attr( $prefix ); ?>-title" value="<?php echo esc_attr( $widget['title'] ); ?>">
			</p>
			<p class="db-ui-field">
				<label for="<?php echo esc_attr( $prefix ); ?>-type">Tipo</label>
				<select name="<?php echo esc_attr( $name ); ?>[type]" id="<?php echo esc_attr( $prefix ); ?>-type" class="db-role-ux-widget__type">
					<?php foreach ( DB_ROLE_UX_WIDGET_TYPES as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $type, $widget['type'] ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="db-ui-field">
				<label for="<?php echo esc_attr( $prefix ); ?>-context">Colonna</label>
				<select name="<?php echo esc_attr( $name ); ?>[context]" id="<?php echo esc_attr( $prefix ); ?>-context">
					<option value="normal"<?php selected( 'normal', $widget['context'] ); ?>>Principale</option>
					<option value="side"<?php selected( 'side', $widget['context'] ); ?>>Laterale</option>
				</select>
			</p>
		</div>

		<p class="db-ui-field" data-for-type="links">
			<label for="<?php echo esc_attr( $prefix ); ?>-links">Link (tipo <em>Elenco di link</em>)</label>
			<textarea name="<?php echo esc_attr( $name ); ?>[links]" id="<?php echo esc_attr( $prefix ); ?>-links" rows="4" class="large-text code"><?php echo esc_textarea( db_role_ux_format_links( $widget['links'] ) ); ?></textarea>
		</p>

		<p class="db-ui-field" data-for-type="text">
			<label for="<?php echo esc_attr( $prefix ); ?>-content">Testo (tipo <em>Testo libero</em>, HTML di base ammesso)</label>
			<textarea name="<?php echo esc_attr( $name ); ?>[content]" id="<?php echo esc_attr( $prefix ); ?>-content" rows="4" class="large-text"><?php echo esc_textarea( $widget['content'] ); ?></textarea>
		</p>

		<fieldset class="db-ui-field" data-for-type="cards">
			<legend>Gruppi di card (tipo <em>Card dell'Area riservata</em>)</legend>
			<ul class="db-role-ux-checklist">
				<?php foreach ( $groups as $capability => $group ) : ?>
					<?php $field_id = $prefix . '-group-' . sanitize_html_class( $capability ); ?>
					<li>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[groups][]" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $capability ); ?>"<?php checked( in_array( $capability, (array) $widget['groups'], true ) ); ?>>
						<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( isset( $group['label'] ) ? $group['label'] : $capability ); ?> <code><?php echo esc_html( $capability ); ?></code></label>
						<?php if ( $role_obj && ! $role_obj->has_cap( $capability ) ) : ?>
							<span class="db-ui-badge db-ui-badge-warning">il ruolo non ha questa capability: il gruppo non compare</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>

		<?php if ( ! $is_new ) : ?>
			<p>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete]" id="<?php echo esc_attr( $prefix ); ?>-delete" value="1">
				<label for="<?php echo esc_attr( $prefix ); ?>-delete">Elimina questo widget</label>
			</p>
		<?php endif; ?>
	</fieldset>
	<?php
}

/**
 * Impostazioni grezze del ruolo dal modulo inviato (già senza slash).
 *
 * @param array $post Dati del modulo.
 * @return array
 */
function db_role_ux_role_settings_from_post( array $post ) {
	$hide = isset( $post['hide_widgets'] ) ? (array) $post['hide_widgets'] : array();
	if ( isset( $post['hide_extra'] ) ) {
		$hide = array_merge( $hide, preg_split( '/[\s,]+/', (string) $post['hide_extra'] ) );
	}

	$widgets = array();
	foreach ( isset( $post['widgets'] ) ? (array) $post['widgets'] : array() as $widget ) {
		if ( ! is_array( $widget ) || ! empty( $widget['delete'] ) ) {
			continue;
		}
		$widget['links'] = db_role_ux_parse_links( isset( $widget['links'] ) ? $widget['links'] : '' );
		$widgets[]       = $widget;
	}

	return array(
		'hide_widgets'   => $hide,
		'widgets'        => $widgets,
		'menu_links'     => db_role_ux_parse_links( isset( $post['menu_links'] ) ? $post['menu_links'] : '', true ),
		'adminbar_links' => db_role_ux_parse_links( isset( $post['adminbar_links'] ) ? $post['adminbar_links'] : '' ),
		'banner'         => isset( $post['banner'] ) ? $post['banner'] : array(),
	);
}

/**
 * Salvataggio del modulo (admin-post.php?action=db_role_ux_save_role).
 */
add_action( 'admin_post_db_role_ux_save_role', 'db_role_ux_handle_save_role' );
function db_role_ux_handle_save_role() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Non hai i permessi per modificare queste impostazioni.', 403 );
	}

	$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
	check_admin_referer( 'db_role_ux_save_role_' . $role );
	if ( ! get_role( $role ) ) {
		wp_die( 'Ruolo inesistente.', 400 );
	}

	if ( isset( $_POST['do'] ) && 'reset' === $_POST['do'] ) {
		db_role_ux_save_role_settings( $role, array() );
		$result = 'reset';
	} else {
		// Ogni campo è sanitizzato da db_role_ux_sanitize_role_settings().
		db_role_ux_save_role_settings( $role, db_role_ux_role_settings_from_post( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = 'ok';
	}

	wp_safe_redirect(
		db_role_ux_page_url(
			'bacheca',
			array(
				'role'  => $role,
				'saved' => $result,
			)
		)
	);
	exit;
}
