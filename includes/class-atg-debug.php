<?php
/**
 * ATG Debug Logger.
 *
 * Stored in wp_options (autoload=no). Enable via the Debug Log admin page.
 * Auto-disabled after ATG_DEBUG_TTL seconds to prevent DB bloat.
 * Never runs in production unless explicitly enabled by an administrator.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Debug
 */
class ATG_Debug {

	const LOG_OPTION     = 'atg_debug_log';
	const ENABLED_OPTION = 'atg_debug_enabled';
	const EXPIRY_OPTION  = 'atg_debug_expiry';
	const MAX_ENTRIES    = 400;
	const AUTO_OFF_SECS  = 3600; // Auto-disable after 1 hour to protect DB.

	/**
	 * Whether debug is currently enabled (cached).
	 *
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * In-memory buffer for the current request (flushed on shutdown).
	 * Avoids one DB write per log call during heavy requests.
	 *
	 * @var array
	 */
	private static $buffer = array();

	/**
	 * Boot: register the shutdown flush and check auto-expiry.
	 */
	public static function boot() {
		self::$enabled = (bool) get_option( self::ENABLED_OPTION, false );

		if ( self::$enabled ) {
			// Auto-disable when TTL expires.
			$expiry = (int) get_option( self::EXPIRY_OPTION, 0 );
			if ( $expiry && time() > $expiry ) {
				self::disable();
				return;
			}
			register_shutdown_function( array( __CLASS__, 'flush_buffer' ) );

			// Capture PHP errors too.
			set_error_handler( array( __CLASS__, 'php_error_handler' ), E_ALL );
		}
	}

	/**
	 * Is debug logging currently active?
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( null === self::$enabled ) {
			self::$enabled = (bool) get_option( self::ENABLED_OPTION, false );
		}
		return self::$enabled;
	}

	/**
	 * Enable debug logging.
	 */
	public static function enable() {
		self::$enabled = true;
		update_option( self::ENABLED_OPTION, 1, 'no' );
		update_option( self::EXPIRY_OPTION, time() + self::AUTO_OFF_SECS, 'no' );
		self::log( 'system', 'Debug logging enabled.', array( 'auto_off_at' => date( 'Y-m-d H:i:s', time() + self::AUTO_OFF_SECS ) ) );
	}

	/**
	 * Disable debug logging.
	 */
	public static function disable() {
		self::$enabled = false;
		update_option( self::ENABLED_OPTION, 0, 'no' );
		delete_option( self::EXPIRY_OPTION );
	}

	/**
	 * Log an event. Silently no-ops when disabled.
	 *
	 * @param string       $context Short tag: rest|classifier|enforcer|form|analytics|system|error.
	 * @param string       $message Human-readable description.
	 * @param array|string $data    Optional structured data.
	 */
	public static function log( $context, $message, $data = array() ) {
		if ( ! self::enabled() ) {
			return;
		}

		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$caller    = isset( $backtrace[1] ) ? ( $backtrace[1]['class'] ?? '' ) . '::' . ( $backtrace[1]['function'] ?? '' ) : '';

		self::$buffer[] = array(
			'ts'      => current_time( 'mysql' ),
			'ms'      => (int) round( microtime( true ) * 1000 ),
			'context' => sanitize_key( $context ),
			'message' => (string) $message,
			'caller'  => $caller,
			'data'    => is_string( $data ) ? $data : wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
		);
	}

	/**
	 * PHP error handler — captures notices/warnings/fatals to the debug log.
	 *
	 * @param int    $errno   Error level.
	 * @param string $errstr  Error message.
	 * @param string $errfile File.
	 * @param int    $errline Line.
	 * @return bool
	 */
	public static function php_error_handler( $errno, $errstr, $errfile, $errline ) {
		// Only log errors from ATG files to avoid noise.
		if ( false === strpos( $errfile, 'ai-traffic-guardian' ) ) {
			return false; // Delegate to default handler.
		}
		$levels = array(
			E_ERROR   => 'E_ERROR',
			E_WARNING => 'E_WARNING',
			E_NOTICE  => 'E_NOTICE',
			E_DEPRECATED => 'E_DEPRECATED',
		);
		$level = isset( $levels[ $errno ] ) ? $levels[ $errno ] : "E_{$errno}";
		self::log(
			'php-error',
			"[{$level}] {$errstr}",
			array(
				'file' => str_replace( ABSPATH, '', $errfile ),
				'line' => $errline,
			)
		);
		return false; // Let WordPress/PHP also handle it normally.
	}

	/**
	 * Flush the in-memory buffer to the DB on shutdown.
	 * Prepends new entries, keeps only the newest MAX_ENTRIES.
	 */
	public static function flush_buffer() {
		if ( empty( self::$buffer ) ) {
			return;
		}
		$existing = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$merged = array_merge( array_reverse( self::$buffer ), $existing );
		$merged = array_slice( $merged, 0, self::MAX_ENTRIES );
		update_option( self::LOG_OPTION, $merged, 'no' );
		self::$buffer = array();
	}

	/**
	 * Get log entries.
	 *
	 * @param int    $limit   Max entries to return.
	 * @param string $context Optional context filter.
	 * @return array
	 */
	public static function get( $limit = 100, $context = '' ) {
		$entries = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		if ( $context ) {
			$entries = array_values( array_filter( $entries, function( $e ) use ( $context ) {
				return isset( $e['context'] ) && $e['context'] === $context;
			} ) );
		}
		return array_slice( $entries, 0, min( $limit, self::MAX_ENTRIES ) );
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		update_option( self::LOG_OPTION, array(), 'no' );
		self::$buffer = array();
		self::log( 'system', 'Log cleared by ' . wp_get_current_user()->user_login );
	}

	/**
	 * Log a REST API call (in + out).
	 *
	 * @param string $endpoint Endpoint path.
	 * @param string $method   HTTP method.
	 * @param array  $params   Request params/body.
	 * @param mixed  $result   Response or error.
	 * @param int    $ms       Execution time in ms.
	 */
	public static function rest( $endpoint, $method, $params, $result, $ms = 0 ) {
		if ( ! self::enabled() ) {
			return;
		}
		$is_error = is_wp_error( $result );
		self::log(
			'rest',
			"{$method} /atg/v1/{$endpoint}" . ( $is_error ? ' → ERROR' : ' → OK' ),
			array(
				'endpoint' => $endpoint,
				'method'   => $method,
				'params'   => $params,
				'result'   => $is_error ? array( 'error' => $result->get_error_message() ) : ( is_array( $result ) ? $result : 'non-array result' ),
				'exec_ms'  => $ms,
			)
		);
	}

	/**
	 * Log a stray output capture (the "265 chars of unexpected output" fix).
	 *
	 * @param string $output The captured output.
	 * @param string $source Where it was captured.
	 */
	public static function stray_output( $output, $source ) {
		if ( '' === trim( $output ) ) {
			return;
		}
		// Always log stray output, regardless of debug enable flag.
		$entries = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		array_unshift(
			$entries,
			array(
				'ts'      => current_time( 'mysql' ),
				'ms'      => (int) round( microtime( true ) * 1000 ),
				'context' => 'stray-output',
				'message' => "Stray output captured in {$source} (" . strlen( $output ) . ' chars). This corrupts REST API JSON responses.',
				'caller'  => $source,
				'data'    => wp_json_encode( array( 'length' => strlen( $output ), 'preview' => substr( $output, 0, 300 ) ) ),
			)
		);
		$entries = array_slice( $entries, 0, self::MAX_ENTRIES );
		update_option( self::LOG_OPTION, $entries, 'no' );
	}

	/**
	 * Return expiry timestamp if set.
	 *
	 * @return int|null
	 */
	public static function expiry() {
		$v = get_option( self::EXPIRY_OPTION, 0 );
		return $v ? (int) $v : null;
	}
}
