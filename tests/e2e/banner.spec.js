// @ts-check
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { STATE, ADMIN_PAGE, resetRoleDashboard } = require( './helpers' );

/**
 * Fascia di benvenuto per ruolo: l'amministratore configura docente
 * (immagine dalla Libreria media, scelta dal selettore) e sottoscrittore
 * (logo del sito); ognuno vede la sua fascia, l'amministratore nessuna.
 *
 * Nome del sito e logo di prova arrivano da bin/setup-e2e.sh.
 */

const SITE = 'IIS Cigna-Baruffi-Garelli';
const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];
const editor = ( role ) => `${ ADMIN_PAGE }&tab=bacheca&role=${ role }`;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( async ( { request } ) => {
	await resetRoleDashboard( request );
} );

test.afterAll( async ( { request } ) => {
	await resetRoleDashboard( request );
} );

/**
 * @param {import('@playwright/test').Page} page
 */
async function save( page ) {
	await page.getByRole( 'button', { name: /^Salva / } ).click();
	await expect( page.locator( '.db-role-ux-wrap' ).getByRole( 'status' ) ).toContainText( 'Impostazioni del ruolo salvate' );
}

test.describe( 'amministratore', () => {
	test.use( { storageState: STATE.admin } );

	test( 'configura la fascia del docente con un\'immagine della Libreria media', async ( { page } ) => {
		await page.goto( editor( 'docente' ) );
		await page.getByLabel( 'Mostra una fascia di benvenuto' ).check();

		await page.getByRole( 'button', { name: 'Scegli dalla Libreria media' } ).click();
		const modal = page.locator( '.media-modal:visible' );
		await expect( modal ).toBeVisible();
		await modal.locator( 'li.attachment' ).first().click();
		await modal.getByRole( 'button', { name: 'Usa questa immagine' } ).click();
		await expect( page.locator( '#db-role-ux-banner-logo-id' ) ).not.toHaveValue( '' );
		await expect( page.locator( 'input[name="banner[logo]"][value="media"]' ) ).toBeChecked();
		await expect( page.locator( '.db-role-ux-media-thumb img' ) ).toBeVisible();

		await page.getByLabel( 'Titolo', { exact: true } ).first().fill( 'Buongiorno {nome}' );
		await page.getByLabel( 'Testo (HTML di base ammesso)' ).fill( 'Sei nell\'area <strong>{ruolo}</strong> di {sito}.' );
		// Il color picker di WordPress mostra il campo dopo il clic sul pulsante.
		await page.locator( '.wp-color-result' ).click();
		await page.locator( '#db-role-ux-banner-color' ).fill( '#0066cc' );
		await save( page );

		const preview = page.locator( '.db-role-ux-preview .db-role-ux-banner' );
		await expect( preview ).toContainText( 'Buongiorno Nome Cognome' );
		await expect( preview ).toContainText( `Sei nell'area Docente di ${ SITE }.` );
	} );

	test( 'configura la fascia del sottoscrittore con il logo del sito', async ( { page } ) => {
		await page.goto( editor( 'subscriber' ) );
		await page.getByLabel( 'Mostra una fascia di benvenuto' ).check();
		await page.locator( 'input[name="banner[logo]"][value="site"]' ).check();
		await page.getByLabel( 'Titolo', { exact: true } ).first().fill( 'Ciao {nome}' );
		await save( page );
		await expect( page.locator( '.db-role-ux-preview img' ) ).toHaveAttribute( 'alt', 'Logo E2E' );
	} );

	test( 'la sua bacheca resta senza fascia', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '.db-role-ux-banner' ) ).toHaveCount( 0 );
	} );

	test( 'accessibilità del modulo', async ( { page } ) => {
		await page.goto( editor( 'docente' ) );
		const result = await new AxeBuilder( { page } ).withTags( TAGS ).include( '.db-role-ux-wrap' ).analyze();
		expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) ).toEqual( [] );
	} );
} );

test.describe( 'docente', () => {
	test.use( { storageState: STATE.docente } );

	test( 'vede la sua fascia sopra il titolo della bacheca', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const banner = page.getByRole( 'region', { name: 'Benvenuto' } );
		await expect( banner ).toBeVisible();
		await expect( banner ).toContainText( 'Buongiorno Prof. Rossi' );
		await expect( banner.locator( 'strong' ) ).toHaveText( 'Docente' );
		await expect( banner ).toContainText( SITE );
		await expect( banner.locator( 'img' ) ).toHaveAttribute( 'alt', 'Logo E2E' );
		await expect( banner ).toHaveCSS( 'border-left-color', 'rgb(0, 102, 204)' );

		const bannerBox = await banner.boundingBox();
		const titleBox = await page.locator( '.wrap > h1' ).first().boundingBox();
		expect( bannerBox && titleBox && bannerBox.y < titleBox.y ).toBe( true );
	} );

	test( 'non la vede fuori dalla bacheca', async ( { page } ) => {
		await page.goto( '/wp-admin/profile.php' );
		await expect( page.locator( '.db-role-ux-banner' ) ).toHaveCount( 0 );
	} );

	test( 'accessibilità della fascia', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const result = await new AxeBuilder( { page } ).withTags( TAGS ).include( '.db-role-ux-banner' ).analyze();
		expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) ).toEqual( [] );
	} );
} );

test.describe( 'sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'vede la sua fascia con il logo del sito', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const banner = page.getByRole( 'region', { name: 'Benvenuto' } );
		await expect( banner ).toContainText( 'Ciao Mario Bianchi' );
		await expect( banner ).not.toContainText( 'Buongiorno' );
		await expect( banner.locator( 'img' ) ).toHaveAttribute( 'alt', 'Logo E2E' );
	} );
} );
