<?php
/**
 * Alert system: "New AI bot detected" notifications inside wp-admin, with
 * optional email digest. De-duplicated per UA.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Alerts
 */
class ATG_Alerts {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Create an alert (deduped by type + UA hash).
	 *
	 * @param string $type    Alert type.
	 * @param string $title   Human title.
	 * @param array  $payload Extra data (ua, ip, path…).
	 */
	public function create( $type, $title, $payload = array() ) {
		global $wpdb;
		$plugin = ATG_Plugin::instance();
		if ( ! $plugin->get( 'alert_new_bot', true ) ) {
			return;
		}
		if ( ! ATG_Licensing::atg_is_pro() ) {
			$first_of_month = gmdate( 'Y-m-01 00:00:00' );
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM " . ATG_DB::table( 'alerts' ) . " WHERE created >= %s",
					$first_of_month
				)
			);
			if ( $count >= 20 ) {
				return;
			}
		}
		$ua  = isset( $payload['ua'] ) ? (string) $payload['ua'] : '';
		$key = 'atg_alert_' . md5( $type . '|' . $ua );
		if ( get_transient( $key ) ) {
			return; // Already alerted recently for this signature.
		}
		set_transient( $key, 1, WEEK_IN_SECONDS );

		$wpdb->insert(
			ATG_DB::table( 'alerts' ),
			array(
				'created' => current_time( 'mysql' ),
				'type'    => sanitize_key( $type ),
				'title'   => sanitize_text_field( $title ),
				'payload' => wp_json_encode( $payload ),
				'status'  => 'open',
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		$is_staging = $plugin->get( 'staging_mode', false ) || ( defined( 'WP_ENV' ) && 'staging' === WP_ENV ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		if ( $plugin->get( 'alert_email', false ) && ! $is_staging ) {
			$to      = get_option( 'admin_email' );
			$subject = sprintf( /* translators: %s alert title */ __( '[Bot Shield Pro] %s', 'ai-traffic-guardian' ), $title );
			$body    = $title . "\n\n" . print_r( $payload, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			wp_mail( $to, $subject, $body );
		}

		$webhook_url = $plugin->get( 'webhook_url', '' );
		if ( ! empty( $webhook_url ) && ! $is_staging ) {
			wp_remote_post(
				$webhook_url,
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'text'    => $title,
							'type'    => $type,
							'payload' => $payload,
						)
					),
					'timeout' => 5,
				)
			);
		}
	}

	/**
	 * List alerts.
	 *
	 * @param string $status open|dismissed|all.
	 * @param int    $limit  Max rows.
	 * @return array
	 */
	public function list( $status = 'open', $limit = 100 ) {
		global $wpdb;
		if ( 'all' === $status ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ATG_DB::table( 'alerts' ) . ' ORDER BY id DESC LIMIT %d', (int) $limit ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ATG_DB::table( 'alerts' ) . ' WHERE status = %s ORDER BY id DESC LIMIT %d', $status, (int) $limit ), ARRAY_A );
	}

	/**
	 * Dismiss an alert.
	 *
	 * @param int $id Alert ID.
	 */
	public function dismiss( $id ) {
		global $wpdb;
		$wpdb->update( ATG_DB::table( 'alerts' ), array( 'status' => 'dismissed' ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Count open alerts.
	 *
	 * @return int
	 */
	public function open_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ATG_DB::table( 'alerts' ) . ' WHERE status = %s', 'open' ) );
	}

	/**
	 * Floating admin notice on plugin screens when open alerts exist.
	 */
	public function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'atg' ) ) {
			return;
		}
		$count = $this->open_count();
		if ( $count < 1 ) {
			return;
		}
		$url = admin_url( 'admin.php?page=atg-alerts' );
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d number of alerts */
					_n( 'Bot Shield Pro detected %d new bot signature in your traffic.', 'Bot Shield Pro detected %d new bot signatures in your traffic.', $count, 'ai-traffic-guardian' ),
					$count
				)
			),
			esc_url( $url ),
			esc_html__( 'Review alerts', 'ai-traffic-guardian' )
		);
	}
}

