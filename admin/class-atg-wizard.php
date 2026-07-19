<?php
/**
 * Guided Onboarding Setup Wizard.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Wizard
 */
class ATG_Wizard {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Register setup wizard page.
	 */
	public function register_page() {
		add_submenu_page(
			null, // Hidden from standard menus
			__( 'Setup Wizard', 'ai-traffic-guardian' ),
			__( 'Setup Wizard', 'ai-traffic-guardian' ),
			'manage_options',
			'atg-wizard',
			array( $this, 'render' )
		);
	}

	/**
	 * Auto-redirect new users to setup wizard on activation.
	 */
	public function maybe_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only redirect if onboarding is not completed and we are not already on the wizard page.
		if ( ! get_option( 'atg_onboarding_completed' ) ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'atg-wizard' !== $page && ( isset( $_GET['page'] ) && 0 === strpos( $_GET['page'], 'atg-' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				wp_safe_redirect( admin_url( 'admin.php?page=atg-wizard' ) );
				exit;
			}
		}
	}

	/**
	 * Render the Setup Wizard view.
	 */
	public function render() {
		if ( isset( $_POST['atg_wizard_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['atg_wizard_nonce'] ), 'atg_wizard_save' ) ) {
			// Save selection.
			$preset = isset( $_POST['preset'] ) ? sanitize_key( $_POST['preset'] ) : 'publisher';
			$cf     = isset( $_POST['cloudflare'] ) ? 'yes' === $_POST['cloudflare'] : false;

			update_option( 'atg_preset', $preset );
			update_option( 'atg_cloudflare_detected', $cf );
			if ( 'headless' === $preset && isset( $_POST['custom_namespace'] ) ) {
				update_option( 'atg_custom_rest_namespace', sanitize_text_field( wp_unslash( $_POST['custom_namespace'] ) ) );
			}
			update_option( 'atg_onboarding_completed', true );

			// Activate shadow mode for 7 days.
			$settings = get_option( 'atg_settings', array() );
			$settings['enforcement'] = 'shadow';
			$settings['shadow_started'] = time();
			update_option( 'atg_settings', $settings );

			wp_safe_redirect( admin_url( 'admin.php?page=atg-dashboard' ) );
			exit;
		}

		// Detect Cloudflare header.
		$cf_detected = isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		?>
		<div class="wrap" style="max-width: 600px; margin: 50px auto; padding: 30px; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
			<h2 style="text-align: center; margin-bottom: 20px; font-weight: 700; color: #1e293b;">
				<?php esc_html_e( 'Welcome to Bot Shield Pro', 'ai-traffic-guardian' ); ?>
			</h2>
			<p style="text-align: center; color: #64748b; margin-bottom: 30px;">
				<?php esc_html_e( 'Configure your defense shield in 3 simple steps.', 'ai-traffic-guardian' ); ?>
			</p>

			<form method="POST">
				<?php wp_nonce_field( 'atg_wizard_save', 'atg_wizard_nonce' ); ?>

				<!-- Step 1: Site Type -->
				<div style="margin-bottom: 25px;">
					<h3 style="font-weight: 600; color: #334155; margin-bottom: 10px;"><?php esc_html_e( '1. What kind of site is this?', 'ai-traffic-guardian' ); ?></h3>
					<select name="preset" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #cbd5e1;">
						<option value="publisher"><?php esc_html_e( 'Content Publisher (maximize AI citation & search)', 'ai-traffic-guardian' ); ?></option>
						<option value="woocommerce"><?php esc_html_e( 'WooCommerce Store (protect checkout & speed)', 'ai-traffic-guardian' ); ?></option>
						<option value="private"><?php esc_html_e( 'Private / Membership (block all scraping)', 'ai-traffic-guardian' ); ?></option>
						<option value="headless"><?php esc_html_e( 'Headless CMS (API-first backend)', 'ai-traffic-guardian' ); ?></option>
					</select>
					<div id="headless-namespace-field" style="margin-top: 10px; display: none;">
						<label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 5px;"><?php esc_html_e( 'Custom REST Namespace (optional):', 'ai-traffic-guardian' ); ?></label>
						<input type="text" name="custom_namespace" placeholder="e.g. my-api/v1" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;" />
					</div>
				</div>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					var select = document.querySelector('select[name="preset"]');
					var field = document.getElementById('headless-namespace-field');
					if (select && field) {
						select.addEventListener('change', function() {
							field.style.display = (this.value === 'headless') ? 'block' : 'none';
						});
					}
				});
				</script>

				<!-- Step 2: Cloudflare Detection -->
				<div style="margin-bottom: 25px;">
					<h3 style="font-weight: 600; color: #334155; margin-bottom: 10px;"><?php esc_html_e( '2. Are you behind Cloudflare?', 'ai-traffic-guardian' ); ?></h3>
					<div style="padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
						<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #475569;">
							<input type="radio" name="cloudflare" value="yes" <?php checked( $cf_detected ); ?>>
							<?php esc_html_e( 'Yes, my traffic routing runs through Cloudflare.', 'ai-traffic-guardian' ); ?>
						</label>
						<label style="display: block; font-weight: 500; color: #475569;">
							<input type="radio" name="cloudflare" value="no" <?php checked( ! $cf_detected ); ?>>
							<?php esc_html_e( 'No, I use standard web hosting or another CDN.', 'ai-traffic-guardian' ); ?>
						</label>
					</div>
				</div>

				<!-- Step 3: Observation Notice -->
				<div style="margin-bottom: 30px;">
					<h3 style="font-weight: 600; color: #334155; margin-bottom: 10px;"><?php esc_html_e( '3. Shadow Mode Active', 'ai-traffic-guardian' ); ?></h3>
					<p style="color: #475569; line-height: 1.5; font-size: 13px;">
						<?php esc_html_e( 'For the first 7 days, the plugin will run in Shadow Mode. It will log traffic patterns and compile your Bot Audit Report without blocking real users. You can switch to Active enforcement at any time.', 'ai-traffic-guardian' ); ?>
					</p>
				</div>

				<button type="submit" class="button button-primary button-large" style="width: 100%; padding: 12px; height: auto; font-size: 16px; font-weight: 600;">
					<?php esc_html_e( 'Complete Setup & Enter Dashboard', 'ai-traffic-guardian' ); ?>
				</button>
			</form>
		</div>
		<?php
	}
}
