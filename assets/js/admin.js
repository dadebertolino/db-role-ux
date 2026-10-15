/**
 * DB Role UX — scheda "Bacheca per ruolo".
 *
 *  - Widget: mostra solo i campi del tipo scelto. Senza JavaScript restano
 *    visibili tutti (il salvataggio usa comunque solo quelli del tipo).
 *  - Fascia di benvenuto: scelta del logo dalla Libreria media (riempie il
 *    campo "ID dell'immagine", che resta usabile anche a mano) e color
 *    picker di WordPress sul colore d'accento.
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

	function initMedia() {
		var button = document.querySelector( '.db-role-ux-media-pick' );
		var input = document.getElementById( 'db-role-ux-banner-logo-id' );
		var thumb = document.querySelector( '.db-role-ux-media-thumb' );
		if ( ! button || ! input || ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame;
		button.hidden = false;
		button.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'Logo della fascia di benvenuto',
					button: { text: 'Usa questa immagine' },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var size = image.sizes && ( image.sizes.thumbnail || image.sizes.full );
					input.value = image.id;
					var media = document.querySelector( 'input[name="banner[logo]"][value="media"]' );
					if ( media ) {
						media.checked = true;
					}
					if ( thumb ) {
						thumb.textContent = '';
						if ( size ) {
							var img = document.createElement( 'img' );
							img.src = size.url;
							img.alt = '';
							thumb.appendChild( img );
						}
					}
					button.focus();
				} );
			}
			frame.open();
			// Si sceglie un'immagine esistente: si parte dalla Libreria, non
			// dal caricamento (che WordPress mostra se la libreria non è
			// ancora arrivata al momento dell'apertura).
			frame.content.mode( 'browse' );
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

		initMedia();

		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.db-role-ux-color' ).wpColorPicker();
		}
	} );
}() );
