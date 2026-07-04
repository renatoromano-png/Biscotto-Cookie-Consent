<?php
/**
 * Tab Scansione — scanner cookie runtime (roadmap §14, v1.1).
 *
 * Carica gli URL bersaglio in iframe nascosti (token monouso, solo admin),
 * raccoglie cosa viene caricato e propone righe per il cookie registry.
 *
 * @package ConsentKit
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pre-compilata solo la homepage: l'utente aggiunge le pagine che vuole (max 10).
$consentkit_urls_text = esc_url( home_url( '/' ) );

$consentkit_last = get_option( ConsentKit_Scanner::RESULTS_OPTION, array() );
$consentkit_last_at = isset( $consentkit_last['scanned_at'] ) ? $consentkit_last['scanned_at'] : '';
?>
<div class="consentkit-scan">
	<p class="description">
		<?php esc_html_e( 'Lo scanner analizza le pagine indicate lato server (veloce) e, in più, carica la homepage in un iframe nascosto (solo per te, come amministratore) per rilevare anche i servizi iniettati via JavaScript. Rileva cookie e domini di terze parti e propone le righe da aggiungere al registro: la revisione e il salvataggio restano a te.', 'consentkit' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ck-scan-urls"><?php esc_html_e( 'URL da scansionare', 'consentkit' ); ?></label></th>
			<td>
				<textarea id="ck-scan-urls" rows="5" class="large-text code"><?php echo esc_textarea( $consentkit_urls_text ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Un URL per riga, massimo 10. La homepage intercetta già ciò che viene caricato a livello di sito (header e footer: font, Google Analytics, GTM, pixel) e copre la gran parte dei casi. Aggiungi altre pagine solo se hanno embed specifici (es. Google Maps nei Contatti, un articolo con YouTube): gli stessi servizi si ripetono su tutte le pagine, quindi non serve scansionare tutto il sito.', 'consentkit' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<p>
		<button type="button" class="button button-primary" id="ck-scan-start"><?php esc_html_e( 'Scansiona ora', 'consentkit' ); ?></button>
		<span id="ck-scan-status" class="ck-scan-status" aria-live="polite"></span>
	</p>

	<?php
	if ( $consentkit_last_at ) :
		// Salvato in UTC: lo mostriamo nel fuso orario configurato del sito.
		$consentkit_last_local = get_date_from_gmt(
			$consentkit_last_at,
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' )
		);
		?>
		<p class="description">
			<?php
			/* translators: %s: data e ora dell'ultima scansione (fuso del sito). */
			printf( esc_html__( 'Ultima scansione: %s.', 'consentkit' ), esc_html( $consentkit_last_local ) );
			?>
		</p>
	<?php endif; ?>

	<div id="ck-scan-results" hidden>
		<h2><?php esc_html_e( 'Risultati', 'consentkit' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Seleziona le righe da aggiungere al registro e verifica la categoria proposta. Le voci già presenti nel registro non vengono duplicate.', 'consentkit' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="ck-scan-checkall" /></th>
					<th><?php esc_html_e( 'Nome / Dominio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Servizio', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Durata', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Categoria', 'consentkit' ); ?></th>
					<th><?php esc_html_e( 'Origine', 'consentkit' ); ?></th>
				</tr>
			</thead>
			<tbody id="ck-scan-rows"></tbody>
		</table>
		<p>
			<button type="button" class="button" id="ck-scan-enrich"><?php esc_html_e( 'Arricchisci dal database', 'consentkit' ); ?></button>
			<span id="ck-scan-enrich-status" class="ck-scan-status" aria-live="polite"></span>
		</p>
		<p class="description"><?php esc_html_e( 'Completa servizio, categoria, durata e link privacy usando Open Cookie Database incluso nel plugin (licenza Apache-2.0, nessuna chiamata esterna). Non sovrascrive mai un campo già compilato.', 'consentkit' ); ?></p>
		<p>
			<button type="button" class="button button-primary" id="ck-scan-import"><?php esc_html_e( 'Aggiungi i selezionati al registro', 'consentkit' ); ?></button>
			<span id="ck-scan-import-status" class="ck-scan-status" aria-live="polite"></span>
		</p>
	</div>

	<div id="ck-scan-frames" style="position:absolute;width:0;height:0;overflow:hidden;left:-9999px;" aria-hidden="true"></div>
</div>
