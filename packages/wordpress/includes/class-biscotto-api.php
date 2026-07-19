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
	 * Tetto globale di scritture per finestra: rete di sicurezza contro chi
	 * distribuisce le richieste su molti indirizzi diversi.
	 */
	const RATE_LIMIT_GLOBAL_MAX = 500;

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
			KEY pseudo_id (pseudo_id)
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
				// indirizzo IP, dal tetto globale, dalla deduplica a 24 ore e dalla
				// retention periodica.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Salva un record di consenso pseudonimizzato.
	 *
	 * Ordine dei controlli: log attivo, nonce (CSRF), rate limit, allowlist
	 * delle azioni, deduplica, insert. Il rate limit precede la deduplica
	 * perche' altrimenti la query di deduplica sarebbe eseguibile senza limite.
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

		// Tetto globale: se il limite per IP viene aggirato distribuendo le
		// richieste su molti indirizzi, questo resta come ultima difesa.
		if ( ! $this->within_limit( 'biscotto_rl_global', self::RATE_LIMIT_GLOBAL_MAX, HOUR_IN_SECONDS ) ) {
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

		return new WP_REST_Response( array( 'logged' => true ), 201 );
	}

	/**
	 * Incrementa un contatore a finestra e dice se si e' entro la soglia.
	 *
	 * Concorrenza: leggere-confrontare-scrivere non e' atomico, quindi
	 * richieste simultanee possono leggere lo stesso valore e far avanzare il
	 * contatore di uno invece che di N, superando la soglia. Con un object
	 * cache persistente si usa wp_cache_incr, che e' atomico; senza, il
	 * transient e' l'unica opzione offerta da WordPress e la soglia va intesa
	 * come approssimata per eccesso.
	 *
	 * @param string $key    Chiave del contatore.
	 * @param int    $max    Soglia oltre la quale si rifiuta.
	 * @param int    $window Durata della finestra, in secondi.
	 * @return bool True se la richiesta rientra nella soglia.
	 */
	private function within_limit( $key, $max, $window ) {
		if ( wp_using_ext_object_cache() ) {
			$hits = wp_cache_incr( $key, 1, 'biscotto' );
			if ( false === $hits ) {
				wp_cache_set( $key, 1, 'biscotto', $window );
				$hits = 1;
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
