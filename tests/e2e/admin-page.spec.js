// @ts-check
const { test, expect } = require( '@playwright/test' );
const { STATE, ADMIN_PAGE } = require( './helpers' );

/**
 * Pagina di riepilogo Strumenti → DB Role UX.
 */

test.describe( 'amministratore', () => {
	test.use( { storageState: STATE.admin } );

	test( 'mostra la policy attiva con il design system', async ( { page } ) => {
		await page.goto( ADMIN_PAGE );
		const wrap = page.locator( '.db-role-ux-wrap' );
		await expect( wrap.getByRole( 'heading', { level: 1, name: 'DB Role UX' } ) ).toBeVisible();
		await expect( page.locator( 'link#db-admin-ui-css' ) ).toHaveCount( 1 );
		await expect( page.locator( 'link#db-role-ux-admin-css' ) ).toHaveCount( 1 );

		const row = ( slug ) => wrap.locator( 'tr', { has: page.locator( 'code', { hasText: new RegExp( `^${ slug }$` ) } ) } );
		await expect( row( 'administrator' ) ).toContainText( 'Completo (amministratore)' );
		await expect( row( 'docente' ) ).toContainText( 'Semplificato' );
		await expect( row( 'subscriber' ) ).toContainText( 'Semplificato' );

		await expect( wrap ).toContainText( 'Gli utenti ripuliti arrivano su' );
		await expect( wrap.locator( 'tr', { hasText: 'db_area_docenti' } ) ).toContainText( 'Docente' );
	} );

	test( 'link Riepilogo nella lista dei plugin', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		await page.locator( 'tr[data-slug="db-role-ux"]' ).getByRole( 'link', { name: 'Riepilogo' } ).click();
		await expect( page ).toHaveURL( /tools\.php\?page=db-role-ux/ );
	} );
} );

test.describe( 'sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'non può aprire la pagina', async ( { page } ) => {
		const response = await page.goto( ADMIN_PAGE );
		expect( response?.status() ).toBe( 403 );
		await expect( page.locator( '.db-role-ux-wrap' ) ).toHaveCount( 0 );
	} );
} );
