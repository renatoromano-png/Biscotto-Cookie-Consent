<?php
/**
 * Core: inizializzazione, caricamento dipendenze, registrazione hook.
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Biscotto {

	/** @var Biscotto|null */
	private static $instance = null;

	/** @var Biscotto_Frontend */
	public $frontend;

	/** @var Biscotto_Admin */
	public $admin;

	/** @var Biscotto_Api */
	public $api;

	/** @var Biscotto_Scanner */
	public $scanner;

	/** @var Biscotto_Shortcodes */
	public $shortcodes;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->frontend = new Biscotto_Frontend();
		$this->api      = new Biscotto_Api();
		// Scanner: serve sia sul frontend (collector in scan-mode) sia per le
		// rotte REST e il tab admin (roadmap §14).
		$this->scanner  = new Biscotto_Scanner();
		// Shortcode per la pagina cookie policy (elenco cookie + stato consenso).
		$this->shortcodes = new Biscotto_Shortcodes();

		if ( is_admin() ) {
			$this->admin = new Biscotto_Admin();
			// Pulsante "Crea pagina Cookie Policy" nel tab Cookie.
			new Biscotto_Policy_Page();
		}
	}

	/**
	 * Helper: legge le impostazioni con fallback ai default.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved    = get_option( BISCOTTO_OPTION, array() );
		$defaults = Biscotto_Consent::default_settings();
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}
}
