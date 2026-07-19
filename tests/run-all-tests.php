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

// Mocked options store
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

// Helper reporting
$passed = 0;
$failed = 0;
$results_log = array();

function assert_test( $name, $assertion ) {
	global $passed, $failed, $results_log;
	if ( $assertion ) {
		$results_log[] = array( 'status' => 'PASS', 'name' => $name );
		$passed++;
	} else {
		$results_log[] = array( 'status' => 'FAIL', 'name' => $name );
		$failed++;
	}
}

/* ----------------------------------------------------------------
 * RUNNING TESTS
 * ---------------------------------------------------------------- */
$classifier = new ATG_Classifier();

// Test Suite 1: Classifier & Presets
$options['atg_preset'] = 'balanced';
$res = $classifier->classify( 'Googlebot/2.1', '66.249.66.1', '/', false );
assert_test( 'Balanced mode: Googlebot allowed', 'google' === $res['vendor'] && 'allow' === $res['action'] );

$options['atg_preset'] = 'strict';
$res = $classifier->classify( 'MysteriousCrawler/1.0', '192.168.1.5', '/', false );
assert_test( 'Strict mode: Unknown bot blocked', 'block' === $res['action'] );

$options['atg_preset'] = 'headless';
$res = $classifier->classify( 'Googlebot', '66.249.66.1', '/wp-json/wp/v2/posts', true );
assert_test( 'Headless mode: REST path triggers headless_rest classification', 'headless_rest' === $res['classification'] );

$res = $classifier->classify( 'Googlebot', '66.249.66.1', '/wp-json/wp/v2/posts', true, array( 'HTTP_AUTHORIZATION' => 'Bearer token123' ) );
assert_test( 'Headless mode: REST path with Authorization header bypassed', 'allow' === $res['action'] );

$res = $classifier->classify( 'CustomSpider/3.1', '10.0.0.1', '/', false );
assert_test( 'Custom Signature: matches and classifies Custom Spider', 'custom_0' === $res['classification'] && 'Spider Corp' === $res['vendor'] );

// Test Suite 2: Allowlist Engine
$allowlist = new ATG_Allowlist();
assert_test( 'Allowlist: IP Match', $allowlist->ip_allowed( '192.168.1.100' ) );
assert_test( 'Allowlist: IP Mismatch', ! $allowlist->ip_allowed( '192.168.1.101' ) );
assert_test( 'Allowlist: Agent Regex Match', false !== $allowlist->ua_allowed( 'Hello TrustedPartnerBot World' ) );
assert_test( 'Allowlist: URI Match', $allowlist->path_match( '/public-api/v1/get' ) );

// Test Suite 3: Rate Limiter
$limiter = new ATG_Rate_Limiter();
$decision = array(
	'ip'      => '10.0.0.50',
	'session' => 'sess_123',
	'action'  => 'allow',
	'reason'  => '',
	'risk'    => 0,
);
global $options, $transients;
$options['atg_settings']['rate_enabled']   = true;
$options['atg_settings']['rate_human_rpm'] = 2;
$options['atg_settings']['rate_burst']     = 1;
$transients = array();

$res1 = $limiter->check( $decision, 'human' );
$res2 = $limiter->check( $decision, 'human' );
$res3 = $limiter->check( $decision, 'human' );

assert_test( 'Rate Limiter: First hits within limit are allowed', 'allow' === $res1['action'] && 'allow' === $res2['action'] );
assert_test( 'Rate Limiter: Exceeding limit triggers throttle', 'throttle' === $res3['action'] );


// Test Suite 4: Form Guard
$guard = new ATG_Form_Guard();

$session = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : 'none';
$token = hash_hmac( 'sha256', 'form|' . $session, wp_salt( 'nonce' ) );

$_POST['atg_hp'] = '';
$_POST['atg_tok'] = $token;

$errors = new WP_Error();
$errors = $guard->check_registration( $errors );
assert_test( 'Form Guard: Clean submission with valid token allowed', ! $errors->has_errors() );

$_POST['atg_hp'] = 'spambot@spam.com';
$errors_spam = new WP_Error();
$errors_spam = $guard->check_registration( $errors_spam );
assert_test( 'Form Guard: Filled honeypot classified as spam', $errors_spam->has_errors() );

// Test Suite 5: Licensing
$options['atg_license_status'] = '';
assert_test( 'Licensing: Default state is inactive/free', ! ATG_Licensing::atg_is_pro() );
$options['atg_license_status'] = 'active';
assert_test( 'Licensing: Active state returns pro status', ATG_Licensing::atg_is_pro() );

// Test Suite 6: Security Audit Engine
$audit = new ATG_Bot_Audit();
$report = $audit->run();
assert_test( 'Audit: Generated audit grade is returned', isset( $report['grade'] ) );
assert_test( 'Audit: Sections check structure exists', isset( $report['sections']['wp_hardening'] ) );


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
		<title>Bot Shield Pro - Test Runner</title>
		<style>
			body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #1e293b; padding: 40px; margin: 0; }
			.container { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
			h1 { font-size: 24px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; color: #0f172a; }
			.test-row { display: flex; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; align-items: center; }
			.test-row:last-child { border-bottom: none; }
			.status { font-weight: bold; padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: uppercase; }
			.status.pass { background: #dcfce7; color: #15803d; }
			.status.fail { background: #fee2e2; color: #b91c1c; }
			.summary { margin-top: 30px; padding: 20px; border-radius: 8px; font-weight: bold; font-size: 16px; text-align: center; }
			.summary.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
			.summary.danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
		</style>
	</head>
	<body>
		<div class="container">
			<h1>Bot Shield Pro - System Diagnostics & Tests</h1>
			<div class="test-list">
				<?php foreach ( $results_log as $log ) : ?>
					<div class="test-row">
						<span class="test-name"><?php echo htmlspecialchars( $log['name'] ); ?></span>
						<span class="status <?php echo strtolower( $log['status'] ); ?>"><?php echo $log['status']; ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $failed === 0 ) : ?>
				<div class="summary success">
					All tests passed successfully! (<?php echo $passed; ?>/<?php echo $passed; ?>)
				</div>
			<?php else : ?>
				<div class="summary danger">
					Warning: <?php echo $failed; ?> tests failed! Please check logs.
				</div>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
}
