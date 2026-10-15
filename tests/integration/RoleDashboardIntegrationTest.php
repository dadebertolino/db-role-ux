<?php
/**
 * Bacheca per ruolo contro un WordPress reale: opzioni, meta box, admin bar,
 * salvataggio da admin-post.php e disinstallazione.
 *
 * @package DB_Role_UX\Tests\Integration
 */

class RoleDashboardIntegrationTest extends WP_UnitTestCase {

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

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		set_current_screen( 'dashboard' );
	}

	public function tear_down() {
		unset( $GLOBALS['wp_meta_boxes'] );
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	private function login( $role ) {
		$user = self::factory()->user->create_and_get( array( 'role' => $role ) );
		wp_set_current_user( $user->ID );
		return $user;
	}

	private function links() {
		return array(
			array(
				'label' => 'Registro',
				'url'   => 'https://registro.example',
			),
		);
	}

	public function test_opzione_salvata_e_letta() {
		db_role_ux_save_role_settings( 'docente', array( 'hide_widgets' => array( '0', 'dashboard_primary' ) ) );
		$this->assertSame( array( '0', 'dashboard_primary' ), get_option( DB_ROLE_UX_OPTION )['docente']['hide_widgets'] );
		$this->assertSame( array( '0', 'dashboard_primary' ), db_role_ux_user_role_settings( $this->login( 'docente' ) )['hide_widgets'] );

		db_role_ux_save_role_settings( 'docente', array() );
		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ) );
	}

	public function test_bacheca_per_ruolo() {
		db_role_ux_save_role_settings(
			'docente',
			array(
				'hide_widgets' => array( 'dashboard_primary' ),
				'widgets'      => array(
					array(
						'id'      => 'link1',
						'title'   => 'Link utili',
						'context' => 'side',
						'links'   => $this->links(),
					),
				),
			)
		);
		$noop = '__return_null';

		foreach ( array( 'docente' => true, 'subscriber' => false, 'administrator' => false ) as $role => $customized ) {
			unset( $GLOBALS['wp_meta_boxes'] );
			wp_add_dashboard_widget( 'dashboard_primary', 'Eventi', $noop, null, null, 'side' );
			$this->login( $role );

			db_role_ux_record_widgets();
			db_role_ux_role_hide_widgets();
			db_role_ux_role_add_widgets();

			$side = $GLOBALS['wp_meta_boxes']['dashboard']['side']['core'];
			$this->assertSame( ! $customized, ! empty( $side['dashboard_primary'] ), "$role: widget nascosto" );
			$this->assertSame( $customized, ! empty( $side['dbru_link1'] ), "$role: widget aggiunto" );
		}

		$this->assertSame( array( 'dashboard_primary' ), array_keys( db_role_ux_seen_widgets() ), 'rilevato, i nostri esclusi' );
	}

	public function test_aggiunti_anche_dopo_la_tabula_rasa() {
		add_filter(
			'db_role_ux_config',
			$nuke = function ( $config ) {
				$config['dashboard_nuke_all'] = true;
				return $config;
			}
		);
		db_role_ux_save_role_settings( 'subscriber', array( 'widgets' => array( array( 'id' => 'w', 'title' => 'Benvenuto', 'type' => 'text', 'content' => 'Ciao' ) ) ) );
		$this->login( 'subscriber' );
		wp_add_dashboard_widget( 'dashboard_activity', 'Attività', '__return_null' );

		do_action( 'wp_dashboard_setup' );

		$this->assertSame( array( 'normal' ), array_keys( $GLOBALS['wp_meta_boxes']['dashboard'] ) );
		$this->assertArrayHasKey( 'dbru_w', $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'] );
		remove_filter( 'db_role_ux_config', $nuke );
	}

	public function test_admin_bar_e_menu() {
		global $menu;
		db_role_ux_save_role_settings(
			'docente',
			array(
				'menu_links'     => array(
					array(
						'label' => 'Profilo',
						'url'   => 'profile.php',
						'icon'  => 'dashicons-admin-users',
					),
				),
				'adminbar_links' => array(
					array(
						'label' => 'Area riservata',
						'url'   => '/area-riservata/',
					),
				),
			)
		);
		$this->login( 'docente' );

		$bar = new WP_Admin_Bar();
		db_role_ux_role_adminbar_links( $bar );
		$this->assertSame( home_url( '/area-riservata/' ), $bar->get_node( 'db-role-ux-link-0' )->href );

		$menu = array( 2 => array( 'Bacheca', 'read', 'index.php' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		db_role_ux_role_menu_links();
		$this->assertSame( esc_url( admin_url( 'profile.php' ) ), $menu['2.900'][2] );
	}

	public function test_salvataggio_da_admin_post() {
		$this->login( 'administrator' );
		$_POST    = array(
			'action'         => 'db_role_ux_save_role',
			'role'           => 'docente',
			'do'             => 'save',
			'_wpnonce'       => wp_create_nonce( 'db_role_ux_save_role_docente' ),
			'adminbar_links' => 'Sito | /',
			'widgets'        => array(
				array(
					'title'   => 'Avviso',
					'type'    => 'text',
					'content' => '<em>Collegio docenti</em><script>alert(1)</script>',
				),
			),
		);
		$_REQUEST = $_POST;

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new Exception( $location );
			}
		);
		try {
			db_role_ux_handle_save_role();
			$this->fail( 'Nessun redirect.' );
		} catch ( WPDieException $e ) {
			$this->fail( 'wp_die: ' . $e->getMessage() );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'saved=ok', $e->getMessage() );
		}

		$saved = db_role_ux_stored_roles()['docente'];
		$this->assertSame( '<em>Collegio docenti</em>alert(1)', $saved['widgets'][0]['content'] );
		$this->assertSame( '/', $saved['adminbar_links'][0]['url'] );
	}

	public function test_salvataggio_con_nonce_sbagliato() {
		$this->login( 'administrator' );
		$_POST    = array(
			'role'     => 'docente',
			'_wpnonce' => wp_create_nonce( 'db_role_ux_save_role_subscriber' ),
		);
		$_REQUEST = $_POST;
		$this->expectException( WPDieException::class );
		db_role_ux_handle_save_role();
	}

	public function test_disinstallazione() {
		db_role_ux_save_role_settings( 'docente', array( 'hide_widgets' => array( 'x' ) ) );
		update_option( DB_ROLE_UX_SEEN_OPTION, array( 'x' => array( 'title' => 'X', 'context' => 'normal' ) ) );
		$site = null;
		if ( is_multisite() ) {
			$site = self::factory()->blog->create();
			switch_to_blog( $site );
			update_option( DB_ROLE_UX_SEEN_OPTION, array( 'y' => array( 'title' => 'Y', 'context' => 'normal' ) ) );
			restore_current_blog();
		}

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'db-role-ux/db-role-ux.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( get_option( DB_ROLE_UX_OPTION ) );
		$this->assertFalse( get_option( DB_ROLE_UX_SEEN_OPTION ) );
		if ( $site ) {
			switch_to_blog( $site );
			$this->assertFalse( get_option( DB_ROLE_UX_SEEN_OPTION ) );
			restore_current_blog();
		}
	}
}
