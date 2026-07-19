<?php
/**
 * Bot Audit Report statistics calculations.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Report_Generator
 */
class ATG_Report_Generator {

	/**
	 * Compile 7-day audit statistics.
	 *
	 * @return array Audit stats.
	 */
	public static function get_stats() {
		global $wpdb;
		$stats_table = ATG_DB::table( 'stats' );
		$from_date   = gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS );

		// 1. Get hits by classification.
		$classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT classification, SUM(hits) AS total FROM %i WHERE day >= %s GROUP BY classification",
				$stats_table,
				$from_date
			),
			ARRAY_A
		);

		$human_hits = 0;
		$bot_hits   = 0;
		$total_hits = 0;

		foreach ( $classes as $c ) {
			$total = (int) $c['total'];
			$total_hits += $total;
			if ( in_array( $c['classification'], array( 'human', 'authenticated' ), true ) ) {
				$human_hits += $total;
			} else {
				$bot_hits += $total;
			}
		}

		// 2. Get top 5 vendors.
		$vendors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor, SUM(hits) AS total FROM %i WHERE vendor != '' AND day >= %s GROUP BY vendor ORDER BY total DESC LIMIT 5",
				$stats_table,
				$from_date
			),
			ARRAY_A
		);

		// Estimate bandwidth wasted: standard 25KB per hit.
		$bandwidth_kb = $bot_hits * 25;
		$bandwidth_mb = round( $bandwidth_kb / 1024, 2 );

		// Outstanding stat.
		$standout = '';
		if ( ! empty( $vendors ) ) {
			$top_vendor = $vendors[0]['vendor'];
			$top_hits   = (int) $vendors[0]['total'];
			$standout   = sprintf(
				__( '%s made %s hits on your site this week.', 'ai-traffic-guardian' ),
				$top_vendor,
				number_format( $top_hits )
			);
		} else {
			$standout = __( 'No crawler activity detected this week.', 'ai-traffic-guardian' );
		}

		return array(
			'human_hits'   => $human_hits,
			'bot_hits'     => $bot_hits,
			'total_hits'   => $total_hits,
			'human_pct'    => $total_hits ? round( ( $human_hits / $total_hits ) * 100, 1 ) : 100,
			'bot_pct'      => $total_hits ? round( ( $bot_hits / $total_hits ) * 100, 1 ) : 0,
			'vendors'      => $vendors,
			'bandwidth_mb' => $bandwidth_mb,
			'standout'     => $standout,
		);
	}
}
