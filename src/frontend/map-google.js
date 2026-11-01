import { popupHtml } from './util';

/**
 * Loads the Google Maps JavaScript API with the site's browser key, then returns the adapter.
 *
 * @param {string} key Maps key.
 * @return {Promise<Object>} Adapter.
 */
export default function google( key ) {
	return new Promise( ( resolve, reject ) => {
		if ( window.google && window.google.maps ) {
			resolve( adapter() );
			return;
		}
		window.__dev3reGmaps = () => resolve( adapter() );
		const s = document.createElement( 'script' );
		s.src = `https://maps.googleapis.com/maps/api/js?key=${ encodeURIComponent( key ) }&callback=__dev3reGmaps&loading=async`;
		s.async = true;
		s.onerror = reject;
		document.head.appendChild( s );
	} );
}

function adapter() {
	const g = window.google.maps;
	return {
		create( el, opts ) {
			const map = new g.Map( el, { center: { lat: opts.lat, lng: opts.lng }, zoom: opts.zoom, mapTypeControl: false, streetViewControl: false, gestureHandling: 'cooperative' } );
			const info = new g.InfoWindow();
			let markers = [];
			const byId = {};
			return {
				setMarkers( list, fit ) {
					markers.forEach( ( m ) => m.setMap( null ) );
					markers = [];
					const bounds = new g.LatLngBounds();
					list.forEach( ( m ) => {
						const mk = new g.Marker( { position: { lat: m.lat, lng: m.lng }, map, title: m.title, label: m.price ? { text: m.price, fontSize: '11px', fontWeight: '700' } : undefined } );
						mk.addListener( 'click', () => {
							info.setContent( popupHtml( m ) );
							info.open( { anchor: mk, map } );
							if ( opts.onMarker ) {
								opts.onMarker( m.id );
							}
						} );
						markers.push( mk );
						byId[ m.id ] = mk;
						bounds.extend( { lat: m.lat, lng: m.lng } );
					} );
					if ( fit && list.length > 1 ) {
						map.fitBounds( bounds, 32 );
					} else if ( fit && list.length === 1 ) {
						map.setCenter( { lat: list[ 0 ].lat, lng: list[ 0 ].lng } );
						map.setZoom( opts.zoom || 15 );
					}
				},
				invalidate() {
					g.event.trigger( map, 'resize' );
				},
				highlight( id, on ) {
					if ( byId[ id ] ) {
						byId[ id ].setZIndex( on === false ? 0 : 1000 );
					}
				},
				onMoveEnd( cb ) {
					map.addListener( 'idle', () => {
						const b = map.getBounds();
						if ( b ) {
							const sw = b.getSouthWest();
							const ne = b.getNorthEast();
							cb( [ sw.lat(), sw.lng(), ne.lat(), ne.lng() ].map( ( v ) => v.toFixed( 5 ) ).join( ',' ) );
						}
					} );
				},
				picker( lat, lng, onMove ) {
					markers.forEach( ( m ) => m.setMap( null ) );
					const mk = new g.Marker( { position: { lat, lng }, map, draggable: true } );
					mk.addListener( 'dragend', () => onMove( mk.getPosition().lat(), mk.getPosition().lng() ) );
					map.addListener( 'click', ( e ) => {
						mk.setPosition( e.latLng );
						onMove( e.latLng.lat(), e.latLng.lng() );
					} );
					map.setCenter( { lat, lng } );
					map.setZoom( 16 );
					return {
						move( la, ln ) {
							mk.setPosition( { lat: la, lng: ln } );
							map.setCenter( { lat: la, lng: ln } );
						},
					};
				},
			};
		},
	};
}
