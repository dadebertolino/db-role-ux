<?php
/**
 * Plugin Name:       DB Role UX
 * Plugin URI:        https://www.davidebertolino.it/progetti/
 * Description:       Esperienza backend semplificata per gli utenti non-amministratori: pulizia della bacheca, della admin bar e del menu laterale, più il launchpad "Area riservata" per ruolo. Guidato da configurazione, fail-safe verso l'amministratore.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Davide Bertolino
 * Author URI:        https://www.davidebertolino.it
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       db-role-ux
 *
 * ------------------------------------------------------------------
 *  FASE 1 della governance UX. Agnostico rispetto alla FONTE del ruolo:
 *  reagisce a una capability / a un insieme di ruoli, non a come il
 *  ruolo è stato assegnato. Il provisioning (db-workspace-governance)
 *  è un plugin separato e successivo.
 *
 *  PRINCIPIO FAIL-SAFE: qualunque cosa vada storta, l'amministratore
 *  (manage_options) vede SEMPRE tutto. La pulizia è additiva e colpisce
 *  solo chi ha esplicitamente meno privilegi. Un errore deve mostrare
 *  troppo, mai troppo poco.
 * ------------------------------------------------------------------
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convivenza con la vecchia versione a file singolo (wp-content/plugins/db-role-ux.php).
 * Se è ancora attiva, le sue funzioni sono già definite: ridefinirle sarebbe un
 * errore fatale. Ci fermiamo e avvisiamo l'amministratore di disattivarla.
 */
if ( function_exists( 'db_role_ux_config' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>DB Role UX</strong>: è ancora attiva la vecchia versione a file singolo (<code>db-role-ux.php</code>). Disattivala ed eliminala: finché è attiva, la nuova versione resta ferma.</p></div>';
		}
	);
	return;
}

define( 'DB_ROLE_UX_VERSION', '1.2.0' );
define( 'DB_ROLE_UX_FILE', __FILE__ );
define( 'DB_ROLE_UX_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Privacy capabilities:
 *  - Dati personali:        NO — legge solo ruoli/capability dell'utente corrente, non salva nulla.
 *  - Script di terze parti: NO
 *  - Consenso utente:       NO
 *  - Dati nel database:     NESSUNO — niente opzioni, tabelle o meta: non serve uninstall.php.
 */

require_once DB_ROLE_UX_DIR . 'inc/config.php';
require_once DB_ROLE_UX_DIR . 'inc/cleanup.php';
require_once DB_ROLE_UX_DIR . 'inc/login-redirect.php';
require_once DB_ROLE_UX_DIR . 'inc/launchpad.php';

if ( is_admin() ) {
	require_once DB_ROLE_UX_DIR . 'inc/admin.php';
}

// Shared components — GitHub auto-updater.
// Istanziato SENZA condizione is_admin(): gli aggiornamenti automatici girano anche via WP-Cron.
require_once DB_ROLE_UX_DIR . 'inc/class-updater.php';
if ( class_exists( 'DB_GitHub_Updater_V2' ) ) {
	new DB_GitHub_Updater_V2( __FILE__, 'dadebertolino', 'db-role-ux' );
}
