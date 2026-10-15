/**
 * DB Role UX — scheda "Bacheca per ruolo".
 * Mostra in ogni widget solo i campi del tipo scelto. Senza JavaScript
 * restano visibili tutti (il salvataggio usa comunque solo quelli del tipo).
 */
( function () {
	'use strict';

	function sync( fieldset ) {
		var select = fieldset.querySelector( '.db-role-ux-widget__type' );
		if ( ! select ) {
			return;
		}
		fieldset.setAttribute( 'data-type', select.value );
		fieldset.querySelectorAll( '[data-for-type]' ).forEach( function ( field ) {
			field.hidden = field.getAttribute( 'data-for-type' ) !== select.value;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.db-role-ux-widget' ).forEach( function ( fieldset ) {
			sync( fieldset );
			var select = fieldset.querySelector( '.db-role-ux-widget__type' );
			if ( select ) {
				select.addEventListener( 'change', function () {
					sync( fieldset );
				} );
			}
		} );
	} );
}() );
