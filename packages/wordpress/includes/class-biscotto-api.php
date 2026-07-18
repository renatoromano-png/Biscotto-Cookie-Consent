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
		register_rest_route(
			'biscotto/v1',
			'/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_consent' ),
				// Endpoint intenzionalmente pubblico: lo chiamano i visitatori non
				// autenticati (via navigator.sendBeacon) per registrare il proprio
				// consenso. La protezione è nel callback stesso: nonce nel body,
				// allowlist delle azioni e flag log_enabled. Per questo il
				// permission_callback è __return_true (pubblico by design).
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Salva un record di consenso pseudonimizzato.
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

		// Nonce nel body (sendBeacon non può inviare header custom): verifica anti-abuso.
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_nonce' ), 403 );
		}

		$action     = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';
		$policy     = isset( $params['policyVersion'] ) ? sanitize_text_field( $params['policyVersion'] ) : '';
		$categories = isset( $params['categories'] ) && is_array( $params['categories'] ) ? $params['categories'] : array();

		$allowed = array( 'granted_all', 'rejected_all', 'custom', 'default_kept' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_REST_Response( array( 'logged' => false, 'error' => 'invalid_action' ), 400 );
		}

		// Pseudo-ID: hash non reversibile di IP + user agent + salt giornaliero.
		// Permette de-duplica relativa senza memorizzare dati identificativi.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$salt  = wp_salt( 'auth' ) . gmdate( 'Y-m-d' );
		$pseudo = hash( 'sha256', $ip . '|' . $ua . '|' . $salt );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			array(
				'created_at'     => current_time( 'mysql', true ),
				'pseudo_id'      => $pseudo,
				'policy_version' => $policy,
				'action'         => $action,
				'categories'     => wp_json_encode( $categories ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return new WP_REST_Response( array( 'logged' => true ), 201 );
	}
}
