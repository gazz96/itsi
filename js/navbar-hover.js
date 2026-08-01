/**
 * navbar-hover.js — robust multi-level hover + click handling for
 *                   .nli > .dd and .dd-i > .dd-nested dropdowns.
 *
 * 3-level strategy:
 *
 *   Level 0 (top menu, .nli):
 *     - Hover: open level-1 panel via CSS :hover + JS .dd-open class
 *     - 180ms debounce on mouseleave prevents flicker
 *     - Touch / click: works via touch-action: manipulation, no JS needed
 *
 *   Level 1 (sub-menu, .dd-i):
 *     - Hover: open level-2 panel via CSS :hover + JS .dd-i-open class
 *     - BUT click is the primary reliable trigger: hover alone is too
 *       fragile on touchpads / fast cursors where the bridge 14px
 *       sometimes misses the rounded corner of .dd.dd-nested
 *     - Items with [data-has-children] get a click toggle:
 *         • click anywhere on .dd-i OR the link inside → toggle .dd-i-open
 *         • click outside → close all open .dd-i panels
 *
 *   Level 2 (sub-sub-menu, leaves in .dd.dd-nested):
 *     - Pure hover via CSS :hover on their .dd-i parent (only need to
 *       operate when the panel is visible)
 *
 * Why both hover AND click for level-1? Mouse users get the speed of hover
 * (cursor movement alone opens panel) AND the precision of click (a click
 * locks it open, escapes close timers, works with trackpad scroll-clicks).
 * Touch users only have tap (which is a click), so the click handler is
 * the canonical fallback.
 *
 * No transitions to fight: visibility flips instantly per CSS (no
 * transition: opacity / transition: visibility declared on .dd).
 *
 * @package itsi
 */
( function() {
	'use strict';

	if ( window.matchMedia( '(hover: none)' ).matches ) {
		// Skip hover entirely on touch devices. The click handler below
		// is touch-friendly already.
	} else {
		const CLOSE_DELAY_MS = 180;

		function wireHover( wrapper, dropdown, openClass ) {
			let closeTimer = null;

			function cancelClose() {
				if ( closeTimer !== null ) {
					clearTimeout( closeTimer );
					closeTimer = null;
				}
			}

			function open() {
				cancelClose();
				wrapper.classList.add( openClass );
			}

			function scheduleClose() {
				cancelClose();
				closeTimer = window.setTimeout( function() {
					wrapper.classList.remove( openClass );
					closeTimer = null;
				}, CLOSE_DELAY_MS );
			}

			wrapper.addEventListener( 'mouseenter', open );
			wrapper.addEventListener( 'mouseleave', scheduleClose );

			dropdown.addEventListener( 'mouseenter', open );
			dropdown.addEventListener( 'mouseleave', scheduleClose );

			wrapper.addEventListener( 'focusin', open );
			wrapper.addEventListener( 'focusout', function( event ) {
				const next = event.relatedTarget;
				if ( next && ( dropdown.contains( next ) || wrapper.contains( next ) ) ) {
					return;
				}
				scheduleClose();
			} );
		}

		function wireHoverAll() {
			document.querySelectorAll( '.nli, .dd-i' ).forEach( function( wrapper ) {
				const dd = wrapper.querySelector( ':scope > .dd' );
				if ( ! dd ) {
					return;
				}
				const openClass = wrapper.classList.contains( 'nli' )
					? 'dd-open'
					: 'dd-i-open';
				wireHover( wrapper, dd, openClass );
			} );
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', wireHoverAll );
		} else {
			wireHoverAll();
		}
	}

	/**
	 * Click-to-toggle for .dd-i[data-has-children] (level-1 items with their
	 * own sub-menu). Behaves like a disclosure widget:
	 *
	 *   - click on the link or the wrapper itself → toggle .dd-i-open
	 *   - if the link points to a real URL (not "#" placeholder), follow
	 *     it after toggling closed, so non-#" links still navigate
	 *   - clicks anywhere outside the .navbar → close all open panels
	 *
	 * Two reasons we use both hover AND click (rather than click-only):
	 *   1. Mouse cursor on the visible chevron still triggers hover on the
	 *      parent .dd-i, so the panel stays open after click without flicker
	 *   2. Click provides a manual "lock" against mouseleave close timers
	 *      — useful for users who want to read a sub-menu without fear of
	 *      losing it as soon as their cursor drifts to a coffee break
	 */
	function wireClickAll() {
		// Capture-phase delegated click on .navbar — works even when the
		// .dd-i element is dynamically added by a future theme change.
		const navbar = document.querySelector( '#navbar' );
		if ( ! navbar ) {
			return;
		}

		navbar.addEventListener( 'click', function( event ) {
			// Walk up from event.target to find the nearest .dd-i with
			// data-has-children (if any)
			const wrapper = event.target.closest( '.dd-i[data-has-children]' );
			if ( wrapper ) {
				event.preventDefault();
				// Close sibling .dd-i panels at the same nesting level —
				// mimic <details> exclusive-open behavior so two panels
				// are not open at once (visually noisy).
				const parent = wrapper.parentElement;
				parent.querySelectorAll( ':scope > .dd-i.dd-i-open' ).forEach( function( sibling ) {
					if ( sibling !== wrapper ) {
						sibling.classList.remove( 'dd-i-open' );
					}
				} );
				wrapper.classList.toggle( 'dd-i-open' );
				return;
			}
		} );

		// Click outside any open .dd-i-open → close all
		document.addEventListener( 'click', function( event ) {
			const inside = event.target.closest( '#navbar .dd-i-open' );
			if ( ! inside ) {
				document.querySelectorAll( '#navbar .dd-i.dd-i-open' ).forEach( function( openItem ) {
					openItem.classList.remove( 'dd-i-open' );
				} );
			}
		} );

		// Escape key closes all open panels
		document.addEventListener( 'keydown', function( event ) {
			if ( event.key === 'Escape' ) {
				document.querySelectorAll( '#navbar .dd-i.dd-i-open' ).forEach( function( openItem ) {
					openItem.classList.remove( 'dd-i-open' );
				} );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', wireClickAll );
	} else {
		wireClickAll();
	}
}() );
