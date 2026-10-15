<?php
/**
 * Bacheca per ruolo: modello dei dati.
 *
 * Le impostazioni per ruolo (widget da nascondere, widget aggiunti, link nel
 * menu laterale e nella admin bar) si modificano da Strumenti → DB Role UX e
 * vivono nell'opzione db_role_ux_roles. Entrano in db_role_ux_config() alla
 * chiave 'roles', PRIMA del filtro db_role_ux_config: un mu-plugin può
 * sempre sovrascrivere ciò che è stato salvato da pannello.
 *
 * Tutto ciò che arriva dall'opzione passa da db_role_ux_sanitize_role_settings()
 * anche in lettura: l'opzione può essere stata scritta a mano o da una
 * versione precedente.
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DB_ROLE_UX_OPTION      = 'db_role_ux_roles';
const DB_ROLE_UX_SEEN_OPTION = 'db_role_ux_seen_widgets';

/**
 * Tipi di widget aggiungibili alla bacheca.
 */
const DB_ROLE_UX_WIDGET_TYPES = array(
	'links' => 'Elenco di link',
	'text'  => 'Testo libero',
	'cards' => "Card dell'Area riservata",
);

/**
 * Impostazioni vuote di un ruolo.
 *
 * @return array
 */
function db_role_ux_empty_role_settings() {
	return array(
		'hide_widgets'   => array(),
		'widgets'        => array(),
		'menu_links'     => array(),
		'adminbar_links' => array(),
	);
}

/**
 * Impostazioni per ruolo salvate da pannello, sanitizzate.
 *
 * @return array slug ruolo => impostazioni.
 */
function db_role_ux_stored_roles() {
	$stored = get_option( DB_ROLE_UX_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		return array();
	}

	$roles = array();
	foreach ( $stored as $slug => $settings ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || ! is_array( $settings ) ) {
			continue;
		}
		$roles[ $slug ] = db_role_ux_sanitize_role_settings( $settings );
	}
	return $roles;
}

/**
 * ID di un widget della bacheca: lettere, cifre, trattino e underscore.
 * '0' è un ID valido (i widget delle circolari usano 0, 1, 2, 3).
 *
 * @param mixed $id ID grezzo.
 * @return string
 */
function db_role_ux_sanitize_widget_id( $id ) {
	return is_scalar( $id ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id ) : '';
}

/**
 * URL di un link. Ammessi:
 *  - assoluti http/https;
 *  - percorsi del sito, con lo slash iniziale ('/area-riservata/');
 *  - pagine del backend, senza slash ('edit.php?post_type=page').
 * Qualunque altro schema (javascript:, data:, …) e gli URL '//host'
 * vengono scartati.
 *
 * @param mixed $url URL grezzo.
 * @return string URL accettato o stringa vuota.
 */
function db_role_ux_sanitize_url( $url ) {
	if ( ! is_scalar( $url ) ) {
		return '';
	}
	$url = trim( (string) $url );
	if ( '' === $url || 0 === strpos( $url, '//' ) || false !== strpos( $url, '\\' ) ) {
		return '';
	}

	if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url ) ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	// Relativo: niente spazi, controlli, virgolette o parentesi angolari.
	return preg_replace( '/[\s\x00-\x1f"\'<>`]/', '', $url );
}

/**
 * URL assoluto di un link già sanitizzato.
 *
 * @param string $url URL sanitizzato.
 * @return string
 */
function db_role_ux_resolve_url( $url ) {
	if ( preg_match( '#^https?://#i', $url ) ) {
		return $url;
	}
	if ( 0 === strpos( $url, '/' ) ) {
		return home_url( $url );
	}
	return admin_url( $url );
}

/**
 * Un link: etichetta e URL obbligatori, icona dashicons opzionale.
 *
 * @param mixed $link      Link grezzo (array).
 * @param bool  $with_icon Conserva l'icona (voci del menu laterale).
 * @return array|null
 */
function db_role_ux_sanitize_link( $link, $with_icon = false ) {
	if ( ! is_array( $link ) ) {
		return null;
	}
	$label = isset( $link['label'] ) ? sanitize_text_field( $link['label'] ) : '';
	$url   = isset( $link['url'] ) ? db_role_ux_sanitize_url( $link['url'] ) : '';
	if ( '' === $label || '' === $url ) {
		return null;
	}

	$clean = array(
		'label' => $label,
		'url'   => $url,
	);
	if ( $with_icon ) {
		$icon          = isset( $link['icon'] ) ? (string) $link['icon'] : '';
		$clean['icon'] = preg_match( '/^dashicons-[a-z0-9\-]+$/', $icon ) ? $icon : 'dashicons-admin-links';
	}
	return $clean;
}

/**
 * Elenco di link sanitizzati (scarta quelli non validi).
 *
 * @param mixed $links     Elenco grezzo.
 * @param bool  $with_icon Conserva l'icona.
 * @return array[]
 */
function db_role_ux_sanitize_links( $links, $with_icon = false ) {
	$clean = array();
	foreach ( is_array( $links ) ? $links : array() as $link ) {
		$link = db_role_ux_sanitize_link( $link, $with_icon );
		if ( $link ) {
			$clean[] = $link;
		}
	}
	return $clean;
}

/**
 * Link scritti una per riga, nel formato "Etichetta | URL" (più "| icona"
 * per il menu laterale). Le righe senza URL valido vengono scartate.
 *
 * @param string $text      Testo della textarea.
 * @param bool   $with_icon Terza colonna: icona dashicons.
 * @return array[]
 */
function db_role_ux_parse_links( $text, $with_icon = false ) {
	$links = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$links[] = array(
			'label' => $parts[0],
			'url'   => $parts[1],
			'icon'  => isset( $parts[2] ) ? $parts[2] : '',
		);
	}
	return db_role_ux_sanitize_links( $links, $with_icon );
}

/**
 * Inverso di db_role_ux_parse_links(): testo per la textarea.
 *
 * @param array[] $links     Link sanitizzati.
 * @param bool    $with_icon Aggiunge l'icona.
 * @return string
 */
function db_role_ux_format_links( array $links, $with_icon = false ) {
	$lines = array();
	foreach ( $links as $link ) {
		$line = $link['label'] . ' | ' . $link['url'];
		if ( $with_icon && ! empty( $link['icon'] ) && 'dashicons-admin-links' !== $link['icon'] ) {
			$line .= ' | ' . $link['icon'];
		}
		$lines[] = $line;
	}
	return implode( "\n", $lines );
}

/**
 * Un widget aggiunto. Restituisce null se manca il titolo o il contenuto
 * del tipo scelto.
 *
 * @param mixed $widget Widget grezzo.
 * @return array|null
 */
function db_role_ux_sanitize_widget( $widget ) {
	if ( ! is_array( $widget ) ) {
		return null;
	}
	$type  = isset( $widget['type'] ) && is_string( $widget['type'] ) && array_key_exists( $widget['type'], DB_ROLE_UX_WIDGET_TYPES ) ? $widget['type'] : 'links';
	$title = isset( $widget['title'] ) ? sanitize_text_field( $widget['title'] ) : '';
	if ( '' === $title ) {
		return null;
	}

	$clean = array(
		'id'      => isset( $widget['id'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $widget['id'] ) ) : '',
		'type'    => $type,
		'title'   => $title,
		'context' => isset( $widget['context'] ) && 'side' === $widget['context'] ? 'side' : 'normal',
	);

	switch ( $type ) {
		case 'text':
			$clean['content'] = isset( $widget['content'] ) ? wp_kses_post( (string) $widget['content'] ) : '';
			if ( '' === trim( $clean['content'] ) ) {
				return null;
			}
			break;

		case 'cards':
			$groups          = isset( $widget['groups'] ) ? (array) $widget['groups'] : array();
			$clean['groups'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $groups ) ) ) );
			if ( empty( $clean['groups'] ) ) {
				return null;
			}
			break;

		case 'links':
		default:
			$clean['links'] = db_role_ux_sanitize_links( isset( $widget['links'] ) ? $widget['links'] : array() );
			if ( empty( $clean['links'] ) ) {
				return null;
			}
	}

	return $clean;
}

/**
 * Impostazioni complete di un ruolo. Gli ID dei widget mancanti o duplicati
 * vengono generati: servono a ritrovare il widget fra un salvataggio e
 * l'altro e come ID della meta box in bacheca (dbru_<id>).
 *
 * @param array $raw Impostazioni grezze.
 * @return array
 */
function db_role_ux_sanitize_role_settings( array $raw ) {
	$clean = db_role_ux_empty_role_settings();

	$hide = isset( $raw['hide_widgets'] ) ? (array) $raw['hide_widgets'] : array();
	foreach ( $hide as $id ) {
		$id = db_role_ux_sanitize_widget_id( $id );
		if ( '' !== $id && ! in_array( $id, $clean['hide_widgets'], true ) ) {
			$clean['hide_widgets'][] = $id;
		}
	}

	$ids = array();
	foreach ( isset( $raw['widgets'] ) ? (array) $raw['widgets'] : array() as $widget ) {
		$widget = db_role_ux_sanitize_widget( $widget );
		if ( ! $widget ) {
			continue;
		}
		if ( '' === $widget['id'] || isset( $ids[ $widget['id'] ] ) ) {
			$base = substr( md5( $widget['type'] . '|' . $widget['title'] . '|' . count( $ids ) ), 0, 8 );
			$id   = $base;
			for ( $n = 2; isset( $ids[ $id ] ); $n++ ) {
				$id = $base . $n;
			}
			$widget['id'] = $id;
		}
		$ids[ $widget['id'] ] = true;
		$clean['widgets'][]   = $widget;
	}

	$clean['menu_links']     = db_role_ux_sanitize_links( isset( $raw['menu_links'] ) ? $raw['menu_links'] : array(), true );
	$clean['adminbar_links'] = db_role_ux_sanitize_links( isset( $raw['adminbar_links'] ) ? $raw['adminbar_links'] : array() );

	return $clean;
}

/**
 * Le impostazioni di un ruolo sono vuote?
 *
 * @param array $settings Impostazioni sanitizzate.
 * @return bool
 */
function db_role_ux_role_settings_empty( array $settings ) {
	return empty( $settings['hide_widgets'] ) && empty( $settings['widgets'] )
		&& empty( $settings['menu_links'] ) && empty( $settings['adminbar_links'] );
}

/**
 * Salva (o azzera, se vuote) le impostazioni di un ruolo.
 *
 * @param string $role_slug Slug del ruolo.
 * @param array  $raw       Impostazioni grezze.
 * @return array Impostazioni salvate.
 */
function db_role_ux_save_role_settings( $role_slug, array $raw ) {
	$stored   = db_role_ux_stored_roles();
	$settings = db_role_ux_sanitize_role_settings( $raw );

	if ( db_role_ux_role_settings_empty( $settings ) ) {
		unset( $stored[ $role_slug ] );
	} else {
		$stored[ $role_slug ] = $settings;
	}

	if ( empty( $stored ) ) {
		delete_option( DB_ROLE_UX_OPTION );
	} else {
		update_option( DB_ROLE_UX_OPTION, $stored );
	}
	return $settings;
}

/**
 * Impostazioni effettive per un utente: unione di quelle dei suoi ruoli,
 * nell'ordine dei ruoli. Widget senza duplicati per ID, link senza
 * duplicati per URL.
 *
 * FAIL-SAFE: chi ha manage_options non ha widget da nascondere. Widget e
 * link aggiunti valgono invece per tutti: aggiungono, non tolgono.
 *
 * @param WP_User $user Utente.
 * @return array
 */
function db_role_ux_user_role_settings( $user ) {
	$merged = db_role_ux_empty_role_settings();
	if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
		return $merged;
	}

	$config = db_role_ux_config();
	$roles  = isset( $config['roles'] ) && is_array( $config['roles'] ) ? $config['roles'] : array();
	$seen   = array(
		'widgets'        => array(),
		'menu_links'     => array(),
		'adminbar_links' => array(),
	);

	foreach ( (array) $user->roles as $role ) {
		if ( empty( $roles[ $role ] ) || ! is_array( $roles[ $role ] ) ) {
			continue;
		}
		$settings = array_merge( db_role_ux_empty_role_settings(), $roles[ $role ] );

		$merged['hide_widgets'] = array_merge( $merged['hide_widgets'], (array) $settings['hide_widgets'] );

		foreach ( (array) $settings['widgets'] as $widget ) {
			if ( isset( $widget['id'] ) && ! isset( $seen['widgets'][ $widget['id'] ] ) ) {
				$seen['widgets'][ $widget['id'] ] = true;
				$merged['widgets'][]              = $widget;
			}
		}
		foreach ( array( 'menu_links', 'adminbar_links' ) as $key ) {
			foreach ( (array) $settings[ $key ] as $link ) {
				if ( isset( $link['url'] ) && ! isset( $seen[ $key ][ $link['url'] ] ) ) {
					$seen[ $key ][ $link['url'] ] = true;
					$merged[ $key ][]             = $link;
				}
			}
		}
	}

	$merged['hide_widgets'] = user_can( $user, 'manage_options' )
		? array()
		: array_values( array_unique( $merged['hide_widgets'] ) );

	return $merged;
}

/**
 * Widget della bacheca rilevati (ID => titolo, contesto), per la scelta da
 * pannello di quelli da nascondere.
 *
 * @return array
 */
function db_role_ux_seen_widgets() {
	$seen = get_option( DB_ROLE_UX_SEEN_OPTION, array() );
	return is_array( $seen ) ? $seen : array();
}
