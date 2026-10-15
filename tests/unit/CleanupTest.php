<?php
/**
 * Pulizia di bacheca, admin bar, menu e rifiniture della schermata.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class CleanupTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	public function test_hook_agganciati_dopo_gli_altri_plugin() {
		$this->assertSame( 9999, has_action( 'wp_dashboard_setup', 'db_role_ux_clean_dashboard' ) );
		$this->assertSame( 9999, has_action( 'admin_bar_menu', 'db_role_ux_clean_adminbar' ) );
		$this->assertSame( 9999, has_action( 'admin_menu', 'db_role_ux_clean_admin_menu' ) );
	}

	public function test_bacheca_rimuove_i_widget_dichiarati() {
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		db_role_ux_clean_dashboard();
		$this->assertSame(
			array(
				array( '0', 'dashboard', 'normal' ),
				array( '1', 'dashboard', 'normal' ),
				array( '2', 'dashboard', 'normal' ),
				array( '3', 'dashboard', 'normal' ),
			),
			$GLOBALS['__dbru_calls']['remove_meta_box']
		);
	}

	public function test_bacheca_salta_le_voci_senza_id_e_usa_normal_di_default() {
		dbru_test_config( array( 'dashboard_widgets' => array( array( '' ), array( 'dashboard_primary' ), array( 'dashboard_quick_press', 'side' ) ) ) );
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		db_role_ux_clean_dashboard();
		$this->assertSame(
			array(
				array( 'dashboard_primary', 'dashboard', 'normal' ),
				array( 'dashboard_quick_press', 'dashboard', 'side' ),
			),
			$GLOBALS['__dbru_calls']['remove_meta_box']
		);
	}

	public function test_bacheca_tabula_rasa() {
		$GLOBALS['wp_meta_boxes'] = array(
			'dashboard' => array( 'normal' => array( 'core' => array( 'x' => array() ) ) ),
			'post'      => array( 'side' => array() ),
		);
		dbru_test_config( array( 'dashboard_nuke_all' => true ) );
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		db_role_ux_clean_dashboard();
		$this->assertSame( array(), $GLOBALS['wp_meta_boxes']['dashboard'] );
		$this->assertArrayHasKey( 'post', $GLOBALS['wp_meta_boxes'], 'solo la bacheca' );
		$this->assertArrayNotHasKey( 'remove_meta_box', $GLOBALS['__dbru_calls'] );
	}

	public function test_admin_e_ospiti_non_vengono_toccati() {
		$bar    = new DBRU_Test_Admin_Bar();
		$screen = new DBRU_Test_Screen();
		foreach ( array( null, dbru_test_user( array( 'administrator' ) ) ) as $user ) {
			dbru_test_login( $user );
			db_role_ux_clean_dashboard();
			db_role_ux_clean_adminbar( $bar );
			db_role_ux_clean_admin_menu();
			$this->assertSame( 'aiuto', db_role_ux_hide_help( 'aiuto', 'dashboard', $screen ) );
			$this->assertTrue( db_role_ux_hide_screen_options( true ) );
			$this->assertSame( array( 'dashboard' => 2 ), db_role_ux_dashboard_one_column( array( 'dashboard' => 2 ), 'dashboard' ) );
			$this->assertSame( 2, db_role_ux_force_one_column_pref( 2 ) );
		}
		$this->assertSame( array(), $GLOBALS['__dbru_calls'] );
		$this->assertSame( array(), $bar->removed );
		$this->assertFalse( $screen->help_removed );
	}

	public function test_admin_bar() {
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		$bar = new DBRU_Test_Admin_Bar();
		db_role_ux_clean_adminbar( $bar );
		$this->assertSame( db_role_ux_config()['adminbar_nodes'], $bar->removed );
		$this->assertContains( 'wp-logo', $bar->removed );
		$this->assertContains( 'new-content', $bar->removed );
	}

	public function test_admin_bar_vuota() {
		dbru_test_config( array( 'adminbar_nodes' => array() ) );
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		$bar = new DBRU_Test_Admin_Bar();
		db_role_ux_clean_adminbar( $bar );
		$this->assertSame( array(), $bar->removed );
	}

	public function test_menu_laterale() {
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		db_role_ux_clean_admin_menu();
		$this->assertArrayNotHasKey( 'remove_menu_page', $GLOBALS['__dbru_calls'], 'vuoto di default' );

		dbru_test_config( array( 'admin_menu_pages' => array( 'edit-comments.php', 'tools.php' ) ) );
		db_role_ux_clean_admin_menu();
		$this->assertSame( array( 'edit-comments.php', 'tools.php' ), $GLOBALS['__dbru_calls']['remove_menu_page'] );
	}

	public function test_rifiniture_per_gli_utenti_target() {
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$screen = new DBRU_Test_Screen();
		$this->assertFalse( db_role_ux_hide_help( 'aiuto', 'dashboard', $screen ) );
		$this->assertTrue( $screen->help_removed );
		$this->assertFalse( db_role_ux_hide_help( 'aiuto', 'dashboard', null ), 'schermata assente' );
		$this->assertFalse( db_role_ux_hide_screen_options( true ) );
		$this->assertSame( 1, db_role_ux_force_one_column_pref( 2 ) );
		$this->assertSame( array( 'dashboard' => 1 ), db_role_ux_dashboard_one_column( array( 'dashboard' => 2 ), 'dashboard' ) );
		$this->assertSame( array( 'edit-post' => 2 ), db_role_ux_dashboard_one_column( array( 'edit-post' => 2 ), 'edit-post' ), 'solo la bacheca' );
	}
}
