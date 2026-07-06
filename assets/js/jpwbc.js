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

	// Filter bar: dual-handle price range slider, synced two-way with the
	// min/max number inputs (which are what actually submit). Progressive
	// enhancement — the number inputs work on their own without JS.
	function bindPriceSlider( bar ) {
		var sliders = bar.querySelectorAll( '.jpwbc-price-slider' );
		Array.prototype.forEach.call( sliders, function ( slider ) {
			var lower = slider.querySelector( '.jpwbc-price-slider__lower' );
			var upper = slider.querySelector( '.jpwbc-price-slider__upper' );
			var fill  = slider.querySelector( '.jpwbc-price-slider__fill' );
			var form  = slider.closest( 'form' );
			if ( ! lower || ! upper || ! form ) {
				return;
			}
			var numMin = form.querySelector( 'input[name="jpwbc_min_price"]' );
			var numMax = form.querySelector( 'input[name="jpwbc_max_price"]' );
			var min    = parseFloat( slider.getAttribute( 'data-min' ) ) || 0;
			var max    = parseFloat( slider.getAttribute( 'data-max' ) ) || 100;
			var span   = ( max - min ) || 1;

			function paint() {
				var lo = parseFloat( lower.value );
				var hi = parseFloat( upper.value );
				if ( lo > hi ) { var t = lo; lo = hi; hi = t; }
				if ( fill ) {
					fill.style.left  = ( ( lo - min ) / span * 100 ) + '%';
					fill.style.right = ( ( max - hi ) / span * 100 ) + '%';
				}
			}

			// Slider -> number inputs.
			lower.addEventListener( 'input', function () {
				if ( parseFloat( lower.value ) > parseFloat( upper.value ) ) {
					lower.value = upper.value;
				}
				if ( numMin ) { numMin.value = lower.value; }
				paint();
			} );
			upper.addEventListener( 'input', function () {
				if ( parseFloat( upper.value ) < parseFloat( lower.value ) ) {
					upper.value = lower.value;
				}
				if ( numMax ) { numMax.value = upper.value; }
				paint();
			} );

			// Number inputs -> slider.
			if ( numMin ) {
				numMin.addEventListener( 'input', function () {
					var v = parseFloat( numMin.value );
					if ( ! isNaN( v ) ) { lower.value = Math.min( Math.max( v, min ), max ); paint(); }
				} );
			}
			if ( numMax ) {
				numMax.addEventListener( 'input', function () {
					var v = parseFloat( numMax.value );
					if ( ! isNaN( v ) ) { upper.value = Math.min( Math.max( v, min ), max ); paint(); }
				} );
			}

			paint();
		} );
	}

	// Filter bar: only one dropdown open at a time (accordion). Works with or
	// without AJAX. Re-applied to the fresh bar after each AJAX swap.
	function bindAccordion( bar ) {
		var items = bar.querySelectorAll( 'details[data-jpwbc-facet]' );
		Array.prototype.forEach.call( items, function ( d ) {
			d.addEventListener( 'toggle', function () {
				if ( ! d.open ) {
					return;
				}
				Array.prototype.forEach.call( items, function ( other ) {
					if ( other !== d ) {
						other.open = false;
					}
				} );
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
		var extraSel = [ '.woocommerce-pagination', '.woocommerce-result-count' ];

		// Resolve the product-grid container: the configured selector first, then
		// common WooCommerce / Elementor fallbacks, so AJAX engages out of the box.
		var candidates = [
			bar.getAttribute( 'data-jpwbc-results' ),
			'ul.products',
			'.elementor-widget-woocommerce-products ul.products',
			'.woocommerce ul.products',
			'.products'
		].filter( Boolean );
		var resultsSel = null;
		for ( var ci = 0; ci < candidates.length; ci++ ) {
			if ( document.querySelector( candidates[ ci ] ) ) {
				resultsSel = candidates[ ci ];
				break;
			}
		}
		if ( ! resultsSel ) {
			return; // grid not found — leave default (full-page) navigation.
		}

		function currentResults() {
			return document.querySelector( resultsSel );
		}

		// Mark the bar so CSS can hide the no-JS-only Apply/Go buttons.
		bar.classList.add( 'jpwbc-ajax-active' );

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

					// Replace the filter bar, preserving open dropdowns + mobile drawer state.
					var wasOpen   = openKeys( bar );
					var wasDrawer = bar.classList.contains( 'jpwbc-drawer-open' );
					var wasSort   = bar.classList.contains( 'jpwbc-drawer-sort' );
					var nextBar   = doc.querySelector( '.jpwbc-filterbar' );
					var liveBar   = bar;
					if ( nextBar && bar.parentNode ) {
						bar.parentNode.replaceChild( nextBar, bar );
						liveBar = nextBar;
						wasOpen.forEach( function ( key ) {
							var d = nextBar.querySelector( 'details[data-jpwbc-facet="' + ( window.CSS && CSS.escape ? CSS.escape( key ) : key ) + '"]' );
							if ( d ) {
								d.open = true;
							}
						} );
						if ( wasDrawer ) {
							nextBar.classList.add( 'jpwbc-drawer-open' );
							nextBar.classList.toggle( 'jpwbc-drawer-sort', wasSort );
							nextBar.classList.toggle( 'jpwbc-drawer-filter', ! wasSort );
						}
						bindFilterAjax( liveBar );
						bindAccordion( liveBar );
						bindPriceSlider( liveBar );
					}

					if ( push ) {
						window.history.pushState( { jpwbcAjax: 1 }, '', url );
					}

					// Don't yank the page while the mobile drawer is open.
					var top = document.querySelector( resultsSel );
					if ( top && top.scrollIntoView && ! ( liveBar && liveBar.classList.contains( 'jpwbc-drawer-open' ) ) ) {
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
			var t = e.target;
			// Auto-apply only for selects (sort) and checkboxes (facets); price
			// number inputs keep their Apply button so typing doesn't fire early.
			if ( ! t || ! t.matches || ! ( t.matches( 'select' ) || t.matches( 'input[type="checkbox"]' ) ) ) {
				return;
			}
			var form = t.closest ? t.closest( 'form' ) : null;
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

	// Mobile drawer: the "Filter"/"Sort" buttons open the filter bar as a slide-in
	// panel; the close button, backdrop and "Show results" close it. Bound once at
	// document level so it survives AJAX bar swaps.
	function setDrawer( open, mode ) {
		var bar      = document.querySelector( '.jpwbc-filterbar' );
		var backdrop = document.querySelector( '.jpwbc-filter-backdrop' );
		if ( ! bar ) {
			return;
		}
		if ( open ) {
			bar.classList.add( 'jpwbc-drawer-open' );
			bar.classList.toggle( 'jpwbc-drawer-sort', 'sort' === mode );
			bar.classList.toggle( 'jpwbc-drawer-filter', 'sort' !== mode );
			if ( backdrop ) { backdrop.hidden = false; }
			document.body.classList.add( 'jpwbc-drawer-lock' );
			// Sort drawer: show the options straight away (like a sort sheet).
			if ( 'sort' === mode ) {
				var sortEl = bar.querySelector( '.jpwbc-filter--sort' );
				if ( sortEl ) { sortEl.open = true; }
			}
		} else {
			bar.classList.remove( 'jpwbc-drawer-open', 'jpwbc-drawer-sort', 'jpwbc-drawer-filter' );
			if ( backdrop ) { backdrop.hidden = true; }
			document.body.classList.remove( 'jpwbc-drawer-lock' );
		}
	}

	function bindMobileDrawer() {
		document.addEventListener( 'click', function ( e ) {
			var opener = e.target.closest ? e.target.closest( '[data-jpwbc-open]' ) : null;
			if ( opener ) {
				e.preventDefault();
				setDrawer( true, opener.getAttribute( 'data-jpwbc-open' ) );
				return;
			}
			var closer = e.target.closest ? e.target.closest( '[data-jpwbc-close]' ) : null;
			if ( closer ) {
				e.preventDefault();
				setDrawer( false );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { setDrawer( false ); }
		} );
	}

	// Close any open filter dropdown when clicking outside it (desktop). The
	// mobile drawer is exempt — it closes via its backdrop / close button.
	function bindOutsideClose() {
		document.addEventListener( 'click', function ( e ) {
			var bar = document.querySelector( '.jpwbc-filterbar' );
			if ( ! bar || bar.classList.contains( 'jpwbc-drawer-open' ) ) {
				return;
			}
			var open = bar.querySelectorAll( '.jpwbc-filter[open]' );
			Array.prototype.forEach.call( open, function ( d ) {
				if ( ! d.contains( e.target ) ) {
					d.open = false;
				}
			} );
		} );
	}

	// Floating "Filter" button: appears once the filter bar scrolls out of view;
	// click scrolls back to it (desktop) or opens the drawer (mobile).
	function bindFilterJump() {
		var jump = document.querySelector( '.jpwbc-filter-jump' );
		if ( ! jump ) {
			return;
		}
		var ticking = false;
		function update() {
			ticking = false;
			var bar = document.querySelector( '.jpwbc-filterbar' );
			if ( ! bar ) { jump.hidden = true; return; }
			var r = bar.getBoundingClientRect();
			jump.hidden = ! ( r.bottom < 10 ); // bar scrolled above the viewport top.
		}
		function onScroll() {
			if ( ! ticking ) { ticking = true; window.requestAnimationFrame( update ); }
		}
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll );
		jump.addEventListener( 'click', function () {
			if ( window.matchMedia && window.matchMedia( '(max-width: 768px)' ).matches ) {
				setDrawer( true, 'filter' );
				return;
			}
			var bar = document.querySelector( '.jpwbc-filterbar' );
			if ( bar && bar.scrollIntoView ) {
				bar.scrollIntoView( { behavior: prefersReduced ? 'auto' : 'smooth', block: 'start' } );
			}
		} );
		update();
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
				bindAccordion( root );
				bindPriceSlider( root );
				bindFilterAjax( root );
			}
		} );

		document.addEventListener( 'click', trackClick, true );

		bindMobileDrawer();
		bindFilterJump();
		bindOutsideClose();
	} );
}() );
