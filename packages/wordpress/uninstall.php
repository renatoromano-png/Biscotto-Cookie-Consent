<?php
/**
 * Disinstallazione: rimuove opzioni e tabella di log.
 *
 * @package Biscotto
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'biscotto_settings' );
delete_option( 'biscotto_scan_results' );

global $wpdb;
// Il nome tabella deriva solo da $wpdb->prefix + suffisso fisso (nessun input utente);
// gli identificatori SQL non possono essere passati come parametri preparati.
$biscotto_table = $wpdb->prefix . 'biscotto_log';
$wpdb->query( "DROP TABLE IF EXISTS `{$biscotto_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

// Transient del rate limit e del flag sul tetto: senza questa pulizia
// resterebbero in wp_options fino alla scadenza, anche a plugin disinstallato.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_biscotto\_%'
	    OR option_name LIKE '\_transient\_timeout\_biscotto\_%'"
);
