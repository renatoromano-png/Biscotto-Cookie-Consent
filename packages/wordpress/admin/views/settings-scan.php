<?php
/**
 * Tab Scansione — scanner cookie runtime (roadmap §14, v1.1).
 *
 * Carica gli URL bersaglio in iframe nascosti (token monouso, solo admin),
 * raccoglie cosa viene caricato e propone righe per il cookie registry.
 *
 * @package Biscotto
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pre-compilata solo la homepage: l'utente aggiunge le pagine che vuole (max 10).
$biscotto_urls_text = esc_url( home_url( '/' ) );

$biscotto_last = get_option( Biscotto_Scanner::RESULTS_OPTION, array() );
$biscotto_last_at = isset( $biscotto_last['scanned_at'] ) ? $biscotto_last['scanned_at'] : '';
?>
<div class="biscotto-scan">
	<p class="description">
		<?php esc_html_e( 'Lo scanner analizza le pagine indicate lato server (veloce) e, in più, carica la homepage in un iframe nascosto (solo per te, come amministratore) per rilevare anche i servizi iniettati via JavaScript. Rileva cookie e domini di terze parti e propone le righe da aggiungere al registro: la revisione e il salvataggio restano a te.', 'biscotto' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="biscotto-scan-urls"><?php esc_html_e( 'URL da scansionare', 'biscotto' ); ?></label></th>
			<td>
				<textarea id="biscotto-scan-urls" rows="5" class="large-text code"><?php echo esc_textarea( $biscotto_urls_text ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Un URL per riga, massimo 10. La homepage intercetta già ciò che viene caricato a livello di sito (header e footer: font, Google Analytics, GTM, pixel) e copre la gran parte dei casi. Aggiungi altre pagine solo se hanno embed specifici (es. Google Maps nei Contatti, un articolo con YouTube): gli stessi servizi si ripetono su tutte le pagine, quindi non serve scansionare tutto il sito.', 'biscotto' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<p>
		<button type="button" class="button button-primary" id="biscotto-scan-start"><?php esc_html_e( 'Scansiona ora', 'biscotto' ); ?></button>
		<span id="biscotto-scan-status" class="biscotto-scan-status" aria-live="polite"></span>
	</p>

	<?php
	if ( $biscotto_last_at ) :
		// Salvato in UTC: lo mostriamo nel fuso orario configurato del sito.
		$biscotto_last_local = get_date_from_gmt(
			$biscotto_last_at,
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' )
		);
		?>
		<p class="description">
			<?php
			/* translators: %s: data e ora dell'ultima scansione (fuso del sito). */
			printf( esc_html__( 'Ultima scansione: %s.', 'biscotto' ), esc_html( $biscotto_last_local ) );
			?>
		</p>
	<?php endif; ?>

	<div id="biscotto-scan-results" hidden>
		<h2><?php esc_html_e( 'Risultati', 'biscotto' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Seleziona le righe da aggiungere al registro e verifica la categoria proposta. Le voci già presenti nel registro non vengono duplicate.', 'biscotto' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="biscotto-scan-checkall" /></th>
					<th><?php esc_html_e( 'Nome / Dominio', 'biscotto' ); ?></th>
					<th><?php esc_html_e( 'Servizio', 'biscotto' ); ?></th>
					<th><?php esc_html_e( 'Durata', 'biscotto' ); ?></th>
					<th><?php esc_html_e( 'Categoria', 'biscotto' ); ?></th>
					<th><?php esc_html_e( 'Origine', 'biscotto' ); ?></th>
				</tr>
			</thead>
			<tbody id="biscotto-scan-rows"></tbody>
		</table>
		<p>
			<button type="button" class="button" id="biscotto-scan-enrich"><?php esc_html_e( 'Arricchisci dal database', 'biscotto' ); ?></button>
			<span id="biscotto-scan-enrich-status" class="biscotto-scan-status" aria-live="polite"></span>
		</p>
		<p class="description"><?php esc_html_e( 'Completa servizio, categoria, durata e link privacy usando Open Cookie Database incluso nel plugin (licenza Apache-2.0, nessuna chiamata esterna). Non sovrascrive mai un campo già compilato.', 'biscotto' ); ?></p>
		<p>
			<button type="button" class="button button-primary" id="biscotto-scan-import"><?php esc_html_e( 'Aggiungi i selezionati al registro', 'biscotto' ); ?></button>
			<span id="biscotto-scan-import-status" class="biscotto-scan-status" aria-live="polite"></span>
		</p>
	</div>

	<hr />
	<h2><?php esc_html_e( 'Database cookie incluso', 'biscotto' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: data dello snapshot del database bundlato (AAAA-MM-GG). */
			esc_html__( 'Il plugin include una copia locale di Open Cookie Database (licenza Apache-2.0), aggiornata al %s. Usarla non invia alcun dato del tuo sito.', 'biscotto' ),
			esc_html( Biscotto_Cookie_Database::SNAPSHOT_DATE )
		);
		?>
	</p>
	<p>
		<button type="button" class="button" id="biscotto-scan-dbcheck"><?php esc_html_e( 'Controlla aggiornamenti database', 'biscotto' ); ?></button>
		<span id="biscotto-scan-dbcheck-status" class="biscotto-scan-status" aria-live="polite"></span>
	</p>
	<p class="description"><?php esc_html_e( "Contatta l'API pubblica di GitHub (api.github.com) solo quando clicchi questo pulsante, per verificare la data dell'ultimo aggiornamento del dataset upstream. Nessun aggiornamento automatico, nessun dato del sito inviato.", 'biscotto' ); ?></p>

	<div id="biscotto-scan-frames" style="position:absolute;width:0;height:0;overflow:hidden;left:-9999px;" aria-hidden="true"></div>
</div>
