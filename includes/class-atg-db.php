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
			id bigint(20) unsigned not null auto_increment,
			ts datetime not null,
			ip_hash char(64) not null default '',
			ua varchar(512) not null default '',
			method varchar(10) not null default 'GET',
			path varchar(768) not null default '',
			classification varchar(32) not null default 'unknown',
			vendor varchar(64) not null default '',
			purpose varchar(32) not null default '',
			bot_name varchar(96) not null default '',
			verified tinyint(1) null default null,
			spoofed tinyint(1) not null default 0,
			action varchar(16) not null default 'allow',
			enforced tinyint(1) not null default 0,
			status_code smallint(4) not null default 200,
			reason varchar(190) not null default '',
			risk tinyint(3) not null default 0,
			is_auth tinyint(1) not null default 0,
			session_hash char(64) not null default '',
			country char(2) not null default '',
			exec_ms smallint(5) unsigned not null default 0,
			PRIMARY KEY  (id),
			KEY ts (ts),
			KEY classification (classification),
			KEY vendor (vendor),
			KEY action (action),
			KEY ip_hash (ip_hash),
			KEY country (country)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$stats} (
			id bigint(20) unsigned not null auto_increment,
			day date not null,
			classification varchar(32) not null default 'unknown',
			vendor varchar(64) not null default '',
			purpose varchar(32) not null default '',
			action varchar(16) not null default 'allow',
			country char(2) not null default '',
			hits bigint(20) unsigned not null default 0,
			PRIMARY KEY  (id),
			UNIQUE KEY bucket (day,classification,vendor,purpose,action,country),
			KEY day (day)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$alerts} (
			id bigint(20) unsigned not null auto_increment,
			created datetime not null,
			type varchar(40) not null default 'new_bot',
			title varchar(190) not null default '',
			payload longtext null,
			status varchar(16) not null default 'open',
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
	 * Housekeeping: prune old logs.
	 */
	public static function prune( $days ) {
		global $wpdb;
		$days = max( 1, absint( $days ) );
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table( 'log' ) . ' WHERE ts < DATE_SUB(%s, INTERVAL %d DAY)',
				current_time( 'mysql' ),
				$days
			)
		);
		$wpdb->query(
			$wpdb->prepare(
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
			$table_name = esc_sql( self::table( $t ) );
			if ( ! empty( $table_name ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}
}
