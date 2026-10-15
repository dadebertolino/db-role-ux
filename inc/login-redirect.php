<?php
/**
 * Redirect post-login degli utenti target (disattivato di default in Fase 1).
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * =====================================================================
 *  4) REDIRECT POST-LOGIN  (disattivato di default in Fase 1)
 * =====================================================================
 */
add_filter( 'login_redirect', 'db_role_ux_login_redirect', 10, 3 );
function db_role_ux_login_redirect( $redirect_to, $requested_redirect_to, $user ) {

	$config = db_role_ux_config();

	// Disattivato → comportamento standard di WordPress.
	if ( empty( $config['login_redirect']['enabled'] ) ) {
		return $redirect_to;
	}

	// Il targeting si valuta sull'oggetto $user passato, non su current_user
	// (al login l'utente corrente non è ancora impostato). Gli admin e gli
	// oggetti non validi (WP_Error) non vengono mai reindirizzati.
	if ( db_role_ux_user_matches( $user ) && ! empty( $config['login_redirect']['url'] ) ) {
		return $config['login_redirect']['url'];
	}

	return $redirect_to;
}
