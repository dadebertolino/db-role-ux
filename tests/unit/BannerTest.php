<?php
/**
 * Fascia di benvenuto per ruolo: sanitizzazione, ruolo che vince,
 * segnaposto, logo, render e modulo.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class BannerTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
		$GLOBALS['__dbru_attachments'] = array(
			12 => array( 'src' => 'https://scuola.example/logo.png', 'alt' => 'Logo IIS' ),
			13 => array( 'src' => 'https://scuola.example/stemma.png', 'alt' => '' ),
		);
	}

	private function banner( array $raw = array() ) {
		return db_role_ux_sanitize_banner( array_merge( array( 'enabled' => '1' ), $raw ) );
	}

	private function values() {
		return array(
			'{nome}'  => 'Prof. Rossi',
			'{ruolo}' => 'Docente',
			'{sito}'  => 'IIS Cigna',
		);
	}

	public function test_spenta_o_non_valida() {
		$this->assertSame( array(), db_role_ux_sanitize_banner( array( 'title' => 'Ciao' ) ), 'senza enabled' );
		$this->assertSame( array(), db_role_ux_sanitize_banner( 'x' ) );
		$this->assertSame( array(), db_role_ux_sanitize_role_settings( array() )['banner'] );
	}

	public function test_sanitizzazione() {
		$banner = $this->banner(
			array(
				'logo'    => 'media',
				'logo_id' => '12abc',
				'title'   => '<b>Ciao</b> {nome}',
				'text'    => '<p onclick="x()">Area {ruolo}</p><script>alert(1)</script>',
				'color'   => ' #0066CC ',
			)
		);
		$this->assertSame(
			array(
				'enabled' => true,
				'logo'    => 'media',
				'logo_id' => 12,
				'title'   => 'Ciao {nome}',
				'text'    => '<p>Area {ruolo}</p>',
				'color'   => '#0066CC',
			),
			$banner
		);
	}

	public function test_logo_e_colore_non_validi() {
		$this->assertSame( 'site', $this->banner( array( 'logo' => 'media', 'logo_id' => '' ) )['logo'], 'media senza ID' );
		$this->assertSame( 'site', $this->banner( array( 'logo' => 'altro' ) )['logo'] );
		$this->assertSame( 0, $this->banner( array( 'logo' => 'site', 'logo_id' => 12 ) )['logo_id'], 'ID solo per media' );
		$this->assertSame( 'none', $this->banner( array( 'logo' => 'none' ) )['logo'] );
		foreach ( array( 'red', '#12345', 'url(x)', array( '#fff' ) ) as $bad ) {
			$this->assertSame( '', $this->banner( array( 'color' => $bad ) )['color'], var_export( $bad, true ) );
		}
	}

	public function test_una_fascia_basta_a_rendere_il_ruolo_personalizzato() {
		db_role_ux_save_role_settings( 'docente', array( 'banner' => array( 'enabled' => '1' ) ) );
		$this->assertArrayHasKey( 'docente', db_role_ux_stored_roles() );
		db_role_ux_save_role_settings( 'docente', array( 'banner' => array( 'title' => 'spenta' ) ) );
		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ) );
	}

	public function test_vince_il_primo_ruolo_con_una_fascia() {
		dbru_test_store_roles(
			array(
				'editor'     => array( 'adminbar_links' => array( array( 'label' => 'X', 'url' => '/' ) ) ),
				'docente'    => array( 'banner' => array( 'enabled' => 1, 'title' => 'Docenti' ) ),
				'subscriber' => array( 'banner' => array( 'enabled' => 1, 'title' => 'Iscritti' ) ),
			)
		);
		$this->assertSame( 'Docenti', db_role_ux_user_role_settings( dbru_test_user( array( 'editor', 'docente', 'subscriber' ) ) )['banner']['title'] );
		$this->assertSame( 'Iscritti', db_role_ux_user_role_settings( dbru_test_user( array( 'subscriber', 'docente' ) ) )['banner']['title'] );
		$this->assertSame( array(), db_role_ux_user_role_settings( dbru_test_user( array( 'editor' ) ) )['banner'], 'nessuna fascia predefinita' );
	}

	public function test_segnaposto() {
		$user               = dbru_test_user( array( 'docente', 'editor', 'fantasma' ) );
		$user->display_name = 'Prof. Rossi';
		$this->assertSame(
			array(
				'{nome}'  => 'Prof. Rossi',
				'{ruolo}' => 'Docente, Editore',
				'{sito}'  => 'IIS Cigna & Baruffi',
			),
			db_role_ux_banner_placeholders( $user )
		);
		$user->display_name = '';
		$this->assertSame( 'utente7', db_role_ux_banner_placeholders( $user )['{nome}'] );
	}

	public function test_render_con_segnaposto_escapati() {
		$values            = $this->values();
		$values['{nome}']  = '<img src=x onerror=alert(1)>';
		$values['{sito}']  = 'Cigna & Baruffi';
		$html              = db_role_ux_render_banner(
			$this->banner(
				array(
					'logo'  => 'none',
					'title' => 'Buongiorno {nome}',
					'text'  => '<strong>{sito}</strong>, area {ruolo}.',
					'color' => '#0066cc',
				)
			),
			$values
		);
		$this->assertStringContainsString( '<section class="db-role-ux-banner" aria-label="Benvenuto" style="--db-role-ux-accent:#0066cc">', $html );
		$this->assertStringContainsString( '<p class="db-role-ux-banner__title">Buongiorno &lt;img src=x onerror=alert(1)&gt;</p>', $html );
		$this->assertStringContainsString( '<strong>Cigna &amp; Baruffi</strong>, area Docente.', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '{', $html );
	}

	public function test_render_senza_testi_ne_colore() {
		$html = db_role_ux_render_banner( $this->banner( array( 'logo' => 'none' ) ), $this->values() );
		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringNotContainsString( 'banner__title', $html );
		$this->assertStringNotContainsString( 'banner__text', $html );
	}

	public function test_logo() {
		$media = db_role_ux_banner_logo( $this->banner( array( 'logo' => 'media', 'logo_id' => 12 ) ) );
		$this->assertStringContainsString( 'src="https://scuola.example/logo.png"', $media );
		$this->assertStringContainsString( 'alt="Logo IIS"', $media );
		$this->assertStringNotContainsString( 'loading=', $media, 'niente lazy loading in cima alla pagina' );

		$no_alt = db_role_ux_banner_logo( $this->banner( array( 'logo' => 'media', 'logo_id' => 13 ) ) );
		$this->assertStringContainsString( 'alt="IIS Cigna &amp; Baruffi"', $no_alt, 'senza alt: nome del sito' );

		$this->assertSame( '', db_role_ux_banner_logo( $this->banner( array( 'logo' => 'site' ) ) ), 'il sito non ha un logo' );
		$GLOBALS['__dbru_theme_mods']['custom_logo'] = 13;
		$this->assertStringContainsString( 'stemma.png', db_role_ux_banner_logo( $this->banner( array( 'logo' => 'site' ) ) ) );
		$this->assertSame( '', db_role_ux_banner_logo( $this->banner( array( 'logo' => 'none' ) ) ) );
		$this->assertSame( '', db_role_ux_banner_logo( $this->banner( array( 'logo' => 'media', 'logo_id' => 99 ) ) ), 'immagine eliminata' );

		$html = db_role_ux_render_banner( $this->banner( array( 'logo' => 'media', 'logo_id' => 12 ) ), $this->values() );
		$this->assertStringContainsString( 'db-role-ux-banner db-role-ux-banner--logo', $html );
	}

	public function test_solo_in_bacheca_e_solo_per_i_ruoli_configurati() {
		$this->assertSame( 10, has_action( 'admin_notices', 'db_role_ux_banner_notice' ) );
		dbru_test_store_roles( array( 'docente' => array( 'banner' => array( 'enabled' => 1, 'logo' => 'none', 'title' => 'Ciao {nome}' ) ) ) );

		$cases = array(
			array( 'docente', 'dashboard', true ),
			array( 'docente', 'edit-post', false ),
			array( 'docente', 'dashboard-network', false ),
			array( 'docente', null, false ),
			array( 'subscriber', 'dashboard', false ),
		);
		foreach ( $cases as $case ) {
			list( $role, $screen, $shown ) = $case;
			dbru_test_login( dbru_test_user( array( $role ) ) );
			$GLOBALS['__dbru_screen'] = $screen;
			ob_start();
			db_role_ux_banner_notice();
			$html = ob_get_clean();
			$this->assertSame( $shown, false !== strpos( $html, 'Ciao Utente 7' ), "$role su " . var_export( $screen, true ) );
		}
	}

	public function test_css_della_bacheca_anche_per_la_sola_fascia() {
		dbru_test_store_roles( array( 'docente' => array( 'banner' => array( 'enabled' => 1 ) ) ) );
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		db_role_ux_dashboard_assets( 'index.php' );
		$this->assertArrayHasKey( 'db-role-ux-dashboard', $GLOBALS['__dbru_calls']['wp_enqueue_style'] );
	}

	public function test_modulo_e_anteprima() {
		dbru_test_store_roles(
			array(
				'docente' => array(
					'banner' => array(
						'enabled' => 1,
						'logo'    => 'media',
						'logo_id' => 12,
						'title'   => 'Ciao {nome}',
						'text'    => 'Area {ruolo} di {sito}',
						'color'   => '#0066cc',
					),
				),
			)
		);
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$_GET = array( 'page' => 'db-role-ux', 'tab' => 'bacheca', 'role' => 'docente' );
		ob_start();
		db_role_ux_render_admin_page();
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/name="banner\[enabled\]"[^>]*checked="checked"/', $html );
		$this->assertMatchesRegularExpression( '/value="media" checked="checked"/', $html );
		$this->assertStringContainsString( 'name="banner[logo_id]" id="db-role-ux-banner-logo-id" value="12"', $html );
		$this->assertStringContainsString( 'value="Ciao {nome}"', $html );
		$this->assertStringContainsString( '>Area {ruolo} di {sito}</textarea>', $html );
		$this->assertStringContainsString( 'Ciao Nome Cognome', $html, 'anteprima' );
		$this->assertStringContainsString( 'Area Docente di IIS Cigna &amp; Baruffi', $html );
		$this->assertStringContainsString( 'Il sito non ha un logo', $html );
		$this->assertLessThan( strpos( $html, 'Widget da nascondere' ), strpos( $html, 'Fascia di benvenuto' ), 'in cima al modulo' );
	}

	public function test_modulo_senza_fascia() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$_GET = array( 'page' => 'db-role-ux', 'tab' => 'bacheca', 'role' => 'docente' );
		ob_start();
		db_role_ux_render_admin_page();
		$html = ob_get_clean();
		$this->assertDoesNotMatchRegularExpression( '/name="banner\[enabled\]"[^>]*checked/', $html );
		$this->assertStringNotContainsString( 'db-role-ux-preview', $html );
		$this->assertMatchesRegularExpression( '/value="site" checked="checked"/', $html );
	}

	public function test_salvataggio_e_asset_del_pannello() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$_POST    = array(
			'_wpnonce' => 'nonce-db_role_ux_save_role_docente',
			'role'     => 'docente',
			'do'       => 'save',
			'banner'   => array(
				'enabled' => '1',
				'logo'    => 'media',
				'logo_id' => '12',
				'title'   => "Ciao {nome}, l\\'area è tua",
				'text'    => '<em>Benvenuto</em>',
				'color'   => '#336699',
			),
		);
		$_REQUEST = $_POST;
		try {
			db_role_ux_handle_save_role();
		} catch ( DBRU_Test_Redirect $redirect ) {
			$this->assertStringContainsString( 'saved=ok', $redirect->getMessage() );
		}
		$banner = db_role_ux_stored_roles()['docente']['banner'];
		$this->assertSame( "Ciao {nome}, l'area è tua", $banner['title'] );
		$this->assertSame( 12, $banner['logo_id'] );
		$this->assertSame( 'fascia di benvenuto', db_role_ux_describe_role_settings( db_role_ux_stored_roles()['docente'] ) );

		db_role_ux_admin_assets( 'tools_page_db-role-ux' );
		$this->assertTrue( $GLOBALS['__dbru_calls']['wp_enqueue_media'] );
		$this->assertArrayHasKey( 'wp-color-picker', $GLOBALS['__dbru_calls']['wp_enqueue_style'] );
		$this->assertArrayHasKey( 'db-role-ux-dashboard', $GLOBALS['__dbru_calls']['wp_enqueue_style'], 'stili per l\'anteprima' );
		$this->assertSame( array( 'jquery', 'wp-color-picker' ), $GLOBALS['__dbru_calls']['wp_enqueue_script']['db-role-ux-admin'][1] );
	}
}
