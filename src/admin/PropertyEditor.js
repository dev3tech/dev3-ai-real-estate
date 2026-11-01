import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { AiButton, AiDraftReview, Badge, Button, Card, Checkbox, ErrorNotice, FormGrid, Icon, Modal, Notice, NumberField, PageHeader, Select, SkeletonCard, Textarea, TextField, Toggle, useToast } from '../core3/ui';
import { MARKET, MapPicker, StatusBadge, htmlToText, openMedia, textToHtml, useApp } from './shared';

const DESC_FIELDS = [
	{ key: 'title', label: __( 'Title', 'dev3-ai-real-estate' ), maxLength: 120 },
	{ key: 'excerpt', label: __( 'Summary for cards', 'dev3-ai-real-estate' ), multiline: true, maxLength: 300 },
	{ key: 'content', label: __( 'Description', 'dev3-ai-real-estate' ), multiline: true },
	{ key: 'highlights', label: __( 'Highlights (one per line)', 'dev3-ai-real-estate' ), multiline: true },
	{ key: 'features', label: __( 'Features spotted (one per line)', 'dev3-ai-real-estate' ), multiline: true },
];
const HOOD_FIELDS = [
	{ key: 'summary', label: __( 'Summary', 'dev3-ai-real-estate' ), multiline: true, maxLength: 600 },
	{ key: 'highlights', label: __( 'Highlights (one per line)', 'dev3-ai-real-estate' ), multiline: true },
];

const BLANK_FIELDS = {
	listing_type: 'sale',
	market: 'available',
	price: '',
	price_period: 'month',
	price_on_request: false,
	price_prefix: '',
	price_suffix: '',
	beds: '',
	baths: '',
	garages: '',
	area: '',
	area_unit: '',
	lot_size: '',
	year_built: '',
	floor: '',
	floors: '',
	energy_class: '',
	reference: '',
	street: '',
	city: '',
	region: '',
	postcode: '',
	country: '',
	lat: '',
	lng: '',
	hide_address: false,
	gallery: [],
	floor_plans: [],
	tour_url: '',
	video_url: '',
	featured: false,
	agent_id: 0,
	highlights: [],
	neighbourhood: '',
	neighbourhood_ai: false,
	nearby: [],
	notes: '',
};

const zero = ( v ) => ( v === 0 || v === '0' ? '' : v );

/**
 * Server record → form state.
 *
 * @param {Object|null} p Property.
 * @return {Object} Form.
 */
function toForm( p ) {
	if ( ! p ) {
		return { title: '', content: '', excerpt: '', status: 'publish', type: '', location: '', features: [], fields: { ...BLANK_FIELDS }, images: [], highlights: '', nearby: '' };
	}
	const f = { ...BLANK_FIELDS, ...p.fields };
	[ 'price', 'beds', 'baths', 'garages', 'area', 'lot_size', 'year_built', 'floor', 'floors' ].forEach( ( k ) => ( f[ k ] = zero( f[ k ] ) ) );
	return {
		title: p.title,
		content: htmlToText( p.content ),
		excerpt: p.excerpt,
		status: p.status,
		type: p.type[ 0 ] ? String( p.type[ 0 ] ) : '',
		location: p.locations[ 0 ] ? String( p.locations[ 0 ] ) : '',
		features: p.features,
		fields: f,
		images: p.images,
		highlights: ( f.highlights || [] ).join( '\n' ),
		nearby: ( f.nearby || [] ).join( '\n' ),
	};
}

/**
 * Form state → REST payload.
 *
 * @param {Object} form Form.
 * @return {Object} Payload.
 */
function toPayload( form ) {
	const fields = { ...form.fields };
	fields.gallery = form.images.map( ( i ) => i.id );
	fields.highlights = form.highlights;
	fields.nearby = form.nearby;
	fields.floor_plans = ( fields.floor_plans || [] ).map( ( p ) => ( { title: p.title, image: p.image, beds: p.beds, baths: p.baths, area: p.area, notes: p.notes } ) );
	return {
		title: form.title,
		content: textToHtml( form.content ),
		excerpt: form.excerpt,
		status: form.status,
		type: form.type ? [ Number( form.type ) ] : [],
		locations: form.location ? [ Number( form.location ) ] : [],
		features: form.features,
		fields,
	};
}

/**
 * Property editor.
 *
 * @param {Object}   props          Props.
 * @param {number}   props.id       Property (0 = new).
 * @param {Function} props.navigate Navigate.
 * @param {Function} props.onUsage  After AI calls.
 * @return {Element} Editor.
 */
export default function PropertyEditor( { id, navigate, onUsage } ) {
	const toast = useToast();
	const { meta, reloadMeta } = useApp();
	const [ record, setRecord ] = useState( null );
	const [ form, setForm ] = useState( id ? null : toForm( null ) );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const [ tone, setTone ] = useState( '' );
	const [ desc, setDesc ] = useState( { busy: false, draft: null, error: null } );
	const [ hood, setHood ] = useState( { busy: false, draft: null, error: null } );
	const [ geo, setGeo ] = useState( false );
	const aiReady = meta.ai && meta.ai.key_set && meta.ai.enabled;

	useEffect( () => {
		if ( ! id ) {
			setRecord( null );
			setForm( toForm( null ) );
			return;
		}
		setForm( null );
		request( `admin/properties/${ id }` )
			.then( ( p ) => {
				setRecord( p );
				setForm( toForm( p ) );
			} )
			.catch( setError );
	}, [ id ] );

	if ( error && ! form ) {
		return <ErrorNotice error={ error } />;
	}
	if ( ! form ) {
		return <SkeletonCard lines={ 10 } />;
	}

	const set = ( key ) => ( value ) => setForm( ( f ) => ( { ...f, [ key ]: value } ) );
	const setF = ( key ) => ( value ) => setForm( ( f ) => ( { ...f, fields: { ...f.fields, [ key ]: value } } ) );
	const f = form.fields;
	const unit = meta.unit;
	const termOptions = ( list, any ) => [ { value: '', label: any }, ...list.map( ( t ) => ( { value: String( t.id ), label: ( t.parent ? '— ' : '' ) + t.name } ) ) ];

	const save = ( e ) => {
		if ( e ) {
			e.preventDefault();
		}
		if ( ! form.title.trim() ) {
			toast.error( __( 'Add a title for the property.', 'dev3-ai-real-estate' ) );
			return;
		}
		setSaving( true );
		request( id ? `admin/properties/${ id }` : 'admin/properties', { method: 'POST', data: toPayload( form ) } )
			.then( ( p ) => {
				toast.success( __( 'Property saved.', 'dev3-ai-real-estate' ) );
				setRecord( p );
				setForm( toForm( p ) );
				reloadMeta();
				if ( ! id ) {
					navigate( `properties/${ p.id }` );
				}
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setSaving( false ) );
	};

	const remove = () =>
		request( `admin/properties/${ id }`, { method: 'DELETE' } )
			.then( () => {
				toast.success( __( 'Moved to trash.', 'dev3-ai-real-estate' ) );
				navigate( 'properties' );
			} )
			.catch( ( err ) => toast.error( err ) );

	const describe = ( mode ) => {
		setDesc( { busy: mode, draft: null, error: null } );
		request( 'admin/ai/describe', { method: 'POST', data: { mode, tone, listing: toPayload( form ) } } )
			.then( ( res ) => setDesc( { busy: false, draft: res, error: null } ) )
			.catch( ( err ) => setDesc( { busy: false, draft: null, error: err } ) )
			.finally( () => mode === 'ai' && onUsage() );
	};
	const acceptDesc = ( v ) => {
		const names = String( v.features || '' )
			.split( '\n' )
			.map( ( s ) => s.trim().toLowerCase() )
			.filter( Boolean );
		const ids = meta.features.filter( ( t ) => names.includes( t.name.toLowerCase() ) ).map( ( t ) => t.id );
		setForm( ( prev ) => ( {
			...prev,
			title: desc.draft && desc.draft.fallback && prev.title.trim() ? prev.title : v.title || prev.title,
			excerpt: v.excerpt,
			content: htmlToText( v.content ) || v.content,
			highlights: v.highlights,
			features: Array.from( new Set( [ ...prev.features, ...ids ] ) ),
		} ) );
		setDesc( { busy: false, draft: null, error: null } );
		toast.info( __( 'Draft copied into the form. Save to publish it.', 'dev3-ai-real-estate' ) );
	};

	const neighbourhood = ( ai ) => {
		setHood( { busy: ai ? 'ai' : 'facts', draft: null, error: null } );
		request( 'admin/ai/neighbourhood', { method: 'POST', data: { ai, fields: toPayload( form ).fields } } )
			.then( ( res ) => setHood( { busy: false, draft: res, error: null } ) )
			.catch( ( err ) => setHood( { busy: false, draft: null, error: err } ) )
			.finally( () => ai && onUsage() );
	};
	const acceptHood = ( v ) => {
		const lines = String( v.highlights || '' )
			.split( '\n' )
			.map( ( s ) => s.trim() )
			.filter( Boolean );
		const res = hood.draft;
		setForm( ( prev ) => ( {
			...prev,
			nearby: ( res.nearby || [] ).join( '\n' ) || prev.nearby,
			fields: {
				...prev.fields,
				neighbourhood: [ String( v.summary || '' ).trim(), lines.map( ( l ) => '• ' + l ).join( '\n' ) ].filter( Boolean ).join( '\n\n' ),
				neighbourhood_ai: !! res.ai,
				lat: prev.fields.lat === '' && res.lat ? res.lat : prev.fields.lat,
				lng: prev.fields.lng === '' && res.lng ? res.lng : prev.fields.lng,
			},
		} ) );
		setHood( { busy: false, draft: null, error: null } );
	};

	const geocode = () => {
		const address = [ f.street, f.city, f.region, f.postcode, f.country ].filter( Boolean ).join( ', ' );
		if ( ! address ) {
			toast.warning( __( 'Type the address first.', 'dev3-ai-real-estate' ) );
			return;
		}
		setGeo( true );
		request( 'admin/geocode', { method: 'POST', data: { address } } )
			.then( ( res ) => setForm( ( prev ) => ( { ...prev, fields: { ...prev.fields, lat: Number( res.lat ).toFixed( 7 ), lng: Number( res.lng ).toFixed( 7 ) } } ) ) )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setGeo( false ) );
	};

	const moveImage = ( i, d ) => {
		const imgs = [ ...form.images ];
		const j = i + d;
		if ( j < 0 || j >= imgs.length ) {
			return;
		}
		[ imgs[ i ], imgs[ j ] ] = [ imgs[ j ], imgs[ i ] ];
		set( 'images' )( imgs );
	};
	const plans = f.floor_plans || [];
	const setPlan = ( i, key, value ) => setF( 'floor_plans' )( plans.map( ( p, j ) => ( j === i ? { ...p, [ key ]: value } : p ) ) );

	const descOriginal = { title: form.title, excerpt: form.excerpt, content: form.content, highlights: form.highlights, features: meta.features.filter( ( t ) => form.features.includes( t.id ) ).map( ( t ) => t.name ).join( '\n' ) };
	const descDraft = desc.draft ? { ...desc.draft.draft, content: htmlToText( desc.draft.draft.content ) } : null;

	return (
		<form onSubmit={ save } className="dev3re-admin-editor-form">
			<PageHeader
				back={ { href: '#/properties', label: __( 'Properties', 'dev3-ai-real-estate' ) } }
				title={ id ? form.title || __( 'Edit property', 'dev3-ai-real-estate' ) : __( 'New property', 'dev3-ai-real-estate' ) }
				description={ record ? <>{ record.url && <a href={ record.url } target="_blank" rel="noreferrer">{ __( 'View on site', 'dev3-ai-real-estate' ) }</a> }</> : __( 'Add photos first: DEV3 AI can write the description from them.', 'dev3-ai-real-estate' ) }
				actions={
					<Button variant="primary" type="submit" loading={ saving } icon="check">
						{ __( 'Save property', 'dev3-ai-real-estate' ) }
					</Button>
				}
			/>
			<div className="dev3re-admin-editor">
				<div className="dev3re-admin-editor__main">
					<Card title={ __( 'Photos', 'dev3-ai-real-estate' ) } description={ __( 'The first photo is the cover. Use the arrows to reorder.', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" icon="plus" onClick={ () => openMedia( { multiple: true, title: __( 'Add photos', 'dev3-ai-real-estate' ), onSelect: ( items ) => set( 'images' )( [ ...form.images, ...items.filter( ( it ) => ! form.images.some( ( x ) => x.id === it.id ) ) ] ) } ) }>{ __( 'Add photos', 'dev3-ai-real-estate' ) }</Button> }>
						{ form.images.length ? (
							<ul className="dev3re-admin-gallery">
								{ form.images.map( ( img, i ) => (
									<li key={ img.id }>
										<img src={ img.url } alt="" />
										{ i === 0 && <span className="dev3re-admin-gallery__cover">{ __( 'Cover', 'dev3-ai-real-estate' ) }</span> }
										<span className="dev3re-admin-gallery__btns">
											<button type="button" onClick={ () => moveImage( i, -1 ) } disabled={ i === 0 } aria-label={ __( 'Move left', 'dev3-ai-real-estate' ) }>‹</button>
											<button type="button" onClick={ () => moveImage( i, 1 ) } disabled={ i === form.images.length - 1 } aria-label={ __( 'Move right', 'dev3-ai-real-estate' ) }>›</button>
											<button type="button" onClick={ () => set( 'images' )( form.images.filter( ( x ) => x.id !== img.id ) ) } aria-label={ __( 'Remove photo', 'dev3-ai-real-estate' ) }>✕</button>
										</span>
									</li>
								) ) }
							</ul>
						) : (
							<button type="button" className="dev3re-admin-dropzone" onClick={ () => openMedia( { multiple: true, title: __( 'Add photos', 'dev3-ai-real-estate' ), onSelect: ( items ) => set( 'images' )( items ) } ) }>
								<Icon name="plus" size={ 22 } />
								<span>{ __( 'Choose photos from the media library', 'dev3-ai-real-estate' ) }</span>
							</button>
						) }
					</Card>

					<Card title={ __( 'Basics', 'dev3-ai-real-estate' ) }>
						<TextField label={ __( 'Title', 'dev3-ai-real-estate' ) } value={ form.title } onChange={ set( 'title' ) } required maxLength={ 200 } />
						<FormGrid>
							<Select label={ __( 'Listing type', 'dev3-ai-real-estate' ) } value={ f.listing_type } onChange={ setF( 'listing_type' ) } options={ [ { value: 'sale', label: __( 'For sale', 'dev3-ai-real-estate' ) }, { value: 'rent', label: __( 'For rent', 'dev3-ai-real-estate' ) } ] } />
							<Select label={ __( 'Property type', 'dev3-ai-real-estate' ) } value={ form.type } onChange={ set( 'type' ) } options={ termOptions( meta.types, __( 'Choose…', 'dev3-ai-real-estate' ) ) } />
							<Select label={ __( 'Availability', 'dev3-ai-real-estate' ) } value={ f.market } onChange={ setF( 'market' ) } options={ Object.keys( MARKET ).map( ( k ) => ( { value: k, label: MARKET[ k ] } ) ) } />
						</FormGrid>
						<Textarea label={ __( 'Description', 'dev3-ai-real-estate' ) } value={ form.content } onChange={ set( 'content' ) } rows={ 9 } help={ __( 'Separate paragraphs with a blank line.', 'dev3-ai-real-estate' ) } />
						<Textarea label={ __( 'Summary for cards', 'dev3-ai-real-estate' ) } value={ form.excerpt } onChange={ set( 'excerpt' ) } rows={ 2 } maxLength={ 300 } />
						<Textarea label={ __( 'Highlights (one per line)', 'dev3-ai-real-estate' ) } value={ form.highlights } onChange={ set( 'highlights' ) } rows={ 4 } help={ __( 'Shown as ticks at the top of the listing. Up to 6.', 'dev3-ai-real-estate' ) } />
					</Card>

					<Card tone="ai" title={ __( 'DEV3 AI · Description from photos', 'dev3-ai-real-estate' ) } description={ __( 'Reads up to 8 photos plus your notes and the facts, then drafts the title, summary, description and highlights. Nothing changes until you accept.', 'dev3-ai-real-estate' ) }>
						<Textarea label={ __( 'Notes for the writer (private)', 'dev3-ai-real-estate' ) } value={ f.notes } onChange={ setF( 'notes' ) } rows={ 3 } placeholder={ __( 'e.g. new roof 2022, quiet cul-de-sac, 5 minutes to the station', 'dev3-ai-real-estate' ) } />
						<div className="dev3re-admin-airow">
							<Select label={ __( 'Tone', 'dev3-ai-real-estate' ) } value={ tone } onChange={ setTone } options={ [ { value: '', label: __( 'Site default', 'dev3-ai-real-estate' ) }, { value: 'warm', label: __( 'Warm', 'dev3-ai-real-estate' ) }, { value: 'professional', label: __( 'Professional', 'dev3-ai-real-estate' ) }, { value: 'luxury', label: __( 'Luxury', 'dev3-ai-real-estate' ) }, { value: 'concise', label: __( 'Concise', 'dev3-ai-real-estate' ) } ] } />
							<AiButton loading={ desc.busy === 'ai' } disabled={ ! aiReady || !! desc.busy } onClick={ () => describe( 'ai' ) }>
								{ sprintf( /* translators: %d: photos */ __( 'Write from %d photos with DEV3 AI', 'dev3-ai-real-estate' ), Math.min( 8, form.images.length ) ) }
							</AiButton>
							<Button variant="ghost" onClick={ () => describe( 'template' ) } loading={ desc.busy === 'template' } disabled={ !! desc.busy }>
								{ __( 'Start from the facts (no AI)', 'dev3-ai-real-estate' ) }
							</Button>
						</div>
						{ ! aiReady && <Notice tone="info">{ __( 'Add a Google Gemini API key in API Config to write from photos. The facts-only draft always works.', 'dev3-ai-real-estate' ) }</Notice> }
						<ErrorNotice error={ desc.error } onRetry={ () => describe( 'ai' ) } settingsHref="#/api" />
						{ desc.draft && desc.draft.observations && desc.draft.observations.length > 0 && (
							<Notice tone="ai" title={ __( 'What DEV3 AI saw in the photos', 'dev3-ai-real-estate' ) }>
								<ul className="dev3re-admin-list">{ desc.draft.observations.map( ( o, i ) => <li key={ i }>{ o }</li> ) }</ul>
							</Notice>
						) }
						{ descDraft && (
							<AiDraftReview
								fields={ DESC_FIELDS }
								draft={ descDraft }
								original={ descOriginal }
								meta={ { model: desc.draft.model || __( 'Template', 'dev3-ai-real-estate' ), tokens: desc.draft.tokens, cached: desc.draft.cached } }
								acceptLabel={ __( 'Use in the form', 'dev3-ai-real-estate' ) }
								onAccept={ acceptDesc }
								onReject={ () => setDesc( { busy: false, draft: null, error: null } ) }
								onRegenerate={ () => describe( desc.draft.fallback ? 'template' : 'ai' ) }
								regenerating={ !! desc.busy }
							/>
						) }
					</Card>

					<Card title={ __( 'Price and details', 'dev3-ai-real-estate' ) }>
						<FormGrid>
							<NumberField label={ sprintf( /* translators: %s: currency */ __( 'Price (%s)', 'dev3-ai-real-estate' ), meta.currency ) } value={ f.price } onChange={ setF( 'price' ) } min={ 0 } step={ 1 } />
							{ f.listing_type === 'rent' && (
								<Select label={ __( 'Rent per', 'dev3-ai-real-estate' ) } value={ f.price_period } onChange={ setF( 'price_period' ) } options={ [ { value: 'month', label: __( 'Month', 'dev3-ai-real-estate' ) }, { value: 'week', label: __( 'Week', 'dev3-ai-real-estate' ) }, { value: 'day', label: __( 'Day', 'dev3-ai-real-estate' ) }, { value: 'year', label: __( 'Year', 'dev3-ai-real-estate' ) } ] } />
							) }
							<TextField label={ __( 'Before price', 'dev3-ai-real-estate' ) } value={ f.price_prefix } onChange={ setF( 'price_prefix' ) } placeholder={ __( 'e.g. From', 'dev3-ai-real-estate' ) } maxLength={ 30 } />
							<TextField label={ __( 'After price', 'dev3-ai-real-estate' ) } value={ f.price_suffix } onChange={ setF( 'price_suffix' ) } placeholder={ __( 'e.g. negotiable', 'dev3-ai-real-estate' ) } maxLength={ 30 } />
						</FormGrid>
						<Toggle label={ __( 'Show “Price on request” instead of the price', 'dev3-ai-real-estate' ) } checked={ !! f.price_on_request } onChange={ setF( 'price_on_request' ) } />
						<FormGrid>
							<NumberField label={ __( 'Bedrooms', 'dev3-ai-real-estate' ) } value={ f.beds } onChange={ setF( 'beds' ) } min={ 0 } max={ 50 } />
							<NumberField label={ __( 'Bathrooms', 'dev3-ai-real-estate' ) } value={ f.baths } onChange={ setF( 'baths' ) } min={ 0 } max={ 50 } step={ 0.5 } />
							<NumberField label={ __( 'Parking spaces', 'dev3-ai-real-estate' ) } value={ f.garages } onChange={ setF( 'garages' ) } min={ 0 } max={ 50 } />
							<NumberField label={ sprintf( /* translators: %s: unit */ __( 'Living area (%s)', 'dev3-ai-real-estate' ), f.area_unit ? ( f.area_unit === 'sqm' ? 'm²' : 'ft²' ) : unit ) } value={ f.area } onChange={ setF( 'area' ) } min={ 0 } />
							<Select label={ __( 'Area unit', 'dev3-ai-real-estate' ) } value={ f.area_unit } onChange={ setF( 'area_unit' ) } options={ [ { value: '', label: sprintf( /* translators: %s: unit */ __( 'Site default (%s)', 'dev3-ai-real-estate' ), unit ) }, { value: 'sqm', label: 'm²' }, { value: 'sqft', label: 'ft²' } ] } />
							<NumberField label={ sprintf( /* translators: %s: unit */ __( 'Lot size (%s)', 'dev3-ai-real-estate' ), unit ) } value={ f.lot_size } onChange={ setF( 'lot_size' ) } min={ 0 } />
							<NumberField label={ __( 'Year built', 'dev3-ai-real-estate' ) } value={ f.year_built } onChange={ setF( 'year_built' ) } min={ 1700 } max={ 2100 } />
							<NumberField label={ __( 'Floor', 'dev3-ai-real-estate' ) } value={ f.floor } onChange={ setF( 'floor' ) } min={ 0 } max={ 300 } />
							<NumberField label={ __( 'Floors in building', 'dev3-ai-real-estate' ) } value={ f.floors } onChange={ setF( 'floors' ) } min={ 0 } max={ 300 } />
							<Select label={ __( 'Energy class', 'dev3-ai-real-estate' ) } value={ f.energy_class } onChange={ setF( 'energy_class' ) } options={ [ '', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ].map( ( v ) => ( { value: v, label: v || '—' } ) ) } />
							<TextField label={ __( 'Reference', 'dev3-ai-real-estate' ) } value={ f.reference } onChange={ setF( 'reference' ) } maxLength={ 40 } />
						</FormGrid>
					</Card>

					<Card title={ __( 'Features', 'dev3-ai-real-estate' ) } description={ __( 'Manage the list in Settings → Vocabulary.', 'dev3-ai-real-estate' ) }>
						<div className="dev3re-admin-checks">
							{ meta.features.map( ( t ) => (
								<Checkbox key={ t.id } label={ t.name } checked={ form.features.includes( t.id ) } onChange={ ( on ) => set( 'features' )( on ? [ ...form.features, t.id ] : form.features.filter( ( x ) => x !== t.id ) ) } />
							) ) }
						</div>
					</Card>

					<Card title={ __( 'Location', 'dev3-ai-real-estate' ) }>
						<TextField label={ __( 'Street address', 'dev3-ai-real-estate' ) } value={ f.street } onChange={ setF( 'street' ) } />
						<FormGrid>
							<TextField label={ __( 'City', 'dev3-ai-real-estate' ) } value={ f.city } onChange={ setF( 'city' ) } />
							<TextField label={ __( 'State / region', 'dev3-ai-real-estate' ) } value={ f.region } onChange={ setF( 'region' ) } />
							<TextField label={ __( 'Postcode', 'dev3-ai-real-estate' ) } value={ f.postcode } onChange={ setF( 'postcode' ) } />
							<TextField label={ __( 'Country', 'dev3-ai-real-estate' ) } value={ f.country } onChange={ setF( 'country' ) } />
							<Select label={ __( 'Area for search', 'dev3-ai-real-estate' ) } value={ form.location } onChange={ set( 'location' ) } options={ termOptions( meta.locations, __( 'Automatic (from the city)', 'dev3-ai-real-estate' ) ) } />
						</FormGrid>
						<div className="dev3re-admin-airow">
							<Button icon="search" onClick={ geocode } loading={ geo }>
								{ __( 'Find on map', 'dev3-ai-real-estate' ) }
							</Button>
							<span className="dev3re-admin-muted">{ f.lat !== '' && f.lat !== null ? `${ Number( f.lat ).toFixed( 5 ) }, ${ Number( f.lng ).toFixed( 5 ) }` : __( 'No pin yet: click the map or find the address.', 'dev3-ai-real-estate' ) }</span>
						</div>
						<MapPicker lat={ f.lat } lng={ f.lng } label={ __( 'Map: click or drag the pin to set the location', 'dev3-ai-real-estate' ) } onChange={ ( la, ln ) => setForm( ( prev ) => ( { ...prev, fields: { ...prev.fields, lat: Number( la ).toFixed( 7 ), lng: Number( ln ).toFixed( 7 ) } } ) ) } />
						<Toggle label={ __( 'Hide the exact address (show the area only)', 'dev3-ai-real-estate' ) } checked={ !! f.hide_address } onChange={ setF( 'hide_address' ) } />
					</Card>

					<Card tone="ai" title={ __( 'DEV3 AI · Neighbourhood highlights', 'dev3-ai-real-estate' ) } description={ __( 'Looks up schools, transport, parks, shops and more within 1 km on OpenStreetMap, then writes a short, factual overview. AI text is labelled on the listing.', 'dev3-ai-real-estate' ) }>
						<div className="dev3re-admin-airow">
							<AiButton loading={ hood.busy === 'ai' } disabled={ ! aiReady || !! hood.busy } onClick={ () => neighbourhood( true ) }>
								{ __( 'Suggest with DEV3 AI', 'dev3-ai-real-estate' ) }
							</AiButton>
							<Button variant="ghost" loading={ hood.busy === 'facts' } disabled={ !! hood.busy } onClick={ () => neighbourhood( false ) }>
								{ __( 'List nearby places (no AI)', 'dev3-ai-real-estate' ) }
							</Button>
						</div>
						<ErrorNotice error={ hood.error } onRetry={ () => neighbourhood( true ) } settingsHref="#/api" />
						{ hood.draft && (
							<AiDraftReview
								fields={ HOOD_FIELDS }
								draft={ hood.draft.draft }
								meta={ { model: hood.draft.model || 'OpenStreetMap', tokens: hood.draft.tokens, cached: hood.draft.cached } }
								acceptLabel={ __( 'Use in the form', 'dev3-ai-real-estate' ) }
								onAccept={ acceptHood }
								onReject={ () => setHood( { busy: false, draft: null, error: null } ) }
								onRegenerate={ () => neighbourhood( !! hood.draft.ai ) }
								regenerating={ !! hood.busy }
							/>
						) }
						<Textarea label={ __( 'Neighbourhood text', 'dev3-ai-real-estate' ) } value={ f.neighbourhood } onChange={ setF( 'neighbourhood' ) } rows={ 5 } />
						<Toggle label={ __( 'Label as AI-generated on the listing', 'dev3-ai-real-estate' ) } help={ __( 'Turn off once you have checked and edited the text yourself.', 'dev3-ai-real-estate' ) } checked={ !! f.neighbourhood_ai } onChange={ setF( 'neighbourhood_ai' ) } />
						<Textarea label={ __( 'Nearby places (one per line, from OpenStreetMap)', 'dev3-ai-real-estate' ) } value={ form.nearby } onChange={ set( 'nearby' ) } rows={ 4 } />
					</Card>

					<Card title={ __( 'Floor plans', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" icon="plus" onClick={ () => setF( 'floor_plans' )( [ ...plans, { title: '', image: 0, image_url: '', beds: '', baths: '', area: '', notes: '' } ] ) }>{ __( 'Add plan', 'dev3-ai-real-estate' ) }</Button> }>
						{ ! plans.length && <p className="dev3re-admin-muted">{ __( 'No floor plans yet.', 'dev3-ai-real-estate' ) }</p> }
						{ plans.map( ( p, i ) => (
							<div key={ i } className="dev3re-admin-plan">
								<div className="dev3re-admin-plan__img">
									{ p.image_url ? <img src={ p.image_url } alt="" /> : <span className="dev3re-admin-prop__ph" /> }
									<Button size="sm" variant="ghost" onClick={ () => openMedia( { title: __( 'Floor plan image', 'dev3-ai-real-estate' ), onSelect: ( items ) => items[ 0 ] && setF( 'floor_plans' )( plans.map( ( x, j ) => ( j === i ? { ...x, image: items[ 0 ].id, image_url: items[ 0 ].url } : x ) ) ) } ) }>
										{ p.image ? __( 'Change image', 'dev3-ai-real-estate' ) : __( 'Choose image', 'dev3-ai-real-estate' ) }
									</Button>
								</div>
								<FormGrid>
									<TextField label={ __( 'Plan title', 'dev3-ai-real-estate' ) } value={ p.title } onChange={ ( v ) => setPlan( i, 'title', v ) } placeholder={ __( 'Ground floor', 'dev3-ai-real-estate' ) } />
									<NumberField label={ __( 'Bedrooms', 'dev3-ai-real-estate' ) } value={ zero( p.beds ) } onChange={ ( v ) => setPlan( i, 'beds', v ) } min={ 0 } />
									<NumberField label={ __( 'Bathrooms', 'dev3-ai-real-estate' ) } value={ zero( p.baths ) } onChange={ ( v ) => setPlan( i, 'baths', v ) } min={ 0 } step={ 0.5 } />
									<NumberField label={ sprintf( /* translators: %s: unit */ __( 'Area (%s)', 'dev3-ai-real-estate' ), unit ) } value={ zero( p.area ) } onChange={ ( v ) => setPlan( i, 'area', v ) } min={ 0 } />
								</FormGrid>
								<Textarea label={ __( 'Notes', 'dev3-ai-real-estate' ) } value={ p.notes } onChange={ ( v ) => setPlan( i, 'notes', v ) } rows={ 2 } />
								<Button size="sm" variant="ghost" icon="trash" onClick={ () => setF( 'floor_plans' )( plans.filter( ( x, j ) => j !== i ) ) }>
									{ __( 'Remove plan', 'dev3-ai-real-estate' ) }
								</Button>
							</div>
						) ) }
					</Card>

					<Card title={ __( 'Virtual tour and video', 'dev3-ai-real-estate' ) } description={ __( 'Matterport, Kuula, CloudPano, Giraffe360, YouTube, Vimeo or a Google Maps embed link. Visitors load it with one click.', 'dev3-ai-real-estate' ) }>
						<FormGrid>
							<TextField label={ __( 'Virtual tour link', 'dev3-ai-real-estate' ) } type="url" value={ f.tour_url } onChange={ setF( 'tour_url' ) } placeholder="https://my.matterport.com/show/?m=…" />
							<TextField label={ __( 'Video link', 'dev3-ai-real-estate' ) } type="url" value={ f.video_url } onChange={ setF( 'video_url' ) } placeholder="https://www.youtube.com/watch?v=…" />
						</FormGrid>
					</Card>
				</div>

				<aside className="dev3re-admin-editor__side">
					<Card title={ __( 'Publish', 'dev3-ai-real-estate' ) } footer={ <Button variant="primary" type="submit" loading={ saving } block>{ __( 'Save property', 'dev3-ai-real-estate' ) }</Button> }>
						{ record && (
							<p className="dev3re-admin-inline">
								<StatusBadge status={ record.status } />
								{ f.featured && <Badge tone="warning">{ __( 'Featured', 'dev3-ai-real-estate' ) }</Badge> }
							</p>
						) }
						<Select label={ __( 'Status', 'dev3-ai-real-estate' ) } value={ form.status } onChange={ set( 'status' ) } options={ [ { value: 'publish', label: __( 'Live', 'dev3-ai-real-estate' ) }, { value: 'pending', label: __( 'In review', 'dev3-ai-real-estate' ) }, { value: 'draft', label: __( 'Draft', 'dev3-ai-real-estate' ) }, { value: 'private', label: __( 'Private', 'dev3-ai-real-estate' ) } ] } />
						<Select label={ __( 'Agent', 'dev3-ai-real-estate' ) } value={ String( f.agent_id || '' ) } onChange={ ( v ) => setF( 'agent_id' )( Number( v ) || 0 ) } options={ [ { value: '', label: __( 'No agent (site contact)', 'dev3-ai-real-estate' ) }, ...meta.agents.map( ( a ) => ( { value: String( a.value ), label: a.label } ) ) ] } />
						<Toggle label={ __( 'Featured', 'dev3-ai-real-estate' ) } help={ __( 'Shown first in search and in “Featured” grids.', 'dev3-ai-real-estate' ) } checked={ !! f.featured } onChange={ setF( 'featured' ) } />
						{ record && (
							<dl className="dev3re-admin-dl">
								<div><dt>{ __( 'Views', 'dev3-ai-real-estate' ) }</dt><dd>{ record.views }</dd></div>
								<div><dt>{ __( 'Leads', 'dev3-ai-real-estate' ) }</dt><dd><a href="#/leads">{ record.leads }</a></dd></div>
							</dl>
						) }
					</Card>
					{ id > 0 && (
						<Button variant="ghost" icon="trash" onClick={ () => setConfirmDelete( true ) } block>
							{ __( 'Move to trash', 'dev3-ai-real-estate' ) }
						</Button>
					) }
				</aside>
			</div>
			<Modal
				open={ confirmDelete }
				onClose={ () => setConfirmDelete( false ) }
				size="sm"
				title={ __( 'Move this property to the trash?', 'dev3-ai-real-estate' ) }
				description={ __( 'It disappears from the site. You can restore it from the WordPress trash.', 'dev3-ai-real-estate' ) }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setConfirmDelete( false ) } data-autofocus>
							{ __( 'Cancel', 'dev3-ai-real-estate' ) }
						</Button>
						<Button variant="danger" onClick={ remove }>
							{ __( 'Move to trash', 'dev3-ai-real-estate' ) }
						</Button>
					</>
				}
			/>
		</form>
	);
}
