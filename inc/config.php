<?php
/**
 * Configurazione dichiarativa e selezione degli utenti target.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * =====================================================================
 *  CONFIGURAZIONE DICHIARATIVA
 *  Tutto ciò che decide "cosa si nasconde e a chi" vive qui.
 *  Per cambiare policy si modifica questo array, non la logica sotto.
 * =====================================================================
 */
function db_role_ux_config() {

	$config = array(

		/*
		 * A CHI si applica la pulizia.
		 * 'target_mode' governa la selezione dell'utente:
		 *   'non_admin' → chiunque NON abbia la capability 'manage_options'
		 *   'roles'     → solo gli utenti nei ruoli elencati in 'target_roles'
		 *   'capability'→ solo gli utenti CHE HANNO la capability in 'target_capability'
		 *
		 * Scelta attuale: 'non_admin' (tutti i non-amministratori).
		 */
		'target_mode'        => 'non_admin',
		'target_roles'       => array( 'subscriber' ), // usato solo se target_mode = 'roles'
		'target_capability'  => 'db_backend_clean',    // usato solo se target_mode = 'capability'

		/*
		 * WIDGET DELLA BACHECA da rimuovere.
		 * Formato: array( id_widget, contesto ). Contesto: 'normal' | 'side' | 'column3' | 'column4'.
		 * NB: sono i widget custom rilevati in ricognizione (Circolari + Menu utente).
		 *
		 * DECISIONE (documentata): le circolari sono gestite dal REGISTRO
		 * ELETTRONICO, non da WordPress. I widget 0/1/2 in bacheca sono quindi
		 * ridondanti — duplicano un'informazione che l'utente ottiene già nel
		 * registro, dove effettivamente lavora. Vengono rimossi per eliminare
		 * il doppione. Se in futuro la gestione circolari rientrasse in
		 * WordPress, commentare le righe 0/1/2 per ripristinarle.
		 */
		'dashboard_widgets'  => array(
			array( '0', 'normal' ), // Circolari non firmate
			array( '1', 'normal' ), // Notifiche nuove circolari da leggere/firmare
			array( '2', 'normal' ), // Circolari firmate
			array( '3', 'normal' ), // Menu utente
		),

		/*
		 * Rimuovere TUTTI i widget bacheca residui, anche non elencati sopra?
		 * true = tabula rasa (utile se in futuro altri plugin ne aggiungono).
		 * Lasciato false: rimuoviamo solo ciò che dichiariamo, più prevedibile.
		 */
		'dashboard_nuke_all' => false,

		/*
		 * NODI DELLA ADMIN BAR da rimuovere (ID reali dalla ricognizione).
		 * Rimuovere un nodo genitore elimina anche i figli.
		 */
		'adminbar_nodes'     => array(
			'wp-logo',          // logo WP + tutto il sottomenu (About, Partecipa, .org, docs...)
			'updates',          // "44 aggiornamenti disponibili"
			'comments',         // commenti in moderazione
			'command-palette',  // ⌘K palette comandi
			'new-content',      // menu "+ Nuovo" con 20+ voci di creazione
			'design-scuole',    // gruppo "About Design Scuole" (tema)
			'design-scuole-conf', // "Configurazione" (tema)
			'manuale',          // voce "Manuale" del tema
		),

		/*
		 * VOCI DEL MENU LATERALE (admin) da rimuovere.
		 * Slug della pagina di primo livello. Vuoto per ora: da popolare
		 * quando decidiamo cosa un non-admin può vedere nel menu sinistro.
		 * Esempi: 'edit.php' (Articoli), 'edit-comments.php' (Commenti),
		 * 'tools.php' (Strumenti).
		 */
		'admin_menu_pages'   => array(),

		/*
		 * REDIRECT POST-LOGIN.
		 * enabled=false → in Fase 1 NON reindirizziamo (decisione rimandata).
		 * Quando lo accendi: 'url' assoluto o relativo alla home.
		 */
		'login_redirect'     => array(
			'enabled' => false,
			'url'     => home_url( '/' ),
		),

		/*
		 * DEBUG: se true, logga in error_log cosa viene rimosso e per quale utente.
		 * Da tenere false in produzione.
		 */
		'debug'              => false,
	);

	/*
	 * Filtro di estensione: consente di sovrascrivere la config senza
	 * toccare il file (utile per test o per un eventuale mu-plugin locale).
	 */
	return apply_filters( 'db_role_ux_config', $config );
}

/**
 * =====================================================================
 *  DECISIONE: chi è soggetto alla pulizia?
 *  Un solo punto di verità, usato per l'utente corrente, per l'utente
 *  passato da login_redirect e per l'anteprima per ruolo della pagina
 *  di riepilogo. FAIL-SAFE: chi ha manage_options è sempre escluso.
 * =====================================================================
 *
 * @param callable $has_cap Riceve una capability, restituisce bool.
 * @param string[] $roles   Ruoli del soggetto valutato.
 * @return bool
 */
function db_role_ux_policy_matches( $has_cap, array $roles ) {

	// Regola d'oro, non negoziabile: l'amministratore vede sempre tutto.
	if ( call_user_func( $has_cap, 'manage_options' ) ) {
		return false;
	}

	$config = db_role_ux_config();
	$mode   = isset( $config['target_mode'] ) ? $config['target_mode'] : 'non_admin';

	switch ( $mode ) {

		case 'capability':
			$cap = isset( $config['target_capability'] ) ? $config['target_capability'] : '';
			return $cap ? (bool) call_user_func( $has_cap, $cap ) : false;

		case 'roles':
			$target = isset( $config['target_roles'] ) ? (array) $config['target_roles'] : array();
			return (bool) array_intersect( $target, $roles );

		case 'non_admin':
		default:
			// Chiunque non sia admin (già escluso sopra).
			return true;
	}
}

/**
 * Un utente è soggetto alla pulizia?
 *
 * @param WP_User $user Utente da valutare.
 * @return bool
 */
function db_role_ux_user_matches( $user ) {

	if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
		return false;
	}

	return db_role_ux_policy_matches(
		function ( $cap ) use ( $user ) {
			return user_can( $user, $cap );
		},
		(array) $user->roles
	);
}

/**
 * Un ruolo è soggetto alla pulizia? Valuta le sole capability del ruolo:
 * un utente con capability aggiunte a mano può fare eccezione.
 *
 * @param string $role_slug Slug del ruolo.
 * @return bool
 */
function db_role_ux_role_matches( $role_slug ) {

	$role = get_role( $role_slug );
	if ( ! $role ) {
		return false;
	}

	return db_role_ux_policy_matches( array( $role, 'has_cap' ), array( $role_slug ) );
}

/**
 * L'utente corrente è soggetto alla pulizia?
 * Fuori dal contesto utente loggato non facciamo nulla.
 *
 * @return bool
 */
function db_role_ux_user_is_targeted() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return db_role_ux_user_matches( wp_get_current_user() );
}

/**
 * Piccolo helper di logging condizionale.
 *
 * @param string $message Messaggio.
 */
function db_role_ux_log( $message ) {
	$config = db_role_ux_config();
	if ( ! empty( $config['debug'] ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$user = wp_get_current_user();
		error_log( sprintf( '[db-role-ux] user#%d (%s): %s', $user->ID, implode( ',', (array) $user->roles ), $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
