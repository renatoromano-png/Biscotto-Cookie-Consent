<?php
/**
 * Tab Generale.
 *
 * @package Biscotto
 * @var array $settings Impostazioni correnti (fornite da render_page()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$biscotto_opt = BISCOTTO_OPTION;
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="biscotto-show-banner"><?php esc_html_e( 'Mostra banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<label>
				<input type="checkbox" id="biscotto-show-banner" name="<?php echo esc_attr( $biscotto_opt ); ?>[show_banner]" value="1" <?php checked( $settings['show_banner'], 1 ); ?> />
				<?php esc_html_e( 'Attiva il banner di consenso', 'biscotto-cookie-consent' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Disattiva solo se il sito usa esclusivamente cookie tecnici (in tal caso resta obbligatoria la cookie policy).', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-title"><?php esc_html_e( 'Titolo banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="text" id="biscotto-title" class="regular-text" name="<?php echo esc_attr( $biscotto_opt ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-body"><?php esc_html_e( 'Testo banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<textarea id="biscotto-body" class="large-text" rows="3" name="<?php echo esc_attr( $biscotto_opt ); ?>[body]"><?php echo esc_textarea( $settings['body'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Deve sintetizzare le finalità (analytics, marketing) — informativa a livelli. Il dettaglio va nella cookie policy.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Etichette pulsanti', 'biscotto-cookie-consent' ); ?></th>
		<td>
			<p>
				<label><?php esc_html_e( 'Accetta', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[accept_label]" value="<?php echo esc_attr( $settings['accept_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Rifiuta', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[reject_label]" value="<?php echo esc_attr( $settings['reject_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Gestisci preferenze', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[customize_label]" value="<?php echo esc_attr( $settings['customize_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Rivedi le scelte (footer/icona)', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[review_label]" value="<?php echo esc_attr( $settings['review_label'] ); ?>" /></label>
			</p>
			<p class="description"><?php esc_html_e( 'Accetta e Rifiuta hanno sempre la stessa grafica (parità imposta dal Garante).', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-color"><?php esc_html_e( 'Colore primario (pulsanti)', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="text" id="biscotto-color" class="biscotto-color-field" name="<?php echo esc_attr( $biscotto_opt ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-primary-text-color"><?php esc_html_e( 'Colore testo pulsanti', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<input type="text" id="biscotto-primary-text-color" class="biscotto-color-field" name="<?php echo esc_attr( $biscotto_opt ); ?>[primary_text_color]" value="<?php echo esc_attr( $settings['primary_text_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = bianco.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-bg-color"><?php esc_html_e( 'Colore sfondo banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<input type="text" id="biscotto-bg-color" class="biscotto-color-field" name="<?php echo esc_attr( $biscotto_opt ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = automatico (chiaro/scuro di sistema). Per un box scuro imposta sfondo scuro e testo chiaro.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-text-color"><?php esc_html_e( 'Colore testo banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<input type="text" id="biscotto-text-color" class="biscotto-color-field" name="<?php echo esc_attr( $biscotto_opt ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = automatico.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-position"><?php esc_html_e( 'Posizione banner', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<select id="biscotto-position" name="<?php echo esc_attr( $biscotto_opt ); ?>[position]">
				<option value="bottom-bar" <?php selected( $settings['position'], 'bottom-bar' ); ?>><?php esc_html_e( 'Barra in basso', 'biscotto-cookie-consent' ); ?></option>
				<option value="modal" <?php selected( $settings['position'], 'modal' ); ?>><?php esc_html_e( 'Riquadro centrato (modal)', 'biscotto-cookie-consent' ); ?></option>
				<option value="box-right" <?php selected( $settings['position'], 'box-right' ); ?>><?php esc_html_e( 'Riquadro in basso a destra', 'biscotto-cookie-consent' ); ?></option>
				<option value="box-left" <?php selected( $settings['position'], 'box-left' ); ?>><?php esc_html_e( 'Riquadro in basso a sinistra', 'biscotto-cookie-consent' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-duration"><?php esc_html_e( 'Durata consenso (giorni)', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="number" id="biscotto-duration" min="1" name="<?php echo esc_attr( $biscotto_opt ); ?>[consent_duration]" value="<?php echo esc_attr( $settings['consent_duration'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-reprompt"><?php esc_html_e( 'Min. giorni prima di riproporre', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<input type="number" id="biscotto-reprompt" min="180" name="<?php echo esc_attr( $biscotto_opt ); ?>[reprompt_after_days]" value="<?php echo esc_attr( $settings['reprompt_after_days'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Il Garante impone almeno 6 mesi (180 giorni). Valori inferiori non sono ammessi.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-policy-version"><?php esc_html_e( 'Versione cookie policy', 'biscotto-cookie-consent' ); ?></label></th>
		<td>
			<input type="text" id="biscotto-policy-version" name="<?php echo esc_attr( $biscotto_opt ); ?>[policy_version]" value="<?php echo esc_attr( $settings['policy_version'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Cambiando questo valore i consensi precedenti vengono invalidati e il banner riproposto (re-consent).', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-force-renew"><?php esc_html_e( 'Forza rinnovo da data', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="date" id="biscotto-force-renew" name="<?php echo esc_attr( $biscotto_opt ); ?>[force_renew_date]" value="<?php echo esc_attr( $settings['force_renew_date'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-privacy-url"><?php esc_html_e( 'URL privacy policy', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="url" id="biscotto-privacy-url" class="regular-text" name="<?php echo esc_attr( $biscotto_opt ); ?>[privacy_policy_url]" value="<?php echo esc_attr( $settings['privacy_policy_url'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="biscotto-cookie-url"><?php esc_html_e( 'URL cookie policy', 'biscotto-cookie-consent' ); ?></label></th>
		<td><input type="url" id="biscotto-cookie-url" class="regular-text" name="<?php echo esc_attr( $biscotto_opt ); ?>[cookie_policy_url]" value="<?php echo esc_attr( $settings['cookie_policy_url'] ); ?>" /></td>
	</tr>
</table>
