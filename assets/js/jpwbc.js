/**
 * JezPress Woo Brand Categories — front-end behaviour.
 *
 * Progressive enhancement: every category is a real <a> that works without JS.
 * This script adds expand/collapse, an optional brand filter, and lazy loading
 * of other brands' categories over AJAX.
 *
 * @since 1.0.0
 */
( function () {
	'use strict';

	var cfg = window.jpwbcFront || {};
	var prefersReduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function openPanel( li, panel, toggle ) {
		panel.hidden = false;
		li.classList.add( 'is-open' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'true' );
		}
	}

	function closePanel( li, panel, toggle ) {
		panel.hidden = true;
		li.classList.remove( 'is-open' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'false' );
		}
	}

	function lazyLoad( li, panel ) {
		if ( panel.getAttribute( 'data-loaded' ) === '1' || panel.getAttribute( 'data-loading' ) === '1' ) {
			return;
		}
		var brandId = li.getAttribute( 'data-brand-id' );
		if ( ! brandId || ! cfg.ajaxUrl ) {
			return;
		}
		panel.setAttribute( 'data-loading', '1' );
		panel.innerHTML = '<p class="jpwbc-loading">' + ( ( cfg.i18n && cfg.i18n.loading ) || 'Loading…' ) + '</p>';

		var body = new URLSearchParams();
		body.append( 'action', 'jpwbc_brand_categories' );
		body.append( 'nonce', cfg.nonce || '' );
		body.append( 'brand_id', brandId );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res && res.success && res.data && typeof res.data.html === 'string' ) {
					panel.innerHTML = res.data.html;
					panel.setAttribute( 'data-loaded', '1' );
				} else {
					panel.innerHTML = '<p class="jpwbc-error">' + ( ( cfg.i18n && cfg.i18n.error ) || 'Could not load categories.' ) + '</p>';
				}
			} )
			.catch( function () {
				panel.innerHTML = '<p class="jpwbc-error">' + ( ( cfg.i18n && cfg.i18n.error ) || 'Could not load categories.' ) + '</p>';
			} )
			.finally( function () {
				panel.removeAttribute( 'data-loading' );
			} );
	}

	function bindToggles( root ) {
		root.addEventListener( 'click', function ( e ) {
			var toggle = e.target.closest ? e.target.closest( '.jpwbc-brand__toggle' ) : null;
			if ( ! toggle ) {
				return;
			}
			e.preventDefault();
			var li = toggle.closest( '.jpwbc-brand' );
			if ( ! li ) {
				return;
			}
			var panel = li.querySelector( '.jpwbc-brand__panel' );
			if ( ! panel ) {
				return;
			}
			if ( li.classList.contains( 'is-open' ) ) {
				closePanel( li, panel, toggle );
			} else {
				if ( panel.getAttribute( 'data-lazy' ) === '1' ) {
					lazyLoad( li, panel );
				}
				openPanel( li, panel, toggle );
			}
		} );
	}

	function bindSearch( root ) {
		var input = root.querySelector( '.jpwbc-search__input' );
		if ( ! input ) {
			return;
		}
		var brands = Array.prototype.slice.call( root.querySelectorAll( '.jpwbc-brands > .jpwbc-brand' ) );
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			brands.forEach( function ( li ) {
				var link = li.querySelector( '.jpwbc-brand__link' );
				var name = link ? link.textContent.trim().toLowerCase() : '';
				li.style.display = ( '' === q || name.indexOf( q ) !== -1 ) ? '' : 'none';
			} );
		} );
	}

	// Record brand-link clicks (feeds the Trending Brands ordering). Fires on
	// capture so it runs before the browser navigates away; sendBeacon survives
	// the page unload. Best-effort — a failed ping just means one uncounted click.
	function trackClick( e ) {
		var link = e.target.closest ? e.target.closest( '[data-jpwbc-brand]' ) : null;
		if ( ! link ) {
			return;
		}
		var id = link.getAttribute( 'data-jpwbc-brand' );
		if ( ! id || ! cfg.ajaxUrl ) {
			return;
		}
		var data = new FormData();
		data.append( 'action', 'jpwbc_track_click' );
		data.append( 'nonce', cfg.nonce || '' );
		data.append( 'brand_id', id );

		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( cfg.ajaxUrl, data );
		} else if ( window.fetch ) {
			fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true } );
		}
	}

	// Live brand filter for the All Brands (A-Z) directory: hides non-matching
	// brands and any letter section left with no visible brands.
	function bindBrandFilter( root ) {
		var input = root.querySelector( '.jpwbc-brandfilter' );
		if ( ! input ) {
			return;
		}
		var groups = Array.prototype.slice.call( root.querySelectorAll( '.jpwbc-az-group' ) );
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			groups.forEach( function ( group ) {
				var items = group.querySelectorAll( '.jpwbc-az-group__item' );
				var visible = 0;
				Array.prototype.forEach.call( items, function ( item ) {
					var link = item.querySelector( 'a' );
					var name = link ? link.textContent.trim().toLowerCase() : '';
					var match = '' === q || name.indexOf( q ) !== -1;
					item.style.display = match ? '' : 'none';
					if ( match ) {
						visible++;
					}
				} );
				group.style.display = ( 0 === visible ) ? 'none' : '';
			} );
		} );
	}

	// Filter bar: auto-submit a form on <select> change (and hide its manual
	// submit button, which is only there for the no-JS fallback).
	function bindAutoSubmit( root ) {
		var forms = root.querySelectorAll( 'form.jpwbc-autosubmit' );
		Array.prototype.forEach.call( forms, function ( form ) {
			form.classList.add( 'jpwbc-js' );
			var select = form.querySelector( 'select' );
			if ( ! select ) {
				return;
			}
			select.addEventListener( 'change', function () {
				form.submit();
			} );
		} );
	}

	ready( function () {
		var roots = document.querySelectorAll( '.jpwbc-brand-cats' );
		Array.prototype.forEach.call( roots, function ( root ) {
			if ( prefersReduced ) {
				root.classList.add( 'jpwbc-no-motion' );
			}
			bindToggles( root );
			bindSearch( root );
			bindBrandFilter( root );
			bindAutoSubmit( root );
		} );

		document.addEventListener( 'click', trackClick, true );
	} );
}() );
