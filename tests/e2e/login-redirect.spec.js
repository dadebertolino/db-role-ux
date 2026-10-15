// @ts-check
const { test, expect } = require( '@playwright/test' );
const { USERS, AREA_RISERVATA, login } = require( './helpers' );

/**
 * Redirect post-login, acceso dalla fixture verso /area-riservata/: vale per
 * gli utenti ripuliti, mai per l'amministratore.
 */

test( 'il sottoscrittore atterra sull\'Area riservata', async ( { page } ) => {
	await login( page, USERS.sottoscrittore );
	await expect( page ).toHaveURL( new RegExp( `${ AREA_RISERVATA }$` ) );
} );

test( 'il docente atterra sull\'Area riservata', async ( { page } ) => {
	await login( page, USERS.docente );
	await expect( page ).toHaveURL( new RegExp( `${ AREA_RISERVATA }$` ) );
} );

test( 'l\'amministratore resta nella bacheca', async ( { page } ) => {
	await login( page, USERS.admin );
	await expect( page ).toHaveURL( /\/wp-admin\/?$/ );
} );
