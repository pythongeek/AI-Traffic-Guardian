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
	 * Check if the active license is valid Pro.
	 *
	 * @return bool True if active Pro.
	 */
	public static function is_pro() {
		$license = get_option( 'atg_license_key', '' );
		if ( empty( $license ) ) {
			return false;
		}

		$cached = get_transient( 'atg_license_status' );
		if ( false !== $cached ) {
			return 'valid' === $cached;
		}

		// Mock verification for premium styling.
		// If the key starts with "BSPRO-", treat it as valid.
		$is_valid = ( 0 === strpos( $license, 'BSPRO-' ) );
		set_transient( 'atg_license_status', $is_valid ? 'valid' : 'invalid', DAY_IN_SECONDS );

		return $is_valid;
	}

	/**
	 * Update the license key.
	 *
	 * @param string $key License key.
	 * @return bool Status.
	 */
	public static function update_license( $key ) {
		update_option( 'atg_license_key', sanitize_text_field( trim( $key ) ) );
		delete_transient( 'atg_license_status' );
		return self::is_pro();
	}
}
