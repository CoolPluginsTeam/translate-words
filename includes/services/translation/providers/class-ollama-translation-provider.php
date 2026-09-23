<?php
/**
 * Linguator adapter for Ollama Cloud text generation.
 *
 * @package Linguator
 */

namespace Linguator\Includes\Services\Translation\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 3 ) . '/ai-connectors/ollama/load.php';
require_once __DIR__ . '/class-ollama-translation-models.php';

use CoolPlugins\AI_Connectors\Ollama\Ollama_Client;
use CoolPlugins\AI_Connectors\Ollama\Ollama_Client_Interface;
use CoolPlugins\AI_Connectors\Ollama\Ollama_Request;
use WP_Error;

/**
 * Adapts Linguator translation instructions to the reusable Ollama connector.
 */
class Ollama_Translation_Provider {
	/**
	 * Ollama connector client.
	 *
	 * @var Ollama_Client_Interface
	 */
	private $client;

	/**
	 * Ollama model identifier.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Creates the Linguator Ollama adapter.
	 *
	 * The optional client supports isolated tests without making live HTTP requests.
	 *
	 * @param string                       $api_key Ollama Cloud API key.
	 * @param string                       $model   Ollama model identifier.
	 * @param Ollama_Client_Interface|null $client  Optional connector client.
	 */
	public function __construct( string $api_key, string $model, $client = null ) {
		$this->client = $client instanceof Ollama_Client_Interface ? $client : new Ollama_Client( $api_key );
		$this->model  = trim( $model );
	}

	/**
	 * Returns approved models currently reported by the Ollama account.
	 *
	 * Models outside Linguator's fixed allowlist are intentionally discarded.
	 *
	 * @return array<string,array<string,mixed>>|WP_Error Available model metadata or an error.
	 */
	public function get_available_models() {
		$available_ids = $this->client->list_models();
		if ( is_wp_error( $available_ids ) ) {
			return $available_ids;
		}

		return array_intersect_key(
			Ollama_Translation_Models::all(),
			array_flip( $available_ids )
		);
	}

	/**
	 * Generates a structured translation response for a Linguator instruction.
	 *
	 * Parsing and validating the returned translation map remains the responsibility
	 * of Linguator's existing translation workflow.
	 *
	 * @param string            $instruction   Complete glossary-aware translation instruction.
	 * @param array<int|string> $response_keys Required translation response keys.
	 * @return string|WP_Error Generated JSON text or a normalized connector error.
	 */
	public function translate_instruction( string $instruction, array $response_keys = array() ) {
		if ( ! Ollama_Translation_Models::is_supported( $this->model ) ) {
			return new WP_Error(
				'lmat_ollama_unsupported_model',
				__( 'The selected Ollama model is not supported by Linguator.', 'translate-words' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === trim( $instruction ) ) {
			return new WP_Error(
				'lmat_ollama_empty_instruction',
				__( 'The Ollama translation instruction cannot be empty.', 'translate-words' ),
				array( 'status' => 400 )
			);
		}

		$format = 'json';
		if ( ! empty( $response_keys ) ) {
			$properties = new \stdClass();
			$required   = array();
			foreach ( $response_keys as $response_key ) {
				$key                = (string) $response_key;
				$properties->{$key} = array( 'type' => 'string' );
				$required[]         = $key;
			}
			$format = array(
				'type'                 => 'object',
				'properties'           => $properties,
				'required'             => $required,
				'additionalProperties' => false,
			);
		}

		$request = new Ollama_Request(
			$this->model,
			array(
				array(
					'role'    => 'system',
					'content' => 'You are a professional translator. Return only a valid JSON object.',
				),
				array(
					'role'    => 'user',
					'content' => $instruction . "\nOllama-specific HTML rule: Treat \\<tag> and \\</tag> sequences as transport wrappers. Translate their visible inner text, but omit those backslash-escaped wrapper tags from the returned value. Preserve genuine unescaped HTML tags and attributes.",
				),
			),
			$format,
			array( 'temperature' => 0 ),
			false
		);

		$response = $this->client->chat( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$finish_reason = strtolower( trim( $response->get_finish_reason() ) );
		$metadata      = $response->get_metadata();
		$is_done       = ! array_key_exists( 'done', $metadata ) || true === $metadata['done'];
		$was_truncated = in_array( $finish_reason, array( 'length', 'max_tokens', 'token_limit' ), true );

		if ( ! $is_done || $was_truncated ) {
			return new WP_Error(
				'lmat_ollama_output_truncated',
				__( 'Ollama stopped before completing the translation response. The batch will be retried in smaller parts.', 'translate-words' ),
				array(
					'status'            => 502,
					'retryable'         => true,
					'finish_reason'     => $finish_reason,
					'prompt_tokens'     => $response->get_prompt_tokens(),
					'completion_tokens' => $response->get_completion_tokens(),
				)
			);
		}

		return $response->get_text();
	}
}
