import { api, cfg, load, store, t, toast } from './util';

const FAV_KEY = 'dev3re_favs';
const CMP_KEY = 'dev3re_compare';

let favs = null;

/**
 * Current favourite ids (account for logged-in users, browser storage for guests).
 *
 * @return {number[]} Ids.
 */
export function getFavs() {
	if ( favs === null ) {
		favs = cfg().loggedIn ? ( cfg().favourites || [] ).map( Number ) : load( FAV_KEY, [] ).map( Number );
	}
	return favs;
}

export const getCompare = () => load( CMP_KEY, [] ).map( Number ).slice( 0, cfg().compareMax || 4 );

/**
 * Toggles a favourite.
 *
 * @param {number} id Property.
 * @return {Promise<boolean>} Saved state.
 */
export function toggleFav( id ) {
	const on = ! getFavs().includes( id );
	if ( cfg().loggedIn ) {
		return api( 'favourites', { method: 'POST', data: { id, on } } ).then( ( res ) => {
			favs = ( res.ids || [] ).map( Number );
			return favs.includes( id );
		} );
	}
	favs = on ? [ id, ...getFavs().filter( ( x ) => x !== id ) ] : getFavs().filter( ( x ) => x !== id );
	store( FAV_KEY, favs.slice( 0, 200 ) );
	return Promise.resolve( on );
}

/**
 * Toggles compare.
 *
 * @param {number} id Property.
 * @return {boolean|null} New state, null when the list is full.
 */
export function toggleCompare( id ) {
	const list = getCompare();
	const max = cfg().compareMax || 4;
	if ( list.includes( id ) ) {
		store( CMP_KEY, list.filter( ( x ) => x !== id ) );
		return false;
	}
	if ( list.length >= max ) {
		return null;
	}
	store( CMP_KEY, [ ...list, id ] );
	return true;
}

/**
 * Syncs button states inside a root.
 *
 * @param {ParentNode} root Root.
 */
export function refreshSaved( root = document ) {
	const f = getFavs();
	const c = getCompare();
	root.querySelectorAll( '[data-dev3re-fav]' ).forEach( ( b ) => {
		const on = f.includes( Number( b.getAttribute( 'data-dev3re-fav' ) ) );
		b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		b.setAttribute( 'aria-label', on ? t( 'favRemove' ) : t( 'favAdd' ) );
		b.title = on ? t( 'favRemove' ) : t( 'favAdd' );
	} );
	root.querySelectorAll( '[data-dev3re-compare]' ).forEach( ( b ) => {
		const on = c.includes( Number( b.getAttribute( 'data-dev3re-compare' ) ) );
		b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		b.setAttribute( 'aria-label', on ? t( 'cmpRemove' ) : t( 'cmpAdd' ) );
		b.title = on ? t( 'cmpRemove' ) : t( 'cmpAdd' );
	} );
	renderBar();
}

/**
 * Sticky compare bar.
 */
function renderBar() {
	if ( ! ( cfg().features || {} ).compare ) {
		return;
	}
	const list = getCompare();
	let bar = document.querySelector( '.dev3re-cmpbar' );
	const onComparePage = !! document.querySelector( '[data-dev3re-comparepage]' );
	if ( ! list.length || onComparePage ) {
		if ( bar ) {
			bar.hidden = true;
		}
		document.documentElement.classList.remove( 'dev3re-has-cmpbar' );
		return;
	}
	if ( ! bar ) {
		bar = document.createElement( 'div' );
		bar.className = 'dev3re dev3re-cmpbar';
		bar.setAttribute( 'role', 'region' );
		bar.setAttribute( 'aria-label', t( 'cmpOpen' ) );
		bar.innerHTML = '<span class="dev3re-cmpbar__text"></span><span class="dev3re-cmpbar__btns"><button type="button" class="dev3re-btn dev3re-btn--link" data-dev3re-cmp-clear></button><a class="dev3re-btn dev3re-btn--primary" data-dev3re-cmp-open></a></span>';
		document.body.appendChild( bar );
		bar.querySelector( '[data-dev3re-cmp-clear]' ).addEventListener( 'click', () => {
			store( CMP_KEY, [] );
			refreshSaved();
		} );
	}
	bar.hidden = false;
	document.documentElement.classList.add( 'dev3re-has-cmpbar' );
	bar.querySelector( '.dev3re-cmpbar__text' ).textContent = t( 'cmpBar' ).replace( '%d', String( list.length ) );
	bar.querySelector( '[data-dev3re-cmp-clear]' ).textContent = t( 'cmpClear' );
	const open = bar.querySelector( '[data-dev3re-cmp-open]' );
	open.textContent = t( 'cmpOpen' );
	open.href = ( cfg().pages || {} ).compare || '#';
}

/**
 * Favourites page for guests (account favourites are rendered by the server).
 *
 * @param {HTMLElement} el Container.
 */
function initFavouritesPage( el ) {
	if ( cfg().loggedIn ) {
		return;
	}
	const ids = getFavs();
	if ( ! ids.length ) {
		return;
	}
	api( 'properties/by-ids?ids=' + ids.join( ',' ) )
		.then( ( res ) => {
			el.innerHTML = res.html;
			refreshSaved( el );
		} )
		.catch( () => {} );
}

/**
 * Compare page.
 *
 * @param {HTMLElement} el Container.
 */
function initComparePage( el ) {
	const draw = () => {
		const ids = getCompare();
		if ( ! ids.length ) {
			return;
		}
		el.setAttribute( 'aria-busy', 'true' );
		api( 'properties/by-ids?format=compare&ids=' + ids.join( ',' ) )
			.then( ( res ) => {
				el.innerHTML = res.html;
			} )
			.catch( () => {} )
			.finally( () => el.setAttribute( 'aria-busy', 'false' ) );
	};
	el.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-dev3re-compare-remove]' );
		if ( btn ) {
			toggleCompare( Number( btn.getAttribute( 'data-dev3re-compare-remove' ) ) );
			if ( ! getCompare().length ) {
				window.location.reload();
				return;
			}
			draw();
		}
	} );
	draw();
}

/**
 * Wires favourite + compare buttons everywhere.
 */
export function initSaved() {
	document.addEventListener( 'click', ( e ) => {
		const fav = e.target.closest( '[data-dev3re-fav]' );
		if ( fav ) {
			e.preventDefault();
			const id = Number( fav.getAttribute( 'data-dev3re-fav' ) );
			fav.classList.add( 'is-busy' );
			toggleFav( id )
				.then( ( on ) => {
					refreshSaved();
					toast( on ? t( 'saved' ) : t( 'favRemove' ) );
				} )
				.catch( ( err ) => toast( err.message || t( 'error' ) ) )
				.finally( () => fav.classList.remove( 'is-busy' ) );
			return;
		}
		const cmp = e.target.closest( '[data-dev3re-compare]' );
		if ( cmp ) {
			e.preventDefault();
			const state = toggleCompare( Number( cmp.getAttribute( 'data-dev3re-compare' ) ) );
			if ( state === null ) {
				toast( t( 'cmpFull' ).replace( '%d', String( cfg().compareMax || 4 ) ) );
			}
			refreshSaved();
		}
	} );
	// After login, merge browser favourites into the account once.
	if ( cfg().loggedIn && cfg().syncFavs ) {
		const local = load( FAV_KEY, [] );
		api( 'favourites', { method: 'POST', data: { merge: local } } )
			.then( ( res ) => {
				favs = ( res.ids || [] ).map( Number );
				store( FAV_KEY, [] );
				refreshSaved();
			} )
			.catch( () => {} );
	}
	document.querySelectorAll( '[data-dev3re-favourites]' ).forEach( initFavouritesPage );
	document.querySelectorAll( '[data-dev3re-comparepage]' ).forEach( initComparePage );
	refreshSaved();
}
