<?php
/**
 * Cookie scanner (roadmap §14, v1.1).
 *
 * Modello a due lati:
 *  - SCAN (cosa è caricato): l'admin carica gli URL bersaglio in un <iframe>
 *    nascosto con un token monouso; dentro l'iframe il JS della pagina gira
 *    davvero, il consenso viene forzato ad "accettato" (SOLO in contesto admin
 *    con token) e il collector legge cookie + storage + risorse + iframe.
 *  - CLASSIFY (cos'è): mappa interna dominio→servizio/categoria e
 *    cookie→categoria. Nessun dato di cookiedatabase.org impacchettato (§9):
 *    quell'arricchimento resta un hook futuro, solo lato-utente.
 *
 * Lo scan RILEVA; non blocca (il blocco di iframe/font è v1.2, §14.5).
 *
 * @package ConsentKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConsentKit_Scanner {

	/** Option in cui salviamo l'ultimo risultato di scan (per la revisione admin). */
	const RESULTS_OPTION = 'consentkit_scan_results';

	/** Azione del nonce monouso che abilita lo scan-mode sul frontend. */
	const SCAN_NONCE = 'consentkit_scan';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		// Sul frontend, in scan-mode, inietta pre-grant + collector DOPO il manager.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_collector' ), 99 );
	}

	// -------------------------------------------------------------------------
	// Scan-mode (frontend dentro l'iframe)
	// -------------------------------------------------------------------------

	/**
	 * Lo scan-mode è attivo solo se: parametro ck_scan presente, nonce valido,
	 * e l'utente corrente può gestire le opzioni. Così il consenso forzato non
	 * tocca MAI i visitatori (nota anti-falsi-positivi, §14.3).
	 *
	 * @return bool
	 */
	public function is_scan_mode() {
		if ( ! isset( $_GET['ck_scan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['ck_scan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (bool) wp_verify_nonce( $nonce, self::SCAN_NONCE );
	}

	/**
	 * In scan-mode: forza il consenso ad "accettato" (prima del manager, via
	 * localStorage) così tutti i tag partono, poi carica il collector che
	 * raccoglie e fa postMessage al parent, ripulendo il pre-grant alla fine.
	 */
	public function maybe_enqueue_collector() {
		if ( ! $this->is_scan_mode() ) {
			return;
		}

		// Pre-grant: scritto PRIMA del manager (handle consentkit-manager, già
		// accodato dal frontend) così init() lo legge come consenso valido.
		$pre_grant = "(function(){try{window.__ckScanMode=true;var pv=(window.ckConfig&&window.ckConfig.policyVersion)||'1';"
			. "localStorage.setItem('ck_consent',JSON.stringify({version:'scan',policyVersion:String(pv),"
			. "timestamp:Math.floor(Date.now()/1000),action:'granted_all',"
			. "categories:{necessary:true,analytics:true,marketing:true,preferences:true}}));}catch(e){}})();";
		wp_add_inline_script( 'consentkit-manager', $pre_grant, 'before' );

		wp_enqueue_script(
			'consentkit-scan-collector',
			CONSENTKIT_URL . 'public/js/scan-collector.js',
			array( 'consentkit-manager' ),
			CONSENTKIT_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// REST
	// -------------------------------------------------------------------------

	public function register_routes() {
		$can_manage = array( $this, 'permission_check' );

		register_rest_route(
			'consentkit/v1',
			'/scan/collect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'collect' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/server',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'scan_server' ),
				'permission_callback' => $can_manage,
			)
		);

		register_rest_route(
			'consentkit/v1',
			'/scan/enrich',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enrich' ),
				'permission_callback' => $can_manage,
			)
		);
	}

	/**
	 * Scan veloce lato server: per ogni URL (stesso sito, max 10) scarica l'HTML
	 * con wp_remote_get e ne estrae i domini di terze parti. Niente rendering,
	 * quindi è rapido; l'iframe runtime (lato client) cattura ciò che parte solo
	 * via JS. I due risultati vengono uniti nel browser.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function scan_server( $request ) {
		$params = $request->get_json_params();
		$urls   = isset( $params['urls'] ) && is_array( $params['urls'] ) ? $params['urls'] : array();

		$site      = wp_parse_url( home_url() );
		$site_host = isset( $site['host'] ) ? strtolower( $site['host'] ) : '';

		$findings = array();
		$count    = 0;
		foreach ( $urls as $raw ) {
			if ( $count >= 10 ) {
				break;
			}
			$url = esc_url_raw( (string) $raw );
			if ( '' === $url ) {
				continue;
			}
			$u    = wp_parse_url( $url );
			$host = isset( $u['host'] ) ? strtolower( $u['host'] ) : '';
			// Sicurezza: solo URL dello stesso sito (no SSRF verso domini terzi).
			if ( '' === $host || ! $this->is_first_party( $host, $site_host ) ) {
				continue;
			}
			$count++;

			$resp = wp_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 3,
					'user-agent'  => 'ConsentKit-Scanner/' . CONSENTKIT_VERSION,
				)
			);
			if ( is_wp_error( $resp ) ) {
				continue;
			}
			$body = wp_remote_retrieve_body( $resp );
			if ( '' === $body ) {
				continue;
			}

			$findings[] = array(
				'url'     => $url,
				'hosts'   => $this->extract_hosts( $body ),
				'cookies' => array(),
			);
		}

		$suggestions = $this->classify_findings( $findings );

		return new WP_REST_Response( array( 'suggestions' => $suggestions ), 200 );
	}

	/**
	 * Estrae gli host dalle risorse caricate nell'HTML (src/href di
	 * script/link/img/iframe) più alcuni domini noti spesso presenti negli
	 * snippet inline (GTM/GA/Pixel/Insight). Il classificatore scarta poi gli
	 * host sconosciuti e quelli di prima parte.
	 *
	 * @param string $body HTML della pagina.
	 * @return array Lista host (lowercase).
	 */
	private function extract_hosts( $body ) {
		$hosts = array();

		if ( preg_match_all( '/<(?:script|link|img|iframe)\b[^>]*?\b(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', $body, $m ) ) {
			foreach ( $m[1] as $res_url ) {
				$p = wp_parse_url( html_entity_decode( $res_url ) );
				if ( isset( $p['host'] ) ) {
					$hosts[ strtolower( $p['host'] ) ] = true;
				}
			}
		}

		// Domini comuni iniettati via snippet inline (non come attributo src).
		$inline = array(
			'www.googletagmanager.com',
			'www.google-analytics.com',
			'connect.facebook.net',
			'snap.licdn.com',
			'px.ads.linkedin.com',
			'www.clarity.ms',
			'static.hotjar.com',
			'maps.googleapis.com',
			'analytics.tiktok.com',
		);
		foreach ( $inline as $domain ) {
			if ( false !== stripos( $body, $domain ) ) {
				$hosts[ $domain ] = true;
			}
		}

		return array_keys( $hosts );
	}

	/**
	 * Solo amministratori, con nonce REST valido (header X-WP-Nonce).
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return bool
	 */
	public function permission_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Riceve i findings grezzi dal collector, li classifica e li salva.
	 *
	 * Body atteso: { findings: [ {url, cookies:[name], storage:[key], hosts:[host], iframes:[src] } ] }
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function collect( $request ) {
		$params   = $request->get_json_params();
		$findings = isset( $params['findings'] ) && is_array( $params['findings'] ) ? $params['findings'] : array();

		$suggestions = $this->classify_findings( $findings );

		update_option(
			self::RESULTS_OPTION,
			array(
				'scanned_at'  => current_time( 'mysql', true ),
				'suggestions' => $suggestions,
			),
			false
		);

		return new WP_REST_Response(
			array(
				'suggestions' => $suggestions,
				'scanned_at'  => current_time( 'mysql', true ),
			),
			200
		);
	}

	/**
	 * Unisce nel cookie registry le righe selezionate dall'admin.
	 *
	 * Body atteso: { cookies: [ {name, service, duration, category, url_policy} ] }
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function import( $request ) {
		$params   = $request->get_json_params();
		$incoming = isset( $params['cookies'] ) && is_array( $params['cookies'] ) ? $params['cookies'] : array();

		$settings = ConsentKit::get_settings();
		$registry = isset( $settings['cookies'] ) && is_array( $settings['cookies'] ) ? $settings['cookies'] : array();

		// Indicizza il registry esistente per nome (case-insensitive) per evitare duplicati.
		$existing = array();
		foreach ( $registry as $row ) {
			if ( isset( $row['name'] ) && '' !== $row['name'] ) {
				$existing[ strtolower( $row['name'] ) ] = true;
			}
		}

		$cats  = ConsentKit_Consent::categories();
		$added = 0;
		foreach ( $incoming as $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name || isset( $existing[ strtolower( $name ) ] ) ) {
				continue;
			}
			$cat = isset( $row['category'] ) && in_array( $row['category'], $cats, true ) ? $row['category'] : 'necessary';
			$registry[] = array(
				'name'       => $name,
				'service'    => isset( $row['service'] ) ? sanitize_text_field( $row['service'] ) : '',
				'duration'   => isset( $row['duration'] ) ? sanitize_text_field( $row['duration'] ) : '',
				'category'   => $cat,
				'url_policy' => isset( $row['url_policy'] ) ? esc_url_raw( $row['url_policy'] ) : '',
			);
			$existing[ strtolower( $name ) ] = true;
			$added++;
		}

		$settings['cookies'] = $registry;
		update_option( CONSENTKIT_OPTION, $settings );

		return new WP_REST_Response(
			array(
				'imported' => $added,
				'total'    => count( $registry ),
			),
			200
		);
	}

	/**
	 * Completa i campi vuoti dei suggerimenti (non ancora importati) usando
	 * il database cookie bundlato (Open Cookie Database). Non sovrascrive
	 * mai un campo già valorizzato — vedi enrich_row().
	 *
	 * Body atteso: { suggestions: [ {name, service, category, duration, url_policy, source}, ... ] }
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function enrich( $request ) {
		$params = $request->get_json_params();
		$rows   = isset( $params['suggestions'] ) && is_array( $params['suggestions'] ) ? $params['suggestions'] : array();

		$csv_path = CONSENTKIT_DIR . 'includes/data/open-cookie-database.csv';
		$index    = ConsentKit_Cookie_Database::build_index( $csv_path );

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->enrich_row( is_array( $row ) ? $row : array(), $index );
		}

		return new WP_REST_Response( array( 'suggestions' => $out ), 200 );
	}

	/**
	 * Arricchisce una riga: se il classificatore interno non aveva
	 * riconosciuto il cookie/dominio (service vuoto), il match del database
	 * riempie servizio+categoria+durata+link insieme; se il servizio era
	 * già stato riconosciuto, il database riempie solo durata/link se ancora
	 * vuoti, senza mai toccare servizio/categoria già decisi.
	 *
	 * @param array $row   Riga suggerimento { name, service, category, duration, url_policy, source }.
	 * @param array $index Indice da ConsentKit_Cookie_Database::build_index().
	 * @return array Riga (eventualmente) arricchita.
	 */
	private function enrich_row( $row, $index ) {
		$name       = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		$source     = isset( $row['source'] ) && 'domain' === $row['source'] ? 'domain' : 'cookie';
		$service    = isset( $row['service'] ) ? (string) $row['service'] : '';
		$category   = isset( $row['category'] ) ? (string) $row['category'] : '';
		$duration   = isset( $row['duration'] ) ? (string) $row['duration'] : '';
		$url_policy = isset( $row['url_policy'] ) ? (string) $row['url_policy'] : '';

		if ( '' === $name ) {
			return $row;
		}

		$match = 'domain' === $source
			? ConsentKit_Cookie_Database::lookup_domain( $name, $index )
			: ConsentKit_Cookie_Database::lookup_cookie( $name, $index );

		if ( null === $match ) {
			return $row;
		}

		// Solo campo vuoto: 'service' vuoto è l'unico caso in cui il
		// classificatore interno non aveva riconosciuto la riga, quindi solo
		// lì il database può anche decidere servizio+categoria.
		if ( '' === $service ) {
			$service  = $match['service'];
			$category = $match['category'];
		}
		if ( '' === $duration ) {
			$duration = $match['duration'];
		}
		if ( '' === $url_policy ) {
			$url_policy = $match['url_policy'];
		}

		return array(
			'name'       => $name,
			'service'    => $service,
			'category'   => $category,
			'duration'   => $duration,
			'url_policy' => $url_policy,
			'source'     => $source,
		);
	}

	// -------------------------------------------------------------------------
	// Classificatore (mappe interne, scritte da zero — §14.6)
	// -------------------------------------------------------------------------

	/**
	 * Trasforma i findings grezzi in righe di registry suggerite, deduplicate.
	 *
	 * @param array $findings Findings per URL dal collector.
	 * @return array Lista di suggerimenti { name, service, duration, category, url_policy, source }.
	 */
	private function classify_findings( $findings ) {
		$by_name = array();
		$site    = wp_parse_url( home_url() );
		$site_host = isset( $site['host'] ) ? strtolower( $site['host'] ) : '';

		foreach ( $findings as $finding ) {
			// 1) Cookie effettivamente impostati → classificati per nome.
			$cookies = isset( $finding['cookies'] ) && is_array( $finding['cookies'] ) ? $finding['cookies'] : array();
			foreach ( $cookies as $cookie_name ) {
				$cookie_name = sanitize_text_field( (string) $cookie_name );
				if ( '' === $cookie_name ) {
					continue;
				}
				// I cookie esistono solo perché lo scan gira in sessione admin
				// loggata: sono rumore per una cookie policy pubblica → esclusi.
				if ( $this->is_session_only_cookie( $cookie_name ) ) {
					continue;
				}
				$info = $this->classify_cookie( $cookie_name );
				$this->merge_suggestion(
					$by_name,
					array(
						'name'       => $cookie_name,
						'service'    => $info['service'],
						'duration'   => '',
						'category'   => $info['category'],
						'url_policy' => $info['url_policy'],
						'source'     => 'cookie',
					)
				);
			}

			// 2) Domini di terze parti (anche senza cookie: es. Google Fonts).
			$hosts = isset( $finding['hosts'] ) && is_array( $finding['hosts'] ) ? $finding['hosts'] : array();
			foreach ( $hosts as $host ) {
				$host = strtolower( sanitize_text_field( (string) $host ) );
				if ( '' === $host || $this->is_first_party( $host, $site_host ) ) {
					continue;
				}
				$info = $this->classify_host( $host );
				if ( null === $info ) {
					continue; // dominio sconosciuto: lo ignoriamo per non sporcare i suggerimenti
				}
				$this->merge_suggestion(
					$by_name,
					array(
						'name'       => $host,
						'service'    => $info['service'],
						'duration'   => '',
						'category'   => $info['category'],
						'url_policy' => $info['url_policy'],
						'source'     => 'domain',
					)
				);
			}
		}

		return array_values( $by_name );
	}

	/**
	 * Inserisce/aggiorna un suggerimento usando il nome come chiave (dedup).
	 *
	 * @param array $bag Accumulatore (per riferimento).
	 * @param array $row Riga suggerita.
	 */
	private function merge_suggestion( &$bag, $row ) {
		$key = strtolower( $row['name'] );
		if ( ! isset( $bag[ $key ] ) ) {
			$bag[ $key ] = $row;
		}
	}

	/**
	 * È un host di prima parte (stesso dominio del sito o sottodominio)?
	 *
	 * @param string $host      Host trovato.
	 * @param string $site_host Host del sito.
	 * @return bool
	 */
	private function is_first_party( $host, $site_host ) {
		if ( '' === $site_host ) {
			return false;
		}
		$site_host = preg_replace( '/^www\./', '', $site_host );
		$host      = preg_replace( '/^www\./', '', $host );
		if ( $host === $site_host ) {
			return true;
		}
		// sottodominio del sito (es. cdn.miosito.it)
		return (bool) preg_match( '/(^|\.)' . preg_quote( $site_host, '/' ) . '$/', $host );
	}

	/**
	 * Mappa dominio → servizio/categoria/policy. Null se sconosciuto.
	 *
	 * @param string $host Host (lowercase).
	 * @return array|null
	 */
	private function classify_host( $host ) {
		$map = array(
			// Google Fonts: nessun cookie ma espone l'IP → preferenze.
			'fonts.googleapis.com'        => array( 'Google Fonts', 'preferences', 'https://policies.google.com/privacy' ),
			'fonts.gstatic.com'           => array( 'Google Fonts', 'preferences', 'https://policies.google.com/privacy' ),
			// Google Maps.
			'maps.googleapis.com'         => array( 'Google Maps', 'marketing', 'https://policies.google.com/privacy' ),
			'maps.google.com'             => array( 'Google Maps', 'marketing', 'https://policies.google.com/privacy' ),
			// Google Analytics / Tag Manager.
			'www.google-analytics.com'    => array( 'Google Analytics', 'analytics', 'https://policies.google.com/privacy' ),
			'google-analytics.com'        => array( 'Google Analytics', 'analytics', 'https://policies.google.com/privacy' ),
			'analytics.google.com'        => array( 'Google Analytics', 'analytics', 'https://policies.google.com/privacy' ),
			'www.googletagmanager.com'    => array( 'Google Tag Manager', 'analytics', 'https://policies.google.com/privacy' ),
			'googletagmanager.com'        => array( 'Google Tag Manager', 'analytics', 'https://policies.google.com/privacy' ),
			// Google Ads / DoubleClick.
			'googleadservices.com'        => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			'www.googleadservices.com'    => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			'googlesyndication.com'       => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			'pagead2.googlesyndication.com' => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			'doubleclick.net'             => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			'stats.g.doubleclick.net'     => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			// YouTube.
			'www.youtube.com'             => array( 'YouTube', 'marketing', 'https://policies.google.com/privacy' ),
			'youtube.com'                 => array( 'YouTube', 'marketing', 'https://policies.google.com/privacy' ),
			'www.youtube-nocookie.com'    => array( 'YouTube (no-cookie)', 'marketing', 'https://policies.google.com/privacy' ),
			'youtube-nocookie.com'        => array( 'YouTube (no-cookie)', 'marketing', 'https://policies.google.com/privacy' ),
			'i.ytimg.com'                 => array( 'YouTube', 'marketing', 'https://policies.google.com/privacy' ),
			// reCAPTCHA (tecnico).
			'www.gstatic.com'             => array( 'Google (gstatic)', 'necessary', 'https://policies.google.com/privacy' ),
			'www.recaptcha.net'           => array( 'Google reCAPTCHA', 'necessary', 'https://policies.google.com/privacy' ),
			// Gravatar.
			'secure.gravatar.com'         => array( 'Gravatar', 'preferences', 'https://automattic.com/privacy/' ),
			'gravatar.com'                => array( 'Gravatar', 'preferences', 'https://automattic.com/privacy/' ),
			// Meta / Facebook.
			'connect.facebook.net'        => array( 'Meta Pixel', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			'www.facebook.com'            => array( 'Meta', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			'facebook.com'                => array( 'Meta', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			// LinkedIn.
			'snap.licdn.com'              => array( 'LinkedIn Insight', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'px.ads.linkedin.com'         => array( 'LinkedIn Ads', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'www.linkedin.com'            => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			// Hotjar.
			'static.hotjar.com'           => array( 'Hotjar', 'analytics', 'https://www.hotjar.com/legal/policies/privacy/' ),
			'script.hotjar.com'           => array( 'Hotjar', 'analytics', 'https://www.hotjar.com/legal/policies/privacy/' ),
			// Microsoft Clarity.
			'www.clarity.ms'              => array( 'Microsoft Clarity', 'analytics', 'https://privacy.microsoft.com/privacystatement' ),
			'clarity.ms'                  => array( 'Microsoft Clarity', 'analytics', 'https://privacy.microsoft.com/privacystatement' ),
			// Cloudflare insights.
			'static.cloudflareinsights.com' => array( 'Cloudflare Insights', 'analytics', 'https://www.cloudflare.com/privacypolicy/' ),
			// Vimeo.
			'player.vimeo.com'            => array( 'Vimeo', 'marketing', 'https://vimeo.com/privacy' ),
			'vimeo.com'                   => array( 'Vimeo', 'marketing', 'https://vimeo.com/privacy' ),
			// TikTok.
			'analytics.tiktok.com'        => array( 'TikTok Pixel', 'marketing', 'https://www.tiktok.com/legal/privacy-policy' ),
		);

		if ( isset( $map[ $host ] ) ) {
			return $this->shape( $map[ $host ] );
		}

		// Match per suffisso di dominio (es. qualunque sottodominio di doubleclick.net).
		foreach ( $map as $domain => $vals ) {
			if ( $host === $domain || self::ends_with( $host, '.' . $domain ) ) {
				return $this->shape( $vals );
			}
		}

		return null;
	}

	/**
	 * Mappa nome cookie → servizio/categoria/policy (con match per prefisso).
	 *
	 * @param string $name Nome cookie.
	 * @return array
	 */
	private function classify_cookie( $name ) {
		$rules = array(
			// Google Analytics.
			'_ga'              => array( 'Google Analytics 4', 'analytics', 'https://policies.google.com/privacy' ),
			'_gid'             => array( 'Google Analytics', 'analytics', 'https://policies.google.com/privacy' ),
			'_gat'             => array( 'Google Analytics', 'analytics', 'https://policies.google.com/privacy' ),
			// Google Ads.
			'_gcl'             => array( 'Google Ads', 'marketing', 'https://policies.google.com/privacy' ),
			// Meta.
			'_fbp'             => array( 'Meta Pixel', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			'_fbc'             => array( 'Meta Pixel', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			'fr'               => array( 'Meta', 'marketing', 'https://www.facebook.com/privacy/policy/' ),
			// LinkedIn.
			'li_gc'            => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'bcookie'          => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'bscookie'         => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'lidc'             => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'usermatchhistory' => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			'an_ua'            => array( 'LinkedIn', 'marketing', 'https://www.linkedin.com/legal/privacy-policy' ),
			// Hotjar.
			'_hj'              => array( 'Hotjar', 'analytics', 'https://www.hotjar.com/legal/policies/privacy/' ),
			// Microsoft Clarity.
			'_clck'            => array( 'Microsoft Clarity', 'analytics', 'https://privacy.microsoft.com/privacystatement' ),
			'_clsk'            => array( 'Microsoft Clarity', 'analytics', 'https://privacy.microsoft.com/privacystatement' ),
			// TikTok.
			'_ttp'             => array( 'TikTok Pixel', 'marketing', 'https://www.tiktok.com/legal/privacy-policy' ),
			// YouTube / DoubleClick.
			'ide'              => array( 'Google DoubleClick', 'marketing', 'https://policies.google.com/privacy' ),
			'test_cookie'      => array( 'Google DoubleClick', 'marketing', 'https://policies.google.com/privacy' ),
			'visitor_info1_live' => array( 'YouTube', 'marketing', 'https://policies.google.com/privacy' ),
			'ysc'              => array( 'YouTube', 'marketing', 'https://policies.google.com/privacy' ),
			// WordPress / tecnici.
			'wordpress_'       => array( 'WordPress', 'necessary', '' ),
			'wp-settings'      => array( 'WordPress', 'necessary', '' ),
			'wp_lang'          => array( 'WordPress', 'necessary', '' ),
			'comment_author'   => array( 'WordPress', 'necessary', '' ),
			'phpsessid'        => array( 'PHP', 'necessary', '' ),
			'wp-wpml'          => array( 'WPML', 'preferences', '' ),
			'wpml_'            => array( 'WPML', 'preferences', '' ),
			'pll_language'     => array( 'Polylang', 'preferences', '' ),
			'woocommerce_'     => array( 'WooCommerce', 'necessary', '' ),
			'wp_woocommerce_session' => array( 'WooCommerce', 'necessary', '' ),
			'ck_consent'       => array( 'ConsentKit', 'necessary', '' ),
		);

		$lc = strtolower( $name );
		foreach ( $rules as $prefix => $vals ) {
			if ( $lc === $prefix || self::starts_with( $lc, $prefix ) ) {
				return $this->shape( $vals );
			}
		}

		// Sconosciuto: per prudenza lo proponiamo come "necessary" da rivedere.
		return array(
			'service'    => '',
			'category'   => 'necessary',
			'url_policy' => '',
		);
	}

	/**
	 * Normalizza [service, category, url_policy] in array associativo.
	 *
	 * @param array $vals Tripletta.
	 * @return array
	 */
	private function shape( $vals ) {
		return array(
			'service'    => isset( $vals[0] ) ? $vals[0] : '',
			'category'   => isset( $vals[1] ) ? $vals[1] : 'necessary',
			'url_policy' => isset( $vals[2] ) ? $vals[2] : '',
		);
	}

	/**
	 * Cookie presenti solo perché lo scan gira in sessione admin loggata
	 * (login, preferenze backend, lingua admin di WPML): vanno esclusi dai
	 * suggerimenti perché un visitatore normale non li ha.
	 *
	 * @param string $name Nome cookie.
	 * @return bool
	 */
	private function is_session_only_cookie( $name ) {
		$lc       = strtolower( $name );
		$prefixes = array(
			'wordpress_',                     // wordpress_logged_in_, _sec_, _test_cookie
			'wp-settings',                    // wp-settings-1, wp-settings-time-1
			'wp-wpml_current_admin_language', // lingua del backend (solo admin)
			'wp_lang',                        // lingua area admin
		);
		foreach ( $prefixes as $p ) {
			if ( 0 === strpos( $lc, $p ) ) {
				return true;
			}
		}
		return false;
	}

	private static function starts_with( $haystack, $needle ) {
		return 0 === strpos( $haystack, $needle );
	}

	private static function ends_with( $haystack, $needle ) {
		$len = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}

	/**
	 * Nonce monouso per abilitare lo scan-mode sul frontend.
	 *
	 * @return string
	 */
	public static function scan_nonce() {
		return wp_create_nonce( self::SCAN_NONCE );
	}
}
