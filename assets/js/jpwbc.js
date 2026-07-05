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
	// submit button, which is only there for the no-JS fallback). Skipped for
	// bars handled by the AJAX enhancement (that binds its own change handler).
	function bindAutoSubmit( root ) {
		var forms = root.querySelectorAll( 'form.jpwbc-autosubmit' );
		Array.prototype.forEach.call( forms, function ( form ) {
			form.classList.add( 'jpwbc-js' );
			if ( form.closest( '.jpwbc-filterbar[data-jpwbc-ajax="1"]' ) ) {
				return;
			}
			var select = form.querySelector( 'select' );
			if ( ! select ) {
				return;
			}
			select.addEventListener( 'change', function () {
				form.submit();
			} );
		} );
	}

	// Filter bar AJAX: fetch the target (filtered) URL, swap the product grid +
	// the filter bar, and pushState — a progressive enhancement over the plain
	// query-param links/forms, which keep working without JS. If the results
	// container can't be found, we do nothing and let normal navigation happen.
	function bindFilterAjax( bar ) {
		if ( ! bar || bar.getAttribute( 'data-jpwbc-ajax' ) !== '1' ) {
			return;
		}
		if ( ! window.fetch || ! window.history || ! window.DOMParser ) {
			return; // fall back to full-page navigation.
		}
		var resultsSel = bar.getAttribute( 'data-jpwbc-results' ) || 'ul.products';
		var extraSel   = [ '.woocommerce-pagination', '.woocommerce-result-count' ];

		function currentResults() {
			return document.querySelector( resultsSel );
		}
		if ( ! currentResults() ) {
			return; // grid not on this page / wrong selector — leave default behaviour.
		}

		function openKeys( scope ) {
			var keys = [];
			Array.prototype.forEach.call( scope.querySelectorAll( 'details[data-jpwbc-facet][open]' ), function ( d ) {
				keys.push( d.getAttribute( 'data-jpwbc-facet' ) );
			} );
			return keys;
		}

		function swap( sel, doc ) {
			var next = doc.querySelector( sel );
			var curr = document.querySelector( sel );
			if ( next && curr && curr.parentNode ) {
				curr.parentNode.replaceChild( next, curr );
				return next;
			}
			return null;
		}

		function navigate( url, push ) {
			var results = currentResults();
			if ( results ) {
				results.classList.add( 'jpwbc-loading-grid' );
			}
			fetch( url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } } )
				.then( function ( r ) { return r.text(); } )
				.then( function ( html ) {
					var doc = new window.DOMParser().parseFromString( html, 'text/html' );

					swap( resultsSel, doc );
					extraSel.forEach( function ( s ) { swap( s, doc ); } );

					// Replace the filter bar, preserving which dropdowns were open.
					var wasOpen = openKeys( bar );
					var nextBar = doc.querySelector( '.jpwbc-filterbar' );
					var liveBar = bar;
					if ( nextBar && bar.parentNode ) {
						bar.parentNode.replaceChild( nextBar, bar );
						liveBar = nextBar;
						wasOpen.forEach( function ( key ) {
							var d = nextBar.querySelector( 'details[data-jpwbc-facet="' + ( window.CSS && CSS.escape ? CSS.escape( key ) : key ) + '"]' );
							if ( d ) {
								d.open = true;
							}
						} );
						bindFilterAjax( liveBar );
					}

					if ( push ) {
						window.history.pushState( { jpwbcAjax: 1 }, '', url );
					}

					var top = document.querySelector( resultsSel );
					if ( top && top.scrollIntoView ) {
						top.scrollIntoView( { behavior: prefersReduced ? 'auto' : 'smooth', block: 'start' } );
					}
				} )
				.catch( function () {
					window.location.href = url; // network error → hard navigate.
				} )
				.finally( function () {
					var r2 = currentResults();
					if ( r2 ) {
						r2.classList.remove( 'jpwbc-loading-grid' );
					}
				} );
		}

		function urlFromForm( form ) {
			var action = form.getAttribute( 'action' ) || window.location.pathname;
			var params = new URLSearchParams( new FormData( form ) );
			var qs     = params.toString();
			return qs ? action + '?' + qs : action;
		}

		// Links (category / facet options / clear).
		bar.addEventListener( 'click', function ( e ) {
			var link = e.target.closest ? e.target.closest( 'a.jpwbc-filter__opt, a.jpwbc-filter__clear' ) : null;
			if ( ! link || ! link.href ) {
				return;
			}
			e.preventDefault();
			navigate( link.href, true );
		} );

		// Forms (price / sort / facet). Intercept submit; auto-submit on change.
		bar.addEventListener( 'submit', function ( e ) {
			var form = e.target.closest ? e.target.closest( 'form' ) : null;
			if ( ! form ) {
				return;
			}
			e.preventDefault();
			navigate( urlFromForm( form ), true );
		} );
		bar.addEventListener( 'change', function ( e ) {
			var form = e.target.closest ? e.target.closest( 'form' ) : null;
			if ( ! form ) {
				return;
			}
			if ( form.requestSubmit ) {
				form.requestSubmit();
			} else {
				navigate( urlFromForm( form ), true );
			}
		} );

		// Pagination links inside the swapped grid.
		document.addEventListener( 'click', function ( e ) {
			var pl = e.target.closest ? e.target.closest( '.woocommerce-pagination a, a.page-numbers' ) : null;
			if ( ! pl || ! pl.href || ! currentResults() ) {
				return;
			}
			e.preventDefault();
			navigate( pl.href, true );
		} );

		if ( ! window.jpwbcPopstateBound ) {
			window.jpwbcPopstateBound = true;
			window.addEventListener( 'popstate', function () {
				var b = document.querySelector( '.jpwbc-filterbar[data-jpwbc-ajax="1"]' );
				if ( b ) {
					// Re-fetch the now-current URL without pushing another state.
					b.dispatchEvent( new CustomEvent( 'jpwbc:popnav' ) );
				}
			} );
		}
		bar.addEventListener( 'jpwbc:popnav', function () {
			navigate( window.location.href, false );
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
			if ( root.classList.contains( 'jpwbc-filterbar' ) ) {
				bindFilterAjax( root );
			}
		} );

		document.addEventListener( 'click', trackClick, true );
	} );
}() );
