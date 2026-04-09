<?php
/**
 * @package Linguator
 */

namespace Linguator\Includes\Options\Business;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Includes\Options\Abstract_Option;
use Linguator\Includes\Options\Options;

/**
 * Stores API keys for supported AI providers.
 */
class Api_Keys extends Abstract_Option {
	/**
	 * @return string
	 */
	public static function key(): string {
		return 'api_keys';
	}

	/**
	 * Stores provider models only. Provider keys are stored in dedicated WP options:
	 * connectors_ai_{provider}_key.
	 *
	 * @return array{openai_model:string, gemini_model:string, anthropic_model:string}
	 */
	protected function get_default() {
		return array(
			'openai_model' => 'gpt-4o-mini',
			'gemini_model' => 'gemini-1.5-flash',
			'anthropic_model' => 'claude-3-5-sonnet-latest',
		);
	}

	/**
	 * @return array
	 */
	protected function get_data_structure(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'openai_model' => array( 'type' => 'string' ),
				'gemini_model' => array( 'type' => 'string' ),
				'anthropic_model' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * @param mixed   $value
	 * @param Options $options
	 * @return array
	 */
	protected function sanitize( $value, Options $options ) {
		$current = $options->get( self::key() );
		if ( ! is_array( $current ) ) {
			$current = $this->get_default();
		}

		if ( ! is_array( $value ) ) {
			return $current;
		}

		$filtered = $current;

		foreach ( array( 'openai_model', 'gemini_model', 'anthropic_model' ) as $k ) {
			if ( array_key_exists( $k, $value ) ) {
				$v = $value[ $k ];
				if ( null === $v ) {
					$filtered[ $k ] = '';
					continue;
				}
				$filtered[ $k ] = sanitize_text_field( (string) $v );
			}
		}

		return $filtered;
	}

	/**
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'API keys for AI translation providers.', 'translate-words' );
	}
}

