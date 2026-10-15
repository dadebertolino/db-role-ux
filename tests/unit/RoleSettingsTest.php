<?php
/**
 * Bacheca per ruolo: sanitizzazione, salvataggio e unione per utente.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class RoleSettingsTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	public function test_url_ammessi_e_scartati() {
		$this->assertSame( 'https://registro.example/login', db_role_ux_sanitize_url( ' https://registro.example/login ' ) );
		$this->assertSame( '/area-riservata/', db_role_ux_sanitize_url( '/area-riservata/' ) );
		$this->assertSame( 'edit.php?post_type=page', db_role_ux_sanitize_url( 'edit.php?post_type=page' ) );
		foreach ( array( 'javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/html,x', 'ftp://x', '//evil.example', '/\\evil', '', array( 'x' ) ) as $bad ) {
			$this->assertSame( '', db_role_ux_sanitize_url( $bad ), var_export( $bad, true ) );
		}
		$this->assertSame( 'edit.phpx=1', db_role_ux_sanitize_url( 'edit.php"x=1' ), 'virgolette rimosse' );
	}

	public function test_url_risolti() {
		$this->assertSame( 'https://registro.example/', db_role_ux_resolve_url( 'https://registro.example/' ) );
		$this->assertSame( 'https://scuola.example/area-riservata/', db_role_ux_resolve_url( '/area-riservata/' ) );
		$this->assertSame( 'https://scuola.example/wp-admin/edit.php?post_type=page', db_role_ux_resolve_url( 'edit.php?post_type=page' ) );
	}

	public function test_link_da_textarea_e_ritorno() {
		$text  = "Registro | https://registro.example\n\n  senza url  \nRotto | javascript:alert(1)\nCalendario | /calendario/ | dashicons-calendar\nIcona strana | profile.php | <b>";
		$links = db_role_ux_parse_links( $text, true );
		$this->assertSame(
			array(
				array( 'label' => 'Registro', 'url' => 'https://registro.example', 'icon' => 'dashicons-admin-links' ),
				array( 'label' => 'Calendario', 'url' => '/calendario/', 'icon' => 'dashicons-calendar' ),
				array( 'label' => 'Icona strana', 'url' => 'profile.php', 'icon' => 'dashicons-admin-links' ),
			),
			$links
		);
		$this->assertSame( "Registro | https://registro.example\nCalendario | /calendario/ | dashicons-calendar\nIcona strana | profile.php", db_role_ux_format_links( $links, true ) );
		$this->assertArrayNotHasKey( 'icon', db_role_ux_parse_links( 'A | /a/' )[0], 'senza icona fuori dal menu' );
	}

	public function test_widget_per_tipo() {
		$this->assertNull( db_role_ux_sanitize_widget( array( 'title' => '', 'links' => array( array( 'label' => 'a', 'url' => '/a/' ) ) ) ), 'senza titolo' );
		$this->assertNull( db_role_ux_sanitize_widget( array( 'title' => 'Vuoto', 'type' => 'links', 'links' => array() ) ) );
		$this->assertNull( db_role_ux_sanitize_widget( array( 'title' => 'Vuoto', 'type' => 'text', 'content' => '  ' ) ) );
		$this->assertNull( db_role_ux_sanitize_widget( array( 'title' => 'Vuoto', 'type' => 'cards', 'groups' => array() ) ) );
		$this->assertNull( db_role_ux_sanitize_widget( 'non un array' ) );

		$text = db_role_ux_sanitize_widget( array( 'title' => '<b>Avviso</b>', 'type' => 'text', 'context' => 'side', 'content' => '<p onclick="x()">Ciao</p><script>alert(1)</script>' ) );
		$this->assertSame( 'Avviso', $text['title'] );
		$this->assertSame( 'side', $text['context'] );
		$this->assertSame( '<p>Ciao</p>', $text['content'] );

		$cards = db_role_ux_sanitize_widget( array( 'title' => 'Card', 'type' => 'cards', 'context' => 'boh', 'groups' => array( 'db_area_docenti', 'db_area_docenti', '' ) ) );
		$this->assertSame( array( 'db_area_docenti' ), $cards['groups'] );
		$this->assertSame( 'normal', $cards['context'] );

		$unknown = db_role_ux_sanitize_widget( array( 'title' => 'T', 'type' => array( 'x' ), 'links' => array( array( 'label' => 'a', 'url' => '/a/' ) ) ) );
		$this->assertSame( 'links', $unknown['type'], 'tipo non valido → elenco di link' );
	}

	public function test_id_dei_widget_generati_e_univoci() {
		$link     = array( array( 'label' => 'a', 'url' => '/a/' ) );
		$settings = db_role_ux_sanitize_role_settings(
			array(
				'widgets' => array(
					array( 'title' => 'Uno', 'links' => $link ),
					array( 'id' => 'abc', 'title' => 'Due', 'links' => $link ),
					array( 'id' => 'ABC', 'title' => 'Tre', 'links' => $link ),
				),
			)
		);
		$ids = wp_list_pluck( $settings['widgets'], 'id' );
		$this->assertCount( 3, array_unique( $ids ) );
		$this->assertSame( 'abc', $ids[1] );
		$this->assertMatchesRegularExpression( '/^[a-z0-9]+$/', $ids[0] );
		$this->assertSame( $settings, db_role_ux_sanitize_role_settings( $settings ), 'idempotente' );
	}

	public function test_widget_nascosti() {
		$settings = db_role_ux_sanitize_role_settings( array( 'hide_widgets' => array( '0', '0', 'dashboard_primary', 'x"><b>', '', array() ) ) );
		$this->assertSame( array( '0', 'dashboard_primary', 'xb' ), $settings['hide_widgets'] );
	}

	public function test_salvataggio_e_azzeramento() {
		db_role_ux_save_role_settings( 'docente', array( 'hide_widgets' => array( 'dashboard_primary' ) ) );
		$this->assertSame( array( 'dashboard_primary' ), get_option( DB_ROLE_UX_OPTION )['docente']['hide_widgets'] );
		$this->assertSame( array( 'dashboard_primary' ), db_role_ux_config()['roles']['docente']['hide_widgets'], 'entra nella config' );

		db_role_ux_save_role_settings( 'subscriber', array( 'adminbar_links' => array( array( 'label' => 'Sito', 'url' => '/' ) ) ) );
		db_role_ux_save_role_settings( 'docente', array() );
		$this->assertSame( array( 'subscriber' ), array_keys( get_option( DB_ROLE_UX_OPTION ) ) );

		db_role_ux_save_role_settings( 'subscriber', array( 'widgets' => array( array( 'title' => 'senza link' ) ) ) );
		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ), 'tutto vuoto: opzione rimossa' );
	}

	public function test_opzione_corrotta_o_scritta_a_mano() {
		$GLOBALS['__dbru_options'][ DB_ROLE_UX_OPTION ] = 'stringa';
		$this->assertSame( array(), db_role_ux_stored_roles() );

		$GLOBALS['__dbru_options'][ DB_ROLE_UX_OPTION ] = array(
			'Docente!' => array( 'menu_links' => array( array( 'label' => 'X', 'url' => 'javascript:alert(1)' ), array( 'label' => 'Y', 'url' => '/y/' ) ) ),
			''         => array(),
			'altro'    => 'non un array',
		);
		$roles = db_role_ux_stored_roles();
		$this->assertSame( array( 'docente' ), array_keys( $roles ) );
		$this->assertSame( array( array( 'label' => 'Y', 'url' => '/y/', 'icon' => 'dashicons-admin-links' ) ), $roles['docente']['menu_links'] );
	}

	public function test_unione_dei_ruoli_dell_utente() {
		$shared = array( 'label' => 'Registro', 'url' => 'https://registro.example' );
		dbru_test_store_roles(
			array(
				'docente'    => array(
					'hide_widgets'   => array( 'a', 'b' ),
					'widgets'        => array( array( 'id' => 'w1', 'title' => 'Docenti', 'links' => array( $shared ) ) ),
					'adminbar_links' => array( $shared ),
				),
				'editor'     => array(
					'hide_widgets'   => array( 'b', 'c' ),
					'widgets'        => array(
						array( 'id' => 'w1', 'title' => 'Duplicato', 'links' => array( $shared ) ),
						array( 'id' => 'w2', 'title' => 'Editori', 'links' => array( $shared ) ),
					),
					'adminbar_links' => array( $shared, array( 'label' => 'Bozze', 'url' => 'edit.php?post_status=draft' ) ),
				),
				'subscriber' => array( 'hide_widgets' => array( 'z' ) ),
			)
		);

		$merged = db_role_ux_user_role_settings( dbru_test_user( array( 'docente', 'editor' ) ) );
		$this->assertSame( array( 'a', 'b', 'c' ), $merged['hide_widgets'] );
		$this->assertSame( array( 'Docenti', 'Editori' ), wp_list_pluck( $merged['widgets'], 'title' ) );
		$this->assertSame( array( 'Registro', 'Bozze' ), wp_list_pluck( $merged['adminbar_links'], 'label' ) );
		$this->assertSame( db_role_ux_empty_role_settings(), db_role_ux_user_role_settings( dbru_test_user( array( 'administrator' ) ) ) );
		$this->assertSame( db_role_ux_empty_role_settings(), db_role_ux_user_role_settings( null ) );
	}

	public function test_fail_safe_niente_da_nascondere_a_chi_ha_manage_options() {
		dbru_test_store_roles(
			array(
				'administrator' => array(
					'hide_widgets'   => array( 'dashboard_primary' ),
					'adminbar_links' => array( array( 'label' => 'Registro', 'url' => 'https://registro.example' ) ),
				),
				'subscriber'    => array( 'hide_widgets' => array( 'dashboard_primary' ) ),
			)
		);
		$admin = db_role_ux_user_role_settings( dbru_test_user( array( 'administrator' ) ) );
		$this->assertSame( array(), $admin['hide_widgets'] );
		$this->assertCount( 1, $admin['adminbar_links'], 'le aggiunte valgono anche per lui' );

		$promoted = db_role_ux_user_role_settings( dbru_test_user( array( 'subscriber' ), array( 'manage_options' => true ) ) );
		$this->assertSame( array(), $promoted['hide_widgets'] );
	}

	public function test_il_filtro_ha_l_ultima_parola() {
		dbru_test_store_roles( array( 'subscriber' => array( 'hide_widgets' => array( 'dal_pannello' ) ) ) );
		add_filter(
			'db_role_ux_config',
			function ( $config ) {
				$config['roles']['subscriber']['hide_widgets'] = array( 'dal_filtro' );
				return $config;
			}
		);
		$this->assertSame( array( 'dal_filtro' ), db_role_ux_user_role_settings( dbru_test_user( array( 'subscriber' ) ) )['hide_widgets'] );
	}
}
