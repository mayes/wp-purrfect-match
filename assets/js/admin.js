/**
 * Purrfect Match — admin settings screen behaviors.
 *  - Copy the shortcode to the clipboard.
 *  - Live-recolor the admin chrome as the brand color changes.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var root = document.querySelector( '.pm-admin' );
		if ( ! root ) {
			return;
		}

		// Copy shortcode.
		var copyBtn = root.querySelector( '.pm-copy' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var text = copyBtn.getAttribute( 'data-clipboard' ) || '';
				var done = function () {
					var original = copyBtn.textContent;
					copyBtn.textContent = 'Copied!';
					copyBtn.classList.add( 'pm-copied' );
					setTimeout( function () {
						copyBtn.textContent = original;
						copyBtn.classList.remove( 'pm-copied' );
					}, 1600 );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( done, done );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = text;
					document.body.appendChild( ta );
					ta.select();
					try { document.execCommand( 'copy' ); } catch ( e ) {}
					document.body.removeChild( ta );
					done();
				}
			} );
		}

		// Live brand recolor: keep the admin chrome in sync with the brand field.
		var brandField = document.getElementById( 'pm_brand' );
		if ( brandField ) {
			var apply = function () {
				var v = String( brandField.value || '' ).trim();
				if ( /^#[0-9a-fA-F]{6}$/.test( v ) ) {
					root.style.setProperty( '--pm-admin-brand', v );
				}
			};
			brandField.addEventListener( 'input', apply );
			brandField.addEventListener( 'change', apply );
		}
	} );
}() );
