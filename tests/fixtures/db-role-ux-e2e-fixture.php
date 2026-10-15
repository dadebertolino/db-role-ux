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
		wp_add_dashboard_widget( 'dbru_e2e_keep', 'Widget non dichiarato', $render );
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
