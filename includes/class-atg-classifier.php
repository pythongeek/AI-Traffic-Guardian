<?php
/**
 * Request classification engine. Pipeline order (gap-analysis compliant):
 *
 *   1. WP-internal automation (cron/CLI/authenticated REST) → allow
 *   2. Authenticated users → always human, never blocked
 *   3. Immutable endpoint allowlist (webhooks, WooCommerce API) → allow
 *   4. User allowlist (IP / path / UA) → allow
 *   5. Signature match → verify identity → policy matrix action
 *   6. Unknown-but-automated UA → alert + default action
 *   7. Otherwise → human (subject to anonymous rate limiting)
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Classifier
 */
class ATG_Classifier {

	/**
	 * The current request's decision (cached for other modules).
	 *
	 * @var array|null
	 */
	public static $current = null;

	/**
	 * Entry point on `init` (priority 1).
	 */
	public function handle_request() {
		// Never classify wp-admin screens (login page GETs handled by form guard).
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		// Never classify REST API requests — WordPress handles REST authentication
		// via its own permission_callback pipeline (rest_authentication_errors /
		// rest_api_init), which fires after init.  Running the classifier here at
		// init priority 1 means REST_REQUEST is true but auth is not yet confirmed,
		// so we would produce noisy stats and could trigger DB errors on a fresh
		// install before the REST response even begins.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Never classify WP-Cron jobs.
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}

		$start    = microtime( true );
		$plugin   = ATG_Plugin::instance();
		$decision = $this->classify();
		$decision['exec_ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );

		self::$current = $decision;

		// Feed decision to other modules (analytics conditional loading, etc.).
		do_action( 'atg_request_classified', $decision );

		// Enforce (or just log, in shadow mode).
		$plugin->enforcer->apply( $decision );
	}

	/**
	 * Build the decision array for the current request.
	 *
	 * @return array
	 */
	public function classify( $mock_ua = null, $mock_ip = null, $mock_path = null, $is_mock = false ) {
		$plugin = ATG_Plugin::instance();
		$ip     = null !== $mock_ip ? $mock_ip : ATG_Plugin::client_ip();
		$ua     = null !== $mock_ua ? $mock_ua : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '' );
		$path   = null !== $mock_path ? $mock_path : ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$session = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : '';

		// Resolve country (GAP-021).
		$country = '';
		$headers = array(
			'HTTP_CF_IPCOUNTRY',
			'HTTP_X_COUNTRY_CODE',
			'HTTP_GEOIP_COUNTRY_CODE',
			'HTTP_X_GEO_COUNTRY',
		);
		foreach ( $headers as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
				break;
			}
		}

		$base = array(
			'ip'             => $ip,
			'ua'             => $ua,
			'path'           => $path,
			'method'         => $method,
			'session'        => $session,
			'classification' => 'unknown',
			'vendor'         => '',
			'purpose'        => '',
			'bot_name'       => '',
			'verified'       => null,
			'spoofed'        => false,
			'action'         => 'allow',
			'reason'         => '',
			'risk'           => 0,
			'is_auth'        => is_user_logged_in(),
			'enforced'       => false,
			'status_code'    => 200,
			'country'        => substr( $country, 0, 2 ),
		);

		// 1. WP-internal automation: never classified.
		$internal = $plugin->allowlist->is_wp_internal();
		if ( $internal ) {
			return array_merge( $base, array(
				'classification' => 'internal',
				'action'         => 'allow',
				'reason'         => 'WordPress internal: ' . $internal,
			) );
		}

		// 2. Authenticated users are always human (P0).
		if ( is_user_logged_in() && $plugin->get( 'auth_bypass', true ) ) {
			return array_merge( $base, array(
				'classification' => 'authenticated',
				'action'         => 'allow',
				'reason'         => 'Authenticated user bypass',
			) );
		}

		// 3. Immutable endpoint allowlist (payment webhooks, Woo API…).
		$path_hit = $plugin->allowlist->path_match( $path );
		if ( $path_hit ) {
			return array_merge( $base, array(
				'classification' => 'allowlisted',
				'action'         => 'allow',
				'reason'         => 'Protected endpoint: ' . $path_hit,
			) );
		}

		// 4. User-defined allowlist.
		if ( $plugin->allowlist->ip_allowed( $ip ) ) {
			return array_merge( $base, array(
				'classification' => 'allowlisted',
				'action'         => 'allow',
				'reason'         => 'IP allowlist',
			) );
		}
		$ua_hit = $plugin->allowlist->ua_allowed( $ua );
		if ( $ua_hit ) {
			return array_merge( $base, array(
				'classification' => 'allowlisted',
				'action'         => 'allow',
				'reason'         => 'UA allowlist: ' . $ua_hit,
			) );
		}

		// A/B Test / Experimentation bypass (GAP-023)
		if ( preg_match( '/(Google-Optimize|Optimizely|VWO|Google-Adwords-Instant)/i', $ua ) ) {
			return array_merge( $base, array(
				'classification' => 'allowlisted',
				'action'         => 'allow',
				'reason'         => 'A/B testing / Experimentation tool bypass',
			) );
		}

		// 5. Signature match → verification → policy.
		$sig = $plugin->bot_db->match( $ua );
		if ( $sig ) {
			return $this->classify_signature_match( $base, $sig );
		}

		// 6. Unknown automated-looking UA → alert + default action.
		if ( $plugin->bot_db->looks_automated( $ua ) ) {
			$default = $plugin->get( 'default_unknown_action', 'throttle_log' );
			$action  = 'throttle_log' === $default ? 'throttle' : $default;
			if ( ! $is_mock ) {
				$plugin->alerts->create(
					'new_bot',
					/* translators: %s user agent */
					sprintf( __( 'New unrecognized bot user agent: %s', 'ai-traffic-guardian' ), substr( $ua, 0, 120 ) ),
					array(
						'ua'   => $ua,
						'ip'   => $ip,
						'path' => $path,
					)
				);
			}
			$base['classification'] = 'unknown_bot';
			$base['action']         = in_array( $action, array( 'allow', 'throttle', 'block' ), true ) ? $action : 'throttle';
			$base['reason']         = 'Unrecognized automated UA';
			$base['risk']           = 55;
			$action_filtered = apply_filters( 'atg_path_policy_override', $base['action'], $path, 'Unknown', 'unknown' );
			if ( $action_filtered !== $base['action'] ) {
				$base['action'] = $action_filtered;
				$base['reason'] = 'Filtered path policy override: ' . $action_filtered;
			} else {
				$override = $plugin->allowlist->get_path_override( $path );
				if ( $override ) {
					$base['action'] = $override;
					$base['reason'] = 'Path override: ' . $override;
				}
			}
			return $base;
		}

		// 7. Human (anonymous). Still subject to progressive rate limiting.
		$base['classification'] = 'human';
		$base['reason']         = 'No bot signals';
		return $base;
	}

	/**
	 * Apply verification + policy to a signature match.
	 *
	 * @param array $base Base decision.
	 * @param array $sig  Signature entry.
	 * @return array
	 */
	private function classify_signature_match( $base, $sig ) {
		$plugin = ATG_Plugin::instance();

		$base['classification'] = 'agent_proxy' === $sig['purpose'] ? 'agent_proxy' : 'bot';
		$base['vendor']         = $sig['vendor'];
		$base['purpose']        = $sig['purpose'];
		$base['bot_name']       = $sig['name'];
		$base['risk']           = 'scraper' === $sig['purpose'] ? 70 : 30;

		// Identity verification.
		$verify_method = isset( $sig['verify'] ) ? $sig['verify'] : 'none';
		if ( 'none' !== $verify_method ) {
			$result = $plugin->verifier->verify( $base['ip'], $sig );
			if ( 'verified' === $result ) {
				$base['verified'] = true;
			} elseif ( 'spoofed' === $result ) {
				// Claiming a verifiable identity and failing = hostile signal.
				$base['verified']       = false;
				$base['spoofed']        = true;
				$base['classification'] = 'bot';
				$base['purpose']        = 'scraper';
				$base['action']         = 'block';
				$base['reason']         = 'UA spoofing: failed ' . $verify_method . ' verification for ' . $sig['name'];
				$base['risk']           = 90;
				$plugin->alerts->create(
					'spoofed_bot',
					sprintf( /* translators: %s bot name */ __( 'Spoofed %s user agent blocked', 'ai-traffic-guardian' ), $sig['name'] ),
					array(
						'ua' => $base['ua'],
						'ip' => $base['ip'],
					)
				);
				return $base;
			} else {
				// Unverifiable: throttle + log, never trust (gap-analysis P1).
				$base['verified'] = false;
				$base['reason']   = 'Identity unverifiable — throttled and logged';
				$base['action']   = 'throttle';
				$base['risk']     = 50;
				$action_filtered = apply_filters( 'atg_path_policy_override', $base['action'], $base['path'], $sig['vendor'], $sig['purpose'] );
				if ( $action_filtered !== $base['action'] ) {
					$base['action'] = $action_filtered;
					$base['reason'] = 'Filtered path policy override: ' . $action_filtered;
				} else {
					$override = $plugin->allowlist->get_path_override( $base['path'] );
					if ( $override ) {
						$base['action'] = $override;
						$base['reason'] = 'Path override: ' . $override;
					}
				}
				return $base;
			}
		}

		$base['action'] = $plugin->policy->action_for( $sig['vendor'], $sig['purpose'] );
		$base['reason'] = 'Policy: ' . $sig['vendor'] . ' / ' . $sig['purpose'];
		$action_filtered = apply_filters( 'atg_path_policy_override', $base['action'], $base['path'], $sig['vendor'], $sig['purpose'] );
		if ( $action_filtered !== $base['action'] ) {
			$base['action'] = $action_filtered;
			$base['reason'] = 'Filtered path policy override: ' . $action_filtered;
		} else {
			$override = $plugin->allowlist->get_path_override( $base['path'] );
			if ( $override ) {
				$base['action'] = $override;
				$base['reason'] = 'Path override: ' . $override;
			}
		}
		return $base;
	}

	/**
	 * Is the current request classified as non-human?
	 *
	 * @return bool
	 */
	public static function is_bot_request() {
		$d = self::$current;
		if ( ! $d ) {
			return false;
		}
		return in_array( $d['classification'], array( 'bot', 'unknown_bot' ), true ) || ! empty( $d['spoofed'] );
	}
}
