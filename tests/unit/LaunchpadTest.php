<?php
/**
 * Launchpad [db_area_riservata]: protezione, card per capability, escape.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class LaunchpadTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	public function test_shortcode_registrato() {
		$this->assertSame( 'db_launchpad_render', $GLOBALS['__dbru_shortcodes']['db_area_riservata'] );
	}

	public function test_ospite_mandato_al_login_con_ritorno_alla_pagina() {
		$html  = db_launchpad_render();
		$login = 'https://scuola.example/wp-login.php?redirect_to=' . rawurlencode( 'https://scuola.example/area-riservata/' );
		$this->assertStringContainsString( 'window.location.href=' . json_encode( $login ) . ';', $html );
		$this->assertStringContainsString( 'href="' . esc_url( $login ) . '"', $html );
		$this->assertStringNotContainsString( 'db-launchpad', $html );
	}

	public function test_il_docente_vede_solo_le_sue_card() {
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		$html = db_launchpad_render();
		$this->assertStringContainsString( 'Ciao Utente 7', $html );
		$this->assertStringContainsString( 'Area Docenti', $html );
		$this->assertStringContainsString( 'Programmazioni', $html );
		$this->assertStringNotContainsString( 'Area Alunni', $html );
		$this->assertStringNotContainsString( 'Segreteria', $html );
		$this->assertStringNotContainsString( 'alert-info', $html );
	}

	public function test_piu_capability_piu_gruppi() {
		dbru_test_login( dbru_test_user( array( 'docente' ), array( 'ms_gestisci_moduli' => true ) ) );
		$html = db_launchpad_render();
		$this->assertStringContainsString( 'Area Docenti', $html );
		$this->assertStringContainsString( 'Segreteria', $html );
		$this->assertLessThan( strpos( $html, 'Segreteria' ), strpos( $html, 'Area Docenti' ), 'ordine della configurazione' );
	}

	public function test_nessuna_card_messaggio_neutro() {
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$html = db_launchpad_render();
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( 'non ci sono funzioni disponibili', $html );
	}

	public function test_gruppo_senza_card_non_conta() {
		add_filter(
			'db_launchpad_cards',
			function () {
				return array(
					'read' => array(
						'label' => 'Vuoto',
						'cards' => array(),
					),
				);
			}
		);
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$html = db_launchpad_render();
		$this->assertStringNotContainsString( 'Vuoto', $html );
		$this->assertStringContainsString( 'non ci sono funzioni disponibili', $html );
	}

	public function test_display_name_vuoto_usa_il_login() {
		$user               = dbru_test_user( array( 'subscriber' ) );
		$user->display_name = '';
		dbru_test_login( $user );
		$this->assertStringContainsString( 'Ciao utente7', db_launchpad_render() );
	}

	public function test_escape_delle_card_dal_filtro() {
		add_filter(
			'db_launchpad_cards',
			function () {
				return array(
					'read' => array(
						'label' => '<b>Gruppo</b>',
						'cards' => array(
							array(
								'title' => '<script>alert(1)</script>',
								'desc'  => '"virgolette" & <i>tag</i>',
								'url'   => 'javascript:alert(1)',
								'icon'  => 'it-file" onload="x',
							),
							array(), // Card senza campi: valori di ripiego.
						),
					),
				);
			}
		);
		$user               = dbru_test_user( array( 'subscriber' ) );
		$user->display_name = '<img src=x onerror=alert(1)>';
		dbru_test_login( $user );
		$html = db_launchpad_render();

		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( '<b>Gruppo', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( '" onload="', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '#it-link', $html, 'icona di ripiego' );
	}
}
