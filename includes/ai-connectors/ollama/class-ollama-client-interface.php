<?php
/**
 * Ollama Cloud client contract.
 *
 * @package Linguator
 */

namespace CoolPlugins\AI_Connectors\Ollama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Defines the public API for an Ollama Cloud client.
 */
interface Ollama_Client_Interface {
	/**
	 * Lists model identifiers available to the authenticated Ollama account.
	 *
	 * @return string[]|WP_Error Model identifiers or an error.
	 */
	public function list_models();

	/**
	 * Sends a chat request to Ollama Cloud.
	 *
	 * @param Ollama_Request $request Request data.
	 * @return Ollama_Response|WP_Error Normalized response or an error.
	 */
	public function chat( Ollama_Request $request );
}
