<?php
/**
 * Activation handler.
 *
 * ROOT CAUSE OF "265 chars unexpected output" BUG:
 * ─────────────────────────────────────────────────
 * During activation WordPress sometimes already has output buffering open (OB
 * level > 0).  When ATG_DB::install() calls
 *   require_once ABSPATH . 'wp-admin/includes/upgrade.php'
 * that file in turn requires wp-admin/includes/schema.php, which on some hosts
 * emits a PHP notice or deprecation warning.  Those characters land in the open
 * output buffer and WordPress reports them as "unexpected output".
 *
 * Fix: wrap the entire activation in ob_start / ob_get_clean, log any stray
 * content via ATG_Debug::stray_output(), and never call flush_rewrite_rules()
 * from within a non-init context (moved to admin_init in ATG_Plugin).
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
		// ── Output buffer guard ────────────────────────────────────────────
		// Capture ANY stray output so it never reaches WordPress's activation
		// checker and never contaminates REST API JSON responses on subsequent
		// page loads.
		$ob_was_open = ob_get_level() > 0;
		if ( ! $ob_was_open ) {
			ob_start();
		}

		try {
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
		} catch ( \Throwable $e ) {
			// PHP 7+ Throwable covers both Exception and Error.
			error_log( 'ATG Activation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		} catch ( \Exception $e ) {
			// PHP 5.x fallback (plugin requires PHP 7.4+ but belt-and-suspenders).
			error_log( 'ATG Activation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		// Drain and log any stray output.
		if ( ! $ob_was_open ) {
			$stray = ob_get_clean();
			if ( $stray && class_exists( 'ATG_Debug' ) ) {
				ATG_Debug::stray_output( $stray, 'ATG_Activator::activate' );
			} elseif ( $stray ) {
				error_log( 'ATG Activation stray output (' . strlen( $stray ) . ' chars): ' . substr( $stray, 0, 500 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
	}

	/**
	 * Activate on the current site.
	 *
	 * NOTE: flush_rewrite_rules() is intentionally NOT called here. Calling it
	 * during activation generates output in certain environments. Instead it is
	 * scheduled via the 'atg_flush_rewrites' option flag and executed on the
	 * very next admin_init hook inside ATG_Plugin::maybe_flush_rewrites().
	 */
	public static function activate_single() {
		// ── Schema ────────────────────────────────────────────────────────
		// Suppress any accidental output from upgrade.php / schema.php.
		ob_start();
		ATG_DB::install();
		$db_output = ob_get_clean();
		if ( $db_output ) {
			error_log( 'ATG DB install stray output: ' . $db_output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		// ── Default options ───────────────────────────────────────────────
		if ( false === get_option( 'atg_settings' ) ) {
			add_option( 'atg_settings', ATG_Plugin::default_settings(), '', 'yes' );
		}
		if ( false === get_option( 'atg_policy_matrix' ) ) {
			add_option( 'atg_policy_matrix', ATG_Policy_Engine::default_matrix(), '', 'yes' );
		}
		if ( false === get_option( 'atg_allowlist' ) ) {
			add_option( 'atg_allowlist', ATG_Allowlist::defaults(), '', 'yes' );
		}

		// ── Shadow mode grace period ───────────────────────────────────────
		$settings = get_option( 'atg_settings', array() );
		if ( empty( $settings['shadow_started'] ) ) {
			$settings['shadow_started'] = time();
			update_option( 'atg_settings', $settings );
		}

		// ── Cron ──────────────────────────────────────────────────────────
		if ( ! wp_next_scheduled( 'atg_cron_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'atg_cron_daily' );
		}
		if ( ! wp_next_scheduled( 'atg_cron_weekly' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'atg_cron_weekly' );
		}

		// ── Rewrite flag (safe deferred flush) ────────────────────────────
		// On the NEXT admin_init, ATG_Plugin::maybe_flush_rewrites() will call
		// ATG_Llms::register_rewrite() + flush_rewrite_rules() in a clean context.
		update_option( 'atg_flush_rewrites', 1, 'no' );
	}
}
