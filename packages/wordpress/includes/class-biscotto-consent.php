<?php
/**
 * Logica consenso: default settings + cookie registry predefinito.
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biscotto_Consent {

	/**
	 * Categorie supportate. "necessary" è sempre attiva e non disattivabile.
	 *
	 * @return array
	 */
	public static function categories() {
		return array( 'necessary', 'analytics', 'marketing', 'preferences' );
	}

	/**
	 * Impostazioni di default usate all'attivazione e come fallback.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			// --- Generale ---
			'title'                => __( 'Utilizziamo i cookie', 'biscotto-cookie-consent' ),
			'body'                 => __( 'Usiamo cookie tecnici e, previo consenso, cookie di analytics e marketing per migliorare il sito e le campagne. Puoi accettare, rifiutare o gestire le preferenze.', 'biscotto-cookie-consent' ),
			'accept_label'         => __( 'Accetta tutto', 'biscotto-cookie-consent' ),
			'reject_label'         => __( 'Rifiuta', 'biscotto-cookie-consent' ),
			'customize_label'      => __( 'Gestisci preferenze', 'biscotto-cookie-consent' ),
			'save_label'           => __( 'Salva preferenze', 'biscotto-cookie-consent' ),
			'close_label'          => __( 'Chiudi', 'biscotto-cookie-consent' ),
			'review_label'         => __( 'Rivedi le tue scelte sui cookie', 'biscotto-cookie-consent' ),
			'prefs_title'          => __( 'Preferenze cookie', 'biscotto-cookie-consent' ),
			'primary_color'        => '#2563eb',
			'primary_text_color'   => '',           // '' = default (#ffffff): testo sui pulsanti
			'bg_color'             => '',           // '' = automatico (chiaro/scuro di sistema)
			'text_color'           => '',           // '' = automatico (chiaro/scuro di sistema)
			'position'             => 'bottom-bar', // bottom-bar | modal | box-right | box-left
			'show_banner'          => 1,            // off per siti con soli cookie tecnici (§13.11)
			'consent_duration'     => 365,          // giorni
			'reprompt_after_days'  => 180,          // Garante: min 6 mesi
			'force_renew_date'     => '',           // YYYY-MM-DD
			'policy_version'       => gmdate( 'Y-m' ),
			'privacy_policy_url'   => '',
			'cookie_policy_url'    => '',

			// --- Integrazioni ---
			'google_consent_mode'  => 1,
			'gtm'                  => 1,
			'gtm_id'               => '',
			'linkedin'             => 0,
			'linkedin_partner_id'  => '',

			// --- Log consensi (server-side, opzionale) ---
			'log_enabled'          => 0,

			// --- Cookie registry ---
			'cookies'              => self::default_registry(),
		);
	}

	/**
	 * Registry iniziale: VUOTO.
	 *
	 * Fino alla v1.0 qui c'era un elenco statico dei cookie più comuni (GA, Ads,
	 * LinkedIn, ecc.) come template di partenza. Dalla v1.1 c'è lo scanner
	 * (tab Scansione): il registro va popolato con ciò che il sito carica
	 * DAVVERO. Pre-caricare cookie generici elencherebbe servizi non presenti —
	 * una cookie policy inesatta è scorretta quanto una incompleta (Garante).
	 *
	 * @return array
	 */
	public static function default_registry() {
		return array();
	}
}
