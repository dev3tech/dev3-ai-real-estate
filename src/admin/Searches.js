import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, ErrorNotice, PageHeader, ResponsiveTable, TextField, useToast } from '../core3/ui';
import { formatDate } from './shared';

const PER_PAGE = 20;
const FREQ = {
	instant: __( 'As listed (hourly)', 'dev3-ai-real-estate' ),
	daily: __( 'Daily', 'dev3-ai-real-estate' ),
	weekly: __( 'Weekly', 'dev3-ai-real-estate' ),
};

/**
 * Saved searches with email alerts.
 *
 * @return {Element} Screen.
 */
export default function Searches() {
	const toast = useToast();
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ sending, setSending ] = useState( false );

	const load = () =>
		request( `admin/searches?${ new URLSearchParams( { search, page: String( page ) } ).toString() }` )
			.then( setData )
			.catch( setError );
	useEffect( () => {
		const t = setTimeout( load, search ? 300 : 0 );
		return () => clearTimeout( t );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ search, page ] );

	const send = () => {
		setSending( true );
		request( 'admin/searches/send', { method: 'POST' } )
			.then( ( res ) => {
				toast.success( sprintf( /* translators: 1: emails sent, 2: searches checked */ __( '%1$d alert emails sent (%2$d searches checked).', 'dev3-ai-real-estate' ), res.sent, res.checked ) );
				load();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setSending( false ) );
	};
	const remove = ( row ) =>
		request( `admin/searches/${ row.id }`, { method: 'DELETE' } )
			.then( () => {
				toast.success( __( 'Saved search deleted.', 'dev3-ai-real-estate' ) );
				load();
			} )
			.catch( ( err ) => toast.error( err ) );

	return (
		<>
			<PageHeader
				title={ __( 'Saved searches', 'dev3-ai-real-estate' ) }
				description={ __( 'Visitors save a search and get an email when new properties match. Guests confirm their email first; every email has an unsubscribe link.', 'dev3-ai-real-estate' ) }
				actions={
					<Button icon="refresh" onClick={ send } loading={ sending }>
						{ __( 'Send alerts now', 'dev3-ai-real-estate' ) }
					</Button>
				}
			/>
			{ error && <ErrorNotice error={ error } onRetry={ load } /> }
			<Card flush>
				<div className="dev3re-admin-toolbar">
					<TextField label={ __( 'Search', 'dev3-ai-real-estate' ) } hideLabel type="search" placeholder={ __( 'Email or search…', 'dev3-ai-real-estate' ) } value={ search } onChange={ ( v ) => {
						setSearch( v );
						setPage( 1 );
					} } />
				</div>
				<ResponsiveTable
					loading={ ! data }
					rows={ data ? data.items : [] }
					pagination={ data ? { page, perPage: PER_PAGE, total: data.total, onPageChange: setPage } : undefined }
					columns={ [
						{ key: 'label', label: __( 'Search', 'dev3-ai-real-estate' ), primary: true, render: ( r ) => (
							<a href={ r.url } target="_blank" rel="noreferrer">
								{ r.label }
							</a>
						) },
						{ key: 'email', label: __( 'Email', 'dev3-ai-real-estate' ), render: ( r ) => (
							<span>
								{ r.email } { ! r.confirmed && <Badge tone="warning">{ __( 'Unconfirmed', 'dev3-ai-real-estate' ) }</Badge> }
							</span>
						) },
						{ key: 'frequency', label: __( 'Frequency', 'dev3-ai-real-estate' ), render: ( r ) => FREQ[ r.frequency ] || r.frequency },
						{ key: 'sent_count', label: __( 'Emails sent', 'dev3-ai-real-estate' ), align: 'end', hideOnMobile: true },
						{ key: 'last_sent', label: __( 'Last checked', 'dev3-ai-real-estate' ), hideOnMobile: true, render: ( r ) => formatDate( r.last_sent, true ) },
					] }
					rowActions={ ( r ) => <Button size="sm" variant="ghost" icon="trash" onClick={ () => remove( r ) } label={ __( 'Delete', 'dev3-ai-real-estate' ) } /> }
					empty={ { icon: 'clock', title: __( 'No saved searches yet', 'dev3-ai-real-estate' ), description: __( 'Visitors use “Save search” on the search page.', 'dev3-ai-real-estate' ) } }
				/>
			</Card>
		</>
	);
}
