<?php
/**
 * Plugin Name:       Biscotto – Cookie Consent
 * Plugin URI:        https://github.com/renatoromano-png/Biscotto-Cookie-Consent
 * Description:       GDPR/ePrivacy cookie consent compliant with the Italian DPA (Garante) guidelines: Google Consent Mode v2, GTM and LinkedIn. No page or CPT limits.
 * Version:           1.5.5
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Food & Tech
 * Author URI:        https://github.com/renatoromano-png
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       biscotto-cookie-consent
 * Domain Path:       /languages
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accesso diretto vietato.
}

define( 'BISCOTTO_VERSION', '1.5.5' );
define( 'BISCOTTO_FILE', __FILE__ );
define( 'BISCOTTO_DIR', plugin_dir_path( __FILE__ ) );
define( 'BISCOTTO_URL', plugin_dir_url( __FILE__ ) );
define( 'BISCOTTO_OPTION', 'biscotto_settings' );

require_once BISCOTTO_DIR . 'includes/class-biscotto-consent.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-frontend.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-admin.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-api.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-cookie-database.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-scanner.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-shortcodes.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto-policy-page.php';
require_once BISCOTTO_DIR . 'includes/class-biscotto.php';

/**
 * All'attivazione: pre-popola le impostazioni con i default (cookie registry
 * incluso), crea la tabella di log e schedula la potatura giornaliera.
 */
function biscotto_activate() {
	if ( false === get_option( BISCOTTO_OPTION ) ) {
		add_option( BISCOTTO_OPTION, Biscotto_Consent::default_settings() );
	}
	Biscotto_Api::maybe_create_log_table();

	if ( ! wp_next_scheduled( 'biscotto_prune_log' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'biscotto_prune_log' );
	}
}
register_activation_hook( __FILE__, 'biscotto_activate' );

/**
 * Alla disattivazione: rimuove l'evento cron di potatura del log.
 * I dati restano: la rimozione e' compito di uninstall.php.
 */
function biscotto_deactivate() {
	wp_clear_scheduled_hook( 'biscotto_prune_log' );
}
register_deactivation_hook( __FILE__, 'biscotto_deactivate' );

/**
 * Rete di sicurezza per la potatura del log.
 *
 * Gli hook di attivazione non vengono rieseguiti quando il plugin viene solo
 * aggiornato restando attivo: un sito gia' in funzione non otterrebbe mai
 * l'evento e la retention resterebbe un no-op silenzioso, senza errori ne'
 * avvisi. Il controllo gira solo in area amministrativa e legge l'opzione dei
 * cron, gia' caricata: costo trascurabile.
 */
function biscotto_ensure_prune_scheduled() {
	if ( ! wp_next_scheduled( 'biscotto_prune_log' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'biscotto_prune_log' );
	}
}
add_action( 'admin_init', 'biscotto_ensure_prune_scheduled' );

/**
 * Bootstrap.
 */
function biscotto() {
	return Biscotto::instance();
}
add_action( 'plugins_loaded', 'biscotto' );
