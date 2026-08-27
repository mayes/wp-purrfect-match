/**
 * Petfinder Explorer (wp-admin). Runs live queries from the site's own
 * origin, which Petfinder accepts. Includes a one-click "Discover extra
 * fields" that resolves the org and probes which animal fields exist.
 */
( function () {
	'use strict';

	var CFG = window.PM_EXPLORER || {};
	var DEFAULT_ORG = CFG.org || '';
	var DEFAULT_TYPE = CFG.type || 'cat';
	var requestSerial = 0;

	function $( id ) {
		return document.getElementById( id );
	}

	function endpoint() {
		return ( $( 'pmx-endpoint' ).value || '' ).trim();
	}

	function organizationIdType( organizationId ) {
		return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test( organizationId ) ? 'id' : 'display_id';
	}

	function status( text, cls ) {
		var s = $( 'pmx-status' );
		s.textContent = text;
		s.className = 'pmx-status' + ( cls ? ' ' + cls : '' );
	}

	function out( text ) {
		$( 'pmx-out' ).textContent = text;
	}

	function busy( value ) {
		$( 'pmx-out' ).setAttribute( 'aria-busy', value ? 'true' : 'false' );
		$( 'pmx-run' ).disabled = !! value;
		$( 'pmx-discover' ).disabled = !! value;
	}

	function gql( query, variables ) {
		return fetch( endpoint(), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { query: query, variables: variables || {} } )
		} ).then( function ( r ) {
			return r.text().then( function ( t ) {
				return { ok: r.ok, statusCode: r.status, text: t };
			} );
		} );
	}

	var PRESETS = {
		org: {
			q: 'query GetOrganization($organizationId: String!, $idType: String!) {\n  organization(id: $organizationId, idType: $idType) {\n    organizationName displayId organizationId\n  }\n}',
			v: { organizationId: DEFAULT_ORG, idType: organizationIdType( DEFAULT_ORG ) }
		},
		search: {
			q: 'query SearchAnimal($pagination: PaginationInfoInput!, $filters: AnimalSearchFiltersInput!) {\n  searchAnimal(pagination: $pagination, sort: { field: "animal_type", order: "desc" }, filters: $filters) {\n    totalCount\n    animals {\n      animalId animalName primaryPhotoId\n      physical { size { label } breed { primary mixed } age { label value } }\n      _contact { address { city state } }\n      publicUrl { url }\n    }\n  }\n}',
			v: { pagination: { fromPage: 0, pageSize: 3 }, filters: { animal_type: [ DEFAULT_TYPE ], adoption_status: 'adoptable' } }
		},
		attrs: {
			q: 'query AllAnimalAttributes($animalType: String!) {\n  allAnimalAttributes(animalType: $animalType) {\n    ages { id name }\n    sexes { id name }\n    sizes { id name }\n    speciesList { primaryBreeds { id name } }\n  }\n}',
			v: { animalType: DEFAULT_TYPE }
		},
		introspect: {
			q: 'query {\n  __type(name: "Animal") {\n    name\n    fields { name type { name kind ofType { name kind } } }\n  }\n}',
			v: {}
		}
	};

	function setPreset( key ) {
		var p = PRESETS[ key ];
		if ( ! p ) {
			return;
		}
		$( 'pmx-query' ).value = p.q;
		$( 'pmx-vars' ).value = JSON.stringify( p.v, null, 2 );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-preset]' ), function ( button ) {
			var selected = button.getAttribute( 'data-preset' ) === key;
			button.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
			button.classList.toggle( 'is-selected', selected );
		} );
	}

	function run() {
		if ( $( 'pmx-run' ).disabled ) {
			return;
		}
		var requestId = ++requestSerial;
		var vars;
		try {
			vars = JSON.parse( $( 'pmx-vars' ).value || '{}' );
		} catch ( e ) {
			status( 'Variables are not valid JSON: ' + e.message, 'err' );
			return;
		}
		status( 'Running…' );
		busy( true );
		var t0 = Date.now();
		gql( $( 'pmx-query' ).value, vars ).then( function ( res ) {
			if ( requestId !== requestSerial ) {
				return;
			}
			var ms = Date.now() - t0;
			var pretty;
			try {
				pretty = JSON.stringify( JSON.parse( res.text ), null, 2 );
			} catch ( _e ) {
				pretty = res.text;
			}
			out( pretty );
			var hasErrors = /"errors"\s*:/.test( res.text );
			if ( ! res.ok ) {
				status( 'HTTP ' + res.statusCode + ' • ' + ms + ' ms', 'err' );
			} else if ( hasErrors ) {
				status( '200 with GraphQL errors • ' + ms + ' ms', 'err' );
			} else {
				status( 'OK • ' + ms + ' ms', 'ok' );
			}
			busy( false );
		} ).catch( function ( err ) {
			if ( requestId !== requestSerial ) {
				return;
			}
			status( 'Request failed: ' + err.message, 'err' );
			out( String( err ) );
			busy( false );
		} );
	}

	// Resolve the org, then probe which extra animal fields exist by removing
	// the ones GraphQL rejects until the query validates.
	function discover() {
		if ( ! DEFAULT_ORG ) {
			status( 'Configure a Petfinder organization ID first.', 'err' );
			out( 'Open Settings → Purrfect Match and save your organization ID, then return here.' );
			return;
		}
		var requestId = ++requestSerial;
		status( 'Resolving organization…' );
		busy( true );
		gql( 'query($id:String!,$t:String!){organization(id:$id,idType:$t){organizationId}}', { id: DEFAULT_ORG, t: organizationIdType( DEFAULT_ORG ) } )
			.then( function ( r ) {
				if ( requestId !== requestSerial ) {
					return;
				}
				var j = JSON.parse( r.text );
				var uuid = j && j.data && j.data.organization && j.data.organization.organizationId;
				if ( ! uuid ) {
					status( 'Could not resolve org ' + DEFAULT_ORG, 'err' );
					out( r.text );
					busy( false );
					return;
				}
				var cand = {
					description: 'description', status: 'status', sex: 'sex', gender: 'gender',
					tags: 'tags', distance: 'distance', publishedAt: 'publishedAt', species: 'species', type: 'type', coat: 'coat',
					photos: 'photos{__typename}', primaryPhoto: 'primaryPhoto{__typename}', videos: 'videos{__typename}',
					attributes: 'attributes{__typename}', environment: 'environment{__typename}',
					breeds: 'breeds{__typename}', colors: 'colors{__typename}', contact: 'contact{__typename}'
				};
				var vars = { p: { fromPage: 0, pageSize: 1 }, f: { animal_type: [ DEFAULT_TYPE ], adoption_status: 'adoptable', organization_id: [ uuid ] } };
				var build = function ( c ) {
					var sel = Object.keys( c ).map( function ( k ) { return c[ k ]; } ).join( ' ' );
					return 'query($p:PaginationInfoInput!,$f:AnimalSearchFiltersInput!){searchAnimal(pagination:$p,filters:$f){animals{ animalId animalName ' + sel + ' }}}';
				};
				var tries = 0;
				function step() {
					if ( requestId !== requestSerial ) {
						return;
					}
					status( 'Probing fields… (pass ' + ( tries + 1 ) + ')' );
					gql( build( cand ), vars ).then( function ( rr ) {
						if ( requestId !== requestSerial ) {
							return;
						}
						var jj;
						try {
							jj = JSON.parse( rr.text );
						} catch ( _e ) {
							status( 'Unexpected response', 'err' );
							out( rr.text );
							busy( false );
							return;
						}
						var errs = jj.errors || [];
						if ( errs.length && tries++ < 10 ) {
							var removed = false;
							errs.forEach( function ( e ) {
								var m = /Cannot query field "([^"]+)"/.exec( e.message || '' );
								if ( m && cand[ m[ 1 ] ] ) {
									delete cand[ m[ 1 ] ];
									removed = true;
								}
							} );
							if ( removed ) {
								step();
								return;
							}
						}
						var valid = Object.keys( cand );
						var sample = jj.data && jj.data.searchAnimal && jj.data.searchAnimal.animals && jj.data.searchAnimal.animals[ 0 ];
						out(
							( errs.length ? 'Candidate fields not rejected before validation stopped:' : '✓ Optional fields accepted by the API:' ) + '\n  ' + valid.join( ', ' ) +
							'\n\nSample animal:\n' + JSON.stringify( sample || jj, null, 2 )
						);
						status( errs.length ? 'Partial result — review the remaining GraphQL errors' : 'Done', errs.length ? 'err' : 'ok' );
						busy( false );
					} ).catch( function ( e ) {
						if ( requestId !== requestSerial ) {
							return;
						}
						status( 'Request failed: ' + e.message, 'err' );
						out( String( e ) + '\n\nIf this is a CORS error, make sure you are running this from your own site.' );
						busy( false );
					} );
				}
				step();
			} )
			.catch( function ( e ) {
				if ( requestId !== requestSerial ) {
					return;
				}
				status( 'Request failed: ' + e.message, 'err' );
				out( String( e ) );
				busy( false );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! $( 'pmx-query' ) ) {
			return;
		}
		var presets = document.querySelector( '.pmx-presets' );
		if ( presets ) {
			presets.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( 'button[data-preset]' );
				if ( b ) {
					setPreset( b.getAttribute( 'data-preset' ) );
				}
				if ( e.target.closest( '#pmx-discover' ) ) {
					discover();
				}
			} );
		}
		$( 'pmx-run' ).addEventListener( 'click', run );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.metaKey || e.ctrlKey ) && e.key === 'Enter' ) {
				run();
			}
		} );
		setPreset( 'org' );
	} );
}() );
