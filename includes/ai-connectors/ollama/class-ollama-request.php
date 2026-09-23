<?php
/**
 * Ollama Cloud request value object.
 *
 * @package Linguator
 */

namespace CoolPlugins\AI_Connectors\Ollama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Contains provider-neutral input for an Ollama chat request.
 */
class Ollama_Request {
	/**
	 * Model identifier.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Chat messages.
	 *
	 * @var array<int,array{role:string,content:string}>
	 */
	private $messages;

	/**
	 * Requested response format.
	 *
	 * @var string|array<string,mixed>|null
	 */
	private $format;

	/**
	 * Ollama generation options.
	 *
	 * @var array<string,mixed>
	 */
	private $options;

	/**
	 * Whether the model should generate a reasoning trace.
	 *
	 * @var bool|null
	 */
	private $think;

	/**
	 * Creates a request value object.
	 *
	 * @param string                          $model    Ollama model identifier.
	 * @param array<int,array<string,mixed>>  $messages Chat messages.
	 * @param string|array<string,mixed>|null $format   Optional response format or JSON schema.
	 * @param array<string,mixed>             $options  Optional Ollama generation options.
	 * @param bool|null                       $think    Optional reasoning mode override.
	 */
	public function __construct( string $model, array $messages, $format = null, array $options = array(), $think = null ) {
		$this->model    = trim( $model );
		$this->messages = $messages;
		$this->format   = $format;
		$this->options  = $options;
		$this->think    = is_bool( $think ) ? $think : null;
	}

	/**
	 * Validates the request without changing its content.
	 *
	 * @return true|WP_Error True when valid, otherwise an error.
	 */
	public function validate() {
		if ( '' === $this->model || ! preg_match( '/^[A-Za-z0-9._:\/-]+$/', $this->model ) ) {
			return new WP_Error(
				'ollama_invalid_model',
				__( 'The Ollama model identifier is invalid.', 'translate-words' )
			);
		}

		if ( empty( $this->messages ) ) {
			return new WP_Error(
				'ollama_invalid_messages',
				__( 'At least one message is required for the Ollama request.', 'translate-words' )
			);
		}

		foreach ( $this->messages as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['role'], $message['content'] ) ) {
				return new WP_Error(
					'ollama_invalid_messages',
					__( 'The Ollama request contains an invalid message.', 'translate-words' )
				);
			}

			if ( ! in_array( $message['role'], array( 'system', 'user', 'assistant' ), true ) || ! is_string( $message['content'] ) ) {
				return new WP_Error(
					'ollama_invalid_messages',
					__( 'The Ollama request contains an invalid message.', 'translate-words' )
				);
			}
		}

		if ( null !== $this->format && ! is_string( $this->format ) && ! is_array( $this->format ) ) {
			return new WP_Error(
				'ollama_invalid_format',
				__( 'The Ollama response format is invalid.', 'translate-words' )
			);
		}

		return true;
	}

	/**
	 * Converts the request to an Ollama API payload.
	 *
	 * @return array<string,mixed> API payload.
	 */
	public function to_array(): array {
		$payload = array(
			'model'    => $this->model,
			'messages' => $this->messages,
			'stream'   => false,
		);

		if ( null !== $this->format && '' !== $this->format ) {
			$payload['format'] = $this->format;
		}

		if ( ! empty( $this->options ) ) {
			$payload['options'] = $this->options;
		}

		if ( null !== $this->think ) {
			$payload['think'] = $this->think;
		}

		return $payload;
	}
}
