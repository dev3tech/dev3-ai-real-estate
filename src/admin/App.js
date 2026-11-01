import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import ApiTab from '../core3/ApiTab';
import UsageTab from '../core3/UsageTab';
import { AppShell, ErrorNotice, PageHeader, SkeletonCard, UsageMeter, useHashRoute } from '../core3/ui';
import Dashboard from './Dashboard';
import Properties from './Properties';
import PropertyEditor from './PropertyEditor';
import Profiles from './Profiles';
import Leads from './Leads';
import Searches from './Searches';
import SettingsTab from './SettingsTab';
import { AppContext } from './shared';
import './admin.css';

const NAV = [
	{ id: 'dashboard', label: __( 'Dashboard', 'dev3-ai-real-estate' ), icon: 'dashboard' },
	{ id: 'properties', label: __( 'Properties', 'dev3-ai-real-estate' ), icon: 'box' },
	{ id: 'agents', label: __( 'Agents', 'dev3-ai-real-estate' ), icon: 'users' },
	{ id: 'agencies', label: __( 'Agencies', 'dev3-ai-real-estate' ), icon: 'columns' },
	{ id: 'leads', label: __( 'Leads', 'dev3-ai-real-estate' ), icon: 'inbox' },
	{ id: 'searches', label: __( 'Saved searches', 'dev3-ai-real-estate' ), icon: 'clock' },
	{ id: 'settings', label: __( 'Settings', 'dev3-ai-real-estate' ), icon: 'settings' },
	{ id: 'api', label: __( 'API Config', 'dev3-ai-real-estate' ), icon: 'key' },
	{ id: 'usage', label: __( 'Usage Logs', 'dev3-ai-real-estate' ), icon: 'chart' },
];

export default function App() {
	const [ route, navigate ] = useHashRoute(
		NAV.map( ( n ) => n.id ),
		'dashboard'
	);
	const [ settings, setSettings ] = useState( null );
	const [ meta, setMeta ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ meter, setMeter ] = useState( 0 );
	const [ newLeads, setNewLeads ] = useState( 0 );

	const reloadMeta = () => request( 'admin/meta' ).then( setMeta );
	const load = () => {
		setError( null );
		Promise.all( [ request( 'settings' ), request( 'admin/meta' ), request( 'admin/leads?status=new&per_page=1' ) ] )
			.then( ( [ s, m, l ] ) => {
				setSettings( s );
				setMeta( m );
				setNewLeads( l.total || 0 );
			} )
			.catch( setError );
	};
	useEffect( load, [] );

	const save = ( patch ) =>
		request( 'settings', { method: 'POST', data: patch } ).then( ( next ) => {
			setSettings( next );
			setMeter( ( n ) => n + 1 );
			return next;
		} );

	const usage = () => setMeter( ( n ) => n + 1 );
	const sub = route.params && route.params[ 0 ];
	const screens = {
		dashboard: () => <Dashboard navigate={ navigate } />,
		properties: () => ( sub ? <PropertyEditor id={ sub === 'new' ? 0 : Number( sub ) } navigate={ navigate } onUsage={ usage } /> : <Properties navigate={ navigate } /> ),
		agents: () => <Profiles kind="agents" openId={ sub === 'new' ? -1 : Number( sub ) || 0 } />,
		agencies: () => <Profiles kind="agencies" openId={ sub === 'new' ? -1 : Number( sub ) || 0 } />,
		leads: () => <Leads onChange={ ( n ) => setNewLeads( n ) } />,
		searches: () => <Searches />,
		settings: () => <SettingsTab settings={ settings } save={ save } />,
		api: () => (
			<>
				<PageHeader title={ __( 'API Config', 'dev3-ai-real-estate' ) } description={ __( 'Connect Google Gemini, pick models and set a monthly AI budget.', 'dev3-ai-real-estate' ) } />
				<ApiTab settings={ settings } save={ save } />
			</>
		),
		usage: () => (
			<>
				<PageHeader title={ __( 'Usage Logs', 'dev3-ai-real-estate' ) } description={ __( 'Requests, tokens and estimated cost of every DEV3 AI feature.', 'dev3-ai-real-estate' ) } />
				<UsageTab />
			</>
		),
	};
	const nav = NAV.map( ( n ) => ( n.id === 'leads' && newLeads ? { ...n, badge: String( newLeads ) } : n ) );

	return (
		<AppContext.Provider value={ { meta, settings, reloadMeta, save } }>
			<AppShell
				name={ config.name || 'DEV3 AI Real Estate Listings for WordPress' }
				version={ config.version }
				icon={ config.assetsUrl ? `${ config.assetsUrl }img/icon.svg` : undefined }
				nav={ nav }
				active={ route.id }
				actions={ <UsageMeter compact refreshKey={ meter } /> }
				footer={ __( 'Made by DEV3 Technologies · AI by Google Gemini, using your own API key.', 'dev3-ai-real-estate' ) }
			>
				{ error && <ErrorNotice error={ error } onRetry={ load } /> }
				{ ( ! settings || ! meta ) && ! error && <SkeletonCard lines={ 6 } /> }
				{ settings && meta && screens[ route.id ]() }
			</AppShell>
		</AppContext.Provider>
	);
}
