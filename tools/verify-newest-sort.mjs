import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const rootDir = path.resolve( here, '..' );
const runtimePath = path.join( rootDir, 'assets', 'js', 'purrfect-match.js' );
const runtimeSource = fs.readFileSync( runtimePath, 'utf8' );
const cssPath = path.join( rootDir, 'assets', 'css', 'purrfect-match.css' );
const cssSource = fs.readFileSync( cssPath, 'utf8' );
const apiBase = 'https://api.example.test/graphql';
const restUrl = 'https://site.example.test/wp-json/purrfect-match/v1/pets';
const organizationId = '12345678-1234-4123-8123-123456789abc';
const defaultSort = [ { field: 'animal_type', order: 'desc' } ];
const newestSort = [ { field: 'publish_time', order: 'DESC' } ];

function response( body, status ) {
	const code = status == null ? 200 : status;
	return {
		ok: code >= 200 && code < 300,
		status: code,
		json: function () {
			return Promise.resolve( body );
		}
	};
}

function searchResponse( animals, totalCount ) {
	return response( {
		data: {
			searchAnimal: {
				totalCount: totalCount == null ? animals.length : totalCount,
				animals: animals
			}
		}
	} );
}

function graphQLError( message, status ) {
	return response( { errors: [ { message: message } ] }, status == null ? 400 : status );
}

function animal( id, name, publishTime ) {
	return {
		animalId: id,
		animalName: name,
		primaryPhotoId: 'photos/' + id + '.jpg',
		description: name + ' has a story.',
		meta: publishTime ? { publishTime: publishTime } : {},
		physical: {
			size: { label: 'Medium' },
			breed: { primary: 'Domestic Short Hair', mixed: false },
			age: { label: 'Adult', value: 'adult' }
		},
		_contact: { address: { city: 'Jupiter', state: 'FL' } },
		publicUrl: { url: '/animal/' + id }
	};
}

function animals( prefix, count ) {
	const list = [];
	for ( let index = 1; index <= count; index += 1 ) {
		const ordinal = String( index ).padStart( 2, '0' );
		list.push( animal(
			prefix.toLowerCase() + '-' + ordinal,
			prefix + ' ' + ordinal,
			'2026-08-' + String( Math.max( 1, 31 - index ) ).padStart( 2, '0' ) + 'T12:00:00Z'
		) );
	}
	return list;
}

class MemoryStorage {
	constructor() {
		this.values = new Map();
	}

	getItem( key ) {
		return this.values.has( String( key ) ) ? this.values.get( String( key ) ) : null;
	}

	setItem( key, value ) {
		this.values.set( String( key ), String( value ) );
	}

	removeItem( key ) {
		this.values.delete( String( key ) );
	}

	keys() {
		return Array.from( this.values.keys() );
	}
}

function classList() {
	const values = new Set();
	return {
		add: function ( value ) { values.add( value ); },
		remove: function ( value ) { values.delete( value ); },
		contains: function ( value ) { return values.has( value ); },
		toggle: function ( value ) {
			if ( values.has( value ) ) {
				values.delete( value );
				return false;
			}
			values.add( value );
			return true;
		}
	};
}

function element() {
	const attributes = {};
	const listeners = {};
	let html = '';
	const node = {
		attributes: attributes,
		children: [],
		classList: classList(),
		disabled: false,
		focused: false,
		hidden: false,
		listeners: listeners,
		options: [],
		textContent: '',
		value: '',
		setAttribute: function ( name, value ) { attributes[ name ] = String( value ); },
		getAttribute: function ( name ) {
			return Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : null;
		},
		addEventListener: function ( name, handler ) {
			listeners[ name ] = listeners[ name ] || [];
			listeners[ name ].push( handler );
		},
		appendChild: function ( child ) { this.children.push( child ); },
		closest: function () { return null; },
		contains: function () { return true; },
		dispatch: function ( name, event ) {
			( listeners[ name ] || [] ).forEach( function ( handler ) { handler( event || {} ); } );
		},
		focus: function () { this.focused = true; },
		querySelector: function () { return null; },
		querySelectorAll: function () { return []; },
		insertAdjacentHTML: function ( position, html ) {
			assert.equal( position, 'beforeend' );
			this.innerHTML += html;
		}
	};
	Object.defineProperty( node, 'innerHTML', {
		get: function () { return html; },
		set: function ( value ) {
			html = String( value == null ? '' : value );
			if ( /<option\b/i.test( html ) ) {
				const parsed = [];
				const pattern = /<option\s+value="([^"]*)"/gi;
				let match;
				while ( ( match = pattern.exec( html ) ) ) {
					parsed.push( { value: match[ 1 ] } );
				}
				node.options = parsed;
			}
		}
	} );
	return node;
}

function widgetDom( config ) {
	const grid = element();
	const chips = element();
	const count = element();
	const status = element();
	const more = element();
	const clear = element();
	const shuffle = element();
	const filters = [ 'breed', 'size', 'age' ].map( function ( key ) {
		const select = element();
		select.setAttribute( 'data-pm-filter', key );
		select.setAttribute( 'data-pm-all', 'All ' + key + 's' );
		select.value = 'all';
		select.options = [ { value: 'all' } ];
		return select;
	} );
	clear.setAttribute( 'data-pm-action', 'clear' );
	shuffle.setAttribute( 'data-pm-action', 'shuffle' );
	const root = element();
	root.setAttribute( 'data-pm-config', JSON.stringify( config ) );
	root.querySelector = function ( selector ) {
		const matches = {
			'[data-pm-grid]': grid,
			'[data-pm-chips]': chips,
			'[data-pm-count]': count,
			'[data-pm-status]': status,
			'[data-pm-more]': more
		};
		return matches[ selector ] || null;
	};
	root.querySelectorAll = function ( selector ) {
		if ( selector === '[data-pm-filter]' ) {
			return filters;
		}
		if ( selector === '[data-pm-action="clear"]' ) {
			return [ clear ];
		}
		if ( selector === '[data-pm-filter], [data-pm-action="shuffle"], [data-pm-action="clear"]' ) {
			return filters.concat( [ shuffle, clear ] );
		}
		return [];
	};

	const document = {
		readyState: 'complete',
		addEventListener: function () {},
		createElement: function ( tagName ) {
			const created = element();
			created.tagName = String( tagName || '' ).toUpperCase();
			return created;
		},
		querySelectorAll: function ( selector ) {
			return selector === '.pm-wrap' ? [ root ] : [];
		}
	};

	return {
		controls: { breed: filters[ 0 ], size: filters[ 1 ], age: filters[ 2 ], clear: clear, shuffle: shuffle },
		count: count,
		document: document,
		grid: grid,
		more: more,
		root: root,
		status: status,
		chips: chips
	};
}

function waitFor( predicate, message ) {
	const started = Date.now();
	return new Promise( function ( resolve, reject ) {
		function check() {
			if ( predicate() ) {
				resolve();
				return;
			}
			if ( Date.now() - started > 2000 ) {
				reject( new Error( message ) );
				return;
			}
			setTimeout( check, 0 );
		}
		check();
	} );
}

async function runWidget( options ) {
	const storage = options.storage || new MemoryStorage();
	const config = {
		apiBase: apiBase,
		s3Url: 'https://images.example.test/',
		petfinderUrl: 'https://www.petfinder.com/',
		organization: [ organizationId ],
		type: 'cat',
		status: 'adoptable',
		limit: options.limit == null ? 4 : options.limit,
		perPage: options.limit == null ? 4 : options.limit,
		showBios: !! options.showBios,
		adoptionFormUrl: options.adoptionFormUrl || '',
		adoptapetUrl: options.adoptapetUrl || '',
		petfinderMemberUrl: options.petfinderMemberUrl || '',
		serverCache: !! options.serverCache,
		canWrite: !! options.canWrite,
		restUrl: options.serverCache ? restUrl : '',
		restNonce: 'test-nonce',
		seo: !! options.seo
	};
	if ( Object.prototype.hasOwnProperty.call( options, 'sort' ) ) {
		config.sort = options.sort;
	}

	const dom = widgetDom( config );
	const calls = [];
	const errors = [];
	let graphIndex = 0;

	function fetchStub( url, init ) {
		const request = {
			body: init && init.body ? String( init.body ) : '',
			headers: ( init && init.headers ) || {},
			method: ( init && init.method ) || 'GET',
			url: String( url )
		};
		if ( request.body ) {
			try {
				request.payload = JSON.parse( request.body );
			} catch ( error ) {
				request.payload = null;
			}
		}
		calls.push( request );

		if ( request.url.indexOf( restUrl ) === 0 ) {
			if ( request.method === 'POST' ) {
				return Promise.resolve( response( { ok: true } ) );
			}
			return Promise.resolve( response( { cats: [] } ) );
		}

		assert.equal( request.url, apiBase, 'unexpected network destination' );
		assert.ok( request.payload && /query SearchAnimal/.test( request.payload.query ), 'expected SearchAnimal request' );
		const result = options.respond( request, graphIndex );
		graphIndex += 1;
		return Promise.resolve( result );
	}

	const window = {
		console: {
			error: function () { errors.push( Array.from( arguments ).join( ' ' ) ); }
		},
		localStorage: storage
	};
	window.window = window;

	const context = vm.createContext( {
		document: dom.document,
		fetch: fetchStub,
		window: window
	} );
	new vm.Script( runtimeSource, { filename: runtimePath } ).runInContext( context );
	const initial = {
		busy: dom.grid.getAttribute( 'aria-busy' ),
		countHtml: dom.count.innerHTML,
		gridHtml: dom.grid.innerHTML,
		role: dom.grid.getAttribute( 'role' )
	};

	await waitFor(
		function () { return dom.grid.getAttribute( 'aria-busy' ) === 'false'; },
		'widget did not reach a settled state'
	);
	await Promise.resolve();

	return {
		calls: calls,
		controls: dom.controls,
		count: dom.count,
		document: dom.document,
		errors: errors,
		grid: dom.grid,
		initial: initial,
		more: dom.more,
		root: dom.root,
		status: dom.status,
		chips: dom.chips,
		storage: storage
	};
}

function graphCalls( result ) {
	return result.calls.filter( function ( call ) {
		return call.payload && /query SearchAnimal/.test( call.payload.query );
	} );
}

function cacheKey( result ) {
	const keys = result.storage.keys().filter( function ( key ) {
		return key.indexOf( 'pmcache:v2:' ) === 0;
	} );
	assert.equal( keys.length, 1, 'expected exactly one visitor cache entry' );
	return keys[ 0 ];
}

function cachedCats( result ) {
	return JSON.parse( result.storage.getItem( cacheKey( result ) ) ).cats;
}

// Frozen 1.7.1 page-zero request, with only the mandated 1.8.0 substitution:
// the former inline animal_type/desc sort is now the allow-listed $sort
// variable. Exact document and variables comparison catches field nesting,
// argument binding, variable-type, filter, pagination, and ordering drift.
const legacy171DefaultQueryWithSortVariable =
	'query SearchAnimal($pagination: PaginationInfoInput!, $filters: AnimalSearchFiltersInput!, $sort: [SortInput!]!) {' +
	'  searchAnimal(pagination: $pagination, sort: $sort, filters: $filters) {' +
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
const legacy171RichDefaultQueryWithSortVariable =
	'query SearchAnimal($pagination: PaginationInfoInput!, $filters: AnimalSearchFiltersInput!, $sort: [SortInput!]!) {' +
	'  searchAnimal(pagination: $pagination, sort: $sort, filters: $filters) {' +
	'    totalCount' +
	'    animals {' +
	'      animalId' +
	'      animalName' +
	'      primaryPhotoId' +
	'      description' +
	'      physical { size { label } breed { primary mixed } age { label value } }' +
	'      _contact { address { city state } }' +
	'      publicUrl { url }' +
	'    }' +
	'  }' +
	'}';
const legacy171DefaultPayload = {
	query: legacy171DefaultQueryWithSortVariable,
	variables: {
		isConsumer: true,
		filters: {
			animal_type: [ 'cat' ],
			adoption_status: 'adoptable',
			organization_id: [ organizationId ]
		},
		pagination: { fromPage: 0, pageSize: 24 },
		sort: defaultSort
	}
};
const legacy171RichDefaultPayload = {
	query: legacy171RichDefaultQueryWithSortVariable,
	variables: legacy171DefaultPayload.variables
};

function assertDefaultRequest( call ) {
	assert.deepEqual( call.payload.variables.sort, defaultSort );
	assert.match( call.payload.query, /\$sort: \[SortInput!\]!/ );
	assert.match( call.payload.query, /sort: \$sort/ );
	assert.doesNotMatch( call.payload.query, /meta \{ publishTime \}/ );
	assert.doesNotMatch( call.payload.query, /publish_time/ );
}

function assertNewestRequest( call ) {
	assert.deepEqual( call.payload.variables.sort, newestSort );
	assert.match( call.payload.query, /\$sort: \[SortInput!\]!/ );
	assert.match( call.payload.query, /sort: \$sort/ );
	assert.match( call.payload.query, /meta \{ publishTime \}/ );
}

function restKeys( result ) {
	const get = result.calls.find( function ( call ) {
		return call.url.indexOf( restUrl + '?' ) === 0 && call.method === 'GET';
	} );
	const post = result.calls.find( function ( call ) {
		return call.url === restUrl && call.method === 'POST';
	} );
	assert.ok( get, 'shared-cache GET was not issued' );
	assert.ok( post && post.payload, 'shared-cache POST was not issued' );
	const getKey = new URL( get.url ).searchParams.get( 'key' );
	return { get: getKey, post: post.payload.key };
}

function assertEs5ProductionSyntax() {
	assert.doesNotMatch( runtimeSource, /^[\t ]*(?:let|const)\s+[A-Za-z_$]/m, 'production runtime must not use let/const declarations' );
	assert.doesNotMatch( runtimeSource, /=>/, 'production runtime must not use arrow functions' );
	assert.doesNotMatch( runtimeSource, /^[\t ]*class\s+[A-Za-z_$]/m, 'production runtime must not use class syntax' );
	assert.doesNotMatch( runtimeSource, /\?\.|\?\?/, 'production runtime must not use optional chaining or nullish coalescing' );
	new vm.Script( runtimeSource, { filename: runtimePath } );
}

async function testAllowListAndDefaultParity() {
	const sourceAnimals = animals( 'Default', 6 );
	const payloads = [];
	const cases = [ {}, { sort: 'default' }, { sort: 'publish_time DESC; drop table animals' } ];

	for ( const testCase of cases ) {
		const result = await runWidget( Object.assign( {}, testCase, {
			respond: function () { return searchResponse( sourceAnimals ); }
		} ) );
		const calls = graphCalls( result );
		assert.equal( calls.length, 1 );
		assertDefaultRequest( calls[ 0 ] );
		assert.deepEqual(
			calls[ 0 ].payload,
			legacy171DefaultPayload,
			'absent/default/invalid mode diverged from the frozen 1.7.1 request semantics'
		);
		payloads.push( calls[ 0 ].payload );
	}

	assert.deepEqual( payloads[ 0 ], payloads[ 1 ], 'absent and explicit default must emit the same request' );
	assert.deepEqual( payloads[ 0 ], payloads[ 2 ], 'invalid sort must emit the proven default request' );
	assert.doesNotMatch( JSON.stringify( payloads[ 2 ] ), /drop table/i, 'invalid shortcode text reached GraphQL' );

	const richResult = await runWidget( {
		showBios: true,
		sort: 'default',
		respond: function () { return searchResponse( sourceAnimals ); }
	} );
	const richCalls = graphCalls( richResult );
	assert.equal( richCalls.length, 1 );
	assert.deepEqual(
		richCalls[ 0 ].payload,
		legacy171RichDefaultPayload,
		'the shipped rich/default mode diverged from the frozen 1.7.1 request semantics'
	);
}

async function testNewestOrderingLimitAndRecordShape() {
	const sourceAnimals = animals( 'Newest', 6 );
	const result = await runWidget( {
		sort: 'newest',
		limit: 4,
		respond: function () { return searchResponse( sourceAnimals ); }
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 1 );
	assertNewestRequest( calls[ 0 ] );
	assert.equal( calls[ 0 ].payload.variables.pagination.pageSize, 24 );

	const cats = cachedCats( result );
	assert.deepEqual( cats.map( function ( cat ) { return cat.name; } ), [ 'Newest 01', 'Newest 02', 'Newest 03', 'Newest 04' ] );
	assert.match( result.grid.innerHTML, /Newest 01/ );
	assert.match( result.grid.innerHTML, /Newest 04/ );
	assert.doesNotMatch( result.grid.innerHTML, /Newest 05|Newest 06/ );
	assert.deepEqual(
		Object.keys( cats[ 0 ] ).sort(),
		[ 'age', 'bio', 'breed', 'city', 'id', 'name', 'photo', 'size', 'state', 'url' ],
		'normalized/cache record shape changed'
	);
	assert.doesNotMatch( JSON.stringify( cats ), /publishTime|"meta"/, 'publication metadata leaked into normalized/cache records' );
}

async function testVisitorAndSharedCacheSeparation() {
	async function run( sort ) {
		return runWidget( {
			sort: sort,
			serverCache: true,
			canWrite: true,
			respond: function () { return searchResponse( animals( sort === 'newest' ? 'Newest cache' : 'Default cache', 4 ) ); }
		} );
	}

	const defaultResult = await run( 'default' );
	const newestResult = await run( 'newest' );
	const invalidResult = await runWidget( {
		sort: 'not-a-real-sort',
		respond: function () { return searchResponse( animals( 'Invalid cache', 4 ) ); }
	} );
	const defaultKey = cacheKey( defaultResult );
	const newestKey = cacheKey( newestResult );
	const invalidKey = cacheKey( invalidResult );

	assert.notEqual( defaultKey, newestKey, 'visitor cache keys reused ordering modes' );
	assert.equal( invalidKey, defaultKey, 'invalid sort did not normalize to the default visitor cache key' );

	const defaultRest = restKeys( defaultResult );
	const newestRest = restKeys( newestResult );
	assert.equal( defaultRest.get, defaultKey );
	assert.equal( defaultRest.post, defaultKey );
	assert.equal( newestRest.get, newestKey );
	assert.equal( newestRest.post, newestKey );
	assert.notEqual( defaultRest.get, newestRest.get, 'shared REST cache GET keys reused ordering modes' );
	assert.notEqual( defaultRest.post, newestRest.post, 'shared REST cache POST keys reused ordering modes' );
}

async function testRichDescriptionDowngrade() {
	const result = await runWidget( {
		sort: 'default',
		showBios: true,
		respond: function ( call, index ) {
			if ( index === 0 ) {
				assert.match( call.payload.query, /description/ );
				return graphQLError( 'Cannot query field "description" on type "Animal".' );
			}
			return searchResponse( animals( 'Basic', 4 ) );
		}
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 2 );
	assertDefaultRequest( calls[ 0 ] );
	assertDefaultRequest( calls[ 1 ] );
	assert.match( calls[ 0 ].payload.query, /description/ );
	assert.doesNotMatch( calls[ 1 ].payload.query, /description/ );
	assert.equal( result.storage.getItem( 'pmrich:v1:' + apiBase ), 'no' );
	assert.match( result.grid.innerHTML, /Basic 01/ );
	assert.equal( result.errors.length, 0 );
}

async function testNewestSortFallback() {
	const result = await runWidget( {
		sort: 'newest',
		respond: function ( call, index ) {
			if ( index === 0 ) {
				return graphQLError( 'Sort field publish_time is not supported.' );
			}
			return searchResponse( animals( 'Fallback default', 4 ) );
		}
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 2 );
	assertNewestRequest( calls[ 0 ] );
	assertDefaultRequest( calls[ 1 ] );
	assert.match( result.grid.innerHTML, /Fallback default 01/ );
	assert.doesNotMatch( result.grid.innerHTML, /newest/i, 'fallback result was labeled newest' );
	assert.equal( result.errors.length, 0 );
}

async function testPublicationMetadataFallbackKeepsRichProbe() {
	const result = await runWidget( {
		sort: 'newest',
		showBios: true,
		respond: function ( call, index ) {
			if ( index === 0 ) {
				assert.match( call.payload.query, /description/ );
				return graphQLError( 'Cannot query field "publishTime" on type "AnimalMeta". Did you mean "description"?' );
			}
			return searchResponse( animals( 'Metadata fallback', 4 ) );
		}
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 2, 'metadata rejection should immediately retry the default query' );
	assertNewestRequest( calls[ 0 ] );
	assertDefaultRequest( calls[ 1 ] );
	assert.match( calls[ 1 ].payload.query, /description/, 'metadata rejection incorrectly disabled rich descriptions' );
	assert.equal( result.storage.getItem( 'pmrich:v1:' + apiBase ), 'yes' );
	assert.match( result.grid.innerHTML, /Metadata fallback 01/ );
	assert.equal( result.errors.length, 0 );
}

async function testCombinedRichAndNewestFallbacks() {
	const result = await runWidget( {
		sort: 'newest',
		showBios: true,
		respond: function ( call, index ) {
			if ( index === 0 ) {
				return graphQLError( 'Cannot query field "description" on type "Animal".' );
			}
			if ( index === 1 ) {
				return graphQLError( 'Sort field publish_time is not supported.' );
			}
			return searchResponse( animals( 'Combined fallback', 4 ) );
		}
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 3 );
	assertNewestRequest( calls[ 0 ] );
	assertNewestRequest( calls[ 1 ] );
	assert.match( calls[ 0 ].payload.query, /description/ );
	assert.doesNotMatch( calls[ 1 ].payload.query, /description/ );
	assertDefaultRequest( calls[ 2 ] );
	assert.doesNotMatch( calls[ 2 ].payload.query, /description/ );
	assert.equal( result.storage.getItem( 'pmrich:v1:' + apiBase ), 'no' );
	assert.match( result.grid.innerHTML, /Combined fallback 01/ );
	assert.equal( result.errors.length, 0 );
}

async function testLaterNewestPageRestartsDefaultOrdering() {
	const newestAnimals = animals( 'Newest page', 30 );
	const defaultAnimals = animals( 'Default restart', 30 );
	const result = await runWidget( {
		sort: 'newest',
		limit: 30,
		respond: function ( call ) {
			const isNewest = call.payload.variables.sort[ 0 ].field === 'publish_time';
			const fromPage = call.payload.variables.pagination.fromPage;
			if ( isNewest && fromPage === 0 ) {
				return searchResponse( newestAnimals.slice( 0, 24 ), 30 );
			}
			if ( isNewest ) {
				return graphQLError( 'Newest pagination is temporarily unavailable.' );
			}
			return searchResponse( defaultAnimals.slice( fromPage * 24, ( fromPage + 1 ) * 24 ), 30 );
		}
	} );
	const calls = graphCalls( result );
	assert.equal( calls.length, 4 );
	assertNewestRequest( calls[ 0 ] );
	assertNewestRequest( calls[ 1 ] );
	assertDefaultRequest( calls[ 2 ] );
	assertDefaultRequest( calls[ 3 ] );
	const cats = cachedCats( result );
	assert.equal( cats.length, 30 );
	assert.equal( cats[ 0 ].name, 'Default restart 01' );
	assert.equal( cats[ 29 ].name, 'Default restart 30' );
	assert.doesNotMatch( JSON.stringify( cats ), /Newest page/, 'partial newest results survived the fallback restart' );
	assert.equal( result.errors.length, 0 );
}

function actionTarget( action ) {
	const target = element();
	target.setAttribute( 'data-pm-action', action );
	target.closest = function ( selector ) {
		return selector === '[data-pm-action]' ? target : null;
	};
	return target;
}

function testStoryKeyboardBehavior( result ) {
	const cardElement = element();
	const opener = element();
	const story = element();
	const close = element();
	const openButton = element();
	let prevented = false;

	cardElement.querySelector = function ( selector ) {
		if ( selector === '[data-pm-flip="open"]' ) { return opener; }
		if ( selector === '[data-pm-story]' ) { return story; }
		if ( selector === '.pm-flip-close' ) { return close; }
		return null;
	};
	openButton.closest = function ( selector ) {
		if ( selector === '[data-pm-flip]' ) { return openButton; }
		if ( selector === '.pm-card' ) { return cardElement; }
		return null;
	};

	result.grid.dispatch( 'click', {
		preventDefault: function () { prevented = true; },
		target: openButton
	} );
	assert.equal( prevented, true, 'story control did not prevent its delegated default action' );
	assert.equal( cardElement.classList.contains( 'is-flipped' ), true, 'story did not open' );
	assert.equal( opener.getAttribute( 'aria-expanded' ), 'true', 'story opener state was not announced' );
	assert.equal( story.getAttribute( 'aria-hidden' ), 'false', 'story region stayed hidden' );
	assert.equal( close.focused, true, 'opening a story did not move focus to its close control' );

	const escapeTarget = {
		closest: function ( selector ) {
			return selector === '.pm-card.is-flipped' ? cardElement : null;
		}
	};
	result.grid.dispatch( 'keydown', { key: 'Escape', target: escapeTarget } );
	assert.equal( cardElement.classList.contains( 'is-flipped' ), false, 'Escape did not close the story' );
	assert.equal( opener.getAttribute( 'aria-expanded' ), 'false', 'Escape did not reset story opener state' );
	assert.equal( story.getAttribute( 'aria-hidden' ), 'true', 'Escape did not hide the story region' );
	assert.equal( opener.focused, true, 'Escape did not restore focus to the story opener' );
}

function assertMotionAndFocusCssContracts() {
	const reduced = cssSource.match( /@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{([\s\S]*?)\n\}/ );
	assert.ok( reduced, 'reduced-motion media query is missing' );
	assert.match( reduced[ 1 ], /\.pm-wrap \*/, 'reduced-motion rules are not scoped to the widget' );
	assert.match( reduced[ 1 ], /animation-duration:\s*\.001ms\s*!important/, 'reduced motion does not neutralize animations' );
	assert.match( reduced[ 1 ], /animation-iteration-count:\s*1\s*!important/, 'reduced motion does not cap animation iterations' );
	assert.match( reduced[ 1 ], /transition-duration:\s*\.001ms\s*!important/, 'reduced motion does not neutralize transitions' );
	assert.match( cssSource, /\.pm-wrap :is\(a, button, select\):focus-visible\s*\{[\s\S]*?outline:\s*2px solid var\(--pm-focus\)\s*!important/, 'keyboard focus outline contract changed' );
}

async function testExistingUiBehaviorContracts() {
	const loaded = animals( 'Legacy', 2 );
	loaded[ 1 ].physical.breed.primary = 'Siamese';
	const result = await runWidget( {
		adoptionFormUrl: 'https://cjpaws.org/adopt/apply/?source=widget',
		limit: 4,
		seo: true,
		showBios: true,
		respond: function () { return searchResponse( loaded ); }
	} );

	assert.equal( result.initial.busy, 'true', 'loading did not mark the results busy' );
	assert.equal( result.initial.role, 'list', 'loading skeletons lost list semantics' );
	assert.equal( ( result.initial.gridHtml.match( /class="pm-skel"/g ) || [] ).length, 4, 'loading did not render the configured four skeletons' );
	assert.equal( ( result.initial.gridHtml.match( /role="listitem"/g ) || [] ).length, 4, 'loading skeletons lost listitem semantics' );
	assert.match( result.initial.countHtml, /pm-loading/, 'loading status was not rendered' );
	assert.match( result.grid.innerHTML, /Legacy 01/ );
	assert.match( result.grid.innerHTML, /data-pm-flip="open"[^>]*aria-expanded="false"/, 'story opener markup changed' );
	assert.match( result.grid.innerHTML, /data-pm-story role="region"[^>]*aria-hidden="true"/, 'story region semantics changed' );
	assert.match(
		result.grid.innerHTML,
		/href="https:\/\/cjpaws\.org\/adopt\/apply\/\?source=widget&amp;pet=Legacy%2001&amp;pet_id=legacy-01"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/,
		'adoption application link no longer carries the pet name/id safely'
	);
	assert.match( result.grid.innerHTML, /class="pm-cta-view"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/, 'Petfinder profile link safety contract changed' );

	const schema = result.root.children.find( function ( child ) { return child.className === 'pm-schema'; } );
	assert.ok( schema, 'schema output was not injected' );
	assert.equal( schema.type, 'application/ld+json' );
	const schemaData = JSON.parse( schema.textContent );
	assert.equal( schemaData[ '@context' ], 'https://schema.org' );
	assert.equal( schemaData[ '@type' ], 'ItemList' );
	assert.deepEqual(
		schemaData.itemListElement.map( function ( item ) { return [ item.position, item.name ]; } ),
		[ [ 1, 'Legacy 01' ], [ 2, 'Legacy 02' ] ],
		'schema list no longer reflects the complete loaded order'
	);

	assert.deepEqual(
		result.controls.breed.options.map( function ( option ) { return option.value; } ),
		[ 'all', 'Domestic Short Hair', 'Siamese' ],
		'filter options were not hydrated from the complete loaded result'
	);
	result.controls.breed.value = 'Siamese';
	result.controls.breed.dispatch( 'change', { target: result.controls.breed } );
	assert.doesNotMatch( result.grid.innerHTML, /Legacy 01/, 'breed filter retained a nonmatching pet' );
	assert.match( result.grid.innerHTML, /Legacy 02/, 'breed filter removed its matching pet' );
	assert.match( result.chips.innerHTML, /Breed: Siamese/, 'active filter chip was not rendered' );
	assert.match( result.count.innerHTML, /Showing 1 of 1/, 'filtered result count changed' );

	const clear = actionTarget( 'clear' );
	result.root.dispatch( 'click', { target: clear } );
	assert.match( result.grid.innerHTML, /Legacy 01/ );
	assert.match( result.grid.innerHTML, /Legacy 02/ );
	assert.equal( result.controls.breed.value, 'all', 'clear did not reset the breed select' );
	testStoryKeyboardBehavior( result );

	const emptyResult = await runWidget( {
		respond: function () { return searchResponse( [] ); }
	} );
	assert.equal( emptyResult.grid.getAttribute( 'role' ), 'region' );
	assert.match( emptyResult.grid.innerHTML, /No adoptable pets right now/, 'empty state copy changed' );
	assert.equal( emptyResult.status.textContent, 'No adoptable pets right now', 'empty state was not announced' );

	let attempts = 0;
	const retryResult = await runWidget( {
		adoptapetUrl: 'https://www.adoptapet.com/shelter/example',
		petfinderMemberUrl: 'https://www.petfinder.com/member/us/fl/example/',
		respond: function () {
			attempts += 1;
			return attempts === 1 ? graphQLError( 'Temporary upstream failure.' ) : searchResponse( animals( 'Retry', 1 ) );
		}
	} );
	assert.match( retryResult.grid.innerHTML, /live listings are taking a cat nap/, 'fallback error state changed' );
	assert.match( retryResult.grid.innerHTML, /View on Adopt-a-Pet/ );
	assert.match( retryResult.grid.innerHTML, /View on Petfinder/ );
	assert.match( retryResult.grid.innerHTML, /data-pm-action="retry"/, 'retry control is missing' );
	assert.equal( retryResult.errors.length, 1, 'the upstream error was not recorded exactly once' );
	retryResult.root.dispatch( 'click', { target: actionTarget( 'retry' ) } );
	await waitFor(
		function () { return /Retry 01/.test( retryResult.grid.innerHTML ) && retryResult.grid.getAttribute( 'aria-busy' ) === 'false'; },
		'retry did not recover to a populated listing'
	);
	assert.equal( attempts, 2, 'retry did not issue exactly one new search' );
	assert.match( retryResult.grid.innerHTML, /Retry 01/, 'retry result did not render' );

	assertMotionAndFocusCssContracts();
}

async function main() {
	assertEs5ProductionSyntax();
	await testAllowListAndDefaultParity();
	await testNewestOrderingLimitAndRecordShape();
	await testVisitorAndSharedCacheSeparation();
	await testRichDescriptionDowngrade();
	await testNewestSortFallback();
	await testPublicationMetadataFallbackKeepsRichProbe();
	await testCombinedRichAndNewestFallbacks();
	await testLaterNewestPageRestartsDefaultOrdering();
	await testExistingUiBehaviorContracts();
	console.log( 'JAVASCRIPT SORT CONTRACT VERIFICATION PASSED' );
}

main().catch( function ( error ) {
	console.error( error && error.stack ? error.stack : error );
	process.exitCode = 1;
} );
