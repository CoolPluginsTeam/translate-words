<?php
/**
 * Run this directly to test the search and replace function.
 * This isn't used as part of the plugin apart from testing.
 */
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

	echo "String: $translated_string, Search: $search, Replace with: $replace, Expected: $expected<br>";
	echo "Result: $actual<br>";

	if ( $actual !== $expected ) {
		echo "<span style='color: red'>Test failed: $actual !== $expected</span>\n";
	} else {
		echo "<span style='color: green'>Test passed</span>\n";
	}

	echo "<br><br>";

}

function add_filter() {}