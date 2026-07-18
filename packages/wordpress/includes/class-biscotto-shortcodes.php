<?php
/**
 * Shortcode per la pagina cookie policy (documento dell'editore, §8/§13.12).
 *
 *  [biscotto_cookie_table]      → tabella dei cookie/servizi per categoria.
 *  [biscotto_consent_settings]  → stato del consenso attuale + pulsante per
 *                                   gestire/revocare le scelte.
 *  [biscotto_cookie_policy]      → combinazione dei due.
 *
 * La policy resta un documento dell'editore: questi shortcode iniettano solo
 * l'elenco cookie e i controlli di consenso, non il testo informativo.
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biscotto_Shortcodes {

	public function __construct() {
		add_shortcode( 'biscotto_cookie_table', array( $this, 'cookie_table' ) );
		add_shortcode( 'biscotto_consent_settings', array( $this, 'consent_settings' ) );
		add_shortcode( 'biscotto_cookie_policy', array( $this, 'cookie_policy' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Etichette categorie (riusate da admin e banner).
	 *
	 * @return array
	 */
	private function category_labels() {
		return array(
			'necessary'   => __( 'Necessari', 'biscotto' ),
			'analytics'   => __( 'Analytics', 'biscotto' ),
			'marketing'   => __( 'Marketing', 'biscotto' ),
			'preferences' => __( 'Preferenze', 'biscotto' ),
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
		$has = has_shortcode( $post->post_content, 'biscotto_cookie_policy' )
			|| has_shortcode( $post->post_content, 'biscotto_consent_settings' );
		if ( ! $has ) {
			return;
		}

		// Dipende dal core (espone window.Biscotto + evento biscotto:consent).
		wp_enqueue_script(
			'biscotto-cookie-policy',
			BISCOTTO_URL . 'public/js/cookie-policy.js',
			array( 'biscotto-manager' ),
			BISCOTTO_VERSION,
			true
		);
		wp_localize_script(
			'biscotto-cookie-policy',
			'biscottoPolicy',
			array(
				'granted'    => __( 'Attivo', 'biscotto' ),
				'denied'     => __( 'Non attivo', 'biscotto' ),
				'categories' => $this->category_labels(),
			)
		);
	}

	/**
	 * [biscotto_cookie_table] — registry per categoria.
	 *
	 * @return string HTML.
	 */
	public function cookie_table() {
		$settings = Biscotto::get_settings();
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
		$other = __( 'Altri', 'biscotto' );
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
		echo '<div class="biscotto-cookie-table">';
		foreach ( $tree as $cat => $services ) {
			if ( empty( $services ) ) {
				continue;
			}
			echo '<section class="biscotto-cat">';
			echo '<h3 class="biscotto-cat-title">' . esc_html( $labels[ $cat ] ) . '</h3>';
			foreach ( $services as $service => $rows ) {
				// Link informativa: il primo disponibile tra i cookie del servizio.
				$policy = '';
				foreach ( $rows as $r ) {
					if ( ! empty( $r['url_policy'] ) ) {
						$policy = $r['url_policy'];
						break;
					}
				}
				echo '<div class="biscotto-service">';
				echo '<h4 class="biscotto-service-name">' . esc_html( $service );
				if ( $policy ) {
					echo ' <a class="biscotto-service-link" href="' . esc_url( $policy ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Informativa', 'biscotto' ) . '</a>';
				}
				echo '</h4>';
				echo '<table class="biscotto-table"><thead><tr>';
				echo '<th>' . esc_html__( 'Nome', 'biscotto' ) . '</th>';
				echo '<th>' . esc_html__( 'Durata', 'biscotto' ) . '</th>';
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
	 * [biscotto_consent_settings] — stato attuale + pulsante gestione.
	 *
	 * @param array $atts Attributi shortcode.
	 * @return string HTML.
	 */
	public function consent_settings( $atts ) {
		$atts = shortcode_atts(
			array(
				'button' => __( 'Gestisci le tue scelte', 'biscotto' ),
				'title'  => __( 'Le tue preferenze attuali', 'biscotto' ),
			),
			$atts,
			'biscotto_consent_settings'
		);

		ob_start();
		echo '<div class="biscotto-consent-settings">';
		if ( '' !== $atts['title'] ) {
			echo '<h3 class="biscotto-cat-title">' . esc_html( $atts['title'] ) . '</h3>';
		}
		// Riempito via JS al load e ad ogni evento biscotto:consent. Fallback no-JS sotto.
		echo '<ul class="biscotto-consent-state" data-biscotto-consent-state>';
		echo '<li>' . esc_html__( 'Attiva JavaScript per vedere e modificare le tue scelte.', 'biscotto' ) . '</li>';
		echo '</ul>';
		echo '<button type="button" class="biscotto-policy-manage">' . esc_html( $atts['button'] ) . '</button>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * [biscotto_cookie_policy] — tabella + impostazioni consenso.
	 *
	 * @param array $atts Attributi shortcode.
	 * @return string HTML.
	 */
	public function cookie_policy( $atts ) {
		return $this->cookie_table() . $this->consent_settings( is_array( $atts ) ? $atts : array() );
	}
}
