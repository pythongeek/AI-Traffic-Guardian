<?php
/**
 * Compatibility layer: page-cache awareness, object-cache notes, multisite
 * handling, XML-RPC / REST policy differentiation.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Compat
 */
class ATG_Compat {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Challenge/throttle responses must never be cached by full-page
		// caches. Most WP page caches only cache 200 GETs, but we make the
		// contract explicit.
		add_action( 'send_headers', array( $this, 'cache_headers' ) );

		// XML-RPC: allow authenticated calls, track the rest.
		add_filter( 'xmlrpc_enabled', array( $this, 'xmlrpc_policy' ) );

		// Multisite: create tables when a new site is added to the network.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', array( $this, 'new_site' ), 99 );
		}
	}

	/**
	 * Explicit cache directives on classified responses.
	 */
	public function cache_headers() {
		$d = ATG_Classifier::$current;
		if ( ! $d ) {
			return;
		}
		if ( in_array( $d['action'], array( 'block', 'throttle' ), true ) ) {
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-store, no-cache, must-revalidate, private', false );
			}
		}
	}

	/**
	 * XML-RPC stays enabled for authenticated clients (mobile apps, remote
	 * publishing) but unauthenticated system.* calls are logged by the
	 * classifier as unknown traffic.
	 *
	 * @param bool $enabled Current flag.
	 * @return bool
	 */
	public function xmlrpc_policy( $enabled ) {
		return apply_filters( 'atg_xmlrpc_enabled', $enabled );
	}

	/**
	 * Provision tables for a newly created multisite site.
	 *
	 * @param WP_Site $site New site.
	 */
	public function new_site( $site ) {
		switch_to_blog( (int) $site->blog_id );
		ATG_DB::install();
		restore_current_blog();
	}

	/**
	 * Environment report for the settings screen (helps support).
	 *
	 * @return array
	 */
	public static function environment() {
		$report = array(
			'multisite'    => is_multisite(),
			'woocommerce'  => class_exists( 'WooCommerce' ),
			'object_cache' => wp_using_ext_object_cache(),
			'seo_plugins'  => ATG_Robots::detected_seo_plugins(),
			'php'          => PHP_VERSION,
			'wp'           => get_bloginfo( 'version' ),
		);

		// Security plugin conflict detection.
		$conflicts = array();
		if ( class_exists( 'WordfenceLS\Controller\ActivationSetup' ) || defined( 'WORDFENCE_VERSION' ) ) {
			$conflicts[] = array(
				'plugin'      => 'Wordfence',
				'issue'       => 'May rate-limit ATG REST API calls from its own firewall',
				'remediation' => 'Add /wp-json/atg/ to Wordfence allowlisted paths',
			);
		}
		if ( function_exists( 'cerber_get_ip' ) || class_exists( 'Cerber_Widget' ) ) {
			$conflicts[] = array(
				'plugin'      => 'WP Cerber',
				'issue'       => 'Has its own honeypot that may double-fire with ATG',
				'remediation' => 'Disable ATG honeypot for comment forms when WP Cerber is active',
			);
		}
		if ( defined( 'ITSEC_VERSION' ) || class_exists( 'ITSEC_Core' ) ) {
			$conflicts[] = array(
				'plugin'      => 'iThemes Security / Solid Security',
				'issue'       => 'File change detection may flag ATG table creation',
				'remediation' => 'Add ATG tables to iThemes exclusion list',
			);
		}
		if ( defined( 'AIO_WP_SECURITY_VERSION' ) || class_exists( 'AIOWPSecurity_Core' ) ) {
			$conflicts[] = array(
				'plugin'      => 'All In One WP Security',
				'issue'       => 'IP blocking may conflict with ATG transient rate limiter',
				'remediation' => 'Use ATG for bot traffic; use AIOS for brute-force login protection',
			);
		}
		if ( defined( 'FLAVOR' ) && 'flavor' === FLAVOR || class_exists( 'ICWP_WPSF' ) ) {
			$conflicts[] = array(
				'plugin'      => 'Shield Security',
				'issue'       => 'Has its own honeypot that may double-fire with ATG',
				'remediation' => 'Disable ATG comment honeypot when Shield is active',
			);
		}
		$report['conflicts'] = $conflicts;

		// Page cache detection.
		$caches = array();
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$caches[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			$caches[] = 'LiteSpeed Cache';
		}
		if ( defined( 'W3TC' ) ) {
			$caches[] = 'W3 Total Cache';
		}
		if ( defined( 'WPSC_VERSION' ) ) {
			$caches[] = 'WP Super Cache';
		}
		$report['page_caches'] = $caches;

		return $report;
	}
}
