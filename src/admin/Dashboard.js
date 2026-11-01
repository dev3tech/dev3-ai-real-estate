import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, Columns, ErrorNotice, Icon, Notice, PageHeader, ResponsiveTable, Stat, StatGrid } from '../core3/ui';
import { LeadBadge, formatDate, useApp } from './shared';

/**
 * Overview: KPIs, setup checklist, latest leads and most viewed listings.
 *
 * @param {Object}   props          Props.
 * @param {Function} props.navigate Navigate.
 * @return {Element} Dashboard.
 */
export default function Dashboard( { navigate } ) {
	const { settings, save } = useApp();
	const [ stats, setStats ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const load = () => request( 'admin/stats' ).then( setStats ).catch( setError );
	useEffect( () => {
		load();
	}, [] );

	const p = stats ? stats.properties : {};
	const checks = stats ? stats.checks : {};
	const steps = [
		{ key: 'pages', label: __( 'Create the search, submit, dashboard, favourites and compare pages', 'dev3-ai-real-estate' ), action: () => {
			setBusy( true );
			request( 'admin/pages', { method: 'POST' } )
				.then( () => save( {} ) )
				.then( load )
				.finally( () => setBusy( false ) );
		}, actionLabel: __( 'Create pages', 'dev3-ai-real-estate' ) },
		{ key: 'agent', label: __( 'Add your first agent', 'dev3-ai-real-estate' ), href: '#/agents/new', actionLabel: __( 'Add agent', 'dev3-ai-real-estate' ) },
		{ key: 'property', label: __( 'Add your first property', 'dev3-ai-real-estate' ), href: '#/properties/new', actionLabel: __( 'Add property', 'dev3-ai-real-estate' ) },
		{ key: 'map', label: __( 'Maps are ready (OpenStreetMap, or Google with a key)', 'dev3-ai-real-estate' ), href: '#/settings', actionLabel: __( 'Map settings', 'dev3-ai-real-estate' ) },
		{ key: 'ai', label: __( 'Connect DEV3 AI for descriptions from photos and smart search', 'dev3-ai-real-estate' ), href: '#/api', actionLabel: __( 'Add API key', 'dev3-ai-real-estate' ) },
	];
	const open = steps.filter( ( s ) => ! checks[ s.key ] );

	return (
		<>
			<PageHeader
				title={ __( 'Dashboard', 'dev3-ai-real-estate' ) }
				description={ __( 'Your listings, leads and alerts at a glance.', 'dev3-ai-real-estate' ) }
				actions={
					<Button variant="primary" icon="plus" href="#/properties/new">
						{ __( 'Add property', 'dev3-ai-real-estate' ) }
					</Button>
				}
			/>
			{ error && <ErrorNotice error={ error } onRetry={ load } /> }
			{ settings && ! settings.api_key_set && (
				<Notice tone="ai" title={ __( 'Connect DEV3 AI', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" variant="primary" href="#/api">{ __( 'Add your API key', 'dev3-ai-real-estate' ) }</Button> }>
					{ __( 'Write listing descriptions from photos, suggest neighbourhood highlights and let visitors search in plain words. Everything else works without a key.', 'dev3-ai-real-estate' ) }
				</Notice>
			) }
			<StatGrid>
				<Stat label={ __( 'Live listings', 'dev3-ai-real-estate' ) } value={ stats ? String( p.publish ) : '' } loading={ ! stats } icon="box" tone="brand" hint={ stats ? sprintf( /* translators: 1: for sale, 2: for rent */ __( '%1$d for sale · %2$d for rent', 'dev3-ai-real-estate' ), p.sale, p.rent ) : '' } />
				<Stat label={ __( 'Waiting for review', 'dev3-ai-real-estate' ) } value={ stats ? String( p.pending ) : '' } loading={ ! stats } icon="clock" tone={ p.pending ? 'warning' : 'neutral' } hint={ stats ? sprintf( /* translators: %d: drafts */ __( '%d drafts', 'dev3-ai-real-estate' ), p.draft ) : '' } />
				<Stat label={ __( 'New leads', 'dev3-ai-real-estate' ) } value={ stats ? String( stats.leads.new ) : '' } loading={ ! stats } icon="inbox" tone="success" hint={ stats ? sprintf( /* translators: %d: leads */ __( '%d in the last 7 days', 'dev3-ai-real-estate' ), stats.leads_7d ) : '' } />
				<Stat label={ __( 'Saved searches', 'dev3-ai-real-estate' ) } value={ stats ? String( stats.searches ) : '' } loading={ ! stats } icon="bolt" hint={ stats ? sprintf( /* translators: %d: sold or rented */ __( '%d sold or rented', 'dev3-ai-real-estate' ), p.closed ) : '' } />
			</StatGrid>
			{ stats && open.length > 0 && (
				<Card style={ { marginTop: 16 } } title={ __( 'Get set up', 'dev3-ai-real-estate' ) } description={ sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d steps done', 'dev3-ai-real-estate' ), steps.length - open.length, steps.length ) }>
					<ul className="dev3re-admin-steps">
						{ steps.map( ( s ) => (
							<li key={ s.key } className={ checks[ s.key ] ? 'is-done' : '' }>
								<span className="dev3re-admin-steps__icon">
									<Icon name={ checks[ s.key ] ? 'success' : 'clock' } size={ 18 } />
								</span>
								<span className="dev3re-admin-steps__label">{ s.label }</span>
								{ ! checks[ s.key ] && (
									<Button size="sm" variant="ghost" href={ s.href } onClick={ s.action } loading={ s.action && busy }>
										{ s.actionLabel }
									</Button>
								) }
							</li>
						) ) }
					</ul>
				</Card>
			) }
			<Columns min={ 340 }>
				<Card style={ { marginTop: 16 } } title={ __( 'Latest leads', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" variant="ghost" href="#/leads">{ __( 'Open inbox', 'dev3-ai-real-estate' ) }</Button> } flush>
					<ResponsiveTable
						loading={ ! stats }
						rows={ stats ? stats.recent : [] }
						onRowClick={ () => navigate( 'leads' ) }
						columns={ [
							{ key: 'name', label: __( 'Name', 'dev3-ai-real-estate' ), primary: true, render: ( r ) => <strong>{ r.name }</strong> },
							{ key: 'property', label: __( 'Property', 'dev3-ai-real-estate' ), render: ( r ) => r.property || '—' },
							{ key: 'status', label: __( 'Status', 'dev3-ai-real-estate' ), render: ( r ) => <LeadBadge status={ r.status } /> },
							{ key: 'created_at', label: __( 'Received', 'dev3-ai-real-estate' ), render: ( r ) => formatDate( r.created_at ), hideOnMobile: true, nowrap: true },
						] }
						empty={ { icon: 'inbox', title: __( 'No leads yet', 'dev3-ai-real-estate' ), description: __( 'Enquiries and viewing requests from your listings land here.', 'dev3-ai-real-estate' ) } }
					/>
				</Card>
				<Card style={ { marginTop: 16 } } title={ __( 'Most viewed', 'dev3-ai-real-estate' ) } actions={ <Button size="sm" variant="ghost" href="#/properties">{ __( 'All properties', 'dev3-ai-real-estate' ) }</Button> } flush>
					<ResponsiveTable
						loading={ ! stats }
						rows={ stats ? stats.popular : [] }
						onRowClick={ ( r ) => navigate( `properties/${ r.id }` ) }
						columns={ [
							{ key: 'title', label: __( 'Property', 'dev3-ai-real-estate' ), primary: true, render: ( r ) => (
								<span className="dev3re-admin-prop">
									{ r.image ? <img src={ r.image } alt="" width="48" height="36" /> : <span className="dev3re-admin-prop__ph" /> }
									<span><strong>{ r.title }</strong><br /><span className="dev3re-admin-muted">{ r.price }</span></span>
								</span>
							) },
							{ key: 'views', label: __( 'Views', 'dev3-ai-real-estate' ), align: 'end' },
							{ key: 'leads', label: __( 'Leads', 'dev3-ai-real-estate' ), align: 'end', render: ( r ) => ( r.leads ? <Badge tone="success">{ r.leads }</Badge> : '0' ) },
						] }
						empty={ { icon: 'box', title: __( 'No views yet', 'dev3-ai-real-estate' ), description: __( 'Publish a property to start tracking views.', 'dev3-ai-real-estate' ) } }
					/>
				</Card>
			</Columns>
		</>
	);
}
