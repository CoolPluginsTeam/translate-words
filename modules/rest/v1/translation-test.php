<?php
/**
 * Live translation previews for configured AI providers.
 *
 * @package Linguator
 */

namespace Linguator\Modules\REST\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Translation_Test {
	/** Registers the settings-only preview endpoint. */
	public static function register_routes( callable $permission_callback ): void {
		register_rest_route( 'lmat/v1', '/settings/test-connection', array(
			'methods' => \WP_REST_Server::CREATABLE,
			'permission_callback' => $permission_callback,
			'callback' => array( self::class, 'run' ),
			'args' => array(
				'provider' => array( 'type' => 'string', 'required' => true, 'maxLength' => 80, 'sanitize_callback' => 'sanitize_key' ),
				'model' => array( 'type' => 'string', 'default' => '', 'maxLength' => 200, 'sanitize_callback' => 'sanitize_text_field' ),
				'text' => array( 'type' => 'string', 'required' => true, 'minLength' => 1, 'maxLength' => 200, 'sanitize_callback' => 'sanitize_textarea_field' ),
				'target_language' => array( 'type' => 'string', 'required' => true, 'validate_callback' => array( self::class, 'validate_target_language' ) ),
			),
		) );
	}

	/** Uses non-default site languages, including custom slugs and regional locales. */
	private static function target_languages(): array {
		$languages = array();
		foreach ( LMAT()->model->get_languages_list() as $language ) {
			if ( $language->is_default ) {
				continue;
			}
			$languages[ $language->slug ] = sanitize_text_field( $language->name . ' (' . $language->locale . ')' );
		}
		return $languages;
	}

	/** Accepts only non-default languages added to this site. */
	public static function validate_target_language( $value ): bool {
		return is_string( $value ) && isset( self::target_languages()[ $value ] );
	}

	/** Runs one short translation without saving provider settings or content. */
	public static function run( \WP_REST_Request $request ) {
		$languages = self::target_languages();
		if ( ! isset( $languages[ $request['target_language'] ] ) ) {
			return new \WP_Error( 'lmat_test_language_invalid', __( 'Select a non-default language added to this site in Linguator.', 'translate-words' ), array( 'status' => 400 ) );
		}
		$provider = $request['provider'];
		$model = $request['model'];
		$text = trim( $request['text'] );
		if ( '' === $text ) {
			return new \WP_Error( 'lmat_test_empty', __( 'Please enter sample text to test translation.', 'translate-words' ), array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return new \WP_Error( 'lmat_test_unavailable', __( 'The WordPress AI Client is not available.', 'translate-words' ), array( 'status' => 400 ) );
		}
		$key = '';
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		if ( 'gemini' === $provider ) {
			$key = trim( (string) get_option( 'connectors_ai_google_api_key', '' ) );
			if ( '' === $key ) {
				return new \WP_Error( 'lmat_test_key_missing', __( 'Save an API key for this provider first.', 'translate-words' ), array( 'status' => 400 ) );
			}
			$provider_id = 'google';
			if ( '' === $model ) {
				$saved = LMAT()->model->options->get( 'api_keys' );
				$model = $saved['gemini_model'] ?? 'gemini-2.5-flash';
			}
		} else {
			$providers = function_exists( 'linguator_get_external_ai_providers' ) ? linguator_get_external_ai_providers() : array();
			if ( ! isset( $providers[ $provider ] ) ) {
				return new \WP_Error( 'lmat_test_provider_invalid', __( 'This provider cannot be tested.', 'translate-words' ), array( 'status' => 400 ) );
			}
			// Reuse the provider's credential, connection and license checks.
			$setup = apply_filters( 'lmat_ai_batch_prepare_provider', null, $provider, $model );
			if ( is_wp_error( $setup ) ) { return $setup; }
			$provider_id = is_array( $setup ) ? ( $setup['provider_id'] ?? '' ) : '';
			$model = is_array( $setup ) ? ( $setup['model_id'] ?? '' ) : '';
		}
		if ( ! $provider_id || ! $registry->hasProvider( $provider_id ) ) {
			return new \WP_Error( 'lmat_test_provider_invalid', __( 'This provider is not available on your site.', 'translate-words' ), array( 'status' => 400 ) );
		}
		$lock = 'lmat_connection_test_' . get_current_user_id() . '_' . md5( $provider );
		if ( get_transient( $lock ) ) {
			return new \WP_Error( 'lmat_test_cooldown', __( 'Please wait five seconds before testing again.', 'translate-words' ), array( 'status' => 429 ) );
		}
		set_transient( $lock, 1, 5 );
		$timeout = static function () { return 30; };
		add_filter( 'wp_ai_client_default_request_timeout', $timeout );
		$started = microtime( true );
		try {
			if ( '' !== $key ) {
				$registry->setProviderRequestAuthentication( $provider_id, new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication( $key ) );
			}
			$builder = wp_ai_client_prompt()->using_provider( $provider_id );
			if ( '' !== $model ) {
				$class = $registry->getProviderClassName( $provider_id );
				$builder = $builder->using_model( $class::model( $model ) );
			}
			$prompt = 'Translate the following English text into ' . $languages[ $request['target_language'] ] . ". Return only the translation.\n\n" . $text;
			$reply = $builder->with_text( $prompt )->generate_text();
			if ( is_wp_error( $reply ) ) {
				return self::test_error( $reply->get_error_message(), $key );
			}
			$reply = trim( (string) $reply );
			if ( '' === $reply ) {
				return new \WP_Error( 'lmat_test_empty_reply', __( 'The provider returned an empty translation. Try another model.', 'translate-words' ), array( 'status' => 502 ) );
			}
			return rest_ensure_response( array( 'translation' => $reply, 'source' => $text, 'model' => $model, 'ms' => (int) round( ( microtime( true ) - $started ) * 1000 ) ) );
		} catch ( \Throwable $error ) {
			return self::test_error( $error->getMessage(), $key );
		} finally {
			remove_filter( 'wp_ai_client_default_request_timeout', $timeout );
		}
	}

	/** Builds a REST error with a friendly summary, a hint and a short provider detail. */
	private static function test_error( $message, $key ) {
		$message = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $message ) ) );
		if ( '' !== $key ) { $message = str_replace( $key, '[redacted]', $message ); }

		// Keep only the first sentence; providers often append long detail lists.
		$parts  = preg_split( '/(?<=[.!?])\s+|\s\*\s/', $message, 2 );
		$detail = trim( $parts[0] );
		if ( mb_strlen( $detail ) > 200 ) { $detail = mb_substr( $detail, 0, 200 ) . '…'; }

		if ( preg_match( '/\b429\b|too many requests|quota|rate.?limit/i', $message ) ) {
			$summary = __( 'Rate limit or quota reached. Your provider plan limits were hit - try a different model, or wait and retry.', 'translate-words' );
			$hint    = __( 'Pick a different model in the select above and run the test again.', 'translate-words' );
			if ( preg_match( '/retry in ((?:\d+h)?(?:\d+m)?\d+)(?:\.\d+)?s/i', $message, $retry ) ) {
				/* translators: %s: wait time, e.g. 1h45m5s */
				$hint .= ' ' . sprintf( __( 'Or retry in %s.', 'translate-words' ), $retry[1] . 's' );
			}
		} elseif ( preg_match( '/\b40[13]\b|unauthori[sz]ed|forbidden|api.?key|permission denied|invalid.*(key|credential)/i', $message ) ) {
			$summary = __( 'The provider rejected the API key.', 'translate-words' );
			$hint    = __( 'Check that the API key is correct, active and saved, then run the test again.', 'translate-words' );
		} elseif ( preg_match( '/\b404\b|not found|not supported|does not exist/i', $message ) ) {
			$summary = __( 'The selected model is not available for this API key.', 'translate-words' );
			$hint    = __( 'Pick a different model in the select above and run the test again.', 'translate-words' );
		} elseif ( preg_match( '/network|timed? ?out|could not resolve|curl|connection/i', $message ) ) {
			$summary = __( 'Could not connect to the provider.', 'translate-words' );
			$hint    = __( 'Check your server internet connection or firewall, then run the test again.', 'translate-words' );
		} else {
			$summary = __( 'The provider returned an error.', 'translate-words' );
			$hint    = __( 'Try a different model or run the test again.', 'translate-words' );
		}

		return new \WP_Error( 'lmat_test_failed', $summary, array( 'status' => 502, 'summary' => $summary, 'hint' => $hint, 'detail' => $detail ) );
	}
}
