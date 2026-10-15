// @ts-check
/**
 * Helper condivisi per gli E2E di DB Role UX.
 */
const path = require( 'path' );

/**
 * Sessioni salvate da auth.setup.js, da usare negli spec con
 * test.use( { storageState: STATE.admin } ).
 */
const STATE = {
	admin: path.join( __dirname, '.auth', 'admin.json' ),
	docente: path.join( __dirname, '.auth', 'docente.json' ),
	sottoscrittore: path.join( __dirname, '.auth', 'sottoscrittore.json' ),
};

/**
 * Utenti creati da bin/setup-e2e.sh (admin: credenziali di default di wp-env).
 */
const USERS = {
	admin: { login: process.env.WP_ADMIN_USER || 'admin', pass: process.env.WP_ADMIN_PASS || 'password' },
	docente: { login: 'docente', pass: 'password' },
	sottoscrittore: { login: 'sottoscrittore', pass: 'password' },
};

const ADMIN_PAGE = '/wp-admin/tools.php?page=db-role-ux';
const AREA_RISERVATA = '/area-riservata/';

/**
 * Login dal form di WordPress; attende di lasciare wp-login.php.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{login: string, pass: string}} user
 */
async function login( page, user ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( user.login );
	await page.locator( '#user_pass' ).fill( user.pass );
	await Promise.all( [
		page.waitForURL( ( url ) => ! url.pathname.endsWith( '/wp-login.php' ) ),
		page.locator( '#wp-submit' ).click(),
	] );
}

module.exports = {
	STATE,
	USERS,
	ADMIN_PAGE,
	AREA_RISERVATA,
	login,
};
