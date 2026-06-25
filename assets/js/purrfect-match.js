/**
 * Purrfect Match — front-end runtime.
 *
 * Loads adoptable pets directly from Petfinder's GraphQL endpoint in the
 * visitor's browser (no API key, no server round-trip), then powers the
 * filter / chip / shuffle / clear UI from the widget's design mockup.
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

	// Per-request page size when fetching the full set (kept small for safety).
	var PAGE_SIZE = 24;

	// Short-lived localStorage cache: be a good citizen of the shared Petfinder
	// endpoint by not re-fetching identical listings on every page view.
	var CACHE_PREFIX = 'pmcache:v1:';
	var CACHE_TTL_MS = 10 * 60 * 1000; // 10 minutes.

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

	// Fetch one page. PAGE_SIZE is kept conservative (matches Petfinder's own
	// widget) so the request is never rejected for an over-large page.
	function searchPage( cfg, orgUuids, fromPage ) {
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
			pagination: { fromPage: fromPage, pageSize: PAGE_SIZE }
		};
		return gql( cfg.apiBase, SEARCH_QUERY, variables ).then( function ( data ) {
			var sa = ( data && data.searchAnimal ) || {};
			return { totalCount: sa.totalCount || 0, animals: sa.animals || [] };
		} );
	}

	// Fetch the full set for the org (up to cfg.limit). Page 0 first to learn
	// totalCount, then the remaining pages in parallel — so filters and counts
	// operate on every animal, not just the first page.
	function fetchAllAnimals( cfg, orgUuids ) {
		// limit 0 (or unset) = fetch all, bounded by a high safety ceiling.
		var cap = cfg.limit > 0 ? cfg.limit : 1000;
		return searchPage( cfg, orgUuids, 0 ).then( function ( first ) {
			var animals = first.animals.slice();
			var total = Math.min( first.totalCount || animals.length, cap );
			if ( ! first.animals.length || animals.length >= total ) {
				return animals.slice( 0, cap );
			}
			var pages = Math.ceil( total / PAGE_SIZE );
			var rest = [];
			for ( var p = 1; p < pages; p++ ) {
				rest.push( searchPage( cfg, orgUuids, p ) );
			}
			return Promise.all( rest ).then( function ( results ) {
				results.forEach( function ( r ) {
					animals = animals.concat( r.animals );
				} );
				return animals.slice( 0, cap );
			} );
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

	function cacheKey( cfg ) {
		var orgs = Array.isArray( cfg.organization ) ? cfg.organization.slice().sort().join( ',' ) : '';
		return CACHE_PREFIX + [ cfg.apiBase, orgs, cfg.type, cfg.status, cfg.limit ].join( '|' );
	}

	function readCache( cfg ) {
		try {
			var raw = window.localStorage.getItem( cacheKey( cfg ) );
			if ( ! raw ) {
				return null;
			}
			var obj = JSON.parse( raw );
			if ( ! obj || typeof obj.t !== 'number' || ! Array.isArray( obj.cats ) ) {
				return null;
			}
			if ( ( Date.now() - obj.t ) > CACHE_TTL_MS ) {
				return null;
			}
			return obj.cats;
		} catch ( e ) {
			return null;
		}
	}

	function writeCache( cfg, cats ) {
		try {
			window.localStorage.setItem( cacheKey( cfg ), JSON.stringify( { t: Date.now(), cats: cats } ) );
		} catch ( e ) {
			/* storage unavailable / full / blocked — caching is best-effort. */
		}
	}

	// Shared (server) cache: visitors read; only capable, logged-in users write.
	function serverGet( cfg ) {
		var url = cfg.restUrl + ( cfg.restUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'key=' + encodeURIComponent( cacheKey( cfg ) );
		return fetch( url, { headers: { Accept: 'application/json' } } )
			.then( function ( r ) {
				return r.ok ? r.json() : null;
			} )
			.then( function ( j ) {
				return ( j && Array.isArray( j.cats ) ) ? j.cats : null;
			} )
			.catch( function () {
				return null;
			} );
	}

	function serverPut( cfg, cats ) {
		try {
			fetch( cfg.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce || '' },
				body: JSON.stringify( { key: cacheKey( cfg ), cats: cats } )
			} ).catch( function () {} );
		} catch ( e ) {
			/* best-effort refresh */
		}
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

		// Pagination / incremental display state.
		var perPage = ( typeof cfg.perPage === 'number' && cfg.perPage > 0 ) ? cfg.perPage : 0; // 0 = show all
		var shown = perPage > 0 ? perPage : Infinity;
		var filtered = [];
		var renderedCount = 0;
		var moreEl = root.querySelector( '[data-pm-more]' );

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

		// Build the adoption-form link for a pet, passing its name/id so the
		// form can prefill which animal this application is for.
		function adoptionLink( cat ) {
			var base = cfg.adoptionFormUrl;
			var sep = base.indexOf( '?' ) === -1 ? '?' : '&';
			var q = 'pet=' + encodeURIComponent( cat.name || '' );
			if ( cat.id ) {
				q += '&pet_id=' + encodeURIComponent( cat.id );
			}
			return base + sep + q;
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
				: '<div class="pm-card-noimg" aria-hidden="true">🐾</div>';

			var badgeHtml = badge ? '<div class="pm-badge">' + badge + '</div>' : '';
			var breedHtml = ( ! cfg.hideBreed && breed ) ? '<div class="pm-breed">' + breed + '</div>' : '';
			var locHtml = '<div class="pm-loc">' + ( loc || escapeHtml( cfg.orgName || '' ) ) + '</div>';

			var ctas;
			if ( cfg.adoptionFormUrl ) {
				ctas =
					'<a class="pm-cta pm-cta-adopt" href="' + escapeHtml( adoptionLink( cat ) ) + '" target="_blank" rel="noopener noreferrer">💌 Apply to adopt</a>' +
					'<a class="pm-cta-view" href="' + url + '" target="_blank" rel="noopener noreferrer">View details</a>';
			} else {
				ctas =
					'<a class="pm-cta" href="' + url + '" target="_blank" rel="noopener noreferrer">Boop to view <span class="pm-cta-arrow" aria-hidden="true">→</span> <span aria-hidden="true">✨</span></a>';
			}

			return (
				'<div class="pm-card">' +
				'<a class="pm-media-link" href="' + url + '" target="_blank" rel="noopener noreferrer" aria-label="' + name + '">' +
				'<div class="pm-card-media">' + media + badgeHtml + '</div>' +
				'</a>' +
				'<div class="pm-card-body">' +
				'<div class="pm-card-head">' +
				'<div>' +
				'<a class="pm-name-link" href="' + url + '" target="_blank" rel="noopener noreferrer"><span class="pm-name">' + name + '</span></a>' +
				breedHtml +
				locHtml +
				'</div>' +
				'<div class="pm-paw" aria-hidden="true">🐾</div>' +
				'</div>' +
				'<div class="pm-cta-row">' + ctas + '</div>' +
				'</div>' +
				'</div>'
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
					resetAndPaint();
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

		// Full (re)render of the visible window [0, shown).
		function paint() {
			readFilters();
			renderChips();
			filtered = applyFilters( cats );

			if ( ! filtered.length ) {
				grid.innerHTML =
					'<div class="pm-empty">' +
					'<div class="pm-empty-emoji">🙀</div>' +
					'<div class="pm-empty-title">No matches for those filters</div>' +
					'<div class="pm-empty-text">Try clearing a filter or two.</div>' +
					'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="clear">Clear filters</button>' +
					'</div>';
				wireActions();
				renderedCount = 0;
				if ( countEl ) {
					countEl.innerHTML = '';
				}
				updateMore();
				setBusy( false );
				return;
			}

			var visible = filtered.slice( 0, shown );
			grid.innerHTML = visible.map( card ).join( '' );
			renderedCount = visible.length;
			renderCount( renderedCount, filtered.length );
			updateMore();
			setBusy( false );
		}

		// Reveal the next batch by appending (existing cards don't re-animate).
		function appendMore() {
			if ( renderedCount >= filtered.length ) {
				return;
			}
			shown = ( shown === Infinity ) ? filtered.length : ( shown + ( perPage > 0 ? perPage : filtered.length ) );
			var next = filtered.slice( renderedCount, shown );
			if ( next.length ) {
				grid.insertAdjacentHTML( 'beforeend', next.map( card ).join( '' ) );
				renderedCount += next.length;
			}
			renderCount( renderedCount, filtered.length );
			updateMore();
		}

		function updateMore() {
			if ( ! moreEl ) {
				return;
			}
			moreEl.hidden = ! ( filtered.length > renderedCount );
		}

		// Reset to the first page and repaint (used on load / filter / shuffle).
		function resetAndPaint() {
			shown = perPage > 0 ? perPage : Infinity;
			paint();
		}

		function hydrateFilterOptions() {
			activeKeys.forEach( function ( k ) {
				var sel = selects[ k ];
				setOptions( sel, sortValues( k, uniqueValues( cats, k ) ), sel.getAttribute( 'data-pm-all' ) || 'All' );
			} );
		}

		function showSkeletons() {
			var n = Math.min( 6, Math.max( 3, perPage > 0 ? perPage : 6 ) );
			var html = '';
			for ( var i = 0; i < n; i++ ) {
				html += skeletonCard();
			}
			grid.innerHTML = html;
			if ( countEl ) {
				countEl.innerHTML =
					'<span class="pm-loading"><span class="pm-paws"><i>🐾</i><i>🐾</i><i>🐾</i></span> ' +
					'Finding adoptable pets…</span>';
			}
		}

		function showError( message ) {
			// If the live listings can't load, point visitors to the shelter's
			// other adoption pages (configured) instead of a dead end.
			var links = '';
			if ( cfg.adoptapetUrl ) {
				links += '<a class="pm-btn pm-btn-brand" href="' + escapeHtml( cfg.adoptapetUrl ) + '" target="_blank" rel="noopener noreferrer">🐾 View on Adopt-a-Pet</a>';
			}
			if ( cfg.petfinderMemberUrl ) {
				links += '<a class="pm-btn" href="' + escapeHtml( cfg.petfinderMemberUrl ) + '" target="_blank" rel="noopener noreferrer">🔎 View on Petfinder</a>';
			}

			if ( links ) {
				grid.innerHTML =
					'<div class="pm-error">' +
					'<div class="pm-error-emoji">🐾</div>' +
					'<div class="pm-error-title">Our live listings are taking a cat nap</div>' +
					'<div class="pm-error-text">No worries — browse our adoptable pets on these platforms:</div>' +
					'<div class="pm-fallback-links">' + links + '</div>' +
					'<button type="button" class="pm-link-btn" data-pm-action="retry">Try the live grid again</button>' +
					'</div>';
			} else {
				grid.innerHTML =
					'<div class="pm-error">' +
					'<div class="pm-error-emoji">😿</div>' +
					'<div class="pm-error-title">' + escapeHtml( message ) + '</div>' +
					'<div class="pm-error-text">Please try again in a moment.</div>' +
					'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="retry">Try again</button>' +
					'</div>';
			}

			if ( countEl ) {
				countEl.innerHTML = '';
			}
			if ( chipsEl ) {
				chipsEl.innerHTML = '';
			}
			if ( moreEl ) {
				moreEl.hidden = true;
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

		// Shown when no organization has been configured yet. Admins get a
		// setup nudge; visitors get a neutral message.
		function showNotConfigured() {
			if ( cfg.canConfigure ) {
				grid.innerHTML =
					'<div class="pm-empty">' +
					'<div class="pm-empty-emoji">⚙️</div>' +
					'<div class="pm-empty-title">Purrfect Match isn’t set up yet</div>' +
					'<div class="pm-empty-text">Add your Petfinder organization ID to start showing adoptable pets.</div>' +
					( cfg.settingsUrl ? '<a class="pm-btn pm-btn-brand" href="' + escapeHtml( cfg.settingsUrl ) + '">Open settings</a>' : '' ) +
					'</div>';
			} else {
				grid.innerHTML =
					'<div class="pm-empty">' +
					'<div class="pm-empty-emoji">🐾</div>' +
					'<div class="pm-empty-title">No pets to show right now</div>' +
					'<div class="pm-empty-text">Please check back soon!</div>' +
					'</div>';
			}
			if ( countEl ) {
				countEl.innerHTML = '';
			}
			if ( chipsEl ) {
				chipsEl.innerHTML = '';
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
			resetAndPaint();
		}

		function clear() {
			activeKeys.forEach( function ( k ) {
				state[ k ] = 'all';
				if ( selects[ k ] ) {
					selects[ k ].value = 'all';
				}
			} );
			resetAndPaint();
		}

		// Render a resolved set (handles the empty case).
		function useCats( list ) {
			cats = list;
			if ( ! cats.length ) {
				showEmpty();
				return;
			}
			hydrateFilterOptions();
			resetAndPaint();
		}

		// Fetch live from Petfinder, cache locally, and (for capable users)
		// refresh the shared server cache for everyone else.
		function liveFetch() {
			showSkeletons();
			resolveOrgs( cfg.apiBase, cfg.organization ).then( function ( uuids ) {
				// Safety guard: organizations were configured but none resolved.
				if ( uuids.length === 0 ) {
					throw new Error( 'We couldn’t find this shelter on Petfinder.' );
				}
				return fetchAllAnimals( cfg, uuids );
			} ).then( function ( animals ) {
				var list = animals.map( function ( a ) {
					return normalize( a, cfg );
				} );
				writeCache( cfg, list );
				if ( cfg.serverCache && cfg.canWrite && cfg.restUrl && list.length ) {
					serverPut( cfg, list );
				}
				useCats( list );
			} ).catch( function ( err ) {
				showError( ( err && err.message ) ? err.message : 'Something went wrong loading pets.' );
			} );
		}

		function load() {
			var orgs = Array.isArray( cfg.organization ) ? cfg.organization : [];

			// No organization configured: never fall through to querying every
			// shelter. Show the setup / neutral state instead.
			if ( ! orgs.length ) {
				showNotConfigured();
				return;
			}

			setBusy( true );

			// 1. Per-visitor cache (fastest).
			var cached = readCache( cfg );
			if ( cached ) {
				useCats( cached );
				return;
			}

			// 2. Shared server cache (one fast read, no Petfinder call).
			if ( cfg.serverCache && cfg.restUrl ) {
				showSkeletons();
				serverGet( cfg ).then( function ( serverCats ) {
					if ( serverCats ) {
						writeCache( cfg, serverCats );
						useCats( serverCats );
					} else {
						liveFetch();
					}
				} ).catch( liveFetch );
				return;
			}

			// 3. Live from Petfinder.
			liveFetch();
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
					} else if ( action === 'more' ) {
						appendMore();
					}
				} );
			} );
		}

		activeKeys.forEach( function ( k ) {
			selects[ k ].addEventListener( 'change', resetAndPaint );
		} );
		wireActions();

		// Auto-load the next batch when the "Load more" button nears the viewport.
		if ( moreEl && window.IntersectionObserver ) {
			var io = new window.IntersectionObserver( function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					if ( entries[ i ].isIntersecting && ! moreEl.hidden ) {
						appendMore();
					}
				}
			}, { rootMargin: '320px' } );
			io.observe( moreEl );
		}

		load();
	}

	ready( function () {
		each( document.querySelectorAll( '.pm-wrap' ), initWidget );
	} );
}());
