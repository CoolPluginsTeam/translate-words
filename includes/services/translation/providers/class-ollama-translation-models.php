<?php
/**
 * Supported Ollama Cloud translation models.
 *
 * @package Linguator
 */

namespace Linguator\Includes\Services\Translation\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides Linguator's fixed allowlist of Ollama Cloud translation models.
 */
class Ollama_Translation_Models {
	/**
	 * Default Ollama model.
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'gemma4:31b';

	/**
	 * Returns supported model metadata keyed by the exact Ollama model ID.
	 *
	 * This is intentionally a fixed product allowlist. It must not be replaced
	 * with every model returned by a remote model-discovery endpoint.
	 *
	 * @return array<string,array<string,mixed>> Supported model metadata.
	 */
	public static function all(): array {
		return array(
			'gemma4:31b'          => array(
				'label' => 'Gemma 4 31B',
				'fast'  => true,
			),
			'gpt-oss:120b'        => array(
				'label' => 'GPT-OSS 120B',
			),
			'nemotron-3-nano:30b' => array(
				'label' => 'Nemotron 3 Nano 30B',
			),
			'nemotron-3-super'    => array(
				'label' => 'Nemotron 3 Super',
			),
			'nemotron-3-ultra'    => array(
				'label' => 'Nemotron 3 Ultra',
			),
		);
	}

	/**
	 * Checks whether a model is supported by Linguator.
	 *
	 * @param string $model Model identifier.
	 * @return bool Whether the model is supported.
	 */
	public static function is_supported( string $model ): bool {
		return array_key_exists( trim( $model ), self::all() );
	}

	/**
	 * Returns the default model identifier.
	 *
	 * @return string Default model identifier.
	 */
	public static function get_default(): string {
		return self::DEFAULT_MODEL;
	}
}
