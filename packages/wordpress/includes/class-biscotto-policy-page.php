<?php
/**
 * Pulsante admin "Crea pagina Cookie Policy": genera una bozza pre-compilata
 * con lo shortcode del registro, da rivedere e completare a mano prima
 * di pubblicare (la policy resta un documento dell'editore, non auto-pubblicato).
 *
 * @package Biscotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Biscotto_Policy_Page {

	const OPTION      = 'biscotto_policy_page_id';
	const ACTION      = 'biscotto_create_policy_page';
	const DATE_OPTION = 'biscotto_policy_last_updated';
	const DATE_ACTION = 'biscotto_update_policy_date';

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_create' ) );
		add_action( 'admin_post_' . self::DATE_ACTION, array( $this, 'handle_update_date' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * URL del pulsante "Crea pagina Cookie Policy" (GET con nonce, azione idempotente).
	 *
	 * @return string
	 */
	public static function create_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::ACTION
		);
	}

	/**
	 * URL del pulsante "Aggiorna data ultima modifica".
	 *
	 * @return string
	 */
	public static function update_date_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::DATE_ACTION ),
			self::DATE_ACTION
		);
	}

	/**
	 * Data (in formato leggibile, localizzata) da mostrare via [biscotto_last_updated].
	 * Se non è mai stata impostata la inizializza a oggi.
	 *
	 * @return string
	 */
	public static function formatted_date() {
		$date = get_option( self::DATE_OPTION );
		if ( ! $date ) {
			$date = current_time( 'Y-m-d' );
			update_option( self::DATE_OPTION, $date );
		}
		return date_i18n( 'j F Y', strtotime( $date ) );
	}

	/**
	 * Se esiste già una pagina creata da qui (e non è stata cancellata), la restituisce.
	 *
	 * @return WP_Post|null
	 */
	public static function existing_page() {
		$page_id = (int) get_option( self::OPTION );
		if ( ! $page_id ) {
			return null;
		}
		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return $post;
	}

	/**
	 * Crea la pagina (se non esiste già) e reindirizza al tab Cookie con un esito.
	 */
	public function handle_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per questa azione.', 'biscotto-cookie-consent' ) );
		}
		check_admin_referer( self::ACTION );

		$existing = self::existing_page();
		if ( $existing ) {
			$this->redirect_back( 'exists', $existing->ID );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Cookie Policy', 'biscotto-cookie-consent' ),
				'post_content' => $this->default_content(),
				'post_status'  => 'draft',
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			$this->redirect_back( 'error' );
			return;
		}

		// Inizializza subito la data mostrata da [biscotto_last_updated].
		if ( ! get_option( self::DATE_OPTION ) ) {
			update_option( self::DATE_OPTION, current_time( 'Y-m-d' ) );
		}

		update_option( self::OPTION, $page_id );
		$this->redirect_back( 'created', $page_id );
	}

	/**
	 * Aggiorna la data di "ultima modifica" della cookie policy a oggi.
	 */
	public function handle_update_date() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per questa azione.', 'biscotto-cookie-consent' ) );
		}
		check_admin_referer( self::DATE_ACTION );

		update_option( self::DATE_OPTION, current_time( 'Y-m-d' ) );

		$url = add_query_arg(
			array(
				'page'                => 'biscotto',
				'tab'                 => 'cookies',
				'biscotto_policy_date' => 'updated',
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param string $result created|exists|error
	 * @param int    $page_id
	 */
	private function redirect_back( $result, $page_id = 0 ) {
		$url = add_query_arg(
			array(
				'page'                    => 'biscotto',
				'tab'                     => 'cookies',
				'biscotto_policy_page'    => $result,
				'biscotto_policy_page_id' => $page_id,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Avviso con esito subito dopo la creazione della pagina, o dopo l'aggiornamento data.
	 */
	public function maybe_render_notice() {
		if ( 'biscotto' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['biscotto_policy_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Data di ultima modifica della Cookie Policy aggiornata a oggi.', 'biscotto-cookie-consent' ) . '</p></div>';
			return;
		}

		if ( ! isset( $_GET['biscotto_policy_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$result  = sanitize_key( $_GET['biscotto_policy_page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_id = isset( $_GET['biscotto_policy_page_id'] ) ? absint( $_GET['biscotto_policy_page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'error' === $result ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Non è stato possibile creare la pagina Cookie Policy.', 'biscotto-cookie-consent' ) . '</p></div>';
			return;
		}

		if ( ! in_array( $result, array( 'created', 'exists' ), true ) || ! $page_id ) {
			return;
		}

		$edit_link = get_edit_post_link( $page_id, 'raw' );
		$message   = 'created' === $result
			? __( 'Bozza della pagina Cookie Policy creata.', 'biscotto-cookie-consent' )
			: __( 'Esiste già una pagina Cookie Policy creata da qui.', 'biscotto-cookie-consent' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $message ); ?>
				<?php if ( $edit_link ) : ?>
					<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Aprila per completare i dati del titolare del sito e pubblicarla.', 'biscotto-cookie-consent' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Testo di base (blocchi Gutenberg) con placeholder da compilare e lo
	 * shortcode del registro cookie al paragrafo 6.
	 *
	 * @return string
	 */
	private function default_content() {
		return '<!-- wp:paragraph -->
<p><strong>⚠️ Bozza generata automaticamente da Biscotto.</strong> Prima di pubblicare: sostituisci i testi tra parentesi quadre (es. [Ragione sociale]) con i dati reali del titolare del sito, compila i dettagli di contatto al paragrafo 10 e verifica che le categorie descritte corrispondano ai cookie effettivamente presenti nel tab Cookie.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Cookie Policy (UE)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><em>Questa cookie policy si applica ai cittadini e ai residenti permanenti legali dello Spazio Economico Europeo e della Svizzera.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Questa cookie policy è stata aggiornata l\'ultima volta: [biscotto_last_updated]</em></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>1. Introduzione</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>La presente Cookie Policy si applica al sito web [nome-sito.it] (di seguito &#8220;il Sito&#8221;), di titolarità di [Ragione sociale, sede legale], e descrive le tipologie di cookie e di altri strumenti di tracciamento (script, web beacon) utilizzati, le finalità per cui vengono trattati e le modalità con cui puoi gestire o revocare in qualsiasi momento le tue preferenze. Continuando a navigare sul Sito dopo aver espresso una scelta tramite il banner cookie acconsenti all&#8217;uso dei cookie descritti in questo documento, nei limiti di quanto indicato.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>2. Cosa sono i cookie?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Un cookie è un piccolo file di testo che il browser salva sul dispositivo (computer, tablet o smartphone) che utilizzi per visitare questo sito. Contiene informazioni relative alla tua navigazione e consente al sito di riconoscerti in visite successive, ricordare le tue preferenze e migliorare l&#8217;esperienza d&#8217;uso. I cookie possono essere installati direttamente dal titolare del Sito (cookie di prima parte) oppure da soggetti terzi, ad esempio fornitori di servizi statistici o pubblicitari (cookie di terza parte).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>3. Cosa sono gli script?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Uno script è un frammento di codice utilizzato per far funzionare correttamente il Sito e le sue funzionalità interattive (moduli, mappe, video, ecc.). Alcuni script, per svolgere il proprio compito, possono raccogliere e trattare dati personali relativi alla tua navigazione.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>4. Cos\'è un web beacon?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Un web beacon (o pixel di tracciamento) è un piccolo elemento grafico, spesso invisibile, inserito in una pagina web o in un&#8217;email, utilizzato per verificare se un utente ha visitato una determinata pagina o aperto un messaggio e per raccogliere ulteriori statistiche di utilizzo del Sito.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>5. Cookie</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Sul Sito utilizziamo cookie di diverse tipologie, appartenenti alle categorie descritte di seguito. Alcuni sono installati da servizi terzi richiamati dalle nostre pagine. Puoi modificare o revocare in qualsiasi momento il consenso prestato tramite il pannello delle preferenze cookie, raggiungibile anche dalla tabella al paragrafo 6.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4>5.1 Cookie tecnici o funzionali</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>I cookie tecnici o funzionali sono necessari al corretto funzionamento del Sito e non possono essere disattivati. Vengono impostati in risposta ad azioni da te effettuate che costituiscono una richiesta di servizi, come impostare le tue preferenze di privacy, accedere ad un&#8217;area riservata o compilare un modulo. Non richiedono il tuo consenso preventivo e sono sempre attivi.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4>5.2 Cookie statistici</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>I cookie statistici ci aiutano a comprendere come i visitatori interagiscono con il Sito, raccogliendo e trasmettendo informazioni in forma aggregata o anonima. Ci permettono di misurare e migliorare le prestazioni delle nostre pagine. Richiedono il tuo consenso preventivo, salvo che siano configurati in modalità realmente anonima.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4>5.3 Cookie di marketing/tracciamento</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>I cookie di marketing/tracciamento vengono utilizzati per tracciare i visitatori attraverso i siti web, con l&#8217;obiettivo di mostrare messaggi pubblicitari pertinenti e coinvolgenti per il singolo utente. Richiedono sempre il tuo consenso preventivo prima di essere installati.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4>5.4 Social media</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>I cookie dei social media consentono di collegarti ai tuoi social network e di condividere contenuti del Sito attraverso tali piattaforme. I gestori dei social network possono inoltre utilizzare questi cookie a fini di profilazione pubblicitaria; ti invitiamo a consultare le rispettive informative privacy. Richiedono il tuo consenso preventivo.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>6. Cookie inseriti</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[biscotto_cookie_policy]
<!-- /wp:shortcode -->

<!-- wp:heading {"level":3} -->
<h3>7. Consenso</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Quando visiti il sito web per la prima volta, ti mostriamo un banner con una spiegazione dei cookie. Non appena scegli un&#8217;opzione, ci dai il permesso di usare le categorie di cookie e i servizi come descritto in questa cookie policy. Puoi disabilitare i cookie anche dal tuo browser, ma il sito potrebbe non funzionare più correttamente.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4>7.1 Gestisci le tue impostazioni di consenso</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Puoi rivedere e modificare in ogni momento le tue scelte usando il pulsante &#8220;Gestisci le tue scelte&#8221; mostrato al paragrafo 6, che apre il pannello con lo stato attuale del consenso per le seguenti categorie:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>Funzionale</strong> &#8211; sempre attivo, non richiede consenso (vedi 5.1).</li>
<li><strong>Preferenze</strong> &#8211; attivabile su tua scelta (vedi 5.1).</li>
<li><strong>Statistiche</strong> &#8211; attivabile su tua scelta (vedi 5.2).</li>
<li><strong>Marketing</strong> &#8211; attivabile su tua scelta (vedi 5.3 e 5.4).</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3>8. Abilitare/disabilitare e cancellazione dei cookie</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Puoi usare il tuo browser per cancellare automaticamente o manualmente i cookie. È anche possibile specificare che determinati cookie non possano essere installati, oppure ricevere un messaggio ogni volta che un cookie viene salvato. Per queste opzioni, consulta la sezione Guida del tuo browser.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Tieni presente che il Sito potrebbe non funzionare correttamente se disabiliti tutti i cookie. Se cancelli i cookie dal browser, verranno reinstallati in base al consenso che presterai alla tua prossima visita.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>9. I tuoi diritti in relazione ai dati personali</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Hai i seguenti diritti relativi ai tuoi dati personali:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Hai il diritto di sapere perché i tuoi dati personali sono necessari, cosa succede ad essi e per quanto tempo verranno conservati.</li>
<li>Diritto di accesso: hai il diritto di accedere ai tuoi dati personali di cui siamo a conoscenza.</li>
<li>Diritto di rettifica: hai il diritto di completare, correggere, far cancellare o bloccare i tuoi dati personali in qualsiasi momento.</li>
<li>Se ci hai dato il consenso a trattare i tuoi dati, hai il diritto di revocarlo e di far cancellare i tuoi dati personali.</li>
<li>Diritto alla portabilità: hai il diritto di richiedere tutti i tuoi dati al titolare del trattamento e trasferirli integralmente a un altro titolare.</li>
<li>Diritto di opposizione: hai il diritto di opporti al trattamento dei tuoi dati; rispetteremo la tua scelta, salvo che sussistano motivi legittimi cogenti per procedere.</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Per esercitare questi diritti, contattaci ai recapiti indicati al paragrafo 10. Se hai un reclamo su come trattiamo i tuoi dati, saremo felici di ascoltarti, ma hai anche il diritto di presentare reclamo all&#8217;autorità di controllo competente (in Italia, il Garante per la protezione dei dati personali).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>10. Dettagli di contatto</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Per domande e/o commenti riguardo questa Cookie Policy, contattaci ai seguenti recapiti:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Titolare del trattamento: [Ragione sociale]</li>
<li>Sede: [Indirizzo completo]</li>
<li>Email: [indirizzo email di contatto]</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>L&#8217;elenco dei cookie riportato al paragrafo 6 è generato automaticamente in base ai servizi e ai cookie effettivamente rilevati sul Sito. La data di ultima modifica di questa pagina è riportata in cima al documento.</p>
<!-- /wp:paragraph -->';
	}
}
