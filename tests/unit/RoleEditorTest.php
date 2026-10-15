<?php
/**
 * Scheda "Bacheca per ruolo": modulo e salvataggio.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class RoleEditorTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	private function render( array $get = array() ) {
		$_GET = array_merge( array( 'page' => 'db-role-ux', 'tab' => 'bacheca' ), $get );
		ob_start();
		try {
			db_role_ux_render_admin_page();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	}

	private function save( array $post ) {
		$_POST    = $post;
		$_REQUEST = $post;
		try {
			db_role_ux_handle_save_role();
		} catch ( DBRU_Test_Redirect $redirect ) {
			return $redirect->getMessage();
		}
		$this->fail( 'Nessun redirect dopo il salvataggio.' );
	}

	public function test_schede_e_ruolo_predefinito() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render();
		$this->assertStringContainsString( 'aria-current="page">Bacheca per ruolo</a>', $html );
		$this->assertStringContainsString( 'name="role" value="subscriber"', $html );
		$this->assertStringContainsString( 'Nessun widget rilevato finora', $html );
		$this->assertStringContainsString( '<legend>Nuovo widget</legend>', $html );
		$this->assertStringContainsString( 'name="_wpnonce" value="nonce-db_role_ux_save_role_subscriber"', $html );

		$this->assertStringContainsString( 'name="role" value="docente"', $this->render( array( 'role' => 'docente' ) ) );
		$this->assertStringContainsString( 'name="role" value="subscriber"', $this->render( array( 'role' => 'fantasma' ) ), 'ruolo inesistente' );
	}

	public function test_modulo_con_impostazioni_salvate() {
		$GLOBALS['__dbru_options'][ DB_ROLE_UX_SEEN_OPTION ] = array(
			'dashboard_primary' => array( 'title' => 'Eventi e notizie', 'context' => 'side' ),
			'0'                 => array( 'title' => 'Circolari <b>', 'context' => 'normal' ),
		);
		dbru_test_store_roles(
			array(
				'docente' => array(
					'hide_widgets' => array( '0', 'mai_visto' ),
					'widgets'      => array( array( 'id' => 'abc', 'title' => 'Link utili', 'links' => array( array( 'label' => 'Registro', 'url' => 'https://registro.example' ) ) ) ),
					'menu_links'   => array( array( 'label' => 'Orario', 'url' => '/orario/', 'icon' => 'dashicons-clock' ) ),
				),
			)
		);
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render( array( 'role' => 'docente' ) );

		$this->assertMatchesRegularExpression( '/value="0" checked="checked"/', $html );
		$this->assertStringContainsString( 'Circolari &lt;b&gt;', $html );
		$this->assertStringNotContainsString( 'value="dashboard_primary" checked', $html );
		$this->assertMatchesRegularExpression( '#id="db-role-ux-hide-extra"[^>]*>mai_visto</textarea>#', $html );
		$this->assertStringContainsString( 'name="widgets[0][id]" value="abc"', $html );
		$this->assertStringContainsString( 'Registro | https://registro.example</textarea>', $html );
		$this->assertStringContainsString( 'Orario | /orario/ | dashicons-clock</textarea>', $html );
		$this->assertStringContainsString( '<legend>Nuovo widget</legend>', $html );
		$this->assertStringContainsString( 'il ruolo non ha questa capability', $html, 'gruppo Segreteria' );
	}

	public function test_ruolo_admin_niente_da_nascondere() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render( array( 'role' => 'administrator' ) );
		$this->assertStringContainsString( 'per il principio fail-safe non gli si nasconde nulla', $html );
		$this->assertStringNotContainsString( 'name="hide_widgets[]"', $html );
	}

	public function test_avviso_se_un_filtro_cambia_il_ruolo() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		add_filter(
			'db_role_ux_config',
			function ( $config ) {
				$config['roles']['subscriber'] = array( 'hide_widgets' => array( 'x' ) );
				return $config;
			}
		);
		$this->assertStringContainsString( 'in bacheca vale il filtro', $this->render() );
	}

	public function test_salvataggio() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$location = $this->save(
			array(
				'_wpnonce'       => 'nonce-db_role_ux_save_role_docente',
				'role'           => 'docente',
				'do'             => 'save',
				'hide_widgets'   => array( '0', '1' ),
				'hide_extra'     => "mai_visto, altro\n",
				'widgets'        => array(
					array( 'id' => 'abc', 'title' => 'Link utili', 'type' => 'links', 'context' => 'side', 'links' => "Registro | https://registro.example\nRotto | javascript:x" ),
					array( 'id' => 'del', 'title' => 'Da eliminare', 'type' => 'links', 'links' => 'A | /a/', 'delete' => '1' ),
					array( 'id' => '', 'title' => "L\\'avviso", 'type' => 'text', 'content' => '<em>Ciao</em>' ),
					array( 'id' => '', 'title' => '', 'type' => 'links', 'links' => '' ),
				),
				'menu_links'     => 'Orario | /orario/ | dashicons-clock',
				'adminbar_links' => 'Sito | /',
			)
		);
		$this->assertStringContainsString( 'tab=bacheca', $location );
		$this->assertStringContainsString( 'role=docente', $location );
		$this->assertStringContainsString( 'saved=ok', $location );

		$saved = db_role_ux_stored_roles()['docente'];
		$this->assertSame( array( '0', '1', 'mai_visto', 'altro' ), $saved['hide_widgets'] );
		$this->assertSame( array( 'Link utili', "L'avviso" ), wp_list_pluck( $saved['widgets'], 'title' ), 'wp_unslash, eliminato, vuoto scartato' );
		$this->assertSame( 'abc', $saved['widgets'][0]['id'] );
		$this->assertSame( 'side', $saved['widgets'][0]['context'] );
		$this->assertCount( 1, $saved['widgets'][0]['links'] );
		$this->assertSame( 'dashicons-clock', $saved['menu_links'][0]['icon'] );
		$this->assertSame( '/', $saved['adminbar_links'][0]['url'] );
	}

	public function test_azzeramento() {
		dbru_test_store_roles( array( 'docente' => array( 'hide_widgets' => array( 'x' ) ) ) );
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$location = $this->save(
			array(
				'_wpnonce'     => 'nonce-db_role_ux_save_role_docente',
				'role'         => 'docente',
				'do'           => 'reset',
				'hide_widgets' => array( 'y' ),
			)
		);
		$this->assertStringContainsString( 'saved=reset', $location );
		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ) );
		$this->assertStringContainsString( 'Impostazioni del ruolo azzerate', $this->render( array( 'role' => 'docente', 'saved' => 'reset' ) ) );
	}

	public function test_salvataggio_rifiutato() {
		$post = array(
			'_wpnonce'     => 'nonce-db_role_ux_save_role_docente',
			'role'         => 'docente',
			'hide_widgets' => array( 'x' ),
		);

		dbru_test_login( dbru_test_user( array( 'editor' ) ) );
		try {
			$this->save( $post );
			$this->fail( 'Salvato senza manage_options.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}

		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		foreach ( array(
			array_merge( $post, array( '_wpnonce' => 'nonce-db_role_ux_save_role_subscriber' ) ),
			array_merge( $post, array( 'role' => 'fantasma', '_wpnonce' => 'nonce-db_role_ux_save_role_fantasma' ) ),
		) as $bad ) {
			try {
				$this->save( $bad );
				$this->fail( 'Salvato con nonce o ruolo non validi.' );
			} catch ( RuntimeException $e ) {
				$this->assertNotInstanceOf( DBRU_Test_Redirect::class, $e );
			}
		}
		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ) );
	}

	public function test_riepilogo_con_link_al_ruolo() {
		dbru_test_store_roles( array( 'docente' => array( 'hide_widgets' => array( 'a', 'b' ), 'adminbar_links' => array( array( 'label' => 'X', 'url' => '/' ) ) ) ) );
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render( array( 'tab' => 'riepilogo' ) );
		$this->assertStringContainsString( '2 widget nascosti, 1 voce in admin bar', $html );
		$this->assertStringContainsString( 'tab=bacheca&amp;role=docente', $html );
		$this->assertStringContainsString( '>Personalizza</a>', $html );
	}
}
