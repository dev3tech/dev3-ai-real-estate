import { createContext, useContext, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Badge } from '../core3/ui';
import { loadMaps } from '../frontend/maps';

/** Shared admin data (meta vocabulary + settings) for every screen. */
export const AppContext = createContext( { meta: null, settings: null, reloadMeta: () => {}, save: () => Promise.resolve() } );
export const useApp = () => useContext( AppContext );

export const STATUS = {
	publish: { label: __( 'Live', 'dev3-ai-real-estate' ), tone: 'success' },
	pending: { label: __( 'In review', 'dev3-ai-real-estate' ), tone: 'warning' },
	draft: { label: __( 'Draft', 'dev3-ai-real-estate' ), tone: 'neutral' },
	private: { label: __( 'Private', 'dev3-ai-real-estate' ), tone: 'info' },
};

export const MARKET = {
	available: __( 'Available', 'dev3-ai-real-estate' ),
	under_offer: __( 'Under offer', 'dev3-ai-real-estate' ),
	sold: __( 'Sold', 'dev3-ai-real-estate' ),
	rented: __( 'Rented', 'dev3-ai-real-estate' ),
};

export const LEAD_STATUS = {
	new: { label: __( 'New', 'dev3-ai-real-estate' ), tone: 'brand' },
	contacted: { label: __( 'Contacted', 'dev3-ai-real-estate' ), tone: 'info' },
	viewing: { label: __( 'Viewing booked', 'dev3-ai-real-estate' ), tone: 'warning' },
	closed: { label: __( 'Closed', 'dev3-ai-real-estate' ), tone: 'success' },
	spam: { label: __( 'Spam', 'dev3-ai-real-estate' ), tone: 'danger' },
};

export function StatusBadge( { status } ) {
	const s = STATUS[ status ] || { label: status, tone: 'neutral' };
	return (
		<Badge tone={ s.tone } dot>
			{ s.label }
		</Badge>
	);
}

export function LeadBadge( { status } ) {
	const s = LEAD_STATUS[ status ] || { label: status, tone: 'neutral' };
	return (
		<Badge tone={ s.tone } dot>
			{ s.label }
		</Badge>
	);
}

/**
 * Localised date.
 *
 * @param {string}  value    MySQL GMT date or ISO.
 * @param {boolean} withTime Include time.
 * @return {string} Date.
 */
export function formatDate( value, withTime = false ) {
	if ( ! value ) {
		return '—';
	}
	const iso = /T/.test( value ) ? value : String( value ).replace( ' ', 'T' ) + 'Z';
	const d = new Date( iso );
	if ( isNaN( d.getTime() ) ) {
		return value;
	}
	return withTime ? d.toLocaleString( undefined, { dateStyle: 'medium', timeStyle: 'short' } ) : d.toLocaleDateString( undefined, { dateStyle: 'medium' } );
}

/**
 * HTML paragraphs → plain text with blank lines.
 *
 * @param {string} html HTML.
 * @return {string} Text.
 */
export function htmlToText( html ) {
	return String( html || '' )
		.replace( /<\/p>\s*<p[^>]*>/gi, '\n\n' )
		.replace( /<br\s*\/?>/gi, '\n' )
		.replace( /<[^>]+>/g, '' )
		.replace( /&amp;/g, '&' )
		.replace( /&#0?39;|&#8217;/g, "'" )
		.replace( /&quot;/g, '"' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.trim();
}

/**
 * Plain text → paragraphs.
 *
 * @param {string} text Text.
 * @return {string} HTML.
 */
export function textToHtml( text ) {
	const esc = ( s ) => s.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	return String( text || '' )
		.split( /\n\s*\n/ )
		.map( ( p ) => p.trim() )
		.filter( Boolean )
		.map( ( p ) => `<p>${ esc( p ).replace( /\n/g, '<br>' ) }</p>` )
		.join( '\n' );
}

/**
 * Opens the WordPress media library.
 *
 * @param {Object}   opts          Options.
 * @param {boolean}  opts.multiple Multiple.
 * @param {string}   opts.title    Title.
 * @param {Function} opts.onSelect ( [ { id, url } ] ) => void.
 */
export function openMedia( { multiple = false, title, onSelect } ) {
	if ( ! window.wp || ! window.wp.media ) {
		return;
	}
	const frame = window.wp.media( { title, multiple, library: { type: 'image' } } );
	frame.on( 'select', () => {
		const items = frame
			.state()
			.get( 'selection' )
			.toJSON()
			.map( ( a ) => ( { id: a.id, url: ( a.sizes && a.sizes.medium && a.sizes.medium.url ) || ( a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url ) || a.url } ) );
		onSelect( items );
	} );
	frame.open();
}

/**
 * Map with a draggable pin (Leaflet/OSM or Google, same adapters as the front end).
 *
 * @param {Object}   props          Props.
 * @param {number}   props.lat      Lat.
 * @param {number}   props.lng      Lng.
 * @param {Function} props.onChange ( lat, lng ) => void.
 * @param {string}   props.label    Accessible label.
 * @return {Element} Map.
 */
export function MapPicker( { lat, lng, onChange, label } ) {
	const ref = useRef( null );
	const pin = useRef( null );
	const cb = useRef( onChange );
	cb.current = onChange;
	useEffect( () => {
		let alive = true;
		const conf = ( window.dev3reConfig && window.dev3reConfig.map ) || {};
		loadMaps()
			.then( ( adapter ) => {
				if ( ! alive || ! ref.current ) {
					return;
				}
				const map = adapter.create( ref.current, { lat: conf.lat || 0, lng: conf.lng || 0, zoom: conf.zoom || 12 } );
				pin.current = map.picker( lat !== '' && lat !== null && lat !== undefined ? Number( lat ) : conf.lat, lng !== '' && lng !== null && lng !== undefined ? Number( lng ) : conf.lng, ( a, b ) => cb.current( a, b ) );
			} )
			.catch( () => {} );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );
	useEffect( () => {
		if ( pin.current && lat !== '' && lng !== '' && lat !== null && lng !== null && lat !== undefined ) {
			pin.current.move( Number( lat ), Number( lng ) );
		}
	}, [ lat, lng ] );
	return <div ref={ ref } className="dev3re-admin-map" role="application" aria-label={ label } />;
}
