<?php
/**
 * Disinstallazione: rimuove le opzioni del plugin (impostazioni per ruolo e
 * widget rilevati), su ogni sito della rete in multisite.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$db_role_ux_options = array( 'db_role_ux_roles', 'db_role_ux_seen_widgets' );

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $db_role_ux_site ) {
		switch_to_blog( $db_role_ux_site );
		array_map( 'delete_option', $db_role_ux_options );
		restore_current_blog();
	}
} else {
	array_map( 'delete_option', $db_role_ux_options );
}
