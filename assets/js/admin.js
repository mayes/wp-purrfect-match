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
		// Mirrors Purrfect_Match_Settings::brand_tokens(): best WCAG ratio among
		// the primary candidates, then the fallback pair only if still < 4.5.
		var ON_BRAND = COPY.onBrand || { primary: [ '#1b1714', '#fffdf9' ], fallback: [ '#050403', '#fffefa' ] };
		var luminanceOf = function ( color ) {
			var weights = [ 0.2126, 0.7152, 0.0722 ];
			var sum = 0;
			for ( var i = 0; i < 3; i++ ) {
				var value = parseInt( color.slice( 1 + ( i * 2 ), 3 + ( i * 2 ) ), 16 ) / 255;
				sum += weights[ i ] * ( value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 ) );
			}
			return sum;
		};
		var contrastFor = function ( color ) {
			var brand = luminanceOf( color );
			var best = ON_BRAND.primary[ 0 ];
			var bestRatio = 0;
			[ 'primary', 'fallback' ].forEach( function ( tier ) {
				if ( tier === 'fallback' && bestRatio >= 4.5 ) {
					return;
				}
				ON_BRAND[ tier ].forEach( function ( candidate ) {
					var other = luminanceOf( candidate );
					var ratio = ( Math.max( brand, other ) + 0.05 ) / ( Math.min( brand, other ) + 0.05 );
					if ( ratio > bestRatio ) {
						best = candidate;
						bestRatio = ratio;
					}
				} );
			} );
			return best;
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

		// fallback === '' hides the element (an empty heading renders nothing).
		var bindPreview = function ( fieldId, selector, fallback ) {
			var field = document.getElementById( fieldId );
			var target = root.querySelector( selector );
			if ( ! field || ! target ) {
				return;
			}
			var update = function () {
				var text = String( field.value || '' ).trim() || fallback;
				target.textContent = text;
				target.hidden = ! text;
			};
			field.addEventListener( 'input', update );
			field.addEventListener( 'change', update );
		};

		bindPreview( 'pm_org_name', '[data-pm-preview-org]', COPY.previewOrg || 'Your rescue' );
		bindPreview( 'pm_title', '[data-pm-preview-title]', '' );
	} );
}() );
