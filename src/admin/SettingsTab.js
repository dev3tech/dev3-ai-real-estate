import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Button, Card, ColorField, FormGrid, NumberField, PageHeader, Select, TextField, Toggle, useToast } from '../core3/ui';
import { useApp } from './shared';

const KEYS = [
	'currency',
	'currency_symbol',
	'currency_position',
	'thousands_sep',
	'area_unit',
	'per_page',
	'hide_sold',
	'map_provider',
	'google_maps_key',
	'map_center_lat',
	'map_center_lng',
	'map_zoom',
	'geocoding_enabled',
	'amenities_enabled',
	'submission_enabled',
	'submission_moderation',
	'agent_signup',
	'favourites_enabled',
	'compare_enabled',
	'saved_searches_enabled',
	'mortgage_enabled',
	'mortgage_rate',
	'mortgage_term',
	'mortgage_down',
	'schema_enabled',
	'notify_email',
	'leads_autoreply',
	'leads_consent',
	'email_admin_leads',
	'email_admin_pending',
	'ai_describe',
	'ai_neighbourhood',
	'ai_nl_search',
	'ai_tone',
	'accent_color',
	'delete_on_uninstall',
	'page_search',
	'page_submit',
	'page_dashboard',
	'page_favourites',
	'page_compare',
];

/**
 * Vocabulary editor (types, features, locations).
 *
 * @param {Object} props       Props.
 * @param {string} props.tax   type|feature|location.
 * @param {string} props.title Title.
 * @param {Array}  props.terms Terms.
 * @return {Element} Card.
 */
function Vocabulary( { tax, title, terms } ) {
	const toast = useToast();
	const { reloadMeta } = useApp();
	const [ name, setName ] = useState( '' );
	const [ parent, setParent ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const add = () => {
		if ( ! name.trim() ) {
			return;
		}
		setBusy( true );
		request( `admin/terms/${ tax }`, { method: 'POST', data: { name, parent: Number( parent ) || 0 } } )
			.then( () => {
				setName( '' );
				return reloadMeta();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};
	const remove = ( t ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( /* translators: %s: term */ __( 'Delete “%s”? Properties keep their other terms.', 'dev3-ai-real-estate' ), t.name ) ) ) {
			return;
		}
		request( `admin/terms/${ tax }/${ t.id }`, { method: 'DELETE' } )
			.then( reloadMeta )
			.catch( ( err ) => toast.error( err ) );
	};
	return (
		<Card title={ title }>
			<ul className="dev3re-admin-terms">
				{ terms.map( ( t ) => (
					<li key={ t.id }>
						<span>
							{ t.parent ? '— ' : '' }
							{ t.name }
							<span className="dev3re-admin-muted"> ({ t.count })</span>
						</span>
						<button type="button" onClick={ () => remove( t ) } aria-label={ sprintf( /* translators: %s: term */ __( 'Delete %s', 'dev3-ai-real-estate' ), t.name ) }>
							✕
						</button>
					</li>
				) ) }
				{ ! terms.length && <li className="dev3re-admin-muted">{ __( 'None yet.', 'dev3-ai-real-estate' ) }</li> }
			</ul>
			<div className="dev3re-admin-airow">
				<TextField label={ __( 'New name', 'dev3-ai-real-estate' ) } hideLabel placeholder={ __( 'Add new…', 'dev3-ai-real-estate' ) } value={ name } onChange={ setName } onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						add();
					}
				} } />
				{ tax === 'location' && (
					<Select label={ __( 'Parent', 'dev3-ai-real-estate' ) } hideLabel value={ parent } onChange={ setParent } options={ [ { value: '', label: __( 'Top level (city)', 'dev3-ai-real-estate' ) }, ...terms.filter( ( t ) => ! t.parent ).map( ( t ) => ( { value: String( t.id ), label: sprintf( /* translators: %s: city */ __( 'In %s', 'dev3-ai-real-estate' ), t.name ) } ) ) ] } />
				) }
				<Button icon="plus" onClick={ add } loading={ busy }>
					{ __( 'Add', 'dev3-ai-real-estate' ) }
				</Button>
			</div>
		</Card>
	);
}

/**
 * Plugin settings.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings Settings.
 * @param {Function} props.save     Save.
 * @return {Element} Settings.
 */
export default function SettingsTab( { settings, save } ) {
	const toast = useToast();
	const { meta } = useApp();
	const [ form, setForm ] = useState( settings );
	const [ busy, setBusy ] = useState( false );
	const [ tool, setTool ] = useState( '' );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const dirty = KEYS.some( ( k ) => String( form[ k ] ) !== String( settings[ k ] ) );

	const submit = ( e ) => {
		e.preventDefault();
		const patch = {};
		KEYS.forEach( ( k ) => ( patch[ k ] = form[ k ] === '' && typeof settings[ k ] === 'number' ? settings[ k ] : form[ k ] ) );
		setBusy( true );
		save( patch )
			.then( ( next ) => {
				setForm( next );
				toast.success( __( 'Settings saved.', 'dev3-ai-real-estate' ) );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};
	const createPages = () => {
		setTool( 'pages' );
		request( 'admin/pages', { method: 'POST' } )
			.then( ( next ) => {
				setForm( next );
				return save( {} );
			} )
			.then( () => toast.success( __( 'Pages created and linked.', 'dev3-ai-real-estate' ) ) )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setTool( '' ) );
	};
	const reindex = () => {
		setTool( 'index' );
		request( 'admin/reindex', { method: 'POST' } )
			.then( ( res ) => toast.success( sprintf( /* translators: %d: count */ __( 'Search index rebuilt for %d properties.', 'dev3-ai-real-estate' ), res.indexed ) ) )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setTool( '' ) );
	};

	const pageOptions = [ { value: '0', label: __( '— Not set —', 'dev3-ai-real-estate' ) }, ...meta.pages.map( ( p ) => ( { value: String( p.value ), label: p.label } ) ) ];
	const footer = (
		<>
			<Button variant="ghost" onClick={ () => setForm( settings ) } disabled={ ! dirty || busy }>
				{ __( 'Discard changes', 'dev3-ai-real-estate' ) }
			</Button>
			<Button variant="primary" type="submit" loading={ busy } disabled={ ! dirty }>
				{ __( 'Save settings', 'dev3-ai-real-estate' ) }
			</Button>
		</>
	);

	return (
		<form onSubmit={ submit } className="dev3re-admin-settings">
			<PageHeader title={ __( 'Settings', 'dev3-ai-real-estate' ) } description={ __( 'Pages, units, maps, submissions, leads, alerts and DEV3 AI features.', 'dev3-ai-real-estate' ) } actions={ <Button variant="primary" type="submit" loading={ busy } disabled={ ! dirty }>{ __( 'Save settings', 'dev3-ai-real-estate' ) }</Button> } />

			<Card title={ __( 'Pages', 'dev3-ai-real-estate' ) } description={ __( 'Each page holds the matching block. Create them all with one click, or pick your own pages.', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" onClick={ createPages } loading={ tool === 'pages' }>{ __( 'Create missing pages', 'dev3-ai-real-estate' ) }</Button> } footer={ footer }>
				<FormGrid>
					<Select label={ __( 'Search page', 'dev3-ai-real-estate' ) } value={ String( form.page_search ) } onChange={ set( 'page_search' ) } options={ pageOptions } />
					<Select label={ __( 'Submit property page', 'dev3-ai-real-estate' ) } value={ String( form.page_submit ) } onChange={ set( 'page_submit' ) } options={ pageOptions } />
					<Select label={ __( 'Dashboard page', 'dev3-ai-real-estate' ) } value={ String( form.page_dashboard ) } onChange={ set( 'page_dashboard' ) } options={ pageOptions } />
					<Select label={ __( 'Saved properties page', 'dev3-ai-real-estate' ) } value={ String( form.page_favourites ) } onChange={ set( 'page_favourites' ) } options={ pageOptions } />
					<Select label={ __( 'Compare page', 'dev3-ai-real-estate' ) } value={ String( form.page_compare ) } onChange={ set( 'page_compare' ) } options={ pageOptions } />
				</FormGrid>
			</Card>

			<Card title={ __( 'Prices and units', 'dev3-ai-real-estate' ) } footer={ footer }>
				<FormGrid>
					<TextField label={ __( 'Currency code', 'dev3-ai-real-estate' ) } value={ form.currency } onChange={ set( 'currency' ) } maxLength={ 3 } help={ __( 'ISO code used in schema, e.g. USD, EUR, AED.', 'dev3-ai-real-estate' ) } />
					<TextField label={ __( 'Currency symbol', 'dev3-ai-real-estate' ) } value={ form.currency_symbol } onChange={ set( 'currency_symbol' ) } maxLength={ 5 } />
					<Select label={ __( 'Symbol position', 'dev3-ai-real-estate' ) } value={ form.currency_position } onChange={ set( 'currency_position' ) } options={ [ { value: 'before', label: '$1,000' }, { value: 'before_space', label: '$ 1,000' }, { value: 'after', label: '1,000$' }, { value: 'after_space', label: '1,000 $' } ] } />
					<Select label={ __( 'Thousands separator', 'dev3-ai-real-estate' ) } value={ form.thousands_sep } onChange={ set( 'thousands_sep' ) } options={ [ { value: ',', label: '1,000' }, { value: '.', label: '1.000' }, { value: ' ', label: '1 000' }, { value: "'", label: "1'000" } ] } />
					<Select label={ __( 'Area unit', 'dev3-ai-real-estate' ) } value={ form.area_unit } onChange={ set( 'area_unit' ) } options={ [ { value: 'sqft', label: __( 'Square feet (ft²)', 'dev3-ai-real-estate' ) }, { value: 'sqm', label: __( 'Square metres (m²)', 'dev3-ai-real-estate' ) } ] } />
					<NumberField label={ __( 'Results per page', 'dev3-ai-real-estate' ) } value={ form.per_page } onChange={ set( 'per_page' ) } min={ 1 } max={ 60 } />
				</FormGrid>
				<Toggle label={ __( 'Hide sold and rented properties from search', 'dev3-ai-real-estate' ) } help={ __( 'Their pages stay online with a “Sold” label.', 'dev3-ai-real-estate' ) } checked={ !! form.hide_sold } onChange={ set( 'hide_sold' ) } />
			</Card>

			<Card title={ __( 'Maps', 'dev3-ai-real-estate' ) } description={ __( 'OpenStreetMap works out of the box. Google Maps needs a browser key with the Maps JavaScript and Geocoding APIs.', 'dev3-ai-real-estate' ) } footer={ footer }>
				<FormGrid>
					<Select label={ __( 'Map provider', 'dev3-ai-real-estate' ) } value={ form.map_provider } onChange={ set( 'map_provider' ) } options={ [ { value: 'osm', label: __( 'OpenStreetMap (free)', 'dev3-ai-real-estate' ) }, { value: 'google', label: 'Google Maps' } ] } />
					{ form.map_provider === 'google' && <TextField label={ __( 'Google Maps browser key', 'dev3-ai-real-estate' ) } value={ form.google_maps_key } onChange={ set( 'google_maps_key' ) } help={ __( 'Restrict it to your domain in Google Cloud.', 'dev3-ai-real-estate' ) } /> }
					<NumberField label={ __( 'Default map centre: latitude', 'dev3-ai-real-estate' ) } value={ form.map_center_lat } onChange={ set( 'map_center_lat' ) } min={ -90 } max={ 90 } step={ 0.0001 } />
					<NumberField label={ __( 'Default map centre: longitude', 'dev3-ai-real-estate' ) } value={ form.map_center_lng } onChange={ set( 'map_center_lng' ) } min={ -180 } max={ 180 } step={ 0.0001 } />
					<NumberField label={ __( 'Default zoom', 'dev3-ai-real-estate' ) } value={ form.map_zoom } onChange={ set( 'map_zoom' ) } min={ 1 } max={ 19 } />
				</FormGrid>
				<Toggle label={ __( 'Find coordinates from addresses (geocoding)', 'dev3-ai-real-estate' ) } checked={ !! form.geocoding_enabled } onChange={ set( 'geocoding_enabled' ) } />
				<Toggle label={ __( 'Look up nearby places on OpenStreetMap', 'dev3-ai-real-estate' ) } help={ __( 'Used for the neighbourhood section (schools, transport, parks…).', 'dev3-ai-real-estate' ) } checked={ !! form.amenities_enabled } onChange={ set( 'amenities_enabled' ) } />
			</Card>

			<Card title={ __( 'Front-end submission', 'dev3-ai-real-estate' ) } footer={ footer }>
				<Toggle label={ __( 'Agents can add properties from the front end', 'dev3-ai-real-estate' ) } checked={ !! form.submission_enabled } onChange={ set( 'submission_enabled' ) } />
				<FormGrid>
					<Select label={ __( 'New listings from agents', 'dev3-ai-real-estate' ) } value={ form.submission_moderation } onChange={ set( 'submission_moderation' ) } options={ [ { value: 'pending', label: __( 'Wait for my review', 'dev3-ai-real-estate' ) }, { value: 'publish', label: __( 'Go live right away', 'dev3-ai-real-estate' ) } ] } />
				</FormGrid>
				<Toggle label={ __( 'Let logged-in users become agents themselves', 'dev3-ai-real-estate' ) } help={ __( 'Off: you link users to agent profiles under Agents.', 'dev3-ai-real-estate' ) } checked={ !! form.agent_signup } onChange={ set( 'agent_signup' ) } />
			</Card>

			<Card title={ __( 'Visitor features', 'dev3-ai-real-estate' ) } footer={ footer }>
				<Toggle label={ __( 'Favourites (heart button)', 'dev3-ai-real-estate' ) } checked={ !! form.favourites_enabled } onChange={ set( 'favourites_enabled' ) } />
				<Toggle label={ __( 'Compare up to 4 properties', 'dev3-ai-real-estate' ) } checked={ !! form.compare_enabled } onChange={ set( 'compare_enabled' ) } />
				<Toggle label={ __( 'Saved searches with email alerts', 'dev3-ai-real-estate' ) } checked={ !! form.saved_searches_enabled } onChange={ set( 'saved_searches_enabled' ) } />
				<Toggle label={ __( 'RealEstateListing schema (rich results)', 'dev3-ai-real-estate' ) } checked={ !! form.schema_enabled } onChange={ set( 'schema_enabled' ) } />
				<Toggle label={ __( 'Mortgage calculator on properties for sale', 'dev3-ai-real-estate' ) } checked={ !! form.mortgage_enabled } onChange={ set( 'mortgage_enabled' ) } />
				{ form.mortgage_enabled && (
					<FormGrid>
						<NumberField label={ __( 'Default interest rate (%)', 'dev3-ai-real-estate' ) } value={ form.mortgage_rate } onChange={ set( 'mortgage_rate' ) } min={ 0 } max={ 30 } step={ 0.05 } />
						<NumberField label={ __( 'Default term (years)', 'dev3-ai-real-estate' ) } value={ form.mortgage_term } onChange={ set( 'mortgage_term' ) } min={ 1 } max={ 50 } />
						<NumberField label={ __( 'Default down payment (%)', 'dev3-ai-real-estate' ) } value={ form.mortgage_down } onChange={ set( 'mortgage_down' ) } min={ 0 } max={ 100 } />
					</FormGrid>
				) }
				<ColorField label={ __( 'Accent colour', 'dev3-ai-real-estate' ) } value={ form.accent_color } onChange={ set( 'accent_color' ) } help={ __( 'Buttons, links and highlights on the front end.', 'dev3-ai-real-estate' ) } />
			</Card>

			<Card title={ __( 'Leads and emails', 'dev3-ai-real-estate' ) } footer={ footer }>
				<TextField label={ __( 'Notification email', 'dev3-ai-real-estate' ) } type="email" value={ form.notify_email } onChange={ set( 'notify_email' ) } placeholder={ __( 'Site admin email', 'dev3-ai-real-estate' ) } help={ __( 'Gets leads for properties without an agent, copies of agent leads and review requests.', 'dev3-ai-real-estate' ) } />
				<Toggle label={ __( 'Send me a copy of every agent lead', 'dev3-ai-real-estate' ) } checked={ !! form.email_admin_leads } onChange={ set( 'email_admin_leads' ) } />
				<Toggle label={ __( 'Email me when a listing waits for review', 'dev3-ai-real-estate' ) } checked={ !! form.email_admin_pending } onChange={ set( 'email_admin_pending' ) } />
				<Toggle label={ __( 'Send visitors an automatic “we got your message” email', 'dev3-ai-real-estate' ) } checked={ !! form.leads_autoreply } onChange={ set( 'leads_autoreply' ) } />
				<Toggle label={ __( 'Ask for consent on enquiry forms (GDPR)', 'dev3-ai-real-estate' ) } checked={ !! form.leads_consent } onChange={ set( 'leads_consent' ) } />
			</Card>

			<Card tone="ai" title={ __( 'DEV3 AI features', 'dev3-ai-real-estate' ) } description={ __( 'Every AI result is a draft you accept, edit or reject. Visitor AI (plain-language search) is rate limited, cached and falls back to the built-in parser.', 'dev3-ai-real-estate' ) } footer={ footer }>
				<Toggle label={ __( 'Descriptions from photos and notes', 'dev3-ai-real-estate' ) } checked={ !! form.ai_describe } onChange={ set( 'ai_describe' ) } />
				<Toggle label={ __( 'Neighbourhood highlights', 'dev3-ai-real-estate' ) } checked={ !! form.ai_neighbourhood } onChange={ set( 'ai_neighbourhood' ) } />
				<Toggle label={ __( 'Plain-language search for visitors', 'dev3-ai-real-estate' ) } help={ __( 'Off: the built-in parser still understands “3 bed house under 500k with garden”.', 'dev3-ai-real-estate' ) } checked={ !! form.ai_nl_search } onChange={ set( 'ai_nl_search' ) } />
				<FormGrid>
					<Select label={ __( 'Default writing tone', 'dev3-ai-real-estate' ) } value={ form.ai_tone } onChange={ set( 'ai_tone' ) } options={ [ { value: 'warm', label: __( 'Warm', 'dev3-ai-real-estate' ) }, { value: 'professional', label: __( 'Professional', 'dev3-ai-real-estate' ) }, { value: 'luxury', label: __( 'Luxury', 'dev3-ai-real-estate' ) }, { value: 'concise', label: __( 'Concise', 'dev3-ai-real-estate' ) } ] } />
				</FormGrid>
			</Card>

			<div className="dev3re-admin-vocab">
				<Vocabulary tax="type" title={ __( 'Property types', 'dev3-ai-real-estate' ) } terms={ meta.types } />
				<Vocabulary tax="feature" title={ __( 'Features', 'dev3-ai-real-estate' ) } terms={ meta.features } />
				<Vocabulary tax="location" title={ __( 'Locations', 'dev3-ai-real-estate' ) } terms={ meta.locations } />
			</div>

			<Card title={ __( 'Maintenance', 'dev3-ai-real-estate' ) } footer={ footer }>
				<div className="dev3re-admin-airow">
					<Button icon="refresh" onClick={ reindex } loading={ tool === 'index' }>
						{ __( 'Rebuild search index', 'dev3-ai-real-estate' ) }
					</Button>
					<span className="dev3re-admin-muted">{ __( 'Only needed after importing properties outside the plugin.', 'dev3-ai-real-estate' ) }</span>
				</div>
				<Toggle label={ __( 'Delete all listings, agents, leads and settings when the plugin is deleted', 'dev3-ai-real-estate' ) } checked={ !! form.delete_on_uninstall } onChange={ set( 'delete_on_uninstall' ) } />
			</Card>
		</form>
	);
}
