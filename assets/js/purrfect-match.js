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

	// Shortcode input never reaches GraphQL directly. These two fixed variable
	// values are the complete public sort allow-list.
	function normalizeSortMode( value ) {
		return value === 'newest' ? 'newest' : 'default';
	}

	function searchSort( mode ) {
		if ( normalizeSortMode( mode ) === 'newest' ) {
			return [ { field: 'publish_time', order: 'DESC' } ];
		}
		return [ { field: 'animal_type', order: 'desc' } ];
	}

	// Build the search query. When rich is true, request the optional
	// `description` field (the pet's "story"); if the endpoint doesn't support
	// it the caller downgrades to the proven query, so this can't break. Newest
	// mode requests publication metadata only to support Petfinder's verified
	// server-side ordering contract; normalized pet records never retain it.
	function buildSearchQuery( rich, sortMode ) {
		var newest = normalizeSortMode( sortMode ) === 'newest';
		return 'query SearchAnimal($pagination: PaginationInfoInput!, $filters: AnimalSearchFiltersInput!, $sort: [SortInput!]!) {' +
			'  searchAnimal(pagination: $pagination, sort: $sort, filters: $filters) {' +
			'    totalCount' +
			'    animals {' +
			'      animalId' +
			'      animalName' +
			'      primaryPhotoId' +
			( newest ? '      meta { publishTime }' : '' ) +
			( rich ? '      description' : '' ) +
			'      physical { size { label } breed { primary mixed } age { label value } }' +
			'      _contact { address { city state } }' +
			'      publicUrl { url }' +
			'    }' +
			'  }' +
			'}';
	}

	var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

	// Order dropdown values sensibly instead of plain alphabetical.
	var VALUE_ORDER = {
		size: [ 'Small', 'Medium', 'Large', 'Extra Large', 'X-Large' ],
		age: [ 'Baby', 'Kitten', 'Puppy', 'Young', 'Adult', 'Senior', 'Mature' ]
	};

	var FILTER_ORDER = [ 'breed', 'size', 'age' ];

	// Per-request page size when fetching the full set (kept small for safety).
	var PAGE_SIZE = 24;

	// Short-lived localStorage cache: be a good citizen of the shared Petfinder
	// endpoint by not re-fetching identical listings on every page view.
	var CACHE_PREFIX = 'pmcache:v2:';
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
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	// Small, consistent line icons for markup rendered in the browser. Paths are
	// static plugin code; labels live on the surrounding controls.
	function icon( name ) {
		var paths = {
			paw: '<ellipse cx="12" cy="15.5" rx="4.6" ry="3.8"></ellipse><circle cx="5.5" cy="10" r="2"></circle><circle cx="9.5" cy="6.5" r="2"></circle><circle cx="14.5" cy="6.5" r="2"></circle><circle cx="18.5" cy="10" r="2"></circle>',
			book: '<path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11v16H7.5A3.5 3.5 0 0 0 4 21.5Z"></path><path d="M20 5.5A3.5 3.5 0 0 0 16.5 2H13v16h3.5a3.5 3.5 0 0 1 3.5 3.5Z"></path>',
			arrow: '<path d="M5 12h14M13 6l6 6-6 6"></path>',
			back: '<path d="M19 12H5M11 6l-6 6 6 6"></path>',
			close: '<path d="m6 6 12 12M18 6 6 18"></path>',
			refresh: '<path d="M20 7v5h-5M4 17v-5h5"></path><path d="M6.1 9a7 7 0 0 1 11.7-2.6L20 9M4 15l2.2 2.6A7 7 0 0 0 17.9 15"></path>',
			search: '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path>',
			mail: '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>'
		};
		return '<svg class="pm-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + ( paths[ name ] || paths.paw ) + '</svg>';
	}

	// Defense-in-depth for URL sinks: allow only http(s) / relative / anchor URLs
	// so a hostile scheme can never reach an href/src. Control chars and spaces
	// — which browsers strip before scheme parsing, enabling "java\tscript:"
	// style bypasses — are removed first.
	function safeUrl( u ) {
		var s = String( u == null ? '' : u ).replace( /[\x00-\x20]+/g, '' );
		if ( ! s ) {
			return '';
		}
		if ( /^https?:\/\//i.test( s ) ) {
			return s;
		}
		// Reject protocol-relative ("//host") and anything else declaring a scheme.
		if ( /^\/\//.test( s ) || /^[a-z][a-z0-9+.-]*:/i.test( s ) ) {
			return '';
		}
		return s;
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
				var uuid = ( data && data.organization && data.organization.organizationId ) || null;
				if ( ! uuid ) {
					delete orgCache[ key ];
				}
				return uuid;
			} )
			.catch( function () {
				delete orgCache[ key ];
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
	// Whether the endpoint supports the optional `description` field, cached per
	// endpoint so the probe only happens once.
	function richState( cfg ) {
		try {
			return window.localStorage.getItem( 'pmrich:v1:' + cfg.apiBase );
		} catch ( e ) {
			return null;
		}
	}

	function setRichState( cfg, value ) {
		try {
			window.localStorage.setItem( 'pmrich:v1:' + cfg.apiBase, value );
		} catch ( e ) {
			/* best-effort */
		}
	}

	// One page. Parses the body even on a 4xx so an "unknown field" validation
	// error (which Apollo returns as HTTP 400) can be detected and downgraded.
	function searchPage( cfg, orgUuids, fromPage, rich, sortMode ) {
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
			pagination: { fromPage: fromPage, pageSize: PAGE_SIZE },
			sort: searchSort( sortMode )
		};
		return fetch( cfg.apiBase, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { query: buildSearchQuery( rich, sortMode ), variables: variables } )
		} ).then( function ( res ) {
			// Apollo returns the validation error body with HTTP 400, so parse
			// the JSON regardless of status to detect an unsupported field.
			return res.json().then(
				function ( json ) {
					if ( json && json.errors && json.errors.length ) {
						var err = new Error( json.errors[ 0 ].message || 'GraphQL error' );
						if ( json.errors.some( function ( graphError ) {
							return graphError && /Cannot query field\s+(?:["']description["']|description\b)/i.test( graphError.message || '' );
						} ) ) {
							err.unknownDescription = true;
						}
						throw err;
					}
					if ( ! res.ok ) {
						throw new Error( 'HTTP error ' + res.status );
					}
					var sa = ( json && json.data && json.data.searchAnimal ) || {};
					return { totalCount: sa.totalCount || 0, animals: sa.animals || [] };
				},
				function () {
					throw new Error( 'HTTP error ' + res.status );
				}
			);
		} );
	}

	// Fetch the full set for the org (up to cfg.limit). Page 0 first to learn
	// totalCount (and whether `description` is supported), then the remaining
	// pages in parallel — so filters and counts operate on every animal.
	function fetchAnimalsForSort( cfg, orgUuids, sortMode ) {
		// limit 0 (or unset) = fetch all, bounded by a high safety ceiling.
		var cap = cfg.limit > 0 ? cfg.limit : 1000;
		var wantRich = !! cfg.showBios && richState( cfg ) !== 'no';

		function firstPage() {
			if ( ! wantRich ) {
				return searchPage( cfg, orgUuids, 0, false, sortMode ).then( function ( r ) {
					r._rich = false;
					return r;
				} );
			}
			return searchPage( cfg, orgUuids, 0, true, sortMode ).then( function ( r ) {
				setRichState( cfg, 'yes' );
				r._rich = true;
				return r;
			} ).catch( function ( e ) {
				// `description` not supported here: remember and use the proven query.
				if ( e && e.unknownDescription ) {
					setRichState( cfg, 'no' );
					return searchPage( cfg, orgUuids, 0, false, sortMode ).then( function ( r ) {
						r._rich = false;
						return r;
					} );
				}
				throw e;
			} );
		}

		return firstPage().then( function ( first ) {
			var useRich = first._rich;
			var animals = first.animals.slice();
			var total = Math.min( first.totalCount || animals.length, cap );
			if ( ! first.animals.length || animals.length >= total ) {
				return animals.slice( 0, cap );
			}
			var pages = Math.ceil( total / PAGE_SIZE );
			var rest = [];
			for ( var p = 1; p < pages; p++ ) {
				rest.push( searchPage( cfg, orgUuids, p, useRich, sortMode ) );
			}
			return Promise.all( rest ).then( function ( results ) {
				results.forEach( function ( r ) {
					animals = animals.concat( r.animals );
				} );
				return animals.slice( 0, cap );
			} );
		} );
	}

	// Newest is an additive enhancement. If Petfinder rejects its sort input or
	// publication metadata (or a later newest page), restart from page zero with
	// the long-proven default request so the widget still renders current pets.
	function fetchAllAnimals( cfg, orgUuids ) {
		var requestedSort = normalizeSortMode( cfg.sort );
		return fetchAnimalsForSort( cfg, orgUuids, requestedSort ).catch( function ( err ) {
			if ( requestedSort === 'newest' ) {
				return fetchAnimalsForSort( cfg, orgUuids, 'default' );
			}
			throw err;
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
		var photoBase = cfg.s3Url || '';
		if ( base && base.charAt( base.length - 1 ) !== '/' ) {
			base += '/';
		}
		if ( photoBase && photoBase.charAt( photoBase.length - 1 ) !== '/' ) {
			photoBase += '/';
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
			bio: a.description ? decodeEntities( String( a.description ) ).replace( /\s+/g, ' ' ).trim() : '',
			photo: a.primaryPhotoId ? ( photoBase + String( a.primaryPhotoId ).replace( /^\/+/, '' ) ) : '',
			url: path ? ( base + path + '/details' ) : ( cfg.petfinderUrl || '#' )
		};
	}

	function cacheKey( cfg ) {
		var orgs = Array.isArray( cfg.organization ) ? cfg.organization.slice().sort().join( ',' ) : '';
		return CACHE_PREFIX + [
			cfg.apiBase,
			cfg.s3Url,
			cfg.petfinderUrl,
			orgs,
			cfg.type,
			cfg.status,
			normalizeSortMode( cfg.sort ),
			cfg.limit,
			cfg.showBios ? 'rich' : 'basic'
		].join( '|' );
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

	// The per-visitor copy is always stamped with the local clock so it lives a
	// full CACHE_TTL_MS for this browser (the shared/server copy has its own,
	// independent, server-side lifetime).
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
		cfg.sort = normalizeSortMode( cfg.sort );

		var strings = cfg.strings || {};
		function message( key, fallback, values ) {
			var output = String( strings[ key ] || fallback );
			Object.keys( values || {} ).forEach( function ( token ) {
				output = output.split( '{' + token + '}' ).join( String( values[ token ] ) );
			} );
			return output;
		}

		var filterLabels = {
			breed: message( 'breed', 'Breed' ),
			size: message( 'size', 'Size' ),
			age: message( 'age', 'Age' )
		};

		var grid = root.querySelector( '[data-pm-grid]' );
		var chipsEl = root.querySelector( '[data-pm-chips]' );
		var countEl = root.querySelector( '[data-pm-count]' );
		var statusEl = root.querySelector( '[data-pm-status]' );
		var clearButtons = root.querySelectorAll( '[data-pm-action="clear"]' );
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
		var loading = false;
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
				'<div class="pm-skel" role="listitem" aria-hidden="true">' +
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

		function card( cat, index ) {
			var rawName = cat.name || '';
			var name = escapeHtml( rawName );
			var breed = escapeHtml( cat.breed );
			var loc = escapeHtml( [ cat.city, cat.state ].filter( Boolean ).join( ', ' ) );
			var photo = escapeHtml( safeUrl( cat.photo ) );
			var url = escapeHtml( safeUrl( cat.url ) );
			var badge = escapeHtml( [ cat.age, cat.size ].filter( Boolean ).join( ' · ' ) );
			var hasStory = !! ( cfg.showBios && cat.bio );
			var cardId = root.id + '-pet-' + index;
			var nameId = cardId + '-name';
			var storyId = cardId + '-story';
			var storyNameId = cardId + '-story-name';
			var newTab = '<span class="pm-sr-only"> ' + escapeHtml( message( 'newTab', '(opens in a new tab)' ) ) + '</span>';

			var media = photo
				? '<img class="pm-card-img" src="' + photo + '" alt="' + escapeHtml( message( 'photoOf', 'Photo of {name}', { name: rawName } ) ) + '" loading="lazy" decoding="async" />'
				: '<div class="pm-card-noimg" aria-hidden="true">' + icon( 'paw' ) + '</div>';

			var badgeHtml = ( cfg.showBadge !== false && badge ) ? '<div class="pm-badge">' + badge + '</div>' : '';
			var breedHtml = ( ! cfg.hideBreed && breed ) ? '<div class="pm-breed">' + breed + '</div>' : '';
			var locHtml = ( cfg.showLocation !== false && ( loc || cfg.orgName ) )
				? '<div class="pm-loc">' + ( loc || escapeHtml( cfg.orgName || '' ) ) + '</div>'
				: '';
			var storyBtn = hasStory
				? '<button type="button" class="pm-flip-btn" data-pm-flip="open" aria-expanded="false" aria-controls="' + storyId + '" aria-label="' + escapeHtml( message( 'readStory', 'Read {name}\'s story', { name: rawName } ) ) + '">' + icon( 'book' ) + '<span>' + escapeHtml( message( 'readStoryShort', 'Read story' ) ) + '</span></button>'
				: '';

			var ctas;
			if ( cfg.adoptionFormUrl ) {
				ctas =
					'<a class="pm-cta pm-cta-adopt" href="' + escapeHtml( safeUrl( adoptionLink( cat ) ) ) + '" target="_blank" rel="noopener noreferrer">' + icon( 'mail' ) + '<span>' + escapeHtml( message( 'apply', 'Apply to adopt' ) ) + '</span>' + newTab + '</a>' +
					'<a class="pm-cta-view" href="' + url + '" target="_blank" rel="noopener noreferrer"><span>' + escapeHtml( message( 'viewProfile', 'View profile' ) ) + '</span>' + newTab + '</a>';
			} else {
				ctas =
					'<a class="pm-cta" href="' + url + '" target="_blank" rel="noopener noreferrer"><span>' + escapeHtml( message( 'meetPet', 'Meet {name}', { name: rawName } ) ) + '</span>' + icon( 'arrow' ) + newTab + '</a>';
			}

			var front =
				'<div class="pm-card-front">' +
				'<a class="pm-media-link" href="' + url + '" target="_blank" rel="noopener noreferrer" aria-label="' + escapeHtml( message( 'viewProfile', 'View profile' ) + ': ' + rawName ) + '">' +
				'<div class="pm-card-media">' + media + badgeHtml + '</div></a>' +
				'<div class="pm-card-body">' +
				'<div class="pm-card-head"><div>' +
				'<h3 class="pm-name" id="' + nameId + '"><a class="pm-name-link" href="' + url + '" target="_blank" rel="noopener noreferrer">' + name + '</a></h3>' +
				breedHtml + locHtml +
				'</div><div class="pm-card-mark pm-paw" aria-hidden="true">' + icon( 'paw' ) + '</div></div>' +
				storyBtn +
				'<div class="pm-cta-row">' + ctas + '</div>' +
				'</div></div>';

			var back = '';
			if ( hasStory ) {
				back =
					'<section class="pm-card-back" id="' + storyId + '" data-pm-story role="region" aria-labelledby="' + storyNameId + '" aria-hidden="true">' +
					'<div class="pm-back-head"><span class="pm-back-name" id="' + storyNameId + '">' + name + '</span>' +
					'<button type="button" class="pm-flip-btn pm-flip-close" data-pm-flip="close" aria-label="' + escapeHtml( message( 'hideStory', 'Hide {name}\'s story', { name: rawName } ) ) + '">' + icon( 'close' ) + '</button></div>' +
					'<div class="pm-back-story" tabindex="0">' + escapeHtml( cat.bio ) + '</div>' +
					'<button type="button" class="pm-flip-btn pm-flip-back" data-pm-flip="close">' + icon( 'back' ) + escapeHtml( message( 'back', 'Back' ) ) + '</button>' +
					'</section>';
			}

			return '<article class="pm-card' + ( hasStory ? ' pm-card--flip' : '' ) + '" role="listitem" aria-labelledby="' + nameId + '"><div class="pm-card-inner">' + front + back + '</div></article>';
		}

		function renderChips() {
			if ( ! chipsEl ) {
				return;
			}
			var active = activeKeys.filter( function ( k ) {
				return state[ k ] !== 'all';
			} );

			if ( ! active.length ) {
				chipsEl.innerHTML = '<span class="pm-chip-tip">' + escapeHtml( message( 'filterTip', 'Choose a filter to narrow the list.' ) ) + '</span>';
				each( clearButtons, function ( button ) { button.hidden = true; } );
				return;
			}
			each( clearButtons, function ( button ) { button.hidden = false; } );

			chipsEl.innerHTML = active.map( function ( k ) {
				var label = filterLabels[ k ];
				return (
					'<button type="button" class="pm-chip" data-pm-chip="' + escapeHtml( k ) + '" aria-label="' + escapeHtml( message( 'removeFilter', 'Remove {label} filter', { label: label } ) ) + '">' +
					escapeHtml( label + ': ' + state[ k ] ) +
					' <span class="pm-chip-x" aria-hidden="true">' + icon( 'close' ) + '</span>' +
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
					if ( selects[ key ] && selects[ key ].focus ) {
						selects[ key ].focus();
					}
				} );
			} );
		}

		function announce( text ) {
			if ( statusEl ) {
				statusEl.textContent = text;
			}
		}

		function renderCount( shown, total ) {
			var text = message( 'showingCount', 'Showing {shown} of {total}', { shown: shown, total: total } );
			if ( countEl ) {
				countEl.classList.remove( 'is-loading' );
				countEl.innerHTML = '<span class="pm-count-pill">' + escapeHtml( text ) + '</span>';
			}
			announce( text );
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
			loading = !! busy;
			grid.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
			each( root.querySelectorAll( '[data-pm-filter], [data-pm-action="shuffle"], [data-pm-action="clear"]' ), function ( control ) {
				control.disabled = !! busy;
			} );
		}

		function statePanel( iconName, title, text, actions ) {
			return (
				'<div class="pm-state">' +
				'<div class="pm-state-icon" aria-hidden="true">' + icon( iconName ) + '</div>' +
				'<h4 class="pm-state-title">' + escapeHtml( title ) + '</h4>' +
				'<p class="pm-state-text">' + escapeHtml( text ) + '</p>' +
				( actions || '' ) +
				'</div>'
			);
		}

		// Full (re)render of the visible window [0, shown).
		function paint() {
			grid.setAttribute( 'role', 'list' );
			readFilters();
			renderChips();
			filtered = applyFilters( cats );

			if ( ! filtered.length ) {
				var noMatchesTitle = message( 'noMatchesTitle', 'No pets match those filters' );
				grid.setAttribute( 'role', 'region' );
				grid.innerHTML = statePanel(
					'search',
					noMatchesTitle,
					message( 'noMatchesText', 'Try removing a filter to see more friends.' ),
					'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="clear">' + escapeHtml( message( 'clearFilters', 'Clear filters' ) ) + '</button>'
				);
				renderedCount = 0;
				if ( countEl ) {
					countEl.textContent = '';
				}
				announce( noMatchesTitle );
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
			var start = renderedCount;
			var next = filtered.slice( renderedCount, shown );
			if ( next.length ) {
				grid.insertAdjacentHTML( 'beforeend', next.map( function ( item, index ) {
					return card( item, start + index );
				} ).join( '' ) );
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
			grid.setAttribute( 'role', 'list' );
			var n = Math.min( 6, Math.max( 3, perPage > 0 ? perPage : 6 ) );
			var html = '';
			for ( var i = 0; i < n; i++ ) {
				html += skeletonCard();
			}
			grid.innerHTML = html;
			if ( countEl ) {
				countEl.classList.add( 'is-loading' );
				countEl.innerHTML = '<span class="pm-loading"><span class="pm-paws" aria-hidden="true"><i></i><i></i><i></i></span>' + escapeHtml( message( 'loading', 'Finding adoptable pets…' ) ) + '</span>';
			}
			announce( message( 'loading', 'Finding adoptable pets…' ) );
		}

		function showError() {
			grid.setAttribute( 'role', 'region' );
			// If the live listings can't load, point visitors to the shelter's
			// other adoption pages (configured) instead of a dead end.
			var links = '';
			if ( cfg.adoptapetUrl ) {
				links += '<a class="pm-btn pm-btn-brand" href="' + escapeHtml( safeUrl( cfg.adoptapetUrl ) ) + '" target="_blank" rel="noopener noreferrer">' + icon( 'paw' ) + escapeHtml( message( 'viewAdoptapet', 'View on Adopt-a-Pet' ) ) + '<span class="pm-sr-only"> ' + escapeHtml( message( 'newTab', '(opens in a new tab)' ) ) + '</span></a>';
			}
			if ( cfg.petfinderMemberUrl ) {
				links += '<a class="pm-btn" href="' + escapeHtml( safeUrl( cfg.petfinderMemberUrl ) ) + '" target="_blank" rel="noopener noreferrer">' + icon( 'search' ) + escapeHtml( message( 'viewPetfinder', 'View on Petfinder' ) ) + '<span class="pm-sr-only"> ' + escapeHtml( message( 'newTab', '(opens in a new tab)' ) ) + '</span></a>';
			}

			var errorTitle;
			if ( links ) {
				errorTitle = message( 'fallbackTitle', 'Our live listings are taking a cat nap' );
				grid.innerHTML = statePanel(
					'paw',
					errorTitle,
					message( 'fallbackText', 'You can still browse adoptable pets on these platforms.' ),
					'<div class="pm-state-actions">' + links + '</div><button type="button" class="pm-link-btn" data-pm-action="retry">' + escapeHtml( message( 'tryAgain', 'Try again' ) ) + '</button>'
				);
			} else {
				errorTitle = message( 'errorTitle', 'We couldn’t load the pets right now' );
				grid.innerHTML = statePanel(
					'refresh',
					errorTitle,
					message( 'errorText', 'Please try again in a moment.' ),
					'<button type="button" class="pm-btn pm-btn-brand" data-pm-action="retry">' + icon( 'refresh' ) + escapeHtml( message( 'tryAgain', 'Try again' ) ) + '</button>'
				);
			}

			if ( countEl ) {
				countEl.classList.remove( 'is-loading' );
				countEl.textContent = '';
			}
			announce( errorTitle );
			if ( chipsEl ) {
				chipsEl.innerHTML = '';
			}
			if ( moreEl ) {
				moreEl.hidden = true;
			}
			setBusy( false );
		}

		function showEmpty() {
			var emptyTitle = message( 'emptyTitle', 'No adoptable pets right now' );
			grid.setAttribute( 'role', 'region' );
			grid.innerHTML = statePanel( 'paw', emptyTitle, message( 'emptyText', 'Please check back soon — new friends arrive all the time.' ) );
			if ( countEl ) {
				countEl.classList.remove( 'is-loading' );
				countEl.textContent = '';
			}
			announce( emptyTitle );
			setBusy( false );
		}

		// Shown when no organization has been configured yet. Admins get a
		// setup nudge; visitors get a neutral message.
		function showNotConfigured() {
			var notConfiguredTitle;
			grid.setAttribute( 'role', 'region' );
			if ( cfg.canConfigure ) {
				notConfiguredTitle = message( 'notConfiguredTitle', 'Purrfect Match isn’t set up yet' );
				grid.innerHTML = statePanel(
					'paw',
					notConfiguredTitle,
					message( 'notConfiguredText', 'Add your Petfinder organization ID to start showing adoptable pets.' ),
					cfg.settingsUrl ? '<a class="pm-btn pm-btn-brand" href="' + escapeHtml( safeUrl( cfg.settingsUrl ) ) + '">' + escapeHtml( message( 'openSettings', 'Open settings' ) ) + '</a>' : ''
				);
			} else {
				notConfiguredTitle = message( 'visitorEmptyTitle', 'No pets to show right now' );
				grid.innerHTML = statePanel( 'paw', notConfiguredTitle, message( 'visitorEmptyText', 'Please check back soon.' ) );
			}
			if ( countEl ) {
				countEl.classList.remove( 'is-loading' );
				countEl.textContent = '';
			}
			announce( notConfiguredTitle );
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
		// Inject an ItemList of the loaded pets as JSON-LD so search engines and
		// AI crawlers that render JavaScript can understand the listing. Runs
		// once per widget, over the full loaded set (not the filtered view).
		function injectSchema( list ) {
			if ( ! cfg.seo || root.__pmSchema || ! list || ! list.length ) {
				return;
			}
			var items = [];
			for ( var i = 0; i < list.length && i < 100; i++ ) {
				var c = list[ i ];
				var li = { '@type': 'ListItem', position: i + 1 };
				if ( c.url ) { li.url = c.url; }
				if ( c.name ) { li.name = c.name; }
				if ( c.photo ) { li.image = c.photo; }
				items.push( li );
			}
			var data = { '@context': 'https://schema.org', '@type': 'ItemList', itemListElement: items };
			var s = document.createElement( 'script' );
			s.type = 'application/ld+json';
			s.className = 'pm-schema';
			// Neutralize "</script>" / "<" inside names so the block can't break out.
			s.textContent = JSON.stringify( data ).replace( /</g, '\\u003c' );
			root.appendChild( s );
			root.__pmSchema = true;
		}

		function useCats( list ) {
			cats = list;
			if ( ! cats.length ) {
				showEmpty();
				return;
			}
			hydrateFilterOptions();
			injectSchema( cats );
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
				if ( window.console && window.console.error ) {
					window.console.error( 'Purrfect Match:', err );
				}
				showError();
			} );
		}

		function load() {
			var orgs = Array.isArray( cfg.organization ) ? cfg.organization : [];
			if ( loading ) {
				return;
			}

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
					if ( serverCats && serverCats.length ) {
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

		activeKeys.forEach( function ( k ) {
			selects[ k ].addEventListener( 'change', resetAndPaint );
		} );

		// One delegated handler covers both template controls and state buttons
		// rendered later with innerHTML.
		root.addEventListener( 'click', function ( e ) {
			var btn = e.target && e.target.closest ? e.target.closest( '[data-pm-action]' ) : null;
			if ( ! btn || ! root.contains( btn ) || btn.disabled ) {
				return;
			}
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

		// Flip cards to reveal/hide the pet story (delegated — cards are dynamic).
		grid.addEventListener( 'click', function ( e ) {
			var btn = e.target && e.target.closest ? e.target.closest( '[data-pm-flip]' ) : null;
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			var cardEl = btn.closest( '.pm-card' );
			if ( ! cardEl ) {
				return;
			}
			var flipped = cardEl.classList.toggle( 'is-flipped' );
			var opener = cardEl.querySelector( '[data-pm-flip="open"]' );
			var story = cardEl.querySelector( '[data-pm-story]' );
			if ( opener ) {
				opener.setAttribute( 'aria-expanded', flipped ? 'true' : 'false' );
			}
			if ( story ) {
				story.setAttribute( 'aria-hidden', flipped ? 'false' : 'true' );
			}
			// The activated button ends up visibility:hidden (front hides while
			// the story shows, panel hides on close), which would drop focus to
			// <body>. Hand focus to the counterpart control instead so keyboard
			// and screen-reader users keep their place.
			var target = flipped ? cardEl.querySelector( '.pm-flip-close' ) : opener;
			if ( target && target.focus ) {
				target.focus();
			}
		} );

		// Escape closes an open story and returns focus to its opener.
		grid.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' && e.key !== 'Esc' ) {
				return;
			}
			var cardEl = e.target && e.target.closest ? e.target.closest( '.pm-card.is-flipped' ) : null;
			if ( ! cardEl ) {
				return;
			}
			cardEl.classList.remove( 'is-flipped' );
			var opener = cardEl.querySelector( '[data-pm-flip="open"]' );
			var story = cardEl.querySelector( '[data-pm-story]' );
			if ( story ) {
				story.setAttribute( 'aria-hidden', 'true' );
			}
			if ( opener ) {
				opener.setAttribute( 'aria-expanded', 'false' );
				if ( opener.focus ) {
					opener.focus();
				}
			}
		} );

		load();
	}

	ready( function () {
		each( document.querySelectorAll( '.pm-wrap' ), initWidget );
	} );
}());
