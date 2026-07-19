<?php
/**
 * REST API: log opzionale dei consensi (prova lato titolare, §13.9).
 * Memorizza dati pseudonimizzati — nessun dato identificativo diretto.
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biscotto_Api {

	const TABLE = 'biscotto_log';

	/** Scritture massime consentite a uno stesso indirizzo IP in un'ora. */
	const RATE_LIMIT_MAX = 10;

	/**
	 * Righe massime scrivibili nella finestra, a livello di sito.
	 *
	 * Non e' un contatore di richieste: si misura direttamente quante righe
	 * esistono gia' nella tabella. Contare le righe invece delle richieste
	 * evita che richieste rifiutate consumino la quota, non dipende da un
	 * object cache persistente e non e' aggirabile con la concorrenza, perche'
	 * la quantita' misurata e' proprio quella che l'abuso fa crescere.
	 *
	 * Regolabile con il filtro `biscotto_write_ceiling`.
	 */
	const WRITE_CEILING_MAX = 2000;

	/** Finestra del tetto sulle scritture, in secondi. */
	const WRITE_CEILING_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Finestra di deduplica, in secondi.
	 *
	 * Attenzione: la finestra effettiva e' piu' corta di cosi'. Il pseudo_id
	 * include un salt che ruota a mezzanotte UTC, quindi due richieste a
	 * cavallo della mezzanotte producono pseudo_id diversi e non vengono mai
	 * riconosciute come duplicate. La finestra reale va da 0 a 24 ore a
	 * seconda dell'ora in cui arriva la prima richiesta. E' accettabile: la
	 * deduplica riduce il volume, non e' un controllo di sicurezza — quello e'
	 * il rate limit.
	 */
	const DEDUPE_WINDOW = DAY_IN_SECONDS;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Crea la tabella di log (chiamata all'attivazione).
	 */
	public static function maybe_create_log_table() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			pseudo_id CHAR(64) NOT NULL,
			policy_version VARCHAR(32) NOT NULL,
			action VARCHAR(20) NOT NULL,
			categories TEXT NOT NULL,
			PRIMARY KEY (id),
			KEY pseudo_id (pseudo_id),
			KEY created_at (created_at)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function register_routes() {
		$settings = Biscotto::get_settings();

		// Il log dei consensi e' opzionale e spento di default: se e' spento la
		// rotta non viene registrata affatto e una richiesta riceve 404.
		if ( empty( $settings['log_enabled'] ) ) {
			return;
		}

		register_rest_route(
			'biscotto/v1',
			'/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_consent' ),
				// Endpoint pubblico per necessita': lo chiamano visitatori anonimi
				// via navigator.sendBeacon per registrare il proprio consenso, quindi
				// non esiste un utente da autorizzare e permission_callback e'
				// __return_true. Il nonce nel body copre il CSRF, non l'abuso: il
				// limite reale alla scrittura su database e' dato dal rate limit per
				// indirizzo IP, dal tetto sulle scritture, dalla deduplica a 24 ore e
				// dalla retention periodica.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Salva un record di consenso pseudonimizzato.
	 *
	 * Ordine dei controlli: log attivo, nonce (CSRF), derivazione dello
	 * pseudo_id, rate limit per IP, allowlist delle azioni, deduplica, tetto
	 * sulle scritture, insert. Il rate limit per IP precede la deduplica
	 * perche' altrimenti la query di deduplica sarebbe eseguibile senza
	 * limite. Il tetto sulle scritture e' l'ultimo controllo perche' misura
	 * righe, non richieste: va calcolato subito prima di scrivere davvero.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 * @return WP_REST_Response
	 */
	public function log_consent( $request ) {
		$settings = Biscotto::get_settings();
		if ( empty( $settings['log_enabled'] ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$params = $request->get_json_params();

		// Nonce nel body: sendBeacon non puo' impostare header custom. Protegge
		// dal CSRF; non e' una barriera di autorizzazione, perche' e' ottenibile
		// da qualunque visitatore anonimo.
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_nonce' ), 403 );
		}

		// Pseudo-ID: hash non reversibile di IP + user agent + salt giornaliero.
		// Permette de-duplica e rate limit senza memorizzare dati identificativi.
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$salt   = wp_salt( 'auth' ) . gmdate( 'Y-m-d' );
		$pseudo = hash( 'sha256', $ip . '|' . $ua . '|' . $salt );

		// Rate limit per indirizzo IP. La chiave NON deve contenere lo user
		// agent ne' altri header: sono scelti dal chiamante, che potrebbe
		// variarli a ogni richiesta per ripartire sempre da un contatore
		// vuoto. Falsificare REMOTE_ADDR richiede invece di completare un
		// handshake TCP. L'IP viene comunque passato per hash col salt
		// giornaliero: non serve conservarlo in chiaro.
		$rl_key = 'biscotto_rl_' . hash( 'sha256', $ip . '|' . $salt );
		if ( ! $this->within_limit( $rl_key, self::RATE_LIMIT_MAX, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'rate_limited' ), 429 );
		}

		$action     = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';
		$policy     = isset( $params['policyVersion'] ) ? sanitize_text_field( $params['policyVersion'] ) : '';
		$categories = isset( $params['categories'] ) && is_array( $params['categories'] ) ? $params['categories'] : array();

		$allowed = array( 'granted_all', 'rejected_all', 'custom', 'default_kept' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_action' ), 400 );
		}

		global $wpdb;
		$table = self::table_name();

		// Deduplica: stessa scelta, stessa versione di policy, stesso visitatore
		// entro la finestra -> nessuna riga nuova.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_WINDOW );
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE pseudo_id = %s AND policy_version = %s AND `action` = %s AND created_at > %s LIMIT 1",
				$pseudo,
				$policy,
				$action,
				$cutoff
			)
		);

		if ( '' !== $wpdb->last_error ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		if ( $exists ) {
			return new WP_REST_Response( array( 'logged' => false, 'reason' => 'duplicate' ), 200 );
		}

		// Tetto sulle scritture: si contano le righe gia' presenti nella
		// finestra, non le richieste ricevute. Sta qui, dopo la deduplica,
		// perche' misura cio' che stiamo per aggiungere davvero.
		// max(1) perche' un filtro che restituisse 0 o un negativo spegnerebbe
		// il log per sempre, con l'avviso in bacheca come unico sintomo.
		$ceiling = max( 1, (int) apply_filters( 'biscotto_write_ceiling', self::WRITE_CEILING_MAX ) );
		$written = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at > %s",
				gmdate( 'Y-m-d H:i:s', time() - self::WRITE_CEILING_WINDOW )
			)
		);

		if ( '' !== $wpdb->last_error ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		if ( $written >= $ceiling ) {
			// Codice distinto dal 429 per IP: qui il log si e' fermato per
			// tutto il sito, non per un singolo visitatore. Il flag permette
			// al pannello di segnalarlo, altrimenti la mancanza di righe
			// resterebbe l'unico sintomo.
			//
			// Si scrive solo se non c'e' gia': senza questa guardia, ogni
			// richiesta rifiutata produrrebbe una UPDATE su wp_options, cioe'
			// di nuovo una scrittura anonima per richiesta — esattamente
			// l'obiezione che questo codice deve chiudere.
			if ( false === get_transient( 'biscotto_write_ceiling_hit' ) ) {
				set_transient( 'biscotto_write_ceiling_hit', time(), WEEK_IN_SECONDS );
			}

			return new WP_REST_Response( array( 'logged' => false, 'error' => 'write_ceiling' ), 429 );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'created_at'     => current_time( 'mysql', true ),
				'pseudo_id'      => $pseudo,
				'policy_version' => $policy,
				'action'         => $action,
				'categories'     => wp_json_encode( $categories ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'db_error' ), 500 );
		}

		// Il tetto si sblocca da solo quando le righe escono dalla finestra:
		// se siamo riusciti a scrivere, la condizione e' rientrata e l'avviso
		// in bacheca non deve restare li' a suggerire di alzare il limite.
		if ( false !== get_transient( 'biscotto_write_ceiling_hit' ) ) {
			delete_transient( 'biscotto_write_ceiling_hit' );
		}

		return new WP_REST_Response( array( 'logged' => true ), 201 );
	}

	/**
	 * Incrementa un contatore a finestra e dice se si e' entro la soglia.
	 *
	 * E' la prima linea, non l'ultima: serve a fermare a basso costo il
	 * martellamento da un singolo indirizzo. Il limite vero alle scritture e'
	 * il tetto sulle righe, che non dipende da questo contatore.
	 *
	 * Concorrenza: senza un object cache persistente WordPress non offre un
	 * contatore atomico, quindi richieste simultanee possono far avanzare il
	 * valore di uno invece che di N. Il limite va quindi inteso come
	 * approssimato per eccesso, ed e' accettabile proprio perche' non e'
	 * l'unica difesa.
	 *
	 * @param string $key    Chiave del contatore.
	 * @param int    $max    Soglia oltre la quale si rifiuta.
	 * @param int    $window Durata della finestra, in secondi.
	 * @return bool True se la richiesta rientra nella soglia.
	 */
	private function within_limit( $key, $max, $window ) {
		if ( wp_using_ext_object_cache() ) {
			// wp_cache_add imposta la scadenza e non fa nulla se la chiave
			// esiste gia'. Serve perche' wp_cache_incr da solo, sui drop-in
			// Redis, crea la chiave SENZA scadenza: il contatore non si
			// azzererebbe mai e il log resterebbe bloccato per sempre.
			wp_cache_add( $key, 0, 'biscotto', $window );
			$hits = wp_cache_incr( $key, 1, 'biscotto' );

			// Se l'object cache non collabora si lascia passare: il tetto
			// sulle righe resta comunque a fare da limite.
			if ( false === $hits ) {
				return true;
			}

			return $hits <= $max;
		}

		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );

		return true;
	}
}
