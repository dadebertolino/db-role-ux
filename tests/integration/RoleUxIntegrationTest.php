<?php
/**
 * DB Role UX contro un WordPress reale.
 *
 * @package DB_Role_UX\Tests\Integration
 */

class RoleUxIntegrationTest extends WP_UnitTestCase {

	/**
	 * Filtro di configurazione aggiunto dal test corrente.
	 *
	 * @var callable|null
	 */
	private $config_filter = null;

	public static function wpSetUpBeforeClass( $factory ) {
		add_role(
			'docente',
			'Docente',
			array(
				'read'            => true,
				'db_area_docenti' => true,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		remove_role( 'docente' );
	}

	public function tear_down() {
		if ( $this->config_filter ) {
			remove_filter( 'db_role_ux_config', $this->config_filter );
		}
		unset( $GLOBALS['wp_meta_boxes'] );
		parent::tear_down();
	}

	private function config( array $overrides ) {
		$this->config_filter = function ( $config ) use ( $overrides ) {
			return array_merge( $config, $overrides );
		};
		add_filter( 'db_role_ux_config', $this->config_filter );
	}

	private function user( $role ) {
		return self::factory()->user->create_and_get( array( 'role' => $role ) );
	}

	public function test_plugin_caricato_con_versione_coerente() {
		$this->assertTrue( defined( 'DB_ROLE_UX_VERSION' ) );
		$header = get_file_data( DB_ROLE_UX_FILE, array( 'Version' => 'Version' ) );
		$this->assertSame( DB_ROLE_UX_VERSION, $header['Version'] );
		$this->assertTrue( class_exists( 'DB_GitHub_Updater_V2' ) );
		$this->assertTrue( shortcode_exists( 'db_area_riservata' ) );
	}

	public function test_policy_con_utenti_reali() {
		$this->assertFalse( db_role_ux_user_matches( $this->user( 'administrator' ) ) );
		$this->assertTrue( db_role_ux_user_matches( $this->user( 'editor' ) ) );
		$this->assertTrue( db_role_ux_user_matches( $this->user( 'subscriber' ) ) );
		$this->assertFalse( db_role_ux_user_matches( new WP_User( 0 ) ) );
		$this->assertFalse( db_role_ux_user_matches( new WP_Error( 'x' ) ) );
	}

	public function test_policy_per_capability_e_per_ruolo() {
		$this->config(
			array(
				'target_mode'       => 'capability',
				'target_capability' => 'db_area_docenti',
			)
		);
		$this->assertTrue( db_role_ux_user_matches( $this->user( 'docente' ) ) );
		$this->assertFalse( db_role_ux_user_matches( $this->user( 'subscriber' ) ) );

		$extra = $this->user( 'subscriber' );
		$extra->add_cap( 'db_area_docenti' );
		$this->assertTrue( db_role_ux_user_matches( $extra ), 'capability assegnata al singolo utente' );
		$this->assertFalse( db_role_ux_role_matches( 'subscriber' ), 'il ruolo resta fuori' );
		$this->assertTrue( db_role_ux_role_matches( 'docente' ) );
	}

	public function test_super_admin_mai_ripulito() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Solo multisite.' );
		}
		$user = $this->user( 'subscriber' );
		$this->assertTrue( db_role_ux_user_matches( $user ) );
		grant_super_admin( $user->ID );
		$this->assertFalse( db_role_ux_user_matches( new WP_User( $user->ID ) ) );
		revoke_super_admin( $user->ID );
	}

	public function test_bacheca_ripulita_solo_per_i_target() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		set_current_screen( 'dashboard' );
		$noop = '__return_null';

		foreach ( array( 'subscriber' => false, 'administrator' => true ) as $role => $kept ) {
			unset( $GLOBALS['wp_meta_boxes'] );
			add_meta_box( '0', 'Circolari non firmate', $noop, 'dashboard', 'normal' );
			add_meta_box( 'dashboard_activity', 'Attività', $noop, 'dashboard', 'normal' );
			wp_set_current_user( $this->user( $role )->ID );

			db_role_ux_clean_dashboard();

			$this->assertSame( $kept, ! empty( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['default']['0'] ), $role );
			$this->assertNotEmpty( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['default']['dashboard_activity'], "$role: non dichiarato" );
		}
	}

	public function test_admin_bar_ripulita_solo_per_i_target() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		foreach ( array( 'editor' => false, 'administrator' => true ) as $role => $kept ) {
			wp_set_current_user( $this->user( $role )->ID );
			$bar = new WP_Admin_Bar();
			$bar->add_node( array( 'id' => 'wp-logo', 'title' => 'WordPress' ) );
			$bar->add_node( array( 'id' => 'about', 'parent' => 'wp-logo', 'title' => 'Info' ) );
			$bar->add_node( array( 'id' => 'my-account', 'title' => 'Account' ) );

			db_role_ux_clean_adminbar( $bar );

			$this->assertSame( $kept, null !== $bar->get_node( 'wp-logo' ), $role );
			$this->assertNotNull( $bar->get_node( 'my-account' ), "$role: non dichiarato" );
		}
	}

	public function test_menu_laterale() {
		global $menu;
		$this->config( array( 'admin_menu_pages' => array( 'tools.php' ) ) );
		$menu = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			5  => array( 'Articoli', 'edit_posts', 'edit.php' ),
			75 => array( 'Strumenti', 'edit_posts', 'tools.php' ),
		);
		wp_set_current_user( $this->user( 'editor' )->ID );

		db_role_ux_clean_admin_menu();

		$this->assertSame( array( 'edit.php' ), array_values( wp_list_pluck( $menu, 2 ) ) );
	}

	public function test_filtro_login_redirect_di_wordpress() {
		$area = home_url( '/area-riservata/' );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', $this->user( 'subscriber' ) ), 'spento' );

		$this->config(
			array(
				'login_redirect' => array(
					'enabled' => true,
					'url'     => $area,
				),
			)
		);
		$this->assertSame( $area, apply_filters( 'login_redirect', admin_url(), '', $this->user( 'subscriber' ) ) );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', $this->user( 'administrator' ) ) );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', new WP_Error( 'invalid_username' ) ) );
	}

	public function test_shortcode() {
		$post = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $post ) );

		wp_set_current_user( 0 );
		$this->assertStringContainsString( wp_login_url( get_permalink() ), html_entity_decode( do_shortcode( '[db_area_riservata]' ) ) );

		wp_set_current_user( $this->user( 'docente' )->ID );
		$html = do_shortcode( '[db_area_riservata]' );
		$this->assertStringContainsString( 'Area Docenti', $html );
		$this->assertStringNotContainsString( 'Segreteria', $html );

		wp_set_current_user( $this->user( 'subscriber' )->ID );
		$this->assertStringContainsString( 'non ci sono funzioni disponibili', do_shortcode( '[db_area_riservata]' ) );
	}

	public function test_pagina_di_riepilogo() {
		wp_set_current_user( $this->user( 'administrator' )->ID );
		$rows = array_column( db_role_ux_role_preview(), null, 'slug' );
		$this->assertFalse( $rows['administrator']['targeted'] );
		$this->assertTrue( $rows['subscriber']['targeted'] );
		$this->assertTrue( $rows['docente']['targeted'] );
		$this->assertContains( 'Docente', db_role_ux_roles_with_cap( 'db_area_docenti' ) );

		ob_start();
		db_role_ux_render_admin_page();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'db-role-ux-wrap', $html );
		$this->assertStringContainsString( '<code>docente</code>', $html );
	}

	public function test_pagina_di_riepilogo_negata_ai_non_admin() {
		wp_set_current_user( $this->user( 'editor' )->ID );
		$this->expectException( WPDieException::class );
		db_role_ux_render_admin_page();
	}
}
