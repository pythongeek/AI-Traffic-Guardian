<?php
/**
 * Plugin Name:       AI Traffic Guardian
 * Plugin URI:        https://example.com/ai-traffic-guardian
 * Description:       Layered AI & bot traffic control for WordPress: verified-bot classification, vendor×purpose policy engine, analytics integrity, accessible form & WooCommerce protection, robots.txt / llms.txt management, shadow mode and a full visual dashboard.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AI Traffic Guardian
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
	define( 'ATG_VERSION', '1.0.0' );
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
if ( ! function_exists( 'atg_autoload' ) ) {
	function atg_autoload( $class ) {
		if ( strpos( $class, 'ATG_' ) !== 0 ) {
			return;
		}
		$slug  = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$paths = array(
			ATG_PLUGIN_DIR . 'includes/' . $slug,
			ATG_PLUGIN_DIR . 'admin/' . $slug,
		);
		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
	spl_autoload_register( 'atg_autoload' );
}

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
