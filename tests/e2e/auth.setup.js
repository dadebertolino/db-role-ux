// @ts-check
const fs = require( 'fs' );
const path = require( 'path' );
const { test: setup, expect } = require( '@playwright/test' );
const { STATE, USERS, login } = require( './helpers' );

/**
 * Progetto "setup": login di admin, docente e sottoscrittore e salvataggio
 * delle sessioni, riusate dagli spec senza ripetere il login.
 */
for ( const name of Object.keys( STATE ) ) {
	setup( `login ${ name }`, async ( { browser } ) => {
		const context = await browser.newContext();
		const page = await context.newPage();
		await login( page, USERS[ name ] );

		const cookies = await context.cookies();
		expect( cookies.some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ) ).toBe( true );

		fs.mkdirSync( path.dirname( STATE[ name ] ), { recursive: true } );
		await context.storageState( { path: STATE[ name ] } );
		await context.close();
	} );
}
