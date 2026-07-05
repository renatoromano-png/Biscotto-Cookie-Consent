<?php
/**
 * Shortcode per la pagina cookie policy (documento dell'editore, §8/§13.12).
 *
 *  [consentkit_cookie_table]      → tabella dei cookie/servizi per categoria.
 *  [consentkit_consent_settings]  → stato del consenso attuale + pulsante per
 *                                   gestire/revocare le scelte.
 *  [consentkit_cookie_policy]      → combinazione dei due.
 *  [consentkit_last_updated]      → data (leggibile) di ultima modifica della
 *                                   policy, aggiornata dal pulsante in admin.
 *
 * La policy resta un documento dell'editore: questi shortcode iniettano solo
 * l'elenco cookie e i controlli di consenso, non il testo informativo.
 *
 * @package ConsentKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConsentKit_Shortcodes {

	public function __construct() {
		add_shortcode( 'consentkit_cookie_table', array( $this, 'cookie_table' ) );
		add_shortcode( 'consentkit_consent_settings', array( $this, 'consent_settings' ) );
		add_shortcode( 'consentkit_cookie_policy', array( $this, 'cookie_policy' ) );
		add_shortcode( 'consentkit_last_updated', array( $this, 'last_updated' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Etichette categorie (riusate da admin e banner).
	 *
	 * @return array
	 */
	private function category_labels() {
		return array(
			'necessary'   => __( 'Necessari', 'consentkit' ),
			'analytics'   => __( 'Analytics', 'consentkit' ),
			'marketing'   => __( 'Marketing', 'consentkit' ),
			'preferences' => __( 'Preferenze', 'consentkit' ),
		);
	}

	/**
	 * Carica lo script della pagina policy solo dove serve (shortcode presente).
	 */
	public function maybe_enqueue() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$has = has_shortcode( $post->post_content, 'consentkit_cookie_policy' )
			|| has_shortcode( $post->post_content, 'consentkit_consent_settings' );
		if ( ! $has ) {
			return;
		}

		// Dipende dal core (espone window.ConsentKit + evento ck:consent).
		wp_enqueue_script(
			'consentkit-cookie-policy',
			CONSENTKIT_URL . 'public/js/cookie-policy.js',
			array( 'consentkit-manager' ),
			CONSENTKIT_VERSION,
			true
		);
		wp_localize_script(
			'consentkit-cookie-policy',
			'ckPolicy',
			array(
				'granted'    => __( 'Attivo', 'consentkit' ),
				'denied'     => __( 'Non attivo', 'consentkit' ),
				'categories' => $this->category_labels(),
			)
		);
	}

	/**
	 * [consentkit_cookie_table] — registry per categoria.
	 *
	 * @return string HTML.
	 */
	public function cookie_table() {
		$settings = ConsentKit::get_settings();
		$cookies  = isset( $settings['cookies'] ) && is_array( $settings['cookies'] ) ? $settings['cookies'] : array();
		$labels   = $this->category_labels();

		// Raggruppa per categoria mantenendo l'ordine canonico.
		$grouped = array();
		foreach ( array_keys( $labels ) as $cat ) {
			$grouped[ $cat ] = array();
		}
		foreach ( $cookies as $row ) {
			$cat = isset( $row['category'], $labels[ $row['category'] ] ) ? $row['category'] : 'necessary';
			$grouped[ $cat ][] = $row;
		}

		// Stile Complianz: categoria → servizio (con link informativa) → cookie.
		$other = __( 'Altri', 'consentkit' );
		$tree  = array();
		foreach ( array_keys( $labels ) as $cat ) {
			$tree[ $cat ] = array();
		}
		foreach ( $grouped as $cat => $rows ) {
			foreach ( $rows as $row ) {
				$service = isset( $row['service'] ) && '' !== trim( (string) $row['service'] ) ? $row['service'] : $other;
				$tree[ $cat ][ $service ][] = $row;
			}
		}

		ob_start();
		echo '<div class="ck-cookie-table">';
		foreach ( $tree as $cat => $services ) {
			if ( empty( $services ) ) {
				continue;
			}
			echo '<section class="ck-cat">';
			echo '<h3 class="ck-cat-title">' . esc_html( $labels[ $cat ] ) . '</h3>';
			foreach ( $services as $service => $rows ) {
				// Link informativa: il primo disponibile tra i cookie del servizio.
				$policy = '';
				foreach ( $rows as $r ) {
					if ( ! empty( $r['url_policy'] ) ) {
						$policy = $r['url_policy'];
						break;
					}
				}
				echo '<div class="ck-service">';
				echo '<h4 class="ck-service-name">' . esc_html( $service );
				if ( $policy ) {
					echo ' <a class="ck-service-link" href="' . esc_url( $policy ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Informativa', 'consentkit' ) . '</a>';
				}
				echo '</h4>';
				echo '<table class="ck-table"><thead><tr>';
				echo '<th>' . esc_html__( 'Nome', 'consentkit' ) . '</th>';
				echo '<th>' . esc_html__( 'Durata', 'consentkit' ) . '</th>';
				echo '</tr></thead><tbody>';
				foreach ( $rows as $r ) {
					$name     = isset( $r['name'] ) ? $r['name'] : '';
					$duration = isset( $r['duration'] ) ? $r['duration'] : '';
					echo '<tr>';
					echo '<td>' . esc_html( $name ) . '</td>';
					echo '<td>' . ( $duration ? esc_html( $duration ) : '&mdash;' ) . '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
				echo '</div>';
			}
			echo '</section>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * [consentkit_consent_settings] — stato attuale + pulsante gestione.
	 *
	 * @param array $atts Attributi shortcode.
	 * @return string HTML.
	 */
	public function consent_settings( $atts ) {
		$atts = shortcode_atts(
			array(
				'button' => __( 'Gestisci le tue scelte', 'consentkit' ),
				'title'  => __( 'Le tue preferenze attuali', 'consentkit' ),
			),
			$atts,
			'consentkit_consent_settings'
		);

		ob_start();
		echo '<div class="ck-consent-settings">';
		if ( '' !== $atts['title'] ) {
			echo '<h3 class="ck-cat-title">' . esc_html( $atts['title'] ) . '</h3>';
		}
		// Riempito via JS al load e ad ogni evento ck:consent. Fallback no-JS sotto.
		echo '<ul class="ck-consent-state" data-ck-consent-state>';
		echo '<li>' . esc_html__( 'Attiva JavaScript per vedere e modificare le tue scelte.', 'consentkit' ) . '</li>';
		echo '</ul>';
		echo '<button type="button" class="ck-policy-manage">' . esc_html( $atts['button'] ) . '</button>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * [consentkit_cookie_policy] — tabella + impostazioni consenso.
	 *
	 * @param array $atts Attributi shortcode.
	 * @return string HTML.
	 */
	public function cookie_policy( $atts ) {
		return $this->cookie_table() . $this->consent_settings( is_array( $atts ) ? $atts : array() );
	}

	/**
	 * [consentkit_last_updated] — data di ultima modifica della policy, in
	 * formato leggibile. Aggiornata dal pulsante "Aggiorna data ultima modifica"
	 * nel tab Cookie dell'admin.
	 *
	 * @return string
	 */
	public function last_updated() {
		return esc_html( ConsentKit_Policy_Page::formatted_date() );
	}
}
