<?php
/**
 * Bootstrap dei test di INTEGRAZIONE.
 *
 * A differenza degli unit test (che stubbano WordPress), questi girano contro
 * un WordPress VERO con un database MySQL reale, tramite la WordPress test
 * suite (WP_UnitTestCase). Verificano ciò che dipende dal core: WP_User e
 * WP_Role reali, user_can() con i super admin del multisite, meta box della
 * bacheca, WP_Admin_Bar, menu laterale, shortcode e filtro login_redirect.
 *
 * Il percorso della test suite arriva da WP_TESTS_DIR (impostato dallo script
 * di installazione bin/install-wp-tests.sh, eseguito nel job CI di integrazione).
 *
 * @package DB_Role_UX\Tests\Integration
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills );
} elseif ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Impossibile trovare {$_tests_dir}/includes/functions.php" . PHP_EOL;
	echo 'Esegui prima bin/install-wp-tests.sh.' . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Carica il plugin prima che WordPress finisca il bootstrap, come farebbe
 * l'attivazione: il file principale aggancia gli hook al caricamento.
 */
function _db_role_ux_manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/db-role-ux.php';
}
tests_add_filter( 'muplugins_loaded', '_db_role_ux_manually_load_plugin' );

require "{$_tests_dir}/includes/bootstrap.php";

// La pagina di riepilogo si carica solo in is_admin(): i test la includono qui.
require_once dirname( __DIR__, 2 ) . '/inc/admin.php';
