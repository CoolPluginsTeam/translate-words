<?php
/**
 * Ollama Cloud API client.
 *
 * @package Linguator
 */

namespace CoolPlugins\AI_Connectors\Ollama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Sends authenticated, non-streaming requests to Ollama Cloud.
 */
class Ollama_Client implements Ollama_Client_Interface {
	/**
	 * Ollama Cloud chat endpoint.
	 *
	 * The endpoint is intentionally fixed to prevent arbitrary server-side requests.
	 */
	private const CHAT_ENDPOINT = 'https://ollama.com/api/chat';

	/**
	 * Ollama Cloud models endpoint.
	 *
	 * @var string
	 */
	private const MODELS_ENDPOINT = 'https://ollama.com/api/tags';

	/**
	 * API key used to authenticate requests.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Creates an Ollama Cloud client.
	 *
	 * @param string $api_key Ollama Cloud API key.
	 * @param int    $timeout Optional request timeout in seconds.
	 */
	public function __construct( string $api_key, int $timeout = 120 ) {
		$this->api_key = (string) preg_replace( '/\s+/', '', $api_key );
		$this->timeout = min( 300, max( 1, $timeout ) );
	}

	/**
	 * Lists model identifiers available to the authenticated Ollama account.
	 *
	 * @return string[]|WP_Error Model identifiers or an error.
	 */
	public function list_models() {
		$api_key_error = $this->get_api_key_error();
		if ( is_wp_error( $api_key_error ) ) {
			return $api_key_error;
		}

		$response = wp_safe_remote_get(
			self::MODELS_ENDPOINT,
			array(
				'headers'     => $this->get_headers(),
				'timeout'     => $this->timeout,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->normalize_transport_error( $response );
		}

		$status      = (int) wp_remote_retrieve_response_code( $response );
		$decoded     = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$retry_after = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );

		if ( $status < 200 || $status >= 300 ) {
			return $this->create_http_error( $status, is_array( $decoded ) ? $decoded : array(), $retry_after );
		}

		if ( ! is_array( $decoded ) || ! isset( $decoded['models'] ) || ! is_array( $decoded['models'] ) ) {
			return new WP_Error(
				'ollama_invalid_models_response',
				__( 'Ollama returned an invalid model list.', 'translate-words' ),
				array( 'status' => 502 )
			);
		}

		$models = array();
		foreach ( $decoded['models'] as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}

			$model_id = '';
			if ( isset( $model['name'] ) && is_string( $model['name'] ) ) {
				$model_id = trim( $model['name'] );
			} elseif ( isset( $model['model'] ) && is_string( $model['model'] ) ) {
				$model_id = trim( $model['model'] );
			}

			if ( '' !== $model_id && preg_match( '/^[A-Za-z0-9._:\/-]+$/', $model_id ) ) {
				$models[] = $model_id;
			}
		}

		return array_values( array_unique( $models ) );
	}

	/**
	 * Sends a chat request to Ollama Cloud.
	 *
	 * @param Ollama_Request $request Request data.
	 * @return Ollama_Response|WP_Error Normalized response or an error.
	 */
	public function chat( Ollama_Request $request ) {
		$api_key_error = $this->get_api_key_error();
		if ( is_wp_error( $api_key_error ) ) {
			return $api_key_error;
		}

		$validation = $request->validate();
		if ( is_wp_error( $validation ) ) {
			$validation->add_data( array( 'status' => 400 ) );
			return $validation;
		}

		$body = wp_json_encode( $request->to_array() );
		if ( false === $body ) {
			return new WP_Error(
				'ollama_encode_error',
				__( 'The Ollama request could not be encoded.', 'translate-words' ),
				array( 'status' => 500 )
			);
		}

		$response = wp_safe_remote_post(
			self::CHAT_ENDPOINT,
			array(
				'headers'     => $this->get_headers(),
				'body'        => $body,
				'timeout'     => $this->timeout,
				'redirection' => 0,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->normalize_transport_error( $response );
		}

		return $this->normalize_response( $response );
	}

	/**
	 * Returns an error when the client has no API key.
	 *
	 * @return true|WP_Error True when configured, otherwise an error.
	 */
	private function get_api_key_error() {
		if ( '' !== $this->api_key ) {
			return true;
		}

		return new WP_Error(
			'ollama_missing_api_key',
			__( 'An Ollama API key is required.', 'translate-words' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Returns headers shared by Ollama Cloud requests.
	 *
	 * @return array<string,string> Request headers.
	 */
	private function get_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
	}

	/**
	 * Converts a WordPress HTTP transport failure to a stable connector error.
	 *
	 * @param WP_Error $error Transport error.
	 * @return WP_Error Normalized error.
	 */
	private function normalize_transport_error( WP_Error $error ): WP_Error {
		$message = strtolower( $error->get_error_message() );
		$timeout = false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'timeout' );

		return new WP_Error(
			$timeout ? 'ollama_timeout' : 'ollama_network_error',
			$timeout
				? __( 'The Ollama request timed out.', 'translate-words' )
				: __( 'The Ollama service could not be reached.', 'translate-words' ),
			array(
				'status'    => 503,
				'retryable' => true,
			)
		);
	}

	/**
	 * Converts an Ollama HTTP response to a response object or stable error.
	 *
	 * @param array<string,mixed> $response WordPress HTTP response.
	 * @return Ollama_Response|WP_Error Normalized response or error.
	 */
	private function normalize_response( array $response ) {
		$status      = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );
		$retry_after = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );

		if ( $status < 200 || $status >= 300 ) {
			return $this->create_http_error( $status, is_array( $decoded ) ? $decoded : array(), $retry_after );
		}

		if ( ! is_array( $decoded ) || ! isset( $decoded['message']['content'] ) || ! is_string( $decoded['message']['content'] ) ) {
			return new WP_Error(
				'ollama_invalid_response',
				__( 'Ollama returned an invalid response.', 'translate-words' ),
				array( 'status' => 502 )
			);
		}

		$metadata = $decoded;
		unset( $metadata['message'] );

		return new Ollama_Response(
			$decoded['message']['content'],
			isset( $decoded['model'] ) && is_string( $decoded['model'] ) ? $decoded['model'] : '',
			isset( $decoded['done_reason'] ) && is_string( $decoded['done_reason'] ) ? $decoded['done_reason'] : '',
			isset( $decoded['prompt_eval_count'] ) ? absint( $decoded['prompt_eval_count'] ) : 0,
			isset( $decoded['eval_count'] ) ? absint( $decoded['eval_count'] ) : 0,
			$metadata
		);
	}

	/**
	 * Creates a stable error from an unsuccessful Ollama response.
	 *
	 * @param int                 $status      HTTP status code.
	 * @param array<string,mixed> $body        Decoded response body.
	 * @param int                 $retry_after Retry delay in seconds.
	 * @return WP_Error Normalized error.
	 */
	private function create_http_error( int $status, array $body, int $retry_after ): WP_Error {
		$code      = 'ollama_request_failed';
		$message   = __( 'The Ollama request failed.', 'translate-words' );
		$retryable = false;

		if ( 401 === $status ) {
			$code    = 'ollama_authentication_failed';
			$message = __( 'The Ollama API key is invalid.', 'translate-words' );
		} elseif ( 403 === $status ) {
			$code    = 'ollama_forbidden';
			$message = __( 'The Ollama account is not permitted to perform this request.', 'translate-words' );
		} elseif ( 404 === $status ) {
			$code    = 'ollama_model_not_found';
			$message = __( 'The selected Ollama model was not found.', 'translate-words' );
		} elseif ( 429 === $status ) {
			$code      = 'ollama_rate_limited';
			$message   = __( 'The Ollama request limit has been reached. Please try again later.', 'translate-words' );
			$retryable = true;
		} elseif ( $status >= 500 ) {
			$code      = 'ollama_server_error';
			$message   = __( 'The Ollama service is temporarily unavailable.', 'translate-words' );
			$retryable = true;
		}

		$provider_message = '';
		if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
			$provider_message = sanitize_text_field( $body['error'] );
		} elseif ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
			$provider_message = sanitize_text_field( $body['message'] );
		}
		$provider_message = str_replace( $this->api_key, '[redacted]', $provider_message );

		return new WP_Error(
			$code,
			$message,
			array(
				'status'           => $status > 0 ? $status : 502,
				'retryable'        => $retryable,
				'retry_after'      => $retry_after,
				'provider_message' => $provider_message,
			)
		);
	}
}
