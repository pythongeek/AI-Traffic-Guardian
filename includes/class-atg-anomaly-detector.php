<?php
/**
 * Traffic anomaly detection: spike and drop alerts.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATG_Anomaly_Detector {

	public function hooks() {
		add_action( 'atg_cron_daily', array( $this, 'run' ) );
	}

	public function run() {
		global $wpdb;
		$stats     = ATG_DB::table( 'stats' );
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$week_ago  = gmdate( 'Y-m-d', strtotime( '-8 days' ) );

		// Vendor-level spike detection.
		$vendors = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT vendor,
						SUM(CASE WHEN day = %s THEN hits ELSE 0 END) AS yesterday,
						AVG(CASE WHEN day >= %s AND day < %s THEN hits ELSE NULL END) AS avg7
				 FROM %i
				 WHERE vendor != \'\'
				   AND day >= %s
				 GROUP BY vendor
				 HAVING avg7 > 0',
				$yesterday,
				$week_ago,
				$yesterday,
				ATG_DB::table( 'stats' ),
				$week_ago
			),
			ARRAY_A
		);

		foreach ( $vendors as $row ) {
			$ratio = round( $row['yesterday'] / $row['avg7'], 1 );
			$purpose = '';
			foreach ( ATG_Bot_Database::signatures() as $sig ) {
				if ( $sig['vendor'] === $row['vendor'] ) {
					$purpose = $sig['purpose'];
					break;
				}
			}

			$threshold = (float) apply_filters( 'atg_anomaly_threshold', 3.0, $row['vendor'], $purpose );

			if ( $ratio > $threshold ) {
				ATG_Plugin::instance()->alerts->create(
					'anomaly_spike',
					sprintf(
						/* translators: 1: vendor name, 2: ratio value */
						__( '%1$s traffic is %2$sx above its 7-day average', 'ai-traffic-guardian' ),
						$row['vendor'],
						$ratio
					),
					array(
						'vendor'    => $row['vendor'],
						'yesterday' => (int) $row['yesterday'],
						'avg7'      => round( $row['avg7'], 1 ),
						'ratio'     => $ratio,
					)
				);
				do_action( 'atg_anomaly_detected', $row['vendor'], $ratio, $row );
			}
		}
	}
}
