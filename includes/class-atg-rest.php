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
	 * @return bool|WP_Error
	 */
	public static function can_manage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Permission check for viewer/editor endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_view_reports() {
		if ( ! current_user_can( 'atg_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Route definitions.
	 */
	public static function routes() {
		$admin  = array( 'permission_callback' => array( __CLASS__, 'can_manage' ) );
		$viewer = array( 'permission_callback' => array( __CLASS__, 'can_view_reports' ) );

		register_rest_route( self::NS, '/summary', array_merge( $viewer, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'summary' ),
		) ) );

		register_rest_route( self::NS, '/log', array_merge( $viewer, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'log' ),
		) ) );
		register_rest_route( self::NS, '/log', array_merge( $admin, array(
			'methods'  => 'DELETE',
			'callback' => array( __CLASS__, 'clear_traffic_data' ),
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

		register_rest_route( self::NS, '/alerts', array_merge( $viewer, array(
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

		register_rest_route( self::NS, '/custom-signatures', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_custom_signatures' ),
		) ) );
		register_rest_route( self::NS, '/custom-signatures', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'add_custom_signature' ),
		) ) );
		register_rest_route( self::NS, '/custom-signatures/(?P<index>\d+)', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'update_custom_signature' ),
		) ) );
		register_rest_route( self::NS, '/custom-signatures/(?P<index>\d+)', array_merge( $admin, array(
			'methods'  => 'DELETE',
			'callback' => array( __CLASS__, 'delete_custom_signature' ),
		) ) );

		register_rest_route( self::NS, '/robots-preview', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'robots_preview' ),
		) ) );

		register_rest_route( self::NS, '/policy/export', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'export_policy' ),
		) ) );

		register_rest_route( self::NS, '/policy/import', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'import_policy' ),
		) ) );

		register_rest_route( self::NS, '/debug-replay', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'debug_replay' ),
		) ) );

		register_rest_route( self::NS, '/audit', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'run_audit' ),
		) ) );

		register_rest_route( self::NS, '/beacon', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'beacon' ),
		) );

		register_rest_route( self::NS, '/debug-log', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'receive_js_debug_log' ),
		) );

		register_rest_route( self::NS, '/debug', array_merge( $admin, array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'get_debug_log' ),
		) ) );
		register_rest_route( self::NS, '/debug', array_merge( $admin, array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'toggle_debug_log' ),
		) ) );
		register_rest_route( self::NS, '/debug', array_merge( $admin, array(
			'methods'  => 'DELETE',
			'callback' => array( __CLASS__, 'clear_debug_log' ),
		) ) );

		// Edge integration contract endpoints (Phase 0)
		register_rest_route( self::NS, '/verify', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'verify_edge' ),
		) );

		register_rest_route( self::NS, '/snapshot', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'get_snapshot' ),
		) );

		register_rest_route( self::NS, '/report/download', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'can_manage' ),
			'callback'            => array( __CLASS__, 'download_report' ),
		) );

		register_rest_route( self::NS, '/dashboard/view', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
			'callback'            => array( __CLASS__, 'save_dashboard_view' ),
		) );

		register_rest_route( self::NS, '/challenge/campaign', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
			'callback'            => array( __CLASS__, 'get_active_campaign' ),
		) );

		register_rest_route( self::NS, '/challenge/submit', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'can_view_reports' ),
			'callback'            => array( __CLASS__, 'submit_challenge' ),
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

		$series = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT day, classification, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . '
				 WHERE classification != \'\' AND day >= %s GROUP BY day, classification ORDER BY day ASC',
				$from
			),
			ARRAY_A
		);
		$actions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT action, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . ' WHERE action != \'\' AND day >= %s GROUP BY action',
				$from
			),
			ARRAY_A
		);
		$purposes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT purpose, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . ' WHERE purpose != \'\' AND day >= %s GROUP BY purpose ORDER BY hits DESC',
				$from
			),
			ARRAY_A
		);
		$vendors = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT vendor, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . ' WHERE vendor != \'\' AND day >= %s GROUP BY vendor ORDER BY hits DESC LIMIT 10',
				$from
			),
			ARRAY_A
		);
		$countries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT country, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . ' WHERE country != \'\' AND day >= %s GROUP BY country ORDER BY hits DESC LIMIT 15',
				$from
			),
			ARRAY_A
		);

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
				'countries'  => $countries,
				'mode'       => $plugin->enforcement_mode(),
				'shadow'     => array(
					'started'   => (int) $plugin->get( 'shadow_started', 0 ),
					'days'      => (int) $plugin->get( 'shadow_days', 7 ),
					'remaining' => max( 0, ( (int) $plugin->get( 'shadow_started', 0 ) + ( (int) $plugin->get( 'shadow_days', 7 ) * DAY_IN_SECONDS ) ) - time() ),
				),
				'shadow_snapshot' => array(
					'total'     => (int) $plugin->get( 'shadow_snapshot_total', 0 ),
					'bot_total' => (int) $plugin->get( 'shadow_snapshot_bot_total', 0 ),
					'bot_share' => (float) $plugin->get( 'shadow_snapshot_bot_share', 0 ),
					'time'      => (int) $plugin->get( 'shadow_snapshot_time', 0 ),
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
			if ( function_exists( 'atg_write_debug_log' ) ) {
				atg_write_debug_log( "REST API: set_mode failed. Invalid mode: '{$mode}'" );
			}
			return new WP_Error( 'atg_bad_mode', __( 'Invalid mode.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}
		$plugin = ATG_Plugin::instance();

		if ( function_exists( 'atg_write_debug_log' ) ) {
			atg_write_debug_log( "REST API: set_mode attempting change from " . $plugin->enforcement_mode() . " to {$mode}" );
		}

		if ( 'active' === $mode && 'shadow' === $plugin->enforcement_mode() ) {
			global $wpdb;
			$stats      = ATG_DB::table( 'stats' );
			$started_ts = (int) $plugin->get( 'shadow_started', 0 );
			$from_date  = $started_ts ? gmdate( 'Y-m-d', $started_ts ) : gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT classification, SUM(hits) AS hits FROM ' . ATG_DB::table( 'stats' ) . ' WHERE day >= %s GROUP BY classification',
					$from_date
				),
				ARRAY_A
			);

			$total       = 0;
			$bot_total   = 0;
			$bot_classes = array( 'bot', 'unknown_bot', 'form_abuse' );
			foreach ( $rows as $row ) {
				$total += (int) $row['hits'];
				if ( in_array( $row['classification'], $bot_classes, true ) ) {
					$bot_total += (int) $row['hits'];
				}
			}

			$plugin->update_settings( array(
				'shadow_snapshot_total'     => $total,
				'shadow_snapshot_bot_total' => $bot_total,
				'shadow_snapshot_bot_share' => $total > 0 ? round( ( $bot_total / $total ) * 100, 1 ) : 0,
				'shadow_snapshot_time'      => time(),
			) );
		}

		$update = array( 'enforcement' => $mode );
		if ( 'shadow' === $mode ) {
			$update['shadow_started'] = time();
		}
		$plugin->update_settings( $update );

		if ( function_exists( 'atg_write_debug_log' ) ) {
			atg_write_debug_log( "REST API: set_mode successfully changed enforcement mode to {$mode}" );
		}

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
				add_action( 'shutdown', function() { flush_rewrite_rules( false ); } );
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
	 * Get custom signatures.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_custom_signatures() {
		$custom = ATG_Plugin::instance()->custom_signatures->get_all();
		return new WP_REST_Response( array( 'signatures' => $custom ), 200 );
	}

	/**
	 * Add custom signature.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_custom_signature( WP_REST_Request $req ) {
		$params = $req->get_json_params();
		$result = ATG_Plugin::instance()->custom_signatures->add( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Update custom signature.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_custom_signature( WP_REST_Request $req ) {
		$index  = (int) $req->get_param( 'index' );
		$params = $req->get_json_params();
		$result = ATG_Plugin::instance()->custom_signatures->update( $index, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Delete custom signature.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_custom_signature( WP_REST_Request $req ) {
		$index  = (int) $req->get_param( 'index' );
		$result = ATG_Plugin::instance()->custom_signatures->delete( $index );
		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Could not delete signature.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
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

	/**
	 * Export policy matrix and custom signatures.
	 */
	public static function export_policy() {
		$plugin = ATG_Plugin::instance();
		$config = array(
			'matrix'            => $plugin->policy->matrix(),
			'custom_signatures' => $plugin->custom_signatures->get(),
		);

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="atg-policy-config.json"' );
		echo wp_json_encode( $config, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import policy matrix and custom signatures.
	 */
	public static function import_policy( WP_REST_Request $req ) {
		$config = $req->get_json_params();
		if ( ! is_array( $config ) ) {
			return new WP_Error( 'atg_bad_import', __( 'Invalid import payload.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}

		$plugin = ATG_Plugin::instance();
		if ( isset( $config['matrix'] ) && is_array( $config['matrix'] ) ) {
			$plugin->policy->update_matrix( $config['matrix'] );
		}
		if ( isset( $config['custom_signatures'] ) && is_array( $config['custom_signatures'] ) ) {
			$plugin->custom_signatures->update_all( $config['custom_signatures'] );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Replay request classification in mock mode.
	 */
	public static function debug_replay( WP_REST_Request $req ) {
		$ua   = sanitize_text_field( (string) $req->get_param( 'ua' ) );
		$ip   = sanitize_text_field( (string) $req->get_param( 'ip' ) );
		$path = sanitize_text_field( (string) $req->get_param( 'path' ) );

		$classifier = ATG_Plugin::instance()->classifier;
		$decision = $classifier->classify( $ua, $ip, $path, true );

		return new WP_REST_Response( array(
			'ok'       => true,
			'decision' => $decision,
		), 200 );
	}

	/**
	 * Verify edge request signature.
	 *
	 * @param WP_REST_Request $req  REST request object.
	 * @param string          $path Endpoint path.
	 * @param string          $method HTTP method.
	 * @return true|WP_REST_Response Returns true on success or WP_REST_Response on authentication failure.
	 */
	private static function check_edge_auth( WP_REST_Request $req, $path, $method ) {
		$site_id   = $req->get_header( 'X-ATG-Site-Id' );
		$timestamp = $req->get_header( 'X-ATG-Timestamp' );
		$signature = $req->get_header( 'X-ATG-Signature' );

		if ( ! $site_id || ! $timestamp || ! $signature ) {
			return new WP_REST_Response( array(
				'error' => array(
					'code'    => 'invalid_signature',
					'message' => 'Signature verification failed.',
				),
			), 401 );
		}

		if ( $site_id !== ATG_Edge::get_site_id() ) {
			return new WP_REST_Response( array(
				'error' => array(
					'code'    => 'unknown_site',
					'message' => 'Unknown site ID.',
				),
			), 404 );
		}

		$body = $req->get_body();
		if ( ! ATG_Edge::verify_signature( $path, $method, $timestamp, $signature, $body ) ) {
			return new WP_REST_Response( array(
				'error' => array(
					'code'    => 'invalid_signature',
					'message' => 'Signature verification failed.',
				),
			), 401 );
		}

		return true;
	}

	/**
	 * Verify edge requests callback.
	 *
	 * @param WP_REST_Request $req REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify_edge( WP_REST_Request $req ) {
		$auth = self::check_edge_auth( $req, '/wp-json/atg/v1/verify', 'POST' );
		if ( $auth !== true ) {
			return $auth;
		}

		$params = json_decode( $req->get_body(), true );
		$ip     = isset( $params['ip'] ) ? sanitize_text_field( $params['ip'] ) : '';
		$ua     = isset( $params['user_agent'] ) ? sanitize_text_field( $params['user_agent'] ) : '';
		$path   = isset( $params['path'] ) ? sanitize_text_field( $params['path'] ) : '';

		$decision = ATG_Plugin::instance()->classifier->classify( $ua, $ip, $path, true );

		return new WP_REST_Response( array(
			'decision'    => $decision['action'],
			'reason'      => $decision['reason'],
			'ttl_seconds' => 300,
		), 200 );
	}

	/**
	 * Get policy snapshot callback.
	 *
	 * @param WP_REST_Request $req REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_snapshot( WP_REST_Request $req ) {
		$auth = self::check_edge_auth( $req, '/wp-json/atg/v1/snapshot', 'GET' );
		if ( $auth !== true ) {
			return $auth;
		}

		$snapshot = ATG_Edge::generate_snapshot();
		return new WP_REST_Response( $snapshot, 200 );
	}

	/**
	 * Download Bot Audit Report files (PDF / PNG).
	 *
	 * @param WP_REST_Request $req REST Request.
	 * @return WP_Error|void
	 */
	public static function download_report( WP_REST_Request $req ) {
		$type = sanitize_key( $req->get_param( 'type' ) );
		$upload_dir = wp_upload_dir();

		if ( 'png' === $type ) {
			$file = $upload_dir['basedir'] . '/bot-audit-report.png';
			if ( ! file_exists( $file ) ) {
				ATG_Report_Generator::generate_report_files();
			}
			if ( file_exists( $file ) ) {
				header( 'Content-Type: image/png' );
				header( 'Content-Disposition: attachment; filename="bot-audit-report.png"' );
				readfile( $file );
				exit;
			}
		} else {
			$file = $upload_dir['basedir'] . '/bot-audit-report.pdf';
			if ( ! file_exists( $file ) ) {
				ATG_Report_Generator::generate_report_files();
			}
			if ( file_exists( $file ) ) {
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: attachment; filename="bot-audit-report.pdf"' );
				readfile( $file );
				exit;
			}
		}

		return new WP_Error( 'file_not_found', __( 'Report file not found.', 'ai-traffic-guardian' ), array( 'status' => 404 ) );
	}

	/**
	 * Save current user's dashboard view preference.
	 *
	 * @param WP_REST_Request $req REST Request.
	 * @return WP_REST_Response
	 */
	public static function save_dashboard_view( WP_REST_Request $req ) {
		$view    = sanitize_key( $req->get_param( 'view' ) );
		$user_id = get_current_user_id();

		if ( $user_id && in_array( $view, array( 'simple', 'advanced' ), true ) ) {
			update_user_meta( $user_id, 'atg_dashboard_view', $view );
			return new WP_REST_Response( array( 'ok' => true, 'view' => $view ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}

	/**
	 * Get the currently active challenge campaign.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_active_campaign() {
		$cached = get_transient( 'atg_active_campaign' );
		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$url = 'https://campaign.aitrafficguardian.com/campaigns/active';
		$res = wp_safe_remote_get( $url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $res ) ) {
			// Campaign server unreachable — return empty state so UI shows "no active campaign".
			$unavailable = array(
				'campaign_id' => null,
				'unavailable' => true,
				'message'     => __( 'Campaign service is currently unavailable. Please try again later.', 'ai-traffic-guardian' ),
			);
			set_transient( 'atg_active_campaign', $unavailable, 15 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( $unavailable, 200 );
		}

		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		set_transient( 'atg_active_campaign', $data, DAY_IN_SECONDS );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Submit shadow mode results to the challenge campaign.
	 *
	 * @param WP_REST_Request $req REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_challenge( WP_REST_Request $req ) {
		$campaign_id = sanitize_key( $req->get_param( 'campaign_id' ) );
		$handle      = sanitize_text_field( $req->get_param( 'display_name' ) );
		$show_domain = (bool) $req->get_param( 'show_domain' );

		if ( ! $campaign_id ) {
			return new WP_Error( 'missing_campaign', __( 'Missing campaign ID.', 'ai-traffic-guardian' ), array( 'status' => 400 ) );
		}

		// 1. Participant ID
		$pid = get_option( 'atg_challenge_participant_id' );
		if ( ! $pid ) {
			$pid = 'atg_p_' . wp_generate_password( 8, false );
			update_option( 'atg_challenge_participant_id', $pid );
		}

		// 2. Aggregate shadow stats
		$stats = ATG_Report_Generator::get_stats();

		// 3. Domain hint masking
		$domain = $stats['site_domain'];
		if ( $show_domain ) {
			$domain_hint = $domain;
		} else {
			$parts = explode( '.', $domain );
			if ( count( $parts ) > 1 ) {
				$first = $parts[0];
				$len   = strlen( $first );
				if ( $len > 2 ) {
					$domain_hint = $first[0] . str_repeat( '*', $len - 2 ) . $first[ $len - 1 ] . '.' . implode( '.', array_slice( $parts, 1 ) );
				} else {
					$domain_hint = $first[0] . '*.' . implode( '.', array_slice( $parts, 1 ) );
				}
			} else {
				$domain_hint = 'anonymous.com';
			}
		}

		// 4. Payload formulation
		$payload = array(
			'campaign_id'        => $campaign_id,
			'participant_id'     => $pid,
			'period_start'       => $stats['period_start'] . 'T00:00:00Z',
			'period_end'         => $stats['period_end'] . 'T00:00:00Z',
			'total_requests'     => (int) $stats['total_hits'],
			'ai_bot_requests'    => (int) $stats['bot_hits'],
			'blocked_percentage' => (float) $stats['bot_pct'],
			'display_name'       => empty( $handle ) ? 'anonymous' : $handle,
			'domain_hint'        => $domain_hint,
		);

		// 5. Submit to central service
		$url = 'https://campaign.aitrafficguardian.com/submit';
		$res = wp_safe_remote_post( $url, array(
			'timeout'   => 10,
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'body'      => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $res ) ) {
			return new WP_Error(
				'submission_failed',
				__( 'Could not reach the campaign server. Please check your internet connection and try again.', 'ai-traffic-guardian' ),
				array( 'status' => 503 )
			);
		}

		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		$code = wp_remote_retrieve_response_code( $res );

		if ( $code >= 400 ) {
			$msg = isset( $data['error'] ) ? $data['error'] : __( 'Submission rejected by central campaign server.', 'ai-traffic-guardian' );
			return new WP_Error( 'submission_rejected', $msg, array( 'status' => $code ) );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Run a full bot protection audit and return the structured report.
	 *
	 * @return WP_REST_Response
	 */
	public static function run_audit() {
		$audit  = new ATG_Bot_Audit();
		$report = $audit->run();
		return new WP_REST_Response( $report, 200 );
	}
	/**
	 * Receive a Javascript error or event log from the browser dashboard.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function receive_js_debug_log( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		$msg  = isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';
		if ( $msg && function_exists( 'atg_write_debug_log' ) ) {
			atg_write_debug_log( "[BROWSER] " . $msg );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Get debug log entries.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function get_debug_log( WP_REST_Request $req ) {
		$context = sanitize_key( (string) $req->get_param( 'context' ) );
		$limit   = min( 400, max( 1, (int) $req->get_param( 'limit' ) ?: 100 ) );

		return new WP_REST_Response(
			array(
				'enabled' => ATG_Debug::enabled(),
				'expiry'  => ATG_Debug::expiry(),
				'entries' => ATG_Debug::get( $limit, $context ),
			),
			200
		);
	}

	/**
	 * Toggle debug logging on/off.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function toggle_debug_log( WP_REST_Request $req ) {
		$body    = $req->get_json_params();
		$enabled = isset( $body['enabled'] ) ? (bool) $body['enabled'] : false;

		if ( $enabled ) {
			ATG_Debug::enable();
		} else {
			ATG_Debug::disable();
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'enabled' => ATG_Debug::enabled(),
				'expiry'  => ATG_Debug::expiry(),
			),
			200
		);
	}

	/**
	 * Clear the debug log option.
	 *
	 * @return WP_REST_Response
	 */
	public static function clear_debug_log() {
		ATG_Debug::clear();
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'enabled' => ATG_Debug::enabled(),
				'expiry'  => ATG_Debug::expiry(),
			),
			200
		);
	}

	/**
	 * Clear all traffic log and statistics database tables.
	 *
	 * @return WP_REST_Response
	 */
	public static function clear_traffic_data() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . ATG_DB::table( 'log' ) );
		$wpdb->query( 'DELETE FROM ' . ATG_DB::table( 'stats' ) );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
