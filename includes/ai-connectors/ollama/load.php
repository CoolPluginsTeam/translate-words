<?php
/**
 * Loads the reusable Ollama Cloud connector.
 *
 * @package Linguator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-ollama-request.php';
require_once __DIR__ . '/class-ollama-response.php';
require_once __DIR__ . '/class-ollama-client-interface.php';
require_once __DIR__ . '/class-ollama-client.php';
