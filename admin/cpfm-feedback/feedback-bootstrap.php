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

/**
 * Registers CPFM feedback notice hooks and CPFM classes.
 */
class Feedback_Bootstrap {

	/**
	 * Boot CPFM loader and register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->load_cpfm();

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_feedback_notices' ) );
			add_action( 'cpfm_after_opt_in_lmat', array( $this, 'after_opt_in' ) );
			add_action( 'add_option_cpfm_opt_in_choice_cool_translations', array( $this, 'sync_lmat_feedback_data_on_add' ), 10, 2 );
			add_action( 'update_option_cpfm_opt_in_choice_cool_translations', array( $this, 'sync_lmat_feedback_data_on_update' ), 10, 2 );
		}

		$this->register_usage_cron();
	}

	/**
	 * Load CPFM loader.
	 */
	private function load_cpfm() {
		if ( ! class_exists( 'CPFM_Loader' ) ) {
			$file = LINGUATOR_DIR . '/admin/cpfm-feedback/class-cpfm-loader.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		if ( class_exists( 'CPFM_Loader' ) ) {
			\CPFM_Loader::load();
		}
	}

	/**
	 * Register CPFM feedback notices, reviews, and deactivation feedback.
	 */
	public function register_feedback_notices() {
		// 1. Opt-in Notice Panel
		add_action( 'cpfm_register_notice', array( $this, 'register_cpfm_optin_notice' ) );

		// 2. Review Notice
		if ( class_exists( 'CPFM_Review' ) ) {
			$name = 'Linguator AI – Auto Translate & Create Multilingual Sites';

			\CPFM_Review::cpfm_register(
				array(
					'id'          => 'lmat',
					'plugin_file' => LINGUATOR_FILE,
					'plugin_name' => $name,
					'review_url'  => 'https://wordpress.org/support/plugin/translate-words/reviews/#new-post',
					'capability'  => 'activate_plugins',
					'quiet_days'  => 0,
					'own_screens' => array(
						'languages_page_lmat',
						'languages_page_lmat_settings',
						'toplevel_page_lmat',
					),
					'trigger'     => array(
						'type'  => 'install_age',
						'hours' => 0,
					),
					'notice'      => array(
						'enabled'        => true,
						'template'       => 'two_step',
						'screens'        => array(
							'plugins',
							'languages_page_lmat',
							'languages_page_lmat_settings',
							'toplevel_page_lmat',
						),
						'inline_screens' => array(),
					),
					'row'         => array( 'enabled' => true ),
					'legacy'      => array(
						'done_options'   => array(
							'lmat_review_prompt' => array( 'yes', 'done', 'dismissed' ),
							'lmat_review_shown'  => array( 'yes', 'done', 'dismissed' ),
						),
						'done_user_meta' => array(
							'lmat_review_dismissed' => array( '1', 'yes', 'true' ),
						),
					),
					'i18n'        => array(
						'dismiss_link'  => __( 'No thanks', 'translate-words' ),
						'later_link'    => __( 'Ask me later', 'translate-words' ),
						'submit_button' => __( 'Submit review', 'translate-words' ),
						'like_question' => __( 'Do you like the %s plugin?', 'translate-words' ),
						'yes_button'    => __( 'Yes, I like it', 'translate-words' ),
						'close_label'   => __( 'Close', 'translate-words' ),
						'direct_line'   => __( 'Enjoying %s? A short review really helps.', 'translate-words' ),
						'thanks_line'   => __( 'Great to hear! A quick review on WordPress.org would really help us.', 'translate-words' ),
						'no_link'       => __( 'I do not like it, dismiss', 'translate-words' ),
						'row_question'  => __( 'Do you like this plugin?', 'translate-words' ),
						'inline_title'  => __( 'Enjoying %s?', 'translate-words' ),
						'inline_text'   => __( 'A short review on WordPress.org helps other people find it.', 'translate-words' ),
					),
				)
			);
		}

		// 3. Deactivation Feedback
		if ( class_exists( 'CPFM_Deactivation_Feedback' ) ) {
			$name = 'Linguator AI – Auto Translate & Create Multilingual Sites';

			\CPFM_Deactivation_Feedback::cpfm_register(
				array(
					'id'                     => 'lmat',
					'slug'                   => 'translate-words',
					'plugin_name'            => $name,
					'version'                => LINGUATOR_VERSION,
					'api'                    => LINGUATOR_FEEDBACK_API,
					'site_key'               => '153',
					'install_date_option'    => 'lmat_install_date',
					'initial_version_option' => 'lmat_initial_version',
					'onboarding_data'        => 'linguator',
					'reasons'                => array(
						'not_working'  => array(
							'title'       => __( "The plugin isn't working", 'translate-words' ),
							'placeholder' => __( 'Which problem did you run into? We read every reply.', 'translate-words' ),
						),
						'not_expected' => array(
							'title'       => __( "It didn't do what I expected", 'translate-words' ),
							'placeholder' => __( 'What were you hoping it would do?', 'translate-words' ),
						),
						'found_better' => array(
							'title'       => __( 'I found a better plugin', 'translate-words' ),
							'placeholder' => __( 'Mind sharing which one?', 'translate-words' ),
						),
						'temporary'    => array(
							'title'       => __( "It's a temporary deactivation", 'translate-words' ),
							'placeholder' => '',
						),
						'other'        => array(
							'title'       => __( 'Another reason', 'translate-words' ),
							'placeholder' => __( 'Please tell us more', 'translate-words' ),
						),
					),
					'i18n'                   => array(
						'title'        => __( 'Before you go…', 'translate-words' ),
						/* translators: %s: plugin name (bold). */
						'intro'        => __( 'What made you deactivate %s? Your answer helps us fix it.', 'translate-words' ),
						'submit'       => __( 'Submit & Deactivate', 'translate-words' ),
						'skip'         => __( 'Skip & Deactivate', 'translate-words' ),
						'deactivating' => __( 'Deactivating…', 'translate-words' ),
						'pick_reason'  => __( 'Please choose a reason.', 'translate-words' ),
						'close_label'  => __( 'Close', 'translate-words' ),
						/* translators: %s: company name. */
						'byline'       => __( 'A plugin by %s', 'translate-words' ),
						'consent'      => __( 'Submitting shares your reason plus your site URL, admin email and basic environment details (PHP, WordPress, active plugins). Skip & Deactivate sends nothing.', 'translate-words' ),
					),
				)
			);
		}
	}

	/**
	 * Register the opt-in feedback notice.
	 */
	public function register_cpfm_optin_notice() {
		$notice = array(
			'title'          => __( 'Linguator AI by Cool Plugins', 'translate-words' ),
			'message'        => __( 'Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'translate-words' ),
			'pages'          => array( 'lmat', 'lmat_settings' ),
			'always_show_on' => array( 'lmat', 'lmat_settings' ),
			'plugin_name'    => 'lmat',
		);

		if ( class_exists( 'CPFM_Feedback_Notice' ) ) {
			\CPFM_Feedback_Notice::cpfm_register_notice( 'cool_translations', $notice );
		}
	}

	/**
	 * Register usage tracking cron.
	 */
	public function register_usage_cron() {
		if ( ! class_exists( 'CPFM_Usage_Cron' ) ) {
			return;
		}

		\CPFM_Usage_Cron::cpfm_register(
			array(
				'id'                     => 'lmat',
				'plugin_name'            => 'Linguator AI – Auto Translate & Create Multilingual Sites',
				'version'                => LINGUATOR_VERSION,
				'api'                    => LINGUATOR_FEEDBACK_API,
				'cron_hook'              => 'lmat_extra_data_update',
				'consent_master_option'  => 'cpfm_opt_in_choice_cool_translations',
				'consent_callback'       => array( $this, 'has_usage_tracking_consent' ),
				'install_date_option'    => 'lmat_install_date',
				'initial_version_option' => 'lmat_initial_version',
				'onboarding_data'        => 'linguator',
				'site_key'               => '153',
			)
		);
	}

	/**
	 * Check if the user consented to telemetry.
	 */
	public function has_usage_tracking_consent() {
		return 'yes' === get_option( 'cpfm_opt_in_choice_cool_translations' );
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

		if ( class_exists( 'CPFM_Usage_Cron' ) ) {
			\CPFM_Usage_Cron::cpfm_schedule_event( 'lmat_extra_data_update' );
			do_action( 'lmat_extra_data_update' );
		}
	}

	/**
	 * Sync `lmat_feedback_data` when the shared CPFM option is first added.
	 */
	public function sync_lmat_feedback_data_on_add( $option, $value ) {
		$this->sync_lmat_feedback_data( $value );
	}

	/**
	 * Sync `lmat_feedback_data` when the shared CPFM option is updated.
	 */
	public function sync_lmat_feedback_data_on_update( $old_value, $value ) {
		$this->sync_lmat_feedback_data( $value );
	}

	/**
	 * Keep `lmat_feedback_data` in sync with the shared CPFM opt-in choice.
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
