/**
 * Biscotto — Cookie registry editor (admin, Cookies tab).
 * --------------------------------------------------------------------------
 * Add/remove rows in the cookie registry, clear the registry, and copy the
 * cookie-policy shortcode. Translated strings arrive via window.consentkitCookies
 * (wp_localize_script), so no PHP is echoed inline (WP.org guideline).
 * --------------------------------------------------------------------------
 */
( function () {
	var cfg = window.consentkitCookies || {};
	var idx = 9001;
	var tbody = document.getElementById( 'biscotto-cookie-rows' );
	if ( ! tbody ) {
		return;
	}

	var addBtn = document.getElementById( 'biscotto-add-cookie' );
	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () {
			var last = tbody.rows[ tbody.rows.length - 1 ];
			var clone = last.cloneNode( true );
			clone.querySelectorAll( 'input, select' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[cookies\]\[\d+\]/, '[cookies][' + idx + ']' );
				if ( field.tagName === 'INPUT' ) { field.value = ''; }
			} );
			tbody.appendChild( clone );
			idx++;
		} );
	}

	tbody.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'biscotto-remove-row' ) ) {
			e.preventDefault();
			if ( tbody.rows.length > 1 ) { e.target.closest( 'tr' ).remove(); }
		}
	} );

	// Svuota registro: rimuove tutte le righe e ne lascia una vuota (template).
	// Al salvataggio il registro risulta vuoto.
	var clearBtn = document.getElementById( 'biscotto-clear-cookies' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( cfg.confirmClear || '' ) ) {
				return;
			}
			while ( tbody.rows.length > 1 ) { tbody.deleteRow( 0 ); }
			var last = tbody.rows[ 0 ];
			if ( last ) {
				last.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
				var sel = last.querySelector( 'select' );
				if ( sel ) { sel.value = 'necessary'; }
			}
		} );
	}

	// Seleziona tutto il testo al click sul campo shortcode (era un onclick inline).
	var shortcodeInput = document.getElementById( 'biscotto-shortcode-copy' );
	if ( shortcodeInput ) {
		shortcodeInput.addEventListener( 'click', function () { this.select(); } );
	}

	// Copia lo shortcode della cookie page negli appunti.
	var copyBtn = document.getElementById( 'biscotto-copy-shortcode' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			var input = document.getElementById( 'biscotto-shortcode-copy' );
			var status = document.getElementById( 'biscotto-copy-status' );
			var announce = function () {
				if ( status ) { status.textContent = cfg.copied || ''; }
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( input.value ).then( announce, function () {
					input.select();
					input.setSelectionRange( 0, 99999 );
					document.execCommand( 'copy' );
					announce();
				} );
			} else {
				input.select();
				input.setSelectionRange( 0, 99999 );
				document.execCommand( 'copy' );
				announce();
			}
		} );
	}
} )();
