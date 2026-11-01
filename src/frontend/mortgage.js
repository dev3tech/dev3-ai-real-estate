import { money } from './util';

/**
 * Monthly repayment of an annuity loan (same formula as Search\Mortgage::monthly).
 *
 * @param {number} principal Loan.
 * @param {number} rate      Annual rate in %.
 * @param {number} years     Term in years.
 * @return {number} Monthly payment rounded to cents.
 */
export function monthly( principal, rate, years ) {
	const p = Math.max( 0, Number( principal ) || 0 );
	const n = Math.max( 1, Math.round( ( Number( years ) || 0 ) * 12 ) );
	const r = Math.max( 0, Number( rate ) || 0 ) / 100 / 12;
	if ( p <= 0 ) {
		return 0;
	}
	if ( r <= 0 ) {
		return Math.round( ( p / n ) * 100 ) / 100;
	}
	return Math.round( ( ( p * r ) / ( 1 - Math.pow( 1 + r, -n ) ) ) * 100 ) / 100;
}

/**
 * Breakdown for the calculator UI.
 *
 * @param {number} price Price.
 * @param {number} down  Down payment %.
 * @param {number} rate  Rate %.
 * @param {number} years Years.
 * @return {Object} { loan, monthly, total, interest }.
 */
export function breakdown( price, down, rate, years ) {
	const loan = Math.max( 0, ( Number( price ) || 0 ) * ( 1 - Math.min( 100, Math.max( 0, Number( down ) || 0 ) ) / 100 ) );
	const m = monthly( loan, rate, years );
	const n = Math.max( 1, Math.round( ( Number( years ) || 0 ) * 12 ) );
	const total = Math.round( m * n * 100 ) / 100;
	return { loan, monthly: m, total, interest: Math.max( 0, total - loan ) };
}

/**
 * Wires a calculator form.
 *
 * @param {HTMLFormElement} form Form.
 */
export function initMortgage( form ) {
	let fmt = {};
	try {
		fmt = JSON.parse( form.getAttribute( 'data-dev3re-mortgage' ) || '{}' );
	} catch ( e ) {}
	const out = ( k ) => form.querySelector( `[data-out="${ k }"]` );
	const update = () => {
		const el = form.elements;
		const b = breakdown( el.price.value, el.down.value, el.rate.value, el.term.value );
		out( 'monthly' ).textContent = money( b.monthly, fmt );
		out( 'loan' ).textContent = money( b.loan, fmt );
		out( 'interest' ).textContent = money( b.interest, fmt );
		out( 'total' ).textContent = money( b.total, fmt );
		out( 'bar' ).style.width = ( b.total > 0 ? Math.round( ( b.loan / b.total ) * 100 ) : 100 ) + '%';
	};
	form.addEventListener( 'input', update );
	update();
}
