import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { esc, popupHtml } from './util';

/**
 * Price-tag marker (pure CSS, no image files).
 *
 * @param {Object} m Marker.
 * @return {Object} Icon.
 */
const tag = ( m ) =>
	L.divIcon( {
		className: 'dev3re-pricepin' + ( m.featured ? ' is-featured' : '' ),
		html: `<span>${ esc( m.price || '•' ) }</span>`,
		iconSize: null,
		iconAnchor: [ 0, 0 ],
		popupAnchor: [ 0, -18 ],
	} );

const dot = () =>
	L.divIcon( {
		className: 'dev3re-pin',
		html: '<span></span>',
		iconSize: [ 28, 28 ],
		iconAnchor: [ 14, 28 ],
		popupAnchor: [ 0, -26 ],
	} );

/**
 * Leaflet + OpenStreetMap adapter.
 */
export default {
	create( el, opts ) {
		const map = L.map( el, { scrollWheelZoom: false, tap: true } ).setView( [ opts.lat, opts.lng ], opts.zoom );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		} ).addTo( map );
		map.on( 'focus', () => map.scrollWheelZoom.enable() );
		map.on( 'blur', () => map.scrollWheelZoom.disable() );
		let layer = L.layerGroup().addTo( map );
		let circle = null;
		const byId = {};
		return {
			setMarkers( markers, fit ) {
				layer.clearLayers();
				if ( circle ) {
					circle.remove();
					circle = null;
				}
				const bounds = [];
				markers.forEach( ( m ) => {
					if ( m.approx ) {
						circle = L.circle( [ m.lat, m.lng ], { radius: 350, color: 'var(--dev3re-accent, #0f766e)', weight: 2, fillOpacity: 0.15 } ).addTo( map );
						bounds.push( [ m.lat, m.lng ] );
						return;
					}
					const mk = L.marker( [ m.lat, m.lng ], { icon: m.url ? tag( m ) : dot(), title: m.title, keyboard: true, alt: m.title, riseOnHover: true } );
					mk.bindPopup( popupHtml( m ), { minWidth: 200, maxWidth: 240, className: 'dev3re-leaflet-popup' } );
					mk.on( 'click', () => opts.onMarker && opts.onMarker( m.id ) );
					mk.addTo( layer );
					byId[ m.id ] = mk;
					bounds.push( [ m.lat, m.lng ] );
				} );
				if ( fit && bounds.length ) {
					if ( bounds.length === 1 ) {
						map.setView( bounds[ 0 ], opts.zoom || 15 );
					} else {
						map.fitBounds( bounds, { padding: [ 40, 40 ], maxZoom: 15 } );
					}
				}
			},
			invalidate() {
				map.invalidateSize();
			},
			highlight( id, on ) {
				const mk = byId[ id ];
				if ( mk && mk._icon ) {
					mk._icon.classList.toggle( 'is-active', on !== false );
					mk.setZIndexOffset( on === false ? 0 : 1000 );
				}
			},
			onMoveEnd( cb ) {
				map.on( 'moveend', () => {
					const b = map.getBounds();
					cb( [ b.getSouth(), b.getWest(), b.getNorth(), b.getEast() ].map( ( v ) => v.toFixed( 5 ) ).join( ',' ) );
				} );
			},
			picker( lat, lng, onMove ) {
				layer.clearLayers();
				layer = L.layerGroup().addTo( map );
				const mk = L.marker( [ lat, lng ], { draggable: true, icon: dot(), keyboard: true, alt: 'Pin' } ).addTo( layer );
				mk.on( 'dragend', () => {
					const p = mk.getLatLng();
					onMove( p.lat, p.lng );
				} );
				map.on( 'click', ( e ) => {
					mk.setLatLng( e.latlng );
					onMove( e.latlng.lat, e.latlng.lng );
				} );
				map.setView( [ lat, lng ], 16 );
				return {
					move( la, ln ) {
						mk.setLatLng( [ la, ln ] );
						map.setView( [ la, ln ], 16 );
					},
				};
			},
		};
	},
};
