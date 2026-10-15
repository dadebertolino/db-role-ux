<?php
/**
 * Bacheca per ruolo: rilevazione, widget nascosti e aggiunti, menu, admin bar.
 *
 * @package DB_Role_UX\Tests
 */

use PHPUnit\Framework\TestCase;

class RoleDashboardTest extends TestCase {

	protected function setUp(): void {
		dbru_test_reset();
	}

	private function links( $label = 'Registro', $url = 'https://registro.example' ) {
		return array( array( 'label' => $label, 'url' => $url ) );
	}

	public function test_ordine_degli_hook() {
		$this->assertSame( 9998, has_action( 'wp_dashboard_setup', 'db_role_ux_record_widgets' ) );
		$this->assertSame( 9999, has_action( 'wp_dashboard_setup', 'db_role_ux_role_hide_widgets' ) );
		$this->assertSame( 10000, has_action( 'wp_dashboard_setup', 'db_role_ux_role_add_widgets' ), 'dopo la tabula rasa' );
		$this->assertSame( 10000, has_action( 'admin_menu', 'db_role_ux_role_menu_links' ) );
		$this->assertSame( 100, has_action( 'admin_bar_menu', 'db_role_ux_role_adminbar_links' ) );
	}

	public function test_rilevazione_dei_widget() {
		$GLOBALS['wp_meta_boxes'] = array(
			'dashboard' => array(
				'normal' => array(
					'core'    => array(
						'dashboard_activity' => array( 'id' => 'dashboard_activity', 'title' => 'Attività <span class="x">3</span>' ),
						'0'                  => array( 'id' => '0', 'title' => '' ),
						'rimosso'            => false,
					),
					'default' => array( 'dbru_abc' => array( 'id' => 'dbru_abc', 'title' => 'Nostro' ) ),
				),
				'side'   => array( 'core' => array( 'dashboard_primary' => array( 'id' => 'dashboard_primary', 'title' => 'Eventi' ) ) ),
			),
		);
		db_role_ux_record_widgets();
		$this->assertSame(
			array(
				'dashboard_activity' => array( 'title' => 'Attività 3', 'context' => 'normal' ),
				'0'                  => array( 'title' => '0', 'context' => 'normal' ),
				'dashboard_primary'  => array( 'title' => 'Eventi', 'context' => 'side' ),
			),
			db_role_ux_seen_widgets()
		);
		$this->assertSame( array( array( DB_ROLE_UX_SEEN_OPTION, false ) ), $GLOBALS['__dbru_calls']['update_option'], 'senza autoload' );

		db_role_ux_record_widgets();
		$this->assertCount( 1, $GLOBALS['__dbru_calls']['update_option'], 'nessuna riscrittura se non cambia nulla' );

		// Un widget visto per un altro ruolo resta in elenco.
		$GLOBALS['wp_meta_boxes'] = array( 'dashboard' => array( 'normal' => array( 'core' => array( 'solo_docenti' => array( 'title' => 'Docenti' ) ) ) ) );
		db_role_ux_record_widgets();
		$this->assertCount( 4, db_role_ux_seen_widgets() );
	}

	public function test_widget_nascosti_in_tutti_i_contesti() {
		dbru_test_store_roles( array( 'docente' => array( 'hide_widgets' => array( 'dashboard_primary' ) ) ) );
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		db_role_ux_role_hide_widgets();
		$this->assertSame(
			array(
				array( 'dashboard_primary', 'dashboard', 'normal' ),
				array( 'dashboard_primary', 'dashboard', 'side' ),
				array( 'dashboard_primary', 'dashboard', 'column3' ),
				array( 'dashboard_primary', 'dashboard', 'column4' ),
			),
			$GLOBALS['__dbru_calls']['remove_meta_box']
		);
	}

	public function test_niente_per_ospiti_altri_ruoli_e_admin() {
		dbru_test_store_roles(
			array(
				'docente'       => array(
					'hide_widgets'   => array( 'dashboard_primary' ),
					'widgets'        => array( array( 'title' => 'W', 'links' => $this->links() ) ),
					'menu_links'     => $this->links(),
					'adminbar_links' => $this->links(),
				),
				'administrator' => array( 'hide_widgets' => array( 'dashboard_primary' ) ),
			)
		);
		$GLOBALS['__dbru_calls'] = array();
		foreach ( array( null, dbru_test_user( array( 'subscriber' ) ), dbru_test_user( array( 'administrator' ) ) ) as $user ) {
			dbru_test_login( $user );
			$bar = new DBRU_Test_Admin_Bar();
			db_role_ux_role_hide_widgets();
			db_role_ux_role_add_widgets();
			db_role_ux_role_menu_links();
			db_role_ux_role_adminbar_links( $bar );
			$this->assertSame( array(), $bar->added );
		}
		$this->assertSame( array(), $GLOBALS['__dbru_calls'] );
		$this->assertSame( array(), $GLOBALS['menu'] );
	}

	public function test_widget_aggiunti() {
		dbru_test_store_roles(
			array(
				'docente' => array(
					'widgets' => array(
						array( 'id' => 'link1', 'title' => 'Link "utili" & co', 'links' => $this->links(), 'context' => 'side' ),
						array( 'id' => 'txt1', 'type' => 'text', 'title' => 'Avviso', 'content' => '<strong>Collegio</strong> lunedì' ),
					),
				),
			)
		);
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		db_role_ux_role_add_widgets();

		$added = $GLOBALS['__dbru_calls']['wp_add_dashboard_widget'];
		$this->assertSame( array( 'dbru_link1', 'Link &quot;utili&quot; &amp; co', 'side' ), array( $added[0][0], $added[0][1], $added[0][3] ) );
		$this->assertSame( array( 'dbru_txt1', 'normal' ), array( $added[1][0], $added[1][3] ) );

		ob_start();
		call_user_func( $added[1][2] );
		$this->assertSame( '<div class="db-role-ux-text"><p><strong>Collegio</strong> lunedì</p></div>', ob_get_clean() );
	}

	public function test_render_dei_link_risolti_ed_escapati() {
		$html = db_role_ux_render_widget(
			array(
				'type'  => 'links',
				'links' => array(
					array( 'label' => 'Area <b>', 'url' => '/area-riservata/' ),
					array( 'label' => 'Pagine', 'url' => 'edit.php?post_type=page&orderby=title' ),
				),
			)
		);
		$this->assertStringContainsString( '<a href="https://scuola.example/area-riservata/">Area &lt;b&gt;</a>', $html );
		$this->assertStringContainsString( 'href="https://scuola.example/wp-admin/edit.php?post_type=page&amp;orderby=title"', $html );
	}

	public function test_card_solo_dei_gruppi_sbloccati() {
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		$html = db_role_ux_render_widget( array( 'type' => 'cards', 'groups' => array( 'ms_gestisci_moduli', 'db_area_docenti', 'inesistente' ) ) );
		$this->assertStringContainsString( 'Area Docenti', $html );
		$this->assertStringContainsString( 'Programmazioni', $html );
		$this->assertStringNotContainsString( 'Segreteria', $html, 'il docente non ha ms_gestisci_moduli' );

		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$this->assertStringContainsString( 'non ci sono funzioni disponibili', db_role_ux_render_widget( array( 'type' => 'cards', 'groups' => array( 'db_area_docenti' ) ) ) );
	}

	public function test_css_della_bacheca_solo_se_servono() {
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		db_role_ux_dashboard_assets( 'index.php' );
		$this->assertArrayNotHasKey( 'wp_enqueue_style', $GLOBALS['__dbru_calls'] );

		dbru_test_store_roles( array( 'docente' => array( 'widgets' => array( array( 'title' => 'W', 'links' => $this->links() ) ) ) ) );
		db_role_ux_dashboard_assets( 'edit.php' );
		$this->assertArrayNotHasKey( 'wp_enqueue_style', $GLOBALS['__dbru_calls'] );
		db_role_ux_dashboard_assets( 'index.php' );
		$this->assertStringEndsWith( '/assets/css/dashboard.css', $GLOBALS['__dbru_calls']['wp_enqueue_style']['db-role-ux-dashboard'][0] );
		$this->assertFileExists( DB_ROLE_UX_DIR . 'assets/css/dashboard.css' );
	}

	public function test_menu_laterale_sotto_la_bacheca() {
		$GLOBALS['menu'] = array(
			'2' => array( 'Bacheca', 'read', 'index.php' ),
			'4' => array( '', 'read', 'separator1' ),
		);
		dbru_test_store_roles(
			array(
				'docente' => array(
					'menu_links' => array(
						array( 'label' => 'Registro & orari', 'url' => 'https://registro.example/?a=1&b=2', 'icon' => 'dashicons-book' ),
						array( 'label' => 'Profilo', 'url' => 'profile.php' ),
					),
				),
			)
		);
		dbru_test_login( dbru_test_user( array( 'docente' ) ) );
		db_role_ux_role_menu_links();

		$menu = $GLOBALS['menu'];
		ksort( $menu );
		$this->assertSame( array( '2', '2.900', '2.901', '4' ), array_map( 'strval', array_keys( $menu ) ) );
		$this->assertSame( 'Registro &amp; orari', $menu['2.900'][0] );
		$this->assertSame( 'read', $menu['2.900'][1] );
		$this->assertSame( 'https://registro.example/?a=1&amp;b=2', $menu['2.900'][2] );
		$this->assertSame( 'dashicons-book', $menu['2.900'][6] );
		$this->assertSame( 'https://scuola.example/wp-admin/profile.php', $menu['2.901'][2] );
		$this->assertSame( 'dashicons-admin-links', $menu['2.901'][6] );
	}

	public function test_admin_bar() {
		dbru_test_store_roles( array( 'subscriber' => array( 'adminbar_links' => array( array( 'label' => 'Area "riservata"', 'url' => '/area-riservata/' ) ) ) ) );
		dbru_test_login( dbru_test_user( array( 'subscriber' ) ) );
		$bar = new DBRU_Test_Admin_Bar();
		db_role_ux_role_adminbar_links( $bar );
		$this->assertSame(
			array(
				array(
					'id'    => 'db-role-ux-link-0',
					'title' => 'Area &quot;riservata&quot;',
					'href'  => 'https://scuola.example/area-riservata/',
				),
			),
			$bar->added
		);
	}
}
