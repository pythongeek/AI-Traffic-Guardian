<?php
/**
 * Pro Tier Licensing Gate manager.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Licensing
 */
class ATG_Licensing {

	/**
	 * Hook initialization.
	 */
	public static function init() {
		add_action( 'atg_cron_daily', array( __CLASS__, 'daily_license_check' ) );
	}

	/**
	 * Check if the active license is valid Pro (with grace period support).
	 *
	 * @return bool True if active Pro or inside grace period.
	 */
	public static function is_pro() {
		$license_data = get_option( 'atg_license_data', array() );

		if ( empty( $license_data ) || ! isset( $license_data['license_key'] ) ) {
			return false;
		}

		if ( 'active' === $license_data['status'] ) {
			// Check expiration date.
			if ( ! empty( $license_data['expires_at'] ) && time() > strtotime( $license_data['expires_at'] ) ) {
				// Expired. Check if we are within the 14-day grace window.
				$failed_at = isset( $license_data['failed_at'] ) ? (int) $license_data['failed_at'] : strtotime( $license_data['expires_at'] );
				if ( ( time() - $failed_at ) <= 14 * DAY_IN_SECONDS ) {
					return true; // Grace period active
				}
				return false;
			}
			return true;
		}

		// If status is failed/revoked, check grace period.
		if ( isset( $license_data['failed_at'] ) && $license_data['failed_at'] > 0 ) {
			if ( ( time() - (int) $license_data['failed_at'] ) <= 14 * DAY_IN_SECONDS ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Single capability check function.
	 *
	 * @return bool
	 */
	public static function atg_is_pro() {
		return self::is_pro();
	}

	/**
	 * Perform the external license check.
	 *
	 * TODO: PLACEHOLDER — Replace this mock with a real license server API call.
	 * Currently, any key starting with "BSPRO-" is accepted as a valid Pro
	 * license. When a real license server is available, replace the body of
	 * this method with a wp_safe_remote_post() call to your API endpoint.
	 *
	 * @param string $key License key.
	 * @return array Check results.
	 */
	public static function verify_with_server( $key ) {
		// ──────────────────────────────────────────────────────────────
		// PLACEHOLDER: Local-only license validation.
		// Replace with: wp_safe_remote_post( 'https://your-license-server.com/verify', ... )
		// when a real license API is available.
		// ──────────────────────────────────────────────────────────────
		$is_valid = ( 0 === strpos( $key, 'BSPRO-' ) );

		if ( $is_valid ) {
			return array(
				'license_key'  => $key,
				'status'       => 'active',
				'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS ),
				'last_checked' => gmdate( 'Y-m-d H:i:s' ),
				'failed_at'    => 0,
			);
		} else {
			return array(
				'license_key'  => $key,
				'status'       => 'expired',
				'expires_at'   => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'last_checked' => gmdate( 'Y-m-d H:i:s' ),
				'failed_at'    => time(),
			);
		}
	}

	/**
	 * Update the license key and perform initial check.
	 *
	 * @param string $key License key.
	 * @return bool Status.
	 */
	public static function update_license( $key ) {
		$key = sanitize_text_field( trim( $key ) );
		if ( empty( $key ) ) {
			delete_option( 'atg_license_data' );
			return false;
		}

		$data = self::verify_with_server( $key );
		update_option( 'atg_license_data', $data );

		return 'active' === $data['status'];
	}

	/**
	 * Daily license check handler.
	 */
	public static function daily_license_check() {
		$license_data = get_option( 'atg_license_data', array() );
		if ( empty( $license_data ) || ! isset( $license_data['license_key'] ) ) {
			return;
		}

		// Perform remote check
		$new_data = self::verify_with_server( $license_data['license_key'] );

		// If it failed due to a network error, preserve the old status but record the fail time if not already set.
		if ( 'active' !== $new_data['status'] ) {
			if ( empty( $license_data['failed_at'] ) ) {
				$license_data['failed_at'] = time();
			}
			$license_data['last_checked'] = gmdate( 'Y-m-d H:i:s' );
			update_option( 'atg_license_data', $license_data );
		} else {
			update_option( 'atg_license_data', $new_data );
		}
	}
}
