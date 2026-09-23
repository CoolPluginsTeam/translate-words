<?php
/**
 * Tests for the reusable Ollama connector adapter.
 *
 * @package Linguator
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text ) {
			return $text;
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;
			private $data;

			public function __construct( $code = '', $message = '', $data = array() ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			public function get_error_code() {
				return $this->code;
			}

			public function get_error_message() {
				return $this->message;
			}

			public function get_error_data() {
				return $this->data;
			}
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $value ) {
			return $value instanceof WP_Error;
		}
	}
}

namespace Linguator\Tests {
	use CoolPlugins\AI_Connectors\Ollama\Ollama_Client_Interface;
	use CoolPlugins\AI_Connectors\Ollama\Ollama_Request;
	use CoolPlugins\AI_Connectors\Ollama\Ollama_Response;
	use Linguator\Includes\Services\Translation\Providers\Ollama_Translation_Provider;
	use Linguator\Modules\REST\V1\Bulk_Translation;
	use PHPUnit\Framework\TestCase;
	use ReflectionMethod;
	use WP_Error;

	require_once dirname( __DIR__, 2 ) . '/includes/services/translation/providers/class-ollama-translation-provider.php';
	require_once dirname( __DIR__, 2 ) . '/modules/rest/v1/bulk-translation.php';

	final class OllamaTranslationProviderTest extends TestCase {
		public function test_filters_remote_models_through_product_allowlist(): void {
			$client   = new FakeOllamaClient( array( 'gemma4:31b', 'not-approved:latest' ) );
			$provider = new Ollama_Translation_Provider( 'unused', 'gemma4:31b', $client );

			$models = $provider->get_available_models();

			$this->assertSame( array( 'gemma4:31b' ), array_keys( $models ) );
		}

		public function test_rejects_unsupported_translation_model_before_request(): void {
			$client   = new FakeOllamaClient( array() );
			$provider = new Ollama_Translation_Provider( 'unused', 'not-approved:latest', $client );

			$result = $provider->translate_instruction( 'Translate this.' );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'lmat_ollama_unsupported_model', $result->get_error_code() );
			$this->assertSame( 0, $client->chat_calls );
		}

		public function test_returns_generated_json_text(): void {
			$client            = new FakeOllamaClient( array( 'gemma4:31b' ) );
			$client->chat_text = '{"title":"Bonjour"}';
			$provider          = new Ollama_Translation_Provider( 'unused', 'gemma4:31b', $client );

			$result = $provider->translate_instruction( 'Translate this.' );

			$this->assertSame( '{"title":"Bonjour"}', $result );
			$this->assertSame( 1, $client->chat_calls );
		}

		public function test_adds_escaped_html_instruction_to_ollama_request(): void {
			$client   = new FakeOllamaClient( array( 'gemma4:31b' ) );
			$provider = new Ollama_Translation_Provider( 'unused', 'gemma4:31b', $client );

			$provider->translate_instruction( 'Translate {"1":"\\<p>Hello\\</p>"}.' );

			$this->assertStringContainsString( 'transport wrappers', $client->last_request->to_array()['messages'][1]['content'] );
		}

		public function test_disables_reasoning_for_translation(): void {
			$client   = new FakeOllamaClient( array( 'gpt-oss:20b' ) );
			$provider = new Ollama_Translation_Provider( 'unused', 'gpt-oss:20b', $client );

			$provider->translate_instruction( 'Translate {"1":"Hello"}.' );
			$payload = $client->last_request->to_array();

			$this->assertFalse( $payload['think'] );
			$this->assertSame( 0, $payload['options']['temperature'] );
		}

		public function test_requires_every_translation_key_in_response_schema(): void {
			$client   = new FakeOllamaClient( array( 'gemma4:31b' ) );
			$provider = new Ollama_Translation_Provider( 'unused', 'gemma4:31b', $client );

			$provider->translate_instruction( 'Translate these strings.', array( 'heading', 'paragraph' ) );
			$format = $client->last_request->to_array()['format'];

			$this->assertSame( array( 'heading', 'paragraph' ), $format['required'] );
			$this->assertFalse( $format['additionalProperties'] );
			$this->assertSame( 'string', $format['properties']->heading['type'] );
			$this->assertSame( 'string', $format['properties']->paragraph['type'] );
		}

		public function test_reports_truncated_generation_with_token_metadata(): void {
			$client                    = new FakeOllamaClient( array( 'gemma4:31b' ) );
			$client->finish_reason     = 'length';
			$client->prompt_tokens     = 420;
			$client->completion_tokens = 128;
			$provider                  = new Ollama_Translation_Provider( 'unused', 'gemma4:31b', $client );

			$result = $provider->translate_instruction( 'Translate these strings.', array( 'heading' ) );

			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'lmat_ollama_output_truncated', $result->get_error_code() );
			$this->assertSame( 'length', $result->get_error_data()['finish_reason'] );
			$this->assertSame( 420, $result->get_error_data()['prompt_tokens'] );
			$this->assertSame( 128, $result->get_error_data()['completion_tokens'] );
		}

		public function test_bulk_parser_preserves_html_entities_in_ollama_json(): void {
			$bulk   = new Bulk_Translation( null );
			$method = new ReflectionMethod( $bulk, 'ai_translate_parse_llm_response' );
			$method->setAccessible( true );

			$result = $method->invoke(
				$bulk,
				'{"content":"[[LMAT_HTML_TAG_0000]]Bonjour &quot;ami&quot;[[LMAT_HTML_TAG_0001]]"}',
				array( 'content' => '<p class="lead">Hello &quot;friend&quot;</p>' ),
				'ollama',
				array(
					'content' => array(
						'[[LMAT_HTML_TAG_0000]]' => '<p class="lead">',
						'[[LMAT_HTML_TAG_0001]]' => '</p>',
					),
				)
			);

			$this->assertSame(
				array( 'content' => '<p class="lead">Bonjour &quot;ami&quot;</p>' ),
				$result
			);
		}
	}

	final class FakeOllamaClient implements Ollama_Client_Interface {
		public $chat_calls        = 0;
		public $chat_text         = '{}';
		public $last_request;
		public $finish_reason     = 'stop';
		public $prompt_tokens     = 10;
		public $completion_tokens = 5;
		private $models;

		public function __construct( array $models ) {
			$this->models = $models;
		}

		public function list_models() {
			return $this->models;
		}

		public function chat( Ollama_Request $request ) {
			++$this->chat_calls;
			$this->last_request = $request;

			return new Ollama_Response(
				$this->chat_text,
				'gemma4:31b',
				$this->finish_reason,
				$this->prompt_tokens,
				$this->completion_tokens
			);
		}
	}
}
