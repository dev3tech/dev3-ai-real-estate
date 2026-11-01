import { api, esc, t, toast } from './util';

/**
 * Accessible lightbox for the property gallery.
 *
 * @param {HTMLElement} el Gallery.
 */
export function initGallery( el ) {
	let photos = [];
	try {
		photos = JSON.parse( el.getAttribute( 'data-dev3re-gallery' ) || '[]' );
	} catch ( e ) {}
	if ( ! photos.length ) {
		return;
	}
	const track = el.querySelector( '.dev3re-gallery__track' );
	const counter = el.querySelector( '[data-dev3re-gcount]' );
	if ( track && counter ) {
		track.addEventListener(
			'scroll',
			() => {
				const i = Math.round( track.scrollLeft / Math.max( 1, track.clientWidth ) );
				counter.textContent = String( Math.min( photos.length, i + 1 ) );
			},
			{ passive: true }
		);
	}
	let box = null;
	let index = 0;
	let opener = null;
	const show = ( i ) => {
		index = ( i + photos.length ) % photos.length;
		const p = photos[ index ];
		const img = box.querySelector( 'img' );
		img.src = p.src;
		img.alt = p.alt || '';
		if ( p.w && p.h ) {
			img.width = p.w;
			img.height = p.h;
		}
		box.querySelector( '.dev3re-lb__count' ).textContent = `${ index + 1 } / ${ photos.length }`;
	};
	const close = () => {
		box.hidden = true;
		document.documentElement.classList.remove( 'dev3re-noscroll' );
		if ( opener ) {
			opener.focus();
		}
	};
	const open = ( i, from ) => {
		opener = from;
		if ( ! box ) {
			box = document.createElement( 'div' );
			box.className = 'dev3re dev3re-lb';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );
			box.setAttribute( 'aria-label', t( 'gallery' ) );
			box.innerHTML = `<div class="dev3re-lb__bar"><span class="dev3re-lb__count"></span><button type="button" class="dev3re-lb__btn" data-close aria-label="${ esc( t( 'close' ) ) }">✕</button></div><figure class="dev3re-lb__fig"><img alt=""></figure><button type="button" class="dev3re-lb__btn dev3re-lb__prev" data-prev aria-label="${ esc( t( 'prev' ) ) }">‹</button><button type="button" class="dev3re-lb__btn dev3re-lb__next" data-next aria-label="${ esc( t( 'next' ) ) }">›</button>`;
			document.body.appendChild( box );
			box.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( '[data-close]' ) || e.target === box || e.target.classList.contains( 'dev3re-lb__fig' ) ) {
					close();
				} else if ( e.target.closest( '[data-prev]' ) ) {
					show( index - 1 );
				} else if ( e.target.closest( '[data-next]' ) ) {
					show( index + 1 );
				}
			} );
			box.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					close();
				} else if ( e.key === 'ArrowLeft' ) {
					show( index - 1 );
				} else if ( e.key === 'ArrowRight' ) {
					show( index + 1 );
				} else if ( e.key === 'Tab' ) {
					const f = Array.from( box.querySelectorAll( 'button' ) );
					const at = f.indexOf( document.activeElement );
					e.preventDefault();
					f[ ( at + ( e.shiftKey ? -1 : 1 ) + f.length ) % f.length ].focus();
				}
			} );
			let x0 = null;
			box.addEventListener( 'touchstart', ( e ) => ( x0 = e.touches[ 0 ].clientX ), { passive: true } );
			box.addEventListener( 'touchend', ( e ) => {
				if ( x0 === null ) {
					return;
				}
				const dx = e.changedTouches[ 0 ].clientX - x0;
				if ( Math.abs( dx ) > 40 ) {
					show( index + ( dx < 0 ? 1 : -1 ) );
				}
				x0 = null;
			} );
		}
		box.hidden = false;
		document.documentElement.classList.add( 'dev3re-noscroll' );
		show( i );
		box.querySelector( '[data-close]' ).focus();
	};
	el.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-index]' );
		if ( btn ) {
			open( Number( btn.getAttribute( 'data-index' ) ) || 0, btn );
		}
	} );
}

/**
 * Click-to-load iframes for tours and videos (privacy + no layout shift).
 *
 * @param {HTMLElement} el Wrapper.
 */
export function initEmbed( el ) {
	const btn = el.querySelector( '.dev3re-embed__play' );
	if ( ! btn ) {
		return;
	}
	btn.addEventListener( 'click', () => {
		const frame = document.createElement( 'iframe' );
		frame.src = el.getAttribute( 'data-dev3re-embed' );
		frame.title = btn.querySelector( 'span' ) ? btn.querySelector( 'span' ).textContent : 'Tour';
		frame.allow = 'fullscreen; xr-spatial-tracking; accelerometer; gyroscope; autoplay';
		frame.setAttribute( 'allowfullscreen', '' );
		frame.loading = 'lazy';
		el.innerHTML = '';
		el.appendChild( frame );
		frame.focus();
	} );
}

/**
 * Enquiry / viewing form.
 *
 * @param {HTMLFormElement} form Form.
 */
export function initEnquiry( form ) {
	const viewing = form.querySelector( '[data-dev3re-viewing]' );
	const msg = form.querySelector( '[data-dev3re-enquiry-msg]' );
	form.addEventListener( 'change', ( e ) => {
		if ( e.target.name === 'kind' && viewing ) {
			viewing.hidden = e.target.value !== 'viewing';
		}
	} );
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const data = Object.fromEntries( new window.FormData( form ) );
		const email = String( data.email || '' );
		if ( ! String( data.name || '' ).trim() || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			msg.textContent = t( 'required' );
			msg.className = 'dev3re-enquiry__msg is-error';
			( String( data.name || '' ).trim() ? form.elements.email : form.elements.name ).focus();
			return;
		}
		if ( form.elements.consent && ! form.elements.consent.checked ) {
			msg.textContent = t( 'consent' );
			msg.className = 'dev3re-enquiry__msg is-error';
			form.elements.consent.focus();
			return;
		}
		const btn = form.querySelector( 'button[type="submit"]' );
		btn.disabled = true;
		msg.textContent = '';
		api( 'leads', { method: 'POST', data } )
			.then( () => {
				msg.textContent = t( 'sent' );
				msg.className = 'dev3re-enquiry__msg is-ok';
				form.elements.message.value = '';
			} )
			.catch( ( err ) => {
				msg.textContent = err.message || t( 'error' );
				msg.className = 'dev3re-enquiry__msg is-error';
			} )
			.finally( () => {
				btn.disabled = false;
			} );
	} );
}

/**
 * Share + print buttons.
 */
export function initShare() {
	document.addEventListener( 'click', ( e ) => {
		const share = e.target.closest( '[data-dev3re-share]' );
		if ( share ) {
			const url = share.getAttribute( 'data-url' ) || window.location.href;
			const title = share.getAttribute( 'data-title' ) || document.title;
			if ( navigator.share ) {
				navigator.share( { title, url } ).catch( () => {} );
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( url ).then( () => toast( t( 'copied' ) ) );
			}
			return;
		}
		if ( e.target.closest( '[data-dev3re-print]' ) ) {
			window.print();
		}
	} );
}

