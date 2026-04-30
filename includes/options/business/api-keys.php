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
	 * Filter and label preferred models for a provider.
	 *
	 * @param string $provider_id Provider id used by WP AI Client (e.g. google).
	 * @param array  $models      Discovered model ids (list<string>).
	 * @return array<string,string> Map of model_id => label.
	 */
	private static function filtered_specific_models( string $provider_id, array $models ): array {
		$provider_id = is_string( $provider_id ) ? strtolower( trim( $provider_id ) ) : '';
		$models      = is_array( $models ) ? $models : array();

		$preferred = array();
		if ( 'google' === $provider_id ) {
			// Gemini models (labels shown in UI). We only show these if the provider reports them.
			$preferred = array(
				'gemini-3.1-pro-preview'        => __( 'gemini-3.1-pro-preview (Best Quality)', 'translate-words' ),
				'gemini-3.1-flash-lite-preview' => __( 'gemini-3.1-flash-lite-preview (Fast & Cheap)', 'translate-words' ),
				'gemma-3n-e4b-it'               => __( 'gemma-3n-e4b-it (Cheapest)', 'translate-words' ),
				'gemini-2.5-pro'                => __( 'gemini-2.5-pro (Best Overall)', 'translate-words' ),
				'gemini-2.5-flash'              => __( 'gemini-2.5-flash (Balanced)', 'translate-words' ),
				'gemini-3-flash-preview'        => __( 'gemini-3-flash-preview (Recommended)', 'translate-words' ),
				'gemini-2.5-pro-preview-tts'    => __( 'gemini-2.5-pro-preview-tts (High Accuracy)', 'translate-words' ),
			);
		}

		if ( empty( $preferred ) ) {
			return array();
		}

		if ( ! empty( $models ) ) {
			$preferred = array_intersect_key(
				$preferred,
				array_flip( array_values( $models ) )
			);
		}

		return $preferred;
	}
	/**
	 * Returns option key.
	 *
	 * @return string
	 */
	public static function key(): string {
		return 'api_keys';
	}

	/**
	 * Stores provider models only. Provider keys are stored in dedicated WP options:
	 * connectors_ai_{provider}_key.
	 *
	 * @return array{gemini_model:string}
	 */
	protected function get_default() {
		return array(
			'gemini_model' => 'gemini-2.5-flash',
		);
	}

	/**
	 * Returns JSON schema structure for this option.
	 *
	 * @return array
	 */
	protected function get_data_structure(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'gemini_model' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Sanitizes stored Gemini model id.
	 *
	 * @param mixed   $value   Incoming value.
	 * @param Options $options Options registry instance.
	 * @return array
	 */
	protected function sanitize( $value, Options $options ) {
		$current = $options->get( self::key() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$default = $this->get_default();
		$gemini  = isset( $current['gemini_model'] ) && is_scalar( $current['gemini_model'] )
			? sanitize_text_field( (string) $current['gemini_model'] )
			: $default['gemini_model'];

		if ( is_array( $value ) && array_key_exists( 'gemini_model', $value ) ) {
			$v = $value['gemini_model'];
			if ( null === $v ) {
				$gemini = '';
			} elseif ( is_scalar( $v ) ) {
				$gemini = sanitize_text_field( (string) $v );
			} else {
				$gemini = '';
			}
		}

		return array(
			'gemini_model' => $gemini,
		);
	}

	/**
	 * Discover available text-generation models for supported providers via WP AI Client.
	 *
	 * Notes:
	 * - This mirrors the model discovery approach used in the TranslatePress AI settings UI.
	 * - Provider API keys are stored in WP options (connectors_ai_{provider}_key). When WP AI
	 *   Client is available, it typically reads those connector settings to configure providers.
	 *
	 * @return array{gemini:list<string>}
	 */
	public static function discover_provider_models(): array {
		$result = array(
			'gemini' => array(),
		);

		// Only attempt discovery when WP AI Client is present.
		if (
			! class_exists( '\WordPress\AiClient\AiClient' ) ||
			! class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) ||
			! class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
		) {
			return $result;
		}

		// Only attempt discovery when a provider key exists.
		$get_provider_key = static function ( string $provider ): string {
			$opt = 'connectors_ai_' . strtolower( $provider ) . '_key';
			return trim( (string) get_option( $opt, '' ) );
		};

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! $registry || ! method_exists( $registry, 'findProviderModelsMetadataForSupport' ) ) {
				return $result;
			}

			// Ensure the registry is authenticated using our stored connector options.
			// Without this, model listing can work right after "test" calls but fail after page reload.
			$auth_class = '\WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication';
			$gemini_key = $get_provider_key( 'gemini' );

			if ( '' !== $gemini_key && class_exists( $auth_class ) && method_exists( $registry, 'setProviderRequestAuthentication' ) ) {
				// WP AI Client provider id is "google" for Gemini.
				$registry->setProviderRequestAuthentication( 'google', new $auth_class( $gemini_key ) );
			}

			$requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
				array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
				array()
			);

			$excluded_patterns = array(
				'audio',
				'transcribe',
				'search',
				'vision',
				'embedding',
				'image',
				'code',
				'whisper',
				'tts',
			);

			$load_models = static function ( string $provider_id, string $api_key ) use ( $registry, $requirements, $excluded_patterns ): array {
				try {
					$api_key = trim( (string) $api_key );
					if ( '' === $api_key ) {
						return array();
					}

					// Cache model lists for 24 hours to avoid repeated provider calls.
					$transient_key = 'lmat_ai_models_' . strtolower( $provider_id ) . '_' . md5( $api_key );
					$cached        = get_transient( $transient_key );
					if ( is_array( $cached ) ) {
						return $cached;
					}

					$models_metadata = $registry->findProviderModelsMetadataForSupport( $provider_id, $requirements );
					$ids             = array();

					if ( is_array( $models_metadata ) ) {
						foreach ( $models_metadata as $model ) {
							if ( is_object( $model ) && method_exists( $model, 'getId' ) ) {
								$ids[] = (string) $model->getId();
							}
						}
					}

					if ( empty( $ids ) ) {
						return array();
					}

					$filtered = array();
					foreach ( $ids as $id ) {
						$lower = strtolower( $id );
						$skip  = false;
						foreach ( $excluded_patterns as $pattern ) {
							if ( false !== strpos( $lower, $pattern ) ) {
								$skip = true;
								break;
							}
						}
						if ( ! $skip ) {
							$filtered[] = $id;
						}
					}

					$final = array_values( array_unique( $filtered ) );
					set_transient( $transient_key, $final, DAY_IN_SECONDS );
					return $final;
				} catch ( \Throwable $e ) {
					return array();
				}
			};

			if ( '' !== $gemini_key ) {
				$models = $load_models( 'google', $gemini_key );
				$filtered = self::filtered_specific_models( 'google', $models );
				// Prefer a curated + labeled subset when available; otherwise return the raw list.
				$result['gemini'] = ! empty( $filtered ) ? $filtered : $models;
			}
		} catch ( \Throwable $e ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Returns option description.
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'API keys for AI translation providers.', 'translate-words' );
	}
}

