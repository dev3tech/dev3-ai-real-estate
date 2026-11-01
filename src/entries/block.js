import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

const ICON = (
	<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
		<path d="M3 10.5L12 3l9 7.5V21a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1z" />
	</svg>
);

const data = () => window.dev3reBlock || { types: [], locations: [] };
const deals = [
	{ value: '', label: __( 'Sale and rent', 'dev3-ai-real-estate' ) },
	{ value: 'sale', label: __( 'For sale', 'dev3-ai-real-estate' ) },
	{ value: 'rent', label: __( 'For rent', 'dev3-ai-real-estate' ) },
];

/**
 * Generic edit: inspector controls + live server-side preview.
 *
 * @param {string}   name     Block name.
 * @param {Function} controls ( attributes, setAttributes ) => controls.
 * @return {Function} Edit component.
 */
const edit = ( name, controls ) =>
	function Edit( { attributes, setAttributes } ) {
		const props = useBlockProps();
		return (
			<div { ...props }>
				{ controls && (
					<InspectorControls>
						<PanelBody title={ __( 'Settings', 'dev3-ai-real-estate' ) }>{ controls( attributes, setAttributes ) }</PanelBody>
					</InspectorControls>
				) }
				<Disabled>
					<ServerSideRender block={ name } attributes={ attributes } />
				</Disabled>
			</div>
		);
	};

const dealControl = ( a, set ) => <SelectControl label={ __( 'Listing type', 'dev3-ai-real-estate' ) } value={ a.listing_type } options={ deals } onChange={ ( v ) => set( { listing_type: v } ) } />;

const blocks = {
	'dev3re/search': {
		title: __( 'Property search', 'dev3-ai-real-estate' ),
		description: __( 'Plain-language search, filters, results and map, with saved searches.', 'dev3-ai-real-estate' ),
		attributes: { listing_type: { type: 'string', default: '' }, columns: { type: 'number', default: 2 }, per_page: { type: 'number', default: 12 }, map: { type: 'string', default: 'yes' } },
		controls: ( a, set ) => (
			<>
				{ dealControl( a, set ) }
				<RangeControl label={ __( 'Columns', 'dev3-ai-real-estate' ) } value={ a.columns } min={ 1 } max={ 4 } onChange={ ( v ) => set( { columns: v } ) } />
				<RangeControl label={ __( 'Results per page', 'dev3-ai-real-estate' ) } value={ a.per_page } min={ 1 } max={ 60 } onChange={ ( v ) => set( { per_page: v } ) } />
				<ToggleControl label={ __( 'Show map', 'dev3-ai-real-estate' ) } checked={ a.map !== 'no' } onChange={ ( v ) => set( { map: v ? 'yes' : 'no' } ) } />
			</>
		),
	},
	'dev3re/quick-search': {
		title: __( 'Property quick search', 'dev3-ai-real-estate' ),
		description: __( 'A hero search box that opens the search page.', 'dev3-ai-real-estate' ),
		attributes: { listing_type: { type: 'string', default: 'sale' } },
		controls: dealControl,
	},
	'dev3re/properties': {
		title: __( 'Properties grid', 'dev3-ai-real-estate' ),
		description: __( 'Featured or latest properties as cards.', 'dev3-ai-real-estate' ),
		attributes: { listing_type: { type: 'string', default: '' }, type: { type: 'string', default: '' }, location: { type: 'string', default: '' }, featured: { type: 'string', default: 'no' }, sort: { type: 'string', default: 'newest' }, limit: { type: 'number', default: 6 }, columns: { type: 'number', default: 3 } },
		controls: ( a, set ) => (
			<>
				{ dealControl( a, set ) }
				<SelectControl label={ __( 'Property type', 'dev3-ai-real-estate' ) } value={ a.type } options={ [ { value: '', label: __( 'Any', 'dev3-ai-real-estate' ) }, ...data().types ] } onChange={ ( v ) => set( { type: v } ) } />
				<SelectControl label={ __( 'Location', 'dev3-ai-real-estate' ) } value={ a.location } options={ [ { value: '', label: __( 'Anywhere', 'dev3-ai-real-estate' ) }, ...data().locations ] } onChange={ ( v ) => set( { location: v } ) } />
				<ToggleControl label={ __( 'Featured only', 'dev3-ai-real-estate' ) } checked={ a.featured === 'yes' } onChange={ ( v ) => set( { featured: v ? 'yes' : 'no' } ) } />
				<SelectControl
					label={ __( 'Order', 'dev3-ai-real-estate' ) }
					value={ a.sort }
					options={ [
						{ value: 'newest', label: __( 'Newest', 'dev3-ai-real-estate' ) },
						{ value: 'price_asc', label: __( 'Price: low to high', 'dev3-ai-real-estate' ) },
						{ value: 'price_desc', label: __( 'Price: high to low', 'dev3-ai-real-estate' ) },
					] }
					onChange={ ( v ) => set( { sort: v } ) }
				/>
				<RangeControl label={ __( 'Number of properties', 'dev3-ai-real-estate' ) } value={ a.limit } min={ 1 } max={ 24 } onChange={ ( v ) => set( { limit: v } ) } />
				<RangeControl label={ __( 'Columns', 'dev3-ai-real-estate' ) } value={ a.columns } min={ 1 } max={ 4 } onChange={ ( v ) => set( { columns: v } ) } />
			</>
		),
	},
	'dev3re/mortgage': {
		title: __( 'Mortgage calculator', 'dev3-ai-real-estate' ),
		description: __( 'Monthly payment, loan and interest estimate.', 'dev3-ai-real-estate' ),
		attributes: { price: { type: 'number', default: 400000 } },
		controls: ( a, set ) => <TextControl type="number" label={ __( 'Default price', 'dev3-ai-real-estate' ) } value={ a.price } onChange={ ( v ) => set( { price: Number( v ) || 0 } ) } />,
	},
	'dev3re/agents': {
		title: __( 'Agents', 'dev3-ai-real-estate' ),
		description: __( 'Grid of your agents with their listing counts.', 'dev3-ai-real-estate' ),
		attributes: { limit: { type: 'number', default: 12 } },
		controls: ( a, set ) => <RangeControl label={ __( 'Number of agents', 'dev3-ai-real-estate' ) } value={ a.limit } min={ 1 } max={ 60 } onChange={ ( v ) => set( { limit: v } ) } />,
	},
	'dev3re/submit': { title: __( 'Submit property', 'dev3-ai-real-estate' ), description: __( 'Front-end listing form for agents.', 'dev3-ai-real-estate' ), attributes: {} },
	'dev3re/dashboard': { title: __( 'Agent dashboard', 'dev3-ai-real-estate' ), description: __( 'Listings, leads, profile, saved properties and alerts.', 'dev3-ai-real-estate' ), attributes: {} },
	'dev3re/favourites': { title: __( 'Saved properties', 'dev3-ai-real-estate' ), description: __( 'The visitor’s favourite properties.', 'dev3-ai-real-estate' ), attributes: {} },
	'dev3re/compare': { title: __( 'Compare properties', 'dev3-ai-real-estate' ), description: __( 'Side-by-side comparison of up to 4 properties.', 'dev3-ai-real-estate' ), attributes: {} },
};

Object.keys( blocks ).forEach( ( name ) => {
	const b = blocks[ name ];
	registerBlockType( name, {
		apiVersion: 2,
		title: b.title,
		description: b.description,
		category: 'widgets',
		icon: ICON,
		keywords: [ 'real estate', 'property', 'dev3' ],
		supports: { align: [ 'wide', 'full' ], html: false },
		attributes: b.attributes,
		edit: edit( name, b.controls ),
		save: () => null,
	} );
} );
