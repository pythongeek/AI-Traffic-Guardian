<?php
/**
 * Business-critical allowlist. This layer runs BEFORE any classification and
 * its built-in entries (webhooks, cron, authenticated API, monitors) cannot
 * be overridden by the bot engine (gap-analysis P0).
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Allowlist
 */
class ATG_Allowlist {

	/**
	 * Default allowlist option value.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'ips'        => array(),   // User-defined IPs / CIDRs.
			'paths'      => array(),   // User-defined path prefixes.
			'uas'        => array(),   // User-defined UA substrings.
			'path_rules' => array(),   // User-defined path override rules.
		);
	}

	/**
	 * Immutable endpoint prefixes that must NEVER be bot-classified:
	 * payment webhooks, WooCommerce API, WP internals.
	 *
	 * @return array
	 */
	public static function protected_paths() {
		$paths = array(
			'/wc-api/',          // WooCommerce legacy webhooks (Stripe, PayPal…).
			'/wp-json/wc/',      // WooCommerce REST API.
			'/wp-json/wc-',      // WooCommerce sub-namespaces.
			'/wp-json/stripe/',  // Stripe plugin endpoints.
			'/wp-json/paypal/',  // PayPal plugin endpoints.
			'/wp-json/edd/',     // Easy Digital Downloads.
			'/wp-cron.php',      // WP-Cron.
			'/xmlrpc.php',       // Handled separately, but never bot-blocked here.
		);
		$preset = get_option( 'atg_preset', '' );
		if ( 'headless' === $preset ) {
			$paths[] = '/wp-json/wp/v2/';
			$custom_ns = get_option( 'atg_custom_rest_namespace', '' );
			if ( $custom_ns ) {
				$paths[] = '/wp-json/' . trim( $custom_ns, '/' ) . '/';
			}
		}
		return apply_filters( 'atg_protected_paths', $paths );
	}

	/**
	 * Get the merged allowlist (option + immutable entries).
	 *
	 * @return array
	 */
	public function get() {
		$stored = get_option( 'atg_allowlist', self::defaults() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * Persist the allowlist.
	 *
	 * @param array $data Allowlist data.
	 */
	public function update( $data ) {
		$clean = self::defaults();
		foreach ( array( 'ips', 'paths', 'uas' ) as $k ) {
			if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
				$clean[ $k ] = array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $data[ $k ] ) ) ) );
			}
		}
		if ( isset( $data['path_rules'] ) && is_array( $data['path_rules'] ) ) {
			foreach ( $data['path_rules'] as $rule ) {
				if ( ! empty( $rule['path'] ) && ! empty( $rule['action'] ) ) {
					$clean['path_rules'][] = array(
						'path'   => sanitize_text_field( $rule['path'] ),
						'action' => in_array( $rule['action'], array( 'allow', 'throttle', 'block' ), true ) ? $rule['action'] : 'allow',
					);
				}
			}
		}
		update_option( 'atg_allowlist', $clean );
		return $clean;
	}

	/**
	 * Is this request path untouchable?
	 *
	 * @param string $path Request URI path.
	 * @return string|false Matched rule or false.
	 */
	public function path_match( $path ) {
		$list  = $this->get();
		$paths = array_merge( self::protected_paths(), (array) $list['paths'] );
		foreach ( $paths as $prefix ) {
			if ( '' !== $prefix && 0 === stripos( $path, $prefix ) ) {
				return $prefix;
			}
		}
		return false;
	}

	/**
	 * Is this IP on the user allowlist (exact or CIDR)?
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public function ip_allowed( $ip ) {
		$list     = $this->get();
		$verifier = new ATG_Verifier();
		foreach ( (array) $list['ips'] as $entry ) {
			if ( '' === $entry ) {
				continue;
			}
			if ( $entry === $ip ) {
				return true;
			}
			if ( false !== strpos( $entry, '/' ) && $verifier->ip_in_cidr( $ip, $entry ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does this UA contain a user-allowlisted substring?
	 *
	 * @param string $ua User agent.
	 * @return string|false
	 */
	public function ua_allowed( $ua ) {
		$list = $this->get();
		foreach ( (array) $list['uas'] as $needle ) {
			if ( '' !== $needle && false !== stripos( $ua, $needle ) ) {
				return $needle;
			}
		}
		return false;
	}

	/**
	 * WordPress-internal automation check: WP-CLI, WP-Cron, authenticated REST,
	 * admin-ajax from logged-in users.
	 *
	 * @return string|false Reason if internal, false otherwise.
	 */
	public function is_wp_internal() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'WP-CLI';
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'WP-Cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && is_user_logged_in() ) {
			return 'Authenticated REST API';
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_user_logged_in() ) {
			return 'Authenticated AJAX';
		}
		return false;
	}

	/**
	 * Get override action for a request path if matched.
	 *
	 * @param string $path Request URI.
	 * @return string|false
	 */
	public function get_path_override( $path ) {
		$list  = $this->get();
		$rules = isset( $list['path_rules'] ) ? (array) $list['path_rules'] : array();
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['path'] ) && 0 === stripos( $path, $rule['path'] ) ) {
				return $rule['action'];
			}
		}
		return false;
	}
}
