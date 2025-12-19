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



use Linguator\Includes\Core\Linguator;
use Linguator\Install\LMAT_Activate;
use Linguator\Install\LMAT_Deactivate;
use Linguator\Install\LMAT_Usable;



// Linguator constants
define( 'LINGUATOR_VERSION', '1.0.2' );
define( 'LMAT_MIN_WP_VERSION', '6.2' );
define( 'LMAT_MIN_PHP_VERSION', '7.2' );
define( 'LINGUATOR_FILE', __FILE__ ); 
define( 'LINGUATOR_DIR', __DIR__ );
define('LINGUATOR_URL', plugin_dir_url(LINGUATOR_FILE));
define( 'LINGUATOR_FEEDBACK_API', 'https://feedback.coolplugins.net/' );
// Translate Words constants
define( 'TWW_TRANSLATIONS', 'tww_options' );
define( 'TWW_PAGE', 'tww_settings' );
define( 'TWW_TRANSLATIONS_LINES', 'tww_options_lines' );
define( 'TWW_NONCE_KEY', 'tww-save-translations' );
define( 'TWW_PLUGINS_DIR', plugin_dir_url( __FILE__ ) );


// Whether we are using Linguator, get the filename of the plugin in use.
if ( ! defined( 'LINGUATOR_ROOT_FILE' ) ) {
	define( 'LINGUATOR_ROOT_FILE', __FILE__ );
}

if ( ! defined( 'LINGUATOR_BASENAME' ) ) {
	define( 'LINGUATOR_BASENAME', plugin_basename( __FILE__ ) ); // Plugin name as known by WP.
	require __DIR__ . '/vendor/autoload.php';
}

define( 'LINGUATOR', ucwords( str_replace( '-', ' ', dirname( LINGUATOR_BASENAME ) ) ) );

// Initialize the plugin
if ( ! empty( $_GET['deactivate-linguator'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	return;
}



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
	require_once 'translate-words/frontend.php';

	// Admin screens.
	if ( is_admin() ) {

		require_once 'translate-words/administration.php';

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



// Linguator
// Handle redirect after activation and language switcher visibility
add_action('admin_init', function() {
	// Don't redirect to wizard if Polylang is detected
	if ( defined( 'POLYLANG_VERSION' ) ) {
		return;
	}
	
	// Only check setup flag on plugins page to avoid unnecessary database queries
	$is_plugins_page = false;
	if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'plugins.php' ) !== false ) {
		$is_plugins_page = true;
	}
	// Only run on plugins page
	if ( $is_plugins_page ) {
		// Only proceed if we need setup and are in admin
		if (get_option('lmat_needs_setup') === 'yes' && is_admin()) {
			if (!is_network_admin() && !isset($_GET['activate-multi'])) {
				// Remove the setup flag
				delete_option('lmat_needs_setup');
				// Redirect to the setup wizard
				wp_safe_redirect(admin_url('admin.php?page=lmat_wizard'));
				exit;
			}
		}
	}
	
	// Ensure language switcher is visible on nav-menus page for new installations
	$install_date = get_option('lmat_install_date');
	
	if ($install_date) {
		// Check if this is a recent installation (within last 24 hours)
		$install_timestamp = strtotime($install_date);
		$time_since_install = time() - $install_timestamp;
		
		// If installed within last 24 hours, ensure language switcher is visible
		if ($time_since_install <= 86400) {
			// Hook into nav-menus page load
			add_action('load-nav-menus.php', function() {
				$user_id = get_current_user_id();
				if (!$user_id) {
					return;
				}
				
				// Get hidden meta boxes for current user
				$hidden_meta_boxes = get_user_meta($user_id, 'metaboxhidden_nav-menus', true);
				
				// Initialize as empty array if not set
				if (!is_array($hidden_meta_boxes)) {
					$hidden_meta_boxes = array();
				}
				
				// Remove language switcher from hidden meta boxes to make it visible
				$hidden_meta_boxes = array_diff($hidden_meta_boxes, array('lmat_lang_switch_box'));
				
				// Update user meta
				update_user_meta($user_id, 'metaboxhidden_nav-menus', $hidden_meta_boxes);
			});
		}
	}
});

require __DIR__ . '/includes/helpers/constant-functions.php';
if ( ! LMAT_Usable::can_activate() ) {
	// WP version or php version is too old.
	return;
}

define( 'LMAT_ACTIVE', true );

if ( LMAT_Deactivate::is_deactivation() ) {
	// Stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules).
	LMAT_Deactivate::add_hooks();
	return;
}

LMAT_Activate::add_hooks();

new Linguator();

