import { cfg } from './util';

let loader = null;

/**
 * Loads the map adapter once (Leaflet + OpenStreetMap by default, Google Maps with a key).
 * The Leaflet bundle is a separate chunk, so pages without maps never download it.
 *
 * @return {Promise<Object>} Adapter { create( el, opts ) }.
 */
export function loadMaps() {
	if ( ! loader ) {
		const map = cfg().map || {};
		loader = map.provider === 'google' && map.key ? import( /* webpackChunkName: "map-google" */ './map-google' ).then( ( m ) => m.default( map.key ) ) : import( /* webpackChunkName: "map-leaflet" */ './map-leaflet' ).then( ( m ) => m.default );
	}
	return loader;
}

/**
 * Renders a map into an element from its data-markers attribute.
 *
 * @param {HTMLElement} el   Map element.
 * @param {Object}      opts { markers, onMarker, zoom }.
 * @return {Promise<Object>} Map handle { setMarkers, invalidate, highlight }.
 */
export function mountMap( el, opts = {} ) {
	if ( el.__dev3reMap ) {
		return Promise.resolve( el.__dev3reMap );
	}
	const conf = cfg().map || {};
	let markers = opts.markers;
	if ( ! markers ) {
		try {
			markers = JSON.parse( el.getAttribute( 'data-markers' ) || '[]' );
		} catch ( e ) {
			markers = [];
		}
	}
	return loadMaps().then( ( adapter ) => {
		const handle = adapter.create( el, {
			lat: conf.lat || 0,
			lng: conf.lng || 0,
			zoom: Number( el.getAttribute( 'data-zoom' ) ) || conf.zoom || 12,
			onMarker: opts.onMarker,
		} );
		handle.setMarkers( markers, true );
		el.__dev3reMap = handle;
		return handle;
	} );
}

/**
 * Lazily mounts standalone maps (single listing, map block) when they scroll into view.
 *
 * @param {ParentNode} root Root.
 */
export function initStandaloneMaps( root ) {
	const els = Array.from( root.querySelectorAll( '[data-dev3re-map]' ) ).filter( ( el ) => ! el.closest( '[data-dev3re-search]' ) );
	if ( ! els.length ) {
		return;
	}
	if ( ! ( 'IntersectionObserver' in window ) ) {
		els.forEach( ( el ) => mountMap( el ) );
		return;
	}
	const io = new window.IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					io.unobserve( entry.target );
					mountMap( entry.target );
				}
			} );
		},
		{ rootMargin: '200px' }
	);
	els.forEach( ( el ) => io.observe( el ) );
}
