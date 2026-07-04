<?php
/**
 * Standalone test for ConsentKit_Cookie_Database — no WordPress bootstrap.
 * Run: php tests/test-cookie-database.php
 */

define( 'ABSPATH', __DIR__ . '/' ); // satisfies the class file's guard

require_once __DIR__ . '/../packages/wordpress/includes/class-consentkit-cookie-database.php';

$failures = 0;

function ck_assert( $cond, $label ) {
	global $failures;
	if ( $cond ) {
		echo "PASS: $label\n";
	} else {
		echo "FAIL: $label\n";
		$failures++;
	}
}

// --- Fixture CSV -----------------------------------------------------
$fixture = sys_get_temp_dir() . '/consentkit-test-ocd.csv';
$csv     = <<<CSV
ID,Platform,Category,Cookie / Data Key name,Domain,Description,Retention period,Data Controller,User Privacy & GDPR Rights Portals,Wildcard match
1,Test Analytics,Analytics,_ga_test,,Test analytics cookie,2 years,TestCo,https://example.com/privacy,0
2,Test Consent,Functional,TestConsentBulk-,,Bulk consent wildcard cookie,1 year,TestCo,https://example.com/privacy,1
3,Test Fonts,Personalization,,fonts.testcdn.com (3rd party),Test font domain,,TestCo,https://example.com/privacy,0
4,Test Security,Security,__test_csrf,,CSRF token,session,TestCo,https://example.com/privacy,0
5,Test Legacy,Necessary,legacy_session,,Legacy necessary cookie,session,TestCo,,0
CSV;
file_put_contents( $fixture, $csv );

$index = ConsentKit_Cookie_Database::build_index( $fixture );

// --- Exact cookie match ------------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '_ga_test', $index );
ck_assert( null !== $m, 'exact cookie match found' );
ck_assert( 'Test Analytics' === $m['service'], 'exact match service' );
ck_assert( 'analytics' === $m['category'], 'exact match category (Analytics -> analytics)' );
ck_assert( '2 years' === $m['duration'], 'exact match duration' );
ck_assert( 'https://example.com/privacy' === $m['url_policy'], 'exact match url_policy' );

// --- Wildcard cookie match (prefix) ------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'TestConsentBulk-XYZ123', $index );
ck_assert( null !== $m, 'wildcard cookie match found' );
ck_assert( 'necessary' === $m['category'], 'wildcard match category (Functional -> necessary)' );

// --- Case-insensitive match ---------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '_GA_TEST', $index );
ck_assert( null !== $m, 'cookie match is case-insensitive' );

// --- No match ------------------------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'totally_unknown_cookie', $index );
ck_assert( null === $m, 'unknown cookie returns null' );

// --- Domain match, with "(3rd party)" suffix cleaned --------------------
$m = ConsentKit_Cookie_Database::lookup_domain( 'fonts.testcdn.com', $index );
ck_assert( null !== $m, 'domain match found (suffix stripped)' );
ck_assert( 'preferences' === $m['category'], 'domain match category (Personalization -> preferences)' );

// --- Domain match via subdomain -----------------------------------------
$m = ConsentKit_Cookie_Database::lookup_domain( 'assets.fonts.testcdn.com', $index );
ck_assert( null !== $m, 'subdomain matches a bare domain entry' );

// --- Security -> necessary -----------------------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( '__test_csrf', $index );
ck_assert( null !== $m && 'necessary' === $m['category'], 'Security category maps to necessary' );

// --- Literal "Necessary" category value ---------------------------------
$m = ConsentKit_Cookie_Database::lookup_cookie( 'legacy_session', $index );
ck_assert( null !== $m && 'necessary' === $m['category'], 'literal "Necessary" category maps to necessary' );

// --- map_category direct ---------------------------------------------------
ck_assert( 'marketing' === ConsentKit_Cookie_Database::map_category( 'Marketing' ), 'map_category Marketing' );
ck_assert( 'necessary' === ConsentKit_Cookie_Database::map_category( 'Something Unknown' ), 'map_category unknown falls back to necessary' );

unlink( $fixture );

echo "\n" . ( $failures ? "$failures FAILING" : 'ALL PASSING' ) . "\n";
exit( $failures ? 1 : 0 );
