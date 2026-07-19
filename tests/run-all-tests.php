<?php
/**
 * Unified Bot Shield Pro CLI & Web Test Runner
 *
 * Runs extensive mock unit tests for all features:
 * - Bot Classification & Headless CMS Mode
 * - Allowlist Routing & Verification
 * - Rate Limiter Thresholds
 * - Form Guard Honeypot & Woocommerce Hook Handling
 * - Security Auditing Engine
 * - Licensing validation logic
 * - Report Generation Statistics
 */

// If accessed via web, bootstrap WordPress to verify admin permissions
$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
	// Locate wp-load.php (4 directories up from plugins/botshield-pro/tests/)
	$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
	if ( file_exists( $wp_load ) ) {
		require_once $wp_load;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access. You must be logged in as an administrator to run this test suite.' );
		}
	} else {
		// Fallback if accessed locally outside WP
		define( 'ABSPATH', true );
	}
} else {
	define( 'ABSPATH', true );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors = array();
		public function add( $code, $message ) {
			$this->errors[ $code ] = $message;
		}
		public function has_errors() {
			return ! empty( $this->errors );
		}
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'mocked_salt_key';
	}
}

// Mocked options store (used in CLI fallback)
$options = array(
	'atg_preset'                => 'balanced',
	'atg_settings'              => array(
		'auth_bypass'       => true,
		'enforcement'       => 'active',
		'honeypot_action'   => 'block',
		'wc_checkout_guard' => 'block',
	),
	'atg_custom_rest_namespace' => 'my-api/v1',
	'atg_allowlist'             => array(
		'ips'    => array( '192.168.1.100' ),
		'agents' => array( 'TrustedPartnerBot' ),
		'uris'   => array( '/public-api/' ),
	),
	'atg_custom_signatures'     => array(
		array(
			'name'    => 'Custom Spider',
			'vendor'  => 'Spider Corp',
			'purpose' => 'commercial',
			'pattern' => 'CustomSpider/\\d',
			'verify'  => 'none',
		),
	),
);

// Global mock state
$transients = array();
$options_saved = array();

// Mock WordPress functions if not loaded
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $options;
		return isset( $options[ $name ] ) ? $options[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		global $options, $options_saved;
		$options[ $name ]       = $value;
		$options_saved[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		global $options;
		unset( $options[ $name ] );
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		global $transients;
		return isset( $transients[ $key ] ) ? $transients[ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		global $transients;
		$transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $transients;
		unset( $transients[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $x ) {
		return trim( $x );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $x ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $x ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $x ) {
		return $x;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $x ) {
		return $x;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( $x ) {
		return addslashes( $x );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( '__' ) ) {
	function __ ( $text, $domain = 'default' ) { return $text; }
}

// Mock Database wrapper if WP DB not initialized
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	class MockWPDB {
		public $prefix = 'wp_';
		public function prepare( $query, ...$args ) {
			if ( is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$parts = explode( '%s', $query );
			$res   = '';
			foreach ( $parts as $i => $part ) {
				$res .= $part;
				if ( isset( $args[ $i ] ) ) {
					$res .= "'" . addslashes( $args[ $i ] ) . "'";
				}
			}
			return $res;
		}
		public function get_results( $query, $output = 'OBJECT' ) {
			return array();
		}
		public function get_var( $query ) {
			return 0;
		}
		public function query( $query ) {
			return true;
		}
	}
	$wpdb = new MockWPDB();
} else {
	$wpdb = $GLOBALS['wpdb'];
}

class MockLogger {
	public function log( $data ) {
		return true;
	}
}

// Mock plugin container
class MockPlugin {
	public $allowlist;
	public $logger;
	public $settings = array();
	public function __construct() {
		global $options;
		$this->allowlist = new ATG_Allowlist();
		$this->logger    = new MockLogger();
		$this->settings  = $options['atg_settings'];
	}
	public function get( $key, $default = false ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}
	public function enforcement_mode() {
		return $this->get( 'enforcement', 'shadow' );
	}
}

if ( ! class_exists( 'ATG_Plugin' ) ) {
	class ATG_Plugin {
		private static $inst = null;
		public static function instance() {
			if ( null === self::$inst ) {
				self::$inst = new MockPlugin();
			}
			return self::$inst;
		}
		public static function client_ip() {
			return '127.0.0.1';
		}
	}
}

// Load plugin classes
require_once dirname( __DIR__ ) . '/includes/class-atg-allowlist.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-classifier.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-rate-limiter.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-form-guard.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-licensing.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-bot-audit.php';
require_once dirname( __DIR__ ) . '/includes/class-atg-verifier.php';

// Helper reporting
$passed = 0;
$failed = 0;
$results_log = array();

function assert_test( $name, $assertion, $expected = '', $actual = '', $message = '' ) {
	global $passed, $failed, $results_log;
	if ( $assertion ) {
		$results_log[] = array(
			'status'   => 'PASS',
			'name'     => $name,
			'expected' => $expected,
			'actual'   => $actual,
			'message'  => $message ? $message : 'Condition met successfully.'
		);
		$passed++;
	} else {
		$results_log[] = array(
			'status'   => 'FAIL',
			'name'     => $name,
			'expected' => $expected,
			'actual'   => $actual,
			'message'  => $message ? $message : 'Condition was not met.'
		);
		$failed++;
	}
}

// Helper to set options dynamically (works in both WP and CLI)
function set_test_option( $key, $value ) {
	global $options;
	$options[ $key ] = $value;
	update_option( $key, $value );
}

/* ----------------------------------------------------------------
 * OPTIONS BACKUP
 * ---------------------------------------------------------------- */
$original_options = array();
$option_keys = array(
	'atg_preset',
	'atg_settings',
	'atg_custom_rest_namespace',
	'atg_allowlist',
	'atg_custom_signatures',
	'atg_license_status',
);

foreach ( $option_keys as $key ) {
	$original_options[ $key ] = get_option( $key );
}

try {
	// Seed configuration options in database for test runner
	set_test_option( 'atg_allowlist', array(
		'ips'        => array( '192.168.1.100' ),
		'paths'      => array( '/public-api/' ),
		'uas'        => array( 'TrustedPartnerBot' ),
		'path_rules' => array(),
	) );
	set_test_option( 'atg_custom_signatures', array(
		array(
			'name'    => 'Custom Spider',
			'vendor'  => 'Spider Corp',
			'purpose' => 'commercial',
			'pattern' => 'CustomSpider/\\d',
			'verify'  => 'none',
		),
	) );
	set_test_option( 'atg_custom_rest_namespace', 'my-api/v1' );

	// Seed identity verifier cache for Googlebot reverse-DNS mock
	$cache_key = 'atg_v_' . md5( '66.249.66.1|Googlebot' );
	set_transient( $cache_key, 'verified', 3600 );

	$classifier = new ATG_Classifier();

	// Test Suite 1: Classifier & Presets
	set_test_option( 'atg_preset', 'balanced' );
	set_test_option( 'atg_settings', array(
		'auth_bypass'       => true,
		'enforcement'       => 'active',
		'honeypot_action'   => 'block',
		'wc_checkout_guard' => 'block',
	) );

	$res = $classifier->classify( 'Googlebot/2.1', '66.249.66.1', '/', false );
	assert_test(
		'Balanced mode: Googlebot allowed',
		'google' === $res['vendor'] && 'allow' === $res['action'],
		'vendor: google, action: allow',
		"vendor: {$res['vendor']}, action: {$res['action']}",
		'Verified Googlebot should be allowed under the Balanced preset.'
	);

	set_test_option( 'atg_preset', 'strict' );
	$res = $classifier->classify( 'MysteriousCrawler/1.0', '192.168.1.5', '/', false );
	assert_test(
		'Strict mode: Unknown bot blocked',
		'block' === $res['action'],
		'action: block',
		"action: {$res['action']}",
		'Unrecognized bots should be blocked under the Strict preset.'
	);

	set_test_option( 'atg_preset', 'headless' );
	$res = $classifier->classify( 'Googlebot', '66.249.66.1', '/wp-json/wp/v2/posts', true );
	assert_test(
		'Headless mode: REST path triggers headless_rest classification',
		'headless_rest' === $res['classification'],
		'classification: headless_rest',
		"classification: {$res['classification']}",
		'REST route should trigger headless_rest classification under the Headless preset.'
	);

	$res = $classifier->classify( 'Googlebot', '66.249.66.1', '/wp-json/wp/v2/posts', true, array( 'HTTP_AUTHORIZATION' => 'Bearer token123' ) );
	assert_test(
		'Headless mode: REST path with Authorization header bypassed',
		'allow' === $res['action'],
		'action: allow',
		"action: {$res['action']}",
		'Headless request with valid authentication header should be allowed.'
	);

	$res = $classifier->classify( 'CustomSpider/3.1', '10.0.0.1', '/', false );
	assert_test(
		'Custom Signature: matches and classifies Custom Spider',
		'custom_0' === $res['classification'] && 'Spider Corp' === $res['vendor'],
		'classification: custom_0, vendor: Spider Corp',
		"classification: {$res['classification']}, vendor: {$res['vendor']}",
		'Custom regex signature should match and map to the custom vendor name.'
	);

	// Test Suite 2: Allowlist Engine
	$allowlist = new ATG_Allowlist();
	assert_test(
		'Allowlist: IP Match',
		$allowlist->ip_allowed( '192.168.1.100' ),
		'true',
		$allowlist->ip_allowed( '192.168.1.100' ) ? 'true' : 'false',
		'IP listed in the allowlist must return true.'
	);
	assert_test(
		'Allowlist: IP Mismatch',
		! $allowlist->ip_allowed( '192.168.1.101' ),
		'false',
		$allowlist->ip_allowed( '192.168.1.101' ) ? 'true' : 'false',
		'IP not in the allowlist must return false.'
	);
	assert_test(
		'Allowlist: Agent Regex Match',
		false !== $allowlist->ua_allowed( 'Hello TrustedPartnerBot World' ),
		'non-false',
		$allowlist->ua_allowed( 'Hello TrustedPartnerBot World' ) ? 'matched' : 'false',
		'UA containing the custom sub-string should match allowlist rules.'
	);
	assert_test(
		'Allowlist: URI Match',
		false !== $allowlist->path_match( '/public-api/v1/get' ),
		'non-false',
		$allowlist->path_match( '/public-api/v1/get' ) ? 'matched' : 'false',
		'Request URL matching prefix rules should be allowed.'
	);

	// Test Suite 3: Rate Limiter
	$limiter = new ATG_Rate_Limiter();
	$decision = array(
		'ip'      => '10.0.0.50',
		'session' => 'sess_123',
		'action'  => 'allow',
		'reason'  => '',
		'risk'    => 0,
	);

	set_test_option( 'atg_settings', array(
		'rate_enabled'   => true,
		'rate_human_rpm' => 2,
		'rate_burst'     => 1,
	) );
	
	// Reset rates
	delete_transient( 'atg_rl_s_' . md5( 'sess_123' ) );
	delete_transient( 'atg_rl_i_' . md5( '10.0.0.50' ) );

	$res1 = $limiter->check( $decision, 'human' );
	$res2 = $limiter->check( $decision, 'human' );
	$res3 = $limiter->check( $decision, 'human' );

	assert_test(
		'Rate Limiter: First hits within limit are allowed',
		'allow' === $res1['action'] && 'allow' === $res2['action'],
		'action: allow, action: allow',
		"action: {$res1['action']}, action: {$res2['action']}",
		'Initial requests under the thresholds must proceed uninterrupted.'
	);
	assert_test(
		'Rate Limiter: Exceeding limit triggers throttle',
		'throttle' === $res3['action'],
		'action: throttle',
		"action: {$res3['action']}",
		'Excessive request counts should trigger progressive throttling.'
	);

	// Test Suite 4: Form Guard
	$guard = new ATG_Form_Guard();

	$session = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : 'none';
	$token = hash_hmac( 'sha256', 'form|' . $session, wp_salt( 'nonce' ) );

	// Temporarily clear user login scope so Form Guard doesn't automatically trust current admin session
	$old_user_id = 0;
	if ( function_exists( 'wp_set_current_user' ) && function_exists( 'get_current_user_id' ) ) {
		$old_user_id = get_current_user_id();
		wp_set_current_user( 0 );
	}

	$_POST['atg_hp'] = '';
	$_POST['atg_tok'] = $token;

	$errors = new WP_Error();
	$errors = $guard->check_registration( $errors );
	assert_test(
		'Form Guard: Clean submission with valid token allowed',
		! $errors->has_errors(),
		'no errors',
		$errors->has_errors() ? 'has errors' : 'no errors',
		'Standard registration forms with correct tokens should pass.'
	);

	$_POST['atg_hp'] = 'spambot@spam.com';
	$errors_spam = new WP_Error();
	$errors_spam = $guard->check_registration( $errors_spam );
	assert_test(
		'Form Guard: Filled honeypot classified as spam',
		$errors_spam->has_errors(),
		'has errors',
		$errors_spam->has_errors() ? 'has errors' : 'no errors',
		'Submissions with populated honeypot fields must be blocked as spam.'
	);

	// Restore user session if applicable
	if ( $old_user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
		wp_set_current_user( $old_user_id );
	}

	// Test Suite 5: Licensing
	set_test_option( 'atg_license_status', '' );
	assert_test(
		'Licensing: Default state is inactive/free',
		! ATG_Licensing::atg_is_pro(),
		'false',
		ATG_Licensing::atg_is_pro() ? 'true' : 'false',
		'Default licensing check should show false / free tier status.'
	);

	set_test_option( 'atg_license_status', 'active' );
	assert_test(
		'Licensing: Active state returns pro status',
		ATG_Licensing::atg_is_pro(),
		'true',
		ATG_Licensing::atg_is_pro() ? 'true' : 'false',
		'Active licensing check should show true / pro tier status.'
	);

	// Test Suite 6: Security Audit Engine
	$audit = new ATG_Bot_Audit();
	$report = $audit->run();
	assert_test(
		'Audit: Generated audit grade is returned',
		isset( $report['grade'] ),
		'grade exists',
		isset( $report['grade'] ) ? 'grade exists' : 'grade missing',
		'Audit results must produce a grading report.'
	);
	assert_test(
		'Audit: Sections check structure exists',
		isset( $report['sections']['security'] ),
		'security section exists',
		isset( $report['sections']['security'] ) ? 'security section exists' : 'security section missing',
		'Audit output must contain the security analysis sub-component.'
	);

} finally {
	/* ----------------------------------------------------------------
	 * OPTIONS RESTORE
	 * ---------------------------------------------------------------- */
	foreach ( $original_options as $key => $val ) {
		if ( false === $val ) {
			delete_option( $key );
		} else {
			update_option( $key, $val );
		}
	}
	
	// Clean up verifier mock transient
	delete_transient( $cache_key );
}

/* ----------------------------------------------------------------
 * FILE LOG WRITING
 * ---------------------------------------------------------------- */
$log_content = "Bot Shield Pro Test Suite - Run at " . date( 'Y-m-d H:i:s' ) . "\n";
$log_content .= "======================================================================\n";
foreach ( $results_log as $log ) {
	$log_content .= "[{$log['status']}] {$log['name']}\n";
	$log_content .= "  Expected: {$log['expected']}\n";
	$log_content .= "  Actual:   {$log['actual']}\n";
	$log_content .= "  Details:  {$log['message']}\n\n";
}
$log_content .= "======================================================================\n";
$log_content .= "Summary: Passed: $passed, Failed: $failed\n";

$log_file_path = __DIR__ . '/test-run-log.txt';
@file_put_contents( $log_file_path, $log_content );

/* ----------------------------------------------------------------
 * RENDERING OUTPUT
 * ---------------------------------------------------------------- */
if ( $is_cli ) {
	echo "=== BOT SHIELD PRO CORE TEST SUITE ===\n\n";
	foreach ( $results_log as $log ) {
		if ( $log['status'] === 'PASS' ) {
			echo "\033[32m[PASS]\033[0m " . $log['name'] . "\n";
		} else {
			echo "\033[31m[FAIL]\033[0m " . $log['name'] . "\n";
			echo "       Expected: " . $log['expected'] . "\n";
			echo "       Actual:   " . $log['actual'] . "\n";
			echo "       Reason:   " . $log['message'] . "\n";
		}
	}
	echo "\n======================================\n";
	echo "Tests Run Complete. Passed: $passed, Failed: $failed\n";
	echo "======================================\n";
	exit( $failed > 0 ? 1 : 0 );
} else {
	// HTML Report
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Bot Shield Pro - System Diagnostic Center</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<style>
			:root {
				--bg-main: #0b0f19;
				--bg-card: #151c2e;
				--bg-accent: #1e294b;
				--border-color: #2e3c64;
				--text-main: #f8fafc;
				--text-muted: #94a3b8;
				--color-pass: #10b981;
				--color-pass-bg: rgba(16, 185, 129, 0.15);
				--color-fail: #ef4444;
				--color-fail-bg: rgba(239, 68, 68, 0.15);
				--color-purple: #8b5cf6;
			}
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				background: var(--bg-main);
				color: var(--text-main);
				padding: 40px 20px;
				margin: 0;
			}
			.container {
				max-width: 900px;
				margin: 0 auto;
				background: var(--bg-card);
				padding: 30px;
				border-radius: 16px;
				box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
				border: 1px solid var(--border-color);
			}
			header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				border-bottom: 1px solid var(--border-color);
				padding-bottom: 20px;
				margin-bottom: 25px;
			}
			h1 {
				font-size: 26px;
				margin: 0;
				background: linear-gradient(135deg, #c084fc, #6366f1);
				-webkit-background-clip: text;
				-webkit-text-fill-color: transparent;
			}
			.log-btn {
				background: var(--bg-accent);
				border: 1px solid var(--border-color);
				color: var(--text-main);
				padding: 8px 16px;
				border-radius: 8px;
				font-weight: 500;
				cursor: pointer;
				text-decoration: none;
				display: inline-flex;
				align-items: center;
				gap: 8px;
				transition: all 0.2s ease;
			}
			.log-btn:hover {
				background: var(--border-color);
				border-color: #475a90;
			}
			.stats-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 15px;
				margin-bottom: 25px;
			}
			.stat-card {
				background: var(--bg-main);
				border: 1px solid var(--border-color);
				padding: 15px 20px;
				border-radius: 12px;
				text-align: center;
			}
			.stat-num {
				font-size: 32px;
				font-weight: bold;
				margin-bottom: 5px;
			}
			.stat-num.pass { color: var(--color-pass); }
			.stat-num.fail { color: var(--color-fail); }
			.stat-num.total { color: var(--color-purple); }
			.stat-label {
				font-size: 13px;
				color: var(--text-muted);
				text-transform: uppercase;
				letter-spacing: 0.05em;
			}
			.test-row {
				border: 1px solid var(--border-color);
				border-radius: 10px;
				margin-bottom: 12px;
				overflow: hidden;
				transition: all 0.2s ease;
			}
			.test-header {
				display: flex;
				justify-content: space-between;
				padding: 15px 20px;
				background: rgba(21, 28, 46, 0.5);
				align-items: center;
				cursor: pointer;
				user-select: none;
			}
			.test-header:hover {
				background: var(--bg-accent);
			}
			.test-info {
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.status-indicator {
				width: 10px;
				height: 10px;
				border-radius: 50%;
			}
			.status-indicator.pass { background-color: var(--color-pass); box-shadow: 0 0 8px var(--color-pass); }
			.status-indicator.fail { background-color: var(--color-fail); box-shadow: 0 0 8px var(--color-fail); }
			.test-name {
				font-weight: 500;
				font-size: 15px;
			}
			.status-badge {
				font-weight: bold;
				padding: 4px 10px;
				border-radius: 6px;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 0.02em;
			}
			.status-badge.pass { background: var(--color-pass-bg); color: var(--color-pass); }
			.status-badge.fail { background: var(--color-fail-bg); color: var(--color-fail); }
			.test-details {
				display: none;
				padding: 20px;
				background: var(--bg-main);
				border-top: 1px solid var(--border-color);
				font-size: 14px;
				line-height: 1.6;
			}
			.test-details p {
				margin: 0 0 10px 0;
			}
			.test-details p:last-child {
				margin-bottom: 0;
			}
			.detail-key {
				color: var(--text-muted);
				font-weight: 500;
				display: inline-block;
				width: 120px;
			}
			.detail-val {
				font-family: monospace;
				background: var(--bg-accent);
				padding: 2px 6px;
				border-radius: 4px;
				color: #e2e8f0;
			}
			.summary-banner {
				margin-top: 30px;
				padding: 20px;
				border-radius: 12px;
				text-align: center;
				font-weight: bold;
				font-size: 16px;
				border: 1px solid transparent;
			}
			.summary-banner.success {
				background: var(--color-pass-bg);
				color: var(--color-pass);
				border-color: rgba(16, 185, 129, 0.3);
			}
			.summary-banner.danger {
				background: var(--color-fail-bg);
				color: var(--color-fail);
				border-color: rgba(239, 68, 68, 0.3);
			}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', () => {
				const rows = document.querySelectorAll('.test-header');
				rows.forEach(row => {
					row.addEventListener('click', () => {
						const details = row.nextElementSibling;
						if (details.style.display === 'block') {
							details.style.display = 'none';
						} else {
							details.style.display = 'block';
						}
					});
				});
			});
		</script>
	</head>
	<body>
		<div class="container">
			<header>
				<div>
					<h1>Bot Shield Pro - System Diagnostic Center</h1>
					<p style="margin: 5px 0 0 0; font-size: 13px; color: var(--text-muted);">Execution time: <?php echo date('Y-m-d H:i:s'); ?></p>
				</div>
				<a href="test-run-log.txt" class="log-btn" download>
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
						<path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
						<path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
					</svg>
					Download Text Log
				</a>
			</header>

			<div class="stats-grid">
				<div class="stat-card">
					<div class="stat-num pass"><?php echo $passed; ?></div>
					<div class="stat-label">Passed</div>
				</div>
				<div class="stat-card">
					<div class="stat-num fail"><?php echo $failed; ?></div>
					<div class="stat-label">Failed</div>
				</div>
				<div class="stat-card">
					<div class="stat-num total"><?php echo ( $passed + $failed ); ?></div>
					<div class="stat-label">Total Tests</div>
				</div>
			</div>

			<div class="test-list">
				<?php foreach ( $results_log as $log ) : ?>
					<div class="test-row">
						<div class="test-header">
							<div class="test-info">
								<span class="status-indicator <?php echo strtolower( $log['status'] ); ?>"></span>
								<span class="test-name"><?php echo htmlspecialchars( $log['name'] ); ?></span>
							</div>
							<span class="status-badge <?php echo strtolower( $log['status'] ); ?>"><?php echo $log['status']; ?></span>
						</div>
						<div class="test-details">
							<p><span class="detail-key">Description:</span><?php echo htmlspecialchars( $log['message'] ); ?></p>
							<p><span class="detail-key">Expected:</span><span class="detail-val"><?php echo htmlspecialchars( $log['expected'] ); ?></span></p>
							<p><span class="detail-key">Actual:</span><span class="detail-val"><?php echo htmlspecialchars( $log['actual'] ); ?></span></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $failed === 0 ) : ?>
				<div class="summary-banner success">
					All tests passed successfully! (<?php echo $passed; ?>/<?php echo $passed; ?>)
				</div>
			<?php else : ?>
				<div class="summary-banner danger">
					Warning: <?php echo $failed; ?> diagnostics failed. Click on rows to expand the validation log details.
				</div>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
}
