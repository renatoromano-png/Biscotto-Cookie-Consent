<?php
/**
 * Tab Integrazioni.
 *
 * @package Biscotto
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$biscotto_opt = BISCOTTO_OPTION;
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'biscotto-cookie-consent' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $biscotto_opt ); ?>[google_consent_mode]" value="1" <?php checked( $settings['google_consent_mode'], 1 ); ?> />
				<?php esc_html_e( 'Inietta i default "denied" prima di GTM e aggiorna al consenso (obbligatorio per Google Ads).', 'biscotto-cookie-consent' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Google Tag Manager', 'biscotto-cookie-consent' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $biscotto_opt ); ?>[gtm]" value="1" <?php checked( $settings['gtm'], 1 ); ?> />
				<?php esc_html_e( 'Push su dataLayer al consenso.', 'biscotto-cookie-consent' ); ?>
			</label>
			<p style="margin-top:8px;">
				<label><?php esc_html_e( 'GTM ID (opzionale, per auto-inject)', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[gtm_id]" value="<?php echo esc_attr( $settings['gtm_id'] ); ?>" placeholder="GTM-XXXXXX" /></label>
			</p>
			<p class="description"><?php esc_html_e( 'Se inserisci il GTM ID, Biscotto carica GTM dopo il Consent Mode default. Se GTM è già nel tema, lascia vuoto.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'LinkedIn Insight Tag', 'biscotto-cookie-consent' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $biscotto_opt ); ?>[linkedin]" value="1" <?php checked( $settings['linkedin'], 1 ); ?> />
				<?php esc_html_e( 'Carica LinkedIn Insight solo dopo consenso marketing.', 'biscotto-cookie-consent' ); ?>
			</label>
			<p style="margin-top:8px;">
				<label><?php esc_html_e( 'Partner ID', 'biscotto-cookie-consent' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $biscotto_opt ); ?>[linkedin_partner_id]" value="<?php echo esc_attr( $settings['linkedin_partner_id'] ); ?>" placeholder="123456" /></label>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Log consensi (server-side)', 'biscotto-cookie-consent' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $biscotto_opt ); ?>[log_enabled]" value="1" <?php checked( $settings['log_enabled'], 1 ); ?> />
				<?php esc_html_e( 'Registra una prova pseudonimizzata del consenso (audit GDPR).', 'biscotto-cookie-consent' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Salva timestamp, versione policy, azione e categorie — senza dati identificativi diretti.', 'biscotto-cookie-consent' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Meta Pixel', 'biscotto-cookie-consent' ); ?></th>
		<td><p class="description"><?php esc_html_e( 'In arrivo nella v1.1.', 'biscotto-cookie-consent' ); ?></p></td>
	</tr>
</table>
