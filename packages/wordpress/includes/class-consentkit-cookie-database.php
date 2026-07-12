<?php
/**
 * Open Cookie Database (vendored, Apache-2.0) — lookup usato per arricchire
 * i suggerimenti dello scanner quando il classificatore interno non
 * riconosce un cookie/dominio (§14.6). Nessuna chiamata esterna qui: solo
 * parsing locale on-demand del CSV vendored (vedi includes/data/NOTICE.md).
 *
 * @package ConsentKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConsentKit_Cookie_Database {

	/** Data dello snapshot vendored (deve combaciare con includes/data/NOTICE.md). */
	const SNAPSHOT_DATE = '2026-07-04';

	/** Repo GitHub del dataset upstream (usato dal controllo aggiornamenti). */
	const GITHUB_REPO = 'jkwakman/Open-Cookie-Database';

	/** Percorso del file nel repo upstream (per l'endpoint /commits). */
	const GITHUB_CSV_PATH = 'open-cookie-database.csv';

	/**
	 * Parsa il CSV in un indice per lookup: match esatto, prefissi wildcard, domini.
	 * Nessuna cache tra richieste: il parsing gira solo quando serve
	 * (azione admin esplicita), non ad ogni page-load frontend.
	 *
	 * @param string $csv_path Percorso del CSV.
	 * @return array{exact: array, wildcard: array, domain: array}
	 */
	public static function build_index( $csv_path ) {
		$csv = self::read_csv_file( $csv_path );
		if ( '' === $csv ) {
			return array(
				'exact'    => array(),
				'wildcard' => array(),
				'domain'   => array(),
			);
		}
		return self::build_index_from_string( $csv );
	}

	/**
	 * Legge il CSV bundlato tramite WP_Filesystem (niente fopen/fread diretti,
	 * cfr. linee guida WordPress.org). È un file locale incluso nel plugin:
	 * nessuna chiamata esterna. Ritorna stringa vuota se illeggibile.
	 *
	 * @param string $csv_path Percorso del CSV.
	 * @return string
	 */
	private static function read_csv_file( $csv_path ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			if ( ! defined( 'ABSPATH' ) || ! file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
				return '';
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) || ! $wp_filesystem->exists( $csv_path ) ) {
			return '';
		}

		$contents = $wp_filesystem->get_contents( $csv_path );
		return is_string( $contents ) ? $contents : '';
	}

	/**
	 * Costruisce l'indice a partire dal contenuto CSV già in memoria.
	 * Parsing puro (str_getcsv riga per riga), senza I/O su file: testabile
	 * in isolamento. Il dataset non contiene newline dentro i campi quotati,
	 * quindi lo split per righe è sicuro.
	 *
	 * @param string $csv Contenuto del CSV.
	 * @return array{exact: array, wildcard: array, domain: array}
	 */
	public static function build_index_from_string( $csv ) {
		$index = array(
			'exact'    => array(),
			'wildcard' => array(),
			'domain'   => array(),
		);

		$lines  = preg_split( '/\r\n|\r|\n/', (string) $csv );
		$header = null;

		foreach ( $lines as $line ) {
			if ( '' === $line ) {
				continue;
			}
			$cols = str_getcsv( $line );

			if ( null === $header ) {
				$header = $cols;
				continue;
			}
			if ( count( $cols ) !== count( $header ) ) {
				continue; // riga malformata, salta
			}
			$row = array_combine( $header, $cols );

			$entry = array(
				'service'    => isset( $row['Platform'] ) ? trim( (string) $row['Platform'] ) : '',
				'category'   => self::map_category( isset( $row['Category'] ) ? $row['Category'] : '' ),
				'duration'   => isset( $row['Retention period'] ) ? trim( (string) $row['Retention period'] ) : '',
				'url_policy' => isset( $row['User Privacy & GDPR Rights Portals'] ) ? trim( (string) $row['User Privacy & GDPR Rights Portals'] ) : '',
			);

			$name = isset( $row['Cookie / Data Key name'] ) ? trim( (string) $row['Cookie / Data Key name'] ) : '';
			if ( '' !== $name ) {
				$is_wildcard = isset( $row['Wildcard match'] ) && '1' === trim( (string) $row['Wildcard match'] );
				$key         = strtolower( $name );
				if ( $is_wildcard ) {
					if ( ! isset( $index['wildcard'][ $key ] ) ) {
						$index['wildcard'][ $key ] = $entry;
					}
				} elseif ( ! isset( $index['exact'][ $key ] ) ) {
						$index['exact'][ $key ] = $entry;
				}
			}

			$domain = isset( $row['Domain'] ) ? self::clean_domain( $row['Domain'] ) : '';
			if ( '' !== $domain && ! isset( $index['domain'][ $domain ] ) ) {
				$index['domain'][ $domain ] = $entry;
			}
		}

		return $index;
	}

	/**
	 * Cerca un cookie per nome: match esatto (case-insensitive), poi prefisso wildcard.
	 *
	 * @param string $name  Nome cookie.
	 * @param array  $index Indice da build_index().
	 * @return array|null
	 */
	public static function lookup_cookie( $name, $index ) {
		$key = strtolower( trim( (string) $name ) );
		if ( '' === $key ) {
			return null;
		}
		if ( isset( $index['exact'][ $key ] ) ) {
			return $index['exact'][ $key ];
		}
		foreach ( $index['wildcard'] as $prefix => $entry ) {
			if ( '' !== $prefix && 0 === strpos( $key, $prefix ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Cerca un dominio: match esatto o sottodominio di un dominio in indice.
	 *
	 * @param string $host  Host da cercare.
	 * @param array  $index Indice da build_index().
	 * @return array|null
	 */
	public static function lookup_domain( $host, $index ) {
		$host = strtolower( trim( (string) $host ) );
		if ( '' === $host ) {
			return null;
		}
		if ( isset( $index['domain'][ $host ] ) ) {
			return $index['domain'][ $host ];
		}
		foreach ( $index['domain'] as $domain => $entry ) {
			if ( self::ends_with( $host, '.' . $domain ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Mappa le 6 categorie di Open Cookie Database sulle 4 categorie Garante
	 * di ConsentKit. Sconosciuta/vuota -> 'necessary' (prudente, l'admin rivede).
	 *
	 * @param string $csv_category Categoria come scritta nel CSV.
	 * @return string
	 */
	public static function map_category( $csv_category ) {
		$map = array(
			'functional'      => 'necessary',
			'necessary'       => 'necessary',
			'security'        => 'necessary', // CSRF/anti-bot/reCAPTCHA: protezioni tecniche, art. 122 Codice Privacy
			'analytics'       => 'analytics',
			'marketing'       => 'marketing',
			'personalization' => 'preferences',
		);
		$key = strtolower( trim( (string) $csv_category ) );
		return isset( $map[ $key ] ) ? $map[ $key ] : 'necessary';
	}

	/**
	 * Ripulisce la colonna Domain dai suffissi descrittivi upstream
	 * (es. "cookiebot.com (3rd party)"). Voci malformate che non terminano
	 * con la parentesi restano invariate: finiscono in indice ma non
	 * corrispondono a nessun host reale, innocue.
	 *
	 * @param string $raw Valore grezzo della colonna Domain.
	 * @return string Dominio pulito, lowercase, o stringa vuota.
	 */
	private static function clean_domain( $raw ) {
		$domain = trim( (string) $raw );
		$domain = preg_replace( '/\s*\([^)]*\)\s*$/', '', $domain );
		return strtolower( trim( (string) $domain ) );
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	private static function ends_with( $haystack, $needle ) {
		$len = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}
}
