<?php
/**
 * Normalized Ollama Cloud response.
 *
 * @package Linguator
 */

namespace CoolPlugins\AI_Connectors\Ollama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides stable access to data returned by Ollama Cloud.
 */
class Ollama_Response {
	/**
	 * Generated text.
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Model identifier.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Completion reason.
	 *
	 * @var string
	 */
	private $finish_reason;

	/**
	 * Number of prompt tokens.
	 *
	 * @var int
	 */
	private $prompt_tokens;

	/**
	 * Number of generated tokens.
	 *
	 * @var int
	 */
	private $completion_tokens;

	/**
	 * Additional provider response metadata.
	 *
	 * @var array<string,mixed>
	 */
	private $metadata;

	/**
	 * Creates a normalized response.
	 *
	 * @param string              $text              Generated text.
	 * @param string              $model             Model identifier.
	 * @param string              $finish_reason     Completion reason.
	 * @param int                 $prompt_tokens     Prompt token count.
	 * @param int                 $completion_tokens Completion token count.
	 * @param array<string,mixed> $metadata          Additional response metadata.
	 */
	public function __construct( string $text, string $model, string $finish_reason, int $prompt_tokens, int $completion_tokens, array $metadata = array() ) {
		$this->text              = $text;
		$this->model             = $model;
		$this->finish_reason     = $finish_reason;
		$this->prompt_tokens     = $prompt_tokens;
		$this->completion_tokens = $completion_tokens;
		$this->metadata          = $metadata;
	}

	/**
	 * Gets the generated text.
	 *
	 * @return string Generated text.
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Gets the model identifier.
	 *
	 * @return string Model identifier.
	 */
	public function get_model(): string {
		return $this->model;
	}

	/**
	 * Gets the completion reason.
	 *
	 * @return string Completion reason.
	 */
	public function get_finish_reason(): string {
		return $this->finish_reason;
	}

	/**
	 * Gets the prompt token count.
	 *
	 * @return int Prompt token count.
	 */
	public function get_prompt_tokens(): int {
		return $this->prompt_tokens;
	}

	/**
	 * Gets the completion token count.
	 *
	 * @return int Completion token count.
	 */
	public function get_completion_tokens(): int {
		return $this->completion_tokens;
	}

	/**
	 * Gets the total token count.
	 *
	 * @return int Total token count.
	 */
	public function get_total_tokens(): int {
		return $this->prompt_tokens + $this->completion_tokens;
	}

	/**
	 * Gets additional provider metadata.
	 *
	 * @return array<string,mixed> Additional provider metadata.
	 */
	public function get_metadata(): array {
		return $this->metadata;
	}
}
