import { api, money, t, toast } from './util';
import { mountMap } from './maps';
import { refreshSaved } from './saved';

const ADVANCED = [ 'baths', 'area_min', 'area_max', 'year_min', 'q' ];

/**
 * Reads the filter form into a plain params object (term arrays as comma lists).
 *
 * @param {HTMLFormElement} form Form.
 * @return {Object} Params.
 */
export function formParams( form ) {
	const out = {};
	new window.FormData( form ).forEach( ( value, name ) => {
		if ( value === '' || name === 'nl' || name === 'sort_ui' || name === 'email' || name === 'frequency' ) {
			return;
		}
		if ( name.endsWith( '[]' ) ) {
			const key = name.slice( 0, -2 );
			out[ key ] = out[ key ] ? out[ key ] + ',' + value : String( value );
			return;
		}
		out[ name ] = String( value );
	} );
	if ( out.sort === 'newest' ) {
		delete out.sort;
	}
	return out;
}

/**
 * Sets a select's value, adding an option when the value is not in the list (e.g. a price from plain-language search).
 *
 * @param {HTMLSelectElement} select Select.
 * @param {string}            value  Value.
 * @param {string}            label  Label for a new option.
 */
function setSelect( select, value, label ) {
	if ( ! select ) {
		return;
	}
	const v = value === undefined || value === null ? '' : String( value );
	if ( v && ! Array.from( select.options ).some( ( o ) => o.value === v ) ) {
		const opt = document.createElement( 'option' );
		opt.value = v;
		opt.textContent = label || v;
		select.appendChild( opt );
	}
	select.value = v;
}

/**
 * Applies a query object (from the server) to the form fields.
 *
 * @param {HTMLFormElement} form  Form.
 * @param {Object}          query Query.
 * @param {Object}          conf  Search config.
 */
export function applyQuery( form, query, conf ) {
	const first = ( v ) => ( Array.isArray( v ) ? v[ 0 ] : String( v || '' ).split( ',' )[ 0 ] ) || '';
	form.querySelectorAll( 'input[name="listing_type"]' ).forEach( ( r ) => {
		r.checked = r.value === ( query.listing_type || '' );
	} );
	setSelect( form.elements.type, first( query.type ) );
	setSelect( form.elements.location, first( query.location ) );
	setPrices( form, conf, query.listing_type || '' );
	setSelect( form.elements.price_min, query.price_min ? Math.round( query.price_min ) : '', money( query.price_min, conf.money ) );
	setSelect( form.elements.price_max, query.price_max ? Math.round( query.price_max ) : '', money( query.price_max, conf.money ) );
	setSelect( form.elements.beds, query.beds || '', ( query.beds || '' ) + '+' );
	setSelect( form.elements.baths, query.baths ? Math.floor( query.baths ) : '', ( query.baths || '' ) + '+' );
	[ 'area_min', 'area_max', 'year_min', 'q' ].forEach( ( k ) => {
		if ( form.elements[ k ] ) {
			form.elements[ k ].value = query[ k ] || '';
		}
	} );
	const feats = Array.isArray( query.features ) ? query.features : String( query.features || '' ).split( ',' ).filter( Boolean );
	form.querySelectorAll( 'input[name="features[]"]' ).forEach( ( c ) => {
		c.checked = feats.includes( c.value );
	} );
	if ( form.elements.sort ) {
		form.elements.sort.value = query.sort || 'newest';
	}
}

/**
 * Rebuilds the price selects for sale or rent steps, keeping the current values.
 *
 * @param {HTMLFormElement} form Form.
 * @param {Object}          conf Config.
 * @param {string}          deal sale|rent|''.
 */
function setPrices( form, conf, deal ) {
	const steps = ( conf.prices && conf.prices[ deal === 'rent' ? 'rent' : 'sale' ] ) || [];
	[ 'price_min', 'price_max' ].forEach( ( name ) => {
		const select = form.elements[ name ];
		if ( ! select || select.dataset.deal === ( deal || 'sale' ) ) {
			return;
		}
		const any = select.options[ 0 ] ? select.options[ 0 ].textContent : '';
		select.innerHTML = '';
		const o = document.createElement( 'option' );
		o.value = '';
		o.textContent = any;
		select.appendChild( o );
		steps.forEach( ( [ value, label ] ) => {
			const opt = document.createElement( 'option' );
			opt.value = String( value );
			opt.textContent = label;
			select.appendChild( opt );
		} );
		select.value = '';
		select.dataset.deal = deal || 'sale';
	} );
}

/**
 * Search page controller.
 *
 * @param {HTMLElement} root Root.
 */
export function initSearch( root ) {
	let conf = {};
	try {
		conf = JSON.parse( root.getAttribute( 'data-dev3re-search' ) || '{}' );
	} catch ( e ) {}
	const form = root.querySelector( '[data-dev3re-form]' );
	const results = root.querySelector( '[data-dev3re-results]' );
	const count = root.querySelector( '[data-dev3re-count]' );
	const chips = root.querySelector( '[data-dev3re-chips]' );
	const mapEl = root.querySelector( '[data-dev3re-map]' );
	const moveBox = root.querySelector( '[data-dev3re-map-move]' );
	const nl = form.elements.nl;
	let lastNl = nl ? nl.value.trim() : '';
	let page = 1;
	let map = null;
	let ctrl = null;
	let skipFit = false;
	const deal = () => ( form.querySelector( 'input[name="listing_type"]:checked' ) || {} ).value || '';
	if ( form.elements.price_min ) {
		form.elements.price_min.dataset.deal = deal() || 'sale';
		form.elements.price_max.dataset.deal = deal() || 'sale';
	}

	const moreCount = () => {
		const p = formParams( form );
		const n = ADVANCED.filter( ( k ) => p[ k ] ).length + ( p.features ? p.features.split( ',' ).length : 0 );
		const badge = root.querySelector( '[data-dev3re-more-count]' );
		if ( badge ) {
			badge.textContent = String( n );
			badge.hidden = ! n;
		}
	};

	const run = ( opts = {} ) => {
		const params = formParams( form );
		if ( page > 1 ) {
			params.pg = String( page );
		}
		const urlParams = new URLSearchParams( params );
		if ( nl && nl.value.trim() ) {
			urlParams.set( 'nl', nl.value.trim() );
		}
		const query = new URLSearchParams( params );
		query.set( 'columns', String( conf.columns || 2 ) );
		query.set( 'per_page', String( conf.perPage || 12 ) );
		query.set( 'base', conf.base || window.location.pathname );
		if ( mapEl ) {
			query.set( 'markers', '1' );
		}
		if ( ctrl ) {
			ctrl.abort();
		}
		ctrl = window.AbortController ? new window.AbortController() : null;
		results.setAttribute( 'aria-busy', 'true' );
		root.classList.add( 'is-loading' );
		if ( count ) {
			count.textContent = t( 'loading' );
		}
		return api( 'properties?' + query.toString(), { signal: ctrl ? ctrl.signal : undefined } )
			.then( ( res ) => {
				results.innerHTML = res.html + ( res.pagination || '' );
				if ( count ) {
					count.textContent = res.count;
				}
				if ( chips ) {
					chips.innerHTML = res.chips || '';
				}
				if ( map && res.markers ) {
					map.setMarkers( res.markers, ! skipFit );
				}
				skipFit = false;
				const qs = urlParams.toString();
				const url = window.location.pathname + ( qs ? '?' + qs : '' );
				if ( opts.replace ) {
					window.history.replaceState( { dev3re: 1 }, '', url );
				} else {
					window.history.pushState( { dev3re: 1 }, '', url );
				}
				refreshSaved( results );
				if ( opts.scroll && results.getBoundingClientRect().top < 0 ) {
					root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} )
			.catch( ( err ) => {
				if ( err && err.name === 'AbortError' ) {
					return;
				}
				if ( count ) {
					count.textContent = ( err && err.message ) || t( 'error' );
				}
			} )
			.finally( () => {
				results.setAttribute( 'aria-busy', 'false' );
				root.classList.remove( 'is-loading' );
				moreCount();
			} );
	};

	const interpret = () => {
		const text = nl.value.trim();
		lastNl = text;
		if ( ! text ) {
			return run();
		}
		root.classList.add( 'is-loading' );
		return api( 'nl-search', { method: 'POST', data: { text } } )
			.then( ( res ) => {
				applyQuery( form, res.query || {}, conf );
			} )
			.catch( () => {
				applyQuery( form, { q: text }, conf );
			} )
			.then( () => run() );
	};

	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		page = 1;
		const panel = root.querySelector( '.dev3re-sbar__panel' );
		if ( e.submitter && panel && panel.contains( e.submitter ) ) {
			toggleMore( false );
		}
		if ( nl && nl.value.trim() !== lastNl ) {
			interpret();
		} else {
			run();
		}
	} );

	let timer = null;
	form.addEventListener( 'change', ( e ) => {
		if ( e.target === nl || e.target.closest( '.dev3re-sbar__panel' ) ) {
			moreCount();
			return;
		}
		if ( e.target.name === 'listing_type' ) {
			setPrices( form, conf, e.target.value );
		}
		page = 1;
		clearTimeout( timer );
		timer = setTimeout( () => run(), 150 );
	} );

	const moreBtn = root.querySelector( '[data-dev3re-more]' );
	const toggleMore = ( open ) => {
		const panel = root.querySelector( '.dev3re-sbar__panel' );
		if ( ! panel || ! moreBtn ) {
			return;
		}
		const next = open === undefined ? panel.hidden : open;
		panel.hidden = ! next;
		moreBtn.setAttribute( 'aria-expanded', next ? 'true' : 'false' );
	};
	if ( moreBtn ) {
		moreBtn.addEventListener( 'click', () => toggleMore() );
	}
	const reset = root.querySelector( '[data-dev3re-reset]' );
	if ( reset ) {
		reset.addEventListener( 'click', () => {
			if ( nl ) {
				nl.value = '';
				lastNl = '';
			}
			applyQuery( form, {}, conf );
			if ( form.elements.bbox ) {
				form.elements.bbox.value = '';
			}
			page = 1;
			run();
		} );
	}

	const sortUi = root.querySelector( 'select[name="sort_ui"]' );
	if ( sortUi ) {
		sortUi.addEventListener( 'change', () => {
			form.elements.sort.value = sortUi.value;
			page = 1;
			skipFit = true;
			run();
		} );
	}

	results.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( '.dev3re-pages a[data-page]' );
		if ( ! link ) {
			return;
		}
		e.preventDefault();
		page = Number( link.getAttribute( 'data-page' ) ) || 1;
		skipFit = true;
		run( { scroll: true } );
	} );

	if ( chips ) {
		chips.addEventListener( 'click', ( e ) => {
			const chip = e.target.closest( '.dev3re-chip' );
			if ( ! chip ) {
				return;
			}
			const key = chip.getAttribute( 'data-key' );
			const value = chip.getAttribute( 'data-value' );
			const els = form.elements;
			if ( key === 'listing_type' ) {
				form.querySelectorAll( 'input[name="listing_type"]' ).forEach( ( r ) => ( r.checked = r.value === '' ) );
			} else if ( key === 'price' ) {
				els.price_min.value = '';
				els.price_max.value = '';
			} else if ( key === 'features' ) {
				form.querySelectorAll( 'input[name="features[]"]' ).forEach( ( c ) => {
					if ( c.value === value ) {
						c.checked = false;
					}
				} );
			} else if ( key === 'bbox' ) {
				els.bbox.value = '';
				if ( moveBox ) {
					moveBox.checked = false;
				}
			} else if ( els[ key ] ) {
				els[ key ].value = '';
			}
			if ( nl ) {
				nl.value = '';
				lastNl = '';
			}
			page = 1;
			run();
		} );
	}

	const viewBtn = root.querySelector( '[data-dev3re-view]' );
	if ( viewBtn ) {
		viewBtn.addEventListener( 'click', () => {
			const on = ! root.classList.contains( 'is-map-view' );
			root.classList.toggle( 'is-map-view', on );
			viewBtn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			const label = viewBtn.querySelector( 'span' );
			if ( label ) {
				label.textContent = on ? t( 'list' ) : t( 'map' );
			}
			if ( on && ! map && mapEl ) {
				startMap();
			}
			if ( map ) {
				setTimeout( () => map.invalidate(), 50 );
			}
		} );
	}

	const startMap = () =>
		mountMap( mapEl, {
			onMarker: ( id ) => {
				const card = results.querySelector( `.dev3re-prop[data-id="${ id }"]` );
				if ( card && window.matchMedia( '(min-width: 1024px)' ).matches ) {
					card.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				}
			},
		} ).then( ( handle ) => {
			map = handle;
			if ( map.onMoveEnd ) {
				map.onMoveEnd( ( bbox ) => {
					if ( moveBox && moveBox.checked ) {
						form.elements.bbox.value = bbox;
						page = 1;
						skipFit = true;
						run( { replace: true } );
					}
				} );
			}
			return map;
		} );

	if ( mapEl ) {
		const wide = window.matchMedia( '(min-width: 1024px)' );
		if ( wide.matches || root.classList.contains( 'is-map-view' ) ) {
			startMap();
		} else {
			wide.addEventListener( 'change', ( ev ) => ev.matches && ! map && startMap() );
		}
		if ( moveBox ) {
			moveBox.addEventListener( 'change', () => {
				if ( ! moveBox.checked && form.elements.bbox.value ) {
					form.elements.bbox.value = '';
					page = 1;
					run();
				}
			} );
		}
	}

	results.addEventListener( 'mouseover', ( e ) => {
		const card = e.target.closest( '.dev3re-prop' );
		if ( map && card && card !== results.__hover ) {
			if ( results.__hover ) {
				map.highlight( results.__hover.getAttribute( 'data-id' ), false );
			}
			results.__hover = card;
			map.highlight( card.getAttribute( 'data-id' ), true );
		}
	} );

	// Save search.
	const saveBtn = root.querySelector( '[data-dev3re-save-search]' );
	const saveBox = root.querySelector( '[data-dev3re-savebox]' );
	if ( saveBtn && saveBox ) {
		saveBtn.addEventListener( 'click', () => {
			saveBox.hidden = ! saveBox.hidden;
			saveBtn.setAttribute( 'aria-expanded', saveBox.hidden ? 'false' : 'true' );
			if ( ! saveBox.hidden ) {
				const first = saveBox.querySelector( 'input, select' );
				if ( first ) {
					first.focus();
				}
			}
		} );
		saveBox.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const msg = saveBox.querySelector( '[data-dev3re-savebox-msg]' );
			const email = saveBox.elements.email ? saveBox.elements.email.value : '';
			const btn = saveBox.querySelector( 'button[type="submit"]' );
			btn.disabled = true;
			api( 'searches', { method: 'POST', data: { email, frequency: saveBox.elements.frequency.value, query: formParams( form ) } } )
				.then( ( res ) => {
					msg.textContent = res.needs_confirm ? t( 'alertConfirm' ) : t( 'alertSaved' );
					toast( msg.textContent );
				} )
				.catch( ( err ) => {
					msg.textContent = err.message || t( 'error' );
				} )
				.finally( () => {
					btn.disabled = false;
				} );
		} );
	}

	window.addEventListener( 'popstate', () => {
		const p = Object.fromEntries( new URLSearchParams( window.location.search ) );
		applyQuery( form, p, conf );
		if ( nl ) {
			nl.value = p.nl || '';
			lastNl = nl.value;
		}
		page = Number( p.pg ) || 1;
		run( { replace: true } );
	} );

	moreCount();
	// A plain-language query in the URL (e.g. from the quick search) is refined by the smart parser once.
	const url = new URLSearchParams( window.location.search );
	if ( nl && url.get( 'nl' ) && ! url.get( 'type' ) && ! url.get( 'price_max' ) && ! url.get( 'beds' ) ) {
		lastNl = '';
		interpret();
	}
}
