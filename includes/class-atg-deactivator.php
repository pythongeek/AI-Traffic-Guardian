<?php
/**
 * Deactivation: clear cron, flush rewrites. Data is kept (see uninstall.php).
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Deactivator
 */
class ATG_Deactivator {

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'atg_cron_daily' );
		wp_clear_scheduled_hook( 'atg_cron_weekly' );
		flush_rewrite_rules();
	}
}
