<?php
/**
 * Activation: schema, default settings, default policy matrix, cron.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Activator
 */
class ATG_Activator {

	/**
	 * Run on activation. Multisite-aware: creates tables per site when
	 * network-activated (capped to avoid timeouts on huge networks).
	 *
	 * @param bool $network_wide Whether the plugin was network-activated.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single();
				restore_current_blog();
			}
		} else {
			self::activate_single();
		}
	}

	/**
	 * Activate on the current site.
	 */
	public static function activate_single() {
		ATG_DB::install();

		if ( false === get_option( 'atg_settings' ) ) {
			add_option( 'atg_settings', ATG_Plugin::default_settings() );
		}
		if ( false === get_option( 'atg_policy_matrix' ) ) {
			add_option( 'atg_policy_matrix', ATG_Policy_Engine::default_matrix() );
		}
		if ( false === get_option( 'atg_allowlist' ) ) {
			add_option( 'atg_allowlist', ATG_Allowlist::defaults() );
		}

		// Shadow mode starts at activation: observe-only for the grace period.
		$settings = get_option( 'atg_settings', array() );
		if ( empty( $settings['shadow_started'] ) ) {
			$settings['shadow_started'] = time();
			update_option( 'atg_settings', $settings );
		}

		if ( ! wp_next_scheduled( 'atg_cron_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'atg_cron_daily' );
		}
		if ( ! wp_next_scheduled( 'atg_cron_weekly' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'atg_cron_weekly' );
		}

		// Flush rewrites so /llms.txt works immediately after activation.
		// Guard for $wp_rewrite being null (can happen in some hosting setups).
		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			ATG_Llms::register_rewrite();
			flush_rewrite_rules();
		}
	}
}
