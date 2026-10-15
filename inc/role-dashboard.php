<?php
/**
 * Bacheca per ruolo: applicazione delle impostazioni.
 *
 * Ordine sugli hook di wp_dashboard_setup:
 *   9998  rilevazione dei widget registrati (per la scelta da pannello)
 *   9999  pulizia globale (cleanup.php) e widget nascosti per ruolo
 *   10000 widget aggiunti per ruolo: dopo la pulizia, così nemmeno la
 *         tabula rasa (dashboard_nuke_all) li rimuove.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contesti della bacheca in cui cercare un widget da nascondere.
 */
const DB_ROLE_UX_DASHBOARD_CONTEXTS = array( 'normal', 'side', 'column3', 'column4' );

/**
 * Prefisso degli ID delle meta box aggiunte dal plugin.
 */
const DB_ROLE_UX_WIDGET_PREFIX = 'dbru_';

/**
 * Impostazioni per ruolo dell'utente corrente.
 *
 * @return array
 */
function db_role_ux_current_role_settings() {
	if ( ! is_user_logged_in() ) {
		return db_role_ux_empty_role_settings();
	}
	return db_role_ux_user_role_settings( wp_get_current_user() );
}

/**
 * =====================================================================
 *  RILEVAZIONE DEI WIDGET
 *  Registra ID, titolo e contesto dei widget presenti in bacheca, per
 *  chiunque la apra (alcuni widget esistono solo per certi ruoli). Solo
 *  dati tecnici, nessun dato personale. L'opzione si riscrive solo se
 *  qualcosa è cambiato.
 * =====================================================================
 */
add_action( 'wp_dashboard_setup', 'db_role_ux_record_widgets', 9998 );
function db_role_ux_record_widgets() {
	global $wp_meta_boxes;

	if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
		return;
	}

	$seen    = db_role_ux_seen_widgets();
	$updated = $seen;

	foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
		foreach ( (array) $priorities as $boxes ) {
			foreach ( (array) $boxes as $id => $box ) {
				$id = (string) $id;
				if ( ! is_array( $box ) || 0 === strpos( $id, DB_ROLE_UX_WIDGET_PREFIX ) || '' === db_role_ux_sanitize_widget_id( $id ) ) {
					continue;
				}
				$title          = isset( $box['title'] ) ? trim( wp_strip_all_tags( (string) $box['title'] ) ) : '';
				$updated[ $id ] = array(
					'title'   => '' !== $title ? $title : $id,
					'context' => (string) $context,
				);
			}
		}
	}

	if ( $updated !== $seen ) {
		update_option( DB_ROLE_UX_SEEN_OPTION, $updated, false );
	}
}

/**
 * =====================================================================
 *  WIDGET NASCOSTI PER RUOLO
 * =====================================================================
 */
add_action( 'wp_dashboard_setup', 'db_role_ux_role_hide_widgets', 9999 );
function db_role_ux_role_hide_widgets() {
	$settings = db_role_ux_current_role_settings();

	foreach ( $settings['hide_widgets'] as $id ) {
		foreach ( DB_ROLE_UX_DASHBOARD_CONTEXTS as $context ) {
			remove_meta_box( $id, 'dashboard', $context );
		}
		db_role_ux_log( "nascosto widget bacheca per ruolo: {$id}" );
	}
}

/**
 * =====================================================================
 *  WIDGET AGGIUNTI PER RUOLO
 * =====================================================================
 */
add_action( 'wp_dashboard_setup', 'db_role_ux_role_add_widgets', 10000 );
function db_role_ux_role_add_widgets() {
	$settings = db_role_ux_current_role_settings();

	foreach ( $settings['widgets'] as $widget ) {
		wp_add_dashboard_widget(
			DB_ROLE_UX_WIDGET_PREFIX . $widget['id'],
			esc_html( $widget['title'] ),
			function () use ( $widget ) {
				echo db_role_ux_render_widget( $widget ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape in db_role_ux_render_widget().
			},
			null,
			null,
			$widget['context']
		);
	}
}

/**
 * HTML del corpo di un widget aggiunto. Tutto l'output è escapato qui.
 *
 * @param array $widget Widget sanitizzato.
 * @return string
 */
function db_role_ux_render_widget( array $widget ) {
	$type = isset( $widget['type'] ) ? $widget['type'] : 'links';

	if ( 'text' === $type ) {
		return '<div class="db-role-ux-text">' . wpautop( wp_kses_post( isset( $widget['content'] ) ? $widget['content'] : '' ) ) . '</div>';
	}

	if ( 'cards' === $type ) {
		return db_role_ux_render_widget_cards( isset( $widget['groups'] ) ? (array) $widget['groups'] : array() );
	}

	$items = '';
	foreach ( isset( $widget['links'] ) ? (array) $widget['links'] : array() as $link ) {
		$items .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( db_role_ux_resolve_url( $link['url'] ) ),
			esc_html( $link['label'] )
		);
	}
	return '<ul class="db-role-ux-links">' . $items . '</ul>';
}

/**
 * Card dell'Area riservata in bacheca: per ogni gruppo scelto, solo se
 * l'utente ha la capability che lo sblocca (come nel launchpad).
 *
 * @param string[] $group_keys Capability dei gruppi scelti.
 * @return string
 */
function db_role_ux_render_widget_cards( array $group_keys ) {
	$groups = db_launchpad_cards();
	$html   = '';

	foreach ( $group_keys as $capability ) {
		if ( empty( $groups[ $capability ]['cards'] ) || ! current_user_can( $capability ) ) {
			continue;
		}
		$cards = '';
		foreach ( (array) $groups[ $capability ]['cards'] as $card ) {
			$cards .= sprintf(
				'<li><a class="db-role-ux-card" href="%s"><span class="db-role-ux-card__title">%s</span><span class="db-role-ux-card__desc">%s</span></a></li>',
				esc_url( isset( $card['url'] ) ? $card['url'] : '#' ),
				esc_html( isset( $card['title'] ) ? $card['title'] : '' ),
				esc_html( isset( $card['desc'] ) ? $card['desc'] : '' )
			);
		}
		$label = isset( $groups[ $capability ]['label'] ) ? $groups[ $capability ]['label'] : $capability;
		$html .= '<h3 class="db-role-ux-cards__label">' . esc_html( $label ) . '</h3><ul class="db-role-ux-cards">' . $cards . '</ul>';
	}

	return '' === $html
		? '<p>Al momento non ci sono funzioni disponibili per il tuo profilo.</p>'
		: $html;
}

/**
 * CSS di widget aggiunti e fascia di benvenuto, solo in bacheca e solo se
 * servono.
 */
add_action( 'admin_enqueue_scripts', 'db_role_ux_dashboard_assets' );
function db_role_ux_dashboard_assets( $hook ) {
	if ( 'index.php' !== $hook ) {
		return;
	}
	$settings = db_role_ux_current_role_settings();
	if ( empty( $settings['widgets'] ) && empty( $settings['banner'] ) ) {
		return;
	}
	wp_enqueue_style( 'db-role-ux-dashboard', plugin_dir_url( DB_ROLE_UX_FILE ) . 'assets/css/dashboard.css', array(), DB_ROLE_UX_VERSION );
}

/**
 * =====================================================================
 *  FASCIA DI BENVENUTO
 *  In cima alla bacheca, sopra il titolo "Bacheca", a tutta larghezza.
 *  Si aggancia ad admin_notices (stampato prima di .wrap) ma non usa la
 *  classe .notice: WordPress non la sposta e non la rende chiudibile.
 * =====================================================================
 */
add_action( 'admin_notices', 'db_role_ux_banner_notice' );
function db_role_ux_banner_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}
	$settings = db_role_ux_current_role_settings();
	if ( empty( $settings['banner'] ) ) {
		return;
	}
	echo db_role_ux_render_banner( $settings['banner'], db_role_ux_banner_placeholders( wp_get_current_user() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape in db_role_ux_render_banner().
}

/**
 * Valori dei segnaposto per un utente: {nome}, {ruolo}, {sito}.
 *
 * @param WP_User $user Utente.
 * @return string[] Segnaposto => valore (non escapato).
 */
function db_role_ux_banner_placeholders( $user ) {
	$names = array();
	$roles = wp_roles()->roles;
	foreach ( (array) $user->roles as $slug ) {
		if ( isset( $roles[ $slug ]['name'] ) ) {
			$names[] = translate_user_role( $roles[ $slug ]['name'] );
		}
	}

	return array(
		'{nome}'  => $user->display_name ? $user->display_name : $user->user_login,
		'{ruolo}' => implode( ', ', $names ),
		'{sito}'  => get_bloginfo( 'name' ),
	);
}

/**
 * Logo della fascia: immagine scelta, logo del sito o niente. Se l'immagine
 * non ha un testo alternativo, si usa il nome del sito (è un logo, non una
 * decorazione).
 *
 * @param array $banner Fascia sanitizzata.
 * @return string
 */
function db_role_ux_banner_logo( array $banner ) {
	$logo = isset( $banner['logo'] ) ? $banner['logo'] : 'site';
	if ( 'none' === $logo ) {
		return '';
	}

	$id = 'media' === $logo ? (int) $banner['logo_id'] : (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		return '';
	}

	$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	return (string) wp_get_attachment_image(
		$id,
		'medium',
		false,
		array(
			'class'   => 'db-role-ux-banner__logo',
			'alt'     => '' !== $alt ? $alt : get_bloginfo( 'name' ),
			'loading' => false,
		)
	);
}

/**
 * HTML della fascia. I valori dei segnaposto vengono escapati prima di
 * entrare nel testo; il testo era già filtrato con wp_kses_post() al
 * salvataggio e lo è di nuovo qui.
 *
 * @param array    $banner Fascia sanitizzata.
 * @param string[] $values Valori dei segnaposto (non escapati).
 * @return string
 */
function db_role_ux_render_banner( array $banner, array $values ) {
	$escaped = array_map( 'esc_html', $values );
	$title   = isset( $banner['title'] ) ? strtr( esc_html( $banner['title'] ), $escaped ) : '';
	$text    = isset( $banner['text'] ) ? strtr( (string) $banner['text'], $escaped ) : '';
	$logo    = db_role_ux_banner_logo( $banner );
	$style   = ! empty( $banner['color'] ) ? ' style="--db-role-ux-accent:' . esc_attr( $banner['color'] ) . '"' : '';

	$html  = '<section class="db-role-ux-banner' . ( $logo ? ' db-role-ux-banner--logo' : '' ) . '" aria-label="Benvenuto"' . $style . '>';
	$html .= $logo ? '<div class="db-role-ux-banner__media">' . $logo . '</div>' : '';
	$html .= '<div class="db-role-ux-banner__body">';
	$html .= '' !== $title ? '<p class="db-role-ux-banner__title">' . $title . '</p>' : '';
	$html .= '' !== trim( $text ) ? '<div class="db-role-ux-banner__text">' . wp_kses_post( wpautop( $text ) ) . '</div>' : '';
	$html .= '</div></section>';

	return $html;
}

/**
 * =====================================================================
 *  LINK NEL MENU LATERALE
 *  Voci di primo livello subito sotto "Bacheca". Si scrivono direttamente
 *  in $menu: add_menu_page() normalizza lo slug come un percorso e
 *  romperebbe gli URL assoluti. WordPress stampa titolo e href delle voci
 *  così come sono, quindi li escapiamo qui.
 * =====================================================================
 */
add_action( 'admin_menu', 'db_role_ux_role_menu_links', 10000 );
function db_role_ux_role_menu_links() {
	global $menu;

	$settings = db_role_ux_current_role_settings();
	if ( empty( $settings['menu_links'] ) || ! is_array( $menu ) ) {
		return;
	}

	foreach ( array_values( $settings['menu_links'] ) as $i => $link ) {
		$position = sprintf( '2.%03d', 900 + $i );
		$label    = esc_html( $link['label'] );

		$menu[ $position ] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$label,
			'read',
			esc_url( db_role_ux_resolve_url( $link['url'] ) ),
			$label,
			'menu-top db-role-ux-menu-link',
			'db-role-ux-menu-' . $i,
			$link['icon'],
		);
	}
}

/**
 * =====================================================================
 *  LINK NELLA ADMIN BAR (backend e frontend)
 * =====================================================================
 */
add_action( 'admin_bar_menu', 'db_role_ux_role_adminbar_links', 100 );
function db_role_ux_role_adminbar_links( $wp_admin_bar ) {
	$settings = db_role_ux_current_role_settings();

	foreach ( array_values( $settings['adminbar_links'] ) as $i => $link ) {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'db-role-ux-link-' . $i,
				'title' => esc_html( $link['label'] ),
				'href'  => db_role_ux_resolve_url( $link['url'] ),
			)
		);
	}
}
