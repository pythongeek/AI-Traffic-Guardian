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
		$this->settings = wp_parse_args( get_option( 'atg_settings', array() ), self::default_settings() );

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
		$this->alerts->hooks();

		// Cron.
		add_action( 'atg_cron_daily', array( $this, 'cron_daily' ) );
		add_action( 'atg_cron_weekly', array( $this->verifier, 'refresh_ip_ranges' ) );

		// REST API + admin.
		ATG_REST::register();
		if ( is_admin() ) {
			$admin = new ATG_Admin();
			$admin->hooks();
		}

		// Session cookie for rate limiting (anonymous traffic only).
		add_action( 'init', array( $this, 'ensure_session_cookie' ), 2 );
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
		if ( $trusted_proxy && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$chain       = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$candidates[] = trim( $chain[0] );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		foreach ( $candidates as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '0.0.0.0';
	}
}
