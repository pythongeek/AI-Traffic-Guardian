<?php
/**
 * Progressive rate limiter: session-bucket primary, IP-bucket secondary.
 * Escalation ladder: pass → throttle (429) → temporary cool-off block.
 * Never hard-blocks on IP alone (corporate NAT / CGNAT / VPN safe).
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Rate_Limiter
 */
class ATG_Rate_Limiter {

	/**
	 * Check the request against the buckets.
	 *
	 * @param array  $decision Classifier decision (may be modified).
	 * @param string $kind     'human' (anonymous) or 'bot'.
	 * @return array Decision possibly upgraded to throttle/block.
	 */
	public function check( $decision, $kind = 'human' ) {
		$plugin = ATG_Plugin::instance();
		if ( ! $plugin->get( 'rate_enabled', true ) ) {
			return $decision;
		}

		$rpm   = 'bot' === $kind ? (int) $plugin->get( 'rate_bot_rpm', 20 ) : (int) $plugin->get( 'rate_human_rpm', 120 );
		$burst = (int) $plugin->get( 'rate_burst', 30 );
		$limit = $rpm + $burst;

		$session_key = ! empty( $decision['session'] ) ? 's_' . md5( $decision['session'] ) : '';
		$ip_key      = 'i_' . md5( $decision['ip'] );

		// Session bucket (primary).
		if ( $session_key ) {
			$s_count = $this->hit( $session_key );
			if ( $s_count <= $limit ) {
				return $decision; // Within session allowance.
			}
		}

		// IP bucket (secondary, more generous to protect shared networks).
		$i_count = $this->hit( $ip_key );
		$ip_soft = $limit * ( 'bot' === $kind ? 3 : 10 );

		if ( $i_count > $ip_soft ) {
			// Cool-off block: temporary, self-expiring with the window.
			$decision['action']      = 'block';
			$decision['reason']      = 'Rate limit exceeded (cool-off)';
			$decision['status_code'] = 429;
			$decision['risk']        = max( (int) $decision['risk'], 80 );
			return $decision;
		}

		// Session exceeded but IP within soft cap → throttle only.
		if ( $session_key ) {
			$decision['action']      = 'throttle';
			$decision['reason']      = 'Session rate limit exceeded';
			$decision['status_code'] = 429;
		}
		return $decision;
	}

	/**
	 * Increment a 60-second bucket and return its count.
	 *
	 * @param string $key Bucket key.
	 * @return int
	 */
	private function hit( $key ) {
		$t   = 'atg_rl_' . $key;
		$val = get_transient( $t );
		if ( false === $val ) {
			set_transient( $t, 1, 60 );
			return 1;
		}
		$val = (int) $val + 1;
		set_transient( $t, $val, 60 );
		return $val;
	}
}
