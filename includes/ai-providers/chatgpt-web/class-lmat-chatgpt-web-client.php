<?php
/**
 * ChatGPT Web session client (cookie-based).
 *
 * Inspired by OmniRoute's ChatGPT Web HTTP pipeline (PR #1593):
 * cookie → GET /api/auth/session (accessToken) → optional sentinel → POST /backend-api/conversation.
 *
 * Note: OmniRoute's production path also TLS-impersonates Chrome/Firefox (tls-client) or
 * drives a real browser (Playwright). WordPress wp_remote_* often trips Cloudflare, so this
 * client uses PHP curl (Chrome/Firefox UA, HTTP/2 when available, full Cookie header) with
 * session-token / optional cf_clearance settings fields.
 *
 * @package Linguator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LMAT_ChatGPT_Web_Client' ) ) {

	/**
	 * Cookie-authenticated ChatGPT Web translator.
	 */
	class LMAT_ChatGPT_Web_Client {

		const OPTION_COOKIE         = 'lmat_chatgpt_web_session';
		const OPTION_SESSION_TOKEN  = 'lmat_chatgpt_web_session_token';
		const OPTION_CF_CLEARANCE   = 'lmat_chatgpt_web_cf_clearance';
		const BASE_URL      = 'https://chatgpt.com';
		const DEFAULT_MODEL = 'gpt-5-5-instant';

		const OAI_CLIENT_VERSION      = 'prod-81e0c5cdf6140e8c5db714d613337f4aeab94029';
		const OAI_CLIENT_BUILD_NUMBER = '6128297';

		/**
		 * Models for settings / test UI → ChatGPT internal slug.
		 *
		 * @return array<string,string>
		 */
		public static function models() {
			return array(
				'gpt-5-5-instant'  => __( 'GPT-5.5 Instant (Recommended)', 'translate-words' ),
				'gpt-5-5-thinking' => __( 'GPT-5.5 Thinking', 'translate-words' ),
				'gpt-5-5-pro'      => __( 'GPT-5.5 Pro', 'translate-words' ),
				'gpt-5-6'          => __( 'GPT-5.6 Sol Instant', 'translate-words' ),
				'gpt-5-6-thinking' => __( 'GPT-5.6 Sol Thinking', 'translate-words' ),
			);
		}

		/**
		 * Map UI model id to ChatGPT web slug.
		 *
		 * @param string $model Model id.
		 * @return string
		 */
		public static function model_slug( $model ) {
			$map = array(
				'gpt-5-5-instant'  => 'gpt-5-5',
				'gpt-5-5-thinking' => 'gpt-5-5',
				'gpt-5-5'          => 'gpt-5-5',
				'gpt-5-5-pro'      => 'gpt-5-5-pro',
				'gpt-5-6'          => 'gpt-5-6',
				'gpt-5-6-thinking' => 'gpt-5-6',
				'gpt-5-6-pro'      => 'gpt-5-6-pro',
				'gpt-5-3'          => 'gpt-5-3',
				'gpt-4o'           => 'gpt-4o',
				'auto'             => 'auto',
			);
			$model = strtolower( trim( (string) $model ) );
			$model = str_replace( '.', '-', $model );
			return isset( $map[ $model ] ) ? $map[ $model ] : 'gpt-5-5';
		}

		/**
		 * Normalize pasted cookie / bare token into Cookie header value.
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
			if ( preg_match( '/__Secure-next-auth\.session-token(?:\.\d+)?\s*=/', $raw ) ) {
				return $raw;
			}
			return '__Secure-next-auth.session-token=' . $raw;
		}

		/**
		 * Whether cookie paste looks usable.
		 *
		 * @param string $cookie Cookie header.
		 * @return bool
		 */
		public static function cookie_looks_valid( $cookie ) {
			$cookie = self::normalize_cookie( $cookie );
			if ( '' === $cookie ) {
				return false;
			}
			return (bool) preg_match( '/__Secure-next-auth\.session-token(?:\.\d+)?\s*=\s*[^;\s]+/', $cookie );
		}

		/**
		 * Extract one cookie value by name from a Cookie header string.
		 *
		 * @param string $cookie Cookie header.
		 * @param string $name   Cookie name (literal; dots allowed).
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
		 * @return array{session_token:string,cf_clearance:string}
		 */
		public static function parse_cookie_parts( $cookie ) {
			$cookie = self::normalize_cookie( $cookie );
			$token  = self::extract_cookie_value( $cookie, '__Secure-next-auth.session-token' );
			if ( '' === $token ) {
				// Chunked session tokens (.0 / .1) — prefer .0 then bare.
				$token = self::extract_cookie_value( $cookie, '__Secure-next-auth.session-token.0' );
			}
			return array(
				'session_token' => $token,
				'cf_clearance'  => self::extract_cookie_value( $cookie, 'cf_clearance' ),
			);
		}

		/**
		 * Build Cookie header from session-token + optional cf_clearance.
		 *
		 * @param string $session_token __Secure-next-auth.session-token value or full cookie paste.
		 * @param string $cf_clearance  Optional cf_clearance value or full cookie paste.
		 * @return string
		 */
		public static function build_cookie_from_parts( $session_token, $cf_clearance = '' ) {
			$session_token = trim( (string) $session_token );
			$cf_clearance  = trim( (string) $cf_clearance );

			$merged = '';
			if ( false !== strpos( $session_token, 'session-token' ) || false !== strpos( $session_token, 'cf_clearance' ) ) {
				$merged = $session_token;
			}
			if ( false !== strpos( $cf_clearance, 'session-token' ) || false !== strpos( $cf_clearance, 'cf_clearance=' ) ) {
				$merged = '' !== $merged ? $merged . '; ' . $cf_clearance : $cf_clearance;
			}

			// Full paste that already has chunked session-token.0/.1: keep those pairs intact.
			if ( '' !== $merged && preg_match( '/__Secure-next-auth\.session-token\.\d+\s*=/', $merged ) ) {
				$kept = array();
				if ( preg_match_all( '/(__Secure-next-auth\.session-token(?:\.\d+)?)\s*=\s*([^;]*)/', $merged, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $pair ) {
						$val = trim( $pair[2] );
						if ( '' !== $val ) {
							$kept[ $pair[1] ] = $pair[1] . '=' . $val;
						}
					}
				}
				$cf = self::extract_cookie_value( $merged, 'cf_clearance' );
				if ( '' === $cf && '' !== $cf_clearance && false === strpos( $cf_clearance, '=' ) ) {
					$cf = $cf_clearance;
				} elseif ( '' === $cf ) {
					$cf = self::extract_cookie_value( self::normalize_cookie( $cf_clearance ), 'cf_clearance' );
				}
				$out = array_values( $kept );
				if ( '' !== $cf ) {
					$out[] = 'cf_clearance=' . $cf;
				}
				return implode( '; ', $out );
			}

			if ( '' !== $merged ) {
				$parts         = self::parse_cookie_parts( $merged );
				$session_token = $parts['session_token'] !== '' ? $parts['session_token'] : $session_token;
				$cf_clearance  = $parts['cf_clearance'] !== '' ? $parts['cf_clearance'] : $cf_clearance;
			}

			if ( 0 === stripos( $session_token, '__Secure-next-auth.session-token=' ) ) {
				$session_token = substr( $session_token, strlen( '__Secure-next-auth.session-token=' ) );
			}
			if ( 0 === stripos( $cf_clearance, 'cf_clearance=' ) ) {
				$cf_clearance = substr( $cf_clearance, strlen( 'cf_clearance=' ) );
			}

			$parts = array();
			if ( '' !== $session_token ) {
				$parts[] = '__Secure-next-auth.session-token=' . $session_token;
			}
			if ( '' !== $cf_clearance ) {
				$parts[] = 'cf_clearance=' . $cf_clearance;
			}
			return implode( '; ', $parts );
		}

		/**
		 * Stored settings field values.
		 *
		 * @return array{session_token:string,cf_clearance:string}
		 */
		public static function get_stored_parts() {
			$token = trim( (string) get_option( self::OPTION_SESSION_TOKEN, '' ) );
			$cf    = trim( (string) get_option( self::OPTION_CF_CLEARANCE, '' ) );

			return array(
				'session_token' => $token,
				'cf_clearance'  => $cf,
			);
		}

		/**
		 * Persist separate fields and rebuild the combined Cookie option.
		 *
		 * @param string $session_token Session token value.
		 * @param string $cf_clearance  Optional cf_clearance.
		 * @return string Built cookie header.
		 */
		public static function save_cookie_parts( $session_token, $cf_clearance = '' ) {
			$cookie = self::build_cookie_from_parts( $session_token, $cf_clearance );
			$parts  = self::parse_cookie_parts( $cookie );

			update_option( self::OPTION_SESSION_TOKEN, $parts['session_token'] );
			update_option( self::OPTION_CF_CLEARANCE, $parts['cf_clearance'] );
			update_option( self::OPTION_COOKIE, $cookie );

			return $cookie;
		}

		/**
		 * Clear all ChatGPT Web auth-related options.
		 *
		 * @return void
		 */
		public static function clear_stored_auth() {
			delete_option( self::OPTION_COOKIE );
			delete_option( self::OPTION_SESSION_TOKEN );
			delete_option( self::OPTION_CF_CLEARANCE );
		}

		/**
		 * Stored session cookie. Prefers separate fields; falls back to legacy combined.
		 *
		 * @return string
		 */
		public static function get_stored_cookie() {
			$combined = self::normalize_cookie( (string) get_option( self::OPTION_COOKIE, '' ) );
			// Prefer stored Cookie header when it still carries chunked session-token.N pairs.
			if ( '' !== $combined && preg_match( '/__Secure-next-auth\.session-token\.\d+\s*=/', $combined ) ) {
				return $combined;
			}
			$parts = self::get_stored_parts();
			if ( '' !== $parts['session_token'] || '' !== $parts['cf_clearance'] ) {
				return self::build_cookie_from_parts( $parts['session_token'], $parts['cf_clearance'] );
			}
			return $combined;
		}

		/**
		 * Stable device id derived from cookie.
		 *
		 * @param string $cookie Cookie.
		 * @return string
		 */
		private static function device_id( $cookie ) {
			$h = hash( 'sha256', $cookie );
			return sprintf(
				'%s-%s-4%s-%s%s-%s',
				substr( $h, 0, 8 ),
				substr( $h, 8, 4 ),
				substr( $h, 13, 3 ),
				dechex( ( hexdec( substr( $h, 16, 1 ) ) & 0x3 ) | 0x8 ),
				substr( $h, 17, 3 ),
				substr( $h, 20, 12 )
			);
		}

		/**
		 * Browser-like headers.
		 *
		 * @param string $cookie Cookie header.
		 * @return array<string,string>
		 */
		private static function browser_headers( $cookie ) {
			return array(
				'User-Agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
				'Accept'             => '*/*',
				'Accept-Language'    => 'en-US,en;q=0.9',
				'Cache-Control'      => 'no-cache',
				'Pragma'             => 'no-cache',
				'Origin'             => self::BASE_URL,
				'Referer'            => self::BASE_URL . '/',
				'Sec-Ch-Ua'          => '"Not;A=Brand";v="99", "Google Chrome";v="128", "Chromium";v="128"',
				'Sec-Ch-Ua-Mobile'   => '?0',
				'Sec-Ch-Ua-Platform' => '"Windows"',
				'Sec-Fetch-Dest'     => 'empty',
				'Sec-Fetch-Mode'     => 'cors',
				'Sec-Fetch-Site'     => 'same-origin',
				'Cookie'             => $cookie,
			);
		}


		/**
		 * Browser-like HTTP via PHP curl (avoids wp_remote_* Cloudflare blocks).
		 *
		 * Prefers ext-curl with Chrome/Firefox UA already in $headers, HTTP/2 when
		 * available, and the full Cookie header. Falls back to shell curl.exe.
		 *
		 * @param string               $method      GET|POST.
		 * @param string               $url         Absolute URL.
		 * @param array<string,string> $headers     Header map (includes Cookie).
		 * @param string|null          $body        Request body for POST.
		 * @param int                  $timeout     Timeout seconds.
		 * @param int                  $redirection Max redirects (0 = none).
		 * @return array{ok:bool,code:int,body:string,headers:array<string,string>,error?:string}
		 */
		private static function http_request( $method, $url, array $headers = array(), $body = null, $timeout = 30, $redirection = 5 ) {
			$method      = strtoupper( (string) $method );
			$url         = (string) $url;
			$timeout     = max( 1, (int) $timeout );
			$redirection = max( 0, (int) $redirection );
			$body        = null === $body ? null : (string) $body;

			// On Windows Local WP, shell curl.exe often passes Cloudflare where PHP
			// libcurl is fingerprint-blocked. Prefer curl.exe there; elsewhere prefer ext-curl.
			// Local's php-cgi PATH often omits System32, so shell curl must resolve an absolute path.
			$prefer_shell = defined( 'PHP_WINDOWS_VERSION_BUILD' );

			if ( $prefer_shell ) {
				$shell = self::http_request_shell_curl( $method, $url, $headers, $body, $timeout, $redirection );
				// Treat missing HTTP status (code 0) as transport failure so PHP curl can run.
				if ( ! empty( $shell['ok'] ) && (int) $shell['code'] > 0 ) {
					return $shell;
				}
				if ( function_exists( 'curl_init' ) ) {
					$php = self::http_request_curl( $method, $url, $headers, $body, $timeout, $redirection );
					if ( ! empty( $php['ok'] ) && (int) $php['code'] > 0 ) {
						return $php;
					}
					// Prefer the more specific error from whichever transport had detail.
					if ( empty( $php['ok'] ) && ! empty( $php['error'] ) ) {
						if ( empty( $shell['ok'] ) || (int) $shell['code'] <= 0 ) {
							$php['error'] = trim(
								( isset( $shell['error'] ) ? (string) $shell['error'] . '; ' : '' ) . (string) $php['error'],
								'; '
							);
						}
						return $php;
					}
					return $php;
				}
				return $shell;
			}

			if ( function_exists( 'curl_init' ) ) {
				return self::http_request_curl( $method, $url, $headers, $body, $timeout, $redirection );
			}

			return self::http_request_shell_curl( $method, $url, $headers, $body, $timeout, $redirection );
		}

		/**
		 * PHP ext-curl transport.
		 *
		 * @param string               $method      Method.
		 * @param string               $url         URL.
		 * @param array<string,string> $headers     Headers.
		 * @param string|null          $body        Body.
		 * @param int                  $timeout     Timeout.
		 * @param int                  $redirection Redirects.
		 * @return array{ok:bool,code:int,body:string,headers:array<string,string>,error?:string}
		 */
		private static function http_request_curl( $method, $url, array $headers, $body, $timeout, $redirection ) {
			$ch = curl_init( $url );
			if ( false === $ch ) {
				return array(
					'ok'      => false,
					'code'    => 0,
					'body'    => '',
					'headers' => array(),
					'error'   => 'curl_init failed',
				);
			}

			$header_lines = array();
			foreach ( $headers as $name => $value ) {
				$header_lines[] = $name . ': ' . $value;
			}

			$opts = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_FOLLOWLOCATION => $redirection > 0,
				CURLOPT_MAXREDIRS      => $redirection,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => min( 30, $timeout ),
				CURLOPT_HTTPHEADER     => $header_lines,
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_ENCODING       => '',
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			);

			// HTTP/1.1 by default — PHP libcurl HTTP/2 is frequently CF-challenged.
			// Set LMAT_CHATGPT_WEB_HTTP2=1 to force HTTP/2 when available.
			$force_http2 = ( '1' === (string) getenv( 'LMAT_CHATGPT_WEB_HTTP2' ) );
			if ( $force_http2 && defined( 'CURL_HTTP_VERSION_2TLS' ) ) {
				$opts[ CURLOPT_HTTP_VERSION ] = CURL_HTTP_VERSION_2TLS;
			} elseif ( $force_http2 && defined( 'CURL_HTTP_VERSION_2_0' ) ) {
				$opts[ CURLOPT_HTTP_VERSION ] = CURL_HTTP_VERSION_2_0;
			} else {
				$opts[ CURLOPT_HTTP_VERSION ] = CURL_HTTP_VERSION_1_1;
			}

			$ca = ini_get( 'curl.cainfo' );
			if ( ! $ca ) {
				$ca = ini_get( 'openssl.cafile' );
			}
			if ( $ca && is_readable( $ca ) ) {
				$opts[ CURLOPT_CAINFO ] = $ca;
			}

			if ( 'POST' === $method || 'PUT' === $method || 'PATCH' === $method ) {
				$opts[ CURLOPT_POSTFIELDS ] = null === $body ? '' : $body;
			}

			curl_setopt_array( $ch, $opts );

			$raw         = curl_exec( $ch );
			$err         = curl_error( $ch );
			$errno       = (int) curl_errno( $ch );
			$code        = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			curl_close( $ch );

			if ( false === $raw ) {
				return array(
					'ok'        => false,
					'code'      => $code ? $code : 0,
					'body'      => '',
					'headers'   => array(),
					'error'     => ( $err ? $err : 'curl_exec failed' ) . ( $errno ? ( ' (errno ' . $errno . ')' ) : '' ),
					'transport' => 'php-curl',
				);
			}

			$raw_headers = substr( $raw, 0, $header_size );
			$resp_body   = substr( $raw, $header_size );
			$parsed      = self::parse_raw_headers( $raw_headers );

			return array(
				'ok'      => true,
				'code'    => $code,
				'body'    => (string) $resp_body,
				'headers'   => $parsed,
				'transport' => 'php-curl',
			);
		}

		/**
		 * Resolve an absolute curl.exe / curl binary path.
		 *
		 * Local WP php-cgi on Windows often has a PATH that only includes ImageMagick
		 * and Ghostscript - bare "curl.exe" then fails with exit 1 and no HTTP response
		 * (reported as HTTP 0). Prefer well-known System32 locations, then PATH lookup.
		 *
		 * @return string Absolute path or bare binary name as last resort.
		 */
		private static function resolve_curl_binary() {
			static $cached = null;
			if ( null !== $cached ) {
				return $cached;
			}

			$candidates = array();
			if ( defined( 'PHP_WINDOWS_VERSION_BUILD' ) ) {
				$sys = getenv( 'SystemRoot' );
				if ( ! $sys ) {
					$sys = getenv( 'WINDIR' );
				}
				if ( ! $sys ) {
					$sys = 'C:\\Windows';
				}
				$candidates[] = $sys . '\\System32\\curl.exe';
				$candidates[] = $sys . '\\SysWOW64\\curl.exe';
				$candidates[] = 'C:\\Windows\\System32\\curl.exe';
			} else {
				$candidates[] = '/usr/bin/curl';
				$candidates[] = '/usr/local/bin/curl';
			}

			foreach ( $candidates as $bin ) {
				if ( $bin && is_file( $bin ) && is_executable( $bin ) ) {
					$cached = $bin;
					return $cached;
				}
				// is_executable is unreliable on some Windows PHP builds — file existence is enough.
				if ( $bin && is_file( $bin ) ) {
					$cached = $bin;
					return $cached;
				}
			}

			// PATH lookup (works in full CLI shells; often fails under Local php-cgi).
			$probe = defined( 'PHP_WINDOWS_VERSION_BUILD' ) ? 'where curl.exe' : 'command -v curl';
			$out   = array();
			$code  = 1;
			@exec( $probe . ' 2>&1', $out, $code );
			if ( 0 === (int) $code && ! empty( $out[0] ) ) {
				$found = trim( (string) $out[0] );
				if ( $found && is_file( $found ) ) {
					$cached = $found;
					return $cached;
				}
			}

			$cached = defined( 'PHP_WINDOWS_VERSION_BUILD' ) ? 'curl.exe' : 'curl';
			return $cached;
		}

		/**
		 * Writable directory for curl --config / body temp files.
		 *
		 * Prefer WP uploads (always writable under Local), then sys temp.
		 *
		 * @return string
		 */
		private static function curl_temp_dir() {
			$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : null;
			if ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
				$dir = trailingslashit( $uploads['basedir'] ) . 'lmat-chatgpt-web-tmp';
				if ( ! is_dir( $dir ) ) {
					wp_mkdir_p( $dir );
				}
				if ( is_dir( $dir ) && is_writable( $dir ) ) {
					return $dir;
				}
			}
			$tmp = sys_get_temp_dir();
			return $tmp ? $tmp : ( defined( 'PHP_WINDOWS_VERSION_BUILD' ) ? 'C:\\Windows\\TEMP' : '/tmp' );
		}

		/**
		 * Create a temp file path in curl_temp_dir().
		 *
		 * @param string $prefix Filename prefix.
		 * @return string|false
		 */
		private static function curl_tempnam( $prefix ) {
			$dir = self::curl_temp_dir();
			$file = @tempnam( $dir, $prefix );
			if ( $file ) {
				return $file;
			}
			// Fallback to WP helper (may land in sys temp).
			return function_exists( 'wp_tempnam' ) ? wp_tempnam( $prefix ) : false;
		}

		/**
		 * Shell curl.exe fallback (Windows Local WP).
		 *
		 * @param string               $method      Method.
		 * @param string               $url         URL.
		 * @param array<string,string> $headers     Headers.
		 * @param string|null          $body        Body.
		 * @param int                  $timeout     Timeout.
		 * @param int                  $redirection Redirects.
		 * @return array{ok:bool,code:int,body:string,headers:array<string,string>,error?:string}
		 */
		private static function http_request_shell_curl( $method, $url, array $headers, $body, $timeout, $redirection ) {
			$curl_bin = self::resolve_curl_binary();

			$config_file = self::curl_tempnam( 'lmatcgwcfg' );
			$body_file   = '';
			$stderr_file = self::curl_tempnam( 'lmatcgwerr' );
			if ( ! $config_file ) {
				return array(
					'ok'      => false,
					'code'    => 0,
					'body'    => '',
					'headers' => array(),
					'error'   => 'Unable to write temp curl config (dir not writable)',
				);
			}

			$lines   = array();
			$lines[] = 'silent';
			$lines[] = 'show-error';
			$lines[] = 'include';
			$lines[] = 'max-time = ' . (int) $timeout;
			$lines[] = 'connect-timeout = ' . (int) min( 30, $timeout );
			$lines[] = 'request = "' . addcslashes( $method, '\\"' ) . '"';
			if ( defined( 'PHP_WINDOWS_VERSION_BUILD' ) ) {
				// Schannel on some Windows builds stalls on cert revocation checks.
				$lines[] = 'ssl-no-revoke';
			}
			if ( $redirection > 0 ) {
				$lines[] = 'location';
				$lines[] = 'max-redirs = ' . (int) $redirection;
			} else {
				$lines[] = 'max-redirs = 0';
			}

			// Prefer HTTP/2 when this curl build supports it (probe with absolute binary).
			$http2_probe = array();
			$http2_code  = 1;
			@exec( escapeshellarg( $curl_bin ) . ' --help 2>&1', $http2_probe, $http2_code );
			if ( 0 === (int) $http2_code && false !== stripos( implode( "\n", $http2_probe ), '--http2' ) ) {
				$lines[] = 'http2';
			}

			foreach ( $headers as $name => $value ) {
				$lines[] = 'header = "' . addcslashes( $name . ': ' . $value, '\\"' ) . '"';
			}

			if ( null !== $body && '' !== $body ) {
				$body_file = self::curl_tempnam( 'lmatcgwbody' );
				if ( ! $body_file || false === file_put_contents( $body_file, $body ) ) {
					@unlink( $config_file );
					if ( $stderr_file ) {
						@unlink( $stderr_file );
					}
					return array(
						'ok'      => false,
						'code'    => 0,
						'body'    => '',
						'headers' => array(),
						'error'   => 'Unable to write temp body for curl.exe',
					);
				}
				// curl -K config: data-binary = @file (forward slashes OK on Windows curl).
				$lines[] = 'data-binary = "@' . str_replace( '\\', '/', $body_file ) . '"';
			}

			$lines[] = 'url = "' . addcslashes( $url, '\\"' ) . '"';
			if ( false === file_put_contents( $config_file, implode( "\n", $lines ) . "\n" ) ) {
				@unlink( $config_file );
				if ( $body_file && file_exists( $body_file ) ) {
					@unlink( $body_file );
				}
				if ( $stderr_file ) {
					@unlink( $stderr_file );
				}
				return array(
					'ok'      => false,
					'code'    => 0,
					'body'    => '',
					'headers' => array(),
					'error'   => 'Unable to write curl config file',
				);
			}

			// Redirect stderr to a file so stdout stays parseable as headers+body.
			$stderr_redirect = $stderr_file ? ( ' 2>' . escapeshellarg( $stderr_file ) ) : ' 2>&1';
			$cmd             = escapeshellarg( $curl_bin ) . ' --config ' . escapeshellarg( $config_file ) . $stderr_redirect;
			$output          = array();
			$code            = 0;
			@exec( $cmd, $output, $code );

			$stderr = '';
			if ( $stderr_file && file_exists( $stderr_file ) ) {
				$stderr = trim( (string) file_get_contents( $stderr_file ) );
				@unlink( $stderr_file );
			}

			@unlink( $config_file );
			if ( $body_file && file_exists( $body_file ) ) {
				@unlink( $body_file );
			}

			$raw = implode( "\n", $output );

			if ( 0 !== (int) $code && '' === $raw ) {
				$err = $stderr ? $stderr : ( 'curl.exe failed (exit ' . $code . ', bin=' . $curl_bin . ')' );
				return array(
					'ok'        => false,
					'code'      => 0,
					'body'      => '',
					'headers'   => array(),
					'error'     => $err,
					'transport' => 'curl.exe',
				);
			}

			$parts       = preg_split( "/\r\n\r\n|\n\n/", $raw, 2 );
			$raw_headers = isset( $parts[0] ) ? $parts[0] : '';
			$resp_body   = isset( $parts[1] ) ? $parts[1] : '';
			if ( preg_match_all( '/^HTTP\/\d(?:\.\d)?\s+(\d+)/mi', $raw_headers, $m ) ) {
				$http_code = (int) end( $m[1] );
			} else {
				$http_code = 0;
			}

			// No HTTP status line → transport failure (e.g. "curl.exe is not recognized").
			if ( $http_code <= 0 ) {
				$err = $stderr;
				if ( '' === $err ) {
					$err = trim( $raw );
				}
				if ( '' === $err ) {
					$err = 'curl.exe returned no HTTP status (exit ' . (int) $code . ', bin=' . $curl_bin . ')';
				}
				return array(
					'ok'        => false,
					'code'      => 0,
					'body'      => '',
					'headers'   => array(),
					'error'     => $err,
					'transport' => 'curl.exe',
				);
			}

			return array(
				'ok'        => true,
				'code'      => $http_code,
				'body'      => (string) $resp_body,
				'headers'   => self::parse_raw_headers( $raw_headers ),
				'transport' => 'curl.exe',
			);
		}
		private static function parse_raw_headers( $raw ) {
			$out   = array();
			$lines = preg_split( "/\r\n|\n|\r/", (string) $raw );
			if ( ! is_array( $lines ) ) {
				return $out;
			}
			foreach ( $lines as $line ) {
				if ( false === strpos( $line, ':' ) ) {
					continue;
				}
				list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
				$key = strtolower( $name );
				if ( '' === $key ) {
					continue;
				}
				if ( isset( $out[ $key ] ) ) {
					$out[ $key ] .= ', ' . $value;
				} else {
					$out[ $key ] = $value;
				}
			}
			return $out;
		}

		/**
		 * OAI request headers.
		 *
		 * @param string $session_id Session UUID.
		 * @param string $device_id  Device UUID.
		 * @return array<string,string>
		 */
		private static function oai_headers( $session_id, $device_id ) {
			return array(
				'OAI-Language'            => 'en-US',
				'OAI-Device-Id'           => $device_id,
				'OAI-Client-Version'      => self::OAI_CLIENT_VERSION,
				'OAI-Client-Build-Number' => self::OAI_CLIENT_BUILD_NUMBER,
				'OAI-Session-Id'          => $session_id,
			);
		}

		/**
		 * Detect Cloudflare challenge HTML/JSON.
		 *
		 * @param string $body Response body.
		 * @param array  $headers Response headers.
		 * @return bool
		 */
		private static function is_cloudflare_block( $body, $headers = array() ) {
			$mitigated = '';
			if ( is_array( $headers ) ) {
				foreach ( $headers as $k => $v ) {
					if ( 'cf-mitigated' === strtolower( (string) $k ) ) {
						$mitigated = is_array( $v ) ? implode( ',', $v ) : (string) $v;
					}
				}
			}
			if ( $mitigated && false !== stripos( $mitigated, 'challenge' ) ) {
				return true;
			}
			$body = (string) $body;
			return (bool) preg_match( '/cf-browser-verification|Just a moment|Attention Required|cf-challenge|cloudflare/i', $body );
		}

		/**
		 * Exchange session cookie for accessToken.
		 *
		 * @param string $cookie  Cookie header.
		 * @param int    $timeout Timeout.
		 * @return array{ok:bool,accessToken?:string,accountId?:string,error?:string,code?:int,cf?:bool}
		 */
		public static function exchange_session( $cookie, $timeout = 30 ) {
			$cookie = self::normalize_cookie( $cookie );
			if ( ! self::cookie_looks_valid( $cookie ) ) {
				return array(
					'ok'    => false,
					'error' => __( 'Paste a ChatGPT Cookie header that includes __Secure-next-auth.session-token (or .0/.1 chunks). Copy it from DevTools → Network → a chatgpt.com request → Request Headers → Cookie.', 'translate-words' ),
					'code'  => 401,
				);
			}

			$headers           = self::browser_headers( $cookie );
			$headers['Accept'] = 'application/json';

			$response = self::http_request( 'GET', self::BASE_URL . '/api/auth/session', $headers, null, $timeout, 0 );

			if ( empty( $response['ok'] ) ) {
				return array(
					'ok'    => false,
					'error' => isset( $response['error'] ) ? $response['error'] : __( 'HTTP request failed.', 'translate-words' ),
					'code'  => 502,
				);
			}

			$code = (int) $response['code'];
			$body = (string) $response['body'];
			$hdrs = isset( $response['headers'] ) && is_array( $response['headers'] ) ? $response['headers'] : array();

			if ( self::is_cloudflare_block( $body, $hdrs ) ) {
				return array(
					'ok'    => false,
					'cf'    => true,
					'code'  => $code ? $code : 403,
					'error' => __( 'Cloudflare blocked the ChatGPT Web session request from this server. Even with direct curl, Cloudflare may still challenge this server. Paste a fresh __Secure-next-auth.session-token (and cf_clearance if present) from chatgpt.com on this machine/network.', 'translate-words' ),
				);
			}

			if ( 401 === $code || 403 === $code ) {
				return array(
					'ok'    => false,
					'code'  => $code,
					'error' => __( 'ChatGPT rejected the session cookie (expired or incomplete). Sign in at chatgpt.com and paste a fresh Cookie header from a live Network request.', 'translate-words' ),
				);
			}

			if ( $code < 200 || $code >= 400 ) {
				$detail = '';
				if ( 0 === $code && ! empty( $response['error'] ) ) {
					$detail = ' ' . (string) $response['error'];
				} elseif ( 0 === $code && ! empty( $response['transport'] ) ) {
					$detail = ' transport=' . (string) $response['transport'];
				}
				return array(
					'ok'    => false,
					'code'  => $code,
					'error' => sprintf(
						/* translators: %d: HTTP status */
						__( 'ChatGPT session exchange failed (HTTP %d).', 'translate-words' ),
						$code
					) . $detail,
				);
			}

			$data = json_decode( $body, true );
			if ( ! is_array( $data ) || empty( $data['accessToken'] ) ) {
				return array(
					'ok'    => false,
					'code'  => 401,
					'error' => __( 'Session response had no accessToken — cookie likely expired. Paste a fresh Cookie header from chatgpt.com.', 'translate-words' ),
				);
			}

			return array(
				'ok'          => true,
				'accessToken' => (string) $data['accessToken'],
				'accountId'   => isset( $data['user']['id'] ) ? (string) $data['user']['id'] : '',
			);
		}

		/**
		 * SHA3-512 PoW (OmniRoute / chat2api style). Falls back to unsolved token.
		 *
		 * @param string $seed       Seed.
		 * @param string $difficulty Difficulty hex prefix.
		 * @param array  $config     Prekey config.
		 * @param string $prefix     Token prefix.
		 * @return string
		 */
		private static function solve_pow( $seed, $difficulty, array $config, $prefix = 'gAAAAAB' ) {
			$target   = strtolower( (string) $difficulty );
			$max_iter = 50000;
			$cfg      = $config;

			for ( $i = 0; $i < $max_iter; $i++ ) {
				$cfg[3] = $i;
				$b64    = base64_encode( wp_json_encode( $cfg ) );
				$hash   = hash( 'sha3-512', $seed . $b64 );
				if ( '' === $target || substr( $hash, 0, strlen( $target ) ) <= $target ) {
					return $prefix . $b64;
				}
			}

			return $prefix . base64_encode( wp_json_encode( $cfg ) );
		}

		/**
		 * Build fingerprint config for sentinel prekey.
		 *
		 * @param string $ua User agent.
		 * @return array
		 */
		private static function prekey_config( $ua ) {
			return array(
				1920,
				gmdate( 'D M j Y H:i:s \G\M\T+0000 (Coordinated Universal Time)' ),
				4294705152,
				0,
				$ua,
				self::BASE_URL . '/_next/static/chunks/webpack.js',
				'dpl=' . str_replace( 'prod-', '', self::OAI_CLIENT_VERSION ),
				'en-US',
				'en-US,en',
				0,
				"webdriver\xE2\x88\x92false",
				'location',
				'webpackChunk_N_E',
				(float) ( microtime( true ) * 1000 ),
				wp_generate_uuid4(),
				'',
				8,
				(int) ( time() * 1000 ),
			);
		}

		/**
		 * Fetch chat-requirements token when available.
		 *
		 * @param string $access_token Bearer.
		 * @param string $cookie       Cookie.
		 * @param string $session_id   Session id.
		 * @param string $device_id    Device id.
		 * @param string $account_id   Account id.
		 * @param int    $timeout      Timeout.
		 * @return array{token?:string,proof?:string}
		 */
		private static function chat_requirements( $access_token, $cookie, $session_id, $device_id, $account_id, $timeout ) {
			$ua     = self::browser_headers( $cookie )['User-Agent'];
			$config = self::prekey_config( $ua );
			$prekey = self::solve_pow( '', '0fffff', $config, 'gAAAAAC' );

			$headers = array_merge(
				self::browser_headers( $cookie ),
				self::oai_headers( $session_id, $device_id ),
				array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
					'Accept'        => '*/*',
				)
			);
			if ( $account_id ) {
				$headers['Chatgpt-Account-Id'] = $account_id;
			}

			$prep = self::http_request(
				'POST',
				self::BASE_URL . '/backend-api/sentinel/chat-requirements/prepare',
				$headers,
				wp_json_encode( array( 'p' => $prekey ) ),
				$timeout
			);

			$out = array();
			if ( empty( $prep['ok'] ) ) {
				return $out;
			}

			$prep_body = json_decode( (string) $prep['body'], true );
			if ( ! is_array( $prep_body ) ) {
				return $out;
			}

			$cr_body = array( 'p' => $prekey );
			if ( ! empty( $prep_body['prepare_token'] ) ) {
				$cr_body['prepare_token'] = $prep_body['prepare_token'];
			}

			$cr = self::http_request(
				'POST',
				self::BASE_URL . '/backend-api/sentinel/chat-requirements',
				$headers,
				wp_json_encode( $cr_body ),
				$timeout
			);

			$data = empty( $cr['ok'] ) ? $prep_body : json_decode( (string) $cr['body'], true );
			if ( ! is_array( $data ) ) {
				$data = $prep_body;
			}

			if ( ! empty( $data['token'] ) ) {
				$out['token'] = (string) $data['token'];
			} elseif ( ! empty( $data['prepare_token'] ) ) {
				$out['token'] = (string) $data['prepare_token'];
			}

			if ( ! empty( $data['proofofwork']['required'] ) && ! empty( $data['proofofwork']['seed'] ) ) {
				$out['proof'] = self::solve_pow(
					(string) $data['proofofwork']['seed'],
					(string) ( $data['proofofwork']['difficulty'] ?? '' ),
					$config,
					'gAAAAAB'
				);
			}

			return $out;
		}

		/**
		 * Parse ChatGPT conversation SSE / JSON body into assistant text.
		 *
		 * @param string $raw Raw body.
		 * @return string
		 */
		public static function parse_conversation_body( $raw ) {
			$raw = (string) $raw;
			if ( '' === $raw ) {
				return '';
			}

			$last      = '';
			$message_id = '';
			$lines     = preg_split( "/\r\n|\n|\r/", $raw );
			if ( ! is_array( $lines ) ) {
				$lines = array( $raw );
			}

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || 'data: [DONE]' === $line ) {
					continue;
				}
				if ( 0 === strpos( $line, 'data:' ) ) {
					$line = trim( substr( $line, 5 ) );
				}
				if ( '' === $line || '[' === $line[0] ) {
					continue;
				}
				$evt = json_decode( $line, true );
				if ( ! is_array( $evt ) ) {
					continue;
				}
				$msg = isset( $evt['message'] ) && is_array( $evt['message'] ) ? $evt['message'] : null;
				if ( ! $msg ) {
					continue;
				}
				$role = $msg['author']['role'] ?? '';
				if ( 'assistant' !== $role ) {
					continue;
				}
				$mid = isset( $msg['id'] ) ? (string) $msg['id'] : '';
				if ( $mid && $message_id && $mid !== $message_id ) {
					// New turn — reset accumulator (OmniRoute SSE quirk).
					$last = '';
				}
				if ( $mid ) {
					$message_id = $mid;
				}
				$parts = $msg['content']['parts'] ?? null;
				if ( is_array( $parts ) ) {
					$text = '';
					foreach ( $parts as $part ) {
						if ( is_string( $part ) ) {
							$text .= $part;
						}
					}
					if ( '' !== $text ) {
						$last = $text;
					}
				}
			}

			if ( '' === $last ) {
				$json = json_decode( $raw, true );
				if ( is_array( $json ) && ! empty( $json['message']['content']['parts'][0] ) && is_string( $json['message']['content']['parts'][0] ) ) {
					$last = $json['message']['content']['parts'][0];
				}
			}

			// Strip internal entity chips ChatGPT embeds.
			$last = preg_replace( '/entity\["[^"]*"(?:,"[^"]*")*\]/', '', (string) $last );

			return trim( (string) $last );
		}

		/**
		 * Generate text via ChatGPT Web conversation endpoint.
		 *
		 * @param string $prompt  Prompt.
		 * @param string $cookie  Optional cookie.
		 * @param string $model   Model id.
		 * @param int    $timeout Timeout.
		 * @return array{ok:bool,text?:string,model?:string,error?:string,code?:int,cf?:bool}
		 */
		public static function generate( $prompt, $cookie = '', $model = '', $timeout = 90 ) {
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

			$session = self::exchange_session( $cookie, min( 30, $timeout ) );
			if ( empty( $session['ok'] ) ) {
				return $session;
			}

			$access_token = $session['accessToken'];
			$account_id   = $session['accountId'] ?? '';
			$session_id   = wp_generate_uuid4();
			$device_id    = self::device_id( $cookie );
			$slug         = self::model_slug( $model );

			$req = self::chat_requirements( $access_token, $cookie, $session_id, $device_id, $account_id, min( 30, $timeout ) );

			$body = array(
				'action'                       => 'next',
				'messages'                     => array(
					array(
						'id'      => wp_generate_uuid4(),
						'author'  => array( 'role' => 'user' ),
						'content' => array(
							'content_type' => 'text',
							'parts'        => array( $prompt ),
						),
					),
				),
				'model'                        => $slug,
				'parent_message_id'            => wp_generate_uuid4(),
				'conversation_id'              => null,
				'timezone_offset_min'          => (int) ( - ( (int) get_option( 'gmt_offset', 0 ) * 60 ) ),
				'history_and_training_disabled'=> false,
				'suggestions'                  => array(),
				'websocket_request_id'         => wp_generate_uuid4(),
			);

			$headers = array_merge(
				self::browser_headers( $cookie ),
				self::oai_headers( $session_id, $device_id ),
				array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'text/event-stream',
				)
			);
			if ( $account_id ) {
				$headers['Chatgpt-Account-Id'] = $account_id;
			}
			if ( ! empty( $req['token'] ) ) {
				$headers['Openai-Sentinel-Chat-Requirements-Token'] = $req['token'];
			}
			if ( ! empty( $req['proof'] ) ) {
				$headers['Openai-Sentinel-Proof-Token'] = $req['proof'];
			}

			$urls = array(
				self::BASE_URL . '/backend-api/f/conversation',
				self::BASE_URL . '/backend-api/conversation',
			);

			$last_error = array(
				'ok'    => false,
				'error' => __( 'ChatGPT Web conversation failed.', 'translate-words' ),
				'code'  => 502,
			);

			foreach ( $urls as $url ) {
				$response = self::http_request(
					'POST',
					$url,
					$headers,
					wp_json_encode( $body ),
					$timeout
				);

				if ( empty( $response['ok'] ) ) {
					$last_error = array(
						'ok'    => false,
						'error' => isset( $response['error'] ) ? $response['error'] : __( 'HTTP request failed.', 'translate-words' ),
						'code'  => 502,
					);
					continue;
				}

				$code = (int) $response['code'];
				$raw  = (string) $response['body'];
				$hdrs = isset( $response['headers'] ) && is_array( $response['headers'] ) ? $response['headers'] : array();

				if ( self::is_cloudflare_block( $raw, $hdrs ) ) {
					return array(
						'ok'    => false,
						'cf'    => true,
						'code'  => $code ? $code : 403,
						'error' => __( 'Cloudflare blocked ChatGPT Web conversation from this server. Even with direct curl, Cloudflare may still challenge this server.', 'translate-words' ),
					);
				}

				if ( 401 === $code || 403 === $code ) {
					$last_error = array(
						'ok'    => false,
						'code'  => $code,
						'error' => __( 'ChatGPT blocked the conversation request (auth/sentinel). Paste a fresh session-token and cf_clearance from a live chatgpt.com Network request.', 'translate-words' ),
					);
					continue;
				}

				if ( 429 === $code ) {
					return array(
						'ok'    => false,
						'code'  => 429,
						'error' => __( 'ChatGPT Web rate limit reached. Wait and try again.', 'translate-words' ),
					);
				}

				if ( $code < 200 || $code >= 300 ) {
					$last_error = array(
						'ok'    => false,
						'code'  => $code,
						'error' => sprintf(
							/* translators: %d: HTTP status */
							__( 'ChatGPT Web conversation failed (HTTP %d).', 'translate-words' ),
							$code
						),
					);
					continue;
				}

				$text = self::parse_conversation_body( $raw );
				if ( '' === $text ) {
					$last_error = array(
						'ok'    => false,
						'code'  => 502,
						'error' => __( 'ChatGPT Web returned an empty reply.', 'translate-words' ),
					);
					continue;
				}

				return array(
					'ok'    => true,
					'text'  => $text,
					'model' => $model,
				);
			}

			return $last_error;
		}
	}
}
