<?php
/**
 * Tab Generale.
 *
 * @package ConsentKit
 * @var array $settings Impostazioni correnti (fornite da render_page()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consentkit_opt = CONSENTKIT_OPTION;
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="ck-show-banner"><?php esc_html_e( 'Mostra banner', 'consentkit' ); ?></label></th>
		<td>
			<label>
				<input type="checkbox" id="ck-show-banner" name="<?php echo esc_attr( $consentkit_opt ); ?>[show_banner]" value="1" <?php checked( $settings['show_banner'], 1 ); ?> />
				<?php esc_html_e( 'Attiva il banner di consenso', 'consentkit' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Disattiva solo se il sito usa esclusivamente cookie tecnici (in tal caso resta obbligatoria la cookie policy).', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-title"><?php esc_html_e( 'Titolo banner', 'consentkit' ); ?></label></th>
		<td><input type="text" id="ck-title" class="regular-text" name="<?php echo esc_attr( $consentkit_opt ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-body"><?php esc_html_e( 'Testo banner', 'consentkit' ); ?></label></th>
		<td>
			<textarea id="ck-body" class="large-text" rows="3" name="<?php echo esc_attr( $consentkit_opt ); ?>[body]"><?php echo esc_textarea( $settings['body'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Deve sintetizzare le finalità (analytics, marketing) — informativa a livelli. Il dettaglio va nella cookie policy.', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Etichette pulsanti', 'consentkit' ); ?></th>
		<td>
			<p>
				<label><?php esc_html_e( 'Accetta', 'consentkit' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[accept_label]" value="<?php echo esc_attr( $settings['accept_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Rifiuta', 'consentkit' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[reject_label]" value="<?php echo esc_attr( $settings['reject_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Gestisci preferenze', 'consentkit' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[customize_label]" value="<?php echo esc_attr( $settings['customize_label'] ); ?>" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Rivedi le scelte (footer/icona)', 'consentkit' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[review_label]" value="<?php echo esc_attr( $settings['review_label'] ); ?>" /></label>
			</p>
			<p class="description"><?php esc_html_e( 'Accetta e Rifiuta hanno sempre la stessa grafica (parità imposta dal Garante).', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-color"><?php esc_html_e( 'Colore primario (pulsanti)', 'consentkit' ); ?></label></th>
		<td><input type="text" id="ck-color" class="ck-color-field" name="<?php echo esc_attr( $consentkit_opt ); ?>[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-primary-text-color"><?php esc_html_e( 'Colore testo pulsanti', 'consentkit' ); ?></label></th>
		<td>
			<input type="text" id="ck-primary-text-color" class="ck-color-field" name="<?php echo esc_attr( $consentkit_opt ); ?>[primary_text_color]" value="<?php echo esc_attr( $settings['primary_text_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = bianco.', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-bg-color"><?php esc_html_e( 'Colore sfondo banner', 'consentkit' ); ?></label></th>
		<td>
			<input type="text" id="ck-bg-color" class="ck-color-field" name="<?php echo esc_attr( $consentkit_opt ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = automatico (chiaro/scuro di sistema). Per un box scuro imposta sfondo scuro e testo chiaro.', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-text-color"><?php esc_html_e( 'Colore testo banner', 'consentkit' ); ?></label></th>
		<td>
			<input type="text" id="ck-text-color" class="ck-color-field" name="<?php echo esc_attr( $consentkit_opt ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Vuoto = automatico.', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-position"><?php esc_html_e( 'Posizione banner', 'consentkit' ); ?></label></th>
		<td>
			<select id="ck-position" name="<?php echo esc_attr( $consentkit_opt ); ?>[position]">
				<option value="bottom-bar" <?php selected( $settings['position'], 'bottom-bar' ); ?>><?php esc_html_e( 'Barra in basso', 'consentkit' ); ?></option>
				<option value="modal" <?php selected( $settings['position'], 'modal' ); ?>><?php esc_html_e( 'Riquadro centrato (modal)', 'consentkit' ); ?></option>
				<option value="box-right" <?php selected( $settings['position'], 'box-right' ); ?>><?php esc_html_e( 'Riquadro in basso a destra', 'consentkit' ); ?></option>
				<option value="box-left" <?php selected( $settings['position'], 'box-left' ); ?>><?php esc_html_e( 'Riquadro in basso a sinistra', 'consentkit' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-duration"><?php esc_html_e( 'Durata consenso (giorni)', 'consentkit' ); ?></label></th>
		<td><input type="number" id="ck-duration" min="1" name="<?php echo esc_attr( $consentkit_opt ); ?>[consent_duration]" value="<?php echo esc_attr( $settings['consent_duration'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-reprompt"><?php esc_html_e( 'Min. giorni prima di riproporre', 'consentkit' ); ?></label></th>
		<td>
			<input type="number" id="ck-reprompt" min="180" name="<?php echo esc_attr( $consentkit_opt ); ?>[reprompt_after_days]" value="<?php echo esc_attr( $settings['reprompt_after_days'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Il Garante impone almeno 6 mesi (180 giorni). Valori inferiori non sono ammessi.', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-policy-version"><?php esc_html_e( 'Versione cookie policy', 'consentkit' ); ?></label></th>
		<td>
			<input type="text" id="ck-policy-version" name="<?php echo esc_attr( $consentkit_opt ); ?>[policy_version]" value="<?php echo esc_attr( $settings['policy_version'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Cambiando questo valore i consensi precedenti vengono invalidati e il banner riproposto (re-consent).', 'consentkit' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-force-renew"><?php esc_html_e( 'Forza rinnovo da data', 'consentkit' ); ?></label></th>
		<td><input type="date" id="ck-force-renew" name="<?php echo esc_attr( $consentkit_opt ); ?>[force_renew_date]" value="<?php echo esc_attr( $settings['force_renew_date'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-privacy-url"><?php esc_html_e( 'URL privacy policy', 'consentkit' ); ?></label></th>
		<td><input type="url" id="ck-privacy-url" class="regular-text" name="<?php echo esc_attr( $consentkit_opt ); ?>[privacy_policy_url]" value="<?php echo esc_attr( $settings['privacy_policy_url'] ); ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="ck-cookie-url"><?php esc_html_e( 'URL cookie policy', 'consentkit' ); ?></label></th>
		<td><input type="url" id="ck-cookie-url" class="regular-text" name="<?php echo esc_attr( $consentkit_opt ); ?>[cookie_policy_url]" value="<?php echo esc_attr( $settings['cookie_policy_url'] ); ?>" /></td>
	</tr>
</table>
