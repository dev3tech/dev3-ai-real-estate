import { breakdown, initMortgage, monthly } from '../../src/frontend/mortgage';
import { load, money, popupHtml, rest, store } from '../../src/frontend/util';
import { applyQuery, formParams } from '../../src/frontend/search';
import { getCompare, refreshSaved, toggleCompare } from '../../src/frontend/saved';
import { collect, htmlToText } from '../../src/frontend/submit';
import { initEnquiry } from '../../src/frontend/single';

const flush = () => new Promise( ( r ) => setTimeout( r, 0 ) );

beforeEach( () => {
	document.body.innerHTML = '';
	window.localStorage.clear();
	window.dev3reConfig = { restUrl: '/wp-json/dev3re/v1/', nonce: '', compareMax: 4, features: { compare: true, favourites: true }, pages: { compare: '/compare/' }, i18n: { sent: 'Sent!', required: 'Fill in', consent: 'Tick consent', cmpBar: '%d selected to compare', cmpOpen: 'Compare now', cmpClear: 'Clear', favAdd: 'Save', favRemove: 'Remove', cmpAdd: 'Add', cmpRemove: 'Drop' } };
} );

describe( 'mortgage maths', () => {
	it( 'matches the annuity formula to the cent', () => {
		// 320,000 at 6.5% over 30 years = 2,022.62 / month.
		expect( monthly( 320000, 6.5, 30 ) ).toBe( 2022.62 );
		expect( monthly( 200000, 4, 15 ) ).toBe( 1479.38 );
	} );

	it( 'handles 0% interest and empty input', () => {
		expect( monthly( 120000, 0, 10 ) ).toBe( 1000 );
		expect( monthly( 0, 5, 30 ) ).toBe( 0 );
		expect( monthly( 'abc', 5, 30 ) ).toBe( 0 );
	} );

	it( 'breaks down loan, total and interest from a down payment %', () => {
		const b = breakdown( 400000, 20, 6.5, 30 );
		expect( b.loan ).toBe( 320000 );
		expect( b.monthly ).toBe( 2022.62 );
		expect( Math.round( b.interest ) ).toBe( Math.round( 2022.62 * 360 - 320000 ) );
	} );

	it( 'updates the calculator UI on input', () => {
		document.body.innerHTML = `<form data-dev3re-mortgage='{"symbol":"$","position":"before","sep":","}'>
			<input name="price" value="400000"><input name="down" value="20"><input name="rate" value="6.5"><input name="term" value="30">
			<p data-out="monthly"></p><span data-out="bar"></span><dd data-out="loan"></dd><dd data-out="interest"></dd><dd data-out="total"></dd></form>`;
		const form = document.querySelector( 'form' );
		initMortgage( form );
		expect( form.querySelector( '[data-out="monthly"]' ).textContent ).toBe( '$2,023' );
		form.elements.down.value = '100';
		form.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		expect( form.querySelector( '[data-out="monthly"]' ).textContent ).toBe( '$0' );
	} );
} );

describe( 'helpers', () => {
	it( 'formats money like the server', () => {
		expect( money( 1250000 ) ).toBe( '$1,250,000' );
		expect( money( 950, { symbol: '€', position: 'after_space', sep: '.' } ) ).toBe( '950 €' );
		expect( money( 1234567, { symbol: '€', position: 'after', sep: '.' } ) ).toBe( '1.234.567€' );
	} );

	it( 'builds REST urls with plain permalinks', () => {
		window.dev3reConfig.restUrl = '/index.php?rest_route=/dev3re/v1/';
		expect( rest( 'properties?beds=2' ) ).toBe( '/index.php?rest_route=/dev3re/v1/properties&beds=2' );
	} );

	it( 'stores JSON safely', () => {
		store( 'k', [ 1, 2 ] );
		expect( load( 'k', [] ) ).toEqual( [ 1, 2 ] );
		expect( load( 'missing', 'x' ) ).toBe( 'x' );
	} );

	it( 'escapes map popups', () => {
		const html = popupHtml( { url: '/p/1', title: '<b>Villa</b>', label: '$1', facts: '3 beds' } );
		expect( html ).toContain( '&lt;b&gt;Villa&lt;/b&gt;' );
		expect( html ).not.toContain( '<b>' );
	} );
} );

const SEARCH_FORM = `<form data-dev3re-form>
	<input name="nl" value="cheap flat">
	<input type="radio" name="listing_type" value="" checked><input type="radio" name="listing_type" value="sale"><input type="radio" name="listing_type" value="rent">
	<select name="type"><option value="">Any</option><option value="house">House</option></select>
	<select name="location"><option value="">Anywhere</option><option value="springfield">Springfield</option></select>
	<select name="price_min"><option value="">No min</option></select>
	<select name="price_max"><option value="">No max</option><option value="500000">$500,000</option></select>
	<select name="beds"><option value="">Any</option><option value="3">3+</option></select>
	<select name="baths"><option value="">Any</option></select>
	<input name="area_min"><input name="area_max"><input name="year_min"><input name="q">
	<input type="checkbox" name="features[]" value="garden"><input type="checkbox" name="features[]" value="swimming-pool">
	<input type="hidden" name="sort" value="newest"><input type="hidden" name="bbox" value="">
	<select name="sort_ui"><option value="newest">Newest</option></select>
</form>`;

describe( 'search form', () => {
	it( 'serialises filters without the plain-language text and default sort', () => {
		document.body.innerHTML = SEARCH_FORM;
		const form = document.querySelector( 'form' );
		form.querySelector( 'input[value="rent"]' ).checked = true;
		form.elements.beds.value = '3';
		form.querySelectorAll( 'input[name="features[]"]' ).forEach( ( c ) => ( c.checked = true ) );
		expect( formParams( form ) ).toEqual( { listing_type: 'rent', beds: '3', features: 'garden,swimming-pool' } );
	} );

	it( 'applies a parsed query, adding missing price options', () => {
		document.body.innerHTML = SEARCH_FORM;
		const form = document.querySelector( 'form' );
		applyQuery( form, { listing_type: 'sale', type: [ 'house' ], location: [ 'springfield' ], price_max: 450000, beds: 3, features: [ 'garden' ], sort: 'price_asc' }, { prices: { sale: [ [ 500000, '$500,000' ] ] }, money: { symbol: '$' } } );
		expect( form.querySelector( 'input[name="listing_type"]:checked' ).value ).toBe( 'sale' );
		expect( form.elements.type.value ).toBe( 'house' );
		expect( form.elements.location.value ).toBe( 'springfield' );
		expect( form.elements.price_max.value ).toBe( '450000' );
		expect( form.elements.price_max.selectedOptions[ 0 ].textContent ).toBe( '$450,000' );
		expect( form.querySelector( 'input[value="garden"]' ).checked ).toBe( true );
		expect( form.querySelector( 'input[value="swimming-pool"]' ).checked ).toBe( false );
		expect( formParams( form ).sort ).toBe( 'price_asc' );
	} );
} );

describe( 'compare and favourites', () => {
	it( 'caps compare at 4 and syncs buttons + the compare bar', () => {
		document.body.innerHTML = '<button data-dev3re-compare="1"></button><button data-dev3re-compare="9"></button>';
		[ 1, 2, 3, 4 ].forEach( ( id ) => expect( toggleCompare( id ) ).toBe( true ) );
		expect( toggleCompare( 5 ) ).toBeNull();
		expect( toggleCompare( 2 ) ).toBe( false );
		expect( getCompare() ).toEqual( [ 1, 3, 4 ] );
		refreshSaved();
		expect( document.querySelector( '[data-dev3re-compare="1"]' ).getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( document.querySelector( '[data-dev3re-compare="9"]' ).getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		expect( document.querySelector( '.dev3re-cmpbar__text' ).textContent ).toBe( '3 selected to compare' );
		expect( document.querySelector( '[data-dev3re-cmp-open]' ).getAttribute( 'href' ) ).toBe( '/compare/' );
	} );
} );

describe( 'submission form', () => {
	it( 'turns AI paragraphs into editable text', () => {
		expect( htmlToText( '<p>Sunny &amp; bright.</p>\n<p>Big garden.</p>' ) ).toBe( 'Sunny & bright.\n\nBig garden.' );
	} );

	it( 'collects a typed payload with gallery order and plans', () => {
		document.body.innerHTML = `<form>
			<input name="title" value="Garden flat"><textarea name="content">Nice</textarea><input name="excerpt" value="">
			<input type="radio" name="listing_type" value="sale"><input type="radio" name="listing_type" value="rent" checked>
			<select name="type"><option value="7" selected>Flat</option></select>
			<input name="price" value="1800"><select name="price_period"><option value="month" selected>m</option></select>
			<input type="checkbox" name="price_on_request" value="1"><input type="checkbox" name="hide_address" value="1" checked>
			<input name="beds" value="2"><input name="city" value="Springfield"><input name="lat" value="39.1"><input name="lng" value="-89.2">
			<input name="highlights" value="Garden&#10;Quiet"><input name="neighbourhood_ai" value="1">
			<input type="checkbox" name="features[]" value="3" checked><input type="checkbox" name="features[]" value="4">
		</form>`;
		const out = collect( document.querySelector( 'form' ), { images: [ { id: 12 }, { id: 10 } ], plans: [ { title: 'Ground', image: 5, beds: 1, baths: 1, area: 50, notes: '', image_url: 'x' } ] }, true );
		expect( out.title ).toBe( 'Garden flat' );
		expect( out.status ).toBe( 'draft' );
		expect( out.type ).toEqual( [ 7 ] );
		expect( out.features ).toEqual( [ 3 ] );
		expect( out.fields.listing_type ).toBe( 'rent' );
		expect( out.fields.hide_address ).toBe( true );
		expect( out.fields.price_on_request ).toBe( false );
		expect( out.fields.gallery ).toEqual( [ 12, 10 ] );
		expect( out.fields.floor_plans[ 0 ] ).toEqual( { title: 'Ground', image: 5, beds: 1, baths: 1, area: 50, notes: '' } );
		expect( out.fields.neighbourhood_ai ).toBe( '1' );
	} );
} );

describe( 'enquiry form', () => {
	const FORM = `<form data-dev3re-enquiry>
		<input name="property_id" value="5"><input type="radio" name="kind" value="enquiry" checked><input type="radio" name="kind" value="viewing">
		<div data-dev3re-viewing hidden></div>
		<input name="name" value=""><input name="email" value=""><textarea name="message">Hi</textarea>
		<input type="checkbox" name="consent" value="1"><button type="submit">Send</button><p data-dev3re-enquiry-msg></p></form>`;

	it( 'validates before sending and posts the lead', async () => {
		document.body.innerHTML = FORM;
		const form = document.querySelector( 'form' );
		window.fetch = jest.fn( () => Promise.resolve( { ok: true, status: 201, json: () => Promise.resolve( { ok: true } ) } ) );
		initEnquiry( form );
		form.dispatchEvent( new window.Event( 'submit', { cancelable: true } ) );
		expect( form.querySelector( '[data-dev3re-enquiry-msg]' ).textContent ).toBe( 'Fill in' );
		form.elements.name.value = 'Ann';
		form.elements.email.value = 'ann@example.com';
		form.dispatchEvent( new window.Event( 'submit', { cancelable: true } ) );
		expect( form.querySelector( '[data-dev3re-enquiry-msg]' ).textContent ).toBe( 'Tick consent' );
		form.elements.consent.checked = true;
		form.querySelector( 'input[value="viewing"]' ).checked = true;
		form.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		form.querySelector( 'input[value="viewing"]' ).dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		expect( form.querySelector( '[data-dev3re-viewing]' ).hidden ).toBe( false );
		form.dispatchEvent( new window.Event( 'submit', { cancelable: true } ) );
		await flush();
		await flush();
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, opts ] = window.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/dev3re/v1/leads' );
		expect( JSON.parse( opts.body ) ).toMatchObject( { name: 'Ann', email: 'ann@example.com', kind: 'viewing', property_id: '5', consent: '1' } );
		expect( form.querySelector( '[data-dev3re-enquiry-msg]' ).textContent ).toBe( 'Sent!' );
	} );
} );
