<?php
/**
 * Plugin Name:       Biscotto – Cookie Consent
 * Plugin URI:        https://github.com/renatoromano-png/Biscotto
 * Description:       GDPR/ePrivacy cookie consent compliant with the Italian DPA (Garante) guidelines: Google Consent Mode v2, GTM and LinkedIn. No page or CPT limits.
 * Version:           1.5.0
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

define( 'BISCOTTO_VERSION', '1.5.0' );
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
require_once BISCOTTO_DIR . 'includes/class-biscotto.php';

/**
 * All'attivazione: pre-popola le impostazioni con i default (cookie registry incluso).
 */
function biscotto_activate() {
	if ( false === get_option( BISCOTTO_OPTION ) ) {
		add_option( BISCOTTO_OPTION, Biscotto_Consent::default_settings() );
	}
	Biscotto_Api::maybe_create_log_table();
}
register_activation_hook( __FILE__, 'biscotto_activate' );

/**
 * Bootstrap.
 */
function biscotto() {
	return Biscotto::instance();
}
add_action( 'plugins_loaded', 'biscotto' );
