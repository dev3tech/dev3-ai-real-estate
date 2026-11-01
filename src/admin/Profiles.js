import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, Drawer, ErrorNotice, FormGrid, PageHeader, ResponsiveTable, Select, Textarea, TextField, useToast } from '../core3/ui';
import { htmlToText, openMedia, textToHtml, useApp } from './shared';

const AGENT_FIELDS = [
	[ 'position', __( 'Job title', 'dev3-ai-real-estate' ), 'text' ],
	[ 'email', __( 'Email for enquiries', 'dev3-ai-real-estate' ), 'email' ],
	[ 'phone', __( 'Phone', 'dev3-ai-real-estate' ), 'tel' ],
	[ 'mobile', __( 'Mobile', 'dev3-ai-real-estate' ), 'tel' ],
	[ 'whatsapp', __( 'WhatsApp number', 'dev3-ai-real-estate' ), 'tel' ],
	[ 'license', __( 'License number', 'dev3-ai-real-estate' ), 'text' ],
	[ 'languages', __( 'Languages', 'dev3-ai-real-estate' ), 'text' ],
	[ 'website', __( 'Website', 'dev3-ai-real-estate' ), 'url' ],
	[ 'facebook', 'Facebook', 'url' ],
	[ 'instagram', 'Instagram', 'url' ],
	[ 'linkedin', 'LinkedIn', 'url' ],
	[ 'x', 'X', 'url' ],
];
const AGENCY_FIELDS = [
	[ 'email', __( 'Email', 'dev3-ai-real-estate' ), 'email' ],
	[ 'phone', __( 'Phone', 'dev3-ai-real-estate' ), 'tel' ],
	[ 'website', __( 'Website', 'dev3-ai-real-estate' ), 'url' ],
	[ 'address', __( 'Office address', 'dev3-ai-real-estate' ), 'text' ],
	[ 'license', __( 'License number', 'dev3-ai-real-estate' ), 'text' ],
];

const blank = () => ( { id: 0, name: '', bio: '', photo: 0, photo_url: '', status: 'publish', agency_id: 0, user_id: 0, user_label: '', fields: {} } );

/**
 * Agents or agencies: list + drawer editor.
 *
 * @param {Object} props        Props.
 * @param {string} props.kind   agents|agencies.
 * @param {number} props.openId Open this id (-1 = new) on load.
 * @return {Element} Screen.
 */
export default function Profiles( { kind, openId } ) {
	const toast = useToast();
	const { meta, reloadMeta } = useApp();
	const isAgent = kind === 'agents';
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ edit, setEdit ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ userSearch, setUserSearch ] = useState( '' );
	const [ users, setUsers ] = useState( [] );

	const load = () =>
		request( `admin/${ kind }` )
			.then( ( res ) => {
				setItems( res.items );
				if ( openId ) {
					const found = res.items.find( ( i ) => i.id === openId );
					setEdit( found ? { ...found, bio: htmlToText( found.bio ) } : openId < 0 ? blank() : null );
				}
			} )
			.catch( setError );
	useEffect( () => {
		setItems( null );
		setEdit( null );
		load();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ kind ] );
	useEffect( () => {
		if ( ! isAgent || ! edit || userSearch.length < 2 ) {
			return;
		}
		const t = setTimeout( () => request( `admin/users?search=${ encodeURIComponent( userSearch ) }` ).then( ( r ) => setUsers( r.items ) ), 250 );
		return () => clearTimeout( t );
	}, [ userSearch, isAgent, edit ] );

	const set = ( key ) => ( value ) => setEdit( ( e ) => ( { ...e, [ key ]: value } ) );
	const setField = ( key ) => ( value ) => setEdit( ( e ) => ( { ...e, fields: { ...e.fields, [ key ]: value } } ) );

	const save = () => {
		if ( ! edit.name.trim() ) {
			toast.error( __( 'Add a name.', 'dev3-ai-real-estate' ) );
			return;
		}
		setSaving( true );
		const data = { name: edit.name, bio: textToHtml( edit.bio ), photo: edit.photo, status: edit.status, fields: edit.fields };
		if ( isAgent ) {
			data.agency_id = Number( edit.agency_id ) || 0;
			data.user_id = Number( edit.user_id ) || 0;
		}
		request( edit.id ? `admin/${ kind }/${ edit.id }` : `admin/${ kind }`, { method: 'POST', data } )
			.then( () => {
				toast.success( isAgent ? __( 'Agent saved.', 'dev3-ai-real-estate' ) : __( 'Agency saved.', 'dev3-ai-real-estate' ) );
				setEdit( null );
				reloadMeta();
				load();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setSaving( false ) );
	};
	const remove = ( row ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( /* translators: %s: name */ __( 'Move %s to the trash?', 'dev3-ai-real-estate' ), row.name ) ) ) {
			return;
		}
		request( `admin/${ kind }/${ row.id }`, { method: 'DELETE' } )
			.then( () => {
				toast.success( __( 'Moved to trash.', 'dev3-ai-real-estate' ) );
				reloadMeta();
				load();
			} )
			.catch( ( err ) => toast.error( err ) );
	};

	const rows = ( items || [] ).filter( ( i ) => ! search || i.name.toLowerCase().includes( search.toLowerCase() ) );
	const fields = isAgent ? AGENT_FIELDS : AGENCY_FIELDS;

	return (
		<>
			<PageHeader
				title={ isAgent ? __( 'Agents', 'dev3-ai-real-estate' ) : __( 'Agencies', 'dev3-ai-real-estate' ) }
				description={ isAgent ? __( 'People who list properties and answer enquiries. Link an agent to a user account so they can use the front-end dashboard.', 'dev3-ai-real-estate' ) : __( 'Offices or brands your agents belong to.', 'dev3-ai-real-estate' ) }
				actions={
					<Button variant="primary" icon="plus" onClick={ () => setEdit( blank() ) }>
						{ isAgent ? __( 'Add agent', 'dev3-ai-real-estate' ) : __( 'Add agency', 'dev3-ai-real-estate' ) }
					</Button>
				}
			/>
			{ error && <ErrorNotice error={ error } onRetry={ load } /> }
			<Card flush>
				<div className="dev3re-admin-toolbar">
					<TextField label={ __( 'Search', 'dev3-ai-real-estate' ) } hideLabel type="search" placeholder={ __( 'Search by name…', 'dev3-ai-real-estate' ) } value={ search } onChange={ setSearch } />
				</div>
				<ResponsiveTable
					loading={ ! items }
					rows={ rows }
					pageSize={ 20 }
					onRowClick={ ( r ) => setEdit( { ...r, bio: htmlToText( r.bio ) } ) }
					columns={ [
						{ key: 'name', label: __( 'Name', 'dev3-ai-real-estate' ), primary: true, sortable: true, render: ( r ) => (
							<span className="dev3re-admin-prop">
								{ r.photo_url ? <img src={ r.photo_url } alt="" width="40" height="40" className="is-round" /> : <span className="dev3re-admin-avatar">{ r.name.slice( 0, 1 ).toUpperCase() }</span> }
								<span>
									<strong>{ r.name }</strong>
									<br />
									<span className="dev3re-admin-muted">{ ( isAgent ? [ r.fields.position, r.agency ] : [ r.fields.address ] ).filter( Boolean ).join( ' · ' ) || '—' }</span>
								</span>
							</span>
						) },
						{ key: 'email', label: __( 'Contact', 'dev3-ai-real-estate' ), render: ( r ) => [ r.fields.email, r.fields.phone ].filter( Boolean ).join( ' · ' ) || '—' },
						isAgent
							? { key: 'user', label: __( 'Account', 'dev3-ai-real-estate' ), hideOnMobile: true, render: ( r ) => ( r.user_id ? <Badge tone="success">{ __( 'Linked', 'dev3-ai-real-estate' ) }</Badge> : <Badge>{ __( 'No login', 'dev3-ai-real-estate' ) }</Badge> ) }
							: { key: 'agents', label: __( 'Agents', 'dev3-ai-real-estate' ), align: 'end' },
						isAgent ? { key: 'listings', label: __( 'Listings', 'dev3-ai-real-estate' ), align: 'end', sortable: true } : { key: 'status', label: __( 'Status', 'dev3-ai-real-estate' ), render: ( r ) => ( r.status === 'publish' ? __( 'Public', 'dev3-ai-real-estate' ) : __( 'Hidden', 'dev3-ai-real-estate' ) ) },
						...( isAgent ? [ { key: 'leads', label: __( 'Leads', 'dev3-ai-real-estate' ), align: 'end', sortable: true } ] : [] ),
					] }
					rowActions={ ( r ) => (
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
						<span className="dev3re-admin-rowacts" onClick={ ( e ) => e.stopPropagation() }>
							<Button size="sm" variant="ghost" icon="external" href={ r.url } target="_blank" label={ __( 'View profile', 'dev3-ai-real-estate' ) } />
							<Button size="sm" variant="ghost" icon="trash" onClick={ () => remove( r ) } label={ __( 'Delete', 'dev3-ai-real-estate' ) } />
						</span>
					) }
					empty={ { icon: 'users', title: isAgent ? __( 'No agents yet', 'dev3-ai-real-estate' ) : __( 'No agencies yet', 'dev3-ai-real-estate' ), action: <Button variant="primary" icon="plus" onClick={ () => setEdit( blank() ) }>{ isAgent ? __( 'Add agent', 'dev3-ai-real-estate' ) : __( 'Add agency', 'dev3-ai-real-estate' ) }</Button> } }
				/>
			</Card>
			<Drawer
				open={ !! edit }
				onClose={ () => setEdit( null ) }
				size="lg"
				title={ edit && edit.id ? edit.name : isAgent ? __( 'New agent', 'dev3-ai-real-estate' ) : __( 'New agency', 'dev3-ai-real-estate' ) }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setEdit( null ) }>
							{ __( 'Cancel', 'dev3-ai-real-estate' ) }
						</Button>
						<Button variant="primary" onClick={ save } loading={ saving }>
							{ __( 'Save', 'dev3-ai-real-estate' ) }
						</Button>
					</>
				}
			>
				{ edit && (
					<div className="dev3re-admin-stack">
						<div className="dev3re-admin-photo">
							{ edit.photo_url ? <img src={ edit.photo_url } alt="" className={ isAgent ? 'is-round' : '' } /> : <span className="dev3re-admin-avatar is-lg">{ ( edit.name || '?' ).slice( 0, 1 ).toUpperCase() }</span> }
							<Button size="sm" onClick={ () => openMedia( { title: isAgent ? __( 'Agent photo', 'dev3-ai-real-estate' ) : __( 'Agency logo', 'dev3-ai-real-estate' ), onSelect: ( items ) => items[ 0 ] && setEdit( ( e ) => ( { ...e, photo: items[ 0 ].id, photo_url: items[ 0 ].url } ) ) } ) }>
								{ isAgent ? __( 'Choose photo', 'dev3-ai-real-estate' ) : __( 'Choose logo', 'dev3-ai-real-estate' ) }
							</Button>
							{ edit.photo > 0 && (
								<Button size="sm" variant="ghost" onClick={ () => setEdit( ( e ) => ( { ...e, photo: 0, photo_url: '' } ) ) }>
									{ __( 'Remove', 'dev3-ai-real-estate' ) }
								</Button>
							) }
						</div>
						<TextField label={ __( 'Name', 'dev3-ai-real-estate' ) } value={ edit.name } onChange={ set( 'name' ) } required />
						<FormGrid>
							{ fields.map( ( [ key, label, type ] ) => (
								<TextField key={ key } label={ label } type={ type } value={ edit.fields[ key ] || '' } onChange={ setField( key ) } />
							) ) }
						</FormGrid>
						{ isAgent && (
							<FormGrid>
								<Select label={ __( 'Agency', 'dev3-ai-real-estate' ) } value={ String( edit.agency_id || '' ) } onChange={ set( 'agency_id' ) } options={ [ { value: '', label: __( 'Independent', 'dev3-ai-real-estate' ) }, ...meta.agencies.map( ( a ) => ( { value: String( a.value ), label: a.label } ) ) ] } />
								<div>
									<TextField label={ __( 'Linked user account', 'dev3-ai-real-estate' ) } type="search" value={ userSearch } onChange={ setUserSearch } placeholder={ edit.user_label || __( 'Type a name or email…', 'dev3-ai-real-estate' ) } help={ edit.user_id ? sprintf( /* translators: %s: user */ __( 'Linked to %s', 'dev3-ai-real-estate' ), edit.user_label ) : __( 'The user gets the “Real estate agent” role and can submit listings.', 'dev3-ai-real-estate' ) } />
									{ users.length > 0 && userSearch.length > 1 && (
										<ul className="dev3re-admin-suggest" role="listbox" aria-label={ __( 'Users', 'dev3-ai-real-estate' ) }>
											{ users.map( ( u ) => (
												<li key={ u.value }>
													<button
														type="button"
														onClick={ () => {
															setEdit( ( e ) => ( { ...e, user_id: u.value, user_label: u.label } ) );
															setUserSearch( '' );
															setUsers( [] );
														} }
													>
														{ u.label }
													</button>
												</li>
											) ) }
										</ul>
									) }
									{ edit.user_id > 0 && (
										<Button size="sm" variant="link" onClick={ () => setEdit( ( e ) => ( { ...e, user_id: 0, user_label: '' } ) ) }>
											{ __( 'Unlink account', 'dev3-ai-real-estate' ) }
										</Button>
									) }
								</div>
							</FormGrid>
						) }
						<Select label={ __( 'Visibility', 'dev3-ai-real-estate' ) } value={ edit.status } onChange={ set( 'status' ) } options={ [ { value: 'publish', label: __( 'Public profile page', 'dev3-ai-real-estate' ) }, { value: 'draft', label: __( 'Hidden', 'dev3-ai-real-estate' ) } ] } />
						<Textarea label={ isAgent ? __( 'About', 'dev3-ai-real-estate' ) : __( 'Description', 'dev3-ai-real-estate' ) } value={ edit.bio } onChange={ set( 'bio' ) } rows={ 6 } />
					</div>
				) }
			</Drawer>
		</>
	);
}
