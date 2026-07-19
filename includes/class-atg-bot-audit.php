<?php
/**
 * Bot Audit Engine.
 *
 * Runs real, live checks against the site's configuration, database, and
 * network state. Returns no mock data: every check either passes/fails on
 * actual evidence or is clearly marked as skipped with the reason.
 *
 * Check severity levels:
 *   pass    – test ran and everything is fine
 *   warning – working but could be better
 *   fail    – something is wrong and needs attention
 *   info    – neutral information, no action required
 *   skip    – could not run (dependency missing, explain why)
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATG_Bot_Audit {

	/**
	 * Run every audit section and return a structured report.
	 *
	 * @return array Full audit report with score and sections.
	 */
	public function run() {
		$sections = array(
			'enforcement'   => $this->section_enforcement(),
			'database'      => $this->section_database(),
			'live_coverage' => $this->section_live_coverage(),
			'robots_txt'    => $this->section_robots_txt(),
			'protection'    => $this->section_protection(),
			'analytics'     => $this->section_analytics(),
			'performance'   => $this->section_performance(),
			'security'      => $this->section_security(),
			'seo_ai'        => $this->section_seo_ai(),
		);

		$score = $this->calculate_score( $sections );

		return array(
			'generated' => current_time( 'mysql' ),
			'score'     => $score,
			'grade'     => $this->grade( $score ),
			'sections'  => $sections,
		);
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Enforcement Config
	 * --------------------------------------------------------------------- */
	private function section_enforcement() {
		$plugin  = ATG_Plugin::instance();
		$mode    = $plugin->enforcement_mode();
		$checks  = array();

		// Mode check.
		if ( 'active' === $mode ) {
			$checks[] = $this->pass( 'mode', 'Enforcement is Active', 'Your site is actively blocking or throttling bots based on your policy. Rules are being enforced on real traffic.' );
		} elseif ( 'shadow' === $mode ) {
			$started = (int) $plugin->get( 'shadow_started', 0 );
			$days_in = $started ? round( ( time() - $started ) / DAY_IN_SECONDS ) : 0;
			$checks[] = $this->warning(
				'mode',
				'Enforcement is in Shadow Mode — bots are NOT being blocked yet',
				"You've been observing for {$days_in} day(s). Shadow mode is perfect for learning what hits your site, but no bot has been blocked yet. When you're ready, click 'Go live' in the dashboard.",
				array(
					'title' => 'Switch to Active enforcement',
					'steps' => array(
						'Go to Bot Shield Pro → Dashboard.',
						"Review the Traffic Log for any false positives (real visitors that were incorrectly flagged as bots).",
						"Click the amber 'Go live: start enforcing' button in the Shadow Mode banner.",
						"Monitor the dashboard for the next 24 hours to confirm no real visitors are being blocked.",
					),
				)
			);
		} else {
			$checks[] = $this->fail(
				'mode',
				'Protection is completely OFF',
				'The panic switch is active. No bots are being classified, throttled, or blocked. This is only intended as a temporary emergency measure.',
				array(
					'title' => 'Re-enable protection',
					'steps' => array(
						'Go to Bot Shield Pro → Dashboard.',
						"Click 'Resume (shadow mode)' in the top bar to re-enable protection in safe observation mode.",
						"If everything looks good in shadow mode, promote to Active enforcement.",
					),
				)
			);
		}

		// Auth bypass.
		if ( $plugin->get( 'auth_bypass', true ) ) {
			$checks[] = $this->pass( 'auth_bypass', 'Logged-in users bypass bot classification', 'Your real customers and editors will never be blocked — regardless of what any bot classifier decides.' );
		} else {
			$checks[] = $this->fail(
				'auth_bypass',
				'Auth bypass is disabled — logged-in users can be flagged as bots',
				'This setting can cause real editors, admins, and customers to be blocked or throttled. It is almost never the right choice.',
				array(
					'title' => 'Enable auth bypass',
					'steps' => array(
						'Go to Bot Shield Pro → Settings.',
						"Check 'Authenticated users bypass all bot classification (strongly recommended)'.",
						"Save settings.",
					),
				)
			);
		}

		// Shadow grace.
		if ( 'shadow' === $mode ) {
			$started = (int) $plugin->get( 'shadow_started', 0 );
			$days    = (int) $plugin->get( 'shadow_days', 7 );
			if ( $started && ( time() - $started ) > ( $days * DAY_IN_SECONDS ) ) {
				$checks[] = $this->warning(
					'shadow_grace',
					'Observation period has ended — you can go live',
					"Your configured {$days}-day observation window is complete. The data in your Traffic Log is now a reliable picture of your real bot traffic.",
					array(
						'title' => 'Review and go live',
						'steps' => array(
							'Go to Bot Shield Pro → Traffic Log.',
							"Filter by Classification = 'Humans' to check that no real visitors were flagged incorrectly.",
							"When confident, return to the Dashboard and click 'Go live'.",
						),
					)
				);
			}
		}

		return array( 'label' => 'Enforcement Configuration', 'icon' => 'shield-alt', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Database & Data Health
	 * --------------------------------------------------------------------- */
	private function section_database() {
		global $wpdb;
		$checks = array();

		// Table existence.
		$tables = array( 'log', 'stats', 'alerts' );
		foreach ( $tables as $t ) {
			$table  = ATG_DB::table( $t );
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ); // phpcs:ignore
			if ( $exists ) {
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
				$checks[] = $this->pass( "table_{$t}", "Table '{$table}' exists ({$count} rows)", 'Database table is healthy and accessible.' );
			} else {
				$checks[] = $this->fail(
					"table_{$t}",
					"Database table '{$table}' is missing",
					'This table is required for the plugin to work. Traffic will not be logged until it is created.',
					array(
						'title' => 'Re-create missing tables',
						'steps' => array(
							'Go to Bot Shield Pro → Settings.',
							"Scroll to the bottom and look for a 'Rebuild database tables' button, OR",
							'Deactivate the plugin from Plugins → Installed Plugins, then reactivate it.',
						),
					)
				);
			}
		}

		// Log data.
		$log_table = ATG_DB::table( 'log' );
		$log_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ); // phpcs:ignore
		if ( $log_count === 0 ) {
			$plugin = ATG_Plugin::instance();
			if ( 'off' === $plugin->enforcement_mode() ) {
				$checks[] = $this->info( 'log_empty', 'Traffic log is empty — protection is currently OFF', 'Enable shadow or active mode to start recording traffic.' );
			} else {
				$checks[] = $this->warning( 'log_empty', 'No traffic has been logged yet', 'Either the site has had no traffic since activation, or logging is very new. Check back after the site receives some visitors.' );
			}
		} else {
			$newest = $wpdb->get_var( "SELECT ts FROM {$log_table} ORDER BY id DESC LIMIT 1" ); // phpcs:ignore
			$checks[] = $this->pass( 'log_data', "Traffic log contains {$log_count} records (latest: {$newest})", 'Traffic logging is working correctly.' );
		}

		// Retention.
		$plugin = ATG_Plugin::instance();
		$ret    = (int) $plugin->get( 'retention_days', 30 );
		if ( $ret < 14 ) {
			$checks[] = $this->warning( 'retention', "Retention is set to {$ret} days — very short", 'Short retention means you lose trend data quickly. 30–90 days gives you enough history for useful pattern analysis.', array(
				'title' => 'Increase retention period',
				'steps' => array( 'Go to Bot Shield Pro → Settings → Privacy & Data.', 'Set Log retention to 30 or more days.', 'Save settings.' ),
			) );
		} else {
			$checks[] = $this->pass( 'retention', "Log retention set to {$ret} days", 'Good balance between history depth and database size.' );
		}

		// Object cache.
		if ( wp_using_ext_object_cache() ) {
			$checks[] = $this->pass( 'object_cache', 'External object cache detected', 'Rate-limit buckets and bot verification results are stored in a persistent cache. This is the fastest possible configuration.' );
		} else {
			$checks[] = $this->warning( 'object_cache', 'No persistent object cache — rate limiting uses the database', 'Rate-limit buckets are stored as transients in the database. This works but adds database queries. Installing a Redis or Memcached object cache (WP Redis plugin) eliminates this overhead.', array(
				'title' => 'Add a persistent object cache',
				'steps' => array(
					"Ask your host if Redis or Memcached is available (most managed WordPress hosts include this).",
					"Install and activate WP Redis (for Redis) or W3 Total Cache (for Memcached).",
					"No Bot Shield Pro configuration change is needed — it detects the cache automatically.",
				),
			) );
		}

		return array( 'label' => 'Database & Data Health', 'icon' => 'database', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Live Bot Coverage
	 * --------------------------------------------------------------------- */
	private function section_live_coverage() {
		global $wpdb;
		$checks = array();
		$plugin = ATG_Plugin::instance();
		$stats  = ATG_DB::table( 'stats' );
		$log    = ATG_DB::table( 'log' );

		$has_data = (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" ); // phpcs:ignore

		if ( ! $has_data ) {
			$checks[] = $this->info( 'no_traffic', 'No traffic data yet — run audit again after the site receives visitors', 'Coverage analysis requires real bot traffic to have been logged. Come back after traffic has been recorded.' );
			return array( 'label' => 'Live Bot Coverage', 'icon' => 'networking', 'checks' => $checks );
		}

		// Which bots have hit the site in the last 30 days?
		$active_bots = $wpdb->get_results( // phpcs:ignore
			"SELECT vendor, purpose, action, SUM(hits) as total FROM {$stats}
			 WHERE vendor != '' AND day >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			 GROUP BY vendor, purpose, action ORDER BY total DESC LIMIT 30",
			ARRAY_A
		);

		// Bots that are being blocked vs allowed.
		$blocked_hits  = 0;
		$throttled_hits = 0;
		$allowed_hits  = 0;
		foreach ( (array) $active_bots as $b ) {
			if ( 'block' === $b['action'] )    $blocked_hits  += (int) $b['total'];
			if ( 'throttle' === $b['action'] ) $throttled_hits += (int) $b['total'];
			if ( 'allow' === $b['action'] )    $allowed_hits   += (int) $b['total'];
		}

		// Spoofed attempts.
		$spoofed_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log} WHERE spoofed = 1" ); // phpcs:ignore

		if ( empty( $active_bots ) ) {
			$checks[] = $this->info( 'no_bots', 'No identified bots seen in the last 30 days', 'Your site may not have been crawled recently, or traffic is below detection threshold.' );
		} else {
			$bot_count = count( array_unique( array_column( $active_bots, 'vendor' ) ) );
			$checks[] = $this->pass( 'active_bots', "{$bot_count} distinct bot vendor(s) seen in the last 30 days", 'Coverage analysis based on real traffic.' );
		}

		if ( $spoofed_count > 0 ) {
			$checks[] = $this->fail(
				'spoofed',
				"{$spoofed_count} spoofed bot identity attempt(s) detected and blocked",
				'Something is pretending to be a major crawler (like Googlebot) but failing the identity verification check. This is a hostile signal and Bot Shield Pro has blocked these automatically.',
				array(
					'title' => 'Review spoofed bot attempts',
					'steps' => array(
						'Go to Bot Shield Pro → Traffic Log.',
						"Use the filter to look for Classification = 'Known bots' and Decision = 'Blocked'.",
						"Look for rows where the 'Verified' column says 'spoofed'.",
						"If you see IPs repeating, consider adding them to your host-level firewall or Cloudflare.",
					),
				)
			);
		}

		// AI training bots with "allow" action — flag as a potential miss.
		$training_allowed = array_filter( (array) $active_bots, function( $b ) { return 'ai_training' === $b['purpose'] && 'allow' === $b['action']; } );
		if ( ! empty( $training_allowed ) ) {
			$names = implode( ', ', array_column( array_values( $training_allowed ), 'vendor' ) );
			$checks[] = $this->warning(
				'training_allowed',
				"AI training crawlers currently set to Allow: {$names}",
				"These crawlers scrape your content to train AI models — they don't send you traffic in return. Most site owners prefer to throttle or block them.",
				array(
					'title' => 'Set AI training bots to Throttle',
					'steps' => array(
						'Go to Bot Shield Pro → AI Policy Matrix.',
						"Find each vendor listed above.",
						"Set the 'ai_training' purpose dropdown to Throttle or Block.",
						"The change takes effect immediately — no save button needed.",
					),
				)
			);
		}

		// Unknown bots with significant volume.
		$unknown_hits = (int) $wpdb->get_var( // phpcs:ignore
			"SELECT SUM(hits) FROM {$stats} WHERE classification = 'unknown_bot' AND day >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
		);
		if ( $unknown_hits > 100 ) {
			$checks[] = $this->warning(
				'unknown_volume',
				"{$unknown_hits} requests from unrecognized bots in the last 7 days",
				'These are automated clients that don\'t match any known signature. They\'re being throttled by default. Check your Alerts page to see their user agents and decide whether to allow or block specific ones.',
				array(
					'title' => 'Review unknown bots',
					'steps' => array(
						'Go to Bot Shield Pro → Alerts.',
						"Review the user agent strings listed.",
						"For any you recognize as a trusted service (like your monitoring tool), add its UA to the Allowlist.",
						"For suspicious ones, no action needed — they're already throttled.",
					),
				)
			);
		} elseif ( $unknown_hits > 0 ) {
			$checks[] = $this->info( 'unknown_low', "{$unknown_hits} unknown bot request(s) in the last 7 days (low volume — monitor only)", 'Low enough to just watch. Check Alerts if the number grows.' );
		}

		return array( 'label' => 'Live Bot Coverage', 'icon' => 'networking', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: robots.txt
	 * --------------------------------------------------------------------- */
	private function section_robots_txt() {
		$checks = array();
		$plugin = ATG_Plugin::instance();

		// Fetch the live robots.txt.
		$robots_url = home_url( '/robots.txt' );
		$response   = wp_remote_get( $robots_url, array( 'timeout' => 10, 'user-agent' => 'ATG-Audit/' . ATG_VERSION ) );

		if ( is_wp_error( $response ) ) {
			$checks[] = $this->fail(
				'robots_fetch',
				'Could not fetch ' . $robots_url . ': ' . $response->get_error_message(),
				'Your robots.txt is not accessible. This means crawlers ignore your crawl rules, including ones meant to block unwanted AI bots.',
				array(
					'title' => 'Fix robots.txt access',
					'steps' => array(
						'Make sure your site is not in Maintenance Mode.',
						'Check if a security plugin is blocking the /robots.txt URL.',
						'In WordPress: go to Settings → Reading and make sure "Discourage search engines" is NOT checked (unless intentional).',
					),
				)
			);
			return array( 'label' => 'robots.txt', 'icon' => 'text-page', 'checks' => $checks );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$checks[] = $this->fail(
				'robots_status',
				"robots.txt returned HTTP {$code} instead of 200",
				'Crawlers expect a 200 OK response. A 404 means they will assume no restrictions and crawl freely.',
				array( 'title' => 'Fix robots.txt', 'steps' => array( 'WordPress generates robots.txt dynamically on non-Multisite installs — check that no plugin or theme is returning a 404 for this URL.', 'If you use a physical robots.txt file in your root, it may override WordPress. Delete it and let WordPress handle it.' ) )
			);
			return array( 'label' => 'robots.txt', 'icon' => 'text-page', 'checks' => $checks );
		}

		$checks[] = $this->pass( 'robots_accessible', 'robots.txt is publicly accessible (HTTP 200)', "Fetched {$code} from {$robots_url}." );

		// Check for ATG rules.
		$has_atg = ( false !== strpos( $body, 'Bot Shield Pro' ) || false !== strpos( $body, 'AI Traffic Guardian' ) );
		if ( 'auto' === $plugin->get( 'robots_mode', 'auto' ) ) {
			if ( $has_atg ) {
				$checks[] = $this->pass( 'robots_atg', 'Bot Shield Pro bot rules are present in robots.txt', 'Your policy matrix is being reflected in robots.txt automatically.' );
			} else {
				$checks[] = $this->warning(
					'robots_atg',
					"Bot Shield Pro is set to Auto mode but its rules aren't in robots.txt yet",
					'This is usually because all your current policies are set to Allow (so there are no Disallow rules to add), OR the rewrite rules need flushing.',
					array(
						'title' => 'Trigger a rewrite flush',
						'steps' => array(
							'Go to Settings → Permalinks in WordPress and click Save Changes (this flushes rewrite rules without changing anything).',
							'If the rules still don\'t appear, check if any of your bot policies are set to Block or Throttle — Allow bots never appear in robots.txt.',
						),
					)
				);
			}
		}

		// Googlebot not blocked.
		if ( preg_match( '/User-agent:\s*Googlebot/i', $body ) && preg_match( '/Disallow:\s*\//i', $body ) ) {
			// Check if Googlebot section has Disallow /.
			$google_section = $this->extract_agent_section( $body, 'Googlebot' );
			if ( preg_match( '/Disallow:\s*\//i', $google_section ) ) {
				$checks[] = $this->fail(
					'googlebot_blocked',
					'Googlebot appears to be blocked in robots.txt — this will destroy your search rankings',
					'A "Disallow: /" rule under "User-agent: Googlebot" stops Google from indexing your site. If this is intentional (private site), ignore this. Otherwise, fix immediately.',
					array(
						'title' => 'Unblock Googlebot',
						'steps' => array(
							'Go to Bot Shield Pro → AI Policy Matrix.',
							"Set Google / search_engine to Allow.",
							"If you use a physical robots.txt file, edit it to remove the Disallow rule under Googlebot.",
						),
					)
				);
			}
		} else {
			$checks[] = $this->pass( 'googlebot_ok', 'Googlebot is not blocked in robots.txt', 'Google can index your site normally.' );
		}

		// SEO plugin conflict check.
		$seo_plugins = ATG_Robots::detected_seo_plugins();
		if ( ! empty( $seo_plugins ) ) {
			$plugin_names = implode( ', ', $seo_plugins );
			$checks[] = $this->pass( 'seo_compat', "SEO plugin(s) detected: {$plugin_names}", 'Bot Shield Pro appends its rules after your SEO plugin\'s rules. There is no conflict — both sets of rules coexist safely.' );
		}

		return array( 'label' => 'robots.txt', 'icon' => 'text-page', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Form & Checkout Protection
	 * --------------------------------------------------------------------- */
	private function section_protection() {
		$checks = array();
		$plugin = ATG_Plugin::instance();

		$honeypot = $plugin->get( 'honeypot_enabled', true );
		if ( ! $honeypot ) {
			$checks[] = $this->warning(
				'honeypot_off',
				'Honeypot protection is disabled',
				'Your forms (comments, registration, checkout) have no bot protection. Spam bots and form-flooding attacks will get through.',
				array( 'title' => 'Enable honeypots', 'steps' => array( 'Go to Bot Shield Pro → Forms & Checkout.', "Check 'Enable honeypot protection' and choose which forms to protect.", 'Save protection settings.' ) )
			);
		} else {
			$protected = array();
			if ( $plugin->get( 'protect_comments', true ) )      $protected[] = 'Comments';
			if ( $plugin->get( 'protect_registration', true ) )  $protected[] = 'Registration';
			if ( $plugin->get( 'protect_woocommerce', true ) && class_exists( 'WooCommerce' ) ) $protected[] = 'WooCommerce checkout';
			if ( $plugin->get( 'protect_login', false ) )        $protected[] = 'Login';

			$checks[] = $this->pass(
				'honeypot_on',
				'Honeypot protection is active on: ' . implode( ', ', $protected ?: array( 'none selected' ) ),
				'Invisible bot traps are active on your selected forms. Real human visitors are not affected.'
			);
		}

		// Timing checks warning.
		if ( $plugin->get( 'timing_checks', false ) ) {
			$checks[] = $this->warning(
				'timing_on',
				'Timing checks are enabled — possible accessibility risk',
				"Timing checks reject submissions that arrive 'too fast'. This can incorrectly block users who use autofill, password managers, or who have motor disabilities. Leave off unless you're seeing extremely high spam volumes.",
				array( 'title' => 'Evaluate whether timing checks are needed', 'steps' => array( 'Go to Bot Shield Pro → Forms & Checkout.', 'Check your Traffic Log for form_abuse entries — if spam is genuinely high, keep timing checks on.', 'Otherwise uncheck "Timing checks" to avoid accessibility issues.' ) )
			);
		} else {
			$checks[] = $this->pass( 'timing_off', 'Timing checks are off (accessibility-safe default)', 'Users with autofill, password managers, or motor disabilities can submit forms without being flagged.' );
		}

		// WooCommerce checks.
		if ( class_exists( 'WooCommerce' ) ) {
			$max    = (int) $plugin->get( 'woo_max_attempts', 5 );
			$window = (int) $plugin->get( 'woo_window_min', 10 );
			if ( $plugin->get( 'protect_woocommerce', true ) ) {
				$checks[] = $this->pass( 'woo_velocity', "WooCommerce card-testing protection: max {$max} checkout attempts per {$window} minutes", 'Anonymous checkout velocity is being monitored. Logged-in customers are exempt.' );
			} else {
				$checks[] = $this->warning(
					'woo_off',
					'WooCommerce is active but checkout protection is disabled',
					'Card-testing bots can attempt unlimited fraudulent purchases. This costs you in chargeback fees and risks your payment processor relationship.',
					array( 'title' => 'Enable checkout protection', 'steps' => array( 'Go to Bot Shield Pro → Forms & Checkout.', "Check 'WooCommerce checkout'.", "Save protection settings." ) )
				);
			}
		}

		return array( 'label' => 'Form & Checkout Protection', 'icon' => 'lock', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Analytics Integrity
	 * --------------------------------------------------------------------- */
	private function section_analytics() {
		$checks = array();
		$plugin = ATG_Plugin::instance();
		$mode   = $plugin->get( 'ga4_mode', 'compat' );

		if ( 'off' === $mode ) {
			$checks[] = $this->warning(
				'analytics_off',
				'Analytics bot filtering is disabled',
				'Bot sessions are being included in your website statistics. This inflates page view counts and distorts conversion rates — a site with 40% bot traffic can show falsely low conversion rates.',
				array( 'title' => 'Enable analytics bot filtering', 'steps' => array( 'Go to Bot Shield Pro → Analytics Integrity.', "Set Mode to 'Compatibility mode' (recommended — works with GTM without breaking anything).", "Save analytics settings." ) )
			);
		} elseif ( 'compat' === $mode ) {
			$checks[] = $this->pass( 'analytics_compat', 'Analytics compatibility mode is active', "Bot sessions are tagged with atg_bot=true in the GTM dataLayer. In GA4, create a custom dimension on 'atg_bot' to filter or segment bot vs. human traffic." );
			$checks[] = $this->info( 'analytics_tip', 'Tip: Create an atg_bot custom dimension in GA4', "In GA4: Admin → Custom definitions → Create custom dimension → Set 'Event parameter' to 'atg_bot'. Then use it in Explorations to exclude bot sessions from your reports." );
		} elseif ( 'conditional' === $mode ) {
			$checks[] = $this->pass( 'analytics_conditional', 'Analytics conditional mode active — GA4 does not load for bots', "Beacons are never sent for flagged bot sessions. Your analytics data is cleaner but requires any GA4 snippet to go through standard wp_enqueue_scripts (hard-coded theme snippets are not affected)." );
		}

		// Server-side purchase check.
		if ( class_exists( 'WooCommerce' ) ) {
			$ss = $plugin->get( 'ga4_server_purchase', false );
			if ( $ss ) {
				$mid    = trim( (string) $plugin->get( 'ga4_measurement_id', '' ) );
				$secret = trim( (string) $plugin->get( 'ga4_api_secret', '' ) );
				if ( '' === $mid || '' === $secret ) {
					$checks[] = $this->fail(
						'ss_keys',
						'Server-side purchase events enabled but GA4 keys are missing',
						'No purchase events will fire until the Measurement ID and API Secret are set.',
						array( 'title' => 'Add GA4 keys', 'steps' => array( 'Go to Bot Shield Pro → Analytics Integrity.', "Enter your GA4 Measurement ID (starts with G-).", "Enter your Measurement Protocol API Secret (GA4 Admin → Data Streams → select stream → Measurement Protocol API secrets → Create).", "Save analytics settings." ) )
					);
				} else {
					$checks[] = $this->pass( 'ss_purchase', 'Server-side WooCommerce purchase events configured', "GA4 Measurement ID: {$mid}. Purchase events fire server-side on payment completion — bots cannot fake them and ad blockers cannot prevent them." );
				}
			}
		}

		return array( 'label' => 'Analytics Integrity', 'icon' => 'chart-bar', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Performance Impact
	 * --------------------------------------------------------------------- */
	private function section_performance() {
		$checks = array();
		global $wpdb;

		// Log table row count vs. performance.
		$log   = ATG_DB::table( 'log' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" ); // phpcs:ignore
		if ( $count > 500000 ) {
			$checks[] = $this->warning(
				'log_large',
				"Log table has {$count} rows — consider reducing retention",
				'Very large tables slow down the Traffic Log page and CSV exports. The daily statistics table is already optimised for the dashboard charts.',
				array( 'title' => 'Reduce log retention', 'steps' => array( 'Go to Bot Shield Pro → Settings → Privacy & Data.', "Reduce 'Log retention' to 30 days.", "Save settings. The next daily cron run will prune old rows." ) )
			);
		} elseif ( $count > 0 ) {
			$checks[] = $this->pass( 'log_size', "Log table size is healthy ({$count} rows)", 'No performance concern at this volume.' );
		}

		// Rate limiting bucket type.
		if ( wp_using_ext_object_cache() ) {
			$checks[] = $this->pass( 'rl_cache', 'Rate-limit buckets use persistent cache (fast)', 'Session and IP counters are stored in Redis/Memcached — no database round-trips for rate limiting.' );
		} else {
			$checks[] = $this->info( 'rl_db', 'Rate-limit buckets use database transients', 'Works correctly but adds a small DB query per classified request. Add an object cache plugin to eliminate this.' );
		}

		// Human logging.
		$plugin = ATG_Plugin::instance();
		if ( $plugin->get( 'log_humans', false ) ) {
			$checks[] = $this->warning(
				'log_humans',
				'Row-level human logging is ON — this grows the log table quickly',
				'Every human page view is written as a DB row. This is only useful for debugging; leave it off in production.',
				array( 'title' => 'Disable human row-level logging', 'steps' => array( 'Go to Bot Shield Pro → Settings → Privacy & Data.', "Uncheck 'Log human requests row-by-row'.", "Save settings." ) )
			);
		} else {
			$checks[] = $this->pass( 'log_humans_off', 'Human traffic is aggregated only (not logged row-by-row)', 'Database stays lean — only bot decisions are written individually.' );
		}

		return array( 'label' => 'Performance Impact', 'icon' => 'performance', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: Security & Privacy
	 * --------------------------------------------------------------------- */
	private function section_security() {
		$checks = array();
		$plugin = ATG_Plugin::instance();

		// IP hashing.
		if ( $plugin->get( 'hash_ips', true ) ) {
			$checks[] = $this->pass( 'ip_hash', 'IP addresses are hashed in the log (GDPR-friendly)', 'Raw IPs are never stored. Logs contain a salted SHA-256 hash that cannot be reversed.' );
		} else {
			$checks[] = $this->warning(
				'ip_raw',
				'Raw IP addresses are being stored in the log',
				'This may create GDPR / CCPA obligations depending on your jurisdiction. IP addresses are considered personal data in most EU interpretations.',
				array( 'title' => 'Enable IP hashing', 'steps' => array( 'Go to Bot Shield Pro → Settings → Privacy & Data.', "Check 'Hash IP addresses in logs (GDPR-friendly)'.", "Save settings. Existing raw IPs are not retroactively hashed — if needed, manually clear the log table." ) )
			);
		}

		// HTTPS check.
		if ( is_ssl() || ( function_exists( 'str_starts_with' ) && str_starts_with( home_url(), 'https://' ) ) || 0 === strpos( home_url(), 'https://' ) ) {
			$checks[] = $this->pass( 'ssl', 'Site uses HTTPS', 'Bot classification data, session cookies, and admin API calls are transmitted securely.' );
		} else {
			$checks[] = $this->fail(
				'ssl',
				'Site is not using HTTPS',
				'The session cookie (atg_sid) and REST API calls are sent in plain text. Anyone on the same network can read them. This is a general site security issue, not just a Bot Shield Pro issue.',
				array( 'title' => 'Enable HTTPS', 'steps' => array( "Contact your host — most include a free Let's Encrypt SSL certificate.", "Most managed WordPress hosts have a one-click SSL button in their control panel.", "After enabling SSL, update WordPress address and Site address in Settings → General to https://." ) )
			);
		}

		// Alert new bot.
		if ( ! $plugin->get( 'alert_new_bot', true ) ) {
			$checks[] = $this->warning(
				'alerts_off',
				'New bot alerts are disabled',
				'You won\'t be notified when an unrecognized AI crawler starts hitting your site.',
				array( 'title' => 'Enable new bot alerts', 'steps' => array( 'Go to Bot Shield Pro → Settings → Privacy & Data.', "Check 'Alert me when a new AI bot signature appears'.", "Save settings." ) )
			);
		} else {
			$checks[] = $this->pass( 'alerts_on', 'New bot signature alerts are enabled', 'You will be notified when unknown AI crawlers appear.' );
		}

		// Data deletion setting.
		if ( $plugin->get( 'delete_data_on_uninstall', false ) ) {
			$checks[] = $this->info( 'delete_on_uninstall', 'Plugin is configured to delete all data on uninstall', 'All tables and settings will be permanently deleted if the plugin is removed. This is GDPR-friendly but means you lose your traffic history.' );
		} else {
			$checks[] = $this->info( 'keep_on_uninstall', 'Data is preserved if the plugin is uninstalled', 'Tables survive plugin removal. You can change this in Settings → Data Management.' );
		}

		return array( 'label' => 'Security & Privacy', 'icon' => 'privacy', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * SECTION: SEO & AI Discovery
	 * --------------------------------------------------------------------- */
	private function section_seo_ai() {
		$checks = array();
		$plugin = ATG_Plugin::instance();

		// llms.txt.
		if ( $plugin->get( 'llms_enabled', false ) ) {
			$llms_url  = home_url( '/llms.txt' );
			$response  = wp_remote_get( $llms_url, array( 'timeout' => 8, 'user-agent' => 'ATG-Audit/' . ATG_VERSION ) );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body_len = strlen( wp_remote_retrieve_body( $response ) );
				$checks[] = $this->pass( 'llms_ok', "/llms.txt is live and accessible ({$body_len} bytes)", "AI answer engines like ChatGPT and Perplexity can discover your site's key content through this file." );
			} else {
				$checks[] = $this->fail(
					'llms_broken',
					'/llms.txt is enabled in settings but returns an error',
					'The file is enabled but not accessible. This may be a rewrite rule or caching issue.',
					array( 'title' => 'Fix llms.txt', 'steps' => array( 'Go to Settings → Permalinks in WordPress and click Save Changes to flush rewrite rules.', 'Clear your page cache if using a caching plugin.', "Visit " . home_url('/llms.txt') . " in your browser to confirm it works." ) )
				);
			}
		} else {
			$checks[] = $this->info(
				'llms_off',
				'/llms.txt is not enabled',
				'This file helps AI answer engines (ChatGPT, Perplexity, Claude) discover and correctly represent your site. Enabling it can increase AI citation traffic.',
				array( 'title' => 'Enable llms.txt', 'steps' => array( 'Go to Bot Shield Pro → SEO & AI Discovery.', "Check 'Enable /llms.txt'.", "Fill in an intro line describing your site.", "Save SEO settings.", "Go to Settings → Permalinks and click Save Changes to register the URL." ) )
			);
		}

		// robots.txt mode.
		$robots_mode = $plugin->get( 'robots_mode', 'auto' );
		if ( 'auto' === $robots_mode ) {
			$checks[] = $this->pass( 'robots_auto', 'robots.txt reflects your policy matrix automatically', "When you change a bot's policy to Block, its Disallow rule appears in robots.txt within seconds." );
		} else {
			$checks[] = $this->warning(
				'robots_manual',
				'robots.txt is in Manual mode',
				"Your policy matrix changes don't automatically update robots.txt. You need to manually copy the snippet from the SEO page and paste it into your robots.txt file.",
				array( 'title' => 'Switch to Automatic mode', 'steps' => array( 'Go to Bot Shield Pro → SEO & AI Discovery.', "Set Mode to 'Automatic'.", "Save SEO settings." ) )
			);
		}

		return array( 'label' => 'SEO & AI Discovery', 'icon' => 'search', 'checks' => $checks );
	}

	/* -----------------------------------------------------------------------
	 * Score + helpers
	 * --------------------------------------------------------------------- */
	private function calculate_score( $sections ) {
		$total  = 0;
		$earned = 0;
		$weights = array( 'fail' => 0, 'warning' => 0.5, 'pass' => 1, 'info' => 1, 'skip' => 1 );
		foreach ( $sections as $section ) {
			foreach ( $section['checks'] as $check ) {
				$total++;
				$earned += isset( $weights[ $check['status'] ] ) ? $weights[ $check['status'] ] : 0.5;
			}
		}
		return $total > 0 ? (int) round( ( $earned / $total ) * 100 ) : 0;
	}

	private function grade( $score ) {
		if ( $score >= 90 ) return array( 'letter' => 'A', 'label' => 'Excellent', 'color' => '#059669' );
		if ( $score >= 75 ) return array( 'letter' => 'B', 'label' => 'Good',      'color' => '#16a34a' );
		if ( $score >= 60 ) return array( 'letter' => 'C', 'label' => 'Fair',      'color' => '#d97706' );
		if ( $score >= 40 ) return array( 'letter' => 'D', 'label' => 'Poor',      'color' => '#dc2626' );
		return array( 'letter' => 'F', 'label' => 'Critical', 'color' => '#991b1b' );
	}

	private function pass( $id, $label, $detail ) {
		return array( 'id' => $id, 'status' => 'pass', 'label' => $label, 'detail' => $detail, 'fix' => null );
	}
	private function fail( $id, $label, $detail, $fix = null ) {
		return array( 'id' => $id, 'status' => 'fail', 'label' => $label, 'detail' => $detail, 'fix' => $fix );
	}
	private function warning( $id, $label, $detail, $fix = null ) {
		return array( 'id' => $id, 'status' => 'warning', 'label' => $label, 'detail' => $detail, 'fix' => $fix );
	}
	private function info( $id, $label, $detail, $fix = null ) {
		return array( 'id' => $id, 'status' => 'info', 'label' => $label, 'detail' => $detail, 'fix' => $fix );
	}

	private function extract_agent_section( $robots_txt, $agent ) {
		$lines   = explode( "\n", $robots_txt );
		$in      = false;
		$section = '';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^User-agent:\s*' . preg_quote( $agent, '/' ) . '$/i', $line ) ) {
				$in = true;
			} elseif ( $in && preg_match( '/^User-agent:/i', $line ) ) {
				break;
			}
			if ( $in ) $section .= $line . "\n";
		}
		return $section;
	}
}
