<?php
/**
 * Plugin Name: Translate Words
 * Description: Thanks to this plugin you can translate all the strings of your portal through the admin panel.
 * Version: 1.2.6
 * Author: Ben Gillbanks
 * Author URI: https://www.binarymoon.co.uk/
 * License: GPLv2 or later
 * Text Domain: translate-words
 *
 * @package tww
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWW_TRANSLATIONS', 'tww_options' );
define( 'TWW_PAGE', 'tww_settings' );
define( 'TWW_TRANSLATIONS_LINES', 'tww_options_lines' );
define( 'TWW_NONCE_KEY', 'tww-save-translations' );
define( 'TWW_PLUGINS_DIR', plugin_dir_url( __FILE__ ) );


/**
 * Initialiaze the whole thing.
 *
 * @return void
 */
function tww_init() {

	/**
	 * Do translations.
	 * This works on frontend AND admin so that we can translate text everywhere.
	 */
	require_once 'includes/frontend.php';

	// Admin screens.
	if ( is_admin() ) {

		require_once 'includes/administration.php';

		add_filter(
			sprintf(
				'plugin_action_links_%1$s',
				plugin_basename( __FILE__ )
			),
			'tww_add_plugin_actions'
		);

	}

}

tww_init();


/**
 * Add a link to the settings page to the plugin actions list.
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

