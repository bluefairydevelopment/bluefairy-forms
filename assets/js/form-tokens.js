/**
 * BlueFairy Forms: fresh tokens for a form served from a cache (WPMAP-152).
 *
 * A page cache hands every visitor the nonce and time token the page was
 * cached with; after a day they are refused. On load, each form asks the site
 * for tokens from now (GET bffrm/v1/tokens/{id}, never cached) and puts them
 * in the form. Logged-in pages are left alone: their nonce belongs to the
 * user, and they are not page-cached. Without this script the server still
 * never drops an expired submission silently: it asks the visitor to send
 * again.
 */
( function () {
	'use strict';

	function refresh( form ) {
		var url = form.getAttribute( 'data-bffrm-tokens' );
		if ( ! url || ! window.fetch ) {
			return;
		}
		window.fetch( url, { credentials: 'omit', cache: 'no-store', headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data || 'string' !== typeof data.nonce ) {
					return;
				}
				var nonce = form.querySelector( 'input[name="bffrm_nonce"]' );
				if ( nonce ) {
					nonce.value = data.nonce;
				}
				Object.keys( data.fields || {} ).forEach( function ( name ) {
					var input = form.querySelector( 'input[name="' + name.replace( /[^A-Za-z0-9_\-]/g, '' ) + '"]' );
					if ( input ) {
						input.value = String( data.fields[ name ] );
					}
				} );
				form.setAttribute( 'data-bffrm-fresh', '1' );
			} )
			[ 'catch' ]( function () {} );
	}

	function init() {
		if ( document.body && document.body.classList.contains( 'logged-in' ) ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( 'form.bffrm-form[data-bffrm-tokens]' ), refresh );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
