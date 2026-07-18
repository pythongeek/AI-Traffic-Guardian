<?php
/**
 * Form & checkout integrity module — accessibility-first.
 *
 *  - Honeypot fields: aria-hidden, tabindex=-1, autocomplete=off, off-screen
 *    CSS (never display:none patterns that trip password managers/AT).
 *  - Timing checks: OFF by default (WCAG risk), optional.
 *  - Confirmed-human sessions (via accessible beacon) skip further checks.
 *  - Optional Turnstile escalation when signals are inconclusive.
 *  - WooCommerce checkout velocity protection with logged-in exemption.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Form_Guard
 */
class ATG_Form_Guard {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		$plugin = ATG_Plugin::instance();
		if ( ! $plugin->get( 'honeypot_enabled', true ) ) {
			return;
		}

		// Inject honeypots.
		if ( $plugin->get( 'protect_comments', true ) ) {
			add_filter( 'comment_form_defaults', array( $this, 'inject_comment_field' ) );
			add_filter( 'preprocess_comment', array( $this, 'check_comment' ), 1 );
		}
		if ( $plugin->get( 'protect_registration', true ) ) {
			add_action( 'register_form', array( $this, 'render_field' ) );
			add_filter( 'registration_errors', array( $this, 'check_registration' ), 1 );
		}
		if ( $plugin->get( 'protect_login', false ) ) {
			add_action( 'login_form', array( $this, 'render_field' ) );
			add_filter( 'authenticate', array( $this, 'check_login' ), 30, 3 );
		}
		if ( $plugin->get( 'protect_woocommerce', true ) && class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_field' ) );
			add_action( 'woocommerce_checkout_process', array( $this, 'check_checkout' ), 1 );
			// Review spam (product reviews use the comment pipeline, covered above).
		}

		// Contact Form 7
		if ( class_exists( 'WPCF7' ) && $plugin->get( 'protect_cf7', true ) ) {
			add_filter( 'wpcf7_form_elements', array( $this, 'inject_cf7_field' ) );
			add_action( 'wpcf7_before_send_mail', array( $this, 'check_cf7' ), 1 );
		}

		// Gravity Forms
		if ( class_exists( 'GFCommon' ) && $plugin->get( 'protect_gravityforms', true ) ) {
			add_filter( 'gform_form_tag', array( $this, 'inject_gravityforms_field' ), 10, 2 );
			add_filter( 'gform_validation', array( $this, 'check_gravityforms' ) );
		}

		// WPForms
		if ( class_exists( 'WPForms' ) && $plugin->get( 'protect_wpforms', true ) ) {
			add_action( 'wpforms_display_field_before', array( $this, 'inject_wpforms_field' ), 10, 2 );
			add_filter( 'wpforms_process', array( $this, 'check_wpforms' ), 10, 3 );
		}

		add_action( 'wp_head', array( $this, 'honeypot_css' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_beacon' ) );
	}

	/**
	 * Enqueue the accessible human-confirmation beacon so form checks can
	 * trust sessions with observed natural interaction (pointer, key,
	 * scroll, touch). Independent of the analytics module.
	 */
	public function enqueue_beacon() {
		if ( is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script( 'atg-frontend', ATG_PLUGIN_URL . 'assets/js/frontend.js', array(), ATG_VERSION, true );
		wp_localize_script(
			'atg-frontend',
			'ATG_FRONT',
			array(
				'beacon' => esc_url_raw( rest_url( 'atg/v1/beacon' ) ),
			)
		);
	}

	/**
	 * CSS that hides the honeypot accessibly (off-screen, focusable-never).
	 */
	public function honeypot_css() {
		echo '<style>.atg-hp{position:absolute!important;left:-9999px!important;top:-9999px!important;height:1px;width:1px;overflow:hidden;}</style>' . "\n";
	}

	/**
	 * Render the honeypot field (accessibility-hardened).
	 */
	public function render_field() {
		$plugin = ATG_Plugin::instance();
		$token  = $this->issue_token();
		echo '<p class="atg-hp" aria-hidden="true">';
		echo '<label>' . esc_html__( 'Leave this field empty', 'ai-traffic-guardian' );
		echo '<input type="text" name="atg_hp" value="" autocomplete="off" tabindex="-1" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" />';
		echo '</label>';
		echo '<input type="hidden" name="atg_tok" value="' . esc_attr( $token ) . '" />';
		if ( $plugin->get( 'timing_checks', false ) ) {
			echo '<input type="hidden" name="atg_ts" value="' . esc_attr( (string) time() ) . '" />';
		}
		echo '</p>';
	}

	/**
	 * Add the honeypot to comment forms.
	 *
	 * @param array $defaults Comment form defaults.
	 * @return array
	 */
	public function inject_comment_field( $defaults ) {
		ob_start();
		$this->render_field();
		$field = ob_get_clean();
		$defaults['comment_field'] = ( isset( $defaults['comment_field'] ) ? $defaults['comment_field'] : '' ) . $field;
		return $defaults;
	}

	/**
	 * Issue a signed token bound to the session cookie (stateless).
	 *
	 * @return string
	 */
	private function issue_token() {
		$session = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : 'none';
		return hash_hmac( 'sha256', 'form|' . $session, wp_salt( 'nonce' ) );
	}

	/**
	 * Core verification. Returns true when the submission looks human.
	 * Fail-open for logged-in users and confirmed-human sessions.
	 *
	 * @return bool
	 */
	private function passes() {
		$plugin = ATG_Plugin::instance();

		// Logged-in users are always trusted (P0).
		if ( is_user_logged_in() ) {
			return true;
		}

		// Confirmed-human session (accessible beacon) skips everything else.
		$session = isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : '';
		if ( $session && get_transient( 'atg_human_' . md5( $session ) ) ) {
			return true;
		}

		// 1. Honeypot must be empty.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hp = isset( $_POST['atg_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['atg_hp'] ) ) : null;
		if ( null === $hp || '' !== trim( $hp ) ) {
			return false; // Field missing (non-standard form post) or filled by a bot.
		}

		// 2. Token must validate.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tok = isset( $_POST['atg_tok'] ) ? sanitize_text_field( wp_unslash( $_POST['atg_tok'] ) ) : '';
		if ( ! hash_equals( $this->issue_token(), $tok ) ) {
			return false;
		}

		// 3. Optional timing check (default OFF for accessibility).
		if ( $plugin->get( 'timing_checks', false ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ts  = isset( $_POST['atg_ts'] ) ? (int) $_POST['atg_ts'] : 0;
			$min = (int) $plugin->get( 'min_seconds', 3 );
			if ( $ts > 0 && ( time() - $ts ) < $min ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Comment / review submissions.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public function check_comment( $commentdata ) {
		if ( ! $this->passes() ) {
			$plugin = ATG_Plugin::instance();
			$this->log_fail( 'comment' );
			if ( 'moderate' === $plugin->get( 'comment_fail_action', 'moderate' ) ) {
				// Soft action: force moderation instead of hard-blocking.
				add_filter( 'pre_comment_approved', function () {
					return 0;
				}, 99 );
				return $commentdata;
			}
			wp_die(
				esc_html__( 'Your submission could not be verified as human. Please go back and try again.', 'ai-traffic-guardian' ),
				esc_html__( 'Submission held', 'ai-traffic-guardian' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
		return $commentdata;
	}

	/**
	 * Registration submissions.
	 *
	 * @param WP_Error $errors Registration errors.
	 * @return WP_Error
	 */
	public function check_registration( $errors ) {
		if ( ! $this->passes() ) {
			$this->log_fail( 'registration' );
			$errors->add( 'atg_verify', __( 'We could not verify this registration as human. Please try again.', 'ai-traffic-guardian' ) );
		}
		return $errors;
	}

	/**
	 * Optional login-form protection (default off).
	 *
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function check_login( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}
		if ( ! $this->passes() ) {
			$this->log_fail( 'login' );
			return new WP_Error( 'atg_verify', __( 'Login verification failed. Please reload the page and try again.', 'ai-traffic-guardian' ) );
		}
		return $user;
	}

	/**
	 * WooCommerce checkout: honeypot + velocity ladder.
	 */
	public function check_checkout() {
		$plugin = ATG_Plugin::instance();
		if ( is_user_logged_in() ) {
			return; // Account-holding customers are never gated.
		}

		// Velocity ladder (card-testing defense).
		$ip      = ATG_Plugin::client_ip();
		$key     = 'atg_wc_' . md5( $ip );
		$tries   = (int) get_transient( $key );
		$max     = (int) $plugin->get( 'woo_max_attempts', 5 );
		$window  = (int) $plugin->get( 'woo_window_min', 10 ) * MINUTE_IN_SECONDS;
		set_transient( $key, $tries + 1, $window );

		if ( $tries >= $max ) {
			$this->log_fail( 'checkout_velocity' );
			wc_add_notice( __( 'Too many checkout attempts. Please wait a few minutes before trying again.', 'ai-traffic-guardian' ), 'error' );
			return;
		}

		if ( ! $this->passes() ) {
			$this->log_fail( 'checkout' );
			wc_add_notice( __( 'We could not verify your checkout as human. Please reload the page and try again — your cart is safe.', 'ai-traffic-guardian' ), 'error' );
		}
	}

	/**
	 * Record a form-protection failure in the traffic log.
	 *
	 * @param string $context comment|registration|login|checkout|checkout_velocity.
	 */
	private function log_fail( $context ) {
		ATG_Plugin::instance()->logger->log(
			array(
				'ip'             => ATG_Plugin::client_ip(),
				'ua'             => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '',
				'path'           => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'method'         => 'POST',
				'classification' => 'form_abuse',
				'vendor'         => '',
				'purpose'        => 'scraper',
				'bot_name'       => '',
				'verified'       => null,
				'spoofed'        => false,
				'action'         => 'block',
				'reason'         => 'Form protection failed: ' . $context,
				'risk'           => 75,
				'is_auth'        => is_user_logged_in(),
				'session'        => isset( $_COOKIE['atg_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['atg_sid'] ) ) : '',
			)
		);
	}

	public function inject_cf7_field( $html ) {
		ob_start();
		$this->render_field();
		return $html . ob_get_clean();
	}

	public function check_cf7( $cf7 ) {
		if ( ! $this->passes() ) {
			$this->log_fail( 'cf7' );
			add_filter( 'wpcf7_spam', '__return_true' );
		}
	}

	public function inject_gravityforms_field( $tag, $form ) {
		ob_start();
		$this->render_field();
		return $tag . ob_get_clean();
	}

	public function check_gravityforms( $validation_result ) {
		if ( ! $this->passes() ) {
			$this->log_fail( 'gravityforms' );
			$validation_result['is_valid'] = false;
		}
		return $validation_result;
	}

	public function inject_wpforms_field( $field, $form ) {
		static $injected = false;
		if ( ! $injected && (int) $field['id'] === (int) array_key_first( $form['fields'] ) ) {
			$this->render_field();
			$injected = true;
		}
	}

	public function check_wpforms( $fields, $entry, $form ) {
		if ( ! $this->passes() ) {
			$this->log_fail( 'wpforms' );
			wpforms()->get( 'process' )->errors[ $form['id'] ]['footer'] = __( 'Submission could not be verified.', 'ai-traffic-guardian' );
		}
		return $fields;
	}
}
