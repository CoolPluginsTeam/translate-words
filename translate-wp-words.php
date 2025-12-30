<?php
/**
 * Plugin Name:       Linguator AI – Auto Translate & Create Multilingual Sites
 * Plugin URI:        https://linguator.com/
 * Description:       Create a multilingual WordPress website in minutes with Linguator AI – Auto Translate & Create Multilingual Sites.
 * Version:           2.0.0
 * Requires at least: 6.8
 * Requires PHP:      7.2
 * Author:            Cool Plugins
 * Author URI:        https://coolplugins.net/
 * Text Domain:       linguator-multilingual-ai-translation
 * License:           GPL2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Includes\Core\Linguator;
use Linguator\Install\LMAT_Activate;
use Linguator\Install\LMAT_Deactivate;
use Linguator\Install\LMAT_Usable;



// Linguator constants - wrapped in checks to prevent redeclaration
if ( ! defined( 'LINGUATOR_VERSION' ) ) {
	define( 'LINGUATOR_VERSION', '2.0.0' );
}
if ( ! defined( 'LMAT_MIN_WP_VERSION' ) ) {
	define( 'LMAT_MIN_WP_VERSION', '6.8' );
}
if ( ! defined( 'LMAT_MIN_PHP_VERSION' ) ) {
	define( 'LMAT_MIN_PHP_VERSION', '7.2' );
}
if ( ! defined( 'LINGUATOR_FILE' ) ) {
	define( 'LINGUATOR_FILE', __FILE__ );
}
if ( ! defined( 'LINGUATOR_DIR' ) ) {
	define( 'LINGUATOR_DIR', __DIR__ );
}
if ( ! defined( 'LINGUATOR_URL' ) ) {
	define( 'LINGUATOR_URL', plugin_dir_url( LINGUATOR_FILE ) );
}
if ( ! defined( 'LINGUATOR_FEEDBACK_API' ) ) {
	define( 'LINGUATOR_FEEDBACK_API', 'https://feedback.coolplugins.net/' );
}
// Whether we are using Linguator, get the filename of the plugin in use.
if ( ! defined( 'LINGUATOR_ROOT_FILE' ) ) {
	define( 'LINGUATOR_ROOT_FILE', __FILE__ );
}

if ( ! defined( 'LINGUATOR_BASENAME' ) ) {
	define( 'LINGUATOR_BASENAME', plugin_basename( __FILE__ ) ); // Plugin name as known by WP.
	require __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'LINGUATOR' ) ) {
	define( 'LINGUATOR', ucwords( str_replace( '-', ' ', dirname( LINGUATOR_BASENAME ) ) ) );
}

// Initialize the plugin
if ( ! empty( $_GET['deactivate-linguator'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	return;
}


// Load legacy Translate Words functionality only for legacy users
add_action( 'init', function() {
	// Check if user has legacy Translate Words data
	$legacy_flag = get_option( 'tww_is_legacy_user' );
	
	// If flag doesn't exist, check if they have existing translations
	if ( false === $legacy_flag ) {
		$existing_translations = get_option( 'tww_options_lines' );
		
		// If they have translations, they're a legacy user
		if ( ! empty( $existing_translations ) && is_array( $existing_translations ) ) {
			update_option( 'tww_is_legacy_user', 'yes' );
			$legacy_flag = 'yes';
		} else {
			// No translations found, mark as new user (not legacy)
			update_option( 'tww_is_legacy_user', 'no' );
			$legacy_flag = 'no';
		}
	}
	
	// Only load Translate Words files for legacy users
	if ( 'yes' === $legacy_flag ) {
		require_once __DIR__ . '/translate-words/tww.php';
	}
}, 1 );



// Display admin notice when linguator plugin is deactivated
add_action('admin_notices', function() {
    $linguator_plugin = 'linguator-multilingual-ai-translation/linguator-multilingual-ai-translation.php';
        if ( is_plugin_active( $linguator_plugin ) ) {
            deactivate_plugins( $linguator_plugin );
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                <?php
                printf(
                    /* translators: %1$s: link to Linguator plugin, %2$s: link to Translate Words plugin */
                    wp_kses_post( __( 'The <a href="%1$s" target="_blank">Linguator – Multilingual AI Translation</a> plugin has been automatically deactivated because all its functionality is now available in <a href="%2$s" target="_blank">Linguator AI – Auto Translate & Create Multilingual Sites</a>.', 'linguator-multilingual-ai-translation' ) ),
                    esc_url( 'https://wordpress.org/plugins/linguator-multilingual-ai-translation/' ),
                    esc_url( 'https://wordpress.org/plugins/translate-words/' )
                );
                ?>
                </p>
            </div>
            <?php
        }
});

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

if ( ! function_exists( 'lmat_has_constant' ) ) {
	require __DIR__ . '/includes/helpers/constant-functions.php';
}
if ( ! LMAT_Usable::can_activate() ) {
	// WP version or php version is too old.
	return;
}

if ( ! defined( 'LMAT_ACTIVE' ) ) {
	define( 'LMAT_ACTIVE', true );
}

if ( LMAT_Deactivate::is_deactivation() ) {
	// Stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules).
	LMAT_Deactivate::add_hooks();
	return;
}

LMAT_Activate::add_hooks();

new Linguator();

