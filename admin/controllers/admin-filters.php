<?php
/**
 * @package Linguator
 */
namespace Linguator\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Linguator\Includes\Filters\Linguator_Filters;
use Linguator\Includes\Other\Linguator_Language;



/**
 * Setup miscellaneous admin filters as well as filters common to admin and frontend
 *
 *  
 */
class Linguator_Admin_Filters extends Linguator_Filters {

	/**
	 * Constructor: setups filters and actions.
	 *
	 *  
	 *
	 * @param object $linguator The Linguator object.
	 */
	public function __construct( &$linguator ) {
		parent::__construct( $linguator );

		// Language management for users
		add_action( 'personal_options_update', array( $this, 'linguator_personal_options_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'linguator_personal_options_update' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'linguator_enqueue_user_profile_scripts' ) );

		// Upgrades plugins and themes translations files
		add_filter( 'themes_update_check_locales', array( $this, 'linguator_update_check_locales' ) );
		add_filter( 'plugins_update_check_locales', array( $this, 'linguator_update_check_locales' ) );

		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Add post state for translations of the privacy policy page
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 10, 2 );
	}

	/**
	 * Updates the user biographies.
	 *
	 *  
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function linguator_personal_options_update( $user_id ) {
		// Biography translations
		foreach ( $this->model->get_languages_list() as $lang ) {
			$meta        = $lang->is_default ? 'description' : 'description_' . $lang->slug;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress core handles nonce verification for linguator_personal_options_update, sanitized below with sanitize_textarea_field
			$description = ! empty( $_POST[ 'description_' . $lang->slug ] ) ? sanitize_textarea_field( trim( wp_unslash( $_POST[ 'description_' . $lang->slug ] ) ) ) : '';

			/** This filter is documented in wp-includes/user.php */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$description = apply_filters( 'pre_user_description', $description ); // Applies WP default filter wp_filter_kses
			update_user_meta( $user_id, $meta, $description );
		}
	}

	/**
	 * Enqueues scripts for multilingual user biography fields on profile screens.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function linguator_enqueue_user_profile_scripts( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		if ( ! $this->model->has_languages() ) {
			return;
		}

		$user_id = 'profile.php' === $hook_suffix ? get_current_user_id() : ( isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core admin screen context.

		if ( ! $user_id ) {
			return;
		}

		$profileuser = get_userdata( $user_id );
		if ( ! $profileuser instanceof \WP_User ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$data   = array();

		wp_enqueue_script(
			'lmat_user',
			plugins_url( "admin/assets/js/build/user{$suffix}.js", LINGUATOR_ROOT_FILE ),
			array(),
			LINGUATOR_VERSION,
			true
		);

		foreach ( $this->model->get_languages_list() as $lang ) {
			$meta        = $lang->is_default ? 'description' : 'description_' . $lang->slug;
			$description = get_user_meta( $profileuser->ID, $meta, true );
			$description = is_string( $description ) ? $description : '';

			$data[] = array(
				'slug'        => $lang->slug,
				'name'        => $lang->name,
				'lang'        => $lang->get_locale( 'display' ),
				'direction'   => $lang->is_rtl ? 'rtl' : 'ltr',
				'flag'        => Linguator_Language::get_flag_information( $lang->flag_code ),
				'description' => sanitize_user_field( 'description', $description, $profileuser->ID, 'edit' ),
			);
		}

		wp_add_inline_script(
			'lmat_user',
			'const linguatorDescriptionData = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Allows to update translations files for plugins and themes.
	 *
	 *  
	 *
	 * @param string[] $locales List of locales to update for plugins and themes.
	 * @return string[]
	 */
	public function linguator_update_check_locales( $locales ) {
		return array_merge( $locales, $this->model->get_languages_list( array( 'fields' => 'locale' ) ) );
	}

	/**
	 * Adds custom classes to the body
	 *
	 *   Adds a text direction dependent class to the body.
	 *   Adds a language dependent class to the body.
	 *
	 * @param string $classes Space-separated list of CSS classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! empty( $this->curlang ) ) {
			$classes .= ' lmat-dir-' . ( $this->curlang->is_rtl ? 'rtl' : 'ltr' );
			$classes .= ' lmat-lang-' . $this->curlang->slug;
		}
		return $classes;
	}

	/**
	 * Adds post state for translations of the privacy policy page.
	 *
	 *  
	 *
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        The current post object.
	 * @return string[]
	 */
	public function display_post_states( $post_states, $post ) {
		$page_for_privacy_policy = get_option( 'wp_page_for_privacy_policy' );

		if ( $page_for_privacy_policy && in_array( $post->ID, $this->model->post->get_translations( $page_for_privacy_policy ) ) ) {
			$post_states['page_for_privacy_policy'] = __( 'Privacy Policy Page', 'translate-words' );
		}

		return $post_states;
	}
}
