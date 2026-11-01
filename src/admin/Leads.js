import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import { Button, Card, Drawer, ErrorNotice, PageHeader, ResponsiveTable, Select, Tabs, Textarea, TextField, useToast } from '../core3/ui';
import { LEAD_STATUS, LeadBadge, formatDate, useApp } from './shared';

const PER_PAGE = 20;

/**
 * Lead inbox: enquiries and viewing requests.
 *
 * @param {Object}   props          Props.
 * @param {Function} props.onChange New-lead count changed.
 * @return {Element} Screen.
 */
export default function Leads( { onChange } ) {
	const toast = useToast();
	const { meta } = useApp();
	const [ status, setStatus ] = useState( '' );
	const [ agent, setAgent ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ open, setOpen ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const load = () => {
		const q = new URLSearchParams( { status, agent, search, page: String( page ), per_page: String( PER_PAGE ) } );
		return request( `admin/leads?${ q.toString() }` )
			.then( ( res ) => {
				setData( res );
				onChange( res.counts.new || 0 );
			} )
			.catch( setError );
	};
	useEffect( () => {
		const t = setTimeout( load, search ? 300 : 0 );
		return () => clearTimeout( t );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ status, agent, search, page ] );

	const update = ( patch ) => {
		setSaving( true );
		return request( `admin/leads/${ open.id }`, { method: 'POST', data: patch } )
			.then( ( lead ) => {
				setOpen( lead );
				toast.success( __( 'Lead updated.', 'dev3-ai-real-estate' ) );
				load();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setSaving( false ) );
	};
	const remove = () =>
		request( `admin/leads/${ open.id }`, { method: 'DELETE' } )
			.then( () => {
				setOpen( null );
				toast.success( __( 'Lead deleted.', 'dev3-ai-real-estate' ) );
				load();
			} )
			.catch( ( err ) => toast.error( err ) );

	const counts = data ? data.counts : {};
	const exportUrl = config.exportUrl ? `${ config.exportUrl }&status=${ encodeURIComponent( status ) }&search=${ encodeURIComponent( search ) }` : '';

	return (
		<>
			<PageHeader
				title={ __( 'Leads', 'dev3-ai-real-estate' ) }
				description={ __( 'Enquiries and viewing requests from property and agent pages. Agents also see their own leads in the front-end dashboard.', 'dev3-ai-real-estate' ) }
				actions={
					exportUrl && (
						<Button icon="file" href={ exportUrl }>
							{ __( 'Export CSV', 'dev3-ai-real-estate' ) }
						</Button>
					)
				}
			/>
			<div className="dev3re-admin-scrolltabs">
				<Tabs
					label={ __( 'Lead status', 'dev3-ai-real-estate' ) }
					value={ status }
					onChange={ ( v ) => {
						setStatus( v );
						setPage( 1 );
					} }
					tabs={ [ { id: '', label: __( 'All', 'dev3-ai-real-estate' ) }, ...Object.keys( LEAD_STATUS ).map( ( k ) => ( { id: k, label: LEAD_STATUS[ k ].label, badge: counts[ k ] || undefined } ) ) ] }
				/>
			</div>
			{ error && <ErrorNotice error={ error } onRetry={ load } /> }
			<Card flush style={ { marginTop: 12 } }>
				<div className="dev3re-admin-toolbar">
					<TextField label={ __( 'Search leads', 'dev3-ai-real-estate' ) } hideLabel type="search" placeholder={ __( 'Name, email or message…', 'dev3-ai-real-estate' ) } value={ search } onChange={ ( v ) => {
						setSearch( v );
						setPage( 1 );
					} } />
					<Select label={ __( 'Agent', 'dev3-ai-real-estate' ) } hideLabel value={ agent } onChange={ ( v ) => {
						setAgent( v );
						setPage( 1 );
					} } options={ [ { value: '', label: __( 'All agents', 'dev3-ai-real-estate' ) }, ...meta.agents.map( ( a ) => ( { value: String( a.value ), label: a.label } ) ) ] } />
				</div>
				<ResponsiveTable
					loading={ ! data }
					rows={ data ? data.items : [] }
					onRowClick={ setOpen }
					pagination={ data ? { page, perPage: PER_PAGE, total: data.total, onPageChange: setPage } : undefined }
					columns={ [
						{ key: 'name', label: __( 'From', 'dev3-ai-real-estate' ), primary: true, render: ( r ) => (
							<span>
								<strong>{ r.name }</strong>
								<br />
								<span className="dev3re-admin-muted">{ r.email }</span>
							</span>
						) },
						{ key: 'kind', label: __( 'Request', 'dev3-ai-real-estate' ), render: ( r ) => ( r.kind === 'viewing' ? __( 'Viewing', 'dev3-ai-real-estate' ) + ( r.viewing_at ? ' · ' + r.viewing_at : '' ) : __( 'Enquiry', 'dev3-ai-real-estate' ) ) },
						{ key: 'property', label: __( 'Property / agent', 'dev3-ai-real-estate' ), hideOnMobile: true, render: ( r ) => (
							<span>
								{ r.property || '—' }
								<br />
								<span className="dev3re-admin-muted">{ r.agent || __( 'Site contact', 'dev3-ai-real-estate' ) }</span>
							</span>
						) },
						{ key: 'status', label: __( 'Status', 'dev3-ai-real-estate' ), render: ( r ) => <LeadBadge status={ r.status } /> },
						{ key: 'created_at', label: __( 'Received', 'dev3-ai-real-estate' ), nowrap: true, render: ( r ) => formatDate( r.created_at, true ) },
					] }
					empty={ { icon: 'inbox', title: __( 'No leads here', 'dev3-ai-real-estate' ), description: __( 'When visitors send an enquiry or ask for a viewing, it appears here and is emailed to the agent.', 'dev3-ai-real-estate' ) } }
				/>
			</Card>
			<Drawer
				open={ !! open }
				onClose={ () => setOpen( null ) }
				size="lg"
				title={ open ? open.name : '' }
				description={ open ? formatDate( open.created_at, true ) : '' }
				footer={
					open && (
						<>
							<Button variant="ghost" icon="trash" onClick={ remove }>
								{ __( 'Delete', 'dev3-ai-real-estate' ) }
							</Button>
							<Button variant="primary" href={ `mailto:${ open.email }?subject=${ encodeURIComponent( open.property || '' ) }` }>
								{ __( 'Reply by email', 'dev3-ai-real-estate' ) }
							</Button>
						</>
					)
				}
			>
				{ open && (
					<div className="dev3re-admin-stack">
						<dl className="dev3re-admin-dl">
							<div><dt>{ __( 'Email', 'dev3-ai-real-estate' ) }</dt><dd><a href={ `mailto:${ open.email }` }>{ open.email }</a></dd></div>
							{ open.phone && <div><dt>{ __( 'Phone', 'dev3-ai-real-estate' ) }</dt><dd><a href={ `tel:${ open.phone }` }>{ open.phone }</a></dd></div> }
							<div><dt>{ __( 'Request', 'dev3-ai-real-estate' ) }</dt><dd>{ open.kind === 'viewing' ? __( 'Viewing', 'dev3-ai-real-estate' ) + ( open.viewing_at ? ' · ' + open.viewing_at : '' ) : __( 'Enquiry', 'dev3-ai-real-estate' ) }</dd></div>
							{ open.property && <div><dt>{ __( 'Property', 'dev3-ai-real-estate' ) }</dt><dd><a href={ open.property_url } target="_blank" rel="noreferrer">{ open.property }</a></dd></div> }
							<div><dt>{ __( 'Agent', 'dev3-ai-real-estate' ) }</dt><dd>{ open.agent || __( 'Site contact', 'dev3-ai-real-estate' ) }</dd></div>
						</dl>
						{ open.message && <blockquote className="dev3re-admin-quote">{ open.message }</blockquote> }
						<Select label={ __( 'Status', 'dev3-ai-real-estate' ) } value={ open.status } onChange={ ( v ) => update( { status: v } ) } options={ Object.keys( LEAD_STATUS ).map( ( k ) => ( { value: k, label: LEAD_STATUS[ k ].label } ) ) } disabled={ saving } />
						<Textarea label={ __( 'Private notes', 'dev3-ai-real-estate' ) } value={ open.notes } onChange={ ( v ) => setOpen( { ...open, notes: v } ) } rows={ 4 } />
						<Button onClick={ () => update( { notes: open.notes } ) } loading={ saving }>
							{ __( 'Save notes', 'dev3-ai-real-estate' ) }
						</Button>
					</div>
				) }
			</Drawer>
		</>
	);
}
