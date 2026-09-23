<?php
/**
 * @package Linguator
 */

namespace Linguator\Includes\Options\Business;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/services/translation/providers/class-ollama-translation-models.php';

use Linguator\Includes\Options\Abstract_Option;
use Linguator\Includes\Options\Options;
use Linguator\Includes\Services\Translation\Providers\Ollama_Translation_Models;

/**
 * Stores API keys for supported AI providers.
 */
class Api_Keys extends Abstract_Option {
	/** Current schema/version of the cached Gemini allowlist. */
	private const GEMINI_MODELS_CACHE_VERSION = 2;

	/**
	 * Filter and label preferred models for a provider.
	 *
	 * @param string $provider_id Provider id used by WP AI Client (e.g. google).
	 * @param array  $models      Discovered model ids (list<string>).
	 * @return array<string,string> Map of model_id => label.
	 */
	private static function filtered_specific_models( string $provider_id, array $models ): array {
		$provider_id = strtolower( trim( $provider_id ) );

		$preferred = array();
		if ( 'google' === $provider_id ) {
			// Gemini models (labels shown in UI). We only show these if the provider reports them.
			$preferred = array(
				'gemini-3.8-flash'              => __( 'gemini-3.8-flash (Recommended)', 'translate-words' ),
				'gemini-3.7-flash'              => __( 'gemini-3.7-flash (Recommended)', 'translate-words' ),
				'gemini-3.6-flash'              => __( 'gemini-3.6-flash (Fast & Reliable)', 'translate-words' ),
				'gemini-3.5-flash'              => __( 'gemini-3.5-flash (High Accuracy)', 'translate-words' ),
				'gemini-3.5-flash-lite'         => __( 'gemini-3.5-flash-lite (Fast & Cheap)', 'translate-words' ),
				'gemini-3.1-pro-preview'        => __( 'gemini-3.1-pro-preview (Best Quality)', 'translate-words' ),
				'gemini-3.1-flash-lite-preview' => __( 'gemini-3.1-flash-lite-preview (Fast & Cheap)', 'translate-words' ),
				'gemini-3-flash-preview'        => __( 'gemini-3-flash-preview (Recommended)', 'translate-words' ),
				'gemini-3-deep-think'           => __( 'gemini-3-deep-think (Advanced Reasoning)', 'translate-words' ),
				'gemini-2.5-pro'                => __( 'gemini-2.5-pro (Best Overall)', 'translate-words' ),
				'gemini-2.5-flash'              => __( 'gemini-2.5-flash (Balanced)', 'translate-words' ),
				'gemini-2.5-pro-preview-tts'    => __( 'gemini-2.5-pro-preview-tts (High Accuracy)', 'translate-words' ),
				'gemma-4-26b-a4b-it'            => __( 'gemma-4-26b-a4b-it (Cheapest)', 'translate-words' ),
				'gemma-3n-e4b-it'               => __( 'gemma-3n-e4b-it (Cheapest)', 'translate-words' ),
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
	 * connectors_ai_google_api_key.
	 *
	 * @return array{gemini_model:string,ollama_model:string}
	 */
	protected function get_default() {
		return array(
			'gemini_model' => 'gemini-2.5-flash',
			'ollama_model' => Ollama_Translation_Models::get_default(),
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
				'ollama_model' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Replaces a removed Ollama model stored by an older plugin version.
	 *
	 * @param mixed $value Stored option value.
	 * @return mixed Prepared option value.
	 */
	protected function prepare( $value ) {
		if ( ! is_array( $value ) || ! array_key_exists( 'ollama_model', $value ) ) {
			return $value;
		}

		$model = is_scalar( $value['ollama_model'] ) ? sanitize_text_field( (string) $value['ollama_model'] ) : '';
		if ( ! Ollama_Translation_Models::is_supported( $model ) ) {
			$value['ollama_model'] = Ollama_Translation_Models::get_default();
		}

		return $value;
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
		$ollama  = isset( $current['ollama_model'] ) && is_scalar( $current['ollama_model'] )
			? sanitize_text_field( (string) $current['ollama_model'] )
			: $default['ollama_model'];

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

		if ( is_array( $value ) && array_key_exists( 'ollama_model', $value ) ) {
			$v = is_scalar( $value['ollama_model'] ) ? sanitize_text_field( (string) $value['ollama_model'] ) : '';
			if ( Ollama_Translation_Models::is_supported( $v ) ) {
				$ollama = $v;
			}
		}

		return array(
			'gemini_model' => $gemini,
			'ollama_model' => $ollama,
		);
	}

	/**
	 * Sub-key of {@see Options::OPTION_NAME} for the last Gemini model list (updated when the key is saved / discovery runs).
	 */
	private static function gemini_models_list_option(): string {
		return 'lmat_gemini_models_list';
	}

	/**
	 * Returns the option key used for the cached Ollama model list.
	 *
	 * @return string Option key.
	 */
	private static function ollama_models_list_option(): string {
		return 'lmat_ollama_models_list';
	}

	/**
	 * Stores the cached Gemini model list.
	 *
	 * @param string                  $api_key Raw Gemini API key.
	 * @param array<string|int,mixed> $gemini Same shape as REST `available_models.gemini`.
	 */
	public static function persist_gemini_models_list( string $api_key, array $gemini ): void {
		$key = trim( $api_key );
		if ( '' === $key ) {
			self::clear_gemini_models_list();
			return;
		}
		$linguator = get_option( Options::OPTION_NAME, array() );
		if ( ! is_array( $linguator ) ) {
			$linguator = array();
		}
		$linguator[ self::gemini_models_list_option() ] = array(
			'fingerprint' => md5( $key ),
			'version'     => self::GEMINI_MODELS_CACHE_VERSION,
			'gemini'      => $gemini,
		);
		update_option( Options::OPTION_NAME, $linguator );
	}

	/**
	 * Clears the cached Gemini model list.
	 *
	 * @return void
	 */
	public static function clear_gemini_models_list(): void {
		$linguator = get_option( Options::OPTION_NAME, array() );
		if ( ! is_array( $linguator ) || ! isset( $linguator[ self::gemini_models_list_option() ] ) ) {
			return;
		}
		unset( $linguator[ self::gemini_models_list_option() ] );
		update_option( Options::OPTION_NAME, $linguator );
	}


	/**
	 * Stores approved Ollama models available to the authenticated account.
	 *
	 * @param string                            $api_key Raw Ollama API key.
	 * @param array<string,array<string,mixed>> $models Approved available models.
	 * @return void
	 */
	public static function persist_ollama_models_list( string $api_key, array $models ): void {
		$key = trim( $api_key );
		if ( '' === $key ) {
			self::clear_ollama_models_list();
			return;
		}

		$linguator = get_option( Options::OPTION_NAME, array() );
		if ( ! is_array( $linguator ) ) {
			$linguator = array();
		}

		$linguator[ self::ollama_models_list_option() ] = array(
			'fingerprint' => hash( 'sha256', $key ),
			'models'      => $models,
		);
		update_option( Options::OPTION_NAME, $linguator );
	}

	/**
	 * Clears the cached Ollama model list.
	 *
	 * @return void
	 */
	public static function clear_ollama_models_list(): void {
		$linguator = get_option( Options::OPTION_NAME, array() );
		$model_key = self::ollama_models_list_option();
		if ( ! is_array( $linguator ) || ! isset( $linguator[ $model_key ] ) ) {
			return;
		}

		unset( $linguator[ $model_key ] );
		update_option( Options::OPTION_NAME, $linguator );
	}

	/**
	 * Models for GET /settings — DB only, no HTTP. Populated when a new key triggers discovery.
	 *
	 * @return array{gemini:array<int|string,mixed>,ollama:array<int|string,mixed>}
	 */
	public static function get_stored_provider_models(): array {
		$result = array(
			'gemini' => array(),
			'ollama' => array(),
		);

		$gemini_key = trim( (string) get_option( 'connectors_ai_google_api_key', '' ) );
		$ollama_key = trim( (string) get_option( 'connectors_ai_ollama_api_key', '' ) );
		$linguator  = get_option( Options::OPTION_NAME, array() );

		$fingerprint = md5( $gemini_key );
		$model_list  = self::gemini_models_list_option();
		$list        = ( is_array( $linguator ) && isset( $linguator[ $model_list ] ) && is_array( $linguator[ $model_list ] ) ) ? $linguator[ $model_list ] : null;

		if (
			'' !== $gemini_key
			&& is_array( $list )
			&& isset( $list['fingerprint'], $list['version'], $list['gemini'] )
			&& $list['fingerprint'] === $fingerprint
			&& self::GEMINI_MODELS_CACHE_VERSION === (int) $list['version']
		) {
			$result['gemini'] = is_array( $list['gemini'] ) ? $list['gemini'] : array();
		}

		$ollama_list_key = self::ollama_models_list_option();
		$ollama_list     = ( is_array( $linguator ) && isset( $linguator[ $ollama_list_key ] ) && is_array( $linguator[ $ollama_list_key ] ) ) ? $linguator[ $ollama_list_key ] : null;
		$ollama_fingerprint_valid = false;
		if ( '' !== $ollama_key && is_array( $ollama_list ) && isset( $ollama_list['fingerprint'] ) && is_string( $ollama_list['fingerprint'] ) ) {
			$stored_fingerprint       = $ollama_list['fingerprint'];
			$sha256_fingerprint       = hash( 'sha256', $ollama_key );
			$legacy_md5_fingerprint   = md5( $ollama_key );
			$ollama_fingerprint_valid = hash_equals( $stored_fingerprint, $sha256_fingerprint )
				|| hash_equals( $stored_fingerprint, $legacy_md5_fingerprint );
		}

		if ( $ollama_fingerprint_valid && isset( $ollama_list['models'] ) ) {
			$stored_ollama    = is_array( $ollama_list['models'] ) ? $ollama_list['models'] : array();
			$result['ollama'] = array_intersect_key( $stored_ollama, Ollama_Translation_Models::all() );
		}

		return $result;
	}

	/**
	 * Discover available text-generation models for supported providers via WP AI Client.
	 *
	 * Notes:
	 * - This mirrors the model discovery approach used in the TranslatePress AI settings UI.
	 * - Provider API keys are stored in WP options (connectors_ai_google_api_key). When WP AI
	 *   Client is available, it typically reads those connector settings to configure providers.
	 *
	 * @return array{gemini:array<int|string,mixed>} List of model ids, or id => label when {@see filtered_specific_models} matches.
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

		$gemini_key = trim( (string) get_option( 'connectors_ai_google_api_key', '' ) );
		if ( '' === $gemini_key ) {
			return $result;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! $registry || ! method_exists( $registry, 'findProviderModelsMetadataForSupport' ) ) {
				return $result;
			}
			$auth_class = '\WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication';

			if ( class_exists( $auth_class ) && method_exists( $registry, 'setProviderRequestAuthentication' ) ) {
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
			);

			$load_models = static function ( string $provider_id ) use ( $registry, $requirements, $excluded_patterns ): array {
				try {
					/*
					 * Cap timeout only for Generative Language API requests (model discovery),
					 * not for every outbound HTTP request while discovery runs.
					 */
					$timeout_filter = static function ( array $args, $url = '' ): array {
						$url = is_string( $url ) ? $url : '';
						if ( '' === $url || false === stripos( $url, 'generativelanguage.googleapis.com' ) ) {
							return $args;
						}
						$args['timeout'] = isset( $args['timeout'] )
							? min( (float) $args['timeout'], 4.0 )
							: 4.0;

						return $args;
					};

					add_filter( 'http_request_args', $timeout_filter, 10, 2 );

					try {
						$models_metadata = $registry->findProviderModelsMetadataForSupport( $provider_id, $requirements );
					} finally {
						remove_filter( 'http_request_args', $timeout_filter, 10 );
					}

					if ( ! is_array( $models_metadata ) ) {
						return array();
					}

					$ids = array();
					foreach ( $models_metadata as $model ) {
						if ( is_object( $model ) && method_exists( $model, 'getId' ) ) {
							$ids[] = (string) $model->getId();
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

					return array_values( array_unique( $filtered ) );
				} catch ( \Throwable $e ) {
					return array();
				}
			};

			$models           = $load_models( 'google' );
			$filtered         = self::filtered_specific_models( 'google', $models );
			$result['gemini'] = $filtered;
			self::persist_gemini_models_list( $gemini_key, $result['gemini'] );
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
