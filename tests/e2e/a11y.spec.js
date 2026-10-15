// @ts-check
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { STATE, ADMIN_PAGE, AREA_RISERVATA } = require( './helpers' );

/**
 * Accessibilità WCAG 2.1 AA con axe-core: solo l'output del plugin (pagina
 * di riepilogo e launchpad), non la bacheca né il tema.
 */

const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} include
 */
async function violations( page, include ) {
	const result = await new AxeBuilder( { page } ).withTags( TAGS ).include( include ).analyze();
	return result.violations.map( ( v ) => `${ v.id }: ${ v.help } → ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` );
}

test.describe( 'pagina di riepilogo', () => {
	test.use( { storageState: STATE.admin } );

	test( 'Strumenti → DB Role UX', async ( { page } ) => {
		await page.goto( ADMIN_PAGE );
		expect( await violations( page, '.db-role-ux-wrap' ) ).toEqual( [] );
	} );
} );

test.describe( 'launchpad docente', () => {
	test.use( { storageState: STATE.docente } );

	test( 'card', async ( { page } ) => {
		await page.goto( AREA_RISERVATA );
		expect( await violations( page, '.db-launchpad' ) ).toEqual( [] );
	} );
} );

test.describe( 'launchpad sottoscrittore', () => {
	test.use( { storageState: STATE.sottoscrittore } );

	test( 'messaggio senza card', async ( { page } ) => {
		await page.goto( AREA_RISERVATA );
		expect( await violations( page, '.db-launchpad' ) ).toEqual( [] );
	} );
} );
