// @ts-check
const { test, expect } = require( '@playwright/test' );
const { STATE } = require( './helpers' );

/**
 * Pulizia del backend: il sottoscrittore vede la bacheca semplificata,
 * l'amministratore vede tutto (principio fail-safe).
 */

const CIRCOLARI = [ '0', '1', '2', '3' ];
const NODI_RIMOSSI = [ 'wp-logo', 'new-content', 'comments', 'design-scuole', 'manuale' ];

test.describe( 'sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'bacheca: via i widget dichiarati, resta il resto', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		for ( const id of CIRCOLARI ) {
			await expect( page.locator( `.postbox[id="${ id }"]` ) ).toHaveCount( 0 );
		}
		await expect( page.locator( '#dbru_e2e_keep' ) ).toBeVisible();
	} );

	test( 'bacheca: una colonna, niente Aiuto né Impostazioni schermata', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '#dashboard-widgets' ) ).toHaveClass( /\bcolumns-1\b/ );
		await expect( page.locator( '#contextual-help-link-wrap' ) ).toHaveCount( 0 );
		await expect( page.locator( '#screen-options-link-wrap' ) ).toHaveCount( 0 );
	} );

	test( 'admin bar: via le voci dichiarate, resta l\'account', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		for ( const id of NODI_RIMOSSI ) {
			await expect( page.locator( `#wp-admin-bar-${ id }` ) ).toHaveCount( 0 );
		}
		await expect( page.locator( '#wp-admin-bar-design-scuole-conf' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wp-admin-bar-my-account' ) ).toBeVisible();
		await expect( page.locator( '#wp-admin-bar-dbru-e2e-keep' ) ).toBeVisible();
	} );

	test( 'admin bar ripulita anche sul frontend', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
		await expect( page.locator( '#wp-admin-bar-wp-logo' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wp-admin-bar-new-content' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'amministratore', () => {
	test.use( { storageState: STATE.admin } );

	test( 'vede tutto: widget, admin bar e strumenti della schermata', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		for ( const id of CIRCOLARI ) {
			await expect( page.locator( `.postbox[id="${ id }"]` ) ).toHaveCount( 1 );
		}
		for ( const id of [ 'wp-logo', 'new-content', 'design-scuole', 'manuale' ] ) {
			await expect( page.locator( `#wp-admin-bar-${ id }` ) ).toHaveCount( 1 );
		}
		await expect( page.locator( '#screen-options-link-wrap' ) ).toBeVisible();
		await expect( page.locator( '#contextual-help-link-wrap' ) ).toBeVisible();
	} );
} );
