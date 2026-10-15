<?php
/**
 * Pagina di riepilogo Strumenti → DB Role UX (sola lettura).
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class AdminPageTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	private function render() {
		ob_start();
		try {
			db_role_ux_render_admin_page();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	}

	public function test_pagina_sotto_strumenti_solo_per_manage_options() {
		db_role_ux_register_admin_page();
		$page = $GLOBALS['__dbru_calls']['add_management_page'][0];
		$this->assertSame( 'manage_options', $page[2] );
		$this->assertSame( 'db-role-ux', $page[3] );
		$this->assertSame( 'db_role_ux_render_admin_page', $page[4] );
	}

	public function test_render_negato_a_chi_non_ha_manage_options() {
		dbru_test_login( dbru_test_user( array( 'editor' ) ) );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionCode( 403 );
		$this->render();
	}

	public function test_css_solo_sulla_pagina_del_plugin() {
		db_role_ux_admin_assets( 'index.php' );
		$this->assertArrayNotHasKey( 'wp_enqueue_style', $GLOBALS['__dbru_calls'] );

		db_role_ux_admin_assets( 'tools_page_db-role-ux' );
		$styles = $GLOBALS['__dbru_calls']['wp_enqueue_style'];
		$this->assertStringEndsWith( '/assets/css/db-admin-ui.css', $styles['db-admin-ui'][0] );
		$this->assertSame( array( 'db-admin-ui' ), $styles['db-role-ux-admin'][1], 'admin.css dipende dal design system' );
		$this->assertFileExists( DB_ROLE_UX_DIR . 'assets/css/db-admin-ui.css' );
		$this->assertFileExists( DB_ROLE_UX_DIR . 'assets/css/admin.css' );
	}

	public function test_link_riepilogo_nella_lista_plugin() {
		$this->assertSame( array( 'disattiva' ), db_role_ux_action_links( array( 'disattiva' ) ) );
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$links = db_role_ux_action_links( array( 'disattiva' ) );
		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'tools.php?page=db-role-ux', $links[0] );
	}

	public function test_descrizione_della_modalita() {
		$this->assertStringContainsString( 'non sono amministratori', db_role_ux_describe_target( array() ) );
		$this->assertStringContainsString( 'docente, subscriber', db_role_ux_describe_target( array( 'target_mode' => 'roles', 'target_roles' => array( 'docente', 'subscriber' ) ) ) );
		$this->assertStringContainsString( 'nessuno viene ripulito', db_role_ux_describe_target( array( 'target_mode' => 'roles', 'target_roles' => array() ) ) );
		$this->assertStringContainsString( 'db_backend_clean', db_role_ux_describe_target( array( 'target_mode' => 'capability', 'target_capability' => 'db_backend_clean' ) ) );
		$this->assertStringContainsString( 'nessuno viene ripulito', db_role_ux_describe_target( array( 'target_mode' => 'capability', 'target_capability' => '' ) ) );
	}

	public function test_anteprima_per_ruolo() {
		$rows = array_column( db_role_ux_role_preview(), null, 'slug' );
		$this->assertFalse( $rows['administrator']['targeted'] );
		$this->assertTrue( $rows['administrator']['admin'] );
		$this->assertTrue( $rows['subscriber']['targeted'] );
		$this->assertSame( 300, $rows['subscriber']['users'] );
		$this->assertSame( array( 'Docente' ), db_role_ux_roles_with_cap( 'db_area_docenti' ) );
	}

	public function test_render_della_pagina() {
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render();

		$this->assertStringContainsString( 'class="wrap db-role-ux-wrap"', $html );
		$this->assertStringContainsString( 'db-ui-page-header', $html );
		$this->assertStringContainsString( '3 / 4', $html, 'ruoli ripuliti' );
		$this->assertStringContainsString( 'Completo (amministratore)', $html );
		$this->assertStringContainsString( '<code>wp-logo</code>', $html );
		$this->assertStringContainsString( 'Spento', $html );
		$this->assertStringContainsString( '[db_area_riservata]', $html );
		$this->assertStringContainsString( 'Nessun ruolo', $html, 'ms_gestisci_moduli non è assegnata' );
	}

	public function test_render_con_redirect_e_tabula_rasa() {
		dbru_test_config(
			array(
				'dashboard_nuke_all' => true,
				'login_redirect'     => array(
					'enabled' => true,
					'url'     => 'https://scuola.example/area-riservata/<x>',
				),
			)
		);
		dbru_test_login( dbru_test_user( array( 'administrator' ) ) );
		$html = $this->render();
		$this->assertStringContainsString( 'Attivo', $html );
		$this->assertStringContainsString( '/area-riservata/&lt;x&gt;', $html );
		$this->assertStringContainsString( 'dashboard_nuke_all', $html );
	}
}
