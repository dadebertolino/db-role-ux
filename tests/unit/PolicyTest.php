<?php
/**
 * Selezione degli utenti target: un solo punto di verità per utenti e ruoli.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class PolicyTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	public function test_non_admin_e_la_modalita_predefinita() {
		$this->assertSame( 'non_admin', db_role_ux_config()['target_mode'] );
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ) ) ) );
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'editor' ) ) ) );
	}

	public function test_l_amministratore_non_e_mai_ripulito() {
		foreach ( array( 'non_admin', 'roles', 'capability' ) as $mode ) {
			dbru_test_reset();
			dbru_test_config(
				array(
					'target_mode'       => $mode,
					'target_roles'      => array( 'administrator' ),
					'target_capability' => 'read',
				)
			);
			$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'administrator' ) ) ), $mode );
			$this->assertFalse( db_role_ux_role_matches( 'administrator' ), $mode );
		}
	}

	public function test_manage_options_aggiunta_a_mano_esclude_l_utente() {
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ), array( 'manage_options' => true ) ) ) );
	}

	public function test_utente_non_valido_o_inesistente() {
		$this->assertFalse( db_role_ux_user_matches( null ) );
		$this->assertFalse( db_role_ux_user_matches( new WP_Error() ) );
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ), array(), 0 ) ) );
	}

	public function test_modalita_roles() {
		dbru_test_config(
			array(
				'target_mode'  => 'roles',
				'target_roles' => array( 'subscriber', 'docente' ),
			)
		);
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ) ) ) );
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'editor', 'docente' ) ) ) );
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'editor' ) ) ) );
		$this->assertTrue( db_role_ux_role_matches( 'docente' ) );
		$this->assertFalse( db_role_ux_role_matches( 'editor' ) );
	}

	public function test_modalita_roles_senza_ruoli_non_ripulisce_nessuno() {
		dbru_test_config(
			array(
				'target_mode'  => 'roles',
				'target_roles' => array(),
			)
		);
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ) ) ) );
	}

	public function test_modalita_capability() {
		dbru_test_config(
			array(
				'target_mode'       => 'capability',
				'target_capability' => 'db_area_docenti',
			)
		);
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'docente' ) ) ) );
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ), array( 'db_area_docenti' => true ) ) ) );
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ) ) ) );
		$this->assertTrue( db_role_ux_role_matches( 'docente' ) );
		$this->assertFalse( db_role_ux_role_matches( 'subscriber' ) );
	}

	public function test_modalita_capability_vuota_non_ripulisce_nessuno() {
		dbru_test_config(
			array(
				'target_mode'       => 'capability',
				'target_capability' => '',
			)
		);
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'docente' ) ) ) );
	}

	public function test_modalita_sconosciuta_ricade_su_non_admin() {
		dbru_test_config( array( 'target_mode' => 'boh' ) );
		$this->assertTrue( db_role_ux_user_matches( dbru_test_user( array( 'subscriber' ) ) ) );
		$this->assertFalse( db_role_ux_user_matches( dbru_test_user( array( 'administrator' ) ) ) );
	}

	public function test_ruolo_inesistente() {
		$this->assertFalse( db_role_ux_role_matches( 'fantasma' ) );
	}

	public function test_utente_corrente() {
		$this->assertFalse( db_role_ux_user_is_targeted(), 'nessuno loggato' );
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$this->assertTrue( db_role_ux_user_is_targeted() );
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$this->assertFalse( db_role_ux_user_is_targeted() );
	}
}
