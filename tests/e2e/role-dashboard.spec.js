// @ts-check
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { STATE, ADMIN_PAGE, resetRoleDashboard } = require( './helpers' );

/**
 * Bacheca per ruolo, dal pannello alla bacheca: l'amministratore configura
 * il ruolo docente, il docente vede il risultato, sottoscrittore e
 * amministratore no.
 */

const EDITOR = `${ ADMIN_PAGE }&tab=bacheca&role=docente`;
const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];

test.describe.configure( { mode: 'serial' } );

/**
 * Compila il modulo "Nuovo widget" (l'ultimo) e salva.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{title: string, type: string, links?: string, content?: string, group?: string, context?: string}} widget
 */
async function addWidget( page, widget ) {
	const form = page.locator( 'fieldset.db-role-ux-widget' ).last();
	await expect( form.locator( 'legend' ).first() ).toHaveText( 'Nuovo widget' );
	await form.getByLabel( /^Titolo/ ).fill( widget.title );
	await form.getByLabel( 'Tipo', { exact: true } ).selectOption( widget.type );
	if ( widget.context ) {
		await form.getByLabel( 'Colonna', { exact: true } ).selectOption( widget.context );
	}
	if ( widget.links ) {
		await form.getByLabel( /^Link \(tipo/ ).fill( widget.links );
	}
	if ( widget.content ) {
		await form.getByLabel( /^Testo \(tipo/ ).fill( widget.content );
	}
	if ( widget.group ) {
		await form.locator( `input[type="checkbox"][value="${ widget.group }"]` ).check();
	}
	await page.getByRole( 'button', { name: /^Salva / } ).click();
	await expect( page.locator( '.db-role-ux-wrap' ).getByRole( 'status' ) ).toContainText( 'Impostazioni del ruolo salvate' );
}

test.beforeAll( async ( { request } ) => {
	await resetRoleDashboard( request );
} );

test.afterAll( async ( { request } ) => {
	await resetRoleDashboard( request );
} );

test.describe( 'amministratore configura il docente', () => {
	test.use( { storageState: STATE.admin } );

	test( 'i widget vengono rilevati aprendo la bacheca', async ( { page } ) => {
		await page.goto( EDITOR );
		await expect( page.locator( '.db-role-ux-editor' ) ).toContainText( 'Nessun widget rilevato finora' );

		await page.goto( '/wp-admin/' );
		await page.goto( EDITOR );
		await expect( page.getByLabel( /Widget non dichiarato/ ) ).toBeVisible();
		await expect( page.getByLabel( /Circolari non firmate/ ) ).toBeVisible();
	} );

	test( 'solo i campi del tipo scelto', async ( { page } ) => {
		await page.goto( EDITOR );
		const form = page.locator( 'fieldset.db-role-ux-widget' ).last();
		await expect( form.getByLabel( /^Link \(tipo/ ) ).toBeVisible();
		await expect( form.getByLabel( /^Testo \(tipo/ ) ).toBeHidden();
		await form.getByLabel( 'Tipo', { exact: true } ).selectOption( 'text' );
		await expect( form.getByLabel( /^Link \(tipo/ ) ).toBeHidden();
		await expect( form.getByLabel( /^Testo \(tipo/ ) ).toBeVisible();
	} );

	test( 'nasconde un widget, aggiunge widget e link', async ( { page } ) => {
		await page.goto( EDITOR );
		await page.getByLabel( /Widget non dichiarato/ ).check();
		await page.getByLabel( 'Menu laterale, sotto Bacheca' ).fill( 'Area riservata | /area-riservata/ | dashicons-welcome-learn-more' );
		await page.getByLabel( 'Admin bar (backend e sito)' ).fill( 'Registro elettronico | https://registro.example.test/' );
		await addWidget( page, {
			title: 'Link utili',
			type: 'links',
			context: 'side',
			links: 'Registro elettronico | https://registro.example.test/\nArea riservata | /area-riservata/',
		} );
		await expect( page.getByLabel( /Widget non dichiarato/ ) ).toBeChecked();

		await addWidget( page, { title: 'Avviso', type: 'text', content: '<strong>Collegio docenti</strong> lunedì alle 15.' } );
		await addWidget( page, { title: 'Le mie funzioni', type: 'cards', group: 'db_area_docenti' } );

		await expect( page.locator( 'fieldset.db-role-ux-widget' ) ).toHaveCount( 4 );
	} );

	test( 'il riepilogo mostra la personalizzazione', async ( { page } ) => {
		await page.goto( ADMIN_PAGE );
		const row = page.locator( 'tr', { has: page.locator( 'code', { hasText: /^docente$/ } ) } );
		await expect( row ).toContainText( '3 widget aggiunti, 1 widget nascosto, 1 voce di menu, 1 voce in admin bar' );
	} );

	test( 'la sua bacheca non cambia', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '#e2e_widget_libero' ) ).toBeVisible();
		await expect( page.locator( '.postbox[id^="dbru_"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.db-role-ux-menu-link' ) ).toHaveCount( 0 );
	} );

	test( 'accessibilità della scheda', async ( { page } ) => {
		await page.goto( EDITOR );
		const result = await new AxeBuilder( { page } ).withTags( TAGS ).include( '.db-role-ux-wrap' ).analyze();
		expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) ).toEqual( [] );
	} );
} );

test.describe( 'docente', () => {
	test.use( { storageState: STATE.docente } );

	test( 'vede la bacheca del suo ruolo', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '#e2e_widget_libero' ) ).toHaveCount( 0 );

		const links = page.locator( '#side-sortables .postbox', { hasText: 'Link utili' } );
		await expect( links ).toBeVisible();
		await expect( links.getByRole( 'link', { name: 'Registro elettronico' } ) ).toHaveAttribute( 'href', 'https://registro.example.test/' );
		await expect( links.getByRole( 'link', { name: 'Area riservata' } ) ).toHaveAttribute( 'href', /\/area-riservata\/$/ );

		const avviso = page.locator( '.postbox', { hasText: 'Avviso' } );
		await expect( avviso.locator( 'strong' ) ).toHaveText( 'Collegio docenti' );

		const cards = page.locator( '.postbox', { hasText: 'Le mie funzioni' } );
		await expect( cards.getByRole( 'heading', { name: 'Area Docenti' } ) ).toBeVisible();
		await expect( cards.getByRole( 'link', { name: /Programmazioni/ } ) ).toBeVisible();
		await expect( page.locator( 'link#db-role-ux-dashboard-css' ) ).toHaveCount( 1 );
	} );

	test( 'voce nel menu laterale e nella admin bar', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const voce = page.locator( '#adminmenu li.db-role-ux-menu-link a' );
		await expect( voce ).toHaveText( /Area riservata/ );
		await voce.click();
		await expect( page ).toHaveURL( /\/area-riservata\/$/ );

		await expect( page.locator( '#wp-admin-bar-db-role-ux-link-0 a' ) ).toHaveAttribute( 'href', 'https://registro.example.test/' );
	} );

	test( 'accessibilità dei widget aggiunti', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const result = await new AxeBuilder( { page } ).withTags( TAGS ).include( '.postbox[id^="dbru_"]' ).analyze();
		expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) ).toEqual( [] );
	} );
} );

test.describe( 'sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'non riceve le impostazioni del docente', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '#e2e_widget_libero' ) ).toBeVisible();
		await expect( page.locator( '.postbox[id^="dbru_"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wp-admin-bar-db-role-ux-link-0' ) ).toHaveCount( 0 );
	} );
} );
