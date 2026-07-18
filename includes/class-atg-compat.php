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

		$conflicts = array();
		if ( class_exists( 'WordfenceLS\Controller\ActivationSetup' ) || defined( 'WORDFENCE_VERSION' ) ) {
			$conflicts[] = 'Wordfence';
		}
		if ( function_exists( 'cerber_get_ip' ) || class_exists( 'Cerber_Widget' ) ) {
			$conflicts[] = 'WP Cerber';
		}
		if ( defined( 'ITSEC_VERSION' ) || class_exists( 'ITSEC_Core' ) ) {
			$conflicts[] = 'iThemes Security / Solid Security';
		}
		if ( defined( 'AIO_WP_SECURITY_VERSION' ) || class_exists( 'AIOWPSecurity_Core' ) ) {
			$conflicts[] = 'All In One WP Security';
		}
		$report['conflicts'] = $conflicts;

		return $report;
	}
}
