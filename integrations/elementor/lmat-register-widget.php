<?php
/**
 * Language Switcher Elementor Widget
 *
 * @package           Linguator
 * @wordpress-plugin
 */

namespace Linguator\Integrations\elementor;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Linguator_Register_Widget
 *
 * Handles the registration of custom Elementor widget.
 */
class Linguator_Register_Widget {

	/**
	 * Constructor
	 *
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'linguator_register_widgets' ) );
		// Register early so frontend can enqueue before Elementor prints assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'linguator_register_dropdown_script' ), 5 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'linguator_register_dropdown_script' ), 5 );
	}

	/**
	 * Register the frontend dropdown script (real file URL).
	 *
	 * @return void
	 */
	public function linguator_register_dropdown_script() {
		wp_register_script(
			'lmat-elementor-dropdown',
			plugins_url( 'integrations/elementor/js/dropdown.js', LINGUATOR_FILE ),
			array(),
			LINGUATOR_VERSION,
			true
		);
	}

	/**
	 * Register custom Elementor widgets
	 *
	 * @return void
	 */
	public function linguator_register_widgets() {
		require_once LINGUATOR_DIR . '/integrations/elementor/lmat-widget.php';
		\Elementor\Plugin::instance()->widgets_manager->register( new Linguator_Widget() );
	}
}

