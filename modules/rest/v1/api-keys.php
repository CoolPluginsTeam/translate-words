<?php
/**
 * @package Linguator
 */

namespace Linguator\Modules\REST\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Includes\Other\Linguator_Model;
use Linguator\Modules\REST\Abstract_Controller;
use WP_Error;
use WP_REST_Server;

/**
 * API Keys REST controller.
 *
 * Stores provider keys in dedicated WP options:
 * - connectors_ai_openai_key
 * - connectors_ai_gemini_key
 * - connectors_ai_anthropic_key
 *
 * Stores selected models in Linguator option `api_keys` via Options registry.
 */
class Api_Keys extends Abstract_Controller {
	/**
	 * @var Linguator_Model
	 */
	protected $model;

	protected $namespace;
	protected $rest_base;

	public function __construct( Linguator_Model $model ) {
		$this->namespace = 'lmat/v1';
		$this->rest_base = 'api-keys';
		$this->model     = $model;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'keys' => array(
							'required' => false,
							'type'     => 'object',
						),
						'models' => array(
							'required' => false,
							'type'     => 'object',
						),
					),
				),
			)
		);
	}

	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view options.', 'translate-words' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit options.', 'translate-words' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		return true;
	}

	private function option_name_for_provider( string $provider ): string {
		return 'connectors_ai_' . strtolower( $provider ) . '_key';
	}

	private function mask_key( string $raw ): string {
		if ( '' === $raw ) {
			return '';
		}
		$tail = substr( $raw, -4 );
		return '••••••••' . $tail;
	}

	public function get_item( $request ) {
		$providers = array( 'openai', 'gemini', 'anthropic' );
		$keys      = array();

		foreach ( $providers as $provider ) {
			$raw          = (string) get_option( $this->option_name_for_provider( $provider ), '' );
			$keys[ $provider ] = $this->mask_key( $raw );
		}

		$models = $this->model->options->get( 'api_keys' );
		if ( ! is_array( $models ) ) {
			$models = array();
		}

		return rest_ensure_response(
			array(
				'keys'   => $keys,
				'models' => $models,
			)
		);
	}

	public function update_item( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$providers = array( 'openai', 'gemini', 'anthropic' );

		// Save keys to dedicated WP options.
		$incoming_keys = isset( $params['keys'] ) && is_array( $params['keys'] ) ? $params['keys'] : array();
		foreach ( $providers as $provider ) {
			if ( ! array_key_exists( $provider, $incoming_keys ) ) {
				continue;
			}
			$v = $incoming_keys[ $provider ];
			$v = is_string( $v ) ? trim( $v ) : '';
			update_option( $this->option_name_for_provider( $provider ), $v );
		}

		// Save models into Linguator option `api_keys`.
		$incoming_models = isset( $params['models'] ) && is_array( $params['models'] ) ? $params['models'] : array();
		if ( ! empty( $incoming_models ) ) {
			$this->model->options->set( 'api_keys', $incoming_models );
		}

		return $this->get_item( $request );
	}
}

