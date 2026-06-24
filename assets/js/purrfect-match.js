/**
 * Purrfect Match — front-end runtime.
 *
 * Loads adoptable pets directly from Petfinder's GraphQL endpoint in the
 * visitor's browser (no API key, no server round-trip), then powers the
 * filter / chip / shuffle / clear UI ported from the CJ Paws mockup.
 *
 * The data layer mirrors Petfinder's own pet-scroller widget:
 *   - resolve organization display IDs (e.g. "FL1629") to UUIDs, then
 *   - run the SearchAnimal query for that organization.
 * Breed / size / age come back on every animal, so filtering happens
 * instantly in the browser against the loaded set.
 */
(function () {
	'use strict';

	/* ----------------------------------------------------------------- */
	/* GraphQL queries (verbatim from the public pet-scroller widget).   */
	/* ----------------------------------------------------------------- */

	var ORG_QUERY =
		'query GetOrganization($organizationId: String!, $idType: String!) {' +
		'  organization(id: $organizationId, idType: $idType) {' +
		'    organizationName displayId organizationId' +
		'  }' +
		'}';

	var SEARCH_QUERY =
		'query SearchAnimal($pagination: PaginationInfoInput!, $filters: AnimalSearchFiltersInput!) {' +
		'  searchAnimal(pagination: $pagination, sort: { field: "animal_type", order: "desc" }, filters: $filters) {' +
		'    totalCount' +
		'    animals {' +
		'      animalId' +
		'      animalName' +
		'      primaryPhotoId' +
		'      physical { size { label } breed { primary mixed } age { label value } }' +
		'      _contact { address { city state } }' +
		'      publicUrl { url }' +
		'    }' +
		'  }' +
		'}';

	var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

	// Order dropdown values sensibly instead of plain alphabetical.
	var VALUE_ORDER = {
		size: [ 'Small', 'Medium', 'Large', 'Extra Large', 'X-Large' ],
		age: [ 'Baby', 'Kitten', 'Puppy', 'Young', 'Adult', 'Senior', 'Mature' ]
	};

	var FILTER_ORDER = [ 'breed', 'size', 'age' ];
	var FILTER_LABEL = { breed: 'Breed', size: 'Size', age: 'Age' };

	// Module-level cache so multiple widgets / repeat loads share org lookups.
	var orgCache = {};

	/* ----------------------------------------------------------------- */
	/* Small helpers.                                                    */
	/* ----------------------------------------------------------------- */

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function each( list, fn ) {
		Array.prototype.forEach.call( list, fn );
	}

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// Petfinder may return names containing HTML entities; decode for display.
	var decoderEl = null;
	function decodeEntities( s ) {
		if ( ! s ) {
			return '';
		}
		if ( s.indexOf( '&' ) === -1 ) {
			return s;
		}
		if ( ! decoderEl ) {
			decoderEl = document.createElement( 'textarea' );
		}
		decoderEl.innerHTML = s;
		return decoderEl.value;
	}

	function isUUID( id ) {
		return typeof id === 'string' && UUID_RE.test( id );
	}

	/* ----------------------------------------------------------------- */
	/* Data layer.                                                       */
	/* ----------------------------------------------------------------- */

	function gql( apiBase, query, variables ) {
		return fetch( apiBase, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { query: query, variables: variables } )
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'HTTP error ' + res.status );
			}
			return res.json();
		} ).then( function ( json ) {
			if ( json && json.errors && json.errors.length ) {
				throw new Error( json.errors[ 0 ].message || 'GraphQL error' );
			}
			return json ? json.data : null;
		} );
	}

	// Resolve a single display ID (or pass through a UUID) to an org UUID.
	function resolveOrg( apiBase, displayId ) {
		if ( isUUID( displayId ) ) {
			return Promise.resolve( displayId );
		}
		var key = apiBase + '|' + displayId;
		if ( orgCache[ key ] ) {
			return orgCache[ key ];
		}
		var p = gql( apiBase, ORG_QUERY, { organizationId: displayId, idType: 'display_id' } )
			.then( function ( data ) {
				return ( data && data.organization && data.organization.organizationId ) || null;
			} )
			.catch( function () {
				return null;
			} );
		orgCache[ key ] = p;
		return p;
	}

	function resolveOrgs( apiBase, ids ) {
		return Promise.all(
			ids.map( function ( id ) {
				return resolveOrg( apiBase, id );
			} )
		).then( function ( list ) {
			return list.filter( function ( x ) {
				return !! x;
			} );
		} );
	}

	function searchAnimals( cfg, orgUuids ) {
		var filters = {
			animal_type: [ cfg.type ],
			adoption_status: cfg.status
		};
		if ( orgUuids && orgUuids.length ) {
			filters.organization_id = orgUuids;
		}
		var variables = {
			isConsumer: true,
			filters: filters,
			pagination: { fromPage: 0, pageSize: cfg.limit }
		};
		return gql( cfg.apiBase, SEARCH_QUERY, variables ).then( function ( data ) {
			return ( data && data.searchAnimal && data.searchAnimal.animals ) || [];
		} );
	}

	// Map a raw GraphQL animal onto the flat shape the UI renders.
	function normalize( a, cfg ) {
		var phys = a.physical || {};
		var breedObj = phys.breed || {};
		var breed = breedObj.primary || '';
		if ( breed && breedObj.mixed === true ) {
			breed += ' Mix';
		}
		var addr = ( a._contact && a._contact.address ) || {};
		var base = cfg.petfinderUrl || '';
		if ( base && base.charAt( base.length - 1 ) !== '/' ) {
			base += '/';
		}
		// base ends with "/"; drop any leading "/" on the path to avoid "//".
		var path = ( a.publicUrl && a.publicUrl.url ) || '';
		if ( path.charAt( 0 ) === '/' ) {
			path = path.slice( 1 );
		}

		return {
			id: a.animalId,
			name: decodeEntities( a.animalName || '' ),
			breed: breed,
			size: ( phys.size && phys.size.label ) || '',
			age: ( phys.age && phys.age.label ) || '',
			city: addr.city || '',
			state: addr.state || '',
			photo: a.primaryPhotoId ? ( cfg.s3Url + a.primaryPhotoId ) : '',
			url: path ? ( base + path + '/details' ) : ( cfg.petfinderUrl || '#' )
		};
	}

	/* ----------------------------------------------------------------- */
	/* Per-widget controller.                                            */
	/* ----------------------------------------------------------------- */

	function initWidget( root ) {
		if ( root.__pmInit ) {
			return;
		}
		root.__pmInit = true;

		var cfg;
		try {
			cfg = JSON.parse( root.getAttribute( 'data-pm-config' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}

		var grid = root.querySelector( '[data-pm-grid]' );
		var chipsEl = root.querySelector( '[data-pm-chips]' );
		var countEl = root.querySelector( '[data-pm-count]' );
		if ( ! grid ) {
			return;
		}

		// Collect whichever filter selects the template rendered.
		var selects = {};
		each( root.querySelectorAll( '[data-pm-filter]' ), function ( sel ) {
			selects[ sel.getAttribute( 'data-pm-filter' ) ] = sel;
		} );
		var activeKeys = FILTER_ORDER.filter( function ( k ) {
			return !! selects[ k ];
		} );

		var cats = [];
		var state = {};
		activeKeys.forEach( function ( k ) {
			state[ k ] = 'all';
		} );

		/* ---- value helpers ---- */

		function uniqueValues( list, key ) {
			var seen = {};
			var out = [];
			list.forEach( function ( item ) {
				var v = item[ key ];
				if ( v && ! seen[ v ] ) {
					seen[ v ] = true;
					out.push( v );
				}
			} );
			return out;
		}

		function sortValues( key, values ) {
			var order = VALUE_ORDER[ key ];
			if ( ! order ) {
				return values.slice().sort( function ( a, b ) {
					return a.localeCompare( b );
				} );
			}
			return values.slice().sort( function ( a, b ) {
				var ia = order.indexOf( a );
				var ib = order.indexOf( b );
				if ( ia === -1 && ib === -1 ) {
					return a.localeCompare( b );
				}
				if ( ia === -1 ) {
					return 1;
				}
				if ( ib === -1 ) {
					return -1;
				}
				return ia - ib;
			} );
		}

		function setOptions( select, values, allLabel ) {
			var current = select.value || 'all';
			var html = '<option value="all">' + escapeHtml( allLabel ) + '</option>';
			values.forEach( function ( v ) {
				html += '<option value="' + escapeHtml( v ) + '">' + escapeHtml( v ) + '</option>';
			} );
			select.innerHTML = html;
			var has = false;
			each( select.options, function ( o ) {
				if ( o.value === current ) {
					has = true;
				}
			} );
			select.value = has ? current : 'all';
		}

		/* ---- rendering ---- */

		function skeletonCard() {
			return (
				'<div class="pm-skel" aria-hidden="true">' +
				'<div class="pm-skel-media"></div>' +
				'<div class="pm-skel-body">' +
				'<div class="pm-skel-line lg"></div>' +
				'<div class="pm-skel-line md"></div>' +
				'<div class="pm-skel-line sm"></div>' +
				'<div class="pm-skel-pill"></div>' +
				'</div></div>'
			);
		}

		function card( cat ) {
			var name = escapeHtml( cat.name );
			var breed = escapeHtml( cat.breed );
			var loc = escapeHtml( [ cat.city, cat.state ].filter( Boolean ).join( ', ' ) );
			var photo = escapeHtml( cat.photo );
			var url = escapeHtml( cat.url );
			var badge = escapeHtml( [ cat.age, cat.size ].filter( Boolean ).join( ' • ' ) );

			var media = photo
				? '<img class="pm-card-img" src="' + photo + '" alt="' + name + '" loading="lazy" />'
				: '<div class="pm-card-noimg">🐾</div>';

			var badgeHtml = badge ? '<div class="pm-badge">' + badge + '</div>' : '';
			var breedHtml = ( ! cfg.hideBreed && breed ) ? '<div class="pm-breed">' + breed + '</div>' : '';
			var locHtml = '<div class="pm-loc">' + ( loc || escapeHtml( cfg.orgName || '' ) ) + '</div>';

			return (
				'<a class="pm-card" href="' + url + '" target="_blank" rel="noopener noreferrer">' +
				'<div class="pm-card-media">' + media + badgeHtml + '</div>' +
				'<div class="pm-card-body">' +
				'<div class="pm-card-head">' +
				'<div>' +
				'<div class="pm-name">' + name + '</div>' +
				breedHtml +
				locHtml +
				'</div>' +
				'<div class="pm-paw" aria-hidden="true">🐾</div>' +
				'</div>' +
				'<span class="pm-cta">Boop to view <span class="pm-cta-arrow" aria-hidden="true">→</span> <span aria-hidden="true">✨</span></span>' +
				'</div>' +
				'</a>'
			);
		}

		function renderChips() {
			if ( ! chipsEl ) {
				return;
			}
			var active = activeKeys.filter( function ( k ) {
				return state[ k ] !== 'all';
			} );

			if ( ! active.length ) {
				chipsEl.innerHTML =
					'<span class="pm-chip-tip">💗 Tip: pick a filter… or hit Shuffle for chaos</span>';
				return;
			}

			chipsEl.innerHTML = active.map( function ( k ) {
				return (
					'<button type="button" class="pm-chip" data-pm-chip="' + escapeHtml( k ) + '">' +
					escapeHtml( FILTER_LABEL[ k ] + ': ' + state[ k ] ) +
					' <span class="pm-chip-x" aria-hidden="true">✕</span>' +
					'</button>'
				);
			} ).join( '' );

			each( chipsEl.querySelectorAll( '[data-pm-chip]' ), function ( btn ) {
				btn.addEventListener( 'click', function () {
					var key = btn.getAttribute( 'data-pm-chip' );
					state[ key ] = 'all';
					if ( selects[ key ] ) {
						selects[ key ].value = 'all';
					}
					render();
				} );
			} );
		}

		function renderCount( shown, total ) {
			if ( ! countEl ) {
				return;
			}
			countEl.innerHTML =
				'<span class="pm-count-pill">🔎 Showing <strong>' + shown + '</strong> of <strong>' + total + '</strong></span>';
		}

		function readFilters() {
			activeKeys.forEach( function ( k ) {
				state[ k ] = selects[ k ].value;
			} );
		}

		function applyFilters( list ) {
			return list.filter( function ( c ) {
				return activeKeys.every( function ( k ) {
					return state[ k ] === 'all' || c[ k ] === state[ k ];
				} );
			} );
		}

		function setBusy( busy ) {
			grid.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}

		function render() {
			readFilters();
			renderChips();

			var filtered = applyFilters( cats );
			renderCount( filtered.length, cats.length );

			if ( ! filtered.length ) {
				grid.innerHTML =
					'<div class="pm-empty">' +
					'<div class="pm-empty-emoji">🙀</div>' +
					'<div class="pm-empty-title">No matches for those filters</div>' +
					'<div class="pm-empty-text">Try clearing a filter or two.</div>' +
					'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="clear">Clear filters</button>' +
					'</div>';
				wireActions();
				setBusy( false );
				return;
			}

			grid.innerHTML = filtered.map( card ).join( '' );
			setBusy( false );
		}

		function hydrateFilterOptions() {
			activeKeys.forEach( function ( k ) {
				var sel = selects[ k ];
				setOptions( sel, sortValues( k, uniqueValues( cats, k ) ), sel.getAttribute( 'data-pm-all' ) || 'All' );
			} );
		}

		function showSkeletons() {
			var n = Math.min( 6, Math.max( 3, parseInt( cfg.limit, 10 ) || 6 ) );
			var html = '';
			for ( var i = 0; i < n; i++ ) {
				html += skeletonCard();
			}
			grid.innerHTML = html;
		}

		function showError( message ) {
			grid.innerHTML =
				'<div class="pm-error">' +
				'<div class="pm-error-emoji">😿</div>' +
				'<div class="pm-error-title">' + escapeHtml( message ) + '</div>' +
				'<div class="pm-error-text">Please try again in a moment.</div>' +
				'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="retry">Try again</button>' +
				'</div>';
			if ( countEl ) {
				countEl.innerHTML = '';
			}
			if ( chipsEl ) {
				chipsEl.innerHTML = '';
			}
			wireActions();
			setBusy( false );
		}

		function showEmpty() {
			grid.innerHTML =
				'<div class="pm-empty">' +
				'<div class="pm-empty-emoji">🐾</div>' +
				'<div class="pm-empty-title">No adoptable pets right now</div>' +
				'<div class="pm-empty-text">Please check back soon — new friends arrive all the time!</div>' +
				'</div>';
			if ( countEl ) {
				countEl.innerHTML = '';
			}
			setBusy( false );
		}

		/* ---- behaviors ---- */

		function shuffle() {
			if ( ! cats.length ) {
				return;
			}
			for ( var i = cats.length - 1; i > 0; i-- ) {
				var j = Math.floor( Math.random() * ( i + 1 ) );
				var tmp = cats[ i ];
				cats[ i ] = cats[ j ];
				cats[ j ] = tmp;
			}
			render();
		}

		function clear() {
			activeKeys.forEach( function ( k ) {
				state[ k ] = 'all';
				if ( selects[ k ] ) {
					selects[ k ].value = 'all';
				}
			} );
			render();
		}

		function load() {
			setBusy( true );
			showSkeletons();

			var orgs = Array.isArray( cfg.organization ) ? cfg.organization : [];
			var orgStep = orgs.length ? resolveOrgs( cfg.apiBase, orgs ) : Promise.resolve( [] );

			orgStep.then( function ( uuids ) {
				// Safety guard: if organizations were configured but none could be
				// resolved, do NOT fall through to querying every shelter.
				if ( orgs.length && uuids.length === 0 ) {
					throw new Error( 'We couldn’t find this shelter on Petfinder.' );
				}
				return searchAnimals( cfg, uuids );
			} ).then( function ( animals ) {
				cats = animals.map( function ( a ) {
					return normalize( a, cfg );
				} );
				if ( ! cats.length ) {
					showEmpty();
					return;
				}
				hydrateFilterOptions();
				render();
			} ).catch( function ( err ) {
				showError( ( err && err.message ) ? err.message : 'Something went wrong loading pets.' );
			} );
		}

		/* ---- event wiring ---- */

		function wireActions() {
			each( root.querySelectorAll( '[data-pm-action]' ), function ( btn ) {
				if ( btn.__pmWired ) {
					return;
				}
				btn.__pmWired = true;
				btn.addEventListener( 'click', function () {
					var action = btn.getAttribute( 'data-pm-action' );
					if ( action === 'shuffle' ) {
						shuffle();
					} else if ( action === 'clear' ) {
						clear();
					} else if ( action === 'retry' ) {
						load();
					}
				} );
			} );
		}

		activeKeys.forEach( function ( k ) {
			selects[ k ].addEventListener( 'change', render );
		} );
		wireActions();

		load();
	}

	ready( function () {
		each( document.querySelectorAll( '.pm-wrap' ), initWidget );
	} );
}());
