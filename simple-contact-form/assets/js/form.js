/* Simple Contact Form — progressive enhancement: submit via AJAX, show inline errors. */
( function () {
	'use strict';

	if ( ! window.fetch || ! window.FormData || ! window.scfForm ) {
		return; // Fall back to a normal POST.
	}

	var FIELDS = [ 'name', 'email', 'phone', 'subject', 'message', 'consent' ];

	function clearErrors( form ) {
		form.querySelectorAll( '.scf-error' ).forEach( function ( el ) {
			el.remove();
		} );
		form.querySelectorAll( '[aria-invalid]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
			el.removeAttribute( 'aria-describedby' );
		} );
	}

	function showNotice( wrap, type, text ) {
		var notice = wrap.querySelector( '.scf-notice' );
		notice.className = 'scf-notice scf-notice--' + type;
		notice.textContent = text;
		notice.hidden = false;
	}

	function showErrors( form, errors ) {
		var first = null;
		FIELDS.forEach( function ( key ) {
			if ( ! errors[ key ] ) {
				return;
			}
			var input = form.querySelector( '[name="scf_' + key + '"]' );
			if ( ! input ) {
				return;
			}
			var msg = document.createElement( 'span' );
			msg.className = 'scf-error';
			msg.id = input.id ? input.id + '-error' : 'scf-' + key + '-error';
			msg.textContent = errors[ key ];
			var holder = input.closest( '.scf-field' ) || input.parentNode;
			holder.appendChild( msg );
			input.setAttribute( 'aria-invalid', 'true' );
			input.setAttribute( 'aria-describedby', msg.id );
			first = first || input;
		} );
		if ( first ) {
			first.focus();
		}
	}

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.classList || ! form.classList.contains( 'scf-form' ) ) {
			return;
		}
		e.preventDefault();

		var wrap = form.closest( '.scf-wrap' );
		var button = form.querySelector( '.scf-submit' );
		var label = button.textContent;

		clearErrors( form );
		button.disabled = true;
		button.textContent = window.scfForm.sending;
		form.setAttribute( 'aria-busy', 'true' );

		fetch( window.scfForm.ajaxUrl, {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					showNotice( wrap, 'success', json.data.message );
					form.reset();
					form.hidden = true;
					wrap.querySelector( '.scf-notice' ).focus();
					return;
				}
				var errors = ( json && json.data && json.data.errors ) || {};
				showNotice( wrap, 'error', errors._form || window.scfForm.invalid );
				showErrors( form, errors );
			} )
			.catch( function () {
				showNotice( wrap, 'error', window.scfForm.error );
			} )
			.then( function () {
				button.disabled = false;
				button.textContent = label;
				form.removeAttribute( 'aria-busy' );
			} );
	} );
} )();
