/**
 * Elementor language switcher dropdown: click toggle + Escape / outside close.
 * Desktop hover-open stays in CSS; click uses .active and can override :hover.
 */
( function () {
	'use strict';

	function setOpen( wrap, open ) {
		if ( ! wrap ) {
			return;
		}
		if ( open ) {
			wrap.classList.remove( 'lmat-force-closed' );
			wrap.classList.add( 'active' );
		} else {
			wrap.classList.remove( 'active' );
		}
		var btn = wrap.querySelector( '.lmat-dropdown-trigger' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function closeWrap( wrap ) {
		if ( ! wrap ) {
			return;
		}
		setOpen( wrap, false );
		// Keep closed even if the pointer is still hovering the trigger.
		wrap.classList.add( 'lmat-force-closed' );
		var btn = wrap.querySelector( '.lmat-dropdown-trigger' );
		if ( btn ) {
			btn.blur();
		}
	}

	function closeAll( except ) {
		document.querySelectorAll( '.lmat-wrapper.dropdown.active' ).forEach( function ( wrap ) {
			if ( wrap !== except ) {
				closeWrap( wrap );
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		// Let language links navigate.
		if ( e.target.closest && e.target.closest( '.lmat-wrapper.dropdown .lmat-language-list a' ) ) {
			return;
		}

		var wrap = e.target.closest && e.target.closest( '.lmat-wrapper.dropdown' );
		if ( wrap ) {
			e.preventDefault();
			e.stopPropagation();

			if ( wrap.classList.contains( 'active' ) ) {
				closeWrap( wrap );
			} else {
				closeAll( wrap );
				setOpen( wrap, true );
			}
			return;
		}

		document.querySelectorAll( '.lmat-wrapper.dropdown.active' ).forEach( closeWrap );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}

		var el = document.activeElement;
		var wrap = el && el.closest ? el.closest( '.lmat-wrapper.dropdown' ) : null;
		if ( ! wrap ) {
			wrap = document.querySelector( '.lmat-wrapper.dropdown.active' );
		}
		if ( ! wrap ) {
			wrap = document.querySelector( '.lmat-wrapper.dropdown:hover' );
		}
		if ( wrap ) {
			closeWrap( wrap );
		}
	} );

	// After a forced close, allow hover again once the pointer leaves the switcher.
	document.addEventListener( 'mouseout', function ( e ) {
		var wrap = e.target.closest && e.target.closest( '.lmat-wrapper.dropdown.lmat-force-closed' );
		if ( ! wrap ) {
			return;
		}
		if ( e.relatedTarget && wrap.contains( e.relatedTarget ) ) {
			return;
		}
		wrap.classList.remove( 'lmat-force-closed' );
	} );
}() );
