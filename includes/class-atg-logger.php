<?php
/**
 * Traffic logger: per-row logging for bots, aggregated daily stats for the
 * dashboard. Humans are aggregated only (unless log_humans is enabled) to
 * keep the table lean.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Logger
 */
class ATG_Logger {

	/**
	 * Log a request decision.
	 *
	 * @param array $d Decision array from the classifier/enforcer.
	 */
	public function log( $d ) {
		global $wpdb;
		$plugin = ATG_Plugin::instance();

		$is_bot_class = ! in_array( $d['classification'], array( 'human', 'authenticated', 'internal', 'allowlisted' ), true );

		// Always update the daily aggregate (cheap upsert).
		$this->bump_stats( $d );

		// Per-row logging: bots always; humans only when explicitly enabled.
		if ( ! $is_bot_class && ! $plugin->get( 'log_humans', false ) ) {
			return;
		}

		$ip_raw  = isset( $d['ip'] ) ? $d['ip'] : '';
		$ip_hash = $plugin->get( 'hash_ips', true )
			? hash( 'sha256', $ip_raw . wp_salt( 'nonce' ) )
			: $ip_raw;

		$wpdb->insert(
			ATG_DB::table( 'log' ),
			array(
				'ts'             => current_time( 'mysql' ),
				'ip_hash'        => is_string( $ip_hash ) ? substr( $ip_hash, 0, 64 ) : '',
				'ua'             => substr( (string) ( isset( $d['ua'] ) ? $d['ua'] : '' ), 0, 512 ),
				'method'         => substr( (string) ( isset( $d['method'] ) ? $d['method'] : 'GET' ), 0, 10 ),
				'path'           => substr( (string) ( isset( $d['path'] ) ? $d['path'] : '' ), 0, 768 ),
				'classification' => substr( (string) $d['classification'], 0, 32 ),
				'vendor'         => substr( (string) ( isset( $d['vendor'] ) ? $d['vendor'] : '' ), 0, 64 ),
				'purpose'        => substr( (string) ( isset( $d['purpose'] ) ? $d['purpose'] : '' ), 0, 32 ),
				'bot_name'       => substr( (string) ( isset( $d['bot_name'] ) ? $d['bot_name'] : '' ), 0, 96 ),
				'verified'       => isset( $d['verified'] ) && null !== $d['verified'] ? (int) $d['verified'] : null,
				'spoofed'        => ! empty( $d['spoofed'] ) ? 1 : 0,
				'action'         => substr( (string) $d['action'], 0, 16 ),
				'enforced'       => ! empty( $d['enforced'] ) ? 1 : 0,
				'status_code'    => (int) ( isset( $d['status_code'] ) ? $d['status_code'] : 200 ),
				'reason'         => substr( (string) ( isset( $d['reason'] ) ? $d['reason'] : '' ), 0, 190 ),
				'risk'           => (int) ( isset( $d['risk'] ) ? $d['risk'] : 0 ),
				'is_auth'        => ! empty( $d['is_auth'] ) ? 1 : 0,
				'session_hash'   => isset( $d['session'] ) ? substr( hash( 'sha256', $d['session'] ), 0, 64 ) : '',
				'country'        => substr( (string) ( isset( $d['country'] ) ? $d['country'] : '' ), 0, 2 ),
				'exec_ms'        => (int) ( isset( $d['exec_ms'] ) ? $d['exec_ms'] : 0 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%d' )
		);
	}

	/**
	 * Increment the daily aggregate bucket.
	 *
	 * @param array $d Decision array.
	 */
	private function bump_stats( $d ) {
		global $wpdb;
		$day     = current_time( 'Y-m-d' );
		$country = isset( $d['country'] ) ? substr( (string) $d['country'], 0, 2 ) : '';
		$rows    = array(
			// Overall bucket per classification.
			array( 'classification' => $d['classification'], 'vendor' => '', 'purpose' => '', 'action' => '', 'country' => $country ),
			// Action bucket.
			array( 'classification' => '', 'vendor' => '', 'purpose' => '', 'action' => $d['action'], 'country' => $country ),
		);
		if ( ! empty( $d['vendor'] ) ) {
			$rows[] = array( 'classification' => '', 'vendor' => $d['vendor'], 'purpose' => '', 'action' => '', 'country' => $country );
		}
		if ( ! empty( $d['purpose'] ) ) {
			$rows[] = array( 'classification' => '', 'vendor' => '', 'purpose' => $d['purpose'], 'action' => '', 'country' => $country );
		}
		foreach ( $rows as $r ) {
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . ATG_DB::table( 'stats' ) . ' (day, classification, vendor, purpose, action, country, hits)
					 VALUES (%s, %s, %s, %s, %s, %s, 1)
					 ON DUPLICATE KEY UPDATE hits = hits + 1',
					$day,
					$r['classification'],
					$r['vendor'],
					$r['purpose'],
					$r['action'],
					$r['country']
				)
			);
		}
	}

	/**
	 * Query the log table with filters + pagination.
	 *
	 * @param array $args Filters: classification, vendor, action, search, page, per_page.
	 * @return array rows + total.
	 */
	public function query( $args = array() ) {
		global $wpdb;
		$table = ATG_DB::table( 'log' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['classification'] ) ) {
			$where[]  = 'classification = %s';
			$params[] = sanitize_text_field( $args['classification'] );
		}
		if ( ! empty( $args['vendor'] ) ) {
			$where[]  = 'vendor = %s';
			$params[] = sanitize_text_field( $args['vendor'] );
		}
		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = sanitize_text_field( $args['action'] );
		}
		if ( isset( $args['spoofed'] ) && '' !== $args['spoofed'] ) {
			$where[]  = 'spoofed = %d';
			$params[] = ! empty( $args['spoofed'] ) ? 1 : 0;
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(ua LIKE %s OR path LIKE %s OR bot_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$per_page = isset( $args['per_page'] ) ? min( 200, max( 10, (int) $args['per_page'] ) ) : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		if ( $params ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
					$params
				)
			);
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
					array_merge( $params, array( $per_page, $offset ) )
				),
				ARRAY_A
			);
		} else {
			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE 1=1"
			);
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE 1=1 ORDER BY id DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Export filtered rows as CSV (streamed to output).
	 *
	 * @param array $args Same filters as query().
	 */
	public function export_csv( $args = array() ) {
		$args['per_page'] = 200;
		$args['page']     = 1;
		$all              = array();
		do {
			$result = $this->query( $args );
			$all    = array_merge( $all, $result['rows'] );
			$args['page']++;
		} while ( $result['rows'] && count( $all ) < 10000 && $args['page'] <= $result['pages'] );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'time', 'classification', 'vendor', 'purpose', 'bot_name', 'verified', 'action', 'enforced', 'status', 'method', 'path', 'ua', 'reason' ) );
		foreach ( $all as $row ) {
			fputcsv(
				$out,
				array( $row['ts'], $row['classification'], $row['vendor'], $row['purpose'], $row['bot_name'], $row['verified'], $row['action'], $row['enforced'], $row['status_code'], $row['method'], $row['path'], $row['ua'], $row['reason'] )
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
