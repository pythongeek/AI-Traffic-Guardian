<?php
/**
 * Cloudflare Edge Push Integration.
 * Synchronizes blocked bot IPs and spoofing attempts to a Cloudflare IP List.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Cloudflare
 */
class ATG_Cloudflare {

	/**
	 * Register actions and filters.
	 */
	public function hooks() {
		add_action( 'atg_request_classified', array( $this, 'maybe_push_ip' ) );
		add_action( 'atg_cron_weekly', array( $this, 'sync_list' ) );
	}

	/**
	 * Inspect the decision and push blocked IPs to Cloudflare.
	 *
	 * @param array $decision Request decision array.
	 */
	public function maybe_push_ip( $decision ) {
		if ( ! ATG_Plugin::instance()->get( 'cloudflare_enabled', false ) ) {
			return;
		}

		if ( 'block' === $decision['action'] || ! empty( $decision['spoofed'] ) ) {
			if ( ! empty( $decision['ip'] ) ) {
				$this->add_ip_to_queue( $decision['ip'] );
			}
		}
	}

	/**
	 * Add an IP address to the synchronization queue and trigger instant push.
	 *
	 * @param string $ip Client IP.
	 */
	public function add_ip_to_queue( $ip ) {
		$ips = get_option( 'atg_cf_blocked_ips', array() );
		if ( ! is_array( $ips ) ) {
			$ips = array();
		}

		if ( in_array( $ip, $ips, true ) ) {
			return;
		}

		$ips[] = $ip;

		// Cap queue at 1000 entries.
		if ( count( $ips ) > 1000 ) {
			array_shift( $ips );
		}

		update_option( 'atg_cf_blocked_ips', $ips );
		$this->push_ip_to_cloudflare( $ip );
	}

	/**
	 * HTTP POST to push a single blocked IP to Cloudflare list.
	 *
	 * @param string $ip Blocked IP.
	 */
	private function push_ip_to_cloudflare( $ip ) {
		$token      = ATG_Plugin::instance()->get( 'cloudflare_api_token' );
		$account_id = ATG_Plugin::instance()->get( 'cloudflare_account_id' );
		$list_id    = ATG_Plugin::instance()->get( 'cloudflare_ip_list_id' );

		if ( ! $token || ! $account_id || ! $list_id ) {
			return;
		}

		$url = sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/rules/lists/%s/items', $account_id, $list_id );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						array(
							'ip'      => $ip,
							'comment' => sprintf( 'Blocked by ATG: %s', gmdate( 'Y-m-d H:i:s' ) ),
						),
					)
				),
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Bot Shield Pro: Cloudflare Edge Push failed: ' . $response->get_error_message() );
		}
	}

	/**
	 * Weekly full synchronization of the blocklist queue.
	 */
	public function sync_list() {
		$token      = ATG_Plugin::instance()->get( 'cloudflare_api_token' );
		$account_id = ATG_Plugin::instance()->get( 'cloudflare_account_id' );
		$list_id    = ATG_Plugin::instance()->get( 'cloudflare_ip_list_id' );

		if ( ! $token || ! $account_id || ! $list_id ) {
			return;
		}

		$ips = get_option( 'atg_cf_blocked_ips', array() );
		$ips = apply_filters( 'atg_cloudflare_push_ips', $ips );

		if ( empty( $ips ) ) {
			return;
		}

		$url   = sprintf( 'https://api.cloudflare.com/client/v4/accounts/%s/rules/lists/%s/items', $account_id, $list_id );
		$items = array();

		foreach ( $ips as $ip ) {
			$items[] = array(
				'ip'      => $ip,
				'comment' => 'Synced by ATG weekly cron',
			);
		}

		// Cloudflare accepts up to 1000 items in a single request.
		$chunks = array_chunk( $items, 1000 );
		foreach ( $chunks as $chunk ) {
			wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $chunk ),
					'timeout' => 15,
				)
			);
		}
	}
}

