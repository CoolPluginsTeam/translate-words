<?php
/**
 * Translate Words Core Functionality
 * 
 * This file contains all the core functionality for the legacy Translate Words feature.
 * Only active for legacy users who had Translate Words before Linguator was integrated.
 *
 * @package tww
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Translate Words constants
define( 'TWW_TRANSLATIONS', 'tww_options' );
define( 'TWW_PAGE', 'tww_settings' );
define( 'TWW_TRANSLATIONS_LINES', 'tww_options_lines' );
define( 'TWW_NONCE_KEY', 'tww-save-translations' );
define( 'TWW_PLUGINS_DIR', plugin_dir_url( __FILE__ ) );


/**
 * Check if user is a legacy Translate Words user.
 * 
 * This function determines if the user had Translate Words functionality before.
 * New users will not have access to Translate Words, only Linguator.
 *
 * @return bool
 */
function tww_is_legacy_user() {
	$legacy_flag = get_option( 'tww_is_legacy_user' );
	
	// If flag doesn't exist, check if they have existing translations
	if ( false === $legacy_flag ) {
		$existing_translations = get_option( TWW_TRANSLATIONS_LINES );
		
		// If they have translations, they're a legacy user
		if ( ! empty( $existing_translations ) && is_array( $existing_translations ) ) {
			update_option( 'tww_is_legacy_user', 'yes' );
			return true;
		}
		
		// No translations found, mark as new user (not legacy)
		update_option( 'tww_is_legacy_user', 'no' );
		return false;
	}
	
	return 'yes' === $legacy_flag;
}

/**
 * Initialiaze the whole thing (Translate Words).
 * 
 * Only loads for legacy users. New users will only see Linguator functionality.
 *
 * @return void
 */
function tww_init() {

	// Only initialize Translate Words for legacy users
	if ( ! tww_is_legacy_user() ) {
		return;
	}

	/**
	 * Do translations.
	 * This works on frontend AND admin so that we can translate text everywhere.
	 */
	require_once 'frontend.php';

	// Admin screens.
	if ( is_admin() ) {

		require_once 'administration.php';

		add_filter(
			sprintf(
				'plugin_action_links_%1$s',
				plugin_basename( LINGUATOR_FILE )
			),
			'tww_add_plugin_actions'
		);

	}

}

/**
 * Add a link to the settings page to the plugin actions list.
 * Translate Words settings page.
 *
 * @param array $links The current list of links.
 * @return array
 */
function tww_add_plugin_actions( $links ) {

	$links[] = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( get_admin_url( null, 'options-general.php?page=' . TWW_PAGE ) ),
		esc_html__( 'Manage Translations', 'translate-words' )
	);

	return $links;

}

// Initialize Translate Words
tww_init();
