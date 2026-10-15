<?php
/**
 * Plugin Name: DB Role UX E2E Fixture
 * Description: Condizioni di test per gli E2E di DB Role UX. Attivo SOLO in
 *              ambiente wp-env (mu-plugin). NON fa parte del pacchetto
 *              distribuito.
 *
 * Sul sito della scuola i widget delle circolari e le voci "Design Scuole"
 * della admin bar arrivano da altri plugin e dal tema. Qui li simuliamo con
 * gli stessi ID, più un widget e una voce NON dichiarati in configurazione,
 * che devono restare: la pulizia è mirata, non una tabula rasa.
 *
 * Il redirect post-login è acceso verso /area-riservata/ (in produzione è
 * spento di default: quel caso è coperto dagli unit test).
 *
 * REST POST /dbru-e2e/v1/reset azzera la bacheca per ruolo fra uno spec e
 * l'altro.
 *
 * @package DB_Role_UX\Tests\Fixtures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_dashboard_setup',
	function () {
		$render = function ( $post, $box ) {
			echo '<p>' . esc_html( $box['title'] ) . '</p>';
		};
		wp_add_dashboard_widget( '0', 'Circolari non firmate', $render );
		wp_add_dashboard_widget( '1', 'Notifiche nuove circolari', $render );
		wp_add_dashboard_widget( '2', 'Circolari firmate', $render );
		wp_add_dashboard_widget( '3', 'Menu utente', $render );
		wp_add_dashboard_widget( 'e2e_widget_libero', 'Widget non dichiarato', $render );
	}
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		$bar->add_node(
			array(
				'id'    => 'design-scuole',
				'title' => 'About Design Scuole',
			)
		);
		$bar->add_node(
			array(
				'id'     => 'design-scuole-conf',
				'parent' => 'design-scuole',
				'title'  => 'Configurazione',
			)
		);
		$bar->add_node(
			array(
				'id'    => 'manuale',
				'title' => 'Manuale',
			)
		);
		$bar->add_node(
			array(
				'id'    => 'dbru-e2e-keep',
				'title' => 'Voce non dichiarata',
			)
		);
	},
	100
);

add_filter(
	'db_role_ux_config',
	function ( $config ) {
		$config['login_redirect'] = array(
			'enabled' => true,
			'url'     => home_url( '/area-riservata/' ),
		);
		return $config;
	}
);

/**
 * REST POST /dbru-e2e/v1/reset → azzera la bacheca per ruolo (impostazioni e
 * widget rilevati), per far partire ogni spec da uno stato noto.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'dbru-e2e/v1',
			'/reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					delete_option( 'db_role_ux_roles' );
					delete_option( 'db_role_ux_seen_widgets' );
					return array( 'ok' => true );
				},
			)
		);
	}
);
