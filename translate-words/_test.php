<?php
/**
 * Run this directly to test the search and replace function.
 * This isn't used as part of the plugin apart from testing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
error_reporting( E_ALL );

include_once 'frontend.php';

do_test( 'Hello', 'Hello', 'Bonjour', 'Bonjour' );
do_test( 'Hello', 'hello', 'Bonjour', 'Bonjour' );
do_test( 'Hello', 'lo', 'Bonjour', 'Hello' );
do_test( 'HelloSir', 'Hello', 'Bonjour', 'HelloSir' );
do_test( 'SirHelloSir', 'Hello', 'Bonjour', 'SirHelloSir' );
do_test( 'Sir Hello Sir', 'Hello', 'Bonjour', 'Sir Bonjour Sir' );
do_test( 'Du buchst:', 'Du buchst:', 'Terminanfrage für', 'Terminanfrage für' );

/**
 * Run a test.
 *
 * @param string $translated_string The string being translated.
 * @param string $search            The search string.
 * @param string $replace           The replacement string.
 * @param string $expected          The expected result.
 * @return void
 */
function do_test( $translated_string, $search, $replace, $expected ) {

	$actual = tww_search_and_replace( $translated_string, [$search], [$replace, 'test'] );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo 'String: ' . htmlspecialchars( $translated_string, ENT_QUOTES, 'UTF-8' ) . ', Search: ' . htmlspecialchars( $search, ENT_QUOTES, 'UTF-8' ) . ', Replace with: ' . htmlspecialchars( $replace, ENT_QUOTES, 'UTF-8' ) . ', Expected: ' . htmlspecialchars( $expected, ENT_QUOTES, 'UTF-8' ) . '<br>';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo 'Result: ' . htmlspecialchars( $actual, ENT_QUOTES, 'UTF-8' ) . '<br>';

	if ( $actual !== $expected ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<span style='color: red'>Test failed: " . htmlspecialchars( $actual, ENT_QUOTES, 'UTF-8' ) . ' !== ' . htmlspecialchars( $expected, ENT_QUOTES, 'UTF-8' ) . "</span>\n";
	} else {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<span style='color: green'>Test passed</span>\n";
	}

	echo '<br><br>';

}

function add_filter() {}