<?php
/**
 * Pulizia del backend per gli utenti target: bacheca, admin bar,
 * menu laterale e rifiniture della schermata.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * =====================================================================
 *  1) PULIZIA BACHECA
 * =====================================================================
 */
add_action( 'wp_dashboard_setup', 'db_role_ux_clean_dashboard', 9999 );
function db_role_ux_clean_dashboard() {

	if ( ! db_role_ux_user_is_targeted() ) {
		return;
	}

	$config = db_role_ux_config();

	// Modalità tabula rasa.
	if ( ! empty( $config['dashboard_nuke_all'] ) ) {
		global $wp_meta_boxes;
		$wp_meta_boxes['dashboard'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		db_role_ux_log( 'dashboard azzerata (nuke_all)' );
		return;
	}

	// Rimozione mirata dei widget dichiarati.
	if ( ! empty( $config['dashboard_widgets'] ) ) {
		foreach ( $config['dashboard_widgets'] as $widget ) {
			$id      = isset( $widget[0] ) ? $widget[0] : '';
			$context = isset( $widget[1] ) ? $widget[1] : 'normal';
			if ( '' === $id ) {
				continue;
			}
			remove_meta_box( $id, 'dashboard', $context );
			db_role_ux_log( "rimosso widget bacheca: {$id} ({$context})" );
		}
	}
}

/**
 * =====================================================================
 *  2) PULIZIA ADMIN BAR
 * =====================================================================
 */
add_action( 'admin_bar_menu', 'db_role_ux_clean_adminbar', 9999 );
function db_role_ux_clean_adminbar( $wp_admin_bar ) {

	if ( ! db_role_ux_user_is_targeted() ) {
		return;
	}

	$config = db_role_ux_config();
	if ( empty( $config['adminbar_nodes'] ) ) {
		return;
	}

	foreach ( $config['adminbar_nodes'] as $node_id ) {
		$wp_admin_bar->remove_node( $node_id );
		db_role_ux_log( "rimosso nodo admin bar: {$node_id}" );
	}
}

/**
 * =====================================================================
 *  3) PULIZIA MENU LATERALE
 * =====================================================================
 */
add_action( 'admin_menu', 'db_role_ux_clean_admin_menu', 9999 );
function db_role_ux_clean_admin_menu() {

	if ( ! db_role_ux_user_is_targeted() ) {
		return;
	}

	$config = db_role_ux_config();
	if ( empty( $config['admin_menu_pages'] ) ) {
		return;
	}

	foreach ( $config['admin_menu_pages'] as $page ) {
		remove_menu_page( $page );
		db_role_ux_log( "rimossa voce menu: {$page}" );
	}
}

/**
 * =====================================================================
 *  3b) RIFINITURE DELLA BACHECA per i non-admin
 *      - nasconde i tab "Impostazioni schermata" e "Aiuto"
 *      - forza la bacheca a UNA sola colonna, eliminando le aree
 *        vuote con "Trascina i riquadri qui"
 * =====================================================================
 */

/**
 * Nasconde il tab "Aiuto" (contextual help) in alto a destra.
 * Restituendo false, WordPress non stampa il pulsante.
 */
add_filter( 'contextual_help', 'db_role_ux_hide_help', 999, 3 );
function db_role_ux_hide_help( $old_help, $screen_id, $screen ) {
	if ( db_role_ux_user_is_targeted() ) {
		// Rimuove tutti i tab di aiuto della schermata corrente.
		if ( is_object( $screen ) && method_exists( $screen, 'remove_help_tabs' ) ) {
			$screen->remove_help_tabs();
		}
		return false;
	}
	return $old_help;
}

/**
 * Nasconde il tab "Impostazioni schermata" (screen options).
 * Il filtro screen_options_show_screen accetta un booleano: false = nascondi.
 */
add_filter( 'screen_options_show_screen', 'db_role_ux_hide_screen_options' );
function db_role_ux_hide_screen_options( $show ) {
	if ( db_role_ux_user_is_targeted() ) {
		return false;
	}
	return $show;
}

/**
 * Forza la bacheca a UNA sola colonna per i non-admin.
 * Con una sola colonna non esistono aree vuote, quindi sparisce
 * il segnaposto "Trascina i riquadri qui".
 *
 * Il filtro screen_layout_columns governa il numero di colonne;
 * 'get_user_option_screen_layout_dashboard' fissa la preferenza
 * dell'utente a 1 così WordPress non ne memorizza altre.
 */
add_filter( 'screen_layout_columns', 'db_role_ux_dashboard_one_column', 999, 2 );
function db_role_ux_dashboard_one_column( $columns, $screen_id ) {
	if ( 'dashboard' === $screen_id && db_role_ux_user_is_targeted() ) {
		$columns['dashboard'] = 1;
	}
	return $columns;
}

add_filter( 'get_user_option_screen_layout_dashboard', 'db_role_ux_force_one_column_pref' );
function db_role_ux_force_one_column_pref( $result ) {
	if ( db_role_ux_user_is_targeted() ) {
		return 1;
	}
	return $result;
}
