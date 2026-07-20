<?php
/**
 * Plugin Name:       Bot Shield Pro
 * Plugin URI:        https://example.com/ai-traffic-guardian
 * Description:       Layered AI & bot traffic control for WordPress: verified-bot classification, vendor×purpose policy engine, analytics integrity, accessible form & WooCommerce protection, robots.txt / llms.txt management, shadow mode and a full visual dashboard.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Bot Shield Pro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-traffic-guardian
 * Domain Path:       /languages
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}
if ( file_exists( plugin_dir_path( __FILE__ ) . 'config/branding.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'config/branding.php';
}
if ( ! defined( 'ATG_VERSION' ) ) {
	define( 'ATG_VERSION', '1.0.1' );
}
if ( ! defined( 'ATG_DB_VERSION' ) ) {
	define( 'ATG_DB_VERSION', '1.1.0' );
}
if ( ! defined( 'ATG_PLUGIN_FILE' ) ) {
	define( 'ATG_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'ATG_PLUGIN_DIR' ) ) {
	define( 'ATG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ATG_PLUGIN_URL' ) ) {
	define( 'ATG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ATG_PLUGIN_BASENAME' ) ) {
	define( 'ATG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Lightweight class autoloader. Classes live in includes/ and admin/ as
 * class-atg-*.php files using the ATG_ prefix.
 *
 * @param string $class Fully-qualified class name.
 */
// Register a custom error and exception log handler for debugging
if ( ! function_exists( 'atg_write_debug_log' ) ) {
	function atg_write_debug_log( $message ) {
		$log_file = dirname( __FILE__ ) . '/debug-log.txt';
		$time = date( 'Y-m-d H:i:s' );
		@file_put_contents( $log_file, "[{$time}] {$message}\n", FILE_APPEND );
	}
}

if ( ! function_exists( 'atg_debug_error_handler' ) ) {
	function atg_debug_error_handler( $errno, $errstr, $errfile, $errline ) {
		// Log errors excluding deprecations to keep logs clean unless they are critical
		if ( ! ( $errno & ( E_DEPRECATED | E_USER_DEPRECATED ) ) ) {
			atg_write_debug_log( "PHP ERROR (Type: {$errno}): {$errstr} in {$errfile} on line {$errline}" );
		}
		return false; // Let standard error handler run too
	}
	set_error_handler( 'atg_debug_error_handler' );
}

if ( ! function_exists( 'atg_debug_exception_handler' ) ) {
	function atg_debug_exception_handler( $exception ) {
		atg_write_debug_log( "PHP EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n" . $exception->getTraceAsString() );
	}
	set_exception_handler( 'atg_debug_exception_handler' );
}

if ( ! function_exists( 'atg_debug_shutdown_handler' ) ) {
	function atg_debug_shutdown_handler() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			atg_write_debug_log( "PHP FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}" );
		}
	}
	register_shutdown_function( 'atg_debug_shutdown_handler' );
}

add_filter( 'rest_request_after_callbacks', function( $response, $handler, $request ) {
	if ( is_wp_error( $response ) ) {
		atg_write_debug_log( "REST API Error on " . $request->get_route() . " (" . $request->get_method() . "): " . $response->get_error_message() . " (Code: " . $response->get_error_code() . ")" );
	}
	return $response;
}, 10, 3 );

// Register closure-based autoloader to prevent prefix/version redefinition conflicts
spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'ATG_' ) !== 0 ) {
		return;
	}
	$slug  = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	$paths = array(
		plugin_dir_path( __FILE__ ) . 'includes/' . $slug,
		plugin_dir_path( __FILE__ ) . 'admin/' . $slug,
	);
	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

register_activation_hook( __FILE__, array( 'ATG_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ATG_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
if ( ! function_exists( 'atg_boot' ) ) {
	function atg_boot() {
		ATG_Plugin::instance()->boot();
	}
	add_action( 'plugins_loaded', 'atg_boot', 5 );
}

