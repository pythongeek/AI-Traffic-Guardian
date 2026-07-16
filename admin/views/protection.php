<?php
/**
 * Forms & checkout protection view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-protection" data-atg-settings-form>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Form protection (accessibility-first)', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Honeypot fields are hidden from assistive technology, skipped in keyboard navigation, and ignored by password managers. Logged-in users always pass.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="honeypot_enabled" />
				<span><?php esc_html_e( 'Enable honeypot protection', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="protect_comments" />
				<span><?php esc_html_e( 'Comments & product reviews', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="protect_registration" />
				<span><?php esc_html_e( 'Registration form', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="protect_login" />
				<span><?php esc_html_e( 'Login form (optional — most sites leave this off)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="protect_woocommerce" />
				<span><?php esc_html_e( 'WooCommerce checkout', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="timing_checks" />
				<span><?php esc_html_e( 'Timing checks (rejects impossibly-fast submissions)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<p class="description atg-inline-note"><?php esc_html_e( 'Timing checks stay off by default: they can flag users with autofill, password managers, or cognitive disabilities. Enable only if spam volume justifies it.', 'ai-traffic-guardian' ); ?></p>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Comment failure action', 'ai-traffic-guardian' ); ?></span>
				<select data-setting="comment_fail_action">
					<option value="moderate"><?php esc_html_e( 'Hold for moderation (recommended)', 'ai-traffic-guardian' ); ?></option>
					<option value="block"><?php esc_html_e( 'Reject submission', 'ai-traffic-guardian' ); ?></option>
				</select>
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'WooCommerce card-testing defense', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Velocity ladder on anonymous checkout attempts. Logged-in customers are never gated.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Max checkout attempts', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="1" max="50" data-setting="woo_max_attempts" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Per time window (minutes)', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="1" max="120" data-setting="woo_window_min" />
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Cloudflare Turnstile escalation (optional)', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Invisible, privacy-friendly challenge used only when honeypot signals are inconclusive. Leave keys empty to keep it disabled.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="turnstile_enabled" />
				<span><?php esc_html_e( 'Enable Turnstile escalation', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Site key', 'ai-traffic-guardian' ); ?></span>
				<input type="text" class="regular-text" data-setting="turnstile_site_key" autocomplete="off" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Secret key', 'ai-traffic-guardian' ); ?></span>
				<input type="password" class="regular-text" data-setting="turnstile_secret" autocomplete="new-password" />
			</label>
		</div>
	</div>

	<p><button class="button button-primary button-hero" data-atg-save-settings><?php esc_html_e( 'Save protection settings', 'ai-traffic-guardian' ); ?></button></p>
</div>
