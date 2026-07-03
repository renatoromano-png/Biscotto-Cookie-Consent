<?php
/**
 * Frontend: Consent Mode default nel <head>, enqueue core JS/CSS, ckConfig.
 *
 * @package ConsentKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConsentKit_Frontend {

	public function __construct() {
		// Consent Mode v2 default + GTM: PRIMA di tutto nel <head> (§13.7).
		add_action( 'wp_head', array( $this, 'print_head_snippets' ), 1 );
		// Core JS/CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Stampa, in cima al <head>, il Consent Mode v2 default (denied) e — se
	 * configurato — lo snippet GTM subito dopo. L'ordine è vincolante.
	 */
	public function print_head_snippets() {
		$s = ConsentKit::get_settings();

		if ( empty( $s['google_consent_mode'] ) && empty( $s['gtm'] ) ) {
			return;
		}

		if ( ! empty( $s['google_consent_mode'] ) ) {
			echo "<!-- ConsentKit: Google Consent Mode v2 default -->\n";
			echo "<script>\n";
			echo "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n";
			echo "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',personalization_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});\n";
			echo "</script>\n";
		}

		$gtm_id = isset( $s['gtm_id'] ) ? trim( $s['gtm_id'] ) : '';
		if ( ! empty( $s['gtm'] ) && '' !== $gtm_id ) {
			$gtm_id = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $gtm_id ) );
			echo "<!-- ConsentKit: Google Tag Manager -->\n";
			echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js( $gtm_id ) . "');</script>\n";
		}
	}

	/**
	 * Enqueue del core (copiato da packages/core in fase di build) + config.
	 */
	public function enqueue_assets() {
		$s = ConsentKit::get_settings();

		wp_enqueue_style(
			'consentkit-banner',
			CONSENTKIT_URL . 'public/css/banner.css',
			array(),
			CONSENTKIT_VERSION
		);

		// Colori personalizzati via variabili CSS inline (vuoto = default/automatico).
		$primary          = sanitize_hex_color( $s['primary_color'] );
		$primary_contrast = sanitize_hex_color( $s['primary_text_color'] );
		$bg               = sanitize_hex_color( $s['bg_color'] );
		$text             = sanitize_hex_color( $s['text_color'] );

		// R6 — auto-contrasto: se il testo non è impostato ma lo sfondo sì,
		// scegli chiaro/scuro in base alla luminanza dello sfondo.
		if ( ! $text && $bg ) {
			$text = self::contrast_text_for( $bg );
		}
		// Testo dei pulsanti: se non impostato, deriva dall'accento (fondo pieno del bottone).
		if ( ! $primary_contrast && $primary ) {
			$primary_contrast = self::contrast_text_for( $primary );
		}

		$ck_vars = array(
			'--ck-primary'          => $primary,
			'--ck-primary-contrast' => $primary_contrast,
			'--ck-bg'               => $bg,
			'--ck-text'             => $text,
		);
		$ck_css = '';
		foreach ( $ck_vars as $ck_var => $ck_value ) {
			if ( $ck_value ) {
				$ck_css .= $ck_var . ':' . $ck_value . ';';
			}
		}
		if ( '' !== $ck_css ) {
			wp_add_inline_style( 'consentkit-banner', ':root{' . $ck_css . '}' );
		}

		wp_enqueue_script(
			'consentkit-manager',
			CONSENTKIT_URL . 'public/js/consent-manager.js',
			array(),
			CONSENTKIT_VERSION,
			true
		);

		// wp_add_inline_script (non wp_localize_script): preserva booleani/int/null
		// nell'oggetto ckConfig. Deve essere stampato PRIMA del core.
		$config = wp_json_encode( $this->build_config( $s ) );
		wp_add_inline_script( 'consentkit-manager', 'window.ckConfig = ' . $config . ';', 'before' );
	}

	/**
	 * Auto-contrasto (R6): dato un colore di sfondo esadecimale, restituisce
	 * il colore di testo che contrasta meglio — scuro su fondo chiaro, chiaro
	 * su fondo scuro. Usa la luminanza relativa WCAG. Ritorna '' se hex non valido.
	 *
	 * @param string $hex Colore esadecimale, es. '#1f2937'.
	 * @return string '#1f2937' | '#ffffff' | ''
	 */
	private static function contrast_text_for( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}
		$channels = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
		foreach ( $channels as $i => $c ) {
			$channels[ $i ] = ( $c <= 0.03928 ) ? ( $c / 12.92 ) : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		// Soglia ~0.22 = punto di crossover tra testo #1f2937 e #ffffff.
		return ( $luminance > 0.22 ) ? '#1f2937' : '#ffffff';
	}

	/**
	 * Costruisce l'oggetto window.ckConfig consumato dal core JS.
	 *
	 * @param array $s Impostazioni.
	 * @return array
	 */
	private function build_config( $s ) {
		$config = array(
			'version'           => CONSENTKIT_VERSION,
			'policyVersion'     => (string) $s['policy_version'],
			'consentDuration'   => (int) $s['consent_duration'],
			'repromptAfterDays' => (int) $s['reprompt_after_days'],
			'forceRenewDate'    => $s['force_renew_date'] ? $s['force_renew_date'] : null,
			'position'          => $s['position'],
			'privacyPolicyUrl'  => $s['privacy_policy_url'] ? esc_url( $s['privacy_policy_url'] ) : '',
			'cookiePolicyUrl'   => $s['cookie_policy_url'] ? esc_url( $s['cookie_policy_url'] ) : '',
			'integrations'      => array(
				'googleConsentMode' => (bool) $s['google_consent_mode'],
				'gtm'               => (bool) $s['gtm'],
				'linkedin'          => (bool) $s['linkedin'],
				'linkedinPartnerId' => $s['linkedin'] ? preg_replace( '/\D/', '', (string) $s['linkedin_partner_id'] ) : '',
			),
			'banner'            => array(
				'title'          => $s['title'],
				'body'           => $s['body'],
				'acceptLabel'    => $s['accept_label'],
				'rejectLabel'    => $s['reject_label'],
				'customizeLabel' => $s['customize_label'],
				'saveLabel'      => $s['save_label'],
				'closeLabel'     => $s['close_label'],
				'reviewLabel'    => $s['review_label'],
				'prefsTitle'     => $s['prefs_title'],
				'privacyLabel'   => __( 'Privacy policy', 'consentkit' ),
				'cookieLabel'    => __( 'Cookie policy', 'consentkit' ),
				'necessaryLabel' => __( 'Necessari (sempre attivi)', 'consentkit' ),
				'categoryLabels' => array(
					'analytics'   => __( 'Analytics', 'consentkit' ),
					'marketing'   => __( 'Marketing', 'consentkit' ),
					'preferences' => __( 'Preferenze', 'consentkit' ),
				),
			),
		);

		// Se il banner è disattivato (solo cookie tecnici, §13.11) non mostriamo nulla
		// ma lasciamo CM default già impostato. Segnaliamo al core con showBanner.
		if ( empty( $s['show_banner'] ) ) {
			$config['showBanner'] = false;
		}

		// Log server-side opzionale (§13.9).
		if ( ! empty( $s['log_enabled'] ) ) {
			$config['logEndpoint'] = esc_url_raw( rest_url( 'consentkit/v1/log' ) );
			$config['logNonce']    = wp_create_nonce( 'wp_rest' );
		}

		return $config;
	}
}
