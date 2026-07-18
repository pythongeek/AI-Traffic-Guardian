<?php
/**
 * Enforcer: applies (or only records, in shadow mode) the final decision.
 * Includes the one-click panic switch and gradual-enforcement behavior.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Enforcer
 */
class ATG_Enforcer {

	/**
	 * Apply the decision to the current request.
	 *
	 * @param array $decision Classifier decision.
	 */
	public function apply( $decision ) {
		$plugin = ATG_Plugin::instance();
		$mode   = $plugin->enforcement_mode();

		// Rate limiting applies to anonymous humans and allowed/throttled bots.
		$rate_enabled = (bool) apply_filters( 'atg_rate_limit_enabled', true, $decision );
		if ( $rate_enabled && in_array( $decision['classification'], array( 'human', 'unknown_bot' ), true ) ) {
			$kind     = 'human' === $decision['classification'] ? 'human' : 'bot';
			$decision = $plugin->rate_limiter->check( $decision, $kind );
		} elseif ( $rate_enabled && 'bot' === $decision['classification'] && 'block' !== $decision['action'] ) {
			$decision = $plugin->rate_limiter->check( $decision, 'bot' );
		}

		// Shadow mode: log everything, enforce nothing.
		if ( 'shadow' === $mode ) {
			$decision['enforced'] = false;
			$plugin->logger->log( $decision );
			$this->send_status_header( $decision, true );
			do_action( 'atg_request_enforced', $decision );
			return;
		}

		// Panic switch: enforcement off.
		if ( 'off' === $mode ) {
			$decision['enforced'] = false;
			$decision['action']   = 'allow';
			$plugin->logger->log( $decision );
			return;
		}

		// Active enforcement.
		$decision['enforced'] = true;
		$this->send_status_header( $decision, false );

		if ( 'block' === $decision['action'] ) {
			$plugin->logger->log( $decision );
			$this->render_block_page( $decision );
		}

		if ( 'throttle' === $decision['action'] && 429 === (int) $decision['status_code'] ) {
			$plugin->logger->log( $decision );
			$this->render_throttle( $decision );
		}

		// Soft-throttle: policy says throttle but the rate ceiling was not hit.
		// Add a short delay for bots so throttling has real cost, never for humans.
		if ( 'throttle' === $decision['action'] && 'bot' === $decision['classification'] ) {
			$delay_ms = (int) apply_filters( 'atg_throttle_delay_ms', 1200, $decision );
			if ( $delay_ms > 0 ) {
				usleep( min( $delay_ms, 5000 ) * 1000 );
			}
		}

		// Allowed or soft-throttled requests continue; log them.
		$plugin->logger->log( $decision );

		/**
		 * Fires after enforcement is applied (after allow/throttle/block).
		 *
		 * @param array $decision Full classification + enforcement array.
		 */
		do_action( 'atg_request_enforced', $decision );
	}

	/**
	 * Optional X-ATG header for cache layers / debugging.
	 *
	 * @param array $decision Decision.
	 * @param bool  $shadow   Whether shadow mode is on.
	 */
	private function send_status_header( $decision, $shadow ) {
		$plugin = ATG_Plugin::instance();
		if ( ! $plugin->get( 'send_status_header', true ) || headers_sent() ) {
			return;
		}
		$value = $decision['classification'] . '; action=' . $decision['action'] . ( $shadow ? '; shadow=1' : '' );
		header( 'X-ATG-Status: ' . $value, false );
	}

	/**
	 * 403 response for blocked traffic. Accessible, noindex, never cached.
	 *
	 * @param array $decision Decision.
	 */
	private function render_block_page( $decision ) {
		status_header( 403 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$ref = rawurlencode( substr( wp_generate_password( 8, false, false ), 0, 8 ) );
		wp_die(
			wp_kses_post(
				'<h1>' . __( 'Access temporarily restricted', 'ai-traffic-guardian' ) . '</h1>' .
				'<p>' . __( 'This request was identified as automated traffic and blocked by the site owner. If you are a human visitor and believe this is a mistake, please contact the site administrator and quote the reference below.', 'ai-traffic-guardian' ) . '</p>' .
				'<p><strong>' . esc_html__( 'Reference:', 'ai-traffic-guardian' ) . ' ATG-' . esc_html( $ref ) . '</strong></p>'
			),
			esc_html__( 'Access restricted', 'ai-traffic-guardian' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * 429 response for hard-throttled traffic.
	 *
	 * @param array $decision Decision.
	 */
	private function render_throttle( $decision ) {
		status_header( 429 );
		nocache_headers();
		header( 'Retry-After: 60', true );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		wp_die(
			wp_kses_post(
				'<h1>' . __( 'Slow down a little', 'ai-traffic-guardian' ) . '</h1>' .
				'<p>' . __( 'Too many requests in a short time. Please wait a minute and try again.', 'ai-traffic-guardian' ) . '</p>'
			),
			esc_html__( 'Too many requests', 'ai-traffic-guardian' ),
			array(
				'response' => 429,
				'exit'     => true,
			)
		);
	}
}
