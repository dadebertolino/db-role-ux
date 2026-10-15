<?php
/**
 * Redirect dopo il login: spento di default, mai per gli admin.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class LoginRedirectTest extends TestCase {

	const AREA = 'https://scuola.example/area-riservata/';

	protected function setUp(): void {
		dbru_test_reset();
	}

	private function enable( $url = self::AREA ) {
		dbru_test_config(
			array(
				'login_redirect' => array(
					'enabled' => true,
					'url'     => $url,
				),
			)
		);
	}

	public function test_spento_di_default() {
		$this->assertSame( 10, has_filter( 'login_redirect', 'db_role_ux_login_redirect' ) );
		$this->assertFalse( db_role_ux_config()['login_redirect']['enabled'] );
		$this->assertSame( '/wp-admin/', db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'subscriber' ) ) ) );
	}

	public function test_acceso_reindirizza_gli_utenti_target() {
		$this->enable();
		$this->assertSame( self::AREA, db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'subscriber' ) ) ) );
	}

	public function test_acceso_non_reindirizza_admin_errori_e_utenti_fuori_target() {
		$this->enable();
		$this->assertSame( '/wp-admin/', db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'administrator' ) ) ) );
		$this->assertSame( '/wp-admin/', db_role_ux_login_redirect( '/wp-admin/', '', new WP_Error() ) );

		dbru_test_config(
			array(
				'target_mode'  => 'roles',
				'target_roles' => array( 'docente' ),
			)
		);
		$this->assertSame( '/wp-admin/', db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'subscriber' ) ) ) );
		$this->assertSame( self::AREA, db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'docente' ) ) ) );
	}

	public function test_acceso_senza_url_lascia_il_default() {
		$this->enable( '' );
		$this->assertSame( '/wp-admin/', db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'subscriber' ) ) ) );
	}

	public function test_valuta_l_utente_passato_non_quello_corrente() {
		$this->enable();
		// Al login l'utente corrente non è ancora impostato.
		dbru_test_login( null );
		$this->assertSame( self::AREA, db_role_ux_login_redirect( '/wp-admin/', '', dbru_test_user( array( 'subscriber' ) ) ) );
	}
}
