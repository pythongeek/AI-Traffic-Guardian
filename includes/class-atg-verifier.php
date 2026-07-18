<?php
/**
 * Bot identity verification: reverse-DNS + forward-DNS confirmation and
 * published IP-range matching. Results are cached aggressively.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Verifier
 */
class ATG_Verifier {

	const CACHE_GROUP = 'atg_verify';
	const CACHE_TTL   = 86400; // 24h per-IP result cache.

	/**
	 * Verify a claimed bot identity for an IP.
	 *
	 * @param string $ip  Client IP.
	 * @param array  $sig Signature entry from ATG_Bot_Database.
	 * @return string verified|spoofed|unverifiable
	 */
	public function verify( $ip, $sig ) {
		$cache_key = 'atg_v_' . md5( $ip . '|' . $sig['name'] );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = 'unverifiable';
		$method = isset( $sig['verify'] ) ? $sig['verify'] : 'none';

		if ( 'rdns' === $method && ! empty( $sig['rdns_suffix'] ) ) {
			$result = $this->verify_rdns( $ip, (array) $sig['rdns_suffix'] );
		} elseif ( 'ip_range' === $method && ! empty( $sig['ip_source'] ) ) {
			$result = $this->verify_ip_range( $ip, $sig['ip_source'] );
		} elseif ( 'none' !== $method ) {
			/**
			 * Filter verification result for custom verification methods.
			 *
			 * @param string $result Default 'unverifiable'.
			 * @param string $ip     Client IP.
			 * @param array  $sig    Signature array.
			 */
			$result = apply_filters( "atg_verify_method_{$method}", 'unverifiable', $ip, $sig );
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Reverse-DNS then forward-DNS confirmation (Google-style verification).
	 *
	 * @param string $ip        IP to check.
	 * @param array  $suffixes  Allowed PTR suffixes (e.g. .googlebot.com).
	 * @return string verified|spoofed|unverifiable
	 */
	private function verify_rdns( $ip, $suffixes ) {
		$host = @gethostbyaddr( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $host || $host === $ip ) {
			return 'unverifiable';
		}
		$host   = strtolower( rtrim( $host, '.' ) );
		$match  = false;
		foreach ( $suffixes as $suffix ) {
			$suffix = strtolower( $suffix );
			if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
				$match = true;
				break;
			}
		}
		if ( ! $match ) {
			// PTR exists but does not belong to the claimed crawler: spoofed.
			return 'spoofed';
		}
		// Forward-confirm: the hostname must resolve back to the same IP.
		$forward = gethostbynamel( $host );
		if ( is_array( $forward ) && in_array( $ip, $forward, true ) ) {
			return 'verified';
		}
		return 'spoofed';
	}

	/**
	 * Match an IP against a published JSON prefix list.
	 *
	 * @param string $ip     IP to check.
	 * @param string $source URL of the JSON range list.
	 * @return string verified|unverifiable
	 */
	private function verify_ip_range( $ip, $source ) {
		$ranges = $this->get_ranges( $source );
		if ( empty( $ranges ) ) {
			return 'unverifiable';
		}
		foreach ( $ranges as $cidr ) {
			if ( $this->ip_in_cidr( $ip, $cidr ) ) {
				return 'verified';
			}
		}
		// Ranges exist and the IP is outside them: treat as spoofed claim.
		return 'spoofed';
	}

	/**
	 * Fetch + cache a published IP range list (OpenAI/Perplexity JSON format:
	 * {"prefixes":[{"ipv4Prefix":"1.2.3.0/24"},…]} or plain array of CIDRs).
	 *
	 * @param string $source URL.
	 * @return array List of CIDR strings.
	 */
	public function get_ranges( $source ) {
		$cache_key = 'atg_ranges_' . md5( $source );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$ranges = $this->fetch_ranges( $source );
		if ( empty( $ranges ) ) {
			set_transient( $cache_key, $ranges, HOUR_IN_SECONDS );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'AI Traffic Guardian: Failed to fetch IP ranges from %s. Caching failure for 1 hour.', $source ) );
		} else {
			set_transient( $cache_key, $ranges, WEEK_IN_SECONDS );
		}
		return $ranges;
	}

	/**
	 * HTTP fetch of a range list with defensive parsing.
	 *
	 * @param string $source URL.
	 * @return array
	 */
	private function fetch_ranges( $source ) {
		$response = wp_remote_get(
			$source,
			array(
				'timeout'    => 8,
				'user-agent' => 'AI-Traffic-Guardian/' . ATG_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		if ( isset( $data['prefixes'] ) && is_array( $data['prefixes'] ) ) {
			foreach ( $data['prefixes'] as $p ) {
				foreach ( array( 'ipv4Prefix', 'ipv6Prefix', 'prefix' ) as $k ) {
					if ( ! empty( $p[ $k ] ) && is_string( $p[ $k ] ) ) {
						$out[] = trim( $p[ $k ] );
					}
				}
			}
		} else {
			foreach ( $data as $item ) {
				if ( is_string( $item ) && false !== strpos( $item, '/' ) ) {
					$out[] = trim( $item );
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Cron: refresh every published range list in the signature table.
	 */
	public function refresh_ip_ranges() {
		foreach ( ATG_Bot_Database::signatures() as $sig ) {
			if ( ! empty( $sig['ip_source'] ) ) {
				$ranges = $this->fetch_ranges( $sig['ip_source'] );
				if ( ! empty( $ranges ) ) {
					set_transient( 'atg_ranges_' . md5( $sig['ip_source'] ), $ranges, WEEK_IN_SECONDS );
				}
			}
		}
	}

	/**
	 * CIDR containment check supporting IPv4 and IPv6.
	 *
	 * @param string $ip   IP address.
	 * @param string $cidr CIDR range.
	 * @return bool
	 */
	public function ip_in_cidr( $ip, $cidr ) {
		if ( false === strpos( $cidr, '/' ) ) {
			return $ip === $cidr;
		}
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$bits = (int) $bits;

		$ip_bin     = @inet_pton( $ip );
		$subnet_bin = @inet_pton( $subnet );
		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}
		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
			return false;
		}
		if ( $rem > 0 ) {
			$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );
			return ( ord( $ip_bin[ $bytes ] ) & ord( $mask ) ) === ( ord( $subnet_bin[ $bytes ] ) & ord( $mask ) );
		}
		return true;
	}
}
