<?php
/**
 * Tab Integrazioni.
 *
 * @package ConsentKit
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consentkit_opt = CONSENTKIT_OPTION;
?>
<p class="description">
	<?php esc_html_e( 'Attiva qui solo gli strumenti che usi davvero: ogni script si carica solo dopo il consenso appropriato (o coi default corretti, per il Consent Mode). Attiva sempre "Google Consent Mode v2" se usi Google Ads o Analytics; il GTM ID va compilato solo se GTM non è già installato altrove nel sito.', 'consentkit' ); ?>
</p>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'biscotto' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $consentkit_opt ); ?>[google_consent_mode]" value="1" <?php checked( $settings['google_consent_mode'], 1 ); ?> />
				<?php esc_html_e( 'Inietta i default "denied" prima di GTM e aggiorna al consenso (obbligatorio per Google Ads).', 'biscotto' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Google Tag Manager', 'biscotto' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $consentkit_opt ); ?>[gtm]" value="1" <?php checked( $settings['gtm'], 1 ); ?> />
				<?php esc_html_e( 'Push su dataLayer al consenso.', 'biscotto' ); ?>
			</label>
			<p style="margin-top:8px;">
				<label><?php esc_html_e( 'GTM ID (opzionale, per auto-inject)', 'biscotto' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[gtm_id]" value="<?php echo esc_attr( $settings['gtm_id'] ); ?>" placeholder="GTM-XXXXXX" /></label>
			</p>
			<p class="description"><?php esc_html_e( 'Se inserisci il GTM ID, Biscotto carica GTM dopo il Consent Mode default. Se GTM è già nel tema, lascia vuoto.', 'biscotto' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'LinkedIn Insight Tag', 'biscotto' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $consentkit_opt ); ?>[linkedin]" value="1" <?php checked( $settings['linkedin'], 1 ); ?> />
				<?php esc_html_e( 'Carica LinkedIn Insight solo dopo consenso marketing.', 'biscotto' ); ?>
			</label>
			<p style="margin-top:8px;">
				<label><?php esc_html_e( 'Partner ID', 'biscotto' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $consentkit_opt ); ?>[linkedin_partner_id]" value="<?php echo esc_attr( $settings['linkedin_partner_id'] ); ?>" placeholder="123456" /></label>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Log consensi (server-side)', 'biscotto' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $consentkit_opt ); ?>[log_enabled]" value="1" <?php checked( $settings['log_enabled'], 1 ); ?> />
				<?php esc_html_e( 'Registra una prova pseudonimizzata del consenso (audit GDPR).', 'biscotto' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Salva timestamp, versione policy, azione e categorie — senza dati identificativi diretti.', 'biscotto' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Meta Pixel', 'biscotto' ); ?></th>
		<td><p class="description"><?php esc_html_e( 'In arrivo nella v1.1.', 'biscotto' ); ?></p></td>
	</tr>
</table>
