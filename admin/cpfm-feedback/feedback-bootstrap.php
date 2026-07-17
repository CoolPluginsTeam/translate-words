<?php
/**
 * CPFM feedback notice bootstrap for Linguator.
 *
 * @package Linguator
 */

namespace Linguator\Admin\cpfm_feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Admin\cpfm_feedback\cron\Linguator_cronjob;

/**
 * Registers CPFM feedback notice hooks with named callbacks.
 */
class Feedback_Bootstrap {

	/**
	 * Notice instance when this plugin boots CPFM locally.
	 *
	 * @var CPFM_Feedback_Notice|null
	 */
	private $notice = null;

	/**
	 * Boot notice (if needed) and register hooks.
	 *
	 * @return CPFM_Feedback_Notice|null Notice instance when created here, otherwise null.
	 */
	public function register_hooks() {
		if ( ! is_admin() ) {
			return null;
		}

		$this->maybe_boot_notice();

		add_action( 'cpfm_register_notice', array( $this, 'register_notice' ) );
		add_action( 'cpfm_after_opt_in_lmat', array( $this, 'after_opt_in' ) );
		add_action( 'add_option_cpfm_opt_in_choice_cool_translations', array( $this, 'sync_lmat_feedback_data_on_add' ), 10, 2 );
		add_action( 'update_option_cpfm_opt_in_choice_cool_translations', array( $this, 'sync_lmat_feedback_data_on_update' ), 10, 2 );

		return $this->notice;
	}

	/**
	 * Prefer an existing global CPFM_Feedback_Notice; otherwise boot ours and alias it.
	 *
	 * @return void
	 */
	private function maybe_boot_notice() {
		if ( class_exists( 'CPFM_Feedback_Notice', false ) ) {
			return;
		}

		$this->notice = new CPFM_Feedback_Notice();

		if ( ! class_exists( 'CPFM_Feedback_Notice', false ) ) {
			class_alias( CPFM_Feedback_Notice::class, 'CPFM_Feedback_Notice' );
		}
	}

	/**
	 * Register Linguator notice with the shared CPFM feedback system.
	 *
	 * @return void
	 */
	public function register_notice() {
		if ( ! class_exists( 'CPFM_Feedback_Notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = array(
			'title'          => __( 'Linguator AI – Auto Translate & Create Multilingual Sites', 'translate-words' ),
			'message'        => __( 'Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'translate-words' ),
			'pages'          => array( 'lmat_settings' ),
			'always_show_on' => array( 'lmat_settings' ),
			'plugin_name'    => 'lmat',
		);

		\CPFM_Feedback_Notice::cpfm_register_notice( 'cool_translations', $notice );

		if ( ! isset( $GLOBALS['cool_plugins_feedback'] ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$GLOBALS['cool_plugins_feedback'] = array();
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$GLOBALS['cool_plugins_feedback']['cool_translations'][] = $notice;
	}

	/**
	 * Persist opt-in and send telemetry after CPFM opt-in for Linguator.
	 *
	 * @param string $category CPFM category key.
	 * @return void
	 */
	public function after_opt_in( $category ) {
		if ( 'cool_translations' !== $category ) {
			return;
		}

		$option = get_option( 'linguator' );
		if ( is_array( $option ) ) {
			$option['lmat_feedback_data'] = true;
			update_option( 'linguator', $option );
		}

		Linguator_cronjob::linguator_send_data();
	}

	/**
	 * Sync `lmat_feedback_data` when the shared CPFM option is first added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function sync_lmat_feedback_data_on_add( $option, $value ) {
		$this->sync_lmat_feedback_data( $value );
	}

	/**
	 * Sync `lmat_feedback_data` when the shared CPFM option is updated.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function sync_lmat_feedback_data_on_update( $old_value, $value ) {
		$this->sync_lmat_feedback_data( $value );
	}

	/**
	 * Keep `lmat_feedback_data` in sync with the shared CPFM opt-in choice (including first "No").
	 *
	 * @param mixed $value Opt-in value (`yes` / `no`).
	 * @return void
	 */
	public function sync_lmat_feedback_data( $value ) {
		$option = get_option( 'linguator' );
		if ( ! is_array( $option ) ) {
			return;
		}

		$option['lmat_feedback_data'] = ( 'yes' === $value );
		update_option( 'linguator', $option );
	}
}
