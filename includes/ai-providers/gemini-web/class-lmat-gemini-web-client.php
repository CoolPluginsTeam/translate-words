<?php
/**
 * Gemini Web session client (cookie-based, inspired by OmniRoute gemini-web / gemini-webapi).
 *
 * Auth: separate __Secure-1PSID + __Secure-1PSIDTS settings fields (built into a Cookie header on save).
 * Flow: GET /app → extract SNlM0e + build label → POST StreamGenerate → parse response text.
 * Multi-turn: persist conversation metadata (cid/rid/rcid) per WP user + scope after the first
 * StreamGenerate, then reuse that chat for subsequent translate-text calls in the same bulk/session
 * (same pattern gemini-webapi ChatSession uses).
 *
 * @package Linguator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LMAT_Gemini_Web_Client' ) ) {

	/**
	 * Cookie-authenticated Gemini Web translator.
	 */
	class LMAT_Gemini_Web_Client {

		const OPTION_COOKIE = 'lmat_gemini_web_session';
		const OPTION_PSID   = 'lmat_gemini_web_psid';
		const OPTION_PSIDTS = 'lmat_gemini_web_psidts';
		const INIT_URL      = 'https://gemini.google.com/app';
		const GENERATE_URL  = 'https://gemini.google.com/_/BardChatUi/data/assistant.lamda.BardFrontendService/StreamGenerate';
		const DEFAULT_MODEL = 'gemini-3.5-flash';

		/**
		 * Transient TTL for reused Gemini chat metadata (covers a bulk job).
		 *
		 * @var int
		 */
		const CHAT_TTL = 2700; // 45 minutes.

		/**
		 * Microseconds to wait before a soft-fail same-prompt same-chat retry (~2s).
		 *
		 * @var int
		 */
		const SOFT_RETRY_PACE_US = 2000000;

		/**
		 * Default metadata shape from gemini-webapi (new chat).
		 *
		 * @return array
		 */
		public static function default_metadata() {
			return array( '', '', '', null, null, null, null, null, null, '' );
		}

		/**
		 * Web models shown in settings (labels; request uses account default unless header known).
		 *
		 * @return array<string,string>
		 */
		public static function models() {
			return array(
				'gemini-3.5-flash'      => __( 'Gemini 3.5 Flash (Recommended)', 'translate-words' ),
				'gemini-3.5-flash-lite' => __( 'Gemini 3.5 Flash Lite (Fast)', 'translate-words' ),
				'gemini-2.5-flash'      => __( 'Gemini 2.5 Flash', 'translate-words' ),
				'gemini-2.5-pro'        => __( 'Gemini 2.5 Pro', 'translate-words' ),
			);
		}

		/**
		 * Normalize pasted cookie / JSON / bare PSID into a Cookie header value.
		 *
		 * @param string $raw Raw paste.
		 * @return string
		 */
		public static function normalize_cookie( $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				return '';
			}
			if ( preg_match( '/^cookie\s*:\s*/i', $raw ) ) {
				$raw = preg_replace( '/^cookie\s*:\s*/i', '', $raw );
			}
			if ( '{' === substr( $raw, 0, 1 ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$src = isset( $decoded['cookies'] ) && is_array( $decoded['cookies'] ) ? $decoded['cookies'] : $decoded;
					$parts = array();
					foreach ( $src as $name => $value ) {
						if ( is_string( $name ) && is_string( $value ) && '' !== trim( $value ) ) {
							$parts[] = $name . '=' . trim( $value );
						}
					}
					if ( ! empty( $parts ) ) {
						return implode( '; ', $parts );
					}
				}
			}
			if ( false === strpos( $raw, '=' ) ) {
				return '__Secure-1PSID=' . $raw;
			}
			return $raw;
		}

		/**
		 * Whether cookie string looks usable.
		 *
		 * @param string $cookie Cookie header.
		 * @return bool
		 */
		public static function cookie_looks_valid( $cookie ) {
			$cookie = self::normalize_cookie( $cookie );
			if ( '' === $cookie ) {
				return false;
			}
			return (bool) preg_match( '/__Secure-1PSID(?![A-Za-z0-9_-])\s*=/', $cookie )
				|| (bool) preg_match( '/__Secure-1PSIDTS\s*=/', $cookie );
		}

		/**
		 * Extract one cookie value by name from a Cookie header string.
		 *
		 * @param string $cookie Cookie header.
		 * @param string $name   Cookie name.
		 * @return string
		 */
		public static function extract_cookie_value( $cookie, $name ) {
			$cookie = self::normalize_cookie( $cookie );
			$name   = preg_quote( (string) $name, '/' );
			if ( preg_match( '/' . $name . '\s*=\s*([^;]*)/', $cookie, $m ) ) {
				return trim( $m[1] );
			}
			return '';
		}

		/**
		 * Split a Cookie header (or legacy combined option) into settings parts.
		 *
		 * @param string $cookie Cookie header.
		 * @return array{psid:string,psidts:string}
		 */
		public static function parse_cookie_parts( $cookie ) {
			$cookie = self::normalize_cookie( $cookie );
			return array(
				'psid'   => self::extract_cookie_value( $cookie, '__Secure-1PSID' ),
				'psidts' => self::extract_cookie_value( $cookie, '__Secure-1PSIDTS' ),
			);
		}

		/**
		 * Build Cookie header from separate PSID / PSIDTS fields.
		 *
		 * Accepts bare values or a full Cookie paste accidentally put in one field.
		 *
		 * @param string $psid   __Secure-1PSID value or full cookie paste.
		 * @param string $psidts __Secure-1PSIDTS value or full cookie paste.
		 * @return string
		 */
		public static function build_cookie_from_parts( $psid, $psidts ) {
			$psid   = trim( (string) $psid );
			$psidts = trim( (string) $psidts );

			// If either field looks like a full Cookie header, prefer parsed parts.
			$merged = '';
			if ( false !== strpos( $psid, '__Secure-1PSID' ) ) {
				$merged = $psid;
			}
			if ( false !== strpos( $psidts, '__Secure-1PSID' ) ) {
				$merged = '' !== $merged ? $merged . '; ' . $psidts : $psidts;
			}
			if ( '' !== $merged ) {
				$parts  = self::parse_cookie_parts( $merged );
				$psid   = $parts['psid'] !== '' ? $parts['psid'] : $psid;
				$psidts = $parts['psidts'] !== '' ? $parts['psidts'] : $psidts;
			}

			// Strip accidental "name=" prefixes.
			if ( 0 === stripos( $psid, '__Secure-1PSID=' ) ) {
				$psid = substr( $psid, strlen( '__Secure-1PSID=' ) );
			}
			if ( 0 === stripos( $psidts, '__Secure-1PSIDTS=' ) ) {
				$psidts = substr( $psidts, strlen( '__Secure-1PSIDTS=' ) );
			}

			$parts = array();
			if ( '' !== $psid ) {
				$parts[] = '__Secure-1PSID=' . $psid;
			}
			if ( '' !== $psidts ) {
				$parts[] = '__Secure-1PSIDTS=' . $psidts;
			}
			return implode( '; ', $parts );
		}

		/**
		 * Stored settings field values.
		 *
		 * @return array{psid:string,psidts:string}
		 */
		public static function get_stored_parts() {
			$psid   = trim( (string) get_option( self::OPTION_PSID, '' ) );
			$psidts = trim( (string) get_option( self::OPTION_PSIDTS, '' ) );

			return array(
				'psid'   => $psid,
				'psidts' => $psidts,
			);
		}

		/**
		 * Persist separate fields and rebuild the combined Cookie option.
		 *
		 * @param string $psid   __Secure-1PSID value.
		 * @param string $psidts __Secure-1PSIDTS value.
		 * @return string Built cookie header (empty when nothing saved).
		 */
		public static function save_cookie_parts( $psid, $psidts ) {
			$cookie = self::build_cookie_from_parts( $psid, $psidts );
			$parts  = self::parse_cookie_parts( $cookie );

			update_option( self::OPTION_PSID, $parts['psid'] );
			update_option( self::OPTION_PSIDTS, $parts['psidts'] );
			update_option( self::OPTION_COOKIE, $cookie );

			return $cookie;
		}

		/**
		 * Clear all Gemini Web auth-related options.
		 *
		 * @return void
		 */
		public static function clear_stored_auth() {
			delete_option( self::OPTION_COOKIE );
			delete_option( self::OPTION_PSID );
			delete_option( self::OPTION_PSIDTS );
		}

		/**
		 * Stored session cookie (unmasked). Prefers separate fields; falls back to legacy combined.
		 *
		 * @return string
		 */
		public static function get_stored_cookie() {
			$parts = self::get_stored_parts();
			if ( '' !== $parts['psid'] || '' !== $parts['psidts'] ) {
				return self::build_cookie_from_parts( $parts['psid'], $parts['psidts'] );
			}
			return self::normalize_cookie( (string) get_option( self::OPTION_COOKIE, '' ) );
		}

		/**
		 * Transient key for chat metadata: scoped per WP user (+ optional job/lang scope).
		 * Unrelated users never share a key.
		 *
		 * @param string $scope Opaque scope (e.g. "en|fr" or bulk job id).
		 * @return string
		 */
		public static function chat_storage_key( $scope = '' ) {
			$uid   = (int) get_current_user_id();
			$scope = (string) $scope;
			$hash  = substr( md5( $uid . '|' . $scope ), 0, 16 );
			return 'lmat_gweb_chat_' . $uid . '_' . $hash;
		}

		/**
		 * Load persisted chat metadata for this user/scope.
		 *
		 * @param string $scope Scope key.
		 * @return array|null Metadata list or null.
		 */
		public static function get_chat_metadata( $scope = '' ) {
			$stored = get_transient( self::chat_storage_key( $scope ) );
			if ( ! is_array( $stored ) || empty( $stored['metadata'] ) || ! is_array( $stored['metadata'] ) ) {
				return null;
			}
			$meta = $stored['metadata'];
			// Need at least cid to continue a chat.
			if ( empty( $meta[0] ) || ! is_string( $meta[0] ) ) {
				return null;
			}
			return $meta;
		}

		/**
		 * Persist chat metadata for this user/scope.
		 *
		 * @param array  $metadata Metadata list.
		 * @param string $scope    Scope key.
		 * @return void
		 */
		public static function set_chat_metadata( $metadata, $scope = '' ) {
			if ( ! is_array( $metadata ) || empty( $metadata[0] ) || ! is_string( $metadata[0] ) ) {
				return;
			}
			set_transient(
				self::chat_storage_key( $scope ),
				array(
					'metadata' => array_values( $metadata ),
					'updated'  => time(),
				),
				self::CHAT_TTL
			);
		}

		/**
		 * Clear persisted chat for this user/scope (forces a new chat next call).
		 *
		 * @param string $scope Scope key.
		 * @return void
		 */
		public static function clear_chat_metadata( $scope = '' ) {
			delete_transient( self::chat_storage_key( $scope ) );
		}

		/**
		 * Default browser-like headers.
		 *
		 * @param string $cookie Cookie header.
		 * @return array<string,string>
		 */
		private static function browser_headers( $cookie ) {
			return array(
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.9',
				'Cookie'          => $cookie,
				'Referer'         => 'https://gemini.google.com/',
				'Origin'          => 'https://gemini.google.com',
				'X-Same-Domain'   => '1',
			);
		}

		/**
		 * Bootstrap session tokens from /app HTML.
		 *
		 * @param string $cookie Cookie header.
		 * @param int    $timeout Seconds.
		 * @return array{ok:bool,at?:string,bl?:string,sid?:string,error?:string,code?:int}
		 */
		public static function bootstrap( $cookie, $timeout = 30 ) {
			$cookie = self::normalize_cookie( $cookie );
			if ( ! self::cookie_looks_valid( $cookie ) ) {
				return array(
					'ok'    => false,
					'error' => __( 'Paste a Gemini cookie header that includes __Secure-1PSID (and ideally __Secure-1PSIDTS) copied from a live Network request on gemini.google.com.', 'translate-words' ),
					'code'  => 401,
				);
			}

			$response = wp_remote_get(
				self::INIT_URL,
				array(
					'headers'     => self::browser_headers( $cookie ),
					'timeout'     => $timeout,
					'redirection' => 5,
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'ok'    => false,
					'error' => $response->get_error_message(),
					'code'  => 502,
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );

			if ( $code < 200 || $code >= 400 || '' === $body ) {
				return array(
					'ok'    => false,
					'error' => sprintf(
						/* translators: %d: HTTP status */
						__( 'Gemini Web session page returned HTTP %d. Re-copy cookies from a live Network request while signed in.', 'translate-words' ),
						$code
					),
					'code'  => $code ? $code : 502,
				);
			}

			if ( ! preg_match( '/"SNlM0e"\s*:\s*"([^"]+)"/', $body, $m ) ) {
				return array(
					'ok'    => false,
					'error' => __( 'Could not read Gemini access token (SNlM0e). Session cookie is missing, expired, or incomplete — paste the full Cookie header from DevTools → Network → any gemini.google.com request.', 'translate-words' ),
					'code'  => 401,
				);
			}

			$bl  = '';
			$sid = '';
			if ( preg_match( '/"cfb2h"\s*:\s*"([^"]+)"/', $body, $bm ) ) {
				$bl = $bm[1];
			}
			if ( preg_match( '/"FdrFJe"\s*:\s*"([^"]+)"/', $body, $sm ) ) {
				$sid = $sm[1];
			}

			return array(
				'ok'  => true,
				'at'  => $m[1],
				'bl'  => $bl,
				'sid' => $sid,
			);
		}

		/**
		 * Parse StreamGenerate body into assistant text + conversation metadata.
		 *
		 * Metadata follows gemini-webapi: inner[1] = [cid, rid, …], rcid from candidates[0][0].
		 *
		 * @param string $raw Response body.
		 * @return array{text:string,metadata:?array}
		 */
		public static function parse_stream_payload( $raw ) {
			$raw = (string) $raw;
			if ( '' === $raw ) {
				return array(
					'text'     => '',
					'metadata' => null,
				);
			}
			$raw = preg_replace( "/^\)\]\}'\s*/", '', $raw );

			$last_text = '';
			$metadata  = null;
			$lines     = preg_split( "/\r\n|\n|\r/", $raw );
			if ( ! is_array( $lines ) ) {
				return array(
					'text'     => '',
					'metadata' => null,
				);
			}

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || ctype_digit( $line ) || ! str_contains( $line, 'wrb.fr' ) ) {
					continue;
				}
				$arr = json_decode( $line, true );
				if ( ! is_array( $arr ) || ! isset( $arr[0] ) || ! is_array( $arr[0] ) ) {
					continue;
				}
				if ( ( $arr[0][0] ?? '' ) !== 'wrb.fr' ) {
					continue;
				}
				$payload = $arr[0][2] ?? null;
				if ( ! is_string( $payload ) || '' === $payload ) {
					continue;
				}
				$inner = json_decode( $payload, true );
				if ( ! is_array( $inner ) ) {
					continue;
				}

				// Conversation ids: part_json[1] = [cid, rid, …]
				if ( isset( $inner[1] ) && is_array( $inner[1] ) ) {
					$m_data = $inner[1];
					$cid    = isset( $m_data[0] ) && is_string( $m_data[0] ) ? $m_data[0] : '';
					$rid    = isset( $m_data[1] ) && is_string( $m_data[1] ) ? $m_data[1] : '';
					if ( '' !== $cid || '' !== $rid ) {
						if ( ! is_array( $metadata ) ) {
							$metadata = self::default_metadata();
						}
						if ( '' !== $cid ) {
							$metadata[0] = $cid;
						}
						if ( '' !== $rid ) {
							$metadata[1] = $rid;
						}
						// Preserve any extra slots gemini returns (e.g. context string at [9]).
						foreach ( $m_data as $i => $val ) {
							if ( $i >= 2 && null !== $val && '' !== $val ) {
								$metadata[ $i ] = $val;
							}
						}
					}
				}

				// Reply candidate id: candidates[0][0]
				if ( isset( $inner[4][0][0] ) && is_string( $inner[4][0][0] ) && '' !== $inner[4][0][0] ) {
					if ( ! is_array( $metadata ) ) {
						$metadata = self::default_metadata();
					}
					$metadata[2] = $inner[4][0][0];
				}

				$text = self::extract_text_from_inner( $inner );
				if ( '' !== $text ) {
					$last_text = $text;
				}
			}

			if ( '' === $last_text ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$last_text = self::extract_text_from_inner( $decoded );
				}
			}

			return array(
				'text'     => trim( $last_text ),
				'metadata' => $metadata,
			);
		}

		/**
		 * Extract text from nested Gemini response arrays.
		 *
		 * @param mixed $node Node.
		 * @return string
		 */
		private static function extract_text_from_inner( $node ) {
			// Classic path: inner[4][0][1] = ["chunk", ...]
			if ( is_array( $node ) && isset( $node[4][0][1] ) && is_array( $node[4][0][1] ) ) {
				$parts = array();
				foreach ( $node[4][0][1] as $chunk ) {
					if ( is_string( $chunk ) && '' !== $chunk ) {
						$parts[] = $chunk;
					}
				}
				if ( ! empty( $parts ) ) {
					return implode( '', $parts );
				}
			}

			// Recursive search for the first substantial string list under candidates.
			if ( is_array( $node ) ) {
				foreach ( $node as $child ) {
					$found = self::extract_text_from_inner( $child );
					if ( '' !== $found ) {
						return $found;
					}
				}
			}
			return '';
		}

		/**
		 * Build StreamGenerate form body (OmniRoute / gemini-webapi shaped).
		 *
		 * @param string     $prompt   Prompt text.
		 * @param string     $at       SNlM0e token.
		 * @param array|null $metadata Chat metadata [cid, rid, rcid, …] or null for new chat.
		 * @return array<string,string>
		 */
		private static function build_generate_body( $prompt, $at, $metadata = null ) {
			$language = 'en';
			if ( ! is_array( $metadata ) ) {
				$metadata = self::default_metadata();
			}

			$inner = array_fill( 0, 81, null );
			$inner[0]  = array( $prompt, 0, null, array(), null, null, 0 );
			$inner[1]  = array( $language );
			$inner[2]  = $metadata;
			$inner[6]  = array( 1 );
			$inner[7]  = 1; // streaming flag index used by gemini-webapi.
			$inner[10] = 1;
			$inner[11] = 0;
			$inner[17] = array( array( 0 ) );
			$inner[18] = 0;
			$inner[27] = 1;
			$inner[30] = array( 4 );
			$inner[41] = array( 1 );
			$inner[53] = 0;
			$inner[61] = array();
			$inner[68] = 1;
			$inner[79] = 1;
			$inner[80] = 1;
			$inner[59] = strtoupper( wp_generate_uuid4() );

			return array(
				'at'    => $at,
				'f.req' => wp_json_encode(
					array(
						null,
						wp_json_encode( $inner ),
					)
				),
			);
		}

		/**
		 * Soft-fail / apology reply detection for Gemini Web.
		 *
		 * Matches known Gemini soft-apology phrases, and (when $expect_json)
		 * any reply that is not a parseable JSON object/array — typical of
		 * translate-text batches that must return {"0":"..."}.
		 *
		 * @param string $text        Assistant text.
		 * @param bool   $expect_json When true, non-JSON counts as soft-fail.
		 * @return bool
		 */
		public static function is_soft_fail_reply( $text, $expect_json = false ) {
			$text = trim( (string) $text );
			if ( '' === $text ) {
				return true;
			}

			$clean = preg_replace( '/^\s*```(?:json)?\s*/i', '', $text );
			$clean = preg_replace( '/\s*```\s*$/', '', (string) $clean );
			$clean = trim( (string) $clean );

			$decoded = json_decode( $clean, true );
			if ( is_array( $decoded ) ) {
				return false;
			}

			$lower = strtolower( $text );
			$phrases = array(
				"i'm having a hard time",
				'i am having a hard time',
				'i encountered an error',
				'i seem to be encountering an error',
				'sorry, something went wrong',
				'something went wrong',
				'encountering an error',
				"i'm unable to",
				'i am unable to',
				'having trouble',
				'try again later',
				'i apologize',
				'apologize for the inconvenience',
				'cannot fulfill this request',
				"i can't assist",
				'i cannot assist',
				"i can't help with that",
				'i cannot help with that',
				'ran into an issue',
				'ran into a problem',
				'technical issue',
				'please try again',
			);
			foreach ( $phrases as $phrase ) {
				if ( false !== strpos( $lower, $phrase ) ) {
					return true;
				}
			}

			// Non-JSON when JSON was required (translate-text batches).
			if ( $expect_json ) {
				return true;
			}

			return false;
		}

		/**
		 * Pace ~2s before soft-fail same-chat retry (avoids hammering Gemini).
		 *
		 * @return void
		 */
		private static function pace_before_soft_retry() {
			$us = (int) self::SOFT_RETRY_PACE_US;
			if ( $us > 0 ) {
				usleep( $us );
			}
		}

		/**
		 * Generate a completion / translation via Gemini Web.
		 *
		 * @param string $prompt     Prompt.
		 * @param string $cookie     Optional cookie override; defaults to stored.
		 * @param string $model      Model id (informational / future header mapping).
		 * @param int    $timeout    Timeout seconds.
		 * @param bool   $reuse_chat When true, continue the persisted chat for $chat_scope.
		 * @param string $chat_scope Scope for chat persistence (per bulk/lang; never shared across users).
		 * @param bool   $expect_json When true, non-JSON replies count as soft-fail (translate-text batches).
		 * @return array{ok:bool,text?:string,model?:string,error?:string,code?:int,chat_id?:string,reused?:bool,soft_retried?:bool,soft_fail?:bool}
		 */
		public static function generate( $prompt, $cookie = '', $model = '', $timeout = 60, $reuse_chat = false, $chat_scope = '', $expect_json = false ) {
			$prompt = trim( (string) $prompt );
			if ( '' === $prompt ) {
				return array(
					'ok'    => false,
					'error' => __( 'Empty prompt.', 'translate-words' ),
					'code'  => 400,
				);
			}

			$cookie = '' !== $cookie ? self::normalize_cookie( $cookie ) : self::get_stored_cookie();
			if ( '' === $model ) {
				$model = self::DEFAULT_MODEL;
			}

			$boot = self::bootstrap( $cookie, min( 30, $timeout ) );
			if ( empty( $boot['ok'] ) ) {
				return $boot;
			}

			$metadata  = null;
			$reused    = false;
			if ( $reuse_chat ) {
				$metadata = self::get_chat_metadata( $chat_scope );
				$reused   = is_array( $metadata );
			}

			$result = self::do_stream_generate( $prompt, $cookie, $boot, $metadata, $timeout );
			if ( empty( $result['ok'] ) && $reused ) {
				// Stale/broken chat - drop it and open a fresh conversation once.
				self::clear_chat_metadata( $chat_scope );
				$result = self::do_stream_generate( $prompt, $cookie, $boot, null, $timeout );
				$reused   = false;
				$metadata = null;
			}

			if ( empty( $result['ok'] ) ) {
				return $result;
			}

			// Soft-fail (apology / non-JSON when expect_json): pace ~2s, ONE same-prompt same-chat retry.
			// Max 2 attempts total - never spam 3+.
			$soft_retried = false;
			$soft_fail    = self::is_soft_fail_reply( isset( $result['text'] ) ? $result['text'] : '', $expect_json );
			if ( $soft_fail ) {
				// Keep / refresh chat metadata so the retry stays on the same conversation.
				if ( $reuse_chat && ! empty( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
					self::set_chat_metadata( $result['metadata'], $chat_scope );
					$metadata = $result['metadata'];
					$reused   = true;
				} elseif ( $reuse_chat ) {
					$metadata = self::get_chat_metadata( $chat_scope );
					$reused   = is_array( $metadata );
				}
				self::pace_before_soft_retry();
				$retry = self::do_stream_generate( $prompt, $cookie, $boot, $metadata, $timeout );
				$soft_retried = true;
				if ( ! empty( $retry['ok'] ) ) {
					$result    = $retry;
					$soft_fail = self::is_soft_fail_reply( isset( $result['text'] ) ? $result['text'] : '', $expect_json );
				}
				// No further retries even if still soft-fail.
			}

			if ( empty( $result['ok'] ) ) {
				return $result;
			}

			if ( $reuse_chat && ! empty( $result['metadata'] ) && is_array( $result['metadata'] ) ) {
				self::set_chat_metadata( $result['metadata'], $chat_scope );
			}

			$out = array(
				'ok'           => true,
				'text'         => $result['text'],
				'model'        => $model,
				'reused'       => $reused,
				'soft_retried' => $soft_retried,
				'soft_fail'    => $soft_fail,
			);
			if ( ! empty( $result['metadata'][0] ) && is_string( $result['metadata'][0] ) ) {
				$out['chat_id'] = $result['metadata'][0];
			}
			return $out;
		}

		/**
		 * POST one StreamGenerate turn.
		 *
		 * @param string     $prompt   Prompt.
		 * @param string     $cookie   Cookie header.
		 * @param array      $boot     Bootstrap tokens.
		 * @param array|null $metadata Chat metadata or null.
		 * @param int        $timeout  Timeout.
		 * @return array{ok:bool,text?:string,metadata?:?array,error?:string,code?:int}
		 */
		private static function do_stream_generate( $prompt, $cookie, $boot, $metadata, $timeout ) {
			$params = array(
				'hl'     => 'en',
				'_reqid' => wp_rand( 100000, 999999 ),
				'rt'     => 'c',
			);
			if ( ! empty( $boot['bl'] ) ) {
				$params['bl'] = $boot['bl'];
			}
			if ( ! empty( $boot['sid'] ) ) {
				$params['f.sid'] = $boot['sid'];
			}

			$url = add_query_arg( $params, self::GENERATE_URL );

			$headers = self::browser_headers( $cookie );
			$headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8';
			$headers['Accept']       = '*/*';

			$response = wp_remote_post(
				$url,
				array(
					'headers' => $headers,
					'body'    => self::build_generate_body( $prompt, $boot['at'], $metadata ),
					'timeout' => $timeout,
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'ok'    => false,
					'error' => $response->get_error_message(),
					'code'  => 502,
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );

			if ( 200 !== $code ) {
				return array(
					'ok'    => false,
					'error' => sprintf(
						/* translators: %d: HTTP status */
						__( 'Gemini Web generate failed (HTTP %d). Re-copy a fresh Cookie header from gemini.google.com.', 'translate-words' ),
						$code
					),
					'code'  => $code,
				);
			}

			$parsed = self::parse_stream_payload( $body );
			$text   = isset( $parsed['text'] ) ? (string) $parsed['text'] : '';
			if ( '' === $text ) {
				return array(
					'ok'    => false,
					'error' => __( 'Gemini Web returned an empty response. Try again or refresh your session cookies.', 'translate-words' ),
					'code'  => 502,
				);
			}

			return array(
				'ok'       => true,
				'text'     => $text,
				'metadata' => isset( $parsed['metadata'] ) ? $parsed['metadata'] : null,
			);
		}
	}
}
