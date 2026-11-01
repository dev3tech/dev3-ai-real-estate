import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, Checkbox, ErrorNotice, Inline, PageHeader, ResponsiveTable, Select, Tabs, TextField, useToast } from '../core3/ui';
import { MARKET, StatusBadge } from './shared';

const PER_PAGE = 20;

/**
 * Properties list with status tabs, search, filters and bulk actions.
 *
 * @param {Object}   props          Props.
 * @param {Function} props.navigate Navigate.
 * @return {Element} List.
 */
export default function Properties( { navigate } ) {
	const toast = useToast();
	const [ status, setStatus ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ deal, setDeal ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ bulk, setBulk ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = () => {
		setError( null );
		const q = new URLSearchParams( { page: String( page ), per_page: String( PER_PAGE ), status, search, listing_type: deal } );
		request( `admin/properties?${ q.toString() }` )
			.then( ( res ) => {
				setData( res );
				setSelected( [] );
			} )
			.catch( setError );
	};
	useEffect( () => {
		const t = setTimeout( load, search ? 300 : 0 );
		return () => clearTimeout( t );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ status, search, deal, page ] );

	const runBulk = () => {
		if ( ! bulk || ! selected.length ) {
			return;
		}
		setBusy( true );
		request( 'admin/properties/bulk', { method: 'POST', data: { ids: selected, action: bulk } } )
			.then( ( res ) => {
				toast.success( sprintf( /* translators: %d: count */ __( '%d properties updated.', 'dev3-ai-real-estate' ), res.done ) );
				setBulk( '' );
				load();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};

	const counts = data ? data.counts : {};
	const all = data ? data.items.map( ( r ) => r.id ) : [];
	const toggle = ( id ) => setSelected( selected.includes( id ) ? selected.filter( ( x ) => x !== id ) : [ ...selected, id ] );

	return (
		<>
			<PageHeader
				title={ __( 'Properties', 'dev3-ai-real-estate' ) }
				description={ __( 'Every listing on the site, including drafts and those waiting for review.', 'dev3-ai-real-estate' ) }
				actions={
					<Button variant="primary" icon="plus" href="#/properties/new">
						{ __( 'Add property', 'dev3-ai-real-estate' ) }
					</Button>
				}
			/>
			<div className="dev3re-admin-scrolltabs">
				<Tabs
					label={ __( 'Status', 'dev3-ai-real-estate' ) }
					value={ status }
					onChange={ ( v ) => {
						setStatus( v );
						setPage( 1 );
					} }
					tabs={ [
						{ id: '', label: __( 'All', 'dev3-ai-real-estate' ) },
						{ id: 'publish', label: __( 'Live', 'dev3-ai-real-estate' ), badge: counts.publish },
						{ id: 'pending', label: __( 'In review', 'dev3-ai-real-estate' ), badge: counts.pending },
						{ id: 'draft', label: __( 'Drafts', 'dev3-ai-real-estate' ), badge: counts.draft },
					] }
				/>
			</div>
			<Card flush style={ { marginTop: 12 } }>
				<div className="dev3re-admin-toolbar">
					<TextField label={ __( 'Search properties', 'dev3-ai-real-estate' ) } hideLabel placeholder={ __( 'Search by title or address…', 'dev3-ai-real-estate' ) } value={ search } onChange={ ( v ) => {
						setSearch( v );
						setPage( 1 );
					} } type="search" />
					<Select label={ __( 'Listing type', 'dev3-ai-real-estate' ) } hideLabel value={ deal } onChange={ ( v ) => {
						setDeal( v );
						setPage( 1 );
					} } options={ [ { value: '', label: __( 'Sale and rent', 'dev3-ai-real-estate' ) }, { value: 'sale', label: __( 'For sale', 'dev3-ai-real-estate' ) }, { value: 'rent', label: __( 'For rent', 'dev3-ai-real-estate' ) } ] } />
					<Inline>
						<Select label={ __( 'Bulk action', 'dev3-ai-real-estate' ) } hideLabel value={ bulk } onChange={ setBulk } placeholder={ sprintf( /* translators: %d: selected */ __( 'Bulk action (%d)', 'dev3-ai-real-estate' ), selected.length ) } options={ [
							{ value: 'publish', label: __( 'Publish', 'dev3-ai-real-estate' ) },
							{ value: 'pending', label: __( 'Move to review', 'dev3-ai-real-estate' ) },
							{ value: 'draft', label: __( 'Move to drafts', 'dev3-ai-real-estate' ) },
							{ value: 'feature', label: __( 'Mark featured', 'dev3-ai-real-estate' ) },
							{ value: 'unfeature', label: __( 'Remove featured', 'dev3-ai-real-estate' ) },
							{ value: 'trash', label: __( 'Move to trash', 'dev3-ai-real-estate' ) },
						] } />
						<Button onClick={ runBulk } disabled={ ! bulk || ! selected.length } loading={ busy }>
							{ __( 'Apply', 'dev3-ai-real-estate' ) }
						</Button>
					</Inline>
				</div>
				{ error && <ErrorNotice error={ error } onRetry={ load } /> }
				<ResponsiveTable
					loading={ ! data }
					rows={ data ? data.items : [] }
					onRowClick={ ( r ) => navigate( `properties/${ r.id }` ) }
					pagination={ data ? { page, perPage: PER_PAGE, total: data.total, onPageChange: setPage } : undefined }
					columns={ [
						{ key: 'sel', label: __( 'Select', 'dev3-ai-real-estate' ), width: 44, hideOnMobile: true, render: ( r ) => (
							// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
							<span onClick={ ( e ) => e.stopPropagation() }>
								<Checkbox label={ sprintf( /* translators: %s: title */ __( 'Select %s', 'dev3-ai-real-estate' ), r.title ) } checked={ selected.includes( r.id ) } onChange={ () => toggle( r.id ) } className="dev3re-admin-hidelabel" />
							</span>
						) },
						{ key: 'title', label: __( 'Property', 'dev3-ai-real-estate' ), primary: true, render: ( r ) => (
							<span className="dev3re-admin-prop is-wide">
								{ r.image ? <img src={ r.image } alt="" width="56" height="42" /> : <span className="dev3re-admin-prop__ph" /> }
								<span>
									<strong>{ r.title }</strong>
									{ r.featured && <Badge tone="warning">{ __( 'Featured', 'dev3-ai-real-estate' ) }</Badge> }
									<br />
									<span className="dev3re-admin-muted">{ [ r.type, r.city, r.agent ].filter( Boolean ).join( ' · ' ) || '—' }</span>
								</span>
							</span>
						) },
						{ key: 'price', label: __( 'Price', 'dev3-ai-real-estate' ), nowrap: true, render: ( r ) => (
							<>
								{ r.price }
								<br />
								<span className="dev3re-admin-muted">{ r.listing_type === 'rent' ? __( 'For rent', 'dev3-ai-real-estate' ) : __( 'For sale', 'dev3-ai-real-estate' ) }{ r.market !== 'available' ? ' · ' + MARKET[ r.market ] : '' }</span>
							</>
						) },
						{ key: 'facts', label: __( 'Beds / baths / area', 'dev3-ai-real-estate' ), hideOnMobile: true, render: ( r ) => [ r.beds ? r.beds + ' bd' : '', r.baths ? r.baths + ' ba' : '', r.area ].filter( Boolean ).join( ' · ' ) || '—' },
						{ key: 'status', label: __( 'Status', 'dev3-ai-real-estate' ), render: ( r ) => <StatusBadge status={ r.status } /> },
						{ key: 'stats', label: __( 'Views / leads', 'dev3-ai-real-estate' ), align: 'end', hideOnMobile: true, render: ( r ) => `${ r.views } / ${ r.leads }` },
					] }
					rowActions={ ( r ) => (
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
						<span className="dev3re-admin-rowacts" onClick={ ( e ) => e.stopPropagation() }>
							<Button size="sm" variant="ghost" icon="edit" href={ `#/properties/${ r.id }` } label={ __( 'Edit', 'dev3-ai-real-estate' ) } />
							<Button size="sm" variant="ghost" icon="external" href={ r.url } target="_blank" label={ __( 'View on site', 'dev3-ai-real-estate' ) } />
						</span>
					) }
					empty={ {
						icon: 'box',
						title: __( 'No properties found', 'dev3-ai-real-estate' ),
						description: search ? __( 'Try another search.', 'dev3-ai-real-estate' ) : __( 'Add your first property: upload photos and let DEV3 AI write the description.', 'dev3-ai-real-estate' ),
						action: (
							<Button variant="primary" icon="plus" href="#/properties/new">
								{ __( 'Add property', 'dev3-ai-real-estate' ) }
							</Button>
						),
					} }
				/>
				{ data && data.items.length > 0 && (
					<div className="dev3re-admin-tablefoot">
						<Checkbox label={ __( 'Select all on this page', 'dev3-ai-real-estate' ) } checked={ selected.length === all.length && all.length > 0 } onChange={ ( v ) => setSelected( v ? all : [] ) } />
					</div>
				) }
			</Card>
		</>
	);
}
