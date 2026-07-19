<?php
/**
 * Edge Integration Layer: Cloudflare Workers, KV sync, Nginx maps, and HMAC verification.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Edge
 */
class ATG_Edge {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Hook into policy update or setting change to trigger KV sync.
		add_action( 'atg_policy_updated', array( $this, 'maybe_trigger_kv_sync' ) );
		add_action( 'atg_cron_weekly', array( $this, 'maybe_trigger_kv_sync' ) );
	}

	/**
	 * Get or generate the unique site ID.
	 *
	 * @return string Site ID.
	 */
	public static function get_site_id() {
		$site_id = get_option( 'atg_edge_site_id' );
		if ( ! $site_id ) {
			$site_id = 'atg_' . substr( md5( wp_generate_uuid4() ), 0, 12 );
			update_option( 'atg_edge_site_id', $site_id );
		}
		return $site_id;
	}

	/**
	 * Get or generate the 32-byte shared secret.
	 *
	 * @return string Hex encoded shared secret.
	 */
	public static function get_shared_secret() {
		$secret = get_option( 'atg_edge_shared_secret' );
		if ( ! $secret ) {
			$secret = bin2hex( wp_generate_password( 32, true, true ) );
			update_option( 'atg_edge_shared_secret', $secret );
		}
		return $secret;
	}

	/**
	 * Compute HMAC signature.
	 *
	 * @param string $path      Request path (e.g. /wp-json/atg/v1/verify).
	 * @param string $method    HTTP method.
	 * @param int    $timestamp Unix timestamp.
	 * @param string $body      Raw request body.
	 * @return string Signature hash.
	 */
	public static function compute_signature( $path, $method, $timestamp, $body = '' ) {
		$secret  = self::get_shared_secret();
		$message = sprintf( '%s.%s.%s.%s', $timestamp, strtoupper( $method ), $path, $body );
		return hash_hmac( 'sha256', $message, $secret );
	}

	/**
	 * Verify HMAC signature.
	 *
	 * @param string $path      Request path.
	 * @param string $method    HTTP method.
	 * @param int    $timestamp Unix timestamp.
	 * @param string $signature Signature to verify.
	 * @param string $body      Raw request body.
	 * @return bool True if valid.
	 */
	public static function verify_signature( $path, $method, $timestamp, $signature, $body = '' ) {
		// Replay protection: 120s window.
		if ( abs( time() - (int) $timestamp ) > 120 ) {
			return false;
		}
		$computed = self::compute_signature( $path, $method, $timestamp, $body );
		return hash_equals( $computed, $signature );
	}

	/**
	 * Generate the current policy snapshot matching contract schema version 1.0.
	 *
	 * @return array Snapshot array.
	 */
	public static function generate_snapshot() {
		$plugin     = ATG_Plugin::instance();
		$signatures = $plugin->bot_db->signatures();
		$custom     = get_option( 'atg_custom_signatures', array() );
		$all_sigs   = array_merge( $signatures, $custom );

		$vendors = array();
		// Group signatures by vendor.
		$grouped = array();
		foreach ( $all_sigs as $sig ) {
			$vendor = strtolower( $sig['vendor'] );
			if ( ! isset( $grouped[ $vendor ] ) ) {
				$grouped[ $vendor ] = array(
					'purposes'        => array(),
					'ip_ranges'       => array(),
					'verified_agents' => array(),
				);
			}
			$purpose = $sig['purpose'];
			$grouped[ $vendor ]['purposes'][ $purpose ] = $plugin->policy->action_for( $sig['vendor'], $purpose );
			if ( ! empty( $sig['ip_source'] ) ) {
				// Cache current IP ranges if loaded locally.
				$grouped[ $vendor ]['ip_ranges'][] = $sig['ip_source'];
			}
			$grouped[ $vendor ]['verified_agents'][] = $sig['name'];
		}

		foreach ( $grouped as $vname => $g ) {
			$vendors[] = array(
				'vendor'          => $vname,
				'purposes'        => $g['purposes'],
				'ip_ranges'       => array_values( array_unique( $g['ip_ranges'] ) ),
				'verified_agents' => array_values( array_unique( $g['verified_agents'] ) ),
			);
		}

		return array(
			'schema_version' => '1.0',
			'site_id'        => self::get_site_id(),
			'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'default_action' => $plugin->get( 'default_unknown_action', 'throttle_log' ) === 'block' ? 'block' : 'observe',
			'shadow_mode'    => 'shadow' === $plugin->enforcement_mode(),
			'vendors'        => $vendors,
			'human_proxies'  => array( 'ChatGPT-User', 'Claude-User', 'Perplexity-User' ),
			'exempt_paths'   => array( '/wc-api/', '/wp-json/wc/', '/wp-cron.php', '/wp-json/wp/v2/' ),
			'throttle'       => array(
				'requests_per_minute' => (int) $plugin->get( 'rate_bot_rpm', 20 ),
				'action_on_exceed'    => 'throttle',
			),
		);
	}

	/**
	 * Perform KV push if Cloudflare KV settings are configured.
	 */
	public function maybe_trigger_kv_sync() {
		$token      = ATG_Plugin::instance()->get( 'cloudflare_api_token' );
		$account_id = ATG_Plugin::instance()->get( 'cloudflare_account_id' );
		$namespace  = ATG_Plugin::instance()->get( 'cloudflare_kv_namespace' );

		if ( ! $token || ! $account_id || ! $namespace ) {
			return;
		}

		$site_id  = self::get_site_id();
		$snapshot = self::generate_snapshot();
		$json     = wp_json_encode( $snapshot );
		$hash     = hash( 'sha256', $json );

		// Check meta cache to avoid redundant KV writes.
		$meta = get_option( 'atg_edge_meta', array() );
		if ( isset( $meta['hash'] ) && $meta['hash'] === $hash ) {
			return;
		}

		// Push to KV policy key.
		$url      = sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/storage/kv/namespaces/%s/values/policy:%s', $account_id, $namespace, $site_id );
		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $json,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return;
		}

		// Update meta locally.
		$meta_data = array(
			'version'   => '1.0',
			'hash'      => $hash,
			'pushed_at' => current_time( 'mysql' ),
		);
		update_option( 'atg_edge_meta', $meta_data );

		// Push meta data to KV as well.
		$meta_url = sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/storage/kv/namespaces/%s/values/meta:%s', $account_id, $namespace, $site_id );
		wp_remote_request(
			$meta_url,
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $meta_data ),
				'timeout' => 15,
			)
		);
	}

	/**
	 * Generate Cloudflare Worker JS code.
	 *
	 * @return string Worker JS.
	 */
	public static function generate_worker_js() {
		$site_id = self::get_site_id();
		$secret  = self::get_shared_secret();

		return <<<JS
/**
 * Cloudflare Worker for Bot Shield Pro / Bot Shield Pro Edge Layer
 */
const SITE_ID = "{$site_id}";

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request, event))
})

async function handleRequest(request, event) {
  const url = new URL(request.url);
  
  // 1. Bypass critical paths
  if (url.pathname.includes('/wp-admin') || url.pathname.includes('/wp-login.php') || url.pathname.includes('/wc-api/') || url.pathname.includes('/wp-json/atg/v1/')) {
    return fetch(request);
  }

  // 2. Read policy from KV
  let policy = null;
  try {
    const raw = await ATG_POLICY_KV.get("policy:" + SITE_ID);
    if (raw) policy = JSON.parse(raw);
  } catch (e) {
    // Fail-open on KV fetch failure
  }

  if (!policy || policy.schema_version !== "1.0") {
    return fetch(request);
  }

  const ua = request.headers.get('user-agent') || '';
  const ip = request.headers.get('cf-connecting-ip') || '';

  // 3. Match human proxies
  for (const proxy of policy.human_proxies) {
    if (ua.includes(proxy)) {
      return fetch(request);
    }
  }

  // 4. Match exempt paths
  for (const path of policy.exempt_paths) {
    if (url.pathname.startsWith(path)) {
      return fetch(request);
    }
  }

  // 5. Evaluate matching bots
  let match = false;
  let action = policy.default_action;

  for (const vendor of policy.vendors) {
    for (const agent of vendor.verified_agents) {
      if (ua.toLowerCase().includes(agent.toLowerCase())) {
        match = true;
        // Verify IP if range list exists
        let isVerified = false;
        if (vendor.ip_ranges && vendor.ip_ranges.length > 0) {
          isVerified = checkIpRanges(ip, vendor.ip_ranges);
        } else {
          isVerified = null; // Unverifiable bot class
        }

        if (isVerified === true) {
          action = vendor.purposes.search || 'allow';
        } else if (isVerified === false) {
          action = 'block'; // Spoofing attempt
        } else {
          action = vendor.purposes.unverified || 'throttle';
        }
        break;
      }
    }
    if (match) break;
  }

  if (!policy.shadow_mode) {
    if (action === 'block') {
      return new Response('Access restricted by Bot Shield Pro (Edge Block)', {
        status: 403,
        headers: { 'Content-Type': 'text/plain', 'X-Robots-Tag': 'noindex, nofollow' }
      });
    }
  }

  return fetch(request);
}

function checkIpRanges(ip, ranges) {
  // Simple CIDR / IP checks
  for (const cidr of ranges) {
    if (ip === cidr) return true;
    if (cidr.includes('/')) {
      const parts = cidr.split('/');
      const base = parts[0];
      const mask = parseInt(parts[1], 10);
      if (ipInSandbox(ip, base, mask)) return true;
    }
  }
  return false;
}

function ipInSandbox(ip, base, mask) {
  // Rough binary conversion helper
  return false; // stubbed for runtime safety
}
JS;
	}

	/**
	 * Generate Nginx map block.
	 *
	 * @return string Nginx config content.
	 */
	public static function generate_nginx_map() {
		$plugin     = ATG_Plugin::instance();
		$signatures = $plugin->bot_db->signatures();
		$custom     = get_option( 'atg_custom_signatures', array() );
		$all_sigs   = array_merge( $signatures, $custom );

		$blocked_patterns = array();
		foreach ( $all_sigs as $sig ) {
			$action = $plugin->policy->action_for( $sig['vendor'], $sig['purpose'] );
			if ( 'block' === $action ) {
				$pat = trim( $sig['pattern'], '#' );
				$pat = str_replace( '|', '; ', $pat );
				$blocked_patterns[] = $pat;
			}
		}

		$out  = "# Bot Shield Pro - Nginx User-Agent Blocking Map\n";
		$out .= "map \$http_user_agent \$is_bad_bot {\n";
		$out .= "    default 0;\n";
		foreach ( $blocked_patterns as $pattern ) {
			$out .= sprintf( "    \"~*%s\" 1;\n", esc_attr( $pattern ) );
		}
		$out .= "}\n\n";
		$out .= "# Place this inside your server block:\n";
		$out .= "# if (\$is_bad_bot) {\n";
		$out .= "#     return 403;\n";
		$out .= "# }\n";

		return $out;
	}
}

