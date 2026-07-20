<?php
/**
 * Bot Audit Report statistics calculations and export generators.
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
	 * Hook initialization.
	 */
	public static function init() {
		add_action( 'atg_cron_daily', array( __CLASS__, 'maybe_trigger_shadow_completion' ) );
	}

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
				"SELECT classification, SUM(hits) AS total FROM {$stats_table} WHERE day >= %s GROUP BY classification",
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
				"SELECT vendor, SUM(hits) AS total FROM {$stats_table} WHERE vendor != '' AND day >= %s GROUP BY vendor ORDER BY total DESC LIMIT 5",
				$from_date
			),
			ARRAY_A
		);

		// Get Googlebot hits specifically for comparisons.
		$googlebot_hits = 0;
		foreach ( $vendors as $v ) {
			if ( 'google' === strtolower( $v['vendor'] ) ) {
				$googlebot_hits = (int) $v['total'];
			}
		}
		if ( ! $googlebot_hits ) {
			$googlebot_hits = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(hits) FROM {$stats_table} WHERE LOWER(vendor) = 'google' AND day >= %s",
					$from_date
				)
			);
		}

		// Estimate bandwidth wasted: standard 25KB per hit.
		$bandwidth_kb = $bot_hits * 25;
		$bandwidth_mb = round( $bandwidth_kb / 1024, 2 );

		// 3. Headline stat logic.
		$headline = __( 'No crawler activity detected this week.', 'ai-traffic-guardian' );
		if ( ! empty( $vendors ) ) {
			$top_vendor      = $vendors[0]['vendor'];
			$top_hits        = (int) $vendors[0]['total'];
			$top_vendor_pct  = $bot_hits ? round( ( $top_hits / $bot_hits ) * 100, 1 ) : 0;

			if ( $googlebot_hits > 0 && strtolower( $top_vendor ) !== 'google' ) {
				$ratio    = round( $top_hits / $googlebot_hits, 1 );
				$headline = sprintf(
					/* translators: 1: Bot Vendor name, 2: Ratio count */
					esc_html__( '%1$s visited %2$s times more often than Googlebot this week', 'ai-traffic-guardian' ),
					$top_vendor,
					$ratio
				);
			} else {
				$headline = sprintf(
					/* translators: 1: Bot Vendor name, 2: Percentage share */
					esc_html__( '%1$s accounted for %2$s%% of all crawler traffic this week', 'ai-traffic-guardian' ),
					$top_vendor,
					$top_vendor_pct
				);
			}
		}

		return array(
			'site_domain'       => isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'localhost',
			'period_start'      => $from_date,
			'period_end'        => gmdate( 'Y-m-d' ),
			'human_hits'        => $human_hits,
			'bot_hits'          => $bot_hits,
			'total_hits'        => $total_hits,
			'human_pct'         => $total_hits ? round( ( $human_hits / $total_hits ) * 100, 1 ) : 100,
			'bot_pct'           => $total_hits ? round( ( $bot_hits / $total_hits ) * 100, 1 ) : 0,
			'vendors'           => $vendors,
			'bandwidth_mb'      => $bandwidth_mb,
			'headline_stat'     => $headline,
			'standout'          => $headline,
		);
	}

	/**
	 * Daily shadow completion check cron handler.
	 */
	public static function maybe_trigger_shadow_completion() {
		$plugin      = ATG_Plugin::instance();
		$enforcement = $plugin->enforcement_mode();
		$started     = (int) $plugin->get( 'shadow_started', 0 );

		if ( 'shadow' === $enforcement && $started > 0 ) {
			if ( ( time() - $started ) >= 7 * DAY_IN_SECONDS ) {
				// Shadow mode 7-day completed! Generate the files.
				self::generate_report_files();
				update_option( 'atg_shadow_report_ready', true );
			}
		}
	}

	/**
	 * Generate and store the server-side PDF and PNG files in the uploads folder.
	 */
	public static function generate_report_files() {
		$stats = self::get_stats();

		// 1. Generate PDF
		$pdf = new ATG_PDF_Writer();

		// PDF raw content stream operators
		$stream = "BT /F1 22 Tf 50 780 Td (" . $stats['site_domain'] . " - Bot Audit Report) Tj\n"
				. "/F1 12 Tf 0 -40 Td (Period: " . $stats['period_start'] . " to " . $stats['period_end'] . ") Tj\n"
				. "0 -25 Td (Total Traversed Requests: " . number_format( $stats['total_hits'] ) . ") Tj\n"
				. "0 -20 Td (AI / Crawler Requests: " . number_format( $stats['bot_hits'] ) . " \(" . $stats['bot_pct'] . "%\)) Tj\n"
				. "0 -20 Td (Human Requests: " . number_format( $stats['human_hits'] ) . " \(" . $stats['human_pct'] . "%\)) Tj\n"
				. "0 -25 Td (Estimated Wasted Bandwidth: " . $stats['bandwidth_mb'] . " MB) Tj\n"
				. "0 -35 Td (Headline Stat:) Tj\n"
				. "/F1 14 Tf 0 -20 Td (" . $stats['headline_stat'] . ") Tj\n"
				. "ET";

		$pdf->add_page( $stream );
		$pdf_content = $pdf->output();

		$upload_dir = wp_upload_dir();
		$pdf_path   = $upload_dir['basedir'] . '/bot-audit-report.pdf';
		file_put_contents( $pdf_path, $pdf_content );

		// 2. Generate PNG using GD
		if ( function_exists( 'imagecreate' ) ) {
			$im         = imagecreate( 1200, 630 );
			$bg         = imagecolorallocate( $im, 15, 23, 42 ); // Dark blue Slate 900
			$text_color = imagecolorallocate( $im, 255, 255, 255 );
			$accent     = imagecolorallocate( $im, 56, 189, 248 ); // Sky blue

			imagestring( $im, 5, 50, 50, 'Bot Shield Pro - Shadow Audit Report', $accent );
			imagestring( $im, 5, 50, 100, 'Domain: ' . $stats['site_domain'], $text_color );
			imagestring( $im, 5, 50, 140, 'Crawler Traffic Share: ' . $stats['bot_pct'] . '%', $text_color );
			imagestring( $im, 5, 50, 180, 'Wasted Bandwidth: ' . $stats['bandwidth_mb'] . ' MB', $text_color );
			imagestring( $im, 4, 50, 240, $stats['headline_stat'], $accent );

			$png_path = $upload_dir['basedir'] . '/bot-audit-report.png';
			imagepng( $im, $png_path );
			imagedestroy( $im );
		}
	}
}
