/**
 * Small front-end helpers (no dependencies).
 */
export const cfg = () => window.dev3reConfig || {};
export const t = ( key ) => ( cfg().i18n && cfg().i18n[ key ] ) || '';

/**
 * REST URL that works with plain and pretty permalinks.
 *
 * @param {string} path Route.
 * @return {string} URL.
 */
export function rest( path ) {
	const base = cfg().restUrl || '/wp-json/dev3re/v1/';
	const [ route, query ] = String( path ).replace( /^\//, '' ).split( /\?(.*)/s );
	const url = base + route;
	return query ? url + ( url.includes( '?' ) ? '&' : '?' ) + query : url;
}

/**
 * fetch() wrapper returning JSON or throwing { message }.
 *
 * @param {string} path    Route.
 * @param {Object} options fetch options (+ json body via `data`).
 * @return {Promise<Object>} JSON.
 */
export function api( path, options = {} ) {
	const opts = { credentials: 'same-origin', method: options.method || 'GET', headers: {} };
	if ( cfg().nonce ) {
		opts.headers[ 'X-WP-Nonce' ] = cfg().nonce;
	}
	if ( options.data ) {
		opts.headers[ 'Content-Type' ] = 'application/json';
		opts.body = JSON.stringify( options.data );
	} else if ( options.body ) {
		opts.body = options.body;
	}
	if ( options.signal ) {
		opts.signal = options.signal;
	}
	return window.fetch( rest( path ), opts ).then( ( res ) =>
		res
			.json()
			.catch( () => ( {} ) )
			.then( ( json ) => {
				if ( ! res.ok ) {
					const err = new Error( ( json && json.message ) || t( 'error' ) );
					err.code = json && json.code;
					err.status = res.status;
					throw err;
				}
				return json;
			} )
	);
}

/**
 * Object → URLSearchParams (handles f[key] and arrays).
 *
 * @param {Object} obj Args.
 * @return {URLSearchParams} Params.
 */
export function toParams( obj ) {
	const p = new URLSearchParams();
	const add = ( key, value ) => {
		if ( value === null || value === undefined || value === '' || value === false ) {
			return;
		}
		if ( Array.isArray( value ) ) {
			value.forEach( ( v ) => add( key + '[]', v ) );
		} else if ( typeof value === 'object' ) {
			Object.keys( value ).forEach( ( k ) => add( `${ key }[${ k }]`, value[ k ] ) );
		} else {
			p.append( key, value === true ? '1' : String( value ) );
		}
	};
	Object.keys( obj ).forEach( ( k ) => add( k, obj[ k ] ) );
	return p;
}

/**
 * FormData → args object (f[key], f[key][] and category[] aware).
 *
 * @param {HTMLFormElement} form Form.
 * @return {Object} Args.
 */
export function formArgs( form ) {
	const out = { f: {} };
	new window.FormData( form ).forEach( ( value, name ) => {
		if ( value === '' ) {
			return;
		}
		const m = name.match( /^f\[([^\]]+)\](\[\])?$/ );
		if ( m ) {
			if ( m[ 2 ] ) {
				out.f[ m[ 1 ] ] = ( out.f[ m[ 1 ] ] || [] ).concat( value );
			} else {
				out.f[ m[ 1 ] ] = value;
			}
			return;
		}
		out[ name.replace( /\[\]$/, '' ) ] = value;
	} );
	return out;
}

export const ready = ( fn ) => ( document.readyState === 'loading' ? document.addEventListener( 'DOMContentLoaded', fn ) : fn() );

/**
 * Escapes text for innerHTML.
 *
 * @param {string} s Text.
 * @return {string} Safe.
 */
export const esc = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

/**
 * Browser location.
 *
 * @return {Promise<{lat:number,lng:number}>} Position.
 */
export function locate() {
	return new Promise( ( resolve, reject ) => {
		if ( ! navigator.geolocation ) {
			reject( new Error( t( 'locateFailed' ) ) );
			return;
		}
		navigator.geolocation.getCurrentPosition( ( pos ) => resolve( { lat: pos.coords.latitude, lng: pos.coords.longitude } ), () => reject( new Error( t( 'locateFailed' ) ) ), { timeout: 10000, maximumAge: 300000 } );
	} );
}

/**
 * Formats money like the server (symbol position + thousands separator).
 *
 * @param {number} amount Amount.
 * @param {Object} fmt    { symbol, position, sep }.
 * @return {string} Money.
 */
export function money( amount, fmt = {} ) {
	const sep = fmt.sep === undefined ? ',' : fmt.sep;
	const n = Math.round( Number( amount ) || 0 );
	const num = String( Math.abs( n ) ).replace( /\B(?=(\d{3})+(?!\d))/g, sep );
	const sign = n < 0 ? '-' : '';
	const sym = fmt.symbol === undefined ? '$' : fmt.symbol;
	switch ( fmt.position ) {
		case 'after':
			return sign + num + sym;
		case 'after_space':
			return sign + num + ' ' + sym;
		case 'before_space':
			return sign + sym + ' ' + num;
		default:
			return sign + sym + num;
	}
}

/**
 * Safe localStorage JSON read/write.
 *
 * @param {string} key      Key.
 * @param {*}      fallback Fallback.
 * @return {*} Value.
 */
export function load( key, fallback ) {
	try {
		const v = window.localStorage.getItem( key );
		return v ? JSON.parse( v ) : fallback;
	} catch ( e ) {
		return fallback;
	}
}

export function store( key, value ) {
	try {
		window.localStorage.setItem( key, JSON.stringify( value ) );
	} catch ( e ) {}
}

/**
 * Small toast for front-end feedback.
 *
 * @param {string} message Message.
 */
export function toast( message ) {
	let el = document.querySelector( '.dev3re-toast' );
	if ( ! el ) {
		el = document.createElement( 'div' );
		el.className = 'dev3re dev3re-toast';
		el.setAttribute( 'role', 'status' );
		el.setAttribute( 'aria-live', 'polite' );
		document.body.appendChild( el );
	}
	el.textContent = message;
	el.classList.add( 'is-on' );
	clearTimeout( el.__t );
	el.__t = setTimeout( () => el.classList.remove( 'is-on' ), 2600 );
}

/**
 * Map popup markup (shared by both map adapters).
 *
 * @param {Object} m Marker { url, title, label, image, facts }.
 * @return {string} HTML.
 */
export function popupHtml( m ) {
	if ( ! m.url ) {
		return `<strong>${ esc( m.title || '' ) }</strong>${ m.approx ? `<br><small>${ esc( t( 'approx' ) ) }</small>` : '' }`;
	}
	return `<a class="dev3re-popup" href="${ esc( m.url ) }">${ m.image ? `<img src="${ esc( m.image ) }" alt="" width="220" height="130" loading="lazy">` : '' }<span class="dev3re-popup__price">${ esc( m.label || m.price || '' ) }</span><span class="dev3re-popup__title">${ esc( m.title ) }</span>${ m.facts ? `<span class="dev3re-popup__facts">${ esc( m.facts ) }</span>` : '' }</a>`;
}
