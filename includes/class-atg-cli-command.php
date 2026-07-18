<?php
/**
 * WP-CLI commands for AI Traffic Guardian.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

/**
 * Manage AI Traffic Guardian from the command line.
 */
class ATG_CLI_Command {

	/**
	 * Show current plugin status.
	 *
	 * ## EXAMPLES
	 *   wp atg status
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function status( $args, $assoc ) {
		$plugin = ATG_Plugin::instance();
		WP_CLI::line( 'Mode: ' . strtoupper( $plugin->enforcement_mode() ) );
		WP_CLI::line( 'Open alerts: ' . $plugin->alerts->open_count() );
		WP_CLI::line( 'DB version: ' . get_option( 'atg_db_version' ) );
	}

	/**
	 * Switch enforcement mode.
	 *
	 * ## OPTIONS
	 * <mode>
	 * : One of: shadow, active, off
	 *
	 * ## EXAMPLES
	 *   wp atg mode shadow
	 *   wp atg mode active
	 *   wp atg mode off
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function mode( $args, $assoc ) {
		$mode = isset( $args[0] ) ? $args[0] : '';
		if ( ! in_array( $mode, array( 'shadow', 'active', 'off' ), true ) ) {
			WP_CLI::error( 'Invalid mode. Use: shadow, active, or off' );
		}
		ATG_Plugin::instance()->update_settings( array( 'enforcement' => $mode ) );
		WP_CLI::success( sprintf( 'Mode set to: %s', $mode ) );
	}

	/**
	 * Show top bots from traffic stats.
	 *
	 * ## OPTIONS
	 * [--days=<days>]
	 * : Number of days to look back. Default: 7
	 *
	 * ## EXAMPLES
	 *   wp atg top-bots --days=30
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function top_bots( $args, $assoc ) {
		global $wpdb;
		$days  = (int) ( isset( $assoc['days'] ) ? $assoc['days'] : 7 );
		$stats = ATG_DB::table( 'stats' );
		$from  = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor, SUM(hits) AS total FROM {$stats} WHERE vendor != '' AND day >= %s GROUP BY vendor ORDER BY total DESC LIMIT 20",
				$from
			),
			ARRAY_A
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'vendor', 'total' ) );
	}

	/**
	 * Export traffic log.
	 *
	 * ## OPTIONS
	 * [--days=<days>]
	 * : Days to export. Default: 7
	 * [--format=<format>]
	 * : Output format: csv or json. Default: csv
	 *
	 * ## EXAMPLES
	 *   wp atg export --days=30 --format=csv > traffic.csv
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function export( $args, $assoc ) {
		$days   = (int) ( isset( $assoc['days'] ) ? $assoc['days'] : 7 );
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'csv';
		ATG_Plugin::instance()->logger->export_csv( array( 'days' => $days ) );
	}

	/**
	 * Classify a test request.
	 *
	 * ## OPTIONS
	 * [--ip=<ip>]
	 * : IP address to classify
	 * [--ua=<ua>]
	 * : User agent string to classify
	 *
	 * ## EXAMPLES
	 *   wp atg classify --ip=66.249.66.1 --ua="Googlebot/2.1"
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function classify( $args, $assoc ) {
		$_SERVER['REMOTE_ADDR']     = isset( $assoc['ip'] ) ? $assoc['ip'] : '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = isset( $assoc['ua'] ) ? $assoc['ua'] : 'WP-CLI-Test/1.0';
		$_SERVER['REQUEST_URI']     = '/test/';
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$d = ATG_Plugin::instance()->classifier->classify();
		WP_CLI\Utils\format_items( 'table', array( $d ), array_keys( $d ) );
	}

	/**
	 * Show and manage alerts.
	 *
	 * ## OPTIONS
	 * [--status=<status>]
	 * : Filter by status: open, dismissed, all. Default: open
	 * [dismiss]
	 * : Dismiss all open alerts
	 * [--all]
	 * : Used with dismiss: dismiss all
	 *
	 * ## EXAMPLES
	 *   wp atg alerts
	 *   wp atg alerts dismiss --all
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function alerts( $args, $assoc ) {
		$plugin = ATG_Plugin::instance();
		if ( 'dismiss' === ( isset( $args[0] ) ? $args[0] : '' ) ) {
			$open = $plugin->alerts->list( 'open' );
			foreach ( $open as $a ) {
				$plugin->alerts->dismiss( (int) $a['id'] );
			}
			WP_CLI::success( sprintf( 'Dismissed %d alerts.', count( $open ) ) );
			return;
		}
		$rows = $plugin->alerts->list( isset( $assoc['status'] ) ? $assoc['status'] : 'open', 50 );
		if ( ! $rows ) {
			WP_CLI::line( 'No alerts.' );
			return;
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'type', 'title', 'status', 'created' ) );
	}

	/**
	 * Refresh IP ranges from all vendors.
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function refresh_ip_ranges( $args, $assoc ) {
		ATG_Plugin::instance()->verifier->refresh_ip_ranges();
		WP_CLI::success( 'IP ranges refreshed.' );
	}

	/**
	 * Apply a policy preset.
	 *
	 * ## OPTIONS
	 * <preset>
	 * : Preset name: publisher, woocommerce, private
	 *
	 * ## EXAMPLES
	 *   wp atg preset publisher
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function preset( $args, $assoc ) {
		$name = isset( $args[0] ) ? $args[0] : '';
		if ( ATG_Plugin::instance()->policy->apply_preset( $name ) ) {
			WP_CLI::success( sprintf( "Preset '%s' applied.", $name ) );
		} else {
			WP_CLI::error( sprintf( "Unknown preset '%s'. Available: publisher, woocommerce, private", $name ) );
		}
	}

	/**
	 * Prune old log entries.
	 *
	 * ## OPTIONS
	 * [--days=<days>]
	 * : Delete entries older than this many days. Default: setting value
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function prune( $args, $assoc ) {
		$days = (int) ( isset( $assoc['days'] ) ? $assoc['days'] : ATG_Plugin::instance()->get( 'retention_days', 30 ) );
		ATG_DB::prune( $days );
		WP_CLI::success( sprintf( 'Pruned entries older than %d days.', $days ) );
	}

	/**
	 * Show environment report.
	 *
	 * @when after_wp_load
	 * @param array $args   Positional arguments.
	 * @param array $assoc  Associative arguments.
	 */
	public function env( $args, $assoc ) {
		$env = ATG_Compat::environment();
		foreach ( $env as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val ) ?: 'none';
			}
			if ( is_bool( $val ) ) {
				$val = $val ? 'yes' : 'no';
			}
			WP_CLI::line( str_pad( $key, 24 ) . $val );
		}
	}
}

// Register commands.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'atg', 'ATG_CLI_Command' );
	WP_CLI::add_command( 'atg status',            array( 'ATG_CLI_Command', 'status' ) );
	WP_CLI::add_command( 'atg mode',              array( 'ATG_CLI_Command', 'mode' ) );
	WP_CLI::add_command( 'atg top-bots',          array( 'ATG_CLI_Command', 'top_bots' ) );
	WP_CLI::add_command( 'atg export',            array( 'ATG_CLI_Command', 'export' ) );
	WP_CLI::add_command( 'atg classify',          array( 'ATG_CLI_Command', 'classify' ) );
	WP_CLI::add_command( 'atg alerts',            array( 'ATG_CLI_Command', 'alerts' ) );
	WP_CLI::add_command( 'atg refresh-ip-ranges', array( 'ATG_CLI_Command', 'refresh_ip_ranges' ) );
	WP_CLI::add_command( 'atg preset',            array( 'ATG_CLI_Command', 'preset' ) );
	WP_CLI::add_command( 'atg prune',             array( 'ATG_CLI_Command', 'prune' ) );
	WP_CLI::add_command( 'atg env',               array( 'ATG_CLI_Command', 'env' ) );
}
