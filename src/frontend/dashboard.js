import { api, t, toast } from './util';

/**
 * Agent dashboard actions (availability, delete, lead status, alerts, profile).
 *
 * @param {HTMLElement} root Dashboard.
 */
export function initDashboard( root ) {
	root.addEventListener( 'change', ( e ) => {
		const market = e.target.closest( '[data-dev3re-market]' );
		if ( market ) {
			api( `agent/properties/${ market.getAttribute( 'data-dev3re-market' ) }/market`, { method: 'POST', data: { market: market.value } } )
				.then( () => toast( t( 'saved' ) ) )
				.catch( ( err ) => toast( err.message || t( 'error' ) ) );
			return;
		}
		const lead = e.target.closest( '[data-dev3re-lead-status]' );
		if ( lead ) {
			api( `agent/leads/${ lead.getAttribute( 'data-dev3re-lead-status' ) }`, { method: 'POST', data: { status: lead.value } } )
				.then( () => {
					toast( t( 'saved' ) );
					const li = lead.closest( '.dev3re-lead' );
					if ( li ) {
						li.classList.toggle( 'is-new', lead.value === 'new' );
					}
				} )
				.catch( ( err ) => toast( err.message || t( 'error' ) ) );
		}
	} );
	root.addEventListener( 'click', ( e ) => {
		const del = e.target.closest( '[data-dev3re-delete-property], [data-dev3re-delete-search]' );
		if ( ! del || ! window.confirm( t( 'confirmDel' ) ) ) {
			return;
		}
		const isSearch = del.hasAttribute( 'data-dev3re-delete-search' );
		const id = del.getAttribute( isSearch ? 'data-dev3re-delete-search' : 'data-dev3re-delete-property' );
		api( isSearch ? `searches/${ id }` : `agent/properties/${ id }`, { method: 'DELETE' } )
			.then( () => {
				const li = del.closest( 'li' );
				if ( li ) {
					li.remove();
				}
			} )
			.catch( ( err ) => toast( err.message || t( 'error' ) ) );
	} );
	const profile = root.querySelector( '[data-dev3re-profile]' );
	if ( profile ) {
		profile.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const data = Object.fromEntries( new window.FormData( profile ) );
			const { name, bio, ...fields } = data;
			const msg = profile.querySelector( '[data-dev3re-profile-msg]' );
			api( 'agent/profile', { method: 'POST', data: { name, bio, fields } } )
				.then( () => {
					msg.textContent = t( 'saved' );
				} )
				.catch( ( err ) => {
					msg.textContent = err.message || t( 'error' );
				} );
		} );
	}
}
