<?php
/**
 * Custom bot signature storage and UI integration.
 * Allows admins to add bot signatures without writing PHP.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATG_Custom_Signatures {

	const OPTION = 'atg_custom_signatures';

	public function hooks() {
		add_filter( 'atg_bot_signatures', array( $this, 'merge_custom_signatures' ), 20 );
	}

	public function merge_custom_signatures( $sigs ) {
		$custom = $this->get_all();
		return array_merge( $sigs, $custom );
	}

	public function get_all() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public function add( $sig ) {
		$validated = $this->validate( $sig );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$sigs   = $this->get_all();
		$sigs[] = $validated;
		update_option( self::OPTION, $sigs );
		return true;
	}

	public function update( $index, $sig ) {
		$sigs = $this->get_all();
		if ( ! isset( $sigs[ $index ] ) ) {
			return new WP_Error( 'not_found', 'Signature not found' );
		}
		$validated = $this->validate( $sig );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$sigs[ $index ] = $validated;
		update_option( self::OPTION, $sigs );
		return true;
	}

	public function delete( $index ) {
		$sigs = $this->get_all();
		if ( ! isset( $sigs[ $index ] ) ) {
			return false;
		}
		array_splice( $sigs, $index, 1 );
		update_option( self::OPTION, $sigs );
		return true;
	}

	public function test_pattern( $pattern, $ua_samples ) {
		$matches = array();
		foreach ( (array) $ua_samples as $ua ) {
			if ( @preg_match( $pattern, $ua ) ) {
				$matches[] = $ua;
			}
		}
		return $matches;
	}

	private function validate( $sig ) {
		$purposes = array_keys( ATG_Bot_Database::purposes() );
		$required = array( 'name', 'vendor', 'purpose', 'pattern' );
		foreach ( $required as $field ) {
			if ( empty( $sig[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( "Field '%s' is required", $field ) );
			}
		}
		if ( ! in_array( $sig['purpose'], $purposes, true ) ) {
			return new WP_Error( 'invalid_purpose', sprintf( "Invalid purpose '%s'", $sig['purpose'] ) );
		}
		if ( @preg_match( $sig['pattern'], '' ) === false ) {
			return new WP_Error( 'invalid_pattern', 'Pattern is not a valid regex' );
		}
		return array(
			'name'        => sanitize_text_field( $sig['name'] ),
			'vendor'      => sanitize_text_field( $sig['vendor'] ),
			'purpose'     => sanitize_key( $sig['purpose'] ),
			'pattern'     => $sig['pattern'],
			'verify'      => in_array( $sig['verify'] ?? 'none', array( 'none', 'rdns', 'ip_range' ), true )
							? $sig['verify'] : 'none',
			'rdns_suffix' => isset( $sig['rdns_suffix'] ) ? array_map( 'sanitize_text_field', (array) $sig['rdns_suffix'] ) : array(),
			'ip_source'   => isset( $sig['ip_source'] ) ? esc_url_raw( $sig['ip_source'] ) : '',
			'custom'      => true,
		);
	}

	public function get() {
		return $this->get_all();
	}

	public function update_all( $sigs ) {
		$valid_sigs = array();
		foreach ( (array) $sigs as $sig ) {
			$validated = $this->validate( $sig );
			if ( ! is_wp_error( $validated ) ) {
				$valid_sigs[] = $validated;
			}
		}
		update_option( self::OPTION, $valid_sigs );
	}
}
