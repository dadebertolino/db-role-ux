<?php
/**
 * Bootstrap dei test UNIT.
 *
 * DB Role UX è procedurale: ogni file di inc/ definisce funzioni e aggancia
 * gli hook al caricamento. Qui stubbiamo il minimo di WordPress che serve
 * (filtri, utenti e ruoli, capability, meta box, admin bar, escape) così il
 * job unit resta leggero: niente MySQL, niente WP test suite. Ciò che
 * richiede WordPress vero (WP_User e WP_Role reali, multisite, la bacheca
 * renderizzata) sta negli integration test e negli E2E.
 *
 * @package DB_Role_UX\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

define( 'DB_ROLE_UX_TEST_ROOT', dirname( __DIR__, 2 ) );

define( 'DB_ROLE_UX_VERSION', 'test' );
define( 'DB_ROLE_UX_FILE', DB_ROLE_UX_TEST_ROOT . '/db-role-ux.php' );
define( 'DB_ROLE_UX_DIR', DB_ROLE_UX_TEST_ROOT . '/' );

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

/* -----------------------------------------------------------------------------
 * Stato globale simulato. Azzerato da dbru_test_reset() fra un test e l'altro.
 * -------------------------------------------------------------------------- */

$GLOBALS['__dbru_filters']    = array();
$GLOBALS['__dbru_shortcodes'] = array();
$GLOBALS['__dbru_calls']      = array();
$GLOBALS['__dbru_user']       = null;
$GLOBALS['__dbru_roles']      = array();
$GLOBALS['__dbru_is_admin']   = false;
$GLOBALS['__dbru_options']    = array();

/* --- Filtri e azioni (priorità e accepted_args come in WordPress) --------- */

function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__dbru_filters'][ $hook ][ (int) $priority ][] = array( $cb, (int) $accepted_args );
	return true;
}
function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $cb, $priority, $accepted_args );
}
function has_filter( $hook, $cb = false ) {
	if ( empty( $GLOBALS['__dbru_filters'][ $hook ] ) ) {
		return false;
	}
	if ( false === $cb ) {
		return true;
	}
	foreach ( $GLOBALS['__dbru_filters'][ $hook ] as $priority => $entries ) {
		foreach ( $entries as $entry ) {
			if ( $entry[0] === $cb ) {
				return $priority;
			}
		}
	}
	return false;
}
function has_action( $hook, $cb = false ) {
	return has_filter( $hook, $cb );
}
function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 1 );
	if ( empty( $GLOBALS['__dbru_filters'][ $hook ] ) ) {
		return $value;
	}
	$by_priority = $GLOBALS['__dbru_filters'][ $hook ];
	ksort( $by_priority );
	foreach ( $by_priority as $entries ) {
		foreach ( $entries as $entry ) {
			$args[0] = call_user_func_array( $entry[0], array_slice( $args, 0, max( 1, $entry[1] ) ) );
		}
	}
	return $args[0];
}
function add_shortcode( $tag, $cb ) {
	$GLOBALS['__dbru_shortcodes'][ $tag ] = $cb;
}

/* --- Opzioni --------------------------------------------------------------- */

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['__dbru_options'] ) ? $GLOBALS['__dbru_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['__dbru_calls']['update_option'][] = array( $key, $autoload );
	$GLOBALS['__dbru_options'][ $key ]          = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['__dbru_options'][ $key ] );
	return true;
}

/* --- Utenti, ruoli, capability --------------------------------------------- */

class WP_User { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	public $ID           = 0;
	public $user_login   = '';
	public $display_name = '';
	public $roles        = array();
	public $allcaps      = array();
	public function exists() {
		return ! empty( $this->ID );
	}
	public function has_cap( $cap ) {
		return ! empty( $this->allcaps[ $cap ] );
	}
}

class WP_Role { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	public $name;
	public $capabilities;
	public function __construct( $name, array $capabilities ) {
		$this->name         = $name;
		$this->capabilities = $capabilities;
	}
	public function has_cap( $cap ) {
		return ! empty( $this->capabilities[ $cap ] );
	}
}

class WP_Error { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}

class WP_Roles { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	public $roles = array();
}

function user_can( $user, $cap ) {
	return $user instanceof WP_User && $user->has_cap( $cap );
}
function current_user_can( $cap ) {
	return user_can( wp_get_current_user(), $cap );
}
function wp_get_current_user() {
	return $GLOBALS['__dbru_user'] instanceof WP_User ? $GLOBALS['__dbru_user'] : new WP_User();
}
function is_user_logged_in() {
	return wp_get_current_user()->exists();
}
function get_role( $slug ) {
	if ( ! isset( $GLOBALS['__dbru_roles'][ $slug ] ) ) {
		return null;
	}
	return new WP_Role( $slug, $GLOBALS['__dbru_roles'][ $slug ]['capabilities'] );
}
function wp_roles() {
	$roles        = new WP_Roles();
	$roles->roles = $GLOBALS['__dbru_roles'];
	return $roles;
}
function translate_user_role( $name ) {
	return $name;
}
function count_users() {
	$avail = array();
	foreach ( $GLOBALS['__dbru_roles'] as $slug => $role ) {
		$avail[ $slug ] = isset( $role['users'] ) ? $role['users'] : 0;
	}
	return array(
		'total_users' => array_sum( $avail ),
		'avail_roles' => $avail,
	);
}

/* --- Admin: meta box, menu, admin bar, schermate --------------------------- */

function remove_meta_box( $id, $screen, $context ) {
	$GLOBALS['__dbru_calls']['remove_meta_box'][] = array( $id, $screen, $context );
}
function wp_add_dashboard_widget( $id, $title, $cb, $control = null, $args = null, $context = 'normal' ) {
	$GLOBALS['__dbru_calls']['wp_add_dashboard_widget'][] = array( $id, $title, $cb, $context );
}
function remove_menu_page( $page ) {
	$GLOBALS['__dbru_calls']['remove_menu_page'][] = $page;
}
function add_management_page( $page_title, $menu_title, $capability, $slug, $cb ) {
	$GLOBALS['__dbru_calls']['add_management_page'][] = array( $page_title, $menu_title, $capability, $slug, $cb );
	return 'tools_page_' . $slug;
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['__dbru_calls']['wp_enqueue_style'][ $handle ] = array( $src, $deps, $ver );
}
function wp_enqueue_media() {
	$GLOBALS['__dbru_calls']['wp_enqueue_media'] = true;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['__dbru_calls']['wp_enqueue_script'][ $handle ] = array( $src, $deps, $ver );
}
function check_admin_referer( $action ) {
	if ( ! isset( $_REQUEST['_wpnonce'] ) || 'nonce-' . $action !== $_REQUEST['_wpnonce'] ) {
		throw new RuntimeException( 'nonce non valido', 403 );
	}
	return 1;
}
function wp_nonce_field( $action ) {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">';
}

/**
 * wp_safe_redirect() seguito da exit: l'eccezione ferma il flusso nel test.
 */
class DBRU_Test_Redirect extends RuntimeException { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}
function wp_safe_redirect( $location ) {
	throw new DBRU_Test_Redirect( $location );
}
function is_admin() {
	return ! empty( $GLOBALS['__dbru_is_admin'] );
}
function wp_die( $message = '', $code = 0 ) {
	throw new RuntimeException( (string) $message, (int) $code );
}

class DBRU_Test_Admin_Bar { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	public $removed = array();
	public function remove_node( $id ) {
		$this->removed[] = $id;
	}
	public $added = array();
	public function add_node( $args ) {
		$this->added[] = $args;
	}
}

class DBRU_Test_Screen { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	public $help_removed = false;
	public function remove_help_tabs() {
		$this->help_removed = true;
	}
}

/* --- Schermata, tema, media ------------------------------------------------- */

function get_current_screen() {
	return isset( $GLOBALS['__dbru_screen'] ) ? (object) array( 'id' => $GLOBALS['__dbru_screen'] ) : null;
}
function get_bloginfo( $show = 'name' ) {
	return 'IIS Cigna & Baruffi';
}
function get_theme_mod( $name, $default = false ) {
	return isset( $GLOBALS['__dbru_theme_mods'][ $name ] ) ? $GLOBALS['__dbru_theme_mods'][ $name ] : $default;
}
function get_post_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['__dbru_attachments'][ $id ]['alt'] ) ? $GLOBALS['__dbru_attachments'][ $id ]['alt'] : '';
}
/**
 * Stub: un'immagine esiste se registrata in $GLOBALS['__dbru_attachments'].
 */
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = '' ) {
	if ( ! isset( $GLOBALS['__dbru_attachments'][ $id ] ) ) {
		return '';
	}
	$attr = array_merge( array( 'src' => $GLOBALS['__dbru_attachments'][ $id ]['src'] ), (array) $attr );
	$html = '<img';
	foreach ( $attr as $name => $value ) {
		if ( false !== $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
	}
	return $html . '>';
}

/* --- URL, plugin, escape ---------------------------------------------------- */

function home_url( $path = '' ) {
	return 'https://scuola.example' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
}
function admin_url( $path = '' ) {
	return home_url( 'wp-admin/' . ltrim( $path, '/' ) );
}
function wp_login_url( $redirect = '' ) {
	return home_url( 'wp-login.php' ) . ( $redirect ? '?redirect_to=' . rawurlencode( $redirect ) : '' );
}
function get_permalink() {
	return home_url( 'area-riservata/' );
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	return 'https://scuola.example/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}
function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
function esc_html( $t ) {
	return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $t ) {
	return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );
	// Come WordPress: protocolli ammessi http/https/mailto, ancore e percorsi relativi.
	if ( '' === $u || preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $u ) && ! preg_match( '#^(https?|mailto):#i', $u ) ) {
		return '';
	}
	return $u;
}
function esc_url( $u ) {
	return htmlspecialchars( esc_url_raw( $u ), ENT_QUOTES, 'UTF-8' );
}
function absint( $n ) {
	return abs( (int) $n );
}
function sanitize_hex_color( $color ) {
	return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ? $color : null;
}
function esc_textarea( $t ) {
	return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function sanitize_key( $key ) {
	return is_scalar( $key ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) : '';
}
function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );
}
function wp_strip_all_tags( $str ) {
	return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $str ) ) );
}
function sanitize_text_field( $str ) {
	if ( ! is_scalar( $str ) ) {
		return '';
	}
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $str ) ) );
}
function wp_kses_post( $html ) {
	// Stub: toglie script/style/iframe e gli attributi on* e javascript:.
	$html = preg_replace( '@<(script|style|iframe)[^>]*?>.*?</\\1>@si', '', (string) $html );
	$html = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
	return preg_replace( '/javascript:/i', '', $html );
}
function wpautop( $text ) {
	return '' === trim( $text ) ? '' : '<p>' . $text . '</p>';
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function add_query_arg( array $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function selected( $a, $b ) {
	if ( (string) $a === (string) $b ) {
		echo ' selected="selected"';
	}
}
function checked( $checked ) {
	if ( $checked ) {
		echo ' checked="checked"';
	}
}
function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options );
}
function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $row ) use ( $field ) {
			return is_object( $row ) ? $row->$field : $row[ $field ];
		},
		$list
	);
}

/* -----------------------------------------------------------------------------
 * Helper per i test.
 * -------------------------------------------------------------------------- */

/**
 * Hook registrati al caricamento dei file del plugin: si conservano fra un
 * test e l'altro, i filtri aggiunti dai test no.
 */
$GLOBALS['__dbru_plugin_filters'] = null;

/**
 * Reset dello stato: utente, ruoli, chiamate registrate e filtri dei test.
 */
function dbru_test_reset() {
	$GLOBALS['__dbru_filters']  = $GLOBALS['__dbru_plugin_filters'];
	$GLOBALS['__dbru_calls']    = array();
	$GLOBALS['__dbru_user']     = null;
	$GLOBALS['__dbru_is_admin'] = false;
	$GLOBALS['__dbru_options']  = array();
	$GLOBALS['menu']            = array();
	$GLOBALS['__dbru_screen']      = null;
	$GLOBALS['__dbru_theme_mods']  = array();
	$GLOBALS['__dbru_attachments'] = array();
	$_GET                       = array();
	$_POST                      = array();
	$_REQUEST                   = array();
	$GLOBALS['__dbru_roles']    = array(
		'administrator' => array(
			'name'         => 'Amministratore',
			'capabilities' => array(
				'read'           => true,
				'manage_options' => true,
			),
			'users'        => 1,
		),
		'editor'        => array(
			'name'         => 'Editore',
			'capabilities' => array(
				'read'       => true,
				'edit_posts' => true,
			),
			'users'        => 2,
		),
		'docente'       => array(
			'name'         => 'Docente',
			'capabilities' => array(
				'read'            => true,
				'db_area_docenti' => true,
			),
			'users'        => 40,
		),
		'subscriber'    => array(
			'name'         => 'Sottoscrittore',
			'capabilities' => array( 'read' => true ),
			'users'        => 300,
		),
	);
	unset( $GLOBALS['wp_meta_boxes'] );
}

/**
 * Crea un utente con i ruoli indicati: le capability sono quelle dei ruoli
 * più quelle extra.
 *
 * @param string[] $roles Ruoli.
 * @param array    $extra Capability aggiuntive (cap => bool).
 * @param int      $id    ID (0 = utente inesistente).
 * @return WP_User
 */
function dbru_test_user( array $roles, array $extra = array(), $id = 7 ) {
	$user               = new WP_User();
	$user->ID           = $id;
	$user->user_login   = 'utente' . $id;
	$user->display_name = 'Utente ' . $id;
	$user->roles        = $roles;
	foreach ( $roles as $role ) {
		if ( isset( $GLOBALS['__dbru_roles'][ $role ] ) ) {
			$user->allcaps = array_merge( $user->allcaps, $GLOBALS['__dbru_roles'][ $role ]['capabilities'] );
		}
	}
	$user->allcaps = array_merge( $user->allcaps, $extra );
	return $user;
}

/**
 * Imposta l'utente corrente.
 *
 * @param WP_User|null $user Utente, null = nessuno loggato.
 */
function dbru_test_login( $user ) {
	$GLOBALS['__dbru_user'] = $user;
}

/**
 * Salva direttamente le impostazioni per ruolo, come da pannello.
 *
 * @param array $roles slug => impostazioni grezze.
 */
function dbru_test_store_roles( array $roles ) {
	foreach ( $roles as $slug => $raw ) {
		db_role_ux_save_role_settings( $slug, $raw );
	}
}

/**
 * Sovrascrive la configurazione tramite il filtro pubblico, come farebbe un
 * mu-plugin.
 *
 * @param array $overrides Chiavi da sostituire.
 */
function dbru_test_config( array $overrides ) {
	add_filter(
		'db_role_ux_config',
		function ( $config ) use ( $overrides ) {
			return array_merge( $config, $overrides );
		}
	);
}

// Carica i sorgenti sotto test (non il file principale: istanzia l'updater).
foreach ( array( 'settings', 'config', 'cleanup', 'login-redirect', 'launchpad', 'role-dashboard', 'admin' ) as $dbru_file ) {
	require_once DB_ROLE_UX_TEST_ROOT . '/inc/' . $dbru_file . '.php';
}
$GLOBALS['__dbru_plugin_filters'] = $GLOBALS['__dbru_filters'];

require_once DB_ROLE_UX_TEST_ROOT . '/vendor/autoload.php';
