<?php
/**
 * Plugin Name:       Biscotto – Cookie Consent & Consent Mode
 * Plugin URI:        https://github.com/renatoromano-png/Biscotto
 * Description:       GDPR/ePrivacy cookie consent compliant with the Italian DPA (Garante) guidelines: Google Consent Mode v2, GTM and LinkedIn. No page or CPT limits.
 * Version:           1.5.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Food & Tech
 * Author URI:        https://github.com/renatoromano-png
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       biscotto
 * Domain Path:       /languages
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accesso diretto vietato.
}

define( 'CONSENTKIT_VERSION', '1.5.0' );
define( 'CONSENTKIT_FILE', __FILE__ );
define( 'CONSENTKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONSENTKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'CONSENTKIT_OPTION', 'consentkit_settings' );

require_once CONSENTKIT_DIR . 'includes/class-consentkit-consent.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-frontend.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-admin.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-api.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-cookie-database.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-scanner.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-shortcodes.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit-policy-page.php';
require_once CONSENTKIT_DIR . 'includes/class-consentkit.php';

/**
 * All'attivazione: pre-popola le impostazioni con i default (cookie registry incluso).
 */
function consentkit_activate() {
	if ( false === get_option( CONSENTKIT_OPTION ) ) {
		add_option( CONSENTKIT_OPTION, ConsentKit_Consent::default_settings() );
	}
	ConsentKit_Api::maybe_create_log_table();
}
register_activation_hook( __FILE__, 'consentkit_activate' );

/**
 * Bootstrap.
 */
function consentkit() {
	return ConsentKit::instance();
}
add_action( 'plugins_loaded', 'consentkit' );
