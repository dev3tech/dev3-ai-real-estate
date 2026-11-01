import apiFetch from '@wordpress/api-fetch';
import { AppContext, htmlToText, textToHtml } from '../../src/admin/shared';
import Properties from '../../src/admin/Properties';
import Leads from '../../src/admin/Leads';
import Searches from '../../src/admin/Searches';
import Dashboard from '../../src/admin/Dashboard';
import SettingsTab from '../../src/admin/SettingsTab';
import { ToastProvider } from '../../src/core3/ui';
import { change, click, render } from './render';

const META = {
	types: [ { id: 3, name: 'House', slug: 'house', parent: 0, count: 2 } ],
	locations: [ { id: 9, name: 'Springfield', slug: 'springfield', parent: 0, count: 2 } ],
	features: [ { id: 4, name: 'Garden', slug: 'garden', parent: 0, count: 1 } ],
	agents: [ { value: 21, label: 'Maya Chen' } ],
	agencies: [],
	pages: [ { value: 5, label: 'Property Search' } ],
	unit: 'ft²',
	currency: 'USD',
	ai: { enabled: true, key_set: false },
};
const SETTINGS = { api_key_set: false, currency: 'USD', currency_symbol: '$', currency_position: 'before', thousands_sep: ',', area_unit: 'sqft', per_page: 12, hide_sold: true, map_provider: 'osm', google_maps_key: '', map_center_lat: 39.78, map_center_lng: -89.65, map_zoom: 12, page_search: 0, page_submit: 0, page_dashboard: 0, page_favourites: 0, page_compare: 0, mortgage_enabled: true, mortgage_rate: 6.5, mortgage_term: 30, mortgage_down: 20, ai_tone: 'warm', accent_color: '#0f766e' };

const wrap = ( el, extra = {} ) => (
	<AppContext.Provider value={ { meta: META, settings: SETTINGS, reloadMeta: () => Promise.resolve(), save: ( p ) => Promise.resolve( { ...SETTINGS, ...p } ), ...extra } }>
		<ToastProvider>{ el }</ToastProvider>
	</AppContext.Provider>
);

afterEach( () => {
	document.body.innerHTML = '';
	apiFetch.mockReset();
	apiFetch.mockImplementation( () => new Promise( () => {} ) );
} );

describe( 'admin helpers', () => {
	it( 'converts paragraphs both ways', () => {
		expect( htmlToText( '<p>One &amp; two</p>\n<p>Three</p>' ) ).toBe( 'One & two\n\nThree' );
		expect( textToHtml( 'A <b>\n\nB' ) ).toBe( '<p>A &lt;b&gt;</p>\n<p>B</p>' );
	} );
} );

describe( 'Properties', () => {
	it( 'lists properties and runs a bulk action on the selection', async () => {
		apiFetch.mockImplementation( ( { url, method } ) => {
			if ( method === 'POST' ) {
				return Promise.resolve( { done: 1 } );
			}
			return Promise.resolve( { items: [ { id: 11, title: 'Maple House', status: 'publish', price: '$450,000', listing_type: 'sale', market: 'available', beds: 3, baths: 2, area: '1,800 ft²', city: 'Springfield', type: 'House', featured: true, agent: 'Maya Chen', views: 40, leads: 2, date: '2026-10-01T10:00:00', url: '/p', image: '' } ], total: 1, counts: { publish: 1, pending: 0, draft: 0 }, _url: url } );
		} );
		const view = await render( wrap( <Properties navigate={ () => {} } /> ) );
		await view.flush();
		expect( view.queryByText( 'Maple House' ) ).not.toBeNull();
		expect( view.queryByText( 'House · Springfield · Maya Chen' ) ).not.toBeNull();
		const box = view.$( 'input[type="checkbox"]' );
		await click( box );
		await change( view.$$( 'select' ).find( ( s ) => s.querySelector( 'option[value="feature"]' ) ), 'feature' );
		await click( view.getByText( 'Apply' ) );
		await view.flush();
		const post = apiFetch.mock.calls.find( ( c ) => c[ 0 ].method === 'POST' );
		expect( post[ 0 ].url ).toContain( 'admin/properties/bulk' );
		expect( post[ 0 ].data ).toEqual( { ids: [ 11 ], action: 'feature' } );
		view.unmount();
	} );
} );

describe( 'Leads', () => {
	it( 'opens a lead and changes its status', async () => {
		const lead = { id: 7, name: 'Ann Lee', email: 'ann@example.com', phone: '', kind: 'viewing', viewing_at: '2026-11-02', status: 'new', notes: '', message: 'Can I see it Saturday?', property: 'Maple House', property_url: '/p', agent: 'Maya Chen', created_at: '2026-10-20 10:00:00' };
		const onChange = jest.fn();
		apiFetch.mockImplementation( ( { method, data } ) => ( method === 'POST' ? Promise.resolve( { ...lead, ...data } ) : Promise.resolve( { items: [ lead ], total: 1, counts: { new: 1 } } ) ) );
		const view = await render( wrap( <Leads onChange={ onChange } /> ) );
		await view.flush();
		expect( onChange ).toHaveBeenCalledWith( 1 );
		await click( view.getByText( 'Ann Lee' ) );
		await view.flush();
		expect( view.queryByText( 'Can I see it Saturday?' ) ).not.toBeNull();
		const status = view.$$( '.dev3re-ui-drawer select, [role="dialog"] select' )[ 0 ];
		await change( status, 'contacted' );
		await view.flush();
		const post = apiFetch.mock.calls.find( ( c ) => c[ 0 ].method === 'POST' );
		expect( post[ 0 ].url ).toContain( 'admin/leads/7' );
		expect( post[ 0 ].data ).toEqual( { status: 'contacted' } );
		view.unmount();
	} );
} );

describe( 'Saved searches', () => {
	it( 'sends alerts on demand', async () => {
		apiFetch.mockImplementation( ( { method } ) => ( method === 'POST' ? Promise.resolve( { sent: 2, checked: 3 } ) : Promise.resolve( { items: [ { id: 1, label: 'For sale · House', email: 'a@b.co', confirmed: false, frequency: 'daily', sent_count: 0, last_sent: null, url: '/s' } ], total: 1 } ) ) );
		const view = await render( wrap( <Searches /> ) );
		await view.flush();
		expect( view.queryByText( 'For sale · House' ) ).not.toBeNull();
		expect( view.queryByText( 'Unconfirmed' ) ).not.toBeNull();
		await click( view.getByText( 'Send alerts now' ) );
		await view.flush();
		expect( view.queryByText( /2 alert emails sent/ ) ).not.toBeNull();
		view.unmount();
	} );
} );

describe( 'Dashboard', () => {
	it( 'shows KPIs and the setup checklist', async () => {
		apiFetch.mockResolvedValue( { properties: { publish: 5, pending: 1, draft: 2, sale: 3, rent: 2, closed: 0, featured: 1 }, leads: { new: 4 }, leads_7d: 6, searches: 2, recent: [], popular: [], checks: { pages: false, property: true, agent: true, map: true, ai: false } } );
		const view = await render( wrap( <Dashboard navigate={ () => {} } /> ) );
		await view.flush();
		expect( view.queryByText( '3 for sale · 2 for rent' ) ).not.toBeNull();
		expect( view.queryByText( 'Create pages' ) ).not.toBeNull();
		expect( view.queryByText( '3 of 5 steps done' ) ).not.toBeNull();
		view.unmount();
	} );
} );

describe( 'Settings', () => {
	it( 'saves changed settings', async () => {
		const save = jest.fn( ( p ) => Promise.resolve( { ...SETTINGS, ...p } ) );
		const view = await render( wrap( <SettingsTab settings={ SETTINGS } save={ save } />, { save } ) );
		const unit = view.$$( 'select' ).find( ( s ) => s.querySelector( 'option[value="sqm"]' ) && ! s.querySelector( 'option[value=""]' ) );
		await change( unit, 'sqm' );
		await click( view.$( 'button[type="submit"]' ) );
		await view.flush();
		expect( save ).toHaveBeenCalled();
		expect( save.mock.calls[ 0 ][ 0 ].area_unit ).toBe( 'sqm' );
		view.unmount();
	} );
} );
