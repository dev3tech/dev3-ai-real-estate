import '../frontend/frontend.scss';
import { ready } from '../frontend/util';
import { initSearch } from '../frontend/search';
import { initSaved } from '../frontend/saved';
import { initStandaloneMaps } from '../frontend/maps';
import { initEmbed, initEnquiry, initGallery, initShare } from '../frontend/single';
import { initMortgage } from '../frontend/mortgage';
import { initBecomeAgent, initSubmit } from '../frontend/submit';
import { initDashboard } from '../frontend/dashboard';

ready( () => {
	document.querySelectorAll( '[data-dev3re-search]' ).forEach( initSearch );
	document.querySelectorAll( '[data-dev3re-gallery]' ).forEach( initGallery );
	document.querySelectorAll( '[data-dev3re-embed]' ).forEach( initEmbed );
	document.querySelectorAll( '[data-dev3re-enquiry]' ).forEach( initEnquiry );
	document.querySelectorAll( '[data-dev3re-mortgage]' ).forEach( initMortgage );
	document.querySelectorAll( '[data-dev3re-submit]' ).forEach( initSubmit );
	document.querySelectorAll( '[data-dev3re-dash]' ).forEach( initDashboard );
	initBecomeAgent();
	initStandaloneMaps( document );
	initShare();
	initSaved();
} );
