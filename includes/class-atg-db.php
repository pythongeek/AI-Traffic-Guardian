<?php
/**
 * Database layer: table names, schema (dbDelta), retention.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_DB
 */
class ATG_DB {

	/**
	 * Return table name with WP prefix.
	 *
	 * @param string $table Short name (log|stats|alerts).
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;
		$map = array(
			'log'    => $wpdb->prefix . 'atg_traffic_log',
			'stats'  => $wpdb->prefix . 'atg_stats_daily',
			'alerts' => $wpdb->prefix . 'atg_alerts',
		);
		return isset( $map[ $table ] ) ? $map[ $table ] : '';
	}

	/**
	 * Create / upgrade schema. Safe to run repeatedly (dbDelta).
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$log    = self::table( 'log' );
		$stats  = self::table( 'stats' );
		$alerts = self::table( 'alerts' );

		$sql = array();

		$sql[] = "CREATE TABLE {$log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ts DATETIME NOT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			ua VARCHAR(512) NOT NULL DEFAULT '',
			method VARCHAR(10) NOT NULL DEFAULT 'GET',
			path VARCHAR(768) NOT NULL DEFAULT '',
			classification VARCHAR(32) NOT NULL DEFAULT 'unknown',
			vendor VARCHAR(64) NOT NULL DEFAULT '',
			purpose VARCHAR(32) NOT NULL DEFAULT '',
			bot_name VARCHAR(96) NOT NULL DEFAULT '',
			verified TINYINT(1) NULL DEFAULT NULL,
			spoofed TINYINT(1) NOT NULL DEFAULT 0,
			action VARCHAR(16) NOT NULL DEFAULT 'allow',
			enforced TINYINT(1) NOT NULL DEFAULT 0,
			status_code SMALLINT(4) NOT NULL DEFAULT 200,
			reason VARCHAR(190) NOT NULL DEFAULT '',
			risk TINYINT(3) NOT NULL DEFAULT 0,
			is_auth TINYINT(1) NOT NULL DEFAULT 0,
			session_hash CHAR(64) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			exec_ms SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY ts (ts),
			KEY classification (classification),
			KEY vendor (vendor),
			KEY action (action),
			KEY ip_hash (ip_hash),
			KEY country (country)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$stats} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			day DATE NOT NULL,
			classification VARCHAR(32) NOT NULL DEFAULT 'unknown',
			vendor VARCHAR(64) NOT NULL DEFAULT '',
			purpose VARCHAR(32) NOT NULL DEFAULT '',
			action VARCHAR(16) NOT NULL DEFAULT 'allow',
			country CHAR(2) NOT NULL DEFAULT '',
			hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY bucket (day, classification, vendor, purpose, action, country),
			KEY day (day)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$alerts} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NOT NULL,
			type VARCHAR(40) NOT NULL DEFAULT 'new_bot',
			title VARCHAR(190) NOT NULL DEFAULT '',
			payload LONGTEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'open',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created (created)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'atg_db_version', ATG_DB_VERSION );
	}

	/**
	 * Ensure schema exists (runs on admin_init when version differs).
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'atg_db_version' ) !== ATG_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Delete log rows older than the retention window.
	 *
	 * @param int $days Retention in days.
	 */
	public static function prune( $days ) {
		global $wpdb;
		$days = max( 1, absint( $days ) );
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'DELETE FROM ' . self::table( 'log' ) . ' WHERE ts < DATE_SUB(%s, INTERVAL %d DAY)',
				current_time( 'mysql' ),
				$days
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'DELETE FROM ' . self::table( 'stats' ) . ' WHERE day < DATE_SUB(%s, INTERVAL %d DAY)',
				current_time( 'mysql' ),
				max( $days, 90 )
			)
		);
		do_action( 'atg_after_prune', $deleted, $days );
	}

	/**
	 * Drop all plugin tables (uninstall only, when the user opted in).
	 */
	public static function drop() {
		global $wpdb;
		foreach ( array( 'log', 'stats', 'alerts' ) as $t ) {
			$table = self::table( $t );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
