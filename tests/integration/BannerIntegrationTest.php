<?php
/**
 * Fascia di benvenuto contro un WordPress reale: immagine vera della
 * Libreria media, logo del sito, schermata della bacheca.
 *
 * @package DB_Role_UX\Tests\Integration
 */

class BannerIntegrationTest extends WP_UnitTestCase {

	/**
	 * ID dell'immagine di prova.
	 *
	 * @var int
	 */
	private static $logo_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$logo_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_post_meta( self::$logo_id, '_wp_attachment_image_alt', 'Logo della scuola' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_attachment( self::$logo_id, true );
	}

	public function tear_down() {
		remove_theme_mod( 'custom_logo' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function notice_for( $role ) {
		$user = self::factory()->user->create_and_get(
			array(
				'role'         => $role,
				'display_name' => 'Mario Rossi',
			)
		);
		wp_set_current_user( $user->ID );
		set_current_screen( 'dashboard' );
		ob_start();
		do_action( 'admin_notices' );
		return ob_get_clean();
	}

	public function test_fascia_con_immagine_della_libreria() {
		db_role_ux_save_role_settings(
			'editor',
			array(
				'banner' => array(
					'enabled' => 1,
					'logo'    => 'media',
					'logo_id' => self::$logo_id,
					'title'   => 'Buongiorno {nome}',
					'text'    => 'Area <strong>{ruolo}</strong> di {sito}',
				),
			)
		);

		$html = $this->notice_for( 'editor' );
		$this->assertStringContainsString( 'aria-label="Benvenuto"', $html );
		$this->assertStringContainsString( 'Buongiorno Mario Rossi', $html );
		$this->assertStringContainsString( '<strong>' . esc_html( translate_user_role( 'Editor' ) ) . '</strong>', $html );
		$this->assertStringContainsString( esc_html( get_bloginfo( 'name' ) ), $html );
		$this->assertStringContainsString( 'alt="Logo della scuola"', $html );
		$this->assertMatchesRegularExpression( '#<img[^>]+src="[^"]+canola[^"]*\.jpg"#', $html );

		$this->assertStringNotContainsString( 'db-role-ux-banner', $this->notice_for( 'subscriber' ), 'ruolo senza fascia' );
		$this->assertStringNotContainsString( 'db-role-ux-banner', $this->notice_for( 'administrator' ) );
	}

	public function test_logo_del_sito() {
		db_role_ux_save_role_settings( 'subscriber', array( 'banner' => array( 'enabled' => 1, 'logo' => 'site', 'title' => 'Ciao' ) ) );
		$this->assertStringNotContainsString( '<img', $this->notice_for( 'subscriber' ), 'il sito non ha un logo' );

		set_theme_mod( 'custom_logo', self::$logo_id );
		$this->assertStringContainsString( 'alt="Logo della scuola"', $this->notice_for( 'subscriber' ) );
	}

	public function test_non_fuori_dalla_bacheca() {
		db_role_ux_save_role_settings( 'subscriber', array( 'banner' => array( 'enabled' => 1, 'title' => 'Ciao' ) ) );
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		set_current_screen( 'profile' );
		ob_start();
		do_action( 'admin_notices' );
		$this->assertStringNotContainsString( 'db-role-ux-banner', ob_get_clean() );
	}
}
