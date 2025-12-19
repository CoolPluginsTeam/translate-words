<?php
/**
 * Translates strings on the frontend.
 *
 * @package tww
 */

/**
 * Process the translated text.
 *
 * @param string $translated_string The string being translated.
 * @return string
 */
/**
 * Process the translated text.
 *
 * @param string $translated_string The string being translated.
 * @return string
 */
function tww_apply_translate_string( $translated_string ) {

	static $cached_overrides = null;

	// Check if overrides are already cached.
	if ( is_null( $cached_overrides ) ) {
		// Retrieve overrides from option if not already cached.
		$cached_overrides = get_option( TWW_TRANSLATIONS_LINES );
	}

	// Check if overrides are not an array or empty.
	if ( ! is_array( $cached_overrides ) || empty( $cached_overrides ) ) {
		// Return original string if overrides are not available.
		return $translated_string;
	}

	// Extract keys and replace arrays from cached overrides.
	$keys = array_column( $cached_overrides, 'original' );
	$replace = array_column( $cached_overrides, 'overwrite' );

	// Perform search and replace using cached keys and replace arrays.
	return tww_search_and_replace( $translated_string, $keys, $replace );

}

add_filter( 'gettext', 'tww_apply_translate_string', 20 );
add_filter( 'ngettext', 'tww_apply_translate_string', 20 );


/**
 * Search and replace the translated string.
 *
 * @param string $translated_string  The string being translated.
 * @param array  $keys               The keys to search for.
 * @param array  $replace            The replacements.
 * @return string
 */
function tww_search_and_replace( $translated_string, $keys, $replace ) {

	/**
	 * We perform two replacements here: first case-sensitive, then case-insensitive.
	 *
	 * The reason for this two-step process is to handle scenarios where we have
	 * translations that are identical except for their case. By doing a case-sensitive
	 * replacement first, we ensure that these translations are correctly replaced
	 * with their exact matches.
	 *
	 * After the case-sensitive replacement, we perform a case-insensitive replacement
	 * to catch any remaining translations that were not matched in the first step due
	 * to case differences.
	 *
	 * This process ensures that we prioritize exact matches while still providing
	 * a fallback for any remaining matches, regardless of case.
	 *
	 * It's important to maintain this two-step process to handle all possible
	 * translation scenarios correctly. Therefore, do not remove or alter this
	 * replacement strategy without a thorough understanding of its implications.
	 */

	/**
	 * Do a case sensitive replacement.
	 * This covers instances where there are two translations that are the same,
	 * but with different cases.
	 */
	$translated_string = tww_do_search_and_replace( $translated_string, $keys, $replace );

	/**
	 * Do a case insensitive replacement.
	 * This picks up instances where case doesn't matter, and works for
	 * backwards compatability.
	 */
	$translated_string = tww_do_search_and_replace( $translated_string, $keys, $replace, 'i' );

	return $translated_string;

}


/**
 * Perform the search and replace.
 *
 * @param string $translated_string The string being translated.
 * @param array  $keys              The keys to search for.
 * @param array  $replace           The replacements.
 * @param bool   $case_sensitive    Whether the search should be case sensitive.
 * @return string
 */
function tww_do_search_and_replace( $translated_string, $keys, $replace, $modifier = '' ) {

	$search_keys = array_map(
		function( $key ) use ( $modifier ) {
			return '/(^|\s+)' . preg_quote( $key, '/') . '($|\s+)/' . $modifier;
		},
		$keys
	);

	return preg_replace_callback(
		$search_keys,
		function( $matches ) use ( $keys, $replace ) {

			// Find the index of the matched search key
			$index = array_search(
				strtolower( trim( $matches[0] ) ),
				array_map( 'strtolower', $keys )
			);

			// If the index is valid and the replacement exists, return the replacement with preserved spaces
			if ( $index !== false && isset( $replace[$index] ) ) {
				return $matches[1] . $replace[$index] . $matches[2];
			}

			// Otherwise, return the original matched string
			return $matches[0];
		},
		$translated_string
	);

}