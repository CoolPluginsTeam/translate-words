<?php
/**
 * Registers the independent Ollama connector with WordPress's Connectors screen.
 *
 * @package Linguator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes Ollama available in Settings > Connectors on WordPress 7.0+.
 *
 * This is intentionally only a settings/UI integration. Translation requests
 * continue to use the independent CoolPlugins AI connector rather than the
 * WordPress AI Client.
 */
final class Linguator_Ollama_Connector_Registration {
	/** Connector identifier used by the WordPress Connectors API. */
	const CONNECTOR_ID = 'ollama';

	/** WordPress option shared with Linguator's translation settings. */
	const API_KEY_OPTION = 'connectors_ai_ollama_api_key';

	/**
	 * Registers hooks when the Connectors API is available.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_connectors_init', array( __CLASS__, 'register' ) );
		add_filter( 'script_module_data_options-connectors-wp-admin', array( __CLASS__, 'set_connection_status' ), 20 );
		add_filter( 'script_module_data_options-connectors', array( __CLASS__, 'set_connection_status' ), 20 );
	}

	/**
	 * Registers the Ollama connector card.
	 *
	 * @param WP_Connector_Registry $registry WordPress connector registry.
	 * @return void
	 */
	public static function register( $registry ) {
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) || ! method_exists( $registry, 'is_registered' ) ) {
			return;
		}

		if ( $registry->is_registered( self::CONNECTOR_ID ) ) {
			return;
		}

		$registry->register(
			self::CONNECTOR_ID,
			array(
				'name'           => 'Ollama',
				'description'    => __( 'Text translation with Ollama models.', 'translate-words' ),
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://ollama.com/settings/keys',
					'setting_name'    => self::API_KEY_OPTION,
					'constant_name'   => 'OLLAMA_API_KEY',
					'env_var_name'    => 'OLLAMA_API_KEY',
				),
				'plugin'         => array(
					'file'      => LINGUATOR_BASENAME,
					'is_active' => '__return_true',
				),
			)
		);
	}

	/**
	 * Reports connection state without registering Ollama in the WP AI Client.
	 *
	 * Core normally derives an AI provider's state from the WP AI Client. Ollama
	 * deliberately bypasses that client, so its state is derived from the shared
	 * connector key instead.
	 *
	 * @param array $data Connectors screen script-module data.
	 * @return array
	 */
	public static function set_connection_status( $data ) {
		if ( ! isset( $data['connectors'][ self::CONNECTOR_ID ]['authentication'] ) ) {
			return $data;
		}

		$api_key = trim( (string) get_option( self::API_KEY_OPTION, '' ) );
		$data['connectors'][ self::CONNECTOR_ID ]['authentication']['isConnected'] = '' !== $api_key;

		return $data;
	}
}

Linguator_Ollama_Connector_Registration::init();
