<?php
/**
 * Tab Cookie — registry editabile.
 *
 * @package Biscotto
 * @var array $settings Impostazioni correnti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$biscotto_opt     = BISCOTTO_OPTION;
$biscotto_cookies = isset( $settings['cookies'] ) && is_array( $settings['cookies'] ) ? $settings['cookies'] : array();
$biscotto_cats    = array(
	'necessary'   => __( 'Necessari', 'biscotto' ),
	'analytics'   => __( 'Analytics', 'biscotto' ),
	'marketing'   => __( 'Marketing', 'biscotto' ),
	'preferences' => __( 'Preferenze', 'biscotto' ),
);

/**
 * Stampa una riga della tabella cookie.
 */
function biscotto_cookie_row( $i, $row, $opt, $cats ) {
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
		<input type="text" id="biscotto-shortcode-copy" class="regular-text code" readonly="readonly" value="[biscotto_cookie_policy]" />
		<button type="button" class="button" id="biscotto-copy-shortcode"><?php esc_html_e( 'Copia', 'biscotto' ); ?></button>
		<span id="biscotto-copy-status" class="biscotto-scan-status" aria-live="polite"></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'In alternativa puoi comporre la pagina con gli shortcode singoli:', 'biscotto' ); ?>
		<code>[biscotto_cookie_table]</code> <?php esc_html_e( '(solo tabella cookie),', 'biscotto' ); ?>
		<code>[biscotto_consent_settings]</code> <?php esc_html_e( '(solo stato consenso + pulsante).', 'biscotto' ); ?>
	</p>
</div>

<table class="widefat striped biscotto-cookies">
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
		$biscotto_i = 0;
		foreach ( $biscotto_cookies as $biscotto_row ) {
			biscotto_cookie_row( $biscotto_i, $biscotto_row, $biscotto_opt, $biscotto_cats );
			$biscotto_i++;
		}
		// Riga template vuota (indice alto, ignorata se lasciata vuota dal sanitize).
		biscotto_cookie_row( 9000, array( 'category' => 'necessary' ), $biscotto_opt, $biscotto_cats );
		?>
	</tbody>
</table>

<p>
	<button type="button" class="button" id="biscotto-add-cookie"><?php esc_html_e( '+ Aggiungi cookie', 'biscotto' ); ?></button>
	<button type="button" class="button" id="biscotto-clear-cookies"><?php esc_html_e( 'Svuota registro', 'biscotto' ); ?></button>
	<span class="description"><?php esc_html_e( 'Dopo aver svuotato, ricordati di salvare.', 'biscotto' ); ?></span>
</p>
<?php // Il comportamento (aggiungi/rimuovi riga, svuota, copia shortcode) è in admin/js/cookies.js, accodato via wp_enqueue_script nel tab Cookie. ?>
