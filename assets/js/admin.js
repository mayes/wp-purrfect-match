/**
 * Purrfect Match — settings-screen interactions.
 */
( function () {
	'use strict';

	var COPY = window.PM_ADMIN || {};

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

		var copyBtn = root.querySelector( '.pm-copy' );
		var copyTimer = null;
		if ( copyBtn ) {
			var originalCopyLabel = copyBtn.textContent;
			var reportCopy = function ( succeeded ) {
				window.clearTimeout( copyTimer );
				copyBtn.textContent = succeeded ? ( COPY.copied || 'Copied!' ) : ( COPY.copyFailed || 'Copy failed' );
				copyBtn.classList.toggle( 'pm-copied', succeeded );
				copyBtn.classList.toggle( 'pm-copy-failed', ! succeeded );
				copyTimer = window.setTimeout( function () {
					copyBtn.textContent = originalCopyLabel;
					copyBtn.classList.remove( 'pm-copied', 'pm-copy-failed' );
				}, 1800 );
			};

			copyBtn.addEventListener( 'click', function () {
				var value = copyBtn.getAttribute( 'data-clipboard' ) || '';
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( value ).then(
						function () { reportCopy( true ); },
						function () { reportCopy( false ); }
					);
					return;
				}

				var textarea = document.createElement( 'textarea' );
				textarea.value = value;
				textarea.setAttribute( 'readonly', '' );
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.select();
				var copied = false;
				try {
					copied = document.execCommand( 'copy' );
				} catch ( error ) {
					copied = false;
				}
				document.body.removeChild( textarea );
				reportCopy( copied );
			} );
		}

		var brandField = document.getElementById( 'pm_brand' );
		var brandPicker = document.querySelector( '[data-color-target="pm_brand"]' );
		var contrastFor = function ( color ) {
			var r = parseInt( color.slice( 1, 3 ), 16 );
			var g = parseInt( color.slice( 3, 5 ), 16 );
			var b = parseInt( color.slice( 5, 7 ), 16 );
			var linearize = function ( channel ) {
				var value = channel / 255;
				return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
			};
			var luminance = ( 0.2126 * linearize( r ) ) + ( 0.7152 * linearize( g ) ) + ( 0.0722 * linearize( b ) );
			var blackContrast = ( luminance + 0.05 ) / 0.05;
			var whiteContrast = 1.05 / ( luminance + 0.05 );
			return blackContrast >= whiteContrast ? '#000000' : '#ffffff';
		};
		var applyBrand = function ( value ) {
			var color = String( value || '' ).trim();
			if ( /^#[0-9a-fA-F]{6}$/.test( color ) ) {
				root.style.setProperty( '--pm-admin-brand', color );
				root.style.setProperty( '--pm-admin-on-brand', contrastFor( color ) );
				if ( brandPicker && brandPicker.value.toLowerCase() !== color.toLowerCase() ) {
					brandPicker.value = color;
				}
			}
		};

		if ( brandField ) {
			brandField.addEventListener( 'input', function () { applyBrand( brandField.value ); } );
			brandField.addEventListener( 'change', function () { applyBrand( brandField.value ); } );
		}
		if ( brandPicker && brandField ) {
			brandPicker.addEventListener( 'input', function () {
				brandField.value = brandPicker.value;
				applyBrand( brandPicker.value );
			} );
		}

		var bindPreview = function ( fieldId, selector, fallback ) {
			var field = document.getElementById( fieldId );
			var target = root.querySelector( selector );
			if ( ! field || ! target ) {
				return;
			}
			var update = function () {
				target.textContent = String( field.value || '' ).trim() || fallback;
			};
			field.addEventListener( 'input', update );
			field.addEventListener( 'change', update );
		};

		bindPreview( 'pm_org_name', '[data-pm-preview-org]', 'Your rescue' );
		bindPreview( 'pm_title', '[data-pm-preview-title]', 'Find your purr-fect match' );
	} );
}() );
