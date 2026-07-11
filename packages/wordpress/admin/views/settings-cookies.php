<?php
/**
 * Tab Cookie — registry editabile.
 *
 * @package ConsentKit
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$consentkit_opt     = CONSENTKIT_OPTION;
$consentkit_cookies = isset( $settings['cookies'] ) && is_array( $settings['cookies'] ) ? $settings['cookies'] : array();
$consentkit_cats    = array(
	'necessary'   => __( 'Necessari', 'biscotto' ),
	'analytics'   => __( 'Analytics', 'biscotto' ),
	'marketing'   => __( 'Marketing', 'biscotto' ),
	'preferences' => __( 'Preferenze', 'biscotto' ),
);

/**
 * Stampa una riga della tabella cookie.
 */
function consentkit_cookie_row( $i, $row, $opt, $cats ) {
	$name     = isset( $row['name'] ) ? $row['name'] : '';
	$service  = isset( $row['service'] ) ? $row['service'] : '';
	$duration = isset( $row['duration'] ) ? $row['duration'] : '';
	$category = isset( $row['category'] ) ? $row['category'] : 'necessary';
	$url      = isset( $row['url_policy'] ) ? $row['url_policy'] : '';
	?>
	<tr>
		<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[cookies][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'es. _ga', 'biscotto' ); ?>" /></td>
		<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[cookies][<?php echo esc_attr( $i ); ?>][service]" value="<?php echo esc_attr( $service ); ?>" placeholder="<?php esc_attr_e( 'es. Google Analytics', 'biscotto' ); ?>" /></td>
		<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[cookies][<?php echo esc_attr( $i ); ?>][duration]" value="<?php echo esc_attr( $duration ); ?>" placeholder="<?php esc_attr_e( 'es. 2 anni', 'biscotto' ); ?>" /></td>
		<td>
			<select name="<?php echo esc_attr( $opt ); ?>[cookies][<?php echo esc_attr( $i ); ?>][category]">
				<?php foreach ( $cats as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $category, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="url" name="<?php echo esc_attr( $opt ); ?>[cookies][<?php echo esc_attr( $i ); ?>][url_policy]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://..." /></td>
		<td><button type="button" class="button-link biscotto-remove-row" aria-label="<?php esc_attr_e( 'Rimuovi', 'biscotto' ); ?>">&times;</button></td>
	</tr>
	<?php
}
?>
<p class="description">
	<?php esc_html_e( 'Elenca qui i cookie/servizi che il sito usa davvero: popola il registro dal tab Scansione oppure aggiungili a mano. Per le terze parti basta servizio, categoria e link alla loro policy: non serve ogni singolo cookie (Garante).', 'biscotto' ); ?>
</p>

<div class="biscotto-copy-code">
	<p class="description"><?php esc_html_e( 'Per pubblicare questo elenco nella tua pagina cookie policy, copia questo codice e incollalo nella pagina:', 'biscotto' ); ?></p>
	<p>
		<input type="text" id="biscotto-shortcode-copy" class="regular-text code" readonly="readonly" value="[consentkit_cookie_policy]" />
		<button type="button" class="button" id="biscotto-copy-shortcode"><?php esc_html_e( 'Copia', 'biscotto' ); ?></button>
		<span id="biscotto-copy-status" class="biscotto-scan-status" aria-live="polite"></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'In alternativa puoi comporre la pagina con gli shortcode singoli:', 'biscotto' ); ?>
		<code>[consentkit_cookie_table]</code> <?php esc_html_e( '(solo tabella cookie),', 'biscotto' ); ?>
		<code>[consentkit_consent_settings]</code> <?php esc_html_e( '(solo stato consenso + pulsante).', 'biscotto' ); ?>
	</p>
	<?php
	$consentkit_policy_page = ConsentKit_Policy_Page::existing_page();
	$consentkit_policy_url  = $consentkit_policy_page
		? get_edit_post_link( $consentkit_policy_page->ID, 'raw' )
		: ConsentKit_Policy_Page::create_url();
	?>
	<p>
		<a href="<?php echo esc_url( $consentkit_policy_url ); ?>" class="button">
			<?php echo $consentkit_policy_page ? esc_html__( 'Apri la bozza Cookie Policy', 'consentkit' ) : esc_html__( 'Crea pagina Cookie Policy', 'consentkit' ); ?>
		</a>
		<span class="description">
			<?php if ( $consentkit_policy_page ) : ?>
				<?php esc_html_e( 'Bozza già creata: da rivedere e completare con i dati del titolare del sito prima di pubblicare.', 'consentkit' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Crea una bozza pre-compilata (con questo shortcode già inserito) da completare con i dati del titolare del sito prima di pubblicare.', 'consentkit' ); ?>
			<?php endif; ?>
		</span>
	</p>
	<p>
		<a href="<?php echo esc_url( ConsentKit_Policy_Page::update_date_url() ); ?>" class="button">
			<?php esc_html_e( 'Aggiorna data ultima modifica', 'consentkit' ); ?>
		</a>
		<span class="description">
			<?php
			/* translators: %s: data corrente di "ultima modifica" mostrata dallo shortcode [consentkit_last_updated]. */
			printf( esc_html__( 'Attualmente mostrata come: %s. Usalo quando pubblichi modifiche sostanziali alla policy.', 'consentkit' ), '<strong>' . esc_html( ConsentKit_Policy_Page::formatted_date() ) . '</strong>' );
			?>
		</span>
	</p>
</div>

<table class="widefat striped consentkit-cookies">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Nome', 'biscotto' ); ?></th>
			<th><?php esc_html_e( 'Servizio', 'biscotto' ); ?></th>
			<th><?php esc_html_e( 'Durata', 'biscotto' ); ?></th>
			<th><?php esc_html_e( 'Categoria', 'biscotto' ); ?></th>
			<th><?php esc_html_e( 'URL policy (terze parti)', 'biscotto' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody id="biscotto-cookie-rows">
		<?php
		$consentkit_i = 0;
		foreach ( $consentkit_cookies as $consentkit_row ) {
			consentkit_cookie_row( $consentkit_i, $consentkit_row, $consentkit_opt, $consentkit_cats );
			$consentkit_i++;
		}
		// Riga template vuota (indice alto, ignorata se lasciata vuota dal sanitize).
		consentkit_cookie_row( 9000, array( 'category' => 'necessary' ), $consentkit_opt, $consentkit_cats );
		?>
	</tbody>
</table>

<p>
	<button type="button" class="button" id="biscotto-add-cookie"><?php esc_html_e( '+ Aggiungi cookie', 'biscotto' ); ?></button>
	<button type="button" class="button" id="biscotto-clear-cookies"><?php esc_html_e( 'Svuota registro', 'biscotto' ); ?></button>
	<span class="description"><?php esc_html_e( 'Dopo aver svuotato, ricordati di salvare.', 'biscotto' ); ?></span>
</p>
<?php // Il comportamento (aggiungi/rimuovi riga, svuota, copia shortcode) è in admin/js/cookies.js, accodato via wp_enqueue_script nel tab Cookie. ?>
