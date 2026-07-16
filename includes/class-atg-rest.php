<?php
/**
 * REST API: dashboard data, policy matrix, allowlist, alerts, settings,
 * mode switching (incl. panic button), CSV export, and the public
 * human-confirmation beacon.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_REST
 */
class ATG_REST {

	const NS = 'atg/v1';

	/**
	 * Register routes on rest_api_init.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Permission check for admin endpoints.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Route definitions.
	 */
	public static function routes() {
		$admin = array( 'permission_callback' => array( __CLASS__, 'can_manage' ) );

		register_rest_route( self::NS, '/summary', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'summary' ),
		) ) );

		register_rest_route( self::NS, '/log', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'log' ),
		) ) );

		register_rest_route( self::NS, '/export', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'export' ),
		) ) );

		register_rest_route( self::NS, '/policy', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_policy' ),
		) ) );
		register_rest_route( self::NS, '/policy', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'set_policy' ),
		) ) );
		register_rest_route( self::NS, '/policy/preset', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'apply_preset' ),
		) ) );

		register_rest_route( self::NS, '/allowlist', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_allowlist' ),
		) ) );
		register_rest_route( self::NS, '/allowlist', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'set_allowlist' ),
		) ) );

		register_rest_route( self::NS, '/alerts', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_alerts' ),
		) ) );
		register_rest_route( self::NS, '/alerts/(?P<id>\d+)/dismiss', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'dismiss_alert' ),
		) ) );

		register_rest_route( self::NS, '/mode', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'set_mode' ),
		) ) );

		register_rest_route( self::NS, '/settings', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_settings' ),
		) ) );
		register_rest_route( self::NS, '/settings', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'set_settings' ),
		) ) );

		register_rest_route( self::NS, '/robots-preview', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'robots_preview' ),
		) ) );

		// Public beacon (rate-limited, no nonce — it only marks a session as
		// human-confirmed; it cannot change any setting).
		register_rest_route( self::NS, '/beacon', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'beacon' ),
		) );
	}

	/**
	 * Dashboard summary: KPIs, time series, breakdowns.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function summary( WP_REST_Request $req ) {
		global $wpdb;
		$stats = ATG_DB::table( 'stats' );
		$days  = min( 90, max( 1, (int) $req->get_param( 'days' ) ?: 7 ) );
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$days} days" ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$series = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, classification, SUM(hits) AS hits FROM {$stats}
				 WHERE classification != '' AND day >= %s GROUP BY day, classification ORDER BY day ASC",
				$from
			),
			ARRAY_A
		);
		$actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action, SUM(hits) AS hits FROM {$stats} WHERE action != '' AND day >= %s GROUP BY action",
				$from
			),
			ARRAY_A
		);
		$purposes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT purpose, SUM(hits) AS hits FROM {$stats} WHERE purpose != '' AND day >= %s GROUP BY purpose ORDER BY hits DESC",
				$from
			),
			ARRAY_A
		);
		$vendors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT vendor, SUM(hits) AS hits FROM {$stats} WHERE vendor != '' AND day >= %s GROUP BY vendor ORDER BY hits DESC LIMIT 10",
				$from
			),
			ARRAY_A
		);
		// phpcs:enable

		// KPIs.
		$total    = 0;
		$by_class = array();
		foreach ( $series as $row ) {
			$total += (int) $row['hits'];
			$by_class[ $row['classification'] ] = ( isset( $by_class[ $row['classification'] ] ) ? $by_class[ $row['classification'] ] : 0 ) + (int) $row['hits'];
		}
		$action_map = array( 'allow' => 0, 'throttle' => 0, 'block' => 0 );
		foreach ( $actions as $row ) {
			$action_map[ $row['action'] ] = (int) $row['hits'];
		}

		$bot_classes = array( 'bot', 'unknown_bot', 'form_abuse' );
		$bot_total   = 0;
		foreach ( $bot_classes as $c ) {
			$bot_total += isset( $by_class[ $c ] ) ? $by_class[ $c ] : 0;
		}
		$human_total = 0;
		foreach ( array( 'human', 'authenticated', 'internal', 'allowlisted', 'agent_proxy' ) as $c ) {
			$human_total += isset( $by_class[ $c ] ) ? $by_class[ $c ] : 0;
		}

		$plugin = ATG_Plugin::instance();

		return new WP_REST_Response(
			array(
				'range'      => $days,
				'kpis'       => array(
					'total'     => $total,
					'bot_share' => $total > 0 ? round( ( $bot_total / $total ) * 100, 1 ) : 0,
					'blocked'   => $action_map['block'],
					'throttled' => $action_map['throttle'],
					'allowed'   => $action_map['allow'],
					'human_eq'  => $human_total,
					'alerts'    => $plugin->alerts->open_count(),
				),
				'series'     => $series,
				'by_class'   => $by_class,
				'actions'    => $action_map,
				'purposes'   => $purposes,
				'vendors'    => $vendors,
				'mode'       => $plugin->enforcement_mode(),
				'shadow'     => array(
					'started'   => (int) $plugin->get( 'shadow_started', 0 ),
					'days'      => (int) $plugin->get( 'shadow_days', 7 ),
					'remaining' => max( 0, ( (int) $plugin->get( 'shadow_started', 0 ) + ( (int) $plugin->get( 'shadow_days', 7 ) * DAY_IN_SECONDS ) ) - time() ),
				),
				'woo'        => class_exists( 'WooCommerce' ),
			),
			200
		);
	}

	/**
	 * Paginated traffic log.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function log( WP_REST_Request $req ) {
		$result = ATG_Plugin::instance()->logger->query(
			array(
				'classification' => $req->get_param( 'classification' ),
				'vendor'         => $req->get_param( 'vendor' ),
				'action'         => $req->get_param( 'action' ),
				'search'         => $req->get_param( 'search' ),
				'page'           => $req->get_param( 'page' ),
				'per_page'       => $req->get_param( 'per_page' ),
			)
		);
		foreach ( $result['rows'] as &$row ) {
			$row['ip_display'] = ATG_Plugin::instance()->get( 'hash_ips', true )
				? substr( $row['ip_hash'], 0, 12 ) . '…'
				: $row['ip_hash'];
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * CSV export.
	 *
	 * @param WP_REST_Request $req Request.
	 */
	public static function export( WP_REST_Request $req ) {
		// Stream CSV directly; WP REST response not used.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=atg-traffic-log.csv' );
		ATG_Plugin::instance()->logger->export_csv(
			array(
				'classification' => $req->get_param( 'classification' ),
				'vendor'         => $req->get_param( 'vendor' ),
				'action'         => $req->get_param( 'action' ),
				'search'         => $req->get_param( 'search' ),
			)
		);
		exit;
	}

	/**
	 * Full policy matrix + signature metadata.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_policy() {
		$plugin = ATG_Plugin::instance();
		return new WP_REST_Response(
			array(
				'matrix'     => $plugin->policy->matrix(),
				'signatures' => ATG_Bot_Database::signatures(),
				'purposes'   => ATG_Bot_Database::purposes(),
				'presets'    => array_map(
					function ( $p ) {
						return array(
							'label' => $p['label'],
							'description' => $p['description'],
						);
					},
					ATG_Policy_Engine::presets()
				),
			),
			200
		);
	}

	/**
	 * Set one policy cell.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_policy( WP_REST_Request $req ) {
		$vendor  = sanitize_text_field( (string) $req->get_param( 'vendor' ) );
		$purpose = sanitize_key( (string) $req->get_param( 'purpose' ) );
		$action  = sanitize_key( (string) $req->get_param( 'action' ) );
		if ( ! ATG_Plugin::instance()->policy->set( $vendor, $purpose, $action ) ) {
			return new WP_Error( 'atg_bad_policy', __( 'Invalid policy update.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Apply a policy preset.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply_preset( WP_REST_Request $req ) {
		$name = sanitize_key( (string) $req->get_param( 'preset' ) );
		if ( ! ATG_Plugin::instance()->policy->apply_preset( $name ) ) {
			return new WP_Error( 'atg_bad_preset', __( 'Unknown preset.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Get allowlist.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_allowlist() {
		return new WP_REST_Response(
			array(
				'allowlist' => ATG_Plugin::instance()->allowlist->get(),
				'protected' => ATG_Allowlist::protected_paths(),
			),
			200
		);
	}

	/**
	 * Save allowlist.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function set_allowlist( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		$data = ATG_Plugin::instance()->allowlist->update( is_array( $body ) ? $body : array() );
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'allowlist' => $data,
			),
			200
		);
	}

	/**
	 * List alerts.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function get_alerts( WP_REST_Request $req ) {
		$status = sanitize_key( (string) ( $req->get_param( 'status' ) ?: 'all' ) );
		$rows   = ATG_Plugin::instance()->alerts->list( $status );
		foreach ( $rows as &$row ) {
			$row['payload'] = json_decode( (string) $row['payload'], true );
		}
		return new WP_REST_Response( array( 'alerts' => $rows ), 200 );
	}

	/**
	 * Dismiss an alert.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function dismiss_alert( WP_REST_Request $req ) {
		ATG_Plugin::instance()->alerts->dismiss( (int) $req->get_param( 'id' ) );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Switch enforcement mode (shadow|active|off). 'off' is the panic button.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_mode( WP_REST_Request $req ) {
		$mode = sanitize_key( (string) $req->get_param( 'mode' ) );
		if ( ! in_array( $mode, array( 'shadow', 'active', 'off' ), true ) ) {
			return new WP_Error( 'atg_bad_mode', __( 'Invalid mode.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}
		$plugin = ATG_Plugin::instance();
		$update = array( 'enforcement' => $mode );
		if ( 'shadow' === $mode ) {
			$update['shadow_started'] = time();
		}
		$plugin->update_settings( $update );
		return new WP_REST_Response(
			array(
				'ok'   => true,
				'mode' => $mode,
			),
			200
		);
	}

	/**
	 * Get settings (+ environment report).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings() {
		return new WP_REST_Response(
			array(
				'settings' => ATG_Plugin::instance()->settings,
				'env'      => ATG_Compat::environment(),
			),
			200
		);
	}

	/**
	 * Save settings (whitelist of keys, type-coerced).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function set_settings( WP_REST_Request $req ) {
		$plugin   = ATG_Plugin::instance();
		$body     = $req->get_json_params();
		$defaults = ATG_Plugin::default_settings();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $body ) ) {
				continue;
			}
			$value = $body[ $key ];
			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		// Enum guards.
		if ( isset( $clean['enforcement'] ) && ! in_array( $clean['enforcement'], array( 'shadow', 'active', 'off' ), true ) ) {
			unset( $clean['enforcement'] );
		}
		if ( isset( $clean['ga4_mode'] ) && ! in_array( $clean['ga4_mode'], array( 'off', 'compat', 'conditional' ), true ) ) {
			unset( $clean['ga4_mode'] );
		}
		if ( isset( $clean['robots_mode'] ) && ! in_array( $clean['robots_mode'], array( 'auto', 'manual' ), true ) ) {
			unset( $clean['robots_mode'] );
		}
		if ( isset( $clean['default_unknown_action'] ) && ! in_array( $clean['default_unknown_action'], array( 'allow', 'throttle', 'block', 'throttle_log' ), true ) ) {
			unset( $clean['default_unknown_action'] );
		}
		if ( isset( $clean['comment_fail_action'] ) && ! in_array( $clean['comment_fail_action'], array( 'moderate', 'block' ), true ) ) {
			unset( $clean['comment_fail_action'] );
		}
		if ( isset( $clean['retention_days'] ) ) {
			$clean['retention_days'] = min( 365, max( 7, (int) $clean['retention_days'] ) );
		}

		$plugin->update_settings( $clean );

		// llms_enabled toggle may need a rewrite flush.  Only safe to call
		// add_rewrite_rule() after init has fired; the REST API runs on init
		// so did_action('init') is already true here.  Schedule the flush on
		// shutdown to avoid interfering with the current request.
		if ( isset( $clean['llms_enabled'] ) ) {
			if ( did_action( 'init' ) ) {
				ATG_Llms::register_rewrite();
				add_action( 'shutdown', 'flush_rewrite_rules' );
			}
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'settings' => $plugin->settings,
			),
			200
		);
	}

	/**
	 * robots.txt preview.
	 *
	 * @return WP_REST_Response
	 */
	public static function robots_preview() {
		return new WP_REST_Response(
			array(
				'rules'       => ATG_Plugin::instance()->robots->build_rules(),
				'seo_plugins' => ATG_Robots::detected_seo_plugins(),
			),
			200
		);
	}

	/**
	 * Public human-confirmation beacon. Marks the session as confirmed-human
	 * (30 min). Rate-limited per IP; accepts only benign interaction signals.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function beacon( WP_REST_Request $req ) {
		$ip  = ATG_Plugin::client_ip();
		$key = 'atg_beacon_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n > 30 ) {
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		$body    = $req->get_json_params();
		$session = isset( $body['sid'] ) ? sanitize_text_field( (string) $body['sid'] ) : '';
		$cookie  = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : '';
		$event   = isset( $body['event'] ) ? sanitize_key( (string) $body['event'] ) : '';

		$human_events = array( 'pointer', 'key', 'scroll', 'touch' );
		if ( $session && $session === $cookie && in_array( $event, $human_events, true ) ) {
			$webdriver = ! empty( $body['webdriver'] );
			if ( ! $webdriver ) {
				set_transient( 'atg_human_' . md5( $session ), 1, 30 * MINUTE_IN_SECONDS );
				return new WP_REST_Response(
					array(
						'ok'       => true,
						'confirmed' => true,
					),
					200
				);
			}
		}
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'confirmed' => false,
			),
			200
		);
	}
}
