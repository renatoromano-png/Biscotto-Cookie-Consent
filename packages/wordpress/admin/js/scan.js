/**
 * ConsentKit — Admin scan orchestrator (roadmap §14).
 * Scan ibrido: (1) analisi veloce lato server di tutti gli URL (endpoint
 * /scan/server, no rendering); (2) pass a runtime via iframe nascosto SOLO
 * sulla homepage per i servizi iniettati via JavaScript. I risultati vengono
 * uniti e l'utente li importa nel cookie registry.
 */
( function () {
	'use strict';

	var cfg = window.ckScan || {};
	var rowsData = [];

	function $( id ) { return document.getElementById( id ); }

	function setStatus( el, msg ) { if ( el ) { el.textContent = msg || ''; } }

	function withScanParam( url ) {
		try {
			var u = new URL( url, cfg.origin );
			// Sicurezza: scansiona SOLO lo stesso sito. Non appendere mai il token
			// di scan a URL di terze parti (eviterebbe di esporlo via URL/referrer).
			if ( u.origin !== cfg.origin ) {
				return null;
			}
			u.searchParams.set( 'ck_scan', cfg.scanNonce );
			return u.href;
		} catch ( e ) {
			return null;
		}
	}

	// Carica un URL in un iframe nascosto e risolve col finding (o null al timeout).
	function scanUrl( url ) {
		return new Promise( function ( resolve ) {
			var target = withScanParam( url );
			if ( !target ) { resolve( null ); return; }

			var frame = document.createElement( 'iframe' );
			var done = false;
			var timer;

			function finish( finding ) {
				if ( done ) { return; }
				done = true;
				window.removeEventListener( 'message', onMessage );
				if ( timer ) { window.clearTimeout( timer ); }
				if ( frame.parentNode ) { frame.parentNode.removeChild( frame ); }
				resolve( finding );
			}

			function onMessage( e ) {
				if ( e.origin !== cfg.origin ) { return; }
				if ( !e.data || !e.data.__ckScan || !e.data.finding ) { return; }
				if ( e.source !== frame.contentWindow ) { return; }
				finish( e.data.finding );
			}

			window.addEventListener( 'message', onMessage );
			// Margine oltre l'attesa interna del collector (SETTLE_MS = 4s).
			timer = window.setTimeout( function () { finish( null ); }, ( cfg.timeoutMs || 12000 ) );

			frame.src = target;
			$( 'ck-scan-frames' ).appendChild( frame );
		} );
	}

	function rest( url, body ) {
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) { return r.json(); } );
	}

	// Unisce due liste di suggerimenti deduplicando per nome.
	function mergeSuggestions( a, b ) {
		var seen = {}, out = [];
		( a || [] ).concat( b || [] ).forEach( function ( r ) {
			var k = ( r && r.name ? r.name : '' ).toLowerCase();
			if ( !k || seen[ k ] ) { return; }
			seen[ k ] = true;
			out.push( r );
		} );
		return out;
	}

	function renderRows( suggestions ) {
		rowsData = suggestions || [];
		var tbody = $( 'ck-scan-rows' );
		tbody.innerHTML = '';

		if ( !rowsData.length ) {
			var tr = document.createElement( 'tr' );
			var td = document.createElement( 'td' );
			td.colSpan = 6;
			td.textContent = cfg.i18n.nothing;
			tr.appendChild( td );
			tbody.appendChild( tr );
		}

		rowsData.forEach( function ( row, i ) {
			var tr = document.createElement( 'tr' );

			var tdCheck = document.createElement( 'td' );
			tdCheck.className = 'check-column';
			var cb = document.createElement( 'input' );
			cb.type = 'checkbox';
			cb.checked = true;
			cb.setAttribute( 'data-i', i );
			cb.className = 'ck-scan-pick';
			tdCheck.appendChild( cb );

			var tdName = document.createElement( 'td' );
			tdName.textContent = row.name || '';

			var tdService = document.createElement( 'td' );
			tdService.textContent = row.service || '';
			if ( row.url_policy ) {
				tdService.appendChild( document.createTextNode( ' ' ) );
				var infoLink = document.createElement( 'a' );
				infoLink.href = row.url_policy;
				infoLink.target = '_blank';
				infoLink.rel = 'noopener nofollow';
				infoLink.textContent = cfg.i18n.info;
				tdService.appendChild( infoLink );
			}

			var tdDuration = document.createElement( 'td' );
			tdDuration.textContent = row.duration || '';

			var tdCat = document.createElement( 'td' );
			var sel = document.createElement( 'select' );
			sel.setAttribute( 'data-i', i );
			sel.className = 'ck-scan-cat';
			Object.keys( cfg.categories ).forEach( function ( slug ) {
				var opt = document.createElement( 'option' );
				opt.value = slug;
				opt.textContent = cfg.categories[ slug ];
				if ( slug === row.category ) { opt.selected = true; }
				sel.appendChild( opt );
			} );
			tdCat.appendChild( sel );

			var tdSource = document.createElement( 'td' );
			tdSource.textContent = row.source === 'domain' ? cfg.i18n.sourceDomain : cfg.i18n.sourceCookie;

			tr.appendChild( tdCheck );
			tr.appendChild( tdName );
			tr.appendChild( tdService );
			tr.appendChild( tdDuration );
			tr.appendChild( tdCat );
			tr.appendChild( tdSource );
			tbody.appendChild( tr );
		} );

		$( 'ck-scan-results' ).hidden = false;
	}

	function startScan() {
		var btn = $( 'ck-scan-start' );
		var status = $( 'ck-scan-status' );
		var allUrls = ( $( 'ck-scan-urls' ).value || '' )
			.split( '\n' )
			.map( function ( s ) { return s.trim(); } )
			.filter( function ( s ) { return s.length; } );

		// Sicurezza: tiene solo gli URL dello stesso sito (vedi withScanParam).
		var urls = [], skipped = 0;
		allUrls.forEach( function ( u ) {
			if ( withScanParam( u ) ) { urls.push( u ); } else { skipped++; }
		} );

		if ( !urls.length ) { setStatus( status, cfg.i18n.noUrls ); return; }

		// Tetto a 10 URL.
		var cap = cfg.maxUrls || 10;
		var tooMany = urls.length > cap;
		if ( tooMany ) { urls = urls.slice( 0, cap ); }

		btn.disabled = true;

		// 1) Scan veloce lato server di tutti gli URL (no rendering).
		setStatus( status, cfg.i18n.scanningServer );
		rest( cfg.serverUrl, { urls: urls } )
			.then( function ( srv ) {
				var serverSug = srv && srv.suggestions ? srv.suggestions : [];
				// 2) Pass a runtime SOLO sulla homepage (primo URL) per i servizi via JS.
				setStatus( status, cfg.i18n.scanningHome );
				return scanUrl( urls[ 0 ] ).then( function ( finding ) {
					var findings = finding ? [ finding ] : [];
					setStatus( status, cfg.i18n.classifying );
					return rest( cfg.collectUrl, { findings: findings } ).then( function ( rt ) {
						var rtSug = rt && rt.suggestions ? rt.suggestions : [];
						return mergeSuggestions( serverSug, rtSug );
					} );
				} );
			} )
			.then( function ( merged ) {
				renderRows( merged );
				var msg = cfg.i18n.done;
				if ( tooMany ) { msg += ' ' + cfg.i18n.tooMany; }
				if ( skipped > 0 ) { msg += ' ' + cfg.i18n.externalSkipped.replace( '%d', skipped ); }
				setStatus( status, msg );
			} )
			.catch( function () { setStatus( status, cfg.i18n.error ); } )
			.then( function () { btn.disabled = false; } );
	}

	function importSelected() {
		var status = $( 'ck-scan-import-status' );
		var picks = [];
		Array.prototype.forEach.call( document.querySelectorAll( '.ck-scan-pick' ), function ( cb ) {
			if ( !cb.checked ) { return; }
			var idx = parseInt( cb.getAttribute( 'data-i' ), 10 );
			var row = rowsData[ idx ];
			if ( !row ) { return; }
			var sel = document.querySelector( '.ck-scan-cat[data-i="' + idx + '"]' );
			picks.push( {
				name: row.name,
				service: row.service,
				duration: row.duration || '',
				category: sel ? sel.value : row.category,
				url_policy: row.url_policy || ''
			} );
		} );

		if ( !picks.length ) { setStatus( status, cfg.i18n.noneSelected ); return; }

		setStatus( status, cfg.i18n.importing );
		rest( cfg.importUrl, { cookies: picks } )
			.then( function ( res ) {
				var n = res && typeof res.imported !== 'undefined' ? res.imported : 0;
				setStatus( status, cfg.i18n.imported.replace( '%d', n ) );
			} )
			.catch( function () { setStatus( status, cfg.i18n.error ); } );
	}

	function enrichSuggestions() {
		var status = $( 'ck-scan-enrich-status' );
		if ( !rowsData.length ) { return; }
		setStatus( status, cfg.i18n.enriching );
		rest( cfg.enrichUrl, { suggestions: rowsData } )
			.then( function ( res ) {
				var enriched = res && res.suggestions ? res.suggestions : rowsData;
				var changed = 0;
				enriched.forEach( function ( row, i ) {
					var before = rowsData[ i ] || {};
					if ( row.service !== before.service || row.duration !== before.duration ||
						row.url_policy !== before.url_policy || row.category !== before.category ) {
						changed++;
					}
				} );
				renderRows( enriched );
				setStatus( status, changed ? cfg.i18n.enriched.replace( '%d', changed ) : cfg.i18n.enrichedNone );
			} )
			.catch( function () { setStatus( status, cfg.i18n.error ); } );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var start = $( 'ck-scan-start' );
		if ( !start ) { return; }
		start.addEventListener( 'click', startScan );
		$( 'ck-scan-import' ).addEventListener( 'click', importSelected );
		$( 'ck-scan-enrich' ).addEventListener( 'click', enrichSuggestions );
		$( 'ck-scan-checkall' ).addEventListener( 'change', function ( e ) {
			Array.prototype.forEach.call( document.querySelectorAll( '.ck-scan-pick' ), function ( cb ) {
				cb.checked = e.target.checked;
			} );
		} );
	} );

} )();
