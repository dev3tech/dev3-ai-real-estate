import { api, cfg, esc, t, toast } from './util';
import { loadMaps } from './maps';

const FIELD_NAMES = [ 'price', 'price_period', 'beds', 'baths', 'area', 'lot_size', 'garages', 'year_built', 'floor', 'reference', 'street', 'city', 'region', 'postcode', 'country', 'lat', 'lng', 'tour_url', 'video_url', 'notes', 'neighbourhood', 'neighbourhood_ai', 'highlights', 'nearby', 'market' ];

/**
 * Plain text paragraphs → HTML-free text for the textarea (AI drafts come as <p> HTML).
 *
 * @param {string} html HTML.
 * @return {string} Text.
 */
export function htmlToText( html ) {
	return String( html || '' )
		.replace( /<\/p>\s*/gi, '\n\n' )
		.replace( /<br\s*\/?>/gi, '\n' )
		.replace( /<[^>]+>/g, '' )
		.replace( /&amp;/g, '&' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /\n{3,}/g, '\n\n' )
		.trim();
}

/**
 * Collects the submission payload from the form + in-memory gallery/plans.
 *
 * @param {HTMLFormElement} form   Form.
 * @param {Object}          state  { images, plans }.
 * @param {boolean}         draft  Save as draft.
 * @return {Object} Payload.
 */
export function collect( form, state, draft = false ) {
	const el = form.elements;
	const fields = {};
	FIELD_NAMES.forEach( ( n ) => {
		if ( el[ n ] ) {
			fields[ n ] = el[ n ].value;
		}
	} );
	fields.listing_type = ( form.querySelector( 'input[name="listing_type"]:checked' ) || {} ).value || 'sale';
	fields.price_on_request = !! ( el.price_on_request && el.price_on_request.checked );
	fields.hide_address = !! ( el.hide_address && el.hide_address.checked );
	fields.gallery = state.images.map( ( i ) => i.id );
	fields.floor_plans = state.plans.map( ( p ) => ( { title: p.title, image: p.image, beds: p.beds, baths: p.baths, area: p.area, notes: p.notes } ) );
	const out = {
		title: el.title.value,
		content: el.content.value,
		excerpt: el.excerpt ? el.excerpt.value : '',
		type: el.type && el.type.value ? [ Number( el.type.value ) ] : [],
		features: Array.from( form.querySelectorAll( 'input[name="features[]"]:checked' ) ).map( ( c ) => Number( c.value ) ),
		fields,
	};
	if ( draft ) {
		out.status = 'draft';
	}
	return out;
}

/**
 * Uploads one file.
 *
 * @param {File} file File.
 * @return {Promise<Object>} { id, url }.
 */
function upload( file ) {
	const body = new window.FormData();
	body.append( 'file', file );
	return api( 'agent/upload', { method: 'POST', body } );
}

/**
 * Submission form controller.
 *
 * @param {HTMLFormElement} form Form.
 */
export function initSubmit( form ) {
	let boot = {};
	try {
		boot = JSON.parse( form.getAttribute( 'data-dev3re-submit' ) || '{}' );
	} catch ( e ) {}
	const state = { images: boot.images || [], plans: ( boot.plans || [] ).map( ( p ) => ( { ...p } ) ) };
	const list = form.querySelector( '[data-dev3re-gallery-list]' );
	const errorBox = form.querySelector( '[data-dev3re-submit-error]' );
	const el = form.elements;

	const showError = ( msg ) => {
		errorBox.textContent = msg;
		errorBox.hidden = ! msg;
		if ( msg ) {
			errorBox.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
	};

	// ----- Gallery -----
	let dragFrom = null;
	const renderGallery = () => {
		list.innerHTML = state.images
			.map(
				( img, i ) => `<li class="dev3re-uploads__item" draggable="true" data-i="${ i }">${ img.url ? `<img src="${ esc( img.url ) }" alt="">` : `<span class="dev3re-uploads__busy">${ esc( t( 'uploading' ) ) }</span>` }${ i === 0 && img.id ? `<span class="dev3re-uploads__cover">${ esc( t( 'cover' ) ) }</span>` : '' }${
					img.id
						? `<span class="dev3re-uploads__btns"><button type="button" data-move="-1" aria-label="${ esc( t( 'moveLeft' ) ) }" ${ i === 0 ? 'disabled' : '' }>‹</button><button type="button" data-move="1" aria-label="${ esc( t( 'moveRight' ) ) }" ${ i === state.images.length - 1 ? 'disabled' : '' }>›</button><button type="button" data-remove aria-label="${ esc( t( 'remove' ) ) }">✕</button></span>`
						: ''
				}</li>`
			)
			.join( '' );
	};
	list.addEventListener( 'click', ( e ) => {
		const li = e.target.closest( '[data-i]' );
		if ( ! li ) {
			return;
		}
		const i = Number( li.getAttribute( 'data-i' ) );
		const move = e.target.closest( '[data-move]' );
		if ( move ) {
			const j = i + Number( move.getAttribute( 'data-move' ) );
			if ( j >= 0 && j < state.images.length ) {
				[ state.images[ i ], state.images[ j ] ] = [ state.images[ j ], state.images[ i ] ];
				renderGallery();
				const btn = list.querySelector( `[data-i="${ j }"] [data-move="${ move.getAttribute( 'data-move' ) }"]` );
				if ( btn && ! btn.disabled ) {
					btn.focus();
				}
			}
		} else if ( e.target.closest( '[data-remove]' ) ) {
			state.images.splice( i, 1 );
			renderGallery();
		}
	} );
	list.addEventListener( 'dragstart', ( e ) => {
		const li = e.target.closest( '[data-i]' );
		dragFrom = li ? Number( li.getAttribute( 'data-i' ) ) : null;
	} );
	list.addEventListener( 'dragover', ( e ) => e.preventDefault() );
	list.addEventListener( 'drop', ( e ) => {
		e.preventDefault();
		const li = e.target.closest( '[data-i]' );
		if ( dragFrom === null || ! li ) {
			return;
		}
		const to = Number( li.getAttribute( 'data-i' ) );
		const [ item ] = state.images.splice( dragFrom, 1 );
		state.images.splice( to, 0, item );
		dragFrom = null;
		renderGallery();
	} );
	const addFiles = ( files ) => {
		Array.from( files )
			.slice( 0, Math.max( 0, 30 - state.images.length ) )
			.forEach( ( file ) => {
				const slot = { id: 0, url: '' };
				state.images.push( slot );
				renderGallery();
				upload( file )
					.then( ( res ) => {
						slot.id = res.id;
						slot.url = res.url;
					} )
					.catch( ( err ) => {
						state.images.splice( state.images.indexOf( slot ), 1 );
						toast( err.message || t( 'error' ) );
					} )
					.finally( renderGallery );
			} );
	};
	const input = form.querySelector( '[data-dev3re-upload]' );
	input.addEventListener( 'change', () => {
		addFiles( input.files );
		input.value = '';
	} );
	const drop = form.querySelector( '[data-dev3re-drop]' );
	[ 'dragenter', 'dragover' ].forEach( ( ev ) =>
		drop.addEventListener( ev, ( e ) => {
			e.preventDefault();
			drop.classList.add( 'is-over' );
		} )
	);
	[ 'dragleave', 'drop' ].forEach( ( ev ) => drop.addEventListener( ev, () => drop.classList.remove( 'is-over' ) ) );
	drop.addEventListener( 'drop', ( e ) => {
		e.preventDefault();
		if ( e.dataTransfer && e.dataTransfer.files ) {
			addFiles( e.dataTransfer.files );
		}
	} );
	renderGallery();

	// ----- Floor plans -----
	const plansEl = form.querySelector( '[data-dev3re-plans]' );
	const renderPlans = () => {
		plansEl.innerHTML = state.plans
			.map(
				( p, i ) => `<div class="dev3re-planrow" data-p="${ i }">
					<div class="dev3re-planrow__img">${ p.image_url ? `<img src="${ esc( p.image_url ) }" alt="">` : '' }<label class="dev3re-btn dev3re-btn--ghost dev3re-btn--sm"><span>${ esc( t( 'planImage' ) ) }</span><input type="file" accept="image/*" class="dev3re-sr" data-plan-file></label></div>
					<div class="dev3re-formgrid dev3re-formgrid--4">
						<div class="dev3re-field"><label for="dev3re-pl-t${ i }">${ esc( t( 'dTitle' ) ) }</label><input id="dev3re-pl-t${ i }" data-k="title" value="${ esc( p.title || '' ) }"></div>
						<div class="dev3re-field"><label for="dev3re-pl-b${ i }">${ esc( t( 'beds' ) ) }</label><input id="dev3re-pl-b${ i }" type="number" min="0" data-k="beds" value="${ esc( p.beds || '' ) }"></div>
						<div class="dev3re-field"><label for="dev3re-pl-ba${ i }">${ esc( t( 'baths' ) ) }</label><input id="dev3re-pl-ba${ i }" type="number" min="0" step="0.5" data-k="baths" value="${ esc( p.baths || '' ) }"></div>
						<div class="dev3re-field"><label for="dev3re-pl-a${ i }">${ esc( t( 'area' ) ) }</label><input id="dev3re-pl-a${ i }" type="number" min="0" data-k="area" value="${ esc( p.area || '' ) }"></div>
					</div>
					<button type="button" class="dev3re-btn dev3re-btn--link" data-plan-remove>${ esc( t( 'remove' ) ) }</button>
				</div>`
			)
			.join( '' );
	};
	plansEl.addEventListener( 'input', ( e ) => {
		const row = e.target.closest( '[data-p]' );
		const k = e.target.getAttribute( 'data-k' );
		if ( row && k ) {
			state.plans[ Number( row.getAttribute( 'data-p' ) ) ][ k ] = e.target.value;
		}
	} );
	plansEl.addEventListener( 'change', ( e ) => {
		if ( ! e.target.matches( '[data-plan-file]' ) || ! e.target.files[ 0 ] ) {
			return;
		}
		const plan = state.plans[ Number( e.target.closest( '[data-p]' ).getAttribute( 'data-p' ) ) ];
		upload( e.target.files[ 0 ] )
			.then( ( res ) => {
				plan.image = res.id;
				plan.image_url = res.url;
				renderPlans();
			} )
			.catch( ( err ) => toast( err.message || t( 'error' ) ) );
	} );
	plansEl.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-plan-remove]' ) ) {
			state.plans.splice( Number( e.target.closest( '[data-p]' ).getAttribute( 'data-p' ) ), 1 );
			renderPlans();
		}
	} );
	form.querySelector( '[data-dev3re-add-plan]' ).addEventListener( 'click', () => {
		state.plans.push( { title: '', beds: '', baths: '', area: '', image: 0, image_url: '', notes: '' } );
		renderPlans();
		const last = plansEl.querySelector( '[data-p]:last-child input[data-k="title"]' );
		if ( last ) {
			last.focus();
		}
	} );
	renderPlans();

	// ----- Rent-only fields -----
	const syncDeal = () => {
		const rent = ( form.querySelector( 'input[name="listing_type"]:checked' ) || {} ).value === 'rent';
		form.querySelectorAll( '[data-dev3re-rent-only]' ).forEach( ( n ) => ( n.hidden = ! rent ) );
	};
	form.addEventListener( 'change', ( e ) => e.target.name === 'listing_type' && syncDeal() );
	syncDeal();

	// ----- Map pin -----
	const pickerEl = form.querySelector( '[data-dev3re-picker]' );
	let picker = null;
	const setPin = ( lat, lng ) => {
		el.lat.value = Number( lat ).toFixed( 7 );
		el.lng.value = Number( lng ).toFixed( 7 );
	};
	const mountPicker = () => {
		if ( picker || ! pickerEl ) {
			return Promise.resolve( picker );
		}
		const conf = cfg().map || {};
		return loadMaps().then( ( adapter ) => {
			const map = adapter.create( pickerEl, { lat: conf.lat || 0, lng: conf.lng || 0, zoom: conf.zoom || 12 } );
			picker = map.picker( boot.lat !== null && boot.lat !== undefined ? boot.lat : conf.lat, boot.lng !== null && boot.lng !== undefined ? boot.lng : conf.lng, setPin );
			return picker;
		} );
	};
	if ( pickerEl && 'IntersectionObserver' in window ) {
		const io = new window.IntersectionObserver( ( entries ) => {
			if ( entries.some( ( en ) => en.isIntersecting ) ) {
				io.disconnect();
				mountPicker();
			}
		} );
		io.observe( pickerEl );
	} else {
		mountPicker();
	}
	form.querySelector( '[data-dev3re-geocode]' ).addEventListener( 'click', ( e ) => {
		const btn = e.currentTarget;
		const address = [ el.street.value, el.city.value, el.region.value, el.postcode.value, el.country.value ].filter( Boolean ).join( ', ' );
		if ( ! address ) {
			return;
		}
		btn.disabled = true;
		api( 'agent/geocode', { method: 'POST', data: { address } } )
			.then( ( res ) => {
				setPin( res.lat, res.lng );
				return mountPicker().then( ( p ) => p && p.move( res.lat, res.lng ) );
			} )
			.catch( () => toast( t( 'pinFailed' ) ) )
			.finally( () => {
				btn.disabled = false;
			} );
	} );

	// ----- Description drafts -----
	const draftBox = form.querySelector( '[data-dev3re-draft]' );
	const showDescDraft = ( res ) => {
		const d = res.draft || {};
		const feats = String( d.features || '' ).split( '\n' ).filter( Boolean );
		draftBox.hidden = false;
		draftBox.innerHTML = `<p class="dev3re-draft__title">${ res.fallback ? '' : `<span class="dev3re-aimark">${ esc( t( 'ai' ) ) }</span> ` }${ esc( res.fallback ? t( 'dFromFacts' ) : t( 'dFromPhotos' ) ) }${ res.photos ? ` · ${ esc( t( 'photos' ).replace( '%d', res.photos ) ) }` : '' }</p>
			<div class="dev3re-field"><label for="dev3re-d-title">${ esc( t( 'dTitle' ) ) }</label><input id="dev3re-d-title" data-d="title" value="${ esc( d.title || '' ) }"></div>
			<div class="dev3re-field"><label for="dev3re-d-excerpt">${ esc( t( 'dSummary' ) ) }</label><textarea id="dev3re-d-excerpt" rows="2" data-d="excerpt">${ esc( d.excerpt || '' ) }</textarea></div>
			<div class="dev3re-field"><label for="dev3re-d-content">${ esc( t( 'dDesc' ) ) }</label><textarea id="dev3re-d-content" rows="9" data-d="content">${ esc( htmlToText( d.content ) ) }</textarea></div>
			<div class="dev3re-field"><label for="dev3re-d-hl">${ esc( t( 'dHighlights' ) ) }</label><textarea id="dev3re-d-hl" rows="4" data-d="highlights">${ esc( d.highlights || '' ) }</textarea></div>
			${ feats.length ? `<p class="dev3re-help">${ esc( t( 'featuresSeen' ) ) } ${ esc( feats.join( ', ' ) ) }</p>` : '' }
			${ res.observations && res.observations.length ? `<ul class="dev3re-draft__obs">${ res.observations.map( ( o ) => `<li>${ esc( o ) }</li>` ).join( '' ) }</ul>` : '' }
			<p class="dev3re-help">${ esc( t( 'notSaved' ) ) }</p>
			<div class="dev3re-inline"><button type="button" class="dev3re-btn dev3re-btn--primary" data-d-accept>${ esc( t( 'useText' ) ) }</button><button type="button" class="dev3re-btn dev3re-btn--ghost" data-d-reject>${ esc( t( 'discard' ) ) }</button></div>`;
		draftBox.querySelector( '[data-d-accept]' ).addEventListener( 'click', () => {
			const v = ( k ) => draftBox.querySelector( `[data-d="${ k }"]` ).value;
			el.title.value = res.fallback && el.title.value.trim() ? el.title.value : v( 'title' ) || el.title.value;
			el.content.value = v( 'content' );
			el.excerpt.value = v( 'excerpt' );
			el.highlights.value = v( 'highlights' );
			( res.feature_ids || [] ).forEach( ( id ) => {
				const box = form.querySelector( `input[name="features[]"][value="${ id }"]` );
				if ( box ) {
					box.checked = true;
				}
			} );
			draftBox.hidden = true;
			el.content.focus();
		} );
		draftBox.querySelector( '[data-d-reject]' ).addEventListener( 'click', () => {
			draftBox.hidden = true;
		} );
		draftBox.querySelector( '[data-d="title"]' ).focus();
	};
	const describe = ( mode, btn ) => {
		btn.disabled = true;
		draftBox.hidden = false;
		draftBox.innerHTML = `<p class="dev3re-help" role="status">${ esc( t( 'working' ) ) }</p>`;
		api( 'agent/ai/describe', { method: 'POST', data: { mode, id: boot.id || 0, listing: collect( form, state ) } } )
			.then( showDescDraft )
			.catch( ( err ) => {
				draftBox.innerHTML = `<p class="dev3re-notice dev3re-notice--error" role="alert">${ esc( err.message || t( 'error' ) ) }</p>`;
			} )
			.finally( () => {
				btn.disabled = false;
			} );
	};
	const aiBtn = form.querySelector( '[data-dev3re-ai-describe]' );
	if ( aiBtn ) {
		aiBtn.addEventListener( 'click', () => describe( 'ai', aiBtn ) );
	}
	const tplBtn = form.querySelector( '[data-dev3re-template-describe]' );
	tplBtn.addEventListener( 'click', () => describe( 'template', tplBtn ) );

	// ----- Neighbourhood drafts -----
	const hoodBox = form.querySelector( '[data-dev3re-hood-draft]' );
	form.querySelectorAll( '[data-dev3re-ai-hood]' ).forEach( ( btn ) =>
		btn.addEventListener( 'click', () => {
			const ai = btn.getAttribute( 'data-dev3re-ai-hood' ) === '1';
			btn.disabled = true;
			hoodBox.hidden = false;
			hoodBox.innerHTML = `<p class="dev3re-help" role="status">${ esc( t( 'working' ) ) }</p>`;
			api( 'agent/ai/neighbourhood', { method: 'POST', data: { ai, fields: collect( form, state ).fields } } )
				.then( ( res ) => {
					const d = res.draft || {};
					hoodBox.innerHTML = `<p class="dev3re-draft__title">${ res.ai ? `<span class="dev3re-aimark">${ esc( t( 'ai' ) ) }</span> ` : '' }${ esc( res.ai ? t( 'hoodAi' ) : t( 'hoodOsm' ) ) }</p>
						<div class="dev3re-field"><label for="dev3re-h-s">${ esc( t( 'dSummary' ) ) }</label><textarea id="dev3re-h-s" rows="3" data-h="summary">${ esc( d.summary || '' ) }</textarea></div>
						<div class="dev3re-field"><label for="dev3re-h-l">${ esc( t( 'dHighlights' ) ) }</label><textarea id="dev3re-h-l" rows="5" data-h="highlights">${ esc( d.highlights || '' ) }</textarea></div>
						<p class="dev3re-help">${ res.ai ? esc( t( 'aiLabelNote' ) ) + ' ' : '' }${ esc( t( 'notSaved' ) ) }</p>
						<div class="dev3re-inline"><button type="button" class="dev3re-btn dev3re-btn--primary" data-h-accept>${ esc( t( 'useText' ) ) }</button><button type="button" class="dev3re-btn dev3re-btn--ghost" data-h-reject>${ esc( t( 'discard' ) ) }</button></div>`;
					hoodBox.querySelector( '[data-h-accept]' ).addEventListener( 'click', () => {
						const s = hoodBox.querySelector( '[data-h="summary"]' ).value.trim();
						const h = hoodBox
							.querySelector( '[data-h="highlights"]' )
							.value.split( '\n' )
							.map( ( x ) => x.trim() )
							.filter( Boolean );
						el.neighbourhood.value = [ s, h.map( ( x ) => '• ' + x ).join( '\n' ) ].filter( Boolean ).join( '\n\n' );
						el.neighbourhood_ai.value = res.ai ? '1' : '0';
						el.nearby.value = ( res.nearby || [] ).join( '\n' );
						if ( res.lat && ! el.lat.value ) {
							setPin( res.lat, res.lng );
							mountPicker().then( ( p ) => p && p.move( res.lat, res.lng ) );
						}
						hoodBox.hidden = true;
					} );
					hoodBox.querySelector( '[data-h-reject]' ).addEventListener( 'click', () => {
						hoodBox.hidden = true;
					} );
				} )
				.catch( ( err ) => {
					hoodBox.innerHTML = `<p class="dev3re-notice dev3re-notice--error" role="alert">${ esc( err.message || t( 'error' ) ) }</p>`;
				} )
				.finally( () => {
					btn.disabled = false;
				} );
		} )
	);

	// ----- Save -----
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const draft = !! ( e.submitter && e.submitter.hasAttribute( 'data-dev3re-draft-btn' ) );
		if ( ! el.title.value.trim() ) {
			showError( t( 'titleRequired' ) );
			el.title.focus();
			return;
		}
		if ( state.images.some( ( i ) => ! i.id ) ) {
			showError( t( 'uploading' ) );
			return;
		}
		showError( '' );
		const btns = form.querySelectorAll( '.dev3re-submit__foot button' );
		btns.forEach( ( b ) => ( b.disabled = true ) );
		api( boot.id ? `agent/properties/${ boot.id }` : 'agent/properties', { method: 'POST', data: collect( form, state, draft ) } )
			.then( ( res ) => {
				toast( res.message || t( 'savedListing' ) );
				boot.id = res.id;
				setTimeout( () => {
					window.location.href = boot.success || res.url;
				}, 1200 );
			} )
			.catch( ( err ) => {
				showError( err.message || t( 'error' ) );
				btns.forEach( ( b ) => ( b.disabled = false ) );
			} );
	} );
}

/**
 * "Become an agent" button on the gate.
 */
export function initBecomeAgent() {
	document.querySelectorAll( '[data-dev3re-become-agent]' ).forEach( ( btn ) =>
		btn.addEventListener( 'click', () => {
			btn.disabled = true;
			api( 'agent/become', { method: 'POST' } )
				.then( () => window.location.reload() )
				.catch( ( err ) => {
					toast( err.message || t( 'error' ) );
					btn.disabled = false;
				} );
		} )
	);
}
