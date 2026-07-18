<?php
/**
 * Uninstall handler. Only removes data when the site owner explicitly opted
 * in via Settings → "Delete all tables and settings when uninstalled".
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ATG_DB_VERSION' ) ) {
	define( 'ATG_DB_VERSION', '1.1.0' );
}
require_once plugin_dir_path( __FILE__ ) . 'includes/class-atg-db.php';

/**
 * Remove data for one site.
 */
function atg_uninstall_site() {
	$settings = get_option( 'atg_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return; // Data kept by design.
	}
	ATG_DB::drop();
	delete_option( 'atg_settings' );
	delete_option( 'atg_policy_matrix' );
	delete_option( 'atg_allowlist' );
	delete_option( 'atg_db_version' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 200,
		)
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		atg_uninstall_site();
		restore_current_blog();
	}
} else {
	atg_uninstall_site();
}
