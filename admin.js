/* KhabarSEO admin script. Plain ES2017, no build step. */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/** Show/hide fields that depend on a radio choice. */
	function initDependencies() {
		var dependents = document.querySelectorAll( '[data-khs-depends-key]' );
		if ( ! dependents.length ) {
			return;
		}
		function currentValue( key ) {
			var checked = document.querySelector( 'input[name="khs[' + key + ']"]:checked' );
			return checked ? checked.value : null;
		}
		function update() {
			dependents.forEach( function ( el ) {
				var value = currentValue( el.getAttribute( 'data-khs-depends-key' ) );
				if ( value === null ) {
					return;
				}
				el.hidden = value !== el.getAttribute( 'data-khs-depends-value' );
			} );
		}
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.type === 'radio' ) {
				update();
			}
		} );
		update();
	}

	/** Media picker for image fields. */
	function initMedia() {
		document.querySelectorAll( '[data-khs-media]' ).forEach( function ( box ) {
			var input = box.querySelector( 'input[type="hidden"]' );
			var preview = box.querySelector( '.khs-media__preview' );
			var select = box.querySelector( '.khs-media__select' );
			var remove = box.querySelector( '.khs-media__remove' );
			var frame = null;

			select.addEventListener( 'click', function () {
				if ( ! window.wp || ! window.wp.media ) {
					return;
				}
				if ( ! frame ) {
					frame = window.wp.media( {
						title: box.getAttribute( 'data-title' ),
						button: { text: box.getAttribute( 'data-button' ) },
						library: { type: 'image' },
						multiple: false
					} );
					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						var url = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
						input.value = attachment.id;
						preview.innerHTML = '';
						var img = document.createElement( 'img' );
						img.className = 'khs-media__img';
						img.src = url;
						img.alt = '';
						preview.appendChild( img );
						remove.hidden = false;
					} );
				}
				frame.open();
			} );

			remove.addEventListener( 'click', function () {
				input.value = '0';
				preview.innerHTML = '';
				remove.hidden = true;
			} );
		} );
	}

	/** Copy the diagnostics text. */
	function initCopy() {
		document.querySelectorAll( '[data-khs-copy]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var target = document.querySelector( button.getAttribute( 'data-khs-copy' ) );
				var notice = button.parentNode.querySelector( '.khs-copied' );
				if ( ! target ) {
					return;
				}
				function done() {
					if ( notice ) {
						notice.hidden = false;
						window.setTimeout( function () {
							notice.hidden = true;
						}, 2500 );
					}
				}
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( target.value ).then( done );
				} else {
					target.focus();
					target.select();
					try {
						document.execCommand( 'copy' );
						done();
					} catch ( e ) {
						/* Selection stays highlighted so the user can copy manually. */
					}
				}
			} );
		} );
	}

	ready( function () {
		initDependencies();
		initMedia();
		initCopy();
	} );
}() );
