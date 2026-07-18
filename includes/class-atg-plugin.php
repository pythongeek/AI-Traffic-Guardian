<?php
/**
 * Core orchestrator: wires every module together.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Plugin
 */
final class ATG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ATG_Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin settings (cached).
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Module handles.
	 */
	public $db;
	public $bot_db;
	public $verifier;
	public $allowlist;
	public $policy;
	public $logger;
	public $alerts;
	public $classifier;
	public $rate_limiter;
	public $enforcer;
	public $analytics;
	public $form_guard;
	public $robots;
	public $llms;
	public $compat;
	public $custom_signatures;
	public $anomaly_detector;
	public $cloudflare;

	/**
	 * Get singleton.
	 *
	 * @return ATG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default settings. Filterable via `atg_default_settings`.
	 *
	 * @return array
	 */
	public static function default_settings() {
		$defaults = array(
			// Enforcement: 'shadow' (log only), 'active' (enforce), 'off' (disabled).
			'enforcement'           => 'shadow',
			'shadow_started'        => 0,
			'shadow_days'           => 7,
			'staging_mode'          => false,
			// Cloudflare Integration.
			'cloudflare_enabled'    => false,
			'cloudflare_api_token'  => '',
			'cloudflare_account_id' => '',
			'cloudflare_ip_list_id' => '',
			// llms.txt enhancements.
			'llms_license'          => 'CC-BY-4.0',
			'llms_optional_urls'    => '',
			// Authenticated users are always treated as human (gap-analysis P0).
			'auth_bypass'           => true,
			// What to do with traffic we cannot classify.
			'default_unknown_action' => 'throttle_log', // allow|throttle|block|throttle_log.
			// Rate limiting.
			'rate_enabled'          => true,
			'rate_human_rpm'        => 120,  // anonymous humans: requests/minute.
			'rate_bot_rpm'          => 20,   // classified bots: requests/minute.
			'rate_burst'            => 30,   // burst allowance (token bucket capacity).
			// Analytics integrity.
			'ga4_mode'              => 'compat', // off|compat|conditional.
			'ga4_measurement_id'    => '',
			'ga4_api_secret'        => '',
			'ga4_server_purchase'   => false,
			// Form & checkout protection (accessibility-first defaults).
			'honeypot_enabled'      => true,
			'timing_checks'         => false, // OFF by default (WCAG risk).
			'min_seconds'           => 3,
			'turnstile_enabled'     => false,
			'turnstile_site_key'    => '',
			'turnstile_secret'      => '',
			'protect_comments'      => true,
			'protect_registration'  => true,
			'protect_login'         => false,
			'protect_woocommerce'   => true,
			'protect_cf7'           => true,
			'protect_gravityforms'  => true,
			'protect_wpforms'       => true,
			'woo_max_attempts'      => 5,    // checkout attempts per window.
			'woo_window_min'        => 10,
			'comment_fail_action'   => 'moderate', // moderate|block.
			// robots.txt.
			'robots_mode'           => 'auto', // auto|manual.
			// llms.txt.
			'llms_enabled'          => false,
			'llms_intro'            => '',
			'llms_posts'            => 20,
			// Privacy & data.
			'hash_ips'              => true,
			'retention_days'        => 30,
			'log_humans'            => false, // aggregate humans only, per-row for bots.
			// Alerts.
			'alert_new_bot'         => true,
			'alert_email'           => false,
			'webhook_url'           => '',
			// Editor access.
			'allow_editor_reports'  => true,
			// Shadow snapshots.
			'shadow_snapshot_total'     => 0,
			'shadow_snapshot_bot_total' => 0,
			'shadow_snapshot_bot_share' => 0,
			'shadow_snapshot_time'      => 0,
			// Headers.
			'send_status_header'    => true,
			// Housekeeping.
			'delete_data_on_uninstall' => false,
		);
		return apply_filters( 'atg_default_settings', $defaults );
	}

	/**
	 * Boot all modules.
	 */
	public function boot() {
		$network_settings = is_multisite() ? get_site_option( 'atg_network_settings', array() ) : array();
		$local_settings   = get_option( 'atg_settings', array() );
		$this->settings   = wp_parse_args( $local_settings, wp_parse_args( $network_settings, self::default_settings() ) );

		$this->bot_db       = new ATG_Bot_Database();
		$this->verifier     = new ATG_Verifier();
		$this->allowlist    = new ATG_Allowlist();
		$this->policy       = new ATG_Policy_Engine();
		$this->logger       = new ATG_Logger();
		$this->alerts       = new ATG_Alerts();
		$this->classifier   = new ATG_Classifier();
		$this->rate_limiter = new ATG_Rate_Limiter();
		$this->enforcer     = new ATG_Enforcer();
		$this->analytics    = new ATG_Analytics();
		$this->form_guard   = new ATG_Form_Guard();
		$this->robots       = new ATG_Robots();
		$this->llms         = new ATG_Llms();
		$this->compat       = new ATG_Compat();
		$this->custom_signatures = new ATG_Custom_Signatures();
		$this->anomaly_detector  = new ATG_Anomaly_Detector();
		$this->cloudflare        = new ATG_Cloudflare();

		// Schema self-healing: run on every boot so REST requests and front-end
		// pages always have tables available, even if activation failed earlier.
		// The version compare reads from WP's option cache — effectively free
		// after the first request of a process.
		ATG_DB::maybe_upgrade();

		// Front-end request classification & enforcement. Runs early, after
		// pluggable functions (auth) are available.
		add_action( 'init', array( $this->classifier, 'handle_request' ), 1 );

		$this->analytics->hooks();
		$this->form_guard->hooks();
		$this->robots->hooks();
		$this->llms->hooks();
		$this->compat->hooks();
		$this->custom_signatures->hooks();
		$this->anomaly_detector->hooks();
		$this->cloudflare->hooks();
		$this->alerts->hooks();

		// GDPR hooks.
		add_filter( 'wp_privacy_policy_content', array( $this, 'privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers',   array( $this, 'register_eraser' ) );

		// Cron.
		add_action( 'atg_cron_daily', array( $this, 'cron_daily' ) );
		add_action( 'atg_cron_weekly', array( $this->verifier, 'refresh_ip_ranges' ) );

		// REST API + admin.
		ATG_REST::register();
		if ( is_admin() ) {
			$admin = new ATG_Admin();
			$admin->hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once ATG_PLUGIN_DIR . 'includes/class-atg-cli-command.php';
		}

		// Session cookie for rate limiting (anonymous traffic only).
		add_action( 'init', array( $this, 'ensure_session_cookie' ), 2 );

		// Update Editor / Admin capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'atg_view_reports' ) ) {
			$admin->add_cap( 'atg_view_reports' );
		}

		$editor = get_role( 'editor' );
		if ( $editor ) {
			if ( $this->get( 'allow_editor_reports', true ) ) {
				$editor->add_cap( 'atg_view_reports' );
			} else {
				$editor->remove_cap( 'atg_view_reports' );
			}
		}
	}

	/**
	 * Get a setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Update settings (merged) and refresh cache.
	 *
	 * @param array $new New values.
	 */
	public function update_settings( $new ) {
		$this->settings = wp_parse_args( $new, $this->settings );
		update_option( 'atg_settings', $this->settings );
	}

	/**
	 * Current enforcement mode, accounting for shadow grace expiry.
	 *
	 * @return string shadow|active|off
	 */
	public function enforcement_mode() {
		if ( $this->get( 'staging_mode', false ) || ( defined( 'WP_ENV' ) && 'staging' === WP_ENV ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return 'shadow';
		}
		// Shadow stays shadow after the grace period until the admin promotes
		// to active — we never silently start blocking traffic.
		return $this->get( 'enforcement', 'shadow' );
	}

	/**
	 * Daily housekeeping: retention pruning + alert digest.
	 */
	public function cron_daily() {
		ATG_DB::prune( (int) $this->get( 'retention_days', 30 ) );
	}

	/**
	 * Set a lightweight anonymous session cookie (SameSite=Lax, HttpOnly not
	 * set so the beacon can read it; contains no personal data).
	 */
	public function ensure_session_cookie() {
		if ( is_user_logged_in() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( empty( $_COOKIE['atg_sid'] ) && ! headers_sent() ) {
			$sid = wp_generate_password( 24, false, false );
			setcookie( 'atg_sid', $sid, time() + 1800, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			$_COOKIE['atg_sid'] = $sid;
		}
	}

	/**
	 * Hash an IP for storage (privacy default) or return it raw.
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	public function store_ip( $ip ) {
		if ( $this->get( 'hash_ips', true ) ) {
			return hash( 'sha256', $ip . wp_salt( 'nonce' ) );
		}
		return $ip;
	}

	/**
	 * Best-effort client IP, honoring trusted proxy headers only when a proxy
	 * is configured (avoids XFF spoofing on direct connections).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$trusted_proxy = apply_filters( 'atg_trusted_proxy', false );
		$candidates    = array();

		// Cloudflare sends the real client IP in CF-Connecting-IP.
		if ( $trusted_proxy && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				$candidates[] = $cf_ip;
			}
		}

		if ( $trusted_proxy && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$chain       = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( $chain[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$ip = '0.0.0.0';
		foreach ( $candidates as $candidate ) {
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate;
				break;
			}
		}

		/**
		 * Filter the resolved client IP address.
		 *
		 * Useful for custom proxy setups, Cloudflare Workers, or Varnish
		 * pass-through configurations.
		 *
		 * @param string $ip Resolved client IP.
		 */
		return apply_filters( 'atg_client_ip', $ip );
	}

	/**
	 * Add plugin policy text to the privacy policy guide.
	 *
	 * @param string $content Privacy policy content.
	 * @return string
	 */
	public function privacy_policy_content( $content ) {
		$text  = '<h2>' . esc_html__( 'Bot Traffic Detection', 'ai-traffic-guardian' ) . '</h2>';
		$text .= '<p>' . sprintf(
			/* translators: 1: hashed/record string, 2: retention days */
			esc_html__( 'This site uses AI Traffic Guardian to detect and manage automated bot traffic. It may log a %1$s of your IP address, your browser\'s user agent string, and the page paths you visit. This data is retained for %2$s days and is used solely for security and traffic analysis.', 'ai-traffic-guardian' ),
			$this->get( 'hash_ips', true ) ? esc_html__( 'cryptographic hash', 'ai-traffic-guardian' ) : esc_html__( 'record', 'ai-traffic-guardian' ),
			esc_html( $this->get( 'retention_days', 30 ) )
		) . '</p>';
		return $content . $text;
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['atg-traffic-log'] = array(
			'exporter_friendly_name' => __( 'Bot Traffic Log', 'ai-traffic-guardian' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Export personal data callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page index.
	 * @return array
	 */
	public function export_personal_data( $email, $page = 1 ) {
		// We don't store user email, so we return empty by default.
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['atg-traffic-log'] = array(
			'eraser_friendly_name' => __( 'Bot Traffic Log', 'ai-traffic-guardian' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Erase personal data callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page index.
	 * @return array
	 */
	public function erase_personal_data( $email, $page = 1 ) {
		return array(
			'items_removed'  => 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
