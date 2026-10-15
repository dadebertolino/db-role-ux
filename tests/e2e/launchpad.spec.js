// @ts-check
const { test, expect } = require( '@playwright/test' );
const { STATE, AREA_RISERVATA } = require( './helpers' );

/**
 * Launchpad [db_area_riservata]: protezione per gli ospiti e card per
 * capability.
 */

test( 'l\'ospite viene mandato al login, con ritorno alla pagina', async ( { page } ) => {
	await page.goto( AREA_RISERVATA );
	await page.waitForURL( /\/wp-login\.php\?redirect_to=/ );
	expect( decodeURIComponent( page.url() ) ).toContain( AREA_RISERVATA );
} );

test.describe( 'docente', () => {
	test.use( { storageState: STATE.docente } );

	test( 'vede le card dell\'Area Docenti e nient\'altro', async ( { page } ) => {
		await page.goto( AREA_RISERVATA );
		const launchpad = page.locator( '.db-launchpad' );
		await expect( launchpad ).toContainText( 'Ciao Prof. Rossi' );
		await expect( launchpad.getByRole( 'heading', { name: 'Area Docenti' } ) ).toBeVisible();
		await expect( launchpad.getByRole( 'link', { name: /Programmazioni/ } ) ).toHaveAttribute( 'href', /#programmazioni$/ );
		await expect( launchpad.getByRole( 'heading', { name: 'Area Alunni' } ) ).toHaveCount( 0 );
		await expect( launchpad.getByRole( 'heading', { name: 'Segreteria' } ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'nessuna card: messaggio neutro', async ( { page } ) => {
		await page.goto( AREA_RISERVATA );
		await expect( page.locator( '.db-launchpad' ).getByRole( 'alert' ) ).toContainText( 'non ci sono funzioni disponibili' );
	} );
} );
