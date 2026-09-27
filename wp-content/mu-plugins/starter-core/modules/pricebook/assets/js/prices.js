/**
 * Pricebook example: fills [data-price="<key>"] nodes from window.STARTER_PRICES and dispatches
 * "starter:prices:ready" on document. Enqueued by the theme (inc/assets.php) with strategy=defer,
 * only while the pricebook module is active.
 *
 * Calculator pattern: [data-price] optionally carries [data-price-qty] (quantity, default 1); the
 * printed value is price × qty — the same "price × quantity" example as
 * starter_pricebook_calc_total() on the PHP side.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function formatPrice( value ) {
		try {
			return value.toLocaleString( document.documentElement.lang || 'ru-RU' );
		} catch ( e ) {
			return String( value );
		}
	}

	ready( function () {
		var prices = window.STARTER_PRICES || {};
		var nodes = document.querySelectorAll( '[data-price]' );

		nodes.forEach( function ( node ) {
			var key = node.getAttribute( 'data-price' );
			var item = prices[ key ];
			if ( ! item || 'number' !== typeof item.value ) {
				return;
			}

			var qty = parseFloat( node.getAttribute( 'data-price-qty' ) || '1' );
			if ( isNaN( qty ) ) {
				qty = 1;
			}

			node.textContent = formatPrice( item.value * qty );
		} );

		document.dispatchEvent( new CustomEvent( 'starter:prices:ready', { detail: { prices: prices } } ) );
	} );
} )();
